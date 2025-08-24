<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isAdmin()) {
    $_SESSION['error'] = "需要管理员权限";
    redirect('../index.php');
}

// 处理添加/编辑卡片
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $rarity = (int)$_POST['rarity'];
        $imageUrl = trim($_POST['image_url']);
        $probability = (float)$_POST['probability'];
        
        // 验证输入
        if (empty($name) || $rarity < 1 || $rarity > 4 || $probability <= 0) {
            throw new Exception("请填写所有必填字段并确保概率大于0");
        }
        
        if ($id) {
            // 更新现有卡片
            query("UPDATE " . DB_TABLE_CARDS . " SET name = ?, rarity = ?, image_url = ?, probability = ? WHERE id = ?", 
                [$name, $rarity, $imageUrl, $probability, $id]);
            $_SESSION['message'] = "卡片更新成功";
        } else {
            // 添加新卡片
            query("INSERT INTO " . DB_TABLE_CARDS . " (name, rarity, image_url, probability) VALUES (?, ?, ?, ?)", 
                [$name, $rarity, $imageUrl, $probability]);
            $_SESSION['message'] = "卡片添加成功";
        }
        
        redirect('../index.php?action=manage_cards');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('../index.php?action=manage_cards');
    }
}

// 处理删除卡片
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        query("DELETE FROM " . DB_TABLE_CARDS . " WHERE id = ?", [$id]);
        $_SESSION['message'] = "卡片删除成功";
        redirect('../index.php?action=manage_cards');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('../index.php?action=manage_cards');
    }
}

// 处理导出卡片
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // 获取所有卡片
    $cards = query("SELECT * FROM " . DB_TABLE_CARDS . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    // 设置响应头
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cards_export.csv"');
    
    // 输出CSV数据
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Rarity', 'Image URL', 'Probability']);
    
    foreach ($cards as $card) {
        fputcsv($output, [
            $card['id'],
            $card['name'],
            $card['rarity'],
            $card['image_url'],
            $card['probability']
        ]);
    }
    
    fclose($output);
    exit;
}

// 处理导入卡片
if (isset($_GET['action']) && $_GET['action'] === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("文件上传失败");
        }
        
        $file = $_FILES['csvFile']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            throw new Exception("无法打开上传的文件");
        }
        
        // 跳过标题行
        fgetcsv($handle);
        
        // 逐行读取并插入数据库
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 5) {
                query("INSERT OR REPLACE INTO " . DB_TABLE_CARDS . " (id, name, rarity, image_url, probability) VALUES (?, ?, ?, ?, ?)", [
                    $data[0], // ID
                    $data[1], // Name
                    $data[2], // Rarity
                    $data[3], // Image URL
                    $data[4]  // Probability
                ]);
            }
        }
        
        fclose($handle);
        $_SESSION['message'] = "卡片导入成功";
        redirect('../index.php?action=manage_cards');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('../index.php?action=manage_cards');
    }
}

// 获取所有卡片
$cards = query("SELECT * FROM " . DB_TABLE_CARDS . " ORDER BY rarity DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 获取要编辑的卡片
$editCard = null;
if (isset($_GET['edit'])) {
    $editCard = query("SELECT * FROM " . DB_TABLE_CARDS . " WHERE id = ?", [(int)$_GET['edit']])->fetch(PDO::FETCH_ASSOC);
    // 如果没有找到要编辑的卡片，重定向到卡片管理页面
    if (!$editCard) {
        $_SESSION['error'] = "未找到指定的卡片";
        redirect('../index.php?action=manage_cards');
    }
}

// 稀有度选项
$rarityOptions = [
    1 => '普通',
    2 => '稀有',
    3 => '史诗',
    4 => '传说',
    5 => '神话'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 卡片管理</title>
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
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }
        .btn-edit {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
            color: #000;
        }
        .btn-edit:hover {
            background-color: #31d2f2;
            border-color: #25cff2;
        }
        .rarity-1 { color: #4CAF50; }
        .rarity-2 { color: #2196F3; }
        .rarity-3 { color: #9C27B0; }
        .rarity-4 { color: #FF9800; }
        .form-container {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
                    <h1 class="h2"><i class="bi bi-card-list me-2"></i>卡片管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="/index.php?action=admin" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left me-1"></i>返回后台
                        </a>
                        <a href="?action=export" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>导出卡片
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('importForm').style.display='block'">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i>导入卡片
                        </button>
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
                        <div class="card form-container">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-card-list me-2"></i><?= $editCard ? '编辑卡片' : '添加新卡片' ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <?php if ($editCard): ?>
                                        <input type="hidden" name="id" value="<?= $editCard['id'] ?>">
                                    <?php endif; ?>
            
            <div class="mb-3">
                <label for="name" class="form-label">卡片名称*</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($editCard['name'] ?? '') ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="rarity" class="form-label">稀有度*</label>
                <select class="form-select" id="rarity" name="rarity" required>
                    <?php foreach ($rarityOptions as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($editCard['rarity'] ?? '') == $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="image_url">图片URL</label>
                <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($editCard['image_url'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="probability">抽中概率*</label>
                <input type="number" id="probability" name="probability" step="0.01" min="0.01" value="<?= htmlspecialchars($editCard['probability'] ?? '0.1') ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary"><?= $editCard ? '更新卡片' : '添加卡片' ?></button>
            
            <?php if ($editCard): ?>
                <a href="/index.php?action=manage_cards" class="btn">取消</a>
            <?php endif; ?>
        </form>
    </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-list me-2"></i>卡片列表</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>名称</th>
                                        <th>稀有度</th>
                                        <th>概率</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cards as $card): ?>
                                        <tr>
                                            <td><?= $card['id'] ?></td>
                                            <td><?= htmlspecialchars($card['name']) ?></td>
                                            <td class="rarity-<?= $card['rarity'] ?>"><?= $rarityOptions[$card['rarity']] ?></td>
                                            <td><?= $card['probability'] ?></td>
                                            <td>
                                                <a href="?action=manage_cards&edit=<?= $card['id'] ?>" class="btn btn-sm btn-edit">编辑</a>
                                                <a href="?action=manage_cards&delete=<?= $card['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除这张卡片吗？')">删除</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div id="importForm" style="display:none;">
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>导入卡片</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" action="?action=import">
                                <div class="mb-3">
                                    <label for="csvFile" class="form-label">选择CSV文件:</label>
                                    <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-primary">导入</button>
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('importForm').style.display='none'">取消</button>
                            </form>
                        </div>
                    </div>
                </div>
</body>
</html>