<?php
/**
 * اختبار زيادة المشاهدات
 * Test Increment Views
 */

require_once 'config.php';

// الحصول على معرف القناة من GET
$channel_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "<h1>اختبار زيادة المشاهدات</h1>";
echo "<p>القناة: $channel_id</p>";
echo "<hr>";

try {
    // عرض القيمة الحالية
    $stmt = $pdo->prepare("SELECT id, name, views_count FROM channels WHERE id = ?");
    $stmt->execute([$channel_id]);
    $channel = $stmt->fetch();
    
    if (!$channel) {
        die("❌ القناة غير موجودة!");
    }
    
    echo "<h2>قبل الزيادة:</h2>";
    echo "<p>الاسم: {$channel['name']}</p>";
    echo "<p>المشاهدات: {$channel['views_count']}</p>";
    echo "<hr>";
    
    // زيادة المشاهدات
    $updateStmt = $pdo->prepare("UPDATE channels SET views_count = views_count + 1 WHERE id = ?");
    $success = $updateStmt->execute([$channel_id]);
    
    if ($success) {
        echo "<p style='color:green;'>✅ تم تحديث العداد بنجاح!</p>";
    } else {
        echo "<p style='color:red;'>❌ فشل التحديث!</p>";
    }
    
    // عرض القيمة الجديدة
    $stmt->execute([$channel_id]);
    $channel = $stmt->fetch();
    
    echo "<h2>بعد الزيادة:</h2>";
    echo "<p>الاسم: {$channel['name']}</p>";
    echo "<p>المشاهدات: <strong style='color:blue;'>{$channel['views_count']}</strong></p>";
    echo "<hr>";
    
    echo "<h3>اختبار API:</h3>";
    echo "<p><a href='api.php?action=increment_view&id=$channel_id' target='_blank'>api.php?action=increment_view&id=$channel_id</a></p>";
    echo "<p><a href='test_increment.php?id=$channel_id'>تحديث الصفحة</a></p>";
    
    // عرض جميع القنوات
    echo "<hr>";
    echo "<h3>جميع القنوات:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>الاسم</th><th>المشاهدات</th><th>اختبار</th></tr>";
    
    $allStmt = $pdo->query("SELECT id, name, views_count FROM channels ORDER BY id");
    while ($ch = $allStmt->fetch()) {
        echo "<tr>";
        echo "<td>{$ch['id']}</td>";
        echo "<td>{$ch['name']}</td>";
        echo "<td><strong>{$ch['views_count']}</strong></td>";
        echo "<td><a href='test_increment.php?id={$ch['id']}'>زيادة +1</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}
