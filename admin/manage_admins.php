<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查是否是超级管理员
if (!isSuperAdmin()) {
    redirect('../index.php');
}

// 处理添加普通管理员
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($username) || empty($password)) {
            throw new Exception("用户名和密码不能为空");
        }
        
        if (strlen($username) < 3) {
            throw new Exception("用户名至少需要3个字符");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("密码至少需要6个字符");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("两次输入的密码不一致");
        }
        
        // 检查用户名是否已存在
        $existing_user = query("SELECT id FROM " . DB_TABLE_USERS . " WHERE username = ?", [$username])->fetch();
        if ($existing_user) {
            throw new Exception("用户名已存在");
        }
        
        // 添加普通管理员
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = query("INSERT INTO " . DB_TABLE_USERS . " (username, password_hash, is_admin, admin_level) VALUES (?, ?, 1, 1)", 
                     [$username, $password_hash]);
        
        $success = "普通管理员添加成功";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 处理删除普通管理员
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    try {
        $user_id = (int)$_POST['user_id'];
        
        // 检查用户是否存在且为普通管理员
        $user = query("SELECT * FROM " . DB_TABLE_USERS . " WHERE id = ? AND admin_level = 1", [$user_id])->fetch();
        if (!$user) {
            throw new Exception("用户不存在或不是普通管理员");
        }
        
        // 删除普通管理员
        query("DELETE FROM " . DB_TABLE_USERS . " WHERE id = ?", [$user_id]);
        
        $success = "普通管理员删除成功";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取所有普通管理员
$admins = query("SELECT * FROM " . DB_TABLE_USERS . " WHERE admin_level = 1 ORDER BY created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 管理员管理</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="bi bi-person-badge me-2"></i>管理员管理</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>返回后台
                </a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="mb-0">添加普通管理员</h3>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认密码</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">添加管理员</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">普通管理员列表</h3>
            </div>
            <div class="card-body">
                <?php if (count($admins) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户名</th>
                                    <th>注册时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?= $admin['id'] ?></td>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td><?= $admin['created_at'] ?></td>
                                        <td>
                                            <form method="post" style="display: inline-block;" onsubmit="return confirm('确定要删除这个管理员吗？');">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mb-0">暂无普通管理员</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>