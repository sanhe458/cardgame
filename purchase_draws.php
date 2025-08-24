<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 模拟登录用户
define('TEST_USERNAME', 'testuser');
define('TEST_PASSWORD', 'user123');

try {
    // 验证用户凭据
    $stmt = query("SELECT id, username, password_hash, coins, is_admin, admin_level, draw_count FROM " . DB_TABLE_USERS . " WHERE username = ?", [TEST_USERNAME]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify(TEST_PASSWORD, $user['password_hash'])) {
        // 设置会话变量
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['coins'] = $user['coins'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['admin_level'] = $user['admin_level'];
        
        // 模拟购买单次抽卡
        $db = getDB();
        
        // 检查狗头币是否足够
        if ($user['coins'] >= 160) {
            $db->beginTransaction();
            try {
                // 扣除狗头币
                $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET coins = coins - 160 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // 增加抽卡次数
                $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count + 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                $db->commit();
                
                // 获取更新后的信息
                $stmt = $db->prepare("SELECT coins, draw_count FROM " . DB_TABLE_USERS . " WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<h1>购买成功</h1>";
                echo "<p>当前狗头币: " . $updatedUser['coins'] . "</p>";
                echo "<p>当前抽卡次数: " . $updatedUser['draw_count'] . "</p>";
                echo "<p><a href='draw_card.php'>前往抽卡页面</a></p>";
            } catch (Exception $e) {
                $db->rollback();
                echo "购买过程中发生错误: " . $e->getMessage();
            }
        } else {
            echo "狗头币不足，无法购买。";
        }
    } else {
        echo "登录失败：用户名或密码错误";
    }
} catch (Exception $e) {
    echo "登录过程中发生错误: " . $e->getMessage();
}
?>