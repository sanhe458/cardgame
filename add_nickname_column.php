<?php
require_once 'config.php';
require_once 'functions.php';

echo "Adding nickname column to users table...\n";

try {
    $db = getDB();
    
    // 检查是否已存在nickname字段
    $stmt = $db->query("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('nickname', $columnNames)) {
        // 添加nickname字段到用户表
        $db->exec("ALTER TABLE " . DB_TABLE_USERS . " ADD COLUMN nickname TEXT");
        echo "Nickname column added successfully.\n";
    } else {
        echo "Nickname column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>