<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查用户是否已登录
if (!isLoggedIn()) {
    redirect('index.php?action=login');
}

$user_id = $_SESSION['user_id'];
$message = '';

// 处理购买请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listing_id'])) {
    $listing_id = (int)$_POST['listing_id'];
    
    try {
        $db = getDB();
        
        // 获取商品信息
        $stmt = $db->prepare("SELECT m.price, m.card_id, m.seller_id, c.name as card_name FROM market_listings m JOIN cards c ON m.card_id = c.id WHERE m.id = ? AND m.status = 'active'");
        $stmt->execute([$listing_id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($listing) {
            // 检查买家是否有足够的狗头币
            $stmt = $db->prepare("SELECT coins FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($buyer && $buyer['coins'] >= $listing['price']) {
                // 开始事务
                $db->beginTransaction();
                
                // 扣除买家狗头币
                $stmt = $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
                $stmt->execute([$listing['price'], $user_id]);
                
                // 增加卖家狗头币
                $stmt = $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                $stmt->execute([$listing['price'], $listing['seller_id']]);
                
                // 将卡牌转移给买家
                $stmt = $db->prepare("INSERT OR IGNORE INTO user_cards (user_id, card_id, name, rarity, image_url) SELECT ?, c.id, c.name, c.rarity, c.image_url FROM cards c WHERE c.id = ?");
                $stmt->execute([$user_id, $listing['card_id']]);
                
                // 更新交易状态和买家ID
                $stmt = $db->prepare("UPDATE market_listings SET status = 'sold', buyer_id = ? WHERE id = ?");
                $stmt->execute([$user_id, $listing_id]);
                
                // 删除已售出的商品记录
                $stmt = $db->prepare("DELETE FROM market_listings WHERE id = ? AND status = 'sold'");
                $stmt->execute([$listing_id]);
                
                // 提交事务
                $db->commit();
                
                $message = '购买成功！您已获得卡牌：' . $listing['card_name'];
                $_SESSION['message'] = $message;
                redirect('my_cards.php');
                exit(); // 确保重定向后脚本停止执行
            } else {
                // 回滚事务
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $message = '狗头币不足，无法购买。';
            }
        } else {
            // 回滚事务
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $message = '商品已下架，无法购买。';
        }
    } catch (Exception $e) {
        // 回滚事务
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $message = '购买失败，请稍后重试。';
    }
} else {
    $message = '无效的请求。';
}

// 重定向回交易市场页面，并传递消息
$_SESSION['message'] = $message;
redirect('market.php');
?>