<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 获取用户信息
$db = getDB();
$stmt = $db->prepare("SELECT coins FROM " . DB_TABLE_USERS . " WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('login.php');
}

$coins = $user['coins'];

// 处理购买请求
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
    $item = $_POST['item'];
    
    switch ($item) {
        case 'single_draw':
            if ($coins >= 160) {
                // 扣除狗头币并增加抽卡次数
                $db->beginTransaction();
                try {
                    // 更新用户狗头币
                    $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET coins = coins - 160 WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // 增加抽卡次数（通过减少已抽卡次数来实现）
                    // 这里我们简单地记录购买
                    $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count + 1 WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $message = '购买成功！您已获得1次抽卡机会。';
                    $coins -= 160;
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollback();
                    $message = '购买失败，请稍后重试。';
                }
            } else {
                $message = '狗头币不足，无法购买。';
            }
            break;
            
        case 'ten_draw':
            if ($coins >= 1600) {
                // 扣除狗头币
                $db->beginTransaction();
                try {
                    // 更新用户狗头币
                    $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET coins = coins - 1600 WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count + 10 WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $message = '购买成功！您已获得10次抽卡机会。';
                    $coins -= 1600;
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollback();
                    $message = '购买失败，请稍后重试。';
                }
            } else {
                $message = '狗头币不足，无法购买。';
            }
            break;
            
        default:
            $message = '无效的购买项目。';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 商店</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>商店</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="stats fade-in-up">
            <div class="stat-card">
                <h3>我的狗头币</h3>
                <div class="stat-value"><?= $coins ?></div>
            </div>
        </div>
        
        <div class="card fade-in-up">
            <h2>购买抽卡次数</h2>
            
            <?php if ($message): ?>
                <div class="message <?= strpos($message, '成功') !== false ? 'success' : 'error' ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <div class="cards-grid">
                <div class="card-item">
                    <div class="card-content">
                        <h3>单次抽卡</h3>
                        <p class="price">160 狗头币</p>
                        <p>获得1次抽卡机会</p>
                        <form method="post">
                            <input type="hidden" name="item" value="single_draw">
                            <button type="submit" name="purchase" class="btn btn-primary btn-block" <?= $coins < 160 ? 'disabled' : '' ?>>购买</button>
                        </form>
                        <?php if ($coins < 160): ?>
                            <p class="insufficient-funds">狗头币不足，无法购买</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-item">
                    <div class="card-content">
                        <h3>十连抽</h3>
                        <p class="price">1600 狗头币</p>
                        <p>获得10次抽卡机会</p>
                        <form method="post">
                            <input type="hidden" name="item" value="ten_draw">
                            <button type="submit" name="purchase" class="btn btn-success btn-block" <?= $coins < 1600 ? 'disabled' : '' ?>>购买</button>
                        </form>
                        <?php if ($coins < 1600): ?>
                            <p class="insufficient-funds">狗头币不足，无法购买</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>