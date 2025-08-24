<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// 获取数据库连接
$db = getDB();

// 处理操作请求
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove'])) {
        // 强制下架卡牌并从数据库中删除
        $listing_id = $_POST['listing_id'];
        $stmt = $db->prepare("DELETE FROM market_listings WHERE id = ?");
        $stmt->execute([$listing_id]);
        $message = '卡牌已强制下架并从数据库中删除。';
    } elseif (isset($_POST['toggle_maintenance'])) {
        // 切换维护模式
        $stmt = $db->prepare("SELECT value FROM system_settings WHERE key = 'market_maintenance'");
        $stmt->execute();
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($setting) {
            $new_value = $setting['value'] == '1' ? '0' : '1';
            $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE key = 'market_maintenance'");
            $stmt->execute([$new_value]);
        } else {
            $new_value = '1';
            $stmt = $db->prepare("INSERT INTO system_settings (key, value) VALUES ('market_maintenance', ?)");
            $stmt->execute([$new_value]);
        }
        
        $message = $new_value == '1' ? '交易市场已进入维护模式。' : '交易市场维护模式已解除。';
    }
}

// 检查维护模式状态
$stmt = $db->prepare("SELECT value FROM system_settings WHERE key = 'market_maintenance'");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);
$maintenance_mode = $setting ? $setting['value'] == '1' : false;

// 获取交易市场列表
$stmt = $db->prepare("SELECT m.id as listing_id, m.price, m.status, m.buyer_id, m.created_at, c.id as card_id, c.name as card_name, c.rarity, c.image_url, u.username as seller_name, u2.username as buyer_name FROM market_listings m JOIN " . DB_TABLE_CARDS . " c ON m.card_id = c.id JOIN " . DB_TABLE_USERS . " u ON m.seller_id = u.id LEFT JOIN " . DB_TABLE_USERS . " u2 ON m.buyer_id = u2.id ORDER BY m.created_at DESC");
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 管理交易市场</title>
    <!-- Bootstrap 5.1.3 CSS -->
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons 1.7.2 -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css">
    <link href="../styles.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .card-rarity-common { border: 2px solid #ccc; }
        .card-rarity-rare { border: 2px solid #007bff; }
        .card-rarity-epic { border: 2px solid #6f42c1; }
        .card-rarity-legendary { border: 2px solid #fd7e14; }
    </style>
</head>
<body>
    <!-- 侧边栏导航 -->
    <?php include 'sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="bi bi-shop me-2"></i>管理交易市场</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>返回后台
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0">管理功能</h3>
            </div>
            <div class="card-body">
                <form method="post">
                    <button type="submit" name="toggle_maintenance" class="btn <?= $maintenance_mode ? 'btn-success' : 'btn-warning' ?>">
                        <?= $maintenance_mode ? '解除维护模式' : '进入维护模式' ?>
                    </button>
                </form>
                <p class="mt-3 mb-0">
                    <?= $maintenance_mode ? '交易市场当前处于维护模式，用户无法访问。' : '交易市场当前正常运行。' ?>
                </p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">交易记录</h3>
            </div>
            <div class="card-body">
                <?php if (count($listings) > 0): ?>
                    <div class="row">
                        <?php foreach ($listings as $listing): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 card-rarity-<?= $listing['rarity'] ?>">
                                    <img src="<?= $listing['image_url'] ?>" alt="<?= $listing['card_name'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= $listing['card_name'] ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-primary"><?= ucfirst($listing['rarity']) ?></span>
                                        </div>
                                        <div class="mb-1"><strong>售价:</strong> <?= $listing['price'] ?> 狗头币</div>
                                        <div class="mb-1"><strong>卖家:</strong> <?= $listing['seller_name'] ?></div>
                                        <div class="mb-1"><strong>买家:</strong> <?= $listing['buyer_name'] ? $listing['buyer_name'] : '无' ?></div>
                                        <div class="mb-1"><strong>状态:</strong> <?= ucfirst($listing['status']) ?></div>
                                        <div class="mb-3"><strong>上架时间:</strong> <?= date('Y-m-d H:i', strtotime($listing['created_at'])) ?></div>
                                        <?php if ($listing['status'] == 'active'): ?>
                                            <form method="post" class="mt-auto">
                                                <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                                <button type="submit" name="remove" class="btn btn-danger btn-sm">强制下架</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>暂无交易记录。</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5.1.3 JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>