<?php
/**
 * سكريبت التحديث التلقائي - upgrade Shashety IPTV 
 * ⚠️ upgrade system!
 */

require_once 'config.php';

$errors = [];
$success = [];
$warnings = [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shashety IPTV — تحديث النظام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-void: #060606;
            --bg-surface: #111111;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --border: rgba(255,255,255,0.07);
            --border-bright: rgba(255,255,255,0.15);
            --red: #e50914;
            --red-dim: rgba(229,9,20,0.15);
            --red-glow: rgba(229,9,20,0.4);
            --gold: #f5c518;
            --gold-dim: rgba(245,197,24,0.12);
            --green: #46d369;
            --green-dim: rgba(70,211,105,0.12);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.55);
            --text-muted: rgba(255,255,255,0.3);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg-void);
            color: var(--text-primary);
            font-family: 'Cairo', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Cinematic background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(229,9,20,0.08) 0%, transparent 70%),
                radial-gradient(ellipse 40% 40% at 80% 80%, rgba(229,9,20,0.04) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* Scanline texture */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0,0,0,0.03) 2px,
                rgba(0,0,0,0.03) 4px
            );
            pointer-events: none;
            z-index: 0;
        }

        .shell {
            position: relative;
            z-index: 1;
            max-width: 760px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 56px;
            animation: fadeDown 0.7s ease both;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--red);
            padding: 8px 20px 8px 16px;
            border-radius: 4px;
            margin-bottom: 32px;
            box-shadow: 0 0 40px var(--red-glow);
        }

        .logo-badge span {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: white;
        }

        .logo-badge i {
            font-size: 14px;
            color: rgba(255,255,255,0.85);
        }

        .page-title {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .page-title span { color: var(--red); }

        .page-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* ── Version pill ── */
        .version-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-bright);
            border-radius: 100px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-top: 20px;
        }

        .version-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            animation: pulse 2s ease infinite;
        }

        /* ── Cards ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px 32px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }

        .card:nth-child(1) { animation-delay: 0.05s; }
        .card:nth-child(2) { animation-delay: 0.1s; }
        .card:nth-child(3) { animation-delay: 0.15s; }
        .card:nth-child(4) { animation-delay: 0.2s; }

        .card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .card-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--red-dim);
            border: 1px solid rgba(229,9,20,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            color: var(--red);
            flex-shrink: 0;
        }

        .card-icon.warn {
            background: var(--gold-dim);
            border-color: rgba(245,197,24,0.2);
            color: var(--gold);
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .card-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Steps list ── */
        .steps-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width: 500px) { .steps-grid { grid-template-columns: 1fr; } }

        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .step-item i {
            color: var(--red);
            font-size: 12px;
            width: 16px;
            text-align: center;
        }

        /* ── Warning list ── */
        .warn-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .warn-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .warn-list li i {
            color: var(--gold);
            margin-top: 2px;
            flex-shrink: 0;
        }

        code {
            background: var(--bg-elevated);
            border: 1px solid var(--border-bright);
            border-radius: 4px;
            padding: 1px 7px;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 11px;
            color: var(--gold);
        }

        /* ── CTA Button ── */
        .cta-wrap {
            margin-top: 8px;
            animation: fadeUp 0.5s 0.3s ease both;
        }

        .btn-launch {
            width: 100%;
            background: var(--red);
            color: white;
            border: none;
            padding: 18px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Cairo', sans-serif;
            letter-spacing: 0.02em;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 24px var(--red-glow);
        }

        .btn-launch::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .btn-launch:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px var(--red-glow);
        }

        .btn-launch:active { transform: translateY(0); }

        .btn-launch:disabled {
            background: #2a2a2a;
            color: var(--text-muted);
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        /* ── Progress ── */
        .progress-wrap {
            margin: 24px 0;
            animation: fadeUp 0.4s ease both;
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .progress-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .progress-pct {
            font-size: 13px;
            font-weight: 700;
            color: var(--red);
            font-variant-numeric: tabular-nums;
        }

        .progress-track {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 100px;
            height: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 100px;
            background: linear-gradient(90deg, #e50914, #ff4d4d);
            width: 0%;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 12px rgba(229,9,20,0.6);
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 20px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4));
            border-radius: 100px;
        }

        /* ── Result messages ── */
        .result-msg {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 12px;
            line-height: 1.5;
        }

        .result-msg.ok {
            background: var(--green-dim);
            border: 1px solid rgba(70,211,105,0.2);
            color: var(--green);
        }

        .result-msg.warn {
            background: var(--gold-dim);
            border: 1px solid rgba(245,197,24,0.2);
            color: var(--gold);
        }

        .result-msg.err {
            background: var(--red-dim);
            border: 1px solid rgba(229,9,20,0.25);
            color: #ff6b6b;
        }

        .result-msg i { flex-shrink: 0; margin-top: 1px; }

        /* ── Step result card (post-run) ── */
        .step-result-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 28px;
            margin-bottom: 12px;
            animation: fadeUp 0.4s ease both;
        }

        .step-result-card h3 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-secondary);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-num {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px 7px;
            font-size: 10px;
            color: var(--text-muted);
        }

        /* ── Summary card ── */
        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border-bright);
            border-radius: 12px;
            padding: 28px 32px;
            margin-top: 24px;
            animation: fadeUp 0.5s 0.1s ease both;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin: 20px 0;
        }

        .stat {
            text-align: center;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 12px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-number.ok { color: var(--green); }
        .stat-number.warn { color: var(--gold); }
        .stat-number.err { color: #ff6b6b; }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .success-headline {
            font-size: 18px;
            font-weight: 700;
            color: var(--green);
            text-align: center;
            margin-bottom: 8px;
        }

        .next-steps {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
        }

        .next-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: border-color 0.2s, color 0.2s;
        }

        .next-item:hover {
            border-color: var(--border-bright);
            color: var(--text-primary);
        }

        .next-item i { color: var(--red); width: 16px; text-align: center; }

        /* ── Footer ── */
        .footer {
            text-align: center;
            margin-top: 48px;
            font-size: 12px;
            color: var(--text-muted);
            letter-spacing: 0.04em;
            animation: fadeUp 0.5s 0.4s ease both;
        }

        .footer span { color: var(--red); }

        /* ── Animations ── */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>
