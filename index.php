<?php
// 抽卡游戏入口文件
require_once 'config.php';
require_once 'functions.php';

// 检查是否已安装
if (!file_exists(DB_FILE) && !isset($_GET['install'])) {
    header('Location: install.php');
    exit;
}

// 自动检查并更新数据库结构（添加coins字段）
try {
    $db = getDB();
    
    // 检查是否已存在coins字段
    $stmt = $db->query("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('coins', $columnNames)) {
        // 添加货币字段到用户表
        $db->exec("ALTER TABLE " . DB_TABLE_USERS . " ADD COLUMN coins INTEGER DEFAULT 0");
    }
} catch (Exception $e) {
    // 静默处理错误，避免影响正常功能
}

// 路由逻辑
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'draw':
        require 'draw_card.php';
        break;
    case 'draw_result':
        require 'draw_result.php';
        break;
    case 'draw_results':
        require 'draw_results.php';
        break;
    case 'shop':
        require 'shop.php';
        break;
    case 'admin':
        require 'admin/index.php';
        break;
    case 'manage_cards':
        require 'admin/manage_cards.php';
        break;
    case 'manage_users':
        require 'admin/manage_users.php';
        break;
    case 'view_draws':
        require 'admin/view_draws.php';
        break;
    case 'system_settings':
        require 'admin/system_settings.php';
        break;
    case 'manage_admins':
        require 'admin/manage_admins.php';
        break;
    default:
        require 'home.php';
}
?>