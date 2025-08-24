<?php
// 抽卡逻辑（不重定向，直接显示结果）

// 检查今日抽卡次数
$todayDraws = getTodayDrawCount($_SESSION['user_id']);

// 获取用户货币和购买的抽卡次数
$user = query("SELECT coins, draw_count FROM " . DB_TABLE_USERS . " WHERE id = ?", [$_SESSION['user_id']])->fetch(PDO::FETCH_ASSOC);
$_SESSION['coins'] = $user['coins'];
$availableDraws = $user['draw_count']; // 用户购买的可用抽卡次数

// 检查是否有足够的抽卡次数
$canDrawSingle = $availableDraws >= 1;
$canDrawTenTimes = $availableDraws >= 10; // 十连抽需要10次抽卡次数

if (!$canDrawSingle) {
    echo "您的抽卡次数不足，无法进行抽卡。请前往商店购买抽卡次数。";
    return;
}

// 抽卡逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取抽卡类型
        $drawType = $_POST['draw_type'] ?? 'free';
        $drawCount = ($drawType === 'ten') ? 10 : 1;
        
        // 检查是否有足够的抽卡次数
        if ($availableDraws < $drawCount) {
            throw new Exception("抽卡次数不足");
        }
        
        // 扣除购买的抽卡次数
        query("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count - ? WHERE id = ?", [$drawCount, $_SESSION['user_id']]);
        
        // 获取所有卡片及其概率
        $cards = query("SELECT id, name, rarity, probability FROM " . DB_TABLE_CARDS)->fetchAll(PDO::FETCH_ASSOC);
        
        // 计算总概率
        $totalProbability = array_sum(array_column($cards, 'probability'));
        
        // 存储抽卡结果
        $drawResults = [];
        
        // 进行多次抽卡
        for ($i = 0; $i < $drawCount; $i++) {
            // 随机选择一张卡片
            $random = mt_rand() / mt_getrandmax() * $totalProbability;
            $selectedCard = null;
            $cumulativeProbability = 0;
            
            foreach ($cards as $card) {
                $cumulativeProbability += $card['probability'];
                if ($random <= $cumulativeProbability) {
                    $selectedCard = $card;
                    break;
                }
            }
            
            if ($selectedCard) {
                // 记录抽卡结果
                query("INSERT INTO " . DB_TABLE_DRAWS . " (user_id, card_id) VALUES (?, ?)", 
                    [$_SESSION['user_id'], $selectedCard['id']]);
                
                // 获取卡片详细信息
                $cardInfo = query("SELECT * FROM " . DB_TABLE_CARDS . " WHERE id = ?", [$selectedCard['id']])->fetch(PDO::FETCH_ASSOC);
                $drawResults[] = $cardInfo;
            }
        }
        
        if (!empty($drawResults)) {
            // 显示抽卡结果
            if ($drawCount > 1) {
                // 十连抽结果
                echo "<h1>十连抽结果</h1>";
                foreach ($drawResults as $result) {
                    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                    echo "<h3>" . $result['name'] . "</h3>";
                    echo "<p>稀有度: " . getRarityName($result['rarity']) . "</p>";
                    echo "</div>";
                }
            } else {
                // 单次抽卡结果
                $card = $drawResults[0];
                echo "<h1>抽卡结果</h1>";
                echo "<p>恭喜您抽到了：</p>";
                echo "<h2>" . $card['name'] . "</h2>";
                echo "<p>稀有度: " . getRarityName($card['rarity']) . "</p>";
                echo "<img src='" . $card['image_url'] . "' alt='" . $card['name'] . "' style='max-width: 300px;'>";
            }
            
            echo "<p><a href='draw_card.php'>继续抽卡</a></p>";
            echo "<p><a href='index.php'>返回首页</a></p>";
        }
    } catch (Exception $e) {
        echo "抽卡失败: " . $e->getMessage();
    }
}

function getRarityName($rarity) {
    switch ($rarity) {
        case 1: return '普通';
        case 2: return '稀有';
        case 3: return '史诗';
        case 4: return '传说';
        default: return '未知';
    }
}
?>