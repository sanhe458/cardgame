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

// 检查维护模式
try {
    $stmt = $db->prepare("SELECT value FROM system_settings WHERE key = 'market_maintenance'");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $maintenance_mode = $setting ? $setting['value'] == '1' : false;
} catch (Exception $e) {
    // 如果无法获取维护模式设置，默认为关闭维护模式
    $maintenance_mode = false;
    // 可以记录错误日志
    error_log("无法获取市场维护模式设置: " . $e->getMessage());
}

// 获取用户拥有的卡牌
try {
    $stmt = $db->prepare("SELECT c.id, c.name, c.rarity, c.image_url FROM " . DB_TABLE_USER_CARDS . " c WHERE c.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $userCards = [];
    // 记录错误日志
    error_log("无法获取用户卡牌列表: " . $e->getMessage());
    // 可以设置错误消息在页面上显示
    $message = '无法获取您的卡牌列表，请稍后重试。';
}

// 处理出售请求
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell'])) {
    $card_id = $_POST['card_id'];
    $price = intval($_POST['price']);
    
    // 验证价格
    if ($price > 0 && $price <= 100000) {
        // 检查用户是否拥有这张卡牌
        // 注意：这里的$card_id是user_cards表中的id，而不是cards表中的id
        $stmt = $db->prepare("SELECT card_id FROM " . DB_TABLE_USER_CARDS . " WHERE user_id = ? AND id = ?");
        $stmt->execute([$_SESSION['user_id'], $card_id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($card) {
                // 开始事务
                $db->beginTransaction();
                try {
                    // 添加到交易市场
                    // 使用$card['card_id']作为cards表中的id
                    $stmt = $db->prepare("INSERT INTO market_listings (card_id, seller_id, price) VALUES (?, ?, ?)");
                    $stmt->execute([$card['card_id'], $_SESSION['user_id'], $price]);
                    
                    // 从用户卡牌列表中移除卡牌
                    // 使用$card_id作为user_cards表中的id
                    $stmt = $db->prepare("DELETE FROM user_cards WHERE user_id = ? AND id = ?");
                    $stmt->execute([$_SESSION['user_id'], $card_id]);
                
                // 提交事务
                $db->commit();
                
                $message = '卡牌已成功上架出售！';
            } catch (Exception $e) {
                $db->rollback();
                $message = '上架失败，请稍后重试。';
            }
        } else {
            $message = '您不拥有这张卡牌。';
        }
    } else {
        $message = '价格必须在1-100000狗头币之间。';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 出售卡牌</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>出售卡牌</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
                <a href="market.php" class="btn btn-secondary btn-lg">交易市场</a>
                <a href="my_cards.php" class="btn btn-primary btn-lg">我的卡牌</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card fade-in-up" style="text-align: center;">
                <h2>出售卡牌</h2>
                
                <?php if ($maintenance_mode): ?>
                    <div class="message error">
                        <h3>交易市场维护中</h3>
                        <p>交易市场当前正在进行维护，暂时无法出售卡牌。请稍后再试。</p>
                    </div>
                <?php else: ?>
                    <?php if ($message): ?>
                        <div class="message <?= strpos($message, '成功') !== false ? 'success' : 'error' ?>">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card" style="margin: 20px auto; max-width: 600px; text-align: left;">
                        <h3>说明</h3>
                        <ul>
                            <li>您可以在这里出售您拥有的卡牌</li>
                            <li>每张卡牌只能出售一次</li>
                            <li>请设置合理的售价</li>
                            <li>出售成功后，狗头币将立即到账</li>
                        </ul>
                    </div>
                    
                    <div class="cards-grid" style="margin: 30px 0;">
                        <?php if (count($userCards) > 0): ?>
                            <?php foreach ($userCards as $card): ?>
                                <div class="card-item">
                                    <div class="card-rarity-<?= $card['rarity'] ?>">
                                        <img src="<?= htmlspecialchars($card['image_url']) ?>" alt="<?= htmlspecialchars($card['name']) ?>" style="width: 100%; height: 200px; object-fit: cover;">
                                        <h3><?= htmlspecialchars($card['name']) ?></h3>
                                        <div class="card-meta">
                                            <span class="rarity rarity-<?= $card['rarity'] ?>"><?= getRarityText($card['rarity']) ?></span>
                                        </div>
                                        <form method="post" style="margin-top: 10px;">
                                            <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                                            <div style="margin: 10px 0;">
                                                <label for="price_<?= $card['id'] ?>">售价:</label>
                                                <input type="number" id="price_<?= $card['id'] ?>" name="price" min="1" max="999999" value="1000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                                            </div>
                                            <button type="submit" name="sell" class="btn btn-primary btn-block">上架出售</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>您当前没有可出售的卡牌。</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
        </div>
    </div>
</body>
</html>