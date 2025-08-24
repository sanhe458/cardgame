<?php
require_once 'config.php';
require_once 'functions.php';

echo "Users table structure:\n";

try {
    $db = getDB();
    $stmt = $db->prepare("PRAGMA table_info(" . DB_TABLE_USERS . ")");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo $column['name'] . " (" . $column['type'] . ")" . ($column['notnull'] ? " NOT NULL" : "") . ($column['pk'] ? " PRIMARY KEY" : "") . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>