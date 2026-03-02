-- ════════════════════════════════════════════════════════════════
-- إنشاء جدول المستخدمين
-- Create Users Table for Password Management
-- ════════════════════════════════════════════════════════════════

-- حذف الجدول إن كان موجوداً (اختياري - احذف -- لتفعيل)
-- DROP TABLE IF EXISTS `users`;

-- إنشاء جدول users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة المستخدم الافتراضي
-- Username: admin
-- Password: Ali1992320
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- ════════════════════════════════════════════════════════════════
-- ملاحظات مهمة:
-- ════════════════════════════════════════════════════════════════
-- 1. كلمة المرور الافتراضية: Ali1992320
-- 2. كلمة المرور مُشفّرة بـ password_hash() في PHP
-- 3. لإعادة تعيين كلمة المرور:
--    - ارفع ملف reset_password.php
--    - افتحه في المتصفح
--    - سيتم تحديث كلمة المرور تلقائياً
-- 4. أو نفّذ هذا SQL:
--    UPDATE users 
--    SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
--    WHERE username = 'admin';
-- ════════════════════════════════════════════════════════════════
