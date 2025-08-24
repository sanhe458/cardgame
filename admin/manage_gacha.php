<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isAdmin()) {
    $_SESSION['error'] = "需要管理员权限";
    redirect('/index.php');
}

// 连接到卡池数据库
$gachaDb = new PDO('sqlite:' . __DIR__ . '/../data/gacha.db');
$gachaDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 连接到主游戏数据库
$db = new PDO('sqlite:' . __DIR__ . '/../data/card_game.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 处理添加/编辑卡池
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pool'])) {
    try {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // 验证输入
        if (empty($name)) {
            throw new Exception("请填写卡池名称");
        }
        
        if ($id) {
            // 更新现有卡池
            $stmt = $gachaDb->prepare("UPDATE gacha_pools SET name = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $description, $isActive, $id]);
            $_SESSION['message'] = "卡池更新成功";
        } else {
            // 添加新卡池
            $stmt = $gachaDb->prepare("INSERT INTO gacha_pools (name, description, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $isActive]);
            $_SESSION['message'] = "卡池添加成功";
        }
        
        redirect('/admin/manage_gacha.php');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('/admin/manage_gacha.php');
    }
}

// 处理删除卡池
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        
        // 先删除关联的卡片
        $stmt = $gachaDb->prepare("DELETE FROM pool_cards WHERE pool_id = ?");
        $stmt->execute([$id]);
        
        // 再删除卡池
        $stmt = $gachaDb->prepare("DELETE FROM gacha_pools WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['message'] = "卡池删除成功";
        redirect('/admin/manage_gacha.php');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('/admin/manage_gacha.php');
    }
}

// 处理为卡池添加卡片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_card_to_pool'])) {
    try {
        $poolId = (int)$_POST['pool_id'];
        $cardId = (int)$_POST['card_id'];
        $probability = (float)$_POST['probability'];
        
        // 验证输入
        if ($probability <= 0) {
            throw new Exception("概率必须大于0");
        }
        
        // 检查卡片是否已存在于卡池中
        $stmt = $gachaDb->prepare("SELECT id FROM pool_cards WHERE pool_id = ? AND card_id = ?");
        $stmt->execute([$poolId, $cardId]);
        
        if ($stmt->fetch()) {
            // 更新现有记录
            $stmt = $gachaDb->prepare("UPDATE pool_cards SET probability = ? WHERE pool_id = ? AND card_id = ?");
            $stmt->execute([$probability, $poolId, $cardId]);
        } else {
            // 添加新记录
            $stmt = $gachaDb->prepare("INSERT INTO pool_cards (pool_id, card_id, probability) VALUES (?, ?, ?)");
            $stmt->execute([$poolId, $cardId, $probability]);
        }
        
        $_SESSION['message'] = "卡片添加成功";
        redirect('/admin/manage_gacha.php?action=edit_pool&id=' . $poolId);
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('/admin/manage_gacha.php?action=edit_pool&id=' . (int)$_POST['pool_id']);
    }
}

