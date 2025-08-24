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

// 处理清理请求
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

// 获取当前残留的已售出数据数量
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM market_listings WHERE status = 'sold'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $residualCount = $result['count'];
} catch (Exception $e) {
    $residualCount = '未知';
    $message = "无法获取残留数据数量: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= SITE_TITLE ?> - 清理市场数据</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <?php include 'sidebar.php'; ?>

            <!-- 主内容区 -->
            <main role="main" class="col-md-10 ml-sm-auto px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">清理市场数据</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>清理已售出的市场列表数据</h3>
                    </div>
                    <div class="card-body">
                        <p>当前数据库中残留的已售出商品数量: <strong><?= $residualCount ?></strong></p>
                        <p>这些数据已经售出，但仍然残留在数据库中，占用空间且无实际用途。建议定期清理。</p>
                        
                        <form method="post">
                            <button type="submit" name="clean_sold" class="btn btn-danger" onclick="return confirm('确定要清理所有已售出的市场列表数据吗？此操作不可恢复。')">
                                <i class="bi bi-trash"></i> 清理已售出数据
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>