<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

session_start();

// 检查管理员权限
if (!isAdmin()) {
    $_SESSION['error'] = "需要管理员权限";
    redirect('../index.php');
}

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $adminUsername = trim($_POST['admin_username']);
        $adminPassword = trim($_POST['admin_password']);
        $siteName = trim($_POST['site_name']);
        $siteTitle = trim($_POST['site_title']);
        $siteDescription = trim($_POST['site_description']);
        
        // 验证输入
        if (empty($adminUsername)) {
            throw new Exception("管理员用户名不能为空");
        }
        
        if (empty($siteName)) {
            throw new Exception("网站名称不能为空");
        }
        
        if (empty($siteTitle)) {
            throw new Exception("浏览器标题不能为空");
        }
        
        // 读取配置文件内容
        $configPath = '../config.php';
        $configContent = file_get_contents($configPath);
        
        // 备份原配置文件
        file_put_contents($configPath . '.backup', $configContent);
        
        // 准备配置项
        $configItems = [];
        
        // 数据库配置 (使用默认值)
        $configItems['DB_FILE'] = '__DIR__ . \'/data/card_game.db\'';
        $configItems['DB_TABLE_CARDS'] = '\'cards\'';
        $configItems['DB_TABLE_USERS'] = '\'users\'';
        $configItems['DB_TABLE_DRAWS'] = '\'draws\'';
        $configItems['DB_TABLE_USER_CARDS'] = '\'user_cards\'';
        
        // 游戏配置
        $configItems['ADMIN_USERNAME'] = "'" . addslashes($adminUsername) . "'";
        if (!empty($adminPassword)) {
            $configItems['ADMIN_PASSWORD_HASH'] = "'" . addslashes(password_hash($adminPassword, PASSWORD_DEFAULT)) . "'";
        } else {
            $configItems['ADMIN_PASSWORD_HASH'] = "'" . addslashes(ADMIN_PASSWORD_HASH) . "'";
        }
        
        // 网站信息
        $configItems['SITE_NAME'] = "'" . addslashes($siteName) . "'";
        $configItems['SITE_TITLE'] = "'" . addslashes($siteTitle) . "'";
        $configItems['SITE_DESCRIPTION'] = "'" . addslashes($siteDescription) . "'";
        
        // 错误报告设置保持不变
        $configItems['display_errors'] = "'1'";
        $configItems['error_reporting'] = 'E_ALL';
        
        // 生成新的配置文件内容
        $newConfigContent = "<?php\n";
        $newConfigContent .= "// 数据库配置\n";
        $newConfigContent .= "define('DB_FILE', " . $configItems['DB_FILE'] . ");\n";
        $newConfigContent .= "define('DB_TABLE_CARDS', " . $configItems['DB_TABLE_CARDS'] . ");\n";
        $newConfigContent .= "define('DB_TABLE_USERS', " . $configItems['DB_TABLE_USERS'] . ");\n";
        $newConfigContent .= "define('DB_TABLE_DRAWS', " . $configItems['DB_TABLE_DRAWS'] . ");\n";
        $newConfigContent .= "define('DB_TABLE_USER_CARDS', " . $configItems['DB_TABLE_USER_CARDS'] . ");\n\n";
        
        $newConfigContent .= "// 游戏配置\n";
        $newConfigContent .= "define('ADMIN_USERNAME', " . $configItems['ADMIN_USERNAME'] . ");\n";
        $newConfigContent .= "define('ADMIN_PASSWORD_HASH', " . $configItems['ADMIN_PASSWORD_HASH'] . "); // 管理员密码\n\n";
        
        $newConfigContent .= "// 网站信息\n";
        $newConfigContent .= "define('SITE_NAME', " . $configItems['SITE_NAME'] . ");\n";
        $newConfigContent .= "define('SITE_TITLE', " . $configItems['SITE_TITLE'] . ");\n";
        $newConfigContent .= "define('SITE_DESCRIPTION', " . $configItems['SITE_DESCRIPTION'] . ");\n\n";
        
        $newConfigContent .= "// 错误报告\n";
        $newConfigContent .= "ini_set('display_errors', " . $configItems['display_errors'] . ");\n";
        $newConfigContent .= "error_reporting(" . $configItems['error_reporting'] . ");\n\n";
        
        $newConfigContent .= "?>";
        
        // 验证配置文件语法
        $tempConfig = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tempConfig, $newConfigContent);
        
        // 检查语法是否正确
        $output = [];
        $returnCode = 0;
        exec('php -l ' . escapeshellarg($tempConfig), $output, $returnCode);
        
        if ($returnCode !== 0) {
            unlink($tempConfig);
            throw new Exception("配置文件语法错误: " . implode("\n", $output));
        }
        
        // 写入更新后的配置文件
        if (file_exists($tempConfig)) {
            rename($tempConfig, $configPath);
        } else {
            throw new Exception("配置文件更新失败");
        }
        
        $_SESSION['message'] = "系统设置已更新";
        redirect('system_settings.php');
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 获取当前配置
$currentAdminUsername = ADMIN_USERNAME;
$currentSiteName = SITE_NAME;
$currentSiteTitle = SITE_TITLE;
$currentSiteDescription = SITE_DESCRIPTION;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 系统设置</title>
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
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
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
                    <h1 class="h2"><i class="bi bi-gear me-2"></i>系统设置</h1>
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
                                <h5 class="card-title mb-0"><i class="bi bi-sliders me-2"></i>系统参数配置</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-4">
                                        <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>管理员设置</h5>
                                        
                                        <div class="mb-3">
                                            <label for="admin_username" class="form-label">管理员用户名</label>
                                            <input type="text" class="form-control" id="admin_username" name="admin_username" value="<?= htmlspecialchars($currentAdminUsername) ?>" required>
                                            <div class="form-text">用于登录后台管理系统的用户名</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="admin_password" class="form-label">管理员新密码</label>
                                            <input type="password" class="form-control" id="admin_password" name="admin_password" placeholder="留空则不修改">
                                            <div class="form-text">输入新密码以修改当前管理员密码</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-globe me-2"></i>网站信息设置</h5>
                                        
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">网站名称</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?= htmlspecialchars($currentSiteName) ?>" required>
                                            <div class="form-text">显示在网站各个页面上的网站名称</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_title" class="form-label">浏览器标题</label>
                                            <input type="text" class="form-control" id="site_title" name="site_title" value="<?= htmlspecialchars($currentSiteTitle) ?>" required>
                                            <div class="form-text">显示在浏览器标签页上的标题</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_description" class="form-label">网站描述</label>
                                            <textarea class="form-control" id="site_description" name="site_description" rows="3" required><?= htmlspecialchars($currentSiteDescription) ?></textarea>
                                            <div class="form-text">网站的简短描述，用于SEO和页面元信息</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>修改设置后请检查网站功能是否正常
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i>保存设置
                                        </button>
                                    </div>
                                </form>
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