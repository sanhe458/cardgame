<?php
require_once 'config.php';
require_once 'functions.php';

$message = '';
$error = '';
$systemCheck = [];

// 环境检测
$systemCheck['php_version'] = version_compare(PHP_VERSION, '7.0.0', '>=');
$systemCheck['pdo_sqlite'] = extension_loaded('pdo_sqlite');
$systemCheck['writable_dir'] = is_writable(dirname(DB_FILE));
$systemCheck['writable_db'] = is_writable(DB_FILE) || (!file_exists(DB_FILE) && is_writable(dirname(DB_FILE)));

$systemCheck['all_passed'] = $systemCheck['php_version'] && $systemCheck['pdo_sqlite'] && $systemCheck['writable_dir'] && $systemCheck['writable_db'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $systemCheck['all_passed']) {
    try {
        // 初始化数据库
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建用户表
        $db->exec("CREATE TABLE IF NOT EXISTS " . DB_TABLE_USERS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            nickname TEXT,
            is_admin BOOLEAN DEFAULT 0,
            admin_level INTEGER DEFAULT 0,
            coins INTEGER DEFAULT 0,
            draw_count INTEGER DEFAULT 0,
            selected_gacha_pool_id INTEGER DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建卡牌表
        $db->exec("CREATE TABLE IF NOT EXISTS " . DB_TABLE_CARDS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            rarity INTEGER NOT NULL, -- 1:普通, 2:稀有, 3:史诗, 4:传说
            image_url TEXT,
            probability FLOAT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建抽卡记录表
        $db->exec("CREATE TABLE IF NOT EXISTS " . DB_TABLE_DRAWS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            card_id INTEGER NOT NULL,
            draw_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES " . DB_TABLE_USERS . " (id),
            FOREIGN KEY (card_id) REFERENCES " . DB_TABLE_CARDS . " (id)
        )");
        
        // 创建用户卡牌表（记录用户拥有的卡牌）
        $db->exec("CREATE TABLE IF NOT EXISTS " . DB_TABLE_USER_CARDS . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            card_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            rarity INTEGER NOT NULL, -- 1:普通, 2:稀有, 3:史诗, 4:传说
            image_url TEXT,
            acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES " . DB_TABLE_USERS . " (id)
        )");
        
        // 创建交易市场表
        $db->exec("CREATE TABLE IF NOT EXISTS market_listings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id INTEGER NOT NULL,
            seller_id INTEGER NOT NULL,
            buyer_id INTEGER REFERENCES users(id),
            price INTEGER NOT NULL,
            status TEXT DEFAULT 'active', -- active, sold, cancelled, admin_removed
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (card_id) REFERENCES " . DB_TABLE_CARDS . " (id),
            FOREIGN KEY (seller_id) REFERENCES " . DB_TABLE_USERS . " (id)
        )");
        
        // 创建系统设置表
        $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT UNIQUE NOT NULL,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建用户签到表
        $db->exec("CREATE TABLE IF NOT EXISTS user_checkins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            checkin_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES " . DB_TABLE_USERS . "(id),
            UNIQUE(user_id, checkin_date)
        )");
        
        // 插入默认管理员账户
        $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT OR IGNORE INTO " . DB_TABLE_USERS . " (username, password_hash, nickname, is_admin, admin_level, coins, draw_count) VALUES (?, ?, ?, 1, 2, 10000, 100)");
        $stmt->execute([ADMIN_USERNAME, $adminPasswordHash, '管理员']);
        
        // 插入示例卡牌
        $sampleCards = [
            ['狗头人战士', 1, 'https://i.imgur.com/3d8fG9b.png', 50.0],
            ['狗头人法师', 1, 'https://i.imgur.com/4d8fG9b.png', 50.0],
            ['狗头人弓箭手', 1, 'https://i.imgur.com/5d8fG9b.png', 50.0],
            ['狗头人萨满', 2, 'https://i.imgur.com/6d8fG9b.png', 20.0],
            ['狗头人酋长', 2, 'https://i.imgur.com/7d8fG9b.png', 20.0],
            ['狗头人英雄', 3, 'https://i.imgur.com/8d8fG9b.png', 10.0],
            ['狗头人传奇', 4, 'https://i.imgur.com/9d8fG9b.png', 5.0],
            ['狗头人之神', 5, 'https://i.imgur.com/0d8fG9b.png', 1.0]
        ];
        
        foreach ($sampleCards as $card) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO " . DB_TABLE_CARDS . " (name, rarity, image_url, probability) VALUES (?, ?, ?, ?)");
            $stmt->execute($card);
        }
        
        // 插入默认系统设置
        $stmt = $db->prepare("INSERT OR IGNORE INTO system_settings (key, value) VALUES ('market_maintenance', '0')");
        $stmt->execute();
        
        // 初始化卡池数据库
        $gachaDb = new PDO('sqlite:' . __DIR__ . '/data/gacha.db');
        $gachaDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建卡池表
        $gachaDb->exec("CREATE TABLE IF NOT EXISTS gacha_pools (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建卡池卡片关联表
        $gachaDb->exec("CREATE TABLE IF NOT EXISTS pool_cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id INTEGER NOT NULL,
            card_id INTEGER NOT NULL,
            probability REAL NOT NULL,
            FOREIGN KEY (pool_id) REFERENCES gacha_pools(id),
            FOREIGN KEY (card_id) REFERENCES " . DB_TABLE_CARDS . "(id)
        )");
        
        // 插入默认卡池
        $stmt = $gachaDb->prepare("INSERT OR IGNORE INTO gacha_pools (name, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute(['新手卡池', '专为新手玩家设计的卡池，包含大量普通和稀有卡片', 1]);
        
        // 获取默认卡池ID
        $poolId = $gachaDb->lastInsertId();
        if (!$poolId) {
            $stmt = $gachaDb->prepare("SELECT id FROM gacha_pools WHERE name = ?");
            $stmt->execute(['新手卡池']);
            $poolId = $stmt->fetchColumn();
        }
        
        // 为默认卡池添加示例卡片
        $poolCards = [
            [1, 50.0],  // 狗头人战士
            [2, 50.0],  // 狗头人法师
            [3, 50.0],  // 狗头人弓箭手
            [4, 20.0],  // 狗头人萨满
            [5, 20.0],  // 狗头人酋长
            [6, 10.0],  // 狗头人英雄
            [7, 5.0],   // 狗头人传奇
            [8, 1.0]    // 狗头人之神
        ];
        
        foreach ($poolCards as $card) {
            $stmt = $gachaDb->prepare("INSERT OR IGNORE INTO pool_cards (pool_id, card_id, probability) VALUES (?, ?, ?)");
            $stmt->execute([$poolId, $card[0], $card[1]]);
        }
        
        $message = '数据库初始化成功！管理员账户已创建（用户名：' . ADMIN_USERNAME . '，密码：admin123）';
    } catch (Exception $e) {
        $error = '初始化失败: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 系统安装</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <style>
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card" style="max-width: 600px; margin: 50px auto;">
            <h2 style="text-align: center;">系统安装向导</h2>
            
            <?php if ($message): ?>
                <div class="message success"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3>环境检测</h3>
                <ul>
                    <li>PHP版本 >= 7.0.0: <span class="<?= $systemCheck['php_version'] ? 'text-success' : 'text-danger' ?>"><?= $systemCheck['php_version'] ? '通过' : '未通过' ?></span></li>
                    <li>PDO SQLite扩展: <span class="<?= $systemCheck['pdo_sqlite'] ? 'text-success' : 'text-danger' ?>"><?= $systemCheck['pdo_sqlite'] ? '通过' : '未通过' ?></span></li>
                    <li>数据库目录可写: <span class="<?= $systemCheck['writable_dir'] ? 'text-success' : 'text-danger' ?>"><?= $systemCheck['writable_dir'] ? '通过' : '未通过' ?></span></li>
                    <li>数据库文件可写: <span class="<?= $systemCheck['writable_db'] ? 'text-success' : 'text-danger' ?>"><?= $systemCheck['writable_db'] ? '通过' : '未通过' ?></span></li>
                </ul>
                
                <?php if ($systemCheck['all_passed']): ?>
                    <div class="message success">环境检测通过，可以进行安装。</div>
                <?php else: ?>
                    <div class="message error">环境检测未通过，请检查并修复问题后再进行安装。</div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>安装说明</h3>
                <p>点击下方按钮初始化数据库并创建必要的表结构。</p>
                <p>系统将自动创建管理员账户：</p>
                <ul>
                    <li>用户名：<?= ADMIN_USERNAME ?></li>
                    <li>密码：admin123</li>
                </ul>
                <p>请在安装完成后立即登录并修改密码。</p>
            </div>
            
            <form method="post" style="text-align: center; margin: 30px 0;">
                <?php if ($systemCheck['all_passed']): ?>
                    <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 12px 25px;">开始安装</button>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" style="font-size: 1.1rem; padding: 12px 25px;" disabled>环境检测未通过，无法安装</button>
                <?php endif; ?>
            </form>
            
            <div class="card">
                <h3>注意事项</h3>
                <ul>
                    <li>请确保数据库文件目录有写入权限</li>
                    <li>安装过程会清空现有数据，请谨慎操作</li>
                    <li>安装完成后请删除或重命名此文件以确保安全</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>