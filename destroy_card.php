<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isLoggedIn()) {
    redirect('login.php');
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my_cards.php');
}

// 获取POST数据
$user_card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;

if ($user_card_id <= 0) {
    $_SESSION['error'] = '无效的卡牌ID';
    redirect('my_cards.php');
}

try {
    $db = getDB();
    
    // 检查卡牌是否属于当前用户
    $stmt = $db->prepare("SELECT id FROM " . DB_TABLE_USER_CARDS . " WHERE user_id = ? AND id = ?");
    $stmt->execute([$_SESSION['user_id'], $user_card_id]);
    $userCard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userCard) {
        $_SESSION['error'] = '您没有这张卡牌或卡牌不存在';
        redirect('my_cards.php');
    }
    
    // 从用户卡牌表中删除卡牌
    $stmt = $db->prepare("DELETE FROM " . DB_TABLE_USER_CARDS . " WHERE user_id = ? AND id = ?");
    $stmt->execute([$_SESSION['user_id'], $user_card_id]);
    
    $_SESSION['success'] = '卡牌销毁成功';
} catch (Exception $e) {
    $_SESSION['error'] = '销毁卡牌时发生错误: ' . $e->getMessage();
}

redirect('my_cards.php');
?>