<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查用户是否已登录
if (!isLoggedIn()) {
    redirect('login.php');
}

$error = '';
$message = '';

// 获取当前用户信息
$db = getDB();
$stmt = $db->prepare("SELECT nickname FROM " . DB_TABLE_USERS . " WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$currentNickname = $user['nickname'] ?? '';

// 处理密码更改请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '请填写所有字段';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '新密码和确认密码不匹配';
    } elseif (strlen($newPassword) < 6) {
        $error = '新密码长度至少为6个字符';
    } else {
        // 验证当前密码
        $stmt = $db->prepare("SELECT password_hash FROM " . DB_TABLE_USERS . " WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            // 更新密码
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
            
            $message = '密码已成功更改';
        } else {
            $error = '当前密码不正确';
        }
    }
}

// 处理昵称更改请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_nickname'])) {
    $nickname = $_POST['nickname'] ?? '';
    
    // 验证输入
    if (empty($nickname)) {
        $error = '请输入昵称';
    } elseif (strlen($nickname) > 50) {
        $error = '昵称长度不能超过50个字符';
    } else {
        // 更新昵称
        $stmt = $db->prepare("UPDATE " . DB_TABLE_USERS . " SET nickname = ? WHERE id = ?");
        $stmt->execute([$nickname, $_SESSION['user_id']]);
        
        $message = '昵称已成功更改';
        $currentNickname = $nickname;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 用户设置</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-sizing: border-box;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        
        .message {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .settings-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        
        .settings-section h3 {
            margin-top: 0;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        small {
            display: block;
            color: #6c757d;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <h1><?= SITE_NAME ?></h1>
            <div class="nav-buttons">
                <span class="badge badge-primary">欢迎, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="index.php" class="btn btn-secondary btn-lg">返回首页</a>
                <a href="logout.php" class="btn btn-error btn-lg">退出</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card fade-in-up">
            <h2>用户设置</h2>
            
            <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message success"><?= $message ?></div>
            <?php endif; ?>
            
            <div class="settings-section">
                <h3>更改密码</h3>
                <form method="post" class="form-group">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label for="current_password">当前密码:</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">新密码:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">确认新密码:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary btn-block">更改密码</button>
                </form>
            </div>
            
            <div class="settings-section">
                <h3>设置昵称</h3>
                <form method="post" class="form-group">
                    <div class="form-group">
                        <label for="nickname">昵称:</label>
                        <input type="text" id="nickname" name="nickname" class="form-control" value="<?= htmlspecialchars($currentNickname) ?>" maxlength="50">
                        <small class="form-text text-muted">昵称将用于交易市场中显示您的名称</small>
                    </div>
                    
                    <button type="submit" name="change_nickname" class="btn btn-primary btn-block">保存昵称</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>