<?php
/**
 * ملف الاتصال بقاعدة البيانات - Shashety IPTV
 * تم التحسين للأداء والأمان
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'iptv_db');
define('DB_USER', 'iptv_user');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الجلسة - يجب أن تكون قبل session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // غيّرها إلى 1 في حالة استخدام HTTPS
    session_start();
}

try {
    // إنشاء اتصال PDO محسّن
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        PDO::ATTR_PERSISTENT         => true, // اتصال دائم لتحسين الأداء
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch(PDOException $e) {
    // تسجيل الخطأ بدلاً من عرضه للمستخدم
    error_log("Database Connection Error: " . $e->getMessage());
    
    // عرض رسالة عامة للمستخدم
    die(json_encode([
        'success' => false,
        'error' => 'عذراً، حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.'
    ]));
}

/**
 * دالة مساعدة لتنظيف المدخلات
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * دالة للتحقق من صحة البريد الإلكتروني
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * دالة لتوليد slug من النص العربي
 */
function generateSlug($text) {
    // تحويل النص إلى أحرف صغيرة
    $text = mb_strtolower($text, 'UTF-8');
    
    // استبدال المسافات بشرطات
    $text = str_replace(' ', '-', $text);
    
    // إزالة الأحرف الخاصة
    $text = preg_replace('/[^a-z0-9\p{Arabic}\-]/u', '', $text);
    
    // إزالة الشرطات المتعددة
    $text = preg_replace('/-+/', '-', $text);
    
    // إزالة الشرطات من البداية والنهاية
    $text = trim($text, '-');
    
    return $text;
}

/**
 * دالة لتسجيل النشاطات
 */
function logActivity($action, $details = '') {
    global $pdo;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // يمكن إضافة جدول logs إذا لزم الأمر
        error_log("Activity: $action | IP: $ip | Details: $details");
        
    } catch(Exception $e) {
        error_log("Logging Error: " . $e->getMessage());
    }
}

/**
 * دالة للتحقق من صلاحية المدير
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * دالة لإعادة توجيه المستخدم
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * دالة لإرجاع استجابة JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
