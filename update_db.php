<?php
require_once 'config.php';

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查是否已存在admin_level字段
    $stmt = $db->query("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'name');
    
    if (!in_array('admin_level', $column_names)) {
        // 添加admin_level字段
        $db->exec("ALTER TABLE " . DB_TABLE_USERS . " ADD COLUMN admin_level INTEGER DEFAULT 0");
        echo "<p style='color: green;'>已成功添加admin_level字段</p>";
        
        // 更新现有管理员用户的admin_level
        $db->exec("UPDATE " . DB_TABLE_USERS . " SET admin_level = 2 WHERE is_admin = 1");
        echo "<p style='color: green;'>已更新现有管理员用户的admin_level字段</p>";
    } else {
        echo "<p>admin_level字段已存在</p>";
    }
    
    // 再次检查表结构
    $stmt = $db->query("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>更新后的Users表字段:</h2>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['name'] . " (" . $column['type'] . ")";
        if ($column['notnull']) echo " NOT NULL";
        if ($column['pk']) echo " PRIMARY KEY";
        echo "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>