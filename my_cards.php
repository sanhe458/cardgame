<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 处理消息显示
$message = '';
$messageType = '';

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $messageType = 'success';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $messageType = 'error';
    unset($_SESSION['error']);
}

// 获取用户卡牌
$db = getDB();
$stmt = $db->prepare("SELECT id, card_id, name, rarity, image_url FROM " . DB_TABLE_USER_CARDS . " WHERE user_id = ? ORDER BY rarity DESC, id ASC");
$stmt->execute([$_SESSION['user_id']]);
$userCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 稀有度颜色映射
$rarityColors = [
    'common' => '#bdc3c7',
    'rare' => '#3498db',
    'epic' => '#9b59b6',
    'legendary' => '#f1c40f',
    'mythic' => '#e74c3c',
    // 数字稀有度映射
    '1' => '#bdc3c7',
    '2' => '#3498db',
    '3' => '#9b59b6',
    '4' => '#f1c40f',
    '5' => '#e74c3c'
];

// 稀有度中文名称
$rarityNames = [
    'common' => '普通',
    'rare' => '稀有',
    'epic' => '史诗',
    'legendary' => '传说',
    'mythic' => '神话',
    // 数字稀有度映射
    '1' => '普通',
    '2' => '稀有',
    '3' => '史诗',
    '4' => '传说',
    '5' => '神话'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 我的卡牌</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <style>
        .destroy-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .destroy-btn:hover {
            background-color: #c0392b;
        }
        
        .card-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>我的卡牌</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
                <a href="market.php" class="btn btn-secondary btn-lg">交易市场</a>
                <a href="sell_card.php" class="btn btn-primary btn-lg">出售卡牌</a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <div class="card fade-in-up">
            <h2 style="text-align: center;">我的卡牌 (<?= count($userCards) ?>张)</h2>
            
            <?php if (empty($userCards)): ?>
                <div class="message info">您还没有获得任何卡牌，请先去抽卡。</div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($userCards as $card): ?>
                        <div class="card-item">
                            <div class="card-image">
                                <img src="<?= htmlspecialchars($card['image_url']) ?>" alt="<?= htmlspecialchars($card['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="card-info">
                                <div class="card-name"><?= htmlspecialchars($card['name']) ?></div>
                                <div class="card-rarity rarity-<?= $card['rarity'] ?>">
                                    <?= $rarityNames[$card['rarity']] ?>
                                </div>
                                <div class="card-actions">
                                    <form method="post" action="destroy_card.php" style="display: inline;" onsubmit="return confirm('确定要销毁这张卡牌吗？此操作不可撤销！');">
                                        <input type="hidden" name="card_id" value="<?= htmlspecialchars($card['id']) ?>">
                                        <button type="submit" class="btn btn-error btn-sm">销毁</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>