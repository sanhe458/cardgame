<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isAdmin()) {
    $_SESSION['error'] = "需要管理员权限";
    redirect('../index.php');
}

// 处理密码重置
if (isset($_GET['reset_password'])) {
    try {
        $userId = (int)$_GET['reset_password'];
        // 生成随机密码
        $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        query("UPDATE " . DB_TABLE_USERS . " SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
        $_SESSION['message'] = "用户密码已重置为: $newPassword";
        redirect('manage_users.php');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 处理用户删除
if (isset($_GET['delete'])) {
    try {
        $userId = (int)$_GET['delete'];
        
        // 不能删除当前登录的管理员
        if ($userId === $_SESSION['user_id']) {
            throw new Exception("不能删除当前登录的用户");
        }
        
        query("DELETE FROM " . DB_TABLE_USERS . " WHERE id = ?", [$userId]);
        $_SESSION['message'] = "用户删除成功";
        redirect('manage_users.php');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 获取所有用户
$users = query("SELECT id, username, nickname, is_admin, admin_level, created_at FROM " . DB_TABLE_USERS . " ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 用户管理</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        .btn-warning:hover {
            background-color: #ffca2c;
            border-color: #ffc720;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }
        .admin-badge {
            background: #5cb85c;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <?php include 'sidebar.php'; ?>

            <!-- 主内容区 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-people me-2"></i>用户管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>返回后台
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>用户列表</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>用户名</th>
                                                <th>昵称</th>
                                                <th>角色</th>
                                                <th>注册时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?= $user['id'] ?></td>
                                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                                    <td><?= htmlspecialchars($user['nickname']) ?></td>
                                                    <td>
                                                        <?php if ($user['is_admin']): ?>
                                                            <?php if ($user['admin_level'] == 2): ?>
                                                                <span class="admin-badge" style="background: #ff9800;">超级管理员</span>
                                                            <?php elseif ($user['admin_level'] == 1): ?>
                                                                <span class="admin-badge" style="background: #2196f3;">普通管理员</span>
                                                            <?php else: ?>
                                                                <span class="admin-badge">管理员</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span>普通用户</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                                    <td>
                                                        <a href="?reset_password=<?= $user['id'] ?>" class="btn btn-warning btn-sm" onclick="return confirm('确定重置该用户密码吗？')">重置密码</a>
                                                        <?php if ((!$user['is_admin'] || $user['id'] === $_SESSION['user_id']) && ($user['admin_level'] < 2 || $_SESSION['admin_level'] == 2)): ?>
                                                            <a href="?delete=<?= $user['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定删除这个用户吗？该操作不可恢复！')">删除</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>