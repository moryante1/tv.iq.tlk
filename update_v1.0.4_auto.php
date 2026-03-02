<?php
/**
 * سكريبت التحديث التلقائي v1.0.4
 * Shashety IPTV - Auto Update Settings
 */

require_once 'config.php';

$errors = [];
$success = [];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث v1.0.4 - Shashety IPTV</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {margin:0;padding:0;box-sizing:border-box;font-family:'Cairo',sans-serif;}
        body{background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;padding:20px;display:flex;align-items:center;justify-content:center;}
        .container{background:white;padding:40px;border-radius:20px;max-width:800px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        h1{color:#667eea;margin-bottom:30px;text-align:center;font-size:2rem;}
        .step{background:#f8f9fa;padding:20px;border-radius:10px;margin-bottom:20px;border-right:4px solid #667eea;}
        .step h3{color:#495057;margin-bottom:10px;}
        .success{background:#d4edda;border-color:#28a745;color:#155724;}
        .error{background:#f8d7da;border-color:#dc3545;color:#721c24;}
        .warning{background:#fff3cd;border-color:#ffc107;color:#856404;}
        .btn{background:#667eea;color:white;border:none;padding:15px 30px;border-radius:10px;cursor:pointer;font-size:1.1rem;font-weight:700;width:100%;margin-top:20px;}
        .btn:hover{background:#5568d3;}
        .progress{background:#e9ecef;height:30px;border-radius:15px;overflow:hidden;margin:20px 0;}
        .progress-bar{background:linear-gradient(90deg,#667eea,#764ba2);height:100%;transition:width 0.3s;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;}
        pre{background:#f8f9fa;padding:10px;border-radius:5px;overflow-x:auto;margin:10px 0;font-size:0.9rem;}
        .icon{font-size:3rem;text-align:center;margin:20px 0;}
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 تحديث v1.0.4 - إعدادات الموقع</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
            
            echo '<div class="progress"><div class="progress-bar" style="width:0%" id="progressBar">0%</div></div>';
            echo '<div id="steps">';
            
            // التحقق من جدول settings
            echo '<div class="step">';
            echo '<h3>الخطوة 1: التحقق من جدول الإعدادات</h3>';
            try {
                $check = $pdo->query("SHOW TABLES LIKE 'settings'");
                if($check->rowCount() > 0) {
                    echo '<p class="success">✅ جدول settings موجود</p>';
                } else {
                    echo '<p class="error">❌ جدول settings غير موجود</p>';
                    echo '<p>يرجى استيراد ملف database.sql الكامل أولاً</p>';
                    $errors[] = 'جدول settings';
                }
            } catch(Exception $e) {
                echo '<p class="error">❌ خطأ: ' . $e->getMessage() . '</p>';
                $errors[] = 'فحص الجدول';
            }
            echo '</div>';
            echo '<script>document.getElementById("progressBar").style.width="25%";document.getElementById("progressBar").textContent="25%";</script>';
            
            if(empty($errors)) {
                // إضافة الإعدادات
                echo '<div class="step">';
                echo '<h3>الخطوة 2: إضافة إعدادات الموقع</h3>';
                try {
                    $settings = [
                        'site_name' => 'Shashety IPTV',
                        'site_description' => 'نظام IPTV احترافي لبث القنوات المباشرة',
                        'site_logo' => '',
                        'welcome_title' => 'مرحباً بك في عالم البث المباشر',
                        'welcome_subtitle' => 'شاهد آلاف القنوات من جميع أنحاء العالم',
                        'footer_text' => 'جميع الحقوق محفوظة © 2024 Shashety IPTV',
                        'contact_phone' => '009647512328848',
                        'contact_email' => 'info@shashety.tv',
                        'contact_facebook' => 'https://facebook.com/xxkpq',
                        'contact_whatsapp' => 'https://wa.me/9647512328848',
                        'theme_color' => '#4cc9f0',
                        'show_categories_count' => '1',
                        'show_channels_count' => '1'
                    ];
                    
                    $count = 0;
                    foreach($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                        $count++;
                    }
                    
                    echo '<p class="success">✅ تم إضافة ' . $count . ' إعداد بنجاح</p>';
                    $success[] = 'الإعدادات';
                    
                } catch(Exception $e) {
                    echo '<p class="error">❌ خطأ: ' . $e->getMessage() . '</p>';
                    $errors[] = 'إضافة الإعدادات';
                }
                echo '</div>';
                echo '<script>document.getElementById("progressBar").style.width="75%";document.getElementById("progressBar").textContent="75%";</script>';
            }
            
            // النتيجة
            echo '<div class="step ' . (empty($errors) ? 'success' : 'error') . '">';
            echo '<h3>📊 نتيجة التحديث</h3>';
            echo '<p><strong>نجح:</strong> ' . count($success) . ' خطوة</p>';
            echo '<p><strong>فشل:</strong> ' . count($errors) . ' خطوة</p>';
            
            if(empty($errors)) {
                echo '<div class="icon">🎉</div>';
                echo '<p style="font-size:1.2rem;text-align:center;margin:20px 0;"><strong>تم التحديث بنجاح!</strong></p>';
                echo '<p style="text-align:center;">يمكنك الآن:</p>';
                echo '<ul style="margin:10px 0 0 20px;line-height:2;">';
                echo '<li>الذهاب إلى <a href="admin.php" style="color:#667eea;">لوحة التحكم</a></li>';
                echo '<li>فتح قسم "إعدادات الموقع"</li>';
                echo '<li>تخصيص موقعك كما تريد!</li>';
                echo '<li><strong>⚠️ احذف هذا الملف (update_v1.0.4_auto.php) للأمان</strong></li>';
                echo '</ul>';
            } else {
                echo '<p style="color:#dc3545;margin-top:20px;">⚠️ حدثت بعض الأخطاء. راجع التفاصيل أعلاه.</p>';
            }
            echo '</div>';
            echo '<script>document.getElementById("progressBar").style.width="100%";document.getElementById("progressBar").textContent="100%";</script>';
            
            echo '</div>';
            
        } else {
        ?>
        
        <div class="icon">🎨</div>
        
        <div class="step">
            <h3>📋 ماذا سيفعل هذا التحديث؟</h3>
            <p>سيتم إضافة الإعدادات التالية لقاعدة البيانات:</p>
            <ul style="margin:15px 0 0 20px;line-height:2;">
                <li>✅ اسم الموقع ووصفه</li>
                <li>✅ النصوص الترحيبية</li>
                <li>✅ معلومات التواصل (هاتف، بريد، فيسبوك، واتساب)</li>
                <li>✅ اللون الأساسي للموقع</li>
                <li>✅ نص الحقوق</li>
                <li>✅ خيارات العرض</li>
            </ul>
        </div>
        
        <div class="step warning">
            <h3>⚠️ تأكد من الآتي قبل المتابعة</h3>
            <ul style="margin:10px 0 0 20px;line-height:2;">
                <li>✓ قاعدة البيانات <code>iptv_db</code> موجودة</li>
                <li>✓ جدول <code>settings</code> موجود</li>
                <li>✓ أخذت نسخة احتياطية من القاعدة</li>
            </ul>
        </div>
        
        <div class="step" style="background:#e7f3ff;border-color:#0066cc;">
            <h3 style="color:#0066cc;">💡 معلومة</h3>
            <p>هذا التحديث آمن ولن يحذف أي بيانات موجودة</p>
            <p style="margin-top:10px;">إذا كانت الإعدادات موجودة بالفعل، سيتم تحديثها فقط</p>
        </div>
        
        <form method="POST">
            <button type="submit" name="update" class="btn">🚀 بدء التحديث</button>
        </form>
        
        <?php } ?>
        
        <p style="text-align:center;margin-top:30px;color:#6c757d;">
            Shashety IPTV v1.0.4 © 2024
        </p>
    </div>
</body>
</html>
