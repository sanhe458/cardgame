<?php
// 数据库配置
define('DB_FILE', __DIR__ . '/data/card_game.db');
define('DB_TABLE_CARDS', 'cards');
define('DB_TABLE_USERS', 'users');
define('DB_TABLE_DRAWS', 'draws');
define('DB_TABLE_USER_CARDS', 'user_cards');

// 游戏配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$B4hsfQkU0lxlGtI2Xm7kb.tdDoUGFJkueWOXaw3.y9ZsQSVr39yca'); // 管理员密码

// 网站信息
define('SITE_NAME', '342班抽卡');
define('SITE_TITLE', '342班抽卡');
define('SITE_DESCRIPTION', '这是一个简单的抽卡游戏。每天签到可获得1000狗头币。');

// 错误报告
ini_set('display_errors', '1');
error_reporting(E_ALL);

?>