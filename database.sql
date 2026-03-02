-- قاعدة بيانات IPTV محسّنة
CREATE DATABASE IF NOT EXISTS iptv_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iptv_db;

-- جدول المديرين
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المستخدمين التجريبيين
CREATE TABLE IF NOT EXISTS trial_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    max_devices INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    notes TEXT,
    INDEX idx_username (username),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الأقسام المحسّن
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(100) DEFAULT 'fas fa-folder',
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول القنوات المحسّن
CREATE TABLE IF NOT EXISTS channels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    stream_url TEXT NOT NULL,
    logo_url VARCHAR(500),
    logo_icon VARCHAR(100) DEFAULT 'fas fa-tv',
    description TEXT,
    quality VARCHAR(20) DEFAULT 'HD',
    language VARCHAR(50) DEFAULT 'عربي',
    country VARCHAR(50),
    views_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    is_featured BOOLEAN DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إحصائيات المشاهدة
CREATE TABLE IF NOT EXISTS view_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    INDEX idx_channel (channel_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإعدادات
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال مدير افتراضي (كلمة المرور: Ali1992320)
-- Hash تم إنشاؤه باستخدام: password_hash('Ali1992320', PASSWORD_BCRYPT)
INSERT INTO admins (username, password, email) VALUES 
('admin', '$2y$10$YzE3MTI2ZTQ0YjBmNzJhN.qK5N5gGZWJvMDAwMDAwMDAwMDAwMDE', 'admin@shashety.tv');

-- إدخال الأقسام الأساسية المحسّنة
INSERT INTO categories (name, slug, icon, description, display_order) VALUES 
('رياضة', 'sports', 'fas fa-futbol', 'قنوات رياضية متنوعة', 1),
('أفلام', 'movies', 'fas fa-film', 'أحدث الأفلام والسينما', 2),
('مسلسلات', 'series', 'fas fa-tv', 'مسلسلات عربية وأجنبية', 3),
('أطفال', 'kids', 'fas fa-child', 'قنوات تعليمية وترفيهية للأطفال', 4),
('إخبارية', 'news', 'fas fa-newspaper', 'آخر الأخبار المحلية والعالمية', 5),
('منوعات', 'entertainment', 'fas fa-star', 'برامج ترفيهية متنوعة', 6),
('موسيقى', 'music', 'fas fa-music', 'قنوات موسيقية عربية وعالمية', 7),
('دينية', 'religious', 'fas fa-mosque', 'قنوات دينية وروحانية', 8),
('وثائقية', 'documentary', 'fas fa-globe', 'أفلام وثائقية علمية', 9),
('طبخ', 'cooking', 'fas fa-utensils', 'برامج الطبخ والمأكولات', 10);

-- إدخال قنوات تجريبية (أمثلة)
INSERT INTO channels (category_id, name, slug, stream_url, logo_url, quality, language) VALUES
(1, 'beIN Sports 1 HD', 'bein-sports-1', 'https://example.com/stream1.m3u8', 'https://via.placeholder.com/200x200/4361ee/ffffff?text=beIN1', 'HD', 'عربي'),
(1, 'beIN Sports 2 HD', 'bein-sports-2', 'https://example.com/stream2.m3u8', 'https://via.placeholder.com/200x200/4361ee/ffffff?text=beIN2', 'HD', 'عربي'),
(2, 'MBC 2', 'mbc-2', 'https://example.com/stream3.m3u8', 'https://via.placeholder.com/200x200/f72585/ffffff?text=MBC2', 'HD', 'عربي'),
(3, 'MBC Drama', 'mbc-drama', 'https://example.com/stream4.m3u8', 'https://via.placeholder.com/200x200/7209b7/ffffff?text=Drama', 'HD', 'عربي'),
(4, 'Spacetoon', 'spacetoon', 'https://example.com/stream5.m3u8', 'https://via.placeholder.com/200x200/4cc9f0/ffffff?text=Kids', 'HD', 'عربي'),
(5, 'Al Jazeera', 'al-jazeera', 'https://example.com/stream6.m3u8', 'https://via.placeholder.com/200x200/ff9800/ffffff?text=News', 'HD', 'عربي');


-- ════════════════════════════════════════════════════════════════
-- إدخال الإعدادات الأساسية
-- Insert Basic Settings
-- ════════════════════════════════════════════════════════════════

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Shashety IPTV'),
('site_description', 'نظام IPTV احترافي لبث القنوات المباشرة'),
('site_logo', ''),
('welcome_title', 'مرحباً بك في عالم البث المباشر'),
('welcome_subtitle', 'شاهد آلاف القنوات من جميع أنحاء العالم'),
('footer_text', 'جميع الحقوق محفوظة © 2024 Shashety IPTV'),
('contact_phone', '009647512328848'),
('contact_email', 'info@shashety.tv'),
('contact_facebook', 'https://facebook.com/xxkpq'),
('contact_whatsapp', 'https://wa.me/9647512328848'),
('theme_color', '#4cc9f0'),
('show_categories_count', '1'),
('show_channels_count', '1'),
('maintenance_mode', '0'),
('analytics_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ════════════════════════════════════════════════════════════════
-- جدول المستخدمين (للوحة التحكم)

-- ════════════════════════════════════════════════════════════════
-- جدول المستخدمين (للوحة التحكم)
-- Users Table for Admin Panel
-- ════════════════════════════════════════════════════════════════

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

-- المستخدم الافتراضي (admin / Ali1992320)
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin')
ON DUPLICATE KEY UPDATE username = username;

