-- ════════════════════════════════════════════════════════════════
-- إضافة مشاهدات تجريبية للقنوات
-- Add Test Views to Channels
-- ════════════════════════════════════════════════════════════════

-- تحديث المشاهدات للقنوات الموجودة
UPDATE channels SET views_count = 18 WHERE name LIKE '%beIN SPORTS FHD 1%';
UPDATE channels SET views_count = 5 WHERE name LIKE '%Al-Sharqiya News-1%';
UPDATE channels SET views_count = 2 WHERE name LIKE '%beIN SPORTS FHD 2%';

-- أو يمكنك إضافة مشاهدات عشوائية لجميع القنوات
UPDATE channels SET views_count = FLOOR(RAND() * 50) WHERE views_count = 0;

-- ════════════════════════════════════════════════════════════════
-- ملاحظة: هذا للاختبار فقط!
-- عند الاستخدام الحقيقي، المشاهدات تزيد تلقائياً عند فتح القناة
-- ════════════════════════════════════════════════════════════════
