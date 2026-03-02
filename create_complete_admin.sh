#!/bin/bash

# سأنشئ admin.php كامل يدمج التصميم الجديد + النماذج من الملف القديم

cat > /mnt/user-data/outputs/iptv_final_no_ip/admin.php << 'EOFPHP'
<?php
require_once 'config.php';

if(!isAdminLoggedIn()) {
    redirect('login.php');
}

// معالجة إضافة قسم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = sanitizeInput($_POST['category_name']);
    $icon = sanitizeInput($_POST['category_icon']);
    $slug = generateSlug($name);
    
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)");
    if($stmt->execute([$name, $slug, $icon])) {
        $_SESSION['success'] = "✅ تم إضافة القسم بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#categories');
}

// معالجة حذف قسم
if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    if($stmt->execute([$id])) {
        $_SESSION['success'] = "✅ تم حذف القسم بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#categories');
}

// معالجة تعديل قسم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $id = (int)$_POST['category_id'];
    $name = sanitizeInput($_POST['category_name']);
    $icon = sanitizeInput($_POST['category_icon']);
    $slug = generateSlug($name);
    
    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ? WHERE id = ?");
    if($stmt->execute([$name, $slug, $icon, $id])) {
        $_SESSION['success'] = "✅ تم تعديل القسم بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#categories');
}

// معالجة إضافة قناة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_channel'])) {
    $category_id = (int)$_POST['category_id'];
    $name = sanitizeInput($_POST['channel_name']);
    $stream_url = sanitizeInput($_POST['stream_url']);
    $logo_icon = sanitizeInput($_POST['logo_icon'] ?? 'fas fa-tv');
    $slug = generateSlug($name);
    
    $stmt = $pdo->prepare("INSERT INTO channels (category_id, name, slug, stream_url, logo_icon) VALUES (?, ?, ?, ?, ?)");
    if($stmt->execute([$category_id, $name, $slug, $stream_url, $logo_icon])) {
        $_SESSION['success'] = "✅ تم إضافة القناة بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#channels');
}

// معالجة تعديل قناة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_channel'])) {
    $id = (int)$_POST['channel_id'];
    $category_id = (int)$_POST['category_id'];
    $name = sanitizeInput($_POST['channel_name']);
    $stream_url = sanitizeInput($_POST['stream_url']);
    $logo_icon = sanitizeInput($_POST['logo_icon'] ?? 'fas fa-tv');
    
    $stmt = $pdo->prepare("UPDATE channels SET category_id = ?, name = ?, stream_url = ?, logo_icon = ? WHERE id = ?");
    if($stmt->execute([$category_id, $name, $stream_url, $logo_icon, $id])) {
        $_SESSION['success'] = "✅ تم تعديل القناة بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#channels');
}

// معالجة حذف قناة
if (isset($_GET['delete_channel'])) {
    $id = (int)$_GET['delete_channel'];
    $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ?");
    if($stmt->execute([$id])) {
        $_SESSION['success'] = "✅ تم حذف القناة بنجاح";
    }
    redirect($_SERVER['PHP_SELF'] . '#channels');
}