</head>
<body>
<div class="shell">

    <!-- Header -->
    <div class="header">
        <div class="logo-badge">
            <i class="fas fa-film"></i>
            <span>Shashety IPTV</span>
        </div>
        <h1 class="page-title">تحديث <span>قاعدة البيانات</span></h1>
        <p class="page-subtitle">ترقية النظام إلى الإصدار الأحدث</p>
        <div class="version-pill">
            <span class="dot"></span>
            الإصدار v1.0.2
        </div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade'])):
    ?>

    <!-- Progress -->
    <div class="progress-wrap">
        <div class="progress-meta">
            <span class="progress-label">جاري التحديث</span>
            <span class="progress-pct" id="pct">0%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="bar"></div>
        </div>
    </div>

    <?php
        // ── Step 1 ──
        echo '<div class="step-result-card">';
        echo '<h3><span class="step-num">01</span> إنشاء جدول المستخدمين التجريبيين</h3>';
        try {
            $sql = "CREATE TABLE IF NOT EXISTS trial_users (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $pdo->exec($sql);
            echo '<div class="result-msg ok"><i class="fas fa-circle-check"></i><span>تم إنشاء جدول trial_users بنجاح</span></div>';
            $success[] = 'جدول المستخدمين';
        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'already exists') !== false) {
                echo '<div class="result-msg warn"><i class="fas fa-triangle-exclamation"></i><span>جدول trial_users موجود بالفعل — تم تخطيه</span></div>';
                $warnings[] = 'جدول المستخدمين موجود';
            } else {
                echo '<div class="result-msg err"><i class="fas fa-circle-xmark"></i><span>' . htmlspecialchars($e->getMessage()) . '</span></div>';
                $errors[] = 'جدول المستخدمين: ' . $e->getMessage();
            }
        }
        echo '</div>';
        echo '<script>document.getElementById("bar").style.width="25%";document.getElementById("pct").textContent="25%";</script>';

        // ── Step 2 ──
        echo '<div class="step-result-card">';
        echo '<h3><span class="step-num">02</span> إضافة حقل الأيقونة للقنوات</h3>';
        try {
            $sql = "ALTER TABLE channels ADD COLUMN logo_icon VARCHAR(100) DEFAULT 'fas fa-tv' AFTER logo_url";
            $pdo->exec($sql);
            echo '<div class="result-msg ok"><i class="fas fa-circle-check"></i><span>تم إضافة حقل logo_icon بنجاح</span></div>';
            $success[] = 'حقل الأيقونة';
        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo '<div class="result-msg warn"><i class="fas fa-triangle-exclamation"></i><span>حقل logo_icon موجود بالفعل — تم تخطيه</span></div>';
                $warnings[] = 'حقل الأيقونة موجود';
            } else {
                echo '<div class="result-msg err"><i class="fas fa-circle-xmark"></i><span>' . htmlspecialchars($e->getMessage()) . '</span></div>';
                $errors[] = 'حقل الأيقونة: ' . $e->getMessage();
            }
        }
        echo '</div>';
        echo '<script>document.getElementById("bar").style.width="50%";document.getElementById("pct").textContent="50%";</script>';

        // ── Step 3 ──
        echo '<div class="step-result-card">';
        echo '<h3><span class="step-num">03</span> تحديث القنوات الموجودة</h3>';
        try {
            $sql = "UPDATE channels SET logo_icon = 'fas fa-tv' WHERE logo_icon IS NULL OR logo_icon = ''";
            $count = $pdo->exec($sql);
            echo '<div class="result-msg ok"><i class="fas fa-circle-check"></i><span>تم تحديث ' . $count . ' قناة بنجاح</span></div>';
            $success[] = 'تحديث القنوات';
        } catch(PDOException $e) {
            echo '<div class="result-msg err"><i class="fas fa-circle-xmark"></i><span>' . htmlspecialchars($e->getMessage()) . '</span></div>';
            $errors[] = 'تحديث القنوات: ' . $e->getMessage();
        }
        echo '</div>';
        echo '<script>document.getElementById("bar").style.width="75%";document.getElementById("pct").textContent="75%";</script>';

        // ── Step 4 ──
        echo '<div class="step-result-card">';
        echo '<h3><span class="step-num">04</span> إضافة مستخدمين تجريبيين</h3>';
        try {
            $sql = "INSERT IGNORE INTO trial_users (username, password, email, expires_at, notes) VALUES
                ('test1', '\$2y\$10\$YzE3MTI2ZTQ0YjBmNzJhN.qK5N5gGZWJvMDAwMDAwMDAwMDAwMDE', 'test1@example.com', DATE_ADD(NOW(), INTERVAL 7 DAY), 'مستخدم تجريبي لمدة أسبوع'),
                ('test2', '\$2y\$10\$YzE3MTI2ZTQ0YjBmNzJhN.qK5N5gGZWJvMDAwMDAwMDAwMDAwMDE', 'test2@example.com', DATE_ADD(NOW(), INTERVAL 30 DAY), 'مستخدم تجريبي لمدة شهر')";
            $pdo->exec($sql);
            echo '<div class="result-msg ok"><i class="fas fa-circle-check"></i><span>تم إضافة المستخدمين التجريبيين بنجاح</span></div>';
            $success[] = 'المستخدمين التجريبيين';
        } catch(PDOException $e) {
            echo '<div class="result-msg warn"><i class="fas fa-triangle-exclamation"></i><span>' . htmlspecialchars($e->getMessage()) . '</span></div>';
            $warnings[] = 'المستخدمين: ' . $e->getMessage();
        }
        echo '</div>';
        echo '<script>document.getElementById("bar").style.width="100%";document.getElementById("pct").textContent="100%";</script>';

        $allGood = count($errors) === 0;
    ?>

    <!-- Summary -->
    <div class="summary-card">
        <div class="summary-stats">
            <div class="stat">
                <div class="stat-number ok"><?= count($success) ?></div>
                <div class="stat-label">نجح</div>
            </div>
            <div class="stat">
                <div class="stat-number warn"><?= count($warnings) ?></div>
                <div class="stat-label">تحذيرات</div>
            </div>
            <div class="stat">
                <div class="stat-number err"><?= count($errors) ?></div>
                <div class="stat-label">أخطاء</div>
            </div>
        </div>

        <?php if ($allGood): ?>
        <p class="success-headline"><i class="fas fa-circle-check"></i> &nbsp;اكتمل التحديث بنجاح</p>
        <div class="next-steps">
            <a href="update.php" class="next-item">
                <i class="fas fa-arrow-left"></i>
                الذهاب إلى تحديث النظام
            </a>
            <div class="next-item" style="color:#ff6b6b;border-color:rgba(229,9,20,0.25);">
                <i class="fas fa-trash-can"></i>
                 upgrade.php الآن لحماية نظامك
            </div>
        </div>
        <?php else: ?>
        <div class="result-msg err" style="justify-content:center;">
            <i class="fas fa-triangle-exclamation"></i>
            <span>حدثت بعض الأخطاء — راجع التفاصيل أعلاه</span>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- Pre-run: what will happen -->
    <div class="card" style="animation-delay:0.05s">
        <div class="card-header">
            <div class="card-icon"><i class="fas fa-list-check"></i></div>
            <div>
                <div class="card-title">ما سيتم تنفيذه</div>
                <div class="card-subtitle">4 خطوات في العملية</div>
            </div>
        </div>
        <div class="steps-grid">
            <div class="step-item"><i class="fas fa-table"></i>إنشاء جدول المستخدمين التجريبيين</div>
            <div class="step-item"><i class="fas fa-image"></i>إضافة حقل الأيقونة للقنوات</div>
            <div class="step-item"><i class="fas fa-rotate"></i>تحديث القنوات الموجودة</div>
            <div class="step-item"><i class="fas fa-user-plus"></i>إضافة مستخدمين للاختبار</div>
        </div>
    </div>

    <!-- Warnings -->
    <div class="card" style="animation-delay:0.1s">
        <div class="card-header">
            <div class="card-icon warn"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <div class="card-title">ملاحظات مهمة</div>
                <div class="card-subtitle">اقرأها قبل المتابعة</div>
            </div>
        </div>
        <ul class="warn-list">
            <li><i class="fas fa-database"></i> خذ نسخة احتياطية من قاعدة البيانات قبل المتابعة</li>
            <li><i class="fas fa-circle-check"></i> تأكد من وجود قاعدة البيانات <code>iptv_db</code></li>
            <li><i class="fas fa-trash-can"></i> تحديث لحماية نظامك</li>
        </ul>
    </div>

    <!-- CTA -->
    <div class="cta-wrap">
        <form method="POST">
            <button type="submit" name="upgrade" class="btn-launch">
                <i class="fas fa-rocket"></i>&nbsp;&nbsp;بدء التحديث الآن
            </button>
        </form>
    </div>

    <?php endif; ?>

    <div class="footer">
        Shashety IPTV &nbsp;<span>v1.0.2</span>&nbsp; © 2024 — جميع الحقوق محفوظة
    </div>

</div>
</body>
</html>
