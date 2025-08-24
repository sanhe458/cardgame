<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查是否已登录且为管理员
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

try {
    // 连接数据库
    $db = getDB();
    
    // 检查是否已存在draw_count字段
    $stmt = $db->prepare("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasDrawCount = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'draw_count') {
            $hasDrawCount = true;
            break;
        }
    }
    
    if (!$hasDrawCount) {
        // 添加draw_count字段
        $db->exec("ALTER TABLE " . DB_TABLE_USERS . " ADD COLUMN draw_count INTEGER DEFAULT 0");
        echo "数据库更新成功：已添加draw_count字段。";
    } else {
        echo "数据库已包含draw_count字段，无需更新。";
    }
} catch (Exception $e) {
    echo "数据库更新失败: " . $e->getMessage();
}
?>