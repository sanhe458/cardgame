<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isAdmin()) {
    $_SESSION['error'] = "需要管理员权限";
    redirect('../index.php');
}

// 分页设置
$perPageOptions = [10, 20, 50, 100];
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 20;
}
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// 获取总记录数
$totalDraws = query("SELECT COUNT(*) FROM " . DB_TABLE_DRAWS)->fetchColumn();
$totalPages = ceil($totalDraws / $perPage);

// 获取抽卡记录（关联用户和卡片信息）
$draws = query("SELECT d.id, d.draw_time, u.username, c.name, c.rarity 
    FROM " . DB_TABLE_DRAWS . " d 
    JOIN " . DB_TABLE_USERS . " u ON d.user_id = u.id 
    JOIN " . DB_TABLE_CARDS . " c ON d.card_id = c.id 
    ORDER BY d.draw_time DESC 
    LIMIT ? OFFSET ?", [$perPage, $offset])->fetchAll(PDO::FETCH_ASSOC);

// 稀有度名称
$rarityNames = [
    1 => '普通',
    2 => '稀有',
    3 => '史诗',
    4 => '传说'
];

// 稀有度颜色类
$rarityClasses = [
    1 => 'rarity-1',
    2 => 'rarity-2',
    3 => 'rarity-3',
    4 => 'rarity-4'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 抽卡记录</title>
    <!-- Bootstrap 5.1.3 CSS -->
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons 1.7.2 -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css">
    <link href="../styles.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .rarity-1 { color: #4CAF50; }
        .rarity-2 { color: #2196F3; }
        .rarity-3 { color: #9C27B0; }
        .rarity-4 { color: #FF9800; }
    </style>
</head>
<body>
    <!-- 侧边栏导航 -->
    <?php include 'sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="bi bi-collection me-2"></i>抽卡记录</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>返回后台
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">最近抽卡记录</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">每页显示:</label>
                    <select class="form-select w-auto" onchange="location = this.value;">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="?per_page=<?= $option ?>&page=1" <?= $option == $perPage ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>时间</th>
                                <th>用户</th>
                                <th>卡片名称</th>
                                <th>稀有度</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($draws as $draw): ?>
                                <tr>
                                    <td><?= $draw['id'] ?></td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($draw['draw_time'])) ?></td>
                                    <td><?= htmlspecialchars($draw['username']) ?></td>
                                    <td><?= htmlspecialchars($draw['name']) ?></td>
                                    <td class="<?= $rarityClasses[$draw['rarity']] ?>"><?= $rarityNames[$draw['rarity']] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="分页导航">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?per_page=<?= $perPage ?>&page=<?= $page - 1 ?>">上一页</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?per_page=<?= $perPage ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?per_page=<?= $perPage ?>&page=<?= $page + 1 ?>">下一页</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5.1.3 JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>