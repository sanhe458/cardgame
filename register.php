<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 如果已经登录，重定向到首页
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码';
    } elseif (strlen($username) < 3) {
        $error = '用户名至少需要3个字符';
    } elseif (strlen($password) < 6) {
        $error = '密码至少需要6个字符';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db = getDB();
            
            // 检查用户名是否已存在
            $stmt = $db->prepare("SELECT id FROM " . DB_TABLE_USERS . " WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = '用户名已存在，请选择其他用户名';
            } else {
                // 创建新用户
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // 检查是否是默认管理员用户
                $isAdmin = ($username === ADMIN_USERNAME) ? 1 : 0;
                $adminLevel = ($username === ADMIN_USERNAME) ? 2 : 0;
                $stmt = $db->prepare("INSERT INTO " . DB_TABLE_USERS . " (username, password_hash, coins, draw_count, is_admin, admin_level, nickname) VALUES (?, ?, 0, 0, ?, ?, '新用户')");
                $stmt->execute([$username, $hashedPassword, $isAdmin, $adminLevel]);
                
                $success = '注册成功！现在可以登录了。';
            }
        } catch (Exception $e) {
            $error = '注册失败，请稍后重试';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 注册</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="card fade-in-up" style="max-width: 400px; margin: 50px auto;">
            <h2 style="text-align: center;">用户注册</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" style="width: 100%;">注册</button>
            </form>
            
            <div style="margin-top: 20px; text-align: center;">
                <p>已有账号？ <a href="login.php">立即登录</a></p>
            </div>
        </div>
    </div>
</body>
</html>