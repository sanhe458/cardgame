<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 检查是否有抽卡结果
if (!isset($_SESSION['drawn_card'])) {
    redirect('draw_card.php');
}

$card = $_SESSION['drawn_card'];

// 清除会话中的抽卡结果
unset($_SESSION['drawn_card']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 抽卡结果</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>抽卡结果</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
                <a href="draw_card.php" class="btn btn-secondary btn-lg">继续抽卡</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card fade-in-up" style="text-align: center; max-width: 600px; margin: 30px auto;">
            <h2>恭喜您获得新卡牌！</h2>
            
            <div class="card-item" style="margin: 30px auto; max-width: 300px;">
                <div class="card-rarity-<?= $card['rarity'] ?>">
                    <img src="<?= $card['image_url'] ?>" alt="<?= $card['name'] ?>" style="width: 100%; height: 200px; object-fit: cover;">
                    <h3><?= $card['name'] ?></h3>
                    <div class="card-meta">
                        <span class="rarity rarity-<?= $card['rarity'] ?>"><?= ucfirst($card['rarity']) ?></span>
                    </div>
                </div>
            </div>
            
            <div style="margin: 20px 0;">
                <a href="draw_card.php" class="btn btn-primary btn-lg">继续抽卡</a>
                <a href="my_cards.php" class="btn btn-secondary btn-lg" style="margin-left: 15px;">查看我的卡牌</a>
            </div>
        </div>
    </div>
</body>
</html>