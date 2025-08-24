<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!hasAdminPrivileges()) {
    redirect('../login.php');
}

// 获取统计信息
$db = getDB();

// 用户总数
$stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_TABLE_USERS);
$stmt->execute();
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 卡牌总数
$stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_TABLE_CARDS);
$stmt->execute();
$totalCards = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 抽卡总数
$stmt = $db->prepare("SELECT COUNT(*) as count FROM " . DB_TABLE_DRAWS);
$stmt->execute();
$totalDraws = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 狗头币总数
$stmt = $db->prepare("SELECT SUM(coins) as total FROM " . DB_TABLE_USERS);
$stmt->execute();
$totalCoins = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 管理后台</title>
    <!-- Bootstrap 5.1.3 CSS -->
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons 1.7.2 -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css">
    <link href="../styles.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-top: 10px;
            color: #0d6efd;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .card-item {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-item h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="bi bi-speedometer2 me-2"></i>管理后台</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="/index.php" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="bi bi-house me-1"></i>返回首页
                </a>
                <a href="/logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>退出登录
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="mb-0 text-center">系统统计</h2>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>用户总数</h3>
                        <div class="stat-value"><?= $totalUsers ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>卡牌总数</h3>
                        <div class="stat-value"><?= $totalCards ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>抽卡总数</h3>
                        <div class="stat-value"><?= $totalDraws ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>狗头币总量</h3>
                        <div class="stat-value"><?= $totalCoins ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2 class="mb-0 text-center">管理菜单</h2>
            </div>
            <div class="card-body">
                <div class="cards-grid">
                    <div class="card-item">
                        <h3>用户管理</h3>
                        <p>管理用户账号和权限</p>
                        <a href="/admin/manage_users.php" class="btn btn-primary">进入</a>
                    </div>
                    
                    <div class="card-item">
                        <h3>卡牌管理</h3>
                        <p>管理游戏卡牌数据</p>
                        <a href="/admin/manage_cards.php" class="btn btn-primary">进入</a>
                    </div>
                    
                    <div class="card-item">
                        <h3>抽卡记录</h3>
                        <p>查看所有抽卡记录</p>
                        <a href="/admin/view_draws.php" class="btn btn-primary">进入</a>
                    </div>
                    
                    <div class="card-item">
                        <h3>系统设置</h3>
                        <p>配置系统参数</p>
                        <a href="/admin/system_settings.php" class="btn btn-primary">进入</a>
                    </div>
                    
                    <div class="card-item">
                        <h3>交易市场</h3>
                        <p>管理交易市场</p>
                        <a href="/admin/manage_market.php" class="btn btn-primary">进入</a>
                    </div>
                    
                    <div class="card-item">
                        <h3>卡池管理</h3>
                        <p>管理卡池和卡片概率</p>
                        <a href="/admin/manage_gacha.php" class="btn btn-primary">进入</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5.1.3 JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>