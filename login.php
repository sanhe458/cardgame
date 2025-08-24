<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 如果已经登录，重定向到首页
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password_hash, is_admin, admin_level FROM " . DB_TABLE_USERS . " WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            // 设置昵称会话变量
            $_SESSION['nickname'] = $user['nickname'] ?? '';
            // 设置管理员权限会话变量
            $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
            $_SESSION['admin_level'] = $user['admin_level'] ?? 0;
            redirect('index.php');
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 登录</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="card fade-in-up">
            <h2>用户登录</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">登录</button>
            </form>
            
            <div style="margin-top: 20px; text-align: center;">
                <p>还没有账户? <a href="register.php">立即注册</a></p>
            </div>
        </div>
    </div>
</body>
</html>