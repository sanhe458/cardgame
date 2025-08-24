<?php
require_once 'config.php';
require_once 'functions.php';

date_default_timezone_set('Asia/Shanghai'); // 设置时区为上海

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

try {
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // 检查今日是否已签到
    $stmt = $db->prepare("SELECT id FROM user_checkins WHERE user_id = ? AND checkin_date = ?");
    $stmt->execute([$userId, $today]);
    $checkin = $stmt->fetch();
    
    if ($checkin) {
        $_SESSION['message'] = "您今天已经签到过了！";
        redirect('index.php');
    }
    
    // 开始事务
    $db->beginTransaction();
    
    // 记录签到
    $stmt = $db->prepare("INSERT INTO user_checkins (user_id, checkin_date) VALUES (?, ?)");
    $stmt->execute([$userId, $today]);
    
    // 增加用户货币（1000狗头币）
    $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET coins = coins + 1000 WHERE id = ?");
    $stmt->execute([$userId]);
    
    // 提交事务
    $db->commit();
    
    $_SESSION['message'] = "签到成功！获得1000狗头币！";
    redirect('index.php');
    
} catch (Exception $e) {
    // 回滚事务
    if ($db->inTransaction()) {
        $db->rollback();
    }
    $_SESSION['error'] = "签到失败: " . $e->getMessage();
    redirect('index.php');
}
?>