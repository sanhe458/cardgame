<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 清除所有会话数据
$_SESSION = array();

// 删除会话cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 重定向到首页
redirect('index.php');
?>