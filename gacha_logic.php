<?php
/**
 * 卡池抽卡逻辑
 */

require_once 'config.php';
require_once 'functions.php';

class GachaSystem {
    private $gachaDb;
    private $gameDb;
    
    public function __construct() {
        // 连接到卡池数据库
        $this->gachaDb = new PDO('sqlite:' . __DIR__ . '/data/gacha.db');
        $this->gachaDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 连接到游戏数据库
        $this->gameDb = getDB();
    }
    
    /**
     * 获取活动卡池
     */
    public function getActivePool() {
        $stmt = $this->gachaDb->prepare("SELECT * FROM gacha_pools WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取所有激活的卡池
     */
    public function getAllActivePools() {
        $stmt = $this->gachaDb->prepare("SELECT * FROM gacha_pools WHERE is_active = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据ID获取特定卡池
     */
    public function getPoolById($poolId) {
        $stmt = $this->gachaDb->prepare("SELECT * FROM gacha_pools WHERE id = ? AND is_active = 1");
        $stmt->execute([$poolId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取卡池中的卡片及其概率
     */
    public function getPoolCards($poolId) {
        // 修改查询，从游戏数据库中获取卡片信息
        $stmt = $this->gachaDb->prepare("SELECT pc.probability, pc.card_id FROM pool_cards pc WHERE pc.pool_id = ? ORDER BY pc.probability DESC");
        $stmt->execute([$poolId]);
        $poolCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取卡片详细信息
        $cards = [];
        foreach ($poolCards as $poolCard) {
            $cardStmt = $this->gameDb->prepare("SELECT id, name, rarity, image_url FROM " . DB_TABLE_CARDS . " WHERE id = ?");
            $cardStmt->execute([$poolCard['card_id']]);
            $card = $cardStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($card) {
                $cards[] = array_merge($card, ['probability' => $poolCard['probability']]);
            }
        }
        
        return $cards;
    }
    
    /**
     * 从卡池中抽取一张卡片
     */
    public function drawCardFromPool($poolId) {
        // 获取卡池中的所有卡片
        $poolCards = $this->getPoolCards($poolId);
        
        if (empty($poolCards)) {
            throw new Exception('该卡池中没有可抽取的卡片');
        }
        
        // 计算总概率
        $totalProbability = 0;
        foreach ($poolCards as $card) {
            $totalProbability += $card['probability'];
        }
        
        // 如果总概率不为100%，需要进行归一化处理
        $normalizedCards = [];
        $cumulativeProbability = 0;
        
        foreach ($poolCards as $card) {
            $normalizedProbability = ($card['probability'] / $totalProbability) * 100;
            $cumulativeProbability += $normalizedProbability;
            $normalizedCards[] = [
                'card' => $card,
                'cumulative_probability' => $cumulativeProbability
            ];
        }
        
        // 生成随机数进行抽卡
        $random = mt_rand(0, 10000) / 100; // 生成0-100之间的随机数
        
        // 根据随机数选择卡片
        foreach ($normalizedCards as $item) {
            if ($random <= $item['cumulative_probability']) {
                return $item['card'];
            }
        }
        
        // 如果没有匹配到（理论上不应该发生），返回最后一张卡片
        return end($normalizedCards)['card'];
    }
    
    /**
     * 执行抽卡
     */
    public function performDraw($userId, $poolId = null) {
        // 开始事务
        $this->gameDb->beginTransaction();
        
        try {
            // 检查用户是否有抽卡次数
            $stmt = $this->gameDb->prepare("SELECT draw_count FROM " . DB_TABLE_USERS . " WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['draw_count'] <= 0) {
                throw new Exception('您没有抽卡次数');
            }
            
            // 如果用户没有选择卡池或选择的卡池无效，则使用默认活动卡池
            if ($poolId === null) {
                $activePool = $this->getActivePool();
                if (!$activePool) {
                    throw new Exception('当前没有活动的卡池');
                }
                $poolId = $activePool['id'];
            }
            
            // 验证卡池是否有效
            $pool = $this->getPoolById($poolId);
            if (!$pool) {
                throw new Exception('选择的卡池无效');
            }
            
            // 从卡池中抽取卡片
            $card = $this->drawCardFromPool($poolId);
            
            // 减少用户抽卡次数
            $stmt = $this->gameDb->prepare("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count - 1 WHERE id = ?");
            $stmt->execute([$userId]);
            
            // 记录抽卡结果
            $stmt = $this->gameDb->prepare("INSERT INTO " . DB_TABLE_DRAWS . " (user_id, card_id, draw_time) VALUES (?, ?, datetime('now'))");
            $stmt->execute([$userId, $card['id']]);
            
            // 将卡牌添加到用户卡牌表
            $stmt = $this->gameDb->prepare("INSERT INTO " . DB_TABLE_USER_CARDS . " (user_id, card_id, name, rarity, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $card['id'], $card['name'], $card['rarity'], $card['image_url']]);
            
            // 提交事务
            $this->gameDb->commit();
            
            return $card;
        } catch (Exception $e) {
            // 回滚事务
            $this->gameDb->rollback();
            throw $e;
        }
    }
    
    /**
     * 执行十连抽
     */
    public function performTenDraw($userId, $poolId = null) {
        $cards = [];
        
        // 开始事务
        $this->gameDb->beginTransaction();
        
        try {
            // 检查用户是否有足够的抽卡次数
            $stmt = $this->gameDb->prepare("SELECT draw_count FROM " . DB_TABLE_USERS . " WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['draw_count'] < 10) {
                throw new Exception('您的抽卡次数不足10次');
            }
            
            // 如果用户没有选择卡池或选择的卡池无效，则使用默认活动卡池
            if ($poolId === null) {
                $activePool = $this->getActivePool();
                if (!$activePool) {
                    throw new Exception('当前没有活动的卡池');
                }
                $poolId = $activePool['id'];
            }
            
            // 验证卡池是否有效
            $pool = $this->getPoolById($poolId);
            if (!$pool) {
                throw new Exception('选择的卡池无效');
            }
            
            // 执行10次抽卡
            for ($i = 0; $i < 10; $i++) {
                // 从卡池中抽取卡片
                $card = $this->drawCardFromPool($poolId);
                $cards[] = $card;
                
                // 记录抽卡结果
                $stmt = $this->gameDb->prepare("INSERT INTO " . DB_TABLE_DRAWS . " (user_id, card_id, draw_time) VALUES (?, ?, datetime('now'))");
                $stmt->execute([$userId, $card['id']]);
                
                // 将卡牌添加到用户卡牌表
                $stmt = $this->gameDb->prepare("INSERT INTO " . DB_TABLE_USER_CARDS . " (user_id, card_id, name, rarity, image_url) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $card['id'], $card['name'], $card['rarity'], $card['image_url']]);
            }
            
            // 减少用户抽卡次数
            $stmt = $this->gameDb->prepare("UPDATE " . DB_TABLE_USERS . " SET draw_count = draw_count - 10 WHERE id = ?");
            $stmt->execute([$userId]);
            
            // 提交事务
            $this->gameDb->commit();
            
            return $cards;
        } catch (Exception $e) {
            // 回滚事务
            $this->gameDb->rollback();
            throw $e;
        }
    }
}
?>