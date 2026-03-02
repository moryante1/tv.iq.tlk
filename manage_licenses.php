<?php
/**
 * إدارة الرخص - للمطور فقط
 * License Management - Developer Only
 */

session_start();
require_once 'config.php';
require_once 'license_config.php';

// كلمة مرور خاصة للمطور (غيّرها!)
$DEVELOPER_PASSWORD = 'dev@2024'; // غيّر هذا!

$logged_in = isset($_SESSION['developer_logged_in']) && $_SESSION['developer_logged_in'];

// تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === $DEVELOPER_PASSWORD) {
        $_SESSION['developer_logged_in'] = true;
        $logged_in = true;
    } else {
        $error = 'كلمة مرور خاطئة';
    }
}

// تسجيل الخروج
if (isset($_GET['logout'])) {
    unset($_SESSION['developer_logged_in']);
    header('Location: manage_licenses.php');
    exit;
}

// تعديل حالة الرخصة
if ($logged_in && isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE licenses SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: manage_licenses.php');
    exit;
}

// حذف رخصة
if ($logged_in && isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM licenses WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: manage_licenses.php');
    exit;
}

// جلب جميع الرخص
$licenses = [];
if ($logged_in) {
    $licenses = $pdo->query("SELECT * FROM licenses ORDER BY created_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الرخص - Shashety IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .login-box {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .active { color: #4caf50; }
        .inactive { color: #f44336; }
        .expired { background: #ffebee; }
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 0 2px;
        }
        .btn-toggle { background: #2196f3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        .error {
            background: #f44336;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php if (!$logged_in): ?>
        <div class="login-box">
            <h2><i class="fas fa-lock"></i> تسجيل الدخول</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="كلمة مرور المطور" required>
                </div>
                <button type="submit" name="login" class="btn">
                    <i class="fas fa-sign-in-alt"></i> دخول
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1><i class="fas fa-key"></i> إدارة الرخص</h1>
                <a href="?logout" style="color: #f44336; text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> خروج
                </a>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo count($licenses); ?></h3>
                    <p>إجمالي الرخص</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($licenses, fn($l) => $l['is_active'])); ?></h3>
                    <p>رخص نشطة</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($licenses, fn($l) => !$l['is_active'])); ?></h3>
                    <p>رخص معطلة</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($licenses, fn($l) => $l['expiry_date'] && strtotime($l['expiry_date']) < time())); ?></h3>
                    <p>رخص منتهية</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>Machine ID</th>
                        <th>النوع</th>
                        <th>التفعيل</th>
                        <th>الانتهاء</th>
                        <th>IP</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($licenses as $license): 
                        $is_expired = $license['expiry_date'] && strtotime($license['expiry_date']) < time();
                        $row_class = $is_expired ? 'expired' : '';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $license['id']; ?></td>
                            <td><?php echo htmlspecialchars($license['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($license['phone']); ?></td>
                            <td><code style="font-size: 10px;"><?php echo substr($license['machine_id'], 0, 16); ?>...</code></td>
                            <td><?php echo getLicenseTypeName($license['license_type']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($license['activation_date'])); ?></td>
                            <td><?php echo $license['expiry_date'] ? date('Y-m-d', strtotime($license['expiry_date'])) : 'مفتوح'; ?></td>
                            <td><?php echo htmlspecialchars($license['ip_address']); ?></td>
                            <td class="<?php echo $license['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $license['is_active'] ? 'نشط' : 'معطل'; ?>
                                <?php if ($is_expired): ?><br><small>(منتهي)</small><?php endif; ?>
                            </td>
                            <td>
                                <a href="?toggle&id=<?php echo $license['id']; ?>" class="btn-small btn-toggle">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <a href="?delete&id=<?php echo $license['id']; ?>" class="btn-small btn-delete" 
                                   onclick="return confirm('متأكد من الحذف؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>
