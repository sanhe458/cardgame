<?php
require_once '../config.php';
require_once '../functions.php';

session_start();

// 检查是否为管理员
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$message = '';
$deletedCount = 0;

// 处理清理市场已售出数据请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_sold'])) {
    try {
        $db = getDB();
        
        // 删除状态为'sold'的记录
        $stmt = $db->prepare("DELETE FROM market_listings WHERE status = 'sold'");
        $stmt->execute();
        
        $deletedCount = $stmt->rowCount();
        $message = "成功清理了 {$deletedCount} 条已售出的市场列表数据。";
        
    } catch (Exception $e) {
        $message = "清理过程中出现错误: " . $e->getMessage();
    }
}

// 处理清理抽卡记录数据请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_draws'])) {
    try {
        $db = getDB();
        
        // 删除所有抽卡记录
        $stmt = $db->prepare("DELETE FROM " . DB_TABLE_DRAWS);
        $stmt->execute();
        
        $deletedCount = $stmt->rowCount();
        $message = "成功清理了 {$deletedCount} 条抽卡记录数据。";
        
    } catch (Exception $e) {
        $message = "清理过程中出现错误: " . $e->getMessage();
    }
}

// 获取当前残留的已售出数据数量
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM market_listings WHERE status = 'sold'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $residualMarketCount = $result['count'];
} catch (Exception $e) {
    $residualMarketCount = '未知';
    $message = "无法获取残留市场数据数量: " . $e->getMessage();
}

// 获取当前抽卡记录数据数量
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_TABLE_DRAWS);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $residualDrawsCount = $result['count'];
} catch (Exception $e) {
    $residualDrawsCount = '未知';
    $message = "无法获取抽卡记录数据数量: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 清理无用数据</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background-color: #343a40;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            width: 1.25rem;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
    </style>
</head>
<body>
    <!-- 侧边栏导航 -->
    <?php include 'sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="bi bi-trash me-2"></i>清理无用数据</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>返回后台
                </a>
            </div>
        </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-info-circle me-2"></i><?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">清理已售出市场数据</h5>
                        <span class="badge bg-primary rounded-pill"><?= $residualMarketCount ?></span>
                    </div>
                    <div class="card-body">
                        <p class="card-text">清理已售出的市场列表数据，这些数据不会再被使用。</p>
                        <form method="post" onsubmit="return confirm('确定要清理所有已售出的市场数据吗？此操作不可恢复！')">
                            <input type="hidden" name="action" value="clean_sold">
                            <button type="submit" name="clean_sold" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i>清理已售出数据
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">清理抽卡记录数据</h5>
                        <span class="badge bg-primary rounded-pill"><?= $residualDrawsCount ?></span>
                    </div>
                    <div class="card-body">
                        <p class="card-text">清理用户的抽卡记录数据，这些数据不会再被使用。</p>
                        <form method="post" onsubmit="return confirm('确定要清理所有抽卡记录数据吗？此操作不可恢复！')">
                            <input type="hidden" name="action" value="clean_draws">
                            <button type="submit" name="clean_draws" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i>清理抽卡记录
                            </button>
                        </form>
                    </div>
                </div>
            </main>

    <!-- Bootstrap 5.1.3 JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>