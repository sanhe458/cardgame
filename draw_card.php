<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'gacha_logic.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 获取用户信息
$db = getDB();
$stmt = $db->prepare("SELECT coins, draw_count FROM " . DB_TABLE_USERS . " WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('login.php');
}

$coins = $user['coins'];
$drawCount = $user['draw_count'];
$selectedPoolId = null; // 默认不选择特定卡池

// 初始化卡池系统
$gacha = new GachaSystem();

// 获取所有激活的卡池
$activePools = $gacha->getAllActivePools();

// 处理卡池选择请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_pool'])) {
    $poolId = $_POST['pool_id'];
    
    // 验证卡池ID是否有效
    $pool = $gacha->getPoolById($poolId);
    if ($pool) {
        // 设置选择的卡池（仅在当前会话中有效）
        $selectedPoolId = $poolId;
        
        $message = '成功切换到卡池: ' . $pool['name'];
    } else {
        $error = '选择的卡池无效';
    }
}

// 检查是否有抽卡次数
$canDraw = $drawCount > 0;

// 处理抽卡请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draw']) && $canDraw) {
    try {
        // 使用卡池系统进行抽卡
        $gacha = new GachaSystem();
        $card = $gacha->performDraw($_SESSION['user_id'], $selectedPoolId);
        
        // 重定向到抽卡结果页面
        $_SESSION['drawn_card'] = $card;
        redirect('draw_result.php');
    } catch (Exception $e) {
        $error = '抽卡失败: ' . $e->getMessage();
    }
}

// 处理十连抽请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ten_draw'])) {
    try {
        // 检查用户是否有足够的抽卡次数
        if ($drawCount < 10) {
            $error = '您的抽卡次数不足10次，无法进行十连抽';
        } else {
            // 使用卡池系统进行十连抽
            $gacha = new GachaSystem();
            $cards = $gacha->performTenDraw($_SESSION['user_id'], $selectedPoolId);
            
            // 重定向到十连抽结果页面
            $_SESSION['drawn_cards'] = $cards;
            redirect('ten_draw_result.php');
        }
    } catch (Exception $e) {
        $error = '十连抽失败: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 抽卡</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1>抽卡</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card fade-in-up">
            <h2 style="text-align: center;">单次抽卡</h2>
            
            <?php if (isset($message)): ?>
                <div class="message success"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <!-- 卡池选择 -->
            <div class="card" style="margin: 20px auto; max-width: 600px;">
                <h3>选择卡池</h3>
                <form method="post" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 20px;">
                    <select name="pool_id" class="form-control" style="flex: 1; min-width: 250px; padding: 12px; font-size: 16px; border-radius: 8px; border: 2px solid #ddd; background-color: white;">
                        <?php foreach ($activePools as $pool): ?>
                            <option value="<?= $pool['id'] ?>" <?= ($selectedPoolId == $pool['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pool['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="select_pool" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px; border-radius: 8px;">切换卡池</button>
                </form>
                
                <?php if ($selectedPoolId): ?>
                    <?php 
                        // 获取当前选择的卡池信息
                        $selectedPool = null;
                        foreach ($activePools as $pool) {
                            if ($pool['id'] == $selectedPoolId) {
                                $selectedPool = $pool;
                                break;
                            }
                        }
                    ?>
                    <?php if ($selectedPool): ?>
                        <div style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #e3f2fd, #bbdefb); border-radius: 8px; border-left: 5px solid var(--primary-color);">
                            <strong>当前卡池:</strong> <?= htmlspecialchars($selectedPool['name']) ?><br>
                            <strong>描述:</strong> <?= htmlspecialchars($selectedPool['description']) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #e3f2fd, #bbdefb); border-radius: 8px; border-left: 5px solid var(--primary-color);">
                        <strong>当前卡池:</strong> 默认卡池
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card" style="margin: 20px auto; max-width: 400px;">
                <h3>剩余抽卡次数</h3>
                <div class="stat-value"><?= $drawCount ?></div>
            </div>
            
            <div class="stat-card" style="margin: 20px auto; max-width: 400px;">
                <h3>当前狗头币</h3>
                <div class="stat-value"><?= $coins ?></div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <div style="text-align: center; margin: 30px 0;">
                <?php if ($canDraw): ?>
                    <form method="post" style="display: inline-block; margin: 0 10px;">
                        <button type="submit" name="draw" class="btn btn-primary btn-lg">单次抽卡</button>
                    </form>
                    
                    <?php if ($drawCount >= 10): ?>
                        <form method="post" style="display: inline-block; margin: 0 10px;">
                            <button type="submit" name="ten_draw" class="btn btn-success btn-lg">十连抽</button>
                        </form>
                    <?php else: ?>
                        <div class="message info" style="margin-top: 15px;">您需要至少10次抽卡次数才能进行十连抽</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="message info">您当前没有抽卡次数，请前往商店购买</div>
                    <a href="shop.php" class="btn btn-success btn-lg" style="margin-top: 15px;">前往商店</a>
                <?php endif; ?>
            </div>
            
            <div class="card" style="margin-top: 30px;">
                <h3>获取抽卡机会说明</h3>
                <ul>
                    <li>每日签到可以获得抽卡次数</li>
                    <li>在商店使用狗头币购买抽卡次数</li>
                    <li>单次抽卡需要160狗头币</li>
                    <li>十连抽需要1600狗头币</li>
                </ul>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="checkin.php" class="btn btn-secondary btn-lg">每日签到</a>
                    <a href="shop.php" class="btn btn-secondary btn-lg">前往商店</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>