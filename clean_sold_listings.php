<?php
require_once 'config.php';
require_once 'functions.php';

echo "开始清理已售出的市场列表数据...\n";

try {
    $db = getDB();
    
    // 删除状态为'sold'的记录
    $stmt = $db->prepare("DELETE FROM market_listings WHERE status = 'sold'");
    $result = $stmt->execute();
    
    $rowCount = $stmt->rowCount();
    echo "成功清理了 {$rowCount} 条已售出的市场列表数据。\n";
    
} catch (Exception $e) {
    echo "清理过程中出现错误: " . $e->getMessage() . "\n";
}

echo "清理完成。\n";
?>