// جلب الإحصائيات
$stats = [];
$stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['channels'] = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$stats['total_views'] = $pdo->query("SELECT SUM(views_count) FROM channels")->fetchColumn() ?: 0;
try {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch(PDOException $e) {
    $stats['users'] = 1;
}

// جلب الأقسام
$categories = $pdo->query("SELECT c.*, COUNT(ch.id) as channel_count FROM categories c LEFT JOIN channels ch ON c.id = ch.category_id GROUP BY c.id ORDER BY c.display_order, c.id")->fetchAll();

// جلب القنوات
$channels = $pdo->query("SELECT ch.*, c.name as category_name FROM channels ch LEFT JOIN categories c ON ch.category_id = c.id ORDER BY ch.category_id, ch.display_order, ch.id")->fetchAll();

// جلب أفضل القنوات
$top_channels = $pdo->query("SELECT name, views_count FROM channels ORDER BY views_count DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - Shashety IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#4cc9f0;--primary-dark:#3aa3c7;--secondary:#00d084;--danger:#ff4444;--warning:#ff9800;}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Cairo',sans-serif;}
        body{background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);min-height:100vh;color:#fff;display:flex;}
        .sidebar{width:280px;background:rgba(0,0,0,0.7);backdrop-filter:blur(20px);padding:30px 20px;border-left:1px solid rgba(255,255,255,0.1);position:fixed;height:100vh;overflow-y:auto;}
        .sidebar::-webkit-scrollbar{width:8px;}
        .sidebar::-webkit-scrollbar-thumb{background:var(--primary);border-radius:10px;}
        .sidebar-header{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:2px solid rgba(76,201,240,0.3);}
        .sidebar-header h2{color:var(--primary);font-size:1.5rem;margin-bottom:5px;}
        .sidebar nav a{display:flex;align-items:center;gap:12px;padding:15px 20px;color:#ccc;text-decoration:none;border-radius:12px;margin-bottom:8px;transition:all 0.3s;}
        .sidebar nav a:hover,.sidebar nav a.active{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;transform:translateX(-5px);}
        .logout-btn{margin-top:30px;width:100%;background:linear-gradient(135deg,var(--danger),#cc0000);color:#fff;border:none;padding:15px;border-radius:12px;cursor:pointer;font-weight:700;}
        .main-content{margin-right:280px;flex:1;padding:40px;max-width:calc(100% - 280px);}
        .top-bar{background:rgba(0,0,0,0.7);backdrop-filter:blur(20px);padding:20px 30px;border-radius:20px;margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;}
        .top-bar h1{font-size:1.8rem;color:var(--primary);}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:30px;}
        .stat-card{background:linear-gradient(135deg,rgba(76,201,240,0.2),rgba(76,201,240,0.05));border:2px solid rgba(76,201,240,0.3);padding:25px;border-radius:20px;transition:all 0.3s;}
        .stat-card:hover{transform:translateY(-5px);}
        .stat-card.green{background:linear-gradient(135deg,rgba(0,208,132,0.2),rgba(0,208,132,0.05));border-color:rgba(0,208,132,0.3);}
        .stat-card.orange{background:linear-gradient(135deg,rgba(255,152,0,0.2),rgba(255,152,0,0.05));border-color:rgba(255,152,0,0.3);}
        .stat-card.red{background:linear-gradient(135deg,rgba(255,68,68,0.2),rgba(255,68,68,0.05));border-color:rgba(255,68,68,0.3);}
        .stat-icon{font-size:3rem;margin-bottom:15px;}
        .stat-value{font-size:2.5rem;font-weight:900;}
        .stat-label{color:#888;}
        .section{display:none;animation:fadeIn 0.5s;}
        .section.active{display:block;}
        .section-title{font-size:1.8rem;color:var(--primary);margin-bottom:25px;}
        .btn{padding:12px 30px;border:none;border-radius:10px;cursor:pointer;font-weight:700;transition:all 0.3s;display:inline-flex;align-items:center;gap:10px;}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;}
        .btn-success{background:linear-gradient(135deg,var(--secondary),#00a86b);color:#fff;}
        .btn-edit{background:linear-gradient(135deg,var(--warning),#f57c00);color:#fff;padding:8px 16px;border-radius:8px;margin:0 5px;border:none;cursor:pointer;}
        .btn-delete{background:linear-gradient(135deg,var(--danger),#cc0000);color:#fff;padding:8px 16px;border-radius:8px;margin:0 5px;border:none;cursor:pointer;}
        .btn-test{background:linear-gradient(135deg,var(--secondary),#00a86b);color:#fff;padding:8px 16px;border-radius:8px;margin:0 5px;border:none;cursor:pointer;}
        table{width:100%;background:rgba(0,0,0,0.4);border-radius:15px;overflow:hidden;border-collapse:collapse;}
        thead{background:linear-gradient(135deg,var(--primary),var(--primary-dark));}
        th,td{padding:15px;text-align:right;}
        th{color:#fff;font-weight:700;}
        td{border-bottom:1px solid rgba(255,255,255,0.05);color:#ccc;}
        tr:hover{background:rgba(76,201,240,0.1);}
        .alert{padding:15px 20px;border-radius:12px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:12px;}
        .alert-success{background:rgba(0,208,132,0.2);border:2px solid rgba(0,208,132,0.5);color:#00d084;}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:99999;align-items:center;justify-content:center;padding:20px;}
        .modal.show{display:flex;}
        .modal-content{background:#1a1a2e;padding:30px;border-radius:20px;max-width:600px;width:100%;max-height:90vh;overflow-y:auto;position:relative;}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;padding-bottom:15px;border-bottom:2px solid rgba(76,201,240,0.3);}
        .modal-header h3{color:var(--primary);font-size:1.5rem;}
        .close-modal{background:var(--danger);border:none;color:#fff;width:35px;height:35px;border-radius:50%;cursor:pointer;font-size:1.5rem;line-height:35px;text-align:center;}
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;margin-bottom:8px;color:var(--primary);font-weight:600;}
        .form-group input,.form-group select{width:100%;padding:12px;background:rgba(255,255,255,0.1);border:2px solid rgba(255,255,255,0.2);border-radius:10px;color:#fff;font-size:1rem;}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--primary);}
        .tools-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:25px;}
        .tool-card{background:linear-gradient(135deg,rgba(76,201,240,0.2),rgba(76,201,240,0.05));border:2px solid rgba(76,201,240,0.4);padding:30px;border-radius:20px;cursor:pointer;transition:all 0.3s;text-align:center;}
        .tool-card:hover{transform:translateY(-8px);}
        .tool-card i{font-size:3.5rem;margin-bottom:20px;display:block;}
        .tool-card h3{font-size:1.3rem;margin-bottom:15px;}
        .tool-card p{color:#ccc;}
        .tool-card.green{background:linear-gradient(135deg,rgba(0,208,132,0.2),rgba(0,208,132,0.05));border-color:rgba(0,208,132,0.4);}
        .tool-card.green i{color:var(--secondary);}
        .tool-card.orange{background:linear-gradient(135deg,rgba(255,152,0,0.2),rgba(255,152,0,0.05));border-color:rgba(255,152,0,0.4);}
        .tool-card.orange i{color:var(--warning);}
        .tool-card.purple{background:linear-gradient(135deg,rgba(156,39,176,0.2),rgba(156,39,176,0.05));border-color:rgba(156,39,176,0.4);}
        .tool-card.purple i{color:#9c27b0;}
        .tool-card.red{background:linear-gradient(135deg,rgba(244,67,54,0.2),rgba(244,67,54,0.05));border-color:rgba(244,67,54,0.4);}
        .tool-card.red i{color:#f44336;}
        #testPlayerModal{position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;display:none;align-items:center;justify-content:center;padding:20px;}
        #testPlayerModal .modal-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);}
        #testPlayerModal .modal-content{position:relative;z-index:1;max-width:1000px;width:100%;background:#1a1a2e;border-radius:15px;}
        #testPlayerModal .modal-header{background:linear-gradient(135deg,var(--primary),var(--primary-dark));padding:20px;border-radius:15px 15px 0 0;}
        #testPlayerModal video{width:100%;height:550px;background:#000;}
        @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-satellite-dish"></i> Shashety IPTV</h2>
            <p>لوحة التحكم</p>
        </div>
        <nav>
            <a href="#dashboard" onclick="showSection('dashboard')" class="active"><i class="fas fa-home"></i> الرئيسية</a>
            <a href="#categories" onclick="showSection('categories')"><i class="fas fa-th-large"></i> الأقسام</a>
            <a href="#channels" onclick="showSection('channels')"><i class="fas fa-tv"></i> القنوات</a>
            <a href="#site-settings" onclick="showSection('site-settings')"><i class="fas fa-cog"></i> إعدادات الموقع</a>
            <a href="#system-tools" onclick="showSection('system-tools')"><i class="fas fa-tools"></i> صيانة النظام</a>
            <a href="#backup" onclick="showSection('backup')"><i class="fas fa-database"></i> النسخ الاحتياطي</a>
        </nav>
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) window.location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
        </button>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-chart-line"></i> لوحة التحكم</h1>
            <div style="display:flex;align-items:center;gap:15px;">
                <span>مرحباً، <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'المدير'); ?></span>
                <i class="fas fa-user-circle" style="font-size:2rem;color:var(--primary);"></i>
            </div>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard -->
        <section id="dashboard" class="section active">
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> الإحصائيات</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="stat-icon fas fa-th-large" style="color:var(--primary);"></i>
                    <div class="stat-value"><?php echo $stats['categories']; ?></div>
                    <div class="stat-label">الأقسام</div>
                </div>
                <div class="stat-card green">
                    <i class="stat-icon fas fa-tv" style="color:var(--secondary);"></i>
                    <div class="stat-value"><?php echo $stats['channels']; ?></div>
                    <div class="stat-label">القنوات</div>
                </div>
                <div class="stat-card orange">
                    <i class="stat-icon fas fa-eye" style="color:var(--warning);"></i>
                    <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                    <div class="stat-label">المشاهدات</div>
                </div>
                <div class="stat-card red">
                    <i class="stat-icon fas fa-users" style="color:var(--danger);"></i>
                    <div class="stat-value"><?php echo $stats['users']; ?></div>
                    <div class="stat-label">المستخدمين</div>
                </div>
            </div>
            <?php if(count($top_channels) > 0): ?>
            <h2 class="section-title" style="margin-top:40px;"><i class="fas fa-fire"></i> أفضل القنوات</h2>
            <table>
                <thead><tr><th>القناة</th><th>المشاهدات</th></tr></thead>
                <tbody>
                    <?php foreach($top_channels as $ch): ?>
                        <tr><td><?php echo htmlspecialchars($ch['name']); ?></td><td><?php echo number_format($ch['views_count']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Categories -->
        <section id="categories" class="section">
            <h2 class="section-title"><i class="fas fa-th-large"></i> إدارة الأقسام</h2>
            <button class="btn btn-primary" onclick="showAddCategoryModal()" style="margin-bottom:20px;">
                <i class="fas fa-plus"></i> إضافة قسم جديد
            </button>
            <?php if(count($categories) > 0): ?>
            <table>
                <thead><tr><th>#</th><th>الاسم</th><th>الأيقونة</th><th>القنوات</th><th>الإجراءات</th></tr></thead>
                <tbody>
                    <?php foreach($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td><i class="<?php echo htmlspecialchars($cat['icon']); ?>" style="font-size:1.5rem;color:var(--primary);"></i></td>
                            <td><?php echo $cat['channel_count']; ?></td>
                            <td>
                                <button class="btn-edit" onclick='editCategory(<?php echo $cat["id"]; ?>,"<?php echo htmlspecialchars($cat["name"], ENT_QUOTES); ?>","<?php echo htmlspecialchars($cat["icon"], ENT_QUOTES); ?>")'>
                                    <i class="fas fa-edit"></i> تعديل
                                </button>
                                <button class="btn-delete" onclick="if(confirm('هل أنت متأكد؟')) window.location.href='?delete_category=<?php echo $cat['id']; ?>'">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Channels -->
        <section id="channels" class="section">
            <h2 class="section-title"><i class="fas fa-tv"></i> إدارة القنوات</h2>
            <button class="btn btn-primary" onclick="showAddChannelModal()" style="margin-bottom:20px;">
                <i class="fas fa-plus"></i> إضافة قناة جديدة
            </button>
            <?php if(count($channels) > 0): ?>
            <table>
                <thead><tr><th>#</th><th>القسم</th><th>القناة</th><th>الشعار</th><th>المشاهدات</th><th>الإجراءات</th></tr></thead>
                <tbody>
                    <?php foreach($channels as $ch): ?>
                        <tr>
                            <td><?php echo $ch['id']; ?></td>
                            <td><?php echo htmlspecialchars($ch['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($ch['name']); ?></td>
                            <td><i class="<?php echo htmlspecialchars($ch['logo_icon']); ?>" style="font-size:2rem;color:var(--primary);"></i></td>
                            <td><?php echo number_format($ch['views_count']); ?></td>
                            <td style="white-space:nowrap;">
                                <button class="btn-test" onclick='testChannel("<?php echo htmlspecialchars($ch["stream_url"], ENT_QUOTES); ?>","<?php echo htmlspecialchars($ch["name"], ENT_QUOTES); ?>")'>
                                    <i class="fas fa-play-circle"></i> تجريب
                                </button>
                                <button class="btn-edit" onclick='editChannel(<?php echo $ch["id"]; ?>,<?php echo $ch["category_id"]; ?>,"<?php echo htmlspecialchars($ch["name"], ENT_QUOTES); ?>","<?php echo htmlspecialchars($ch["stream_url"], ENT_QUOTES); ?>","<?php echo htmlspecialchars($ch["logo_icon"], ENT_QUOTES); ?>")'>
                                    <i class="fas fa-edit"></i> تعديل
                                </button>
                                <button class="btn-delete" onclick="if(confirm('هل أنت متأكد؟')) window.location.href='?delete_channel=<?php echo $ch['id']; ?>'">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Site Settings -->
        <section id="site-settings" class="section">
            <h2 class="section-title"><i class="fas fa-cog"></i> إعدادات الموقع</h2>
            <div style="text-align:center;padding:50px;">
                <button class="btn btn-primary" onclick="window.location.href='admin_site_settings.html'">
                    <i class="fas fa-external-link-alt"></i> فتح صفحة الإعدادات
                </button>
            </div>
        </section>

        <!-- System Tools -->
        <section id="system-tools" class="section">
            <h2 class="section-title"><i class="fas fa-tools"></i> صيانة النظام</h2>
            <div class="tools-grid">
                <div class="tool-card" onclick="window.location.href='admin.php#site-settings'">
                    <i class="fas fa-cog" style="color:var(--primary);"></i>
                    <h3 style="color:var(--primary);">إعدادات الموقع</h3>
                    <p>تخصيص اسم الموقع</p>
                </div>
                <div class="tool-card green" onclick="confirmAction('setup.php')">
                    <i class="fas fa-rocket"></i>
                    <h3 style="color:var(--secondary);">إعداد النظام</h3>
                    <p>إعداد قاعدة البيانات</p>
                </div>
                <div class="tool-card orange" onclick="window.open('settings.php','_blank')">
                    <i class="fas fa-sliders-h"></i>
                    <h3 style="color:var(--warning);">إدارة الإعدادات</h3>
                    <p>إعدادات متقدمة</p>
                </div>
                <div class="tool-card purple" onclick="confirmAction('update_v1.0.4_auto.php')">
                    <i class="fas fa-sync-alt"></i>
                    <h3 style="color:#9c27b0;">التحديث التلقائي</h3>
                    <p>تحديثات قاعدة البيانات</p>
                </div>
                <div class="tool-card red" onclick="confirmAction('upgrade.php')">
                    <i class="fas fa-level-up-alt"></i>
                    <h3 style="color:#f44336;">ترقية النظام</h3>
                    <p>أحدث إصدار</p>
                </div>
            </div>
        </section>

        <!-- Backup -->
        <section id="backup" class="section">
            <h2 class="section-title"><i class="fas fa-database"></i> النسخ الاحتياطي</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:30px;">
                <div style="background:rgba(0,208,132,0.1);border:2px solid rgba(0,208,132,0.3);padding:30px;border-radius:20px;">
                    <h3 style="color:var(--secondary);margin-bottom:25px;"><i class="fas fa-download"></i> تصدير</h3>
                    <a href="backup_system.php?action=export_full" class="btn btn-success" style="display:block;text-align:center;text-decoration:none;margin-bottom:15px;">
                        <i class="fas fa-database"></i> نسخة كاملة
                    </a>
                    <a href="backup_system.php?action=export_channels" class="btn btn-primary" style="display:block;text-align:center;text-decoration:none;">
                        <i class="fas fa-tv"></i> القنوات فقط
                    </a>
                </div>
                <div style="background:rgba(255,152,0,0.1);border:2px solid rgba(255,152,0,0.3);padding:30px;border-radius:20px;">
                    <h3 style="color:var(--warning);margin-bottom:25px;"><i class="fas fa-upload"></i> استعادة</h3>
                    <form action="backup_system.php?action=import" method="POST" enctype="multipart/form-data" onsubmit="return confirm('هل أنت متأكد؟')">
                        <input type="file" name="sql_file" accept=".sql" required style="margin-bottom:15px;">
                        <button type="submit" class="btn btn-success" style="width:100%;"><i class="fas fa-check-circle"></i> استعادة</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> إضافة قسم جديد</h3>
                <button class="close-modal" onclick="closeModal('addCategoryModal')">×</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>اسم القسم</label>
                    <input type="text" name="category_name" required>
                </div>
                <div class="form-group">
                    <label>أيقونة القسم (Font Awesome)</label>
                    <input type="text" name="category_icon" value="fas fa-tv" required>
                </div>
                <button type="submit" name="add_category" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> تعديل القسم</h3>
                <button class="close-modal" onclick="closeModal('editCategoryModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="form-group">
                    <label>اسم القسم</label>
                    <input type="text" name="category_name" id="edit_category_name" required>
                </div>
                <div class="form-group">
                    <label>أيقونة القسم</label>
                    <input type="text" name="category_icon" id="edit_category_icon" required>
                </div>
                <button type="submit" name="edit_category" class="btn btn-success"><i class="fas fa-save"></i> تحديث</button>
            </form>
        </div>
    </div>

    <!-- Add Channel Modal -->
    <div id="addChannelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> إضافة قناة جديدة</h3>
                <button class="close-modal" onclick="closeModal('addChannelModal')">×</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>القسم</label>
                    <select name="category_id" required>
                        <option value="">اختر القسم</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>اسم القناة</label>
                    <input type="text" name="channel_name" required>
                </div>
                <div class="form-group">
                    <label>رابط البث</label>
                    <input type="text" name="stream_url" required>
                </div>
                <div class="form-group">
                    <label>أيقونة القناة</label>
                    <input type="text" name="logo_icon" value="fas fa-tv">
                </div>
                <button type="submit" name="add_channel" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
            </form>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div id="editChannelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> تعديل القناة</h3>
                <button class="close-modal" onclick="closeModal('editChannelModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="channel_id" id="edit_channel_id">
                <div class="form-group">
                    <label>القسم</label>
                    <select name="category_id" id="edit_channel_category" required>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>اسم القناة</label>
                    <input type="text" name="channel_name" id="edit_channel_name" required>
                </div>
                <div class="form-group">
                    <label>رابط البث</label>
                    <input type="text" name="stream_url" id="edit_channel_url" required>
                </div>
                <div class="form-group">
                    <label>أيقونة القناة</label>
                    <input type="text" name="logo_icon" id="edit_channel_icon">
                </div>
                <button type="submit" name="edit_channel" class="btn btn-success"><i class="fas fa-save"></i> تحديث</button>
            </form>
        </div>
    </div>

    <!-- Test Player Modal -->
    <div id="testPlayerModal">
        <div class="modal-overlay" onclick="closeTestPlayer()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-play-circle"></i> <span id="testChannelName">اختبار القناة</span></h2>
                <button onclick="closeTestPlayer()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:35px;height:35px;border-radius:50%;cursor:pointer;">×</button>
            </div>
            <video id="testPlayer" controls autoplay></video>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
        let testHls = null;
        function showSection(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelector('a[href="#'+id+'"]').classList.add('active');
        }
        function showAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.add('show');
        }
        function showAddChannelModal() {
            document.getElementById('addChannelModal').classList.add('show');
        }
        function editCategory(id, name, icon) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_icon').value = icon;
            document.getElementById('editCategoryModal').classList.add('show');
        }
        function editChannel(id, catId, name, url, icon) {
            document.getElementById('edit_channel_id').value = id;
            document.getElementById('edit_channel_category').value = catId;
            document.getElementById('edit_channel_name').value = name;
            document.getElementById('edit_channel_url').value = url;
            document.getElementById('edit_channel_icon').value = icon;
            document.getElementById('editChannelModal').classList.add('show');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        function testChannel(url, name) {
            document.getElementById('testPlayerModal').style.display = 'flex';
            document.getElementById('testChannelName').textContent = name;
            const video = document.getElementById('testPlayer');
            video.pause();
            video.src = '';
            if(Hls.isSupported()) {
                if(testHls) testHls.destroy();
                testHls = new Hls();
                testHls.loadSource(url);
                testHls.attachMedia(video);
                testHls.on(Hls.Events.MANIFEST_PARSED, () => video.play());
            } else if(video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
                video.play();
            }
        }
        function closeTestPlayer() {
            document.getElementById('testPlayerModal').style.display = 'none';
            const video = document.getElementById('testPlayer');
            video.pause();
            video.src = '';
            if(testHls) { testHls.destroy(); testHls = null; }
        }
        function confirmAction(url) {
            if(confirm('هل أنت متأكد؟')) window.location.href = url;
        }
        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') {
                closeTestPlayer();
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
            }
        });
    </script>
</body>
</html>
EOFPHP

echo "✅ تم إنشاء admin.php كامل مع جميع النماذج!"
