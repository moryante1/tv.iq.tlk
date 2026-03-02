<?php
/**
 * إعدادات الاتصال بسيرفر الرخص
 * License Server Connection Settings
 */

// ════════════════════════════════════════════════════════════
// إعدادات سيرفر الرخص - غيّرها!
// ════════════════════════════════════════════════════════════

// URL سيرفر الرخص (غيّره لسيرفرك!)
define('LICENSE_SERVER_URL', 'http://localhost/act/api.php');

// مفتاح API (نفس المفتاح في سيرفر الرخص!)
define('LICENSE_API_KEY', 'your-secret-key-change-this-2024');

// ════════════════════════════════════════════════════════════
// دوال النظام
// ════════════════════════════════════════════════════════════

/**
 * توليد معرف فريد للجهاز + Domain
 * Updated: يتضمن Domain لمنع النسخ على نفس السيرفر
 */
function getMachineId() {
    // عوامل Hardware
    $hardware_factors = [
        php_uname('n'),                    // اسم السيرفر
        $_SERVER['SERVER_ADDR'] ?? '',     // IP السيرفر
        $_SERVER['SERVER_SOFTWARE'] ?? '', // Apache/Nginx version
        $_SERVER['DOCUMENT_ROOT'] ?? '',   // المسار الأساسي
    ];
    
    // عوامل Domain - المهم لمنع النسخ!
    $domain_factors = [
        $_SERVER['HTTP_HOST'] ?? '',       // Domain الحالي (example.com)
        $_SERVER['SERVER_NAME'] ?? '',     // اسم السيرفر
        dirname(__FILE__),                 // مسار الملفات الفعلي
    ];
    
    // دمج كل العوامل
    $all_factors = array_merge($hardware_factors, $domain_factors);
    
    // إنشاء معرف فريد
    $unique_string = implode('|', $all_factors);
    return hash('sha256', $unique_string);
}

/**
 * التحقق من الرخصة من السيرفر
 */
function verifyLicenseFromServer($license_key) {
    $machine_id = getMachineId();
    
    $params = http_build_query([
        'action' => 'verify',
        'machine_id' => $machine_id,
        'license_key' => $license_key,
        'api_key' => LICENSE_API_KEY
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'لا يمكن الاتصال بسيرفر الرخص'
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'خطأ في استجابة السيرفر'
            ];
        }
        
        return $data;
        
    } catch(Exception $e) {
        return [
            'success' => false,
            'valid' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * فحص حالة الرخصة
 */
function checkLicenseStatus() {
    $machine_id = getMachineId();
    
    $params = http_build_query([
        'action' => 'check_status',
        'machine_id' => $machine_id,
        'api_key' => LICENSE_API_KEY
    ]);
    
    $url = LICENSE_SERVER_URL . '?' . $params;
    
    try {
        $response = @file_get_contents($url);
        if ($response === false) return null;
        return json_decode($response, true);
    } catch(Exception $e) {
        return null;
    }
}

/**
 * حفظ مفتاح الرخصة
 */
function saveLicenseKey($license_key) {
    return file_put_contents(__DIR__ . '/license_key.txt', $license_key);
}

/**
 * قراءة مفتاح الرخصة
 */
function getLicenseKey() {
    $file = __DIR__ . '/license_key.txt';
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return null;
}

/**
 * حذف مفتاح الرخصة
 */
function deleteLicenseKey() {
    $file = __DIR__ . '/license_key.txt';
    if (file_exists($file)) {
        return unlink($file);
    }
    return true;
}
