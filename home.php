<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查是否已安装
if (!file_exists(DB_FILE)) {
    redirect('install.php');
}

// 获取卡片总数
$cardCount = query("SELECT COUNT(*) FROM " . DB_TABLE_CARDS)->fetchColumn();

// 获取用户抽卡总数
$userDrawCount = isLoggedIn() ? 
    query("SELECT COUNT(*) FROM " . DB_TABLE_DRAWS . " WHERE user_id = ?", [$_SESSION['user_id']])->fetchColumn() : 0;

// 获取稀有卡数量
$rareCardCount = query("SELECT COUNT(*) FROM " . DB_TABLE_CARDS . " WHERE rarity >= 3")->fetchColumn();

// 获取用户货币和购买的抽卡次数
if (isLoggedIn()) {
    $user = query("SELECT coins, draw_count, nickname FROM " . DB_TABLE_USERS . " WHERE id = ?", [$_SESSION['user_id']])->fetch(PDO::FETCH_ASSOC);
    $_SESSION['coins'] = $user['coins'];
    $_SESSION['nickname'] = $user['nickname'];
    $availableDraws = $user['draw_count']; // 用户购买的可用抽卡次数
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(SITE_DESCRIPTION) ?>">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1><?= SITE_NAME ?></h1>
            <?php if (isLoggedIn()): ?>
                <div class="nav-buttons">
                    <span class="badge badge-primary">欢迎, <?= htmlspecialchars($_SESSION['nickname'] ?: $_SESSION['username']) ?></span>
                    <span class="badge badge-success"><?= $_SESSION['coins'] ?> 狗头币</span>
                    <a href="?action=draw" class="btn btn-lg">抽卡</a>
                    <a href="checkin.php" class="btn btn-secondary btn-lg">签到</a>
                    <a href="?action=shop" class="btn btn-secondary btn-lg">商店</a>
                    <a href="market.php" class="btn btn-secondary btn-lg">交易市场</a>
                    <a href="sell_card.php" class="btn btn-secondary btn-lg">出售卡牌</a>
                    <a href="my_cards.php" class="btn btn-secondary btn-lg">我的卡牌</a>
                    <a href="user_settings.php" class="btn btn-secondary btn-lg">用户设置</a>
                    <?php if (isAdmin()): ?>
                        <a href="?action=admin" class="btn btn-warning btn-lg">管理后台</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-error btn-lg">退出</a>
                </div>
            <?php else: ?>
                <div class="nav-buttons">
                    <a href="login.php" class="btn btn-lg">登录</a>
                    <a href="register.php" class="btn btn-secondary btn-lg">注册</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <div class="stats fade-in-up">
            <div class="stat-card">
                <h3>总卡片数量</h3>
                <div class="stat-value"><?= $cardCount ?></div>
            </div>
            <div class="stat-card">
                <h3>稀有卡片数量</h3>
                <div class="stat-value"><?= $rareCardCount ?></div>
            </div>
            <?php if (isLoggedIn()): ?>
                <div class="stat-card">
                    <h3>您已抽卡次数</h3>
                    <div class="stat-value"><?= $userDrawCount ?></div>
                </div>
                <div class="stat-card">
                    <h3>您购买的抽卡次数</h3>
                    <div class="stat-value"><?= $availableDraws ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card fade-in-up">
            <h2>游戏说明</h2>
            <p>这是一个简单的抽卡游戏。每天签到可获得1000狗头币。</p>
            <p>您需要前往商店使用狗头币购买抽卡次数才能进行抽卡。</p>
            <p>卡片分为不同稀有度：普通、稀有、史诗和传说，稀有度越高抽中概率越低。</p>
        </div>
    </div>
</body>
</html>