// 处理从卡池移除卡片
if (isset($_GET['remove_card'])) {
    try {
        $id = (int)$_GET['remove_card'];
        $poolId = (int)$_GET['pool_id'];
        
        $stmt = $gachaDb->prepare("DELETE FROM pool_cards WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['message'] = "卡片移除成功";
        redirect('/admin/manage_gacha.php?action=edit_pool&id=' . $poolId);
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect('/admin/manage_gacha.php?action=edit_pool&id=' . (int)$_GET['pool_id']);
    }
}

// 获取所有卡池
$pools = $gachaDb->query("SELECT * FROM gacha_pools ORDER BY is_active DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// 获取要编辑的卡池
$editPool = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_pool' && isset($_GET['id'])) {
    $editPoolId = (int)$_GET['id'];
    $stmt = $gachaDb->prepare("SELECT * FROM gacha_pools WHERE id = ?");
    $stmt->execute([$editPoolId]);
    $editPool = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 如果没有找到要编辑的卡池，重定向到卡池管理页面
    if (!$editPool) {
        $_SESSION['error'] = "未找到指定的卡池";
        redirect('/admin/manage_gacha.php');
    }
    
    // 获取卡池中的卡片
    // 先从卡池数据库获取卡片ID和概率
    $stmt = $gachaDb->prepare("SELECT pc.id as pool_card_id, pc.card_id, pc.probability FROM pool_cards pc WHERE pc.pool_id = ? ORDER BY pc.id");
    $stmt->execute([$editPoolId]);
    $poolCardData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 然后从主数据库获取卡片详细信息
    $poolCards = [];
    if (!empty($poolCardData)) {
        $cardIds = array_column($poolCardData, 'card_id');
        $placeholders = str_repeat('?,', count($cardIds) - 1) . '?';
        $stmt = $db->prepare("SELECT * FROM " . DB_TABLE_CARDS . " WHERE id IN ($placeholders) ORDER BY rarity DESC, name ASC");
        $stmt->execute($cardIds);
        $cardDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 合并卡池数据和卡片详细信息
        $cardDetailsMap = [];
        foreach ($cardDetails as $card) {
            $cardDetailsMap[$card['id']] = $card;
        }
        
        foreach ($poolCardData as $poolCard) {
            if (isset($cardDetailsMap[$poolCard['card_id']])) {
                $poolCards[] = array_merge($poolCard, $cardDetailsMap[$poolCard['card_id']]);
            }
        }
    }
    
    // 获取不在卡池中的卡片
    $stmt = $gachaDb->prepare("SELECT card_id FROM pool_cards WHERE pool_id = ?");
    $stmt->execute([$editPoolId]);
    $usedCardIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($usedCardIds)) {
        // 如果卡池中没有卡片，则所有卡片都可用
        $stmt = $db->prepare("SELECT * FROM " . DB_TABLE_CARDS . " ORDER BY rarity DESC, name ASC");
        $stmt->execute();
        $availableCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 排除已在卡池中的卡片
        $placeholders = str_repeat('?,', count($usedCardIds) - 1) . '?';
        $stmt = $db->prepare("SELECT * FROM " . DB_TABLE_CARDS . " WHERE id NOT IN ($placeholders) ORDER BY rarity DESC, name ASC");
        $stmt->execute($usedCardIds);
        $availableCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 获取所有卡片（用于添加到卡池）
$allCards = query("SELECT * FROM " . DB_TABLE_CARDS . " ORDER BY rarity DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 稀有度选项
$rarityOptions = [
    1 => '普通',
    2 => '稀有',
    3 => '史诗',
    4 => '传说'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 卡池管理</title>
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
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
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
        .card-preview { display: flex; align-items: center; }
        .card-image { width: 50px; height: 50px; margin-right: 10px; object-fit: cover; }
        .pool-cards-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .pool-card { border: 1px solid #ddd; border-radius: 5px; padding: 10px; width: 200px; }
        .active-pool { background-color: #e8f5e9; }
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
                    <h1 class="h2"><i class="bi bi-dice me-2"></i>卡池管理</h1>
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

    <?php if (isset($_GET['action']) && $_GET['action'] === 'edit_pool' && $editPool): ?>
        <!-- 编辑卡池卡片 -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">编辑卡池 "<?= htmlspecialchars($editPool['name']) ?>"</h2>
            </div>
            <div class="card-body">
                <a href="/admin/manage_gacha.php" class="btn btn-primary mb-3">返回卡池列表</a>
                
                <h3 class="h5 mt-4 mb-3">卡池中的卡片</h3>
                <?php if (empty($poolCards)): ?>
                    <p>该卡池中暂无卡片</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>卡片</th>
                                    <th>稀有度</th>
                                    <th>概率</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($poolCards as $poolCard): ?>
                                <tr>
                                    <td>
                                        <div class="card-preview">
                                            <?php if (!empty($poolCard['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($poolCard['image_url']) ?>" alt="<?= htmlspecialchars($poolCard['name']) ?>" class="card-image">
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($poolCard['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="rarity-<?= $poolCard['rarity'] ?>">
                                        <?= $rarityOptions[$poolCard['rarity']] ?? '未知' ?>
                                    </td>
                                    <td><?= $poolCard['probability'] ?>%</td>
                                    <td>
                                        <a href="/admin/manage_gacha.php?remove_card=<?= $poolCard['pool_card_id'] ?>&pool_id=<?= $editPool['id'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('确定要从该卡池中移除此卡片吗？')">移除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <h3 class="h5 mt-4 mb-3">添加卡片到卡池</h3>
                <div class="card">
                    <div class="card-body">
                        <form method="post" action="/admin/manage_gacha.php">
                            <input type="hidden" name="add_card_to_pool" value="1">
                            <input type="hidden" name="pool_id" value="<?= $editPool['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="card_id" class="form-label">选择卡片:</label>
                                <select name="card_id" id="card_id" class="form-select" required>
                                    <option value="">请选择卡片</option>
                                    <?php foreach ($availableCards as $card): ?>
                                    <option value="<?= $card['id'] ?>">
                                        <?= htmlspecialchars($card['name']) ?> (<?= $rarityOptions[$card['rarity']] ?? '未知' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="probability" class="form-label">概率 (%):</label>
                                <input type="number" name="probability" id="probability" class="form-control" step="0.01" min="0.01" max="100" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">添加卡片</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- 卡池列表 -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0"><?= $editPool ? '编辑卡池' : '添加新卡池' ?></h2>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="save_pool" value="1">
                    <?php if ($editPool): ?>
                        <input type="hidden" name="id" value="<?= $editPool['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">卡池名称:</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= $editPool ? htmlspecialchars($editPool['name']) : '' ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">描述:</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?= $editPool ? htmlspecialchars($editPool['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= ($editPool && $editPool['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">设为活动卡池</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><?= $editPool ? '更新卡池' : '添加卡池' ?></button>
                    <?php if ($editPool): ?>
                        <a href="/admin/manage_gacha.php" class="btn btn-secondary">取消</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">卡池列表</h2>
            </div>
            <div class="card-body">
                <?php if (empty($pools)): ?>
                    <p>暂无卡池</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>名称</th>
                                    <th>描述</th>
                                    <th>状态</th>
                                    <th>创建时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pools as $pool): ?>
                                <tr class="<?= $pool['is_active'] ? 'active-pool' : '' ?>">
                                    <td><?= htmlspecialchars($pool['name']) ?></td>
                                    <td><?= htmlspecialchars($pool['description']) ?></td>
                                    <td><?= $pool['is_active'] ? '活动中' : '未激活' ?></td>
                                    <td><?= $pool['created_at'] ?></td>
                                    <td>
                                        <a href="/admin/manage_gacha.php?action=edit_pool&id=<?= $pool['id'] ?>" class="btn btn-primary btn-sm">编辑卡片</a>
                                        <a href="/admin/manage_gacha.php?delete=<?= $pool['id'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('确定要删除此卡池吗？这将移除所有关联的卡片设置。')">删除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>