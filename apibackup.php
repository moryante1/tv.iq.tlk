<?php
/**
 * API للتطبيق - Shashety IPTV
 * نقطة النهاية الرئيسية للبيانات
 */

// رؤوس CORS محسّنة
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// التعامل مع طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// الحصول على الإجراء المطلوب
$action = sanitizeInput($_GET['action'] ?? '');

// تحديد الإجراءات المسموح بها
$allowedActions = ['categories', 'channels', 'channel', 'search', 'featured', 'stats', 'increment_view'];

if (!in_array($action, $allowedActions)) {
    jsonResponse([
        'success' => false,
        'error' => 'إجراء غير صالح'
    ], 400);
}

// توجيه الطلبات حسب الإجراء
switch($action) {
    case 'categories':
        getCategories();
        break;
        
    case 'channels':
        getChannels();
        break;
        
    case 'channel':
        getChannel();
        break;
        
    case 'search':
        searchChannels();
        break;
        
    case 'featured':
        getFeaturedChannels();
        break;
        
    case 'stats':
        getStatistics();
        break;
        
    case 'increment_view':
        incrementViewCount();
        break;
        
    default:
        jsonResponse([
            'success' => false,
            'error' => 'إجراء غير معروف'
        ], 400);
}

/**
 * الحصول على جميع الأقسام مع عدد القنوات
 */
function getCategories() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.slug,
                c.icon,
                c.description,
                COUNT(ch.id) as channel_count
            FROM categories c
            LEFT JOIN channels ch ON c.id = ch.category_id AND ch.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.display_order ASC, c.name ASC
        ");
        
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'count' => count($categories),
            'categories' => $categories
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - getCategories: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في جلب الأقسام'
        ], 500);
    }
}

/**
 * الحصول على القنوات حسب القسم
 */
function getChannels() {
    global $pdo;
    
    try {
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        if ($category_id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'معرف القسم غير صالح'
            ], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ch.*,
                c.name as category_name,
                c.icon as category_icon
            FROM channels ch
            JOIN categories c ON ch.category_id = c.id
            WHERE ch.category_id = ? AND ch.is_active = 1
            ORDER BY ch.display_order ASC, ch.name ASC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$category_id, $limit, $offset]);
        $channels = $stmt->fetchAll();
        
        // الحصول على العدد الكلي
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM channels 
            WHERE category_id = ? AND is_active = 1
        ");
        $countStmt->execute([$category_id]);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse([
            'success' => true,
            'count' => count($channels),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'channels' => $channels
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - getChannels: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في جلب القنوات'
        ], 500);
    }
}

/**
 * الحصول على قناة واحدة
 */
function getChannel() {
    global $pdo;
    
    try {
        $channel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($channel_id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'معرف القناة غير صالح'
            ], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ch.*,
                c.name as category_name,
                c.icon as category_icon
            FROM channels ch
            JOIN categories c ON ch.category_id = c.id
            WHERE ch.id = ? AND ch.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute([$channel_id]);
        $channel = $stmt->fetch();
        
        if (!$channel) {
            jsonResponse([
                'success' => false,
                'error' => 'القناة غير موجودة'
            ], 404);
        }
        
        jsonResponse([
            'success' => true,
            'channel' => $channel
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - getChannel: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في جلب القناة'
        ], 500);
    }
}

/**
 * البحث في القنوات
 */
function searchChannels() {
    global $pdo;
    
    try {
        $query = sanitizeInput($_GET['q'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        if (empty($query) || mb_strlen($query) < 2) {
            jsonResponse([
                'success' => false,
                'error' => 'يجب إدخال حرفين على الأقل للبحث'
            ], 400);
        }
        
        $searchTerm = '%' . $query . '%';
        
        $stmt = $pdo->prepare("
            SELECT 
                ch.*,
                c.name as category_name,
                c.icon as category_icon
            FROM channels ch
            JOIN categories c ON ch.category_id = c.id
            WHERE ch.is_active = 1 
            AND (ch.name LIKE ? OR ch.description LIKE ?)
            ORDER BY ch.views_count DESC, ch.name ASC
            LIMIT ?
        ");
        
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        $channels = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'query' => $query,
            'count' => count($channels),
            'channels' => $channels
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - searchChannels: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في البحث'
        ], 500);
    }
}

/**
 * الحصول على القنوات المميزة
 */
function getFeaturedChannels() {
    global $pdo;
    
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $stmt = $pdo->prepare("
            SELECT 
                ch.*,
                c.name as category_name,
                c.icon as category_icon
            FROM channels ch
            JOIN categories c ON ch.category_id = c.id
            WHERE ch.is_active = 1 AND ch.is_featured = 1
            ORDER BY ch.display_order ASC, ch.views_count DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        $channels = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'count' => count($channels),
            'channels' => $channels
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - getFeaturedChannels: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في جلب القنوات المميزة'
        ], 500);
    }
}

/**
 * الحصول على الإحصائيات العامة
 */
function getStatistics() {
    global $pdo;
    
    try {
        // عدد القنوات النشطة
        $channelsStmt = $pdo->query("SELECT COUNT(*) as total FROM channels WHERE is_active = 1");
        $totalChannels = $channelsStmt->fetch()['total'];
        
        // عدد الأقسام النشطة
        $categoriesStmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1");
        $totalCategories = $categoriesStmt->fetch()['total'];
        
        // إجمالي المشاهدات
        $viewsStmt = $pdo->query("SELECT SUM(views_count) as total FROM channels");
        $totalViews = $viewsStmt->fetch()['total'] ?? 0;
        
        jsonResponse([
            'success' => true,
            'stats' => [
                'total_channels' => $totalChannels,
                'total_categories' => $totalCategories,
                'total_views' => $totalViews,
                'online' => true
            ]
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - getStatistics: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في جلب الإحصائيات'
        ], 500);
    }
}

/**
 * زيادة عداد المشاهدات
 */
function incrementViewCount() {
    global $pdo;
    
    try {
        // قبول GET أو POST
        $channel_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['channel_id']) ? (int)$_POST['channel_id'] : 0);
        
        if ($channel_id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'معرف القناة غير صالح'
            ], 400);
            return;
        }
        
        // التحقق من وجود القناة
        $checkStmt = $pdo->prepare("SELECT id FROM channels WHERE id = ? AND is_active = 1");
        $checkStmt->execute([$channel_id]);
        
        if (!$checkStmt->fetch()) {
            jsonResponse([
                'success' => false,
                'error' => 'القناة غير موجودة'
            ], 404);
            return;
        }
        
        // زيادة عداد المشاهدات - هذا الأهم!
        $stmt = $pdo->prepare("UPDATE channels SET views_count = views_count + 1 WHERE id = ?");
        $success = $stmt->execute([$channel_id]);
        
        if (!$success) {
            jsonResponse([
                'success' => false,
                'error' => 'فشل تحديث العداد'
            ], 500);
            return;
        }
        
        // تسجيل المشاهدة (اختياري - فقط إذا كان الجدول موجود)
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $viewStmt = $pdo->prepare("
                INSERT INTO view_stats (channel_id, ip_address, user_agent, viewed_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $viewStmt->execute([$channel_id, $ip, $user_agent]);
        } catch(PDOException $e) {
            // الجدول غير موجود - تجاهل الخطأ
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'تم تسجيل المشاهدة بنجاح',
            'channel_id' => $channel_id
        ]);
        
    } catch(PDOException $e) {
        error_log("API Error - incrementViewCount: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'error' => 'حدث خطأ في تسجيل المشاهدة'
        ], 500);
    }
}
