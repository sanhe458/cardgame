<?php

/**
 * 获取数据库连接
 * @return PDO
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}

/**
 * 执行SQL查询
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function query($sql, $params = []) {
    // 基本的SQL注入防护
    if (preg_match('/(union|select|insert|update|delete|drop|create|alter|exec|execute)\s+/i', $sql)) {
        // 允许正常的SQL操作，但阻止潜在的恶意关键字组合
        $whitelist = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
        $sqlUpper = strtoupper($sql);
        $isAllowed = false;
        
        foreach ($whitelist as $allowed) {
            if (strpos($sqlUpper, $allowed) !== false) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            throw new Exception("不允许的SQL操作");
        }
    }
    
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * 检查用户是否登录
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * 检查是否是管理员
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

/**
 * 检查是否是超级管理员
 * @return bool
 */
function isSuperAdmin() {
    return isset($_SESSION['admin_level']) && $_SESSION['admin_level'] == 2;
}

/**
 * 检查是否是普通管理员
 * @return bool
 */
function isNormalAdmin() {
    return isset($_SESSION['admin_level']) && $_SESSION['admin_level'] == 1;
}

/**
 * 检查是否具有管理员权限（普通管理员或超级管理员）
 * @return bool
 */
function hasAdminPrivileges() {
    return isset($_SESSION['admin_level']) && $_SESSION['admin_level'] > 0;
}

/**
 * 重定向到指定页面
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * 获取今日已抽卡次数
 * @param int $userId
 * @return int
 */
function getTodayDrawCount($userId) {
    $today = date('Y-m-d');
    $stmt = query("SELECT COUNT(*) FROM " . DB_TABLE_DRAWS . " WHERE user_id = ? AND date(draw_time) = ?", [$userId, $today]);
    return $stmt->fetchColumn();
}

/**
 * 检查交易市场是否处于维护模式
 * @return bool
 */
function isMarketMaintenance() {
    try {
        $stmt = query("SELECT value FROM system_settings WHERE key = 'market_maintenance'");
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        return $setting ? $setting['value'] == '1' : false;
    } catch (Exception $e) {
        // 如果查询失败，默认不处于维护模式
        return false;
    }
}

/**
 * 获取卡牌稀有度文本
 * @param int $rarity 稀有度数值
 * @return string 稀有度文本
 */
function getRarityText($rarity) {
    switch ($rarity) {
        case 1: return '普通';
        case 2: return '稀有';
        case 3: return '史诗';
        case 4: return '传说';
        default: return '未知';
    }
}
?>