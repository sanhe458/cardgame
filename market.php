<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 获取数据库连接
$db = getDB();

// 获取用户信息
$stmt = $db->prepare("SELECT coins FROM " . DB_TABLE_USERS . " WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$coins = $user['coins'];

// 检查维护模式
$stmt = $db->prepare("SELECT value FROM system_settings WHERE key = 'market_maintenance'");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);
$maintenance_mode = $setting ? $setting['value'] == '1' : false;

// 获取交易市场列表
$stmt = $db->prepare("SELECT m.id as listing_id, m.price, m.created_at, m.seller_id, c.id as card_id, c.name as card_name, c.rarity, c.image_url, u.username as seller_name, u.nickname as seller_nickname FROM market_listings m JOIN " . DB_TABLE_CARDS . " c ON m.card_id = c.id JOIN " . DB_TABLE_USERS . " u ON m.seller_id = u.id WHERE m.status = 'active' ORDER BY m.created_at DESC");
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取购买结果消息
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 交易市场</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>交易市场</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
                <a href="my_cards.php" class="btn btn-secondary btn-lg">我的卡牌</a>
                <a href="sell_card.php" class="btn btn-primary btn-lg">出售卡牌</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card fade-in-up" style="text-align: center;">
            <h2>交易市场</h2>
            
            <?php if ($maintenance_mode): ?>
                <div class="message error">
                    <h3>交易市场维护中</h3>
                    <p>交易市场当前正在进行维护，暂时无法使用。请稍后再试。</p>
                </div>
                <?php exit(); // 维护模式下停止执行 ?>
            <?php else: ?>
                <div class="stat-card fade-in-up" style="margin: 20px auto; max-width: 400px;">
                    <h3>我的狗头币</h3>
                    <div class="stat-value"><?= $coins ?></div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message <?= strpos($message, '成功') !== false ? 'success' : 'error' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                
                <div class="cards-grid" style="margin: 30px 0;">
                    <?php if (count($listings) > 0): ?>
                        <?php foreach ($listings as $listing): ?>
                            <div class="card-item">
                                <div class="card-rarity-<?= $listing['rarity'] ?>">
                                    <img src="<?= $listing['image_url'] ?>" alt="<?= $listing['card_name'] ?>" style="width: 100%; height: 200px; object-fit: cover;">
                                    <h3><?= $listing['card_name'] ?></h3>
                                    <div class="card-meta">
                                        <span class="rarity rarity-<?= $listing['rarity'] ?>"><?= ucfirst($listing['rarity']) ?></span>
                                    </div>
                                    <div class="price">售价: <?= $listing['price'] ?> 狗头币</div>
                                    <div class="seller">卖家: <?= htmlspecialchars($listing['seller_nickname'] ?: $listing['seller_name']) ?></div>
                                    <div class="date">上架时间: <?= date('Y-m-d H:i', strtotime($listing['created_at'])) ?></div>
                                    <form action="buy_card.php" method="post" style="margin-top: 10px;">
                                        <input type="hidden" name="listing_id" value="<?= $listing['listing_id'] ?>">
                                        <button type="submit" name="buy" class="btn btn-success btn-block" <?= ($coins < $listing['price'] || $listing['seller_id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>><?= $listing['seller_id'] == $_SESSION['user_id'] ? '自己的卡牌' : '购买' ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>暂无正在售卖的卡牌。</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>