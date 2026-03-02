<?php
/**
 * صفحة تسجيل الدخول - Shashety IPTV
 * محسّنة للأمان والأداء
 */

require_once 'config.php';

if (isAdminLoggedIn()) {
    redirect('admin.php');
}

$error = '';
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttempt = $_SESSION['last_attempt'] ?? 0;

$lockoutTime = 300;
$maxAttempts = 5;

if ($loginAttempts >= $maxAttempts) {
    $timePassed = time() - $lastAttempt;
    if ($timePassed < $lockoutTime) {
        $remainingTime = ceil(($lockoutTime - $timePassed) / 60);
        $error = "تم تجاوز عدد المحاولات. يرجى الانتظار {$remainingTime} دقيقة.";
    } else {
        $_SESSION['login_attempts'] = 0;
        $loginAttempts = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loginAttempts < $maxAttempts) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['login_attempts'] = 0;

                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);

                logActivity('تسجيل دخول ناجح', "المستخدم: {$username}");
                redirect('admin.php');
            } else {
                $_SESSION['login_attempts'] = $loginAttempts + 1;
                $_SESSION['last_attempt'] = time();
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                logActivity('محاولة تسجيل دخول فاشلة', "المستخدم: {$username}");
            }
        } catch(PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'حدث خطأ في عملية تسجيل الدخول';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>تسجيل الدخول — Shashety IPTV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --red: #e50914;
            --red-dark: #b20710;
            --red-glow: rgba(229, 9, 20, 0.35);
            --gold: #f5c518;
            --white: #ffffff;
            --off-white: #e5e5e5;
            --gray-light: #a3a3a3;
            --gray-mid: #6b6b6b;
            --surface: rgba(0, 0, 0, 0.75);
            --surface-hover: rgba(20, 20, 20, 0.9);
            --border: rgba(255,255,255,0.12);
            --input-bg: rgba(255,255,255,0.07);
            --input-bg-focus: rgba(255,255,255,0.12);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html { font-size: 16px; }

        body {
            font-family: 'Cairo', sans-serif;
            background: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: var(--white);
        }

        /* ── Cinematic Background ── */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        /* Film grid overlay */
        .bg-grid {
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridDrift 30s linear infinite;
        }

        @keyframes gridDrift {
            0%   { background-position: 0 0; }
            100% { background-position: 60px 60px; }
        }

        /* Radial depth vignette */
        .bg-vignette {
            background: radial-gradient(ellipse 80% 80% at 50% 50%,
                transparent 0%,
                rgba(0,0,0,0.4) 60%,
                rgba(0,0,0,0.85) 100%);
        }

        /* Red cinematic accent */
        .bg-accent {
            background:
                radial-gradient(ellipse 50% 40% at 15% 20%, rgba(229,9,20,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 40% 50% at 85% 75%, rgba(180,0,0,0.12) 0%, transparent 60%);
            animation: accentBreath 8s ease-in-out infinite alternate;
        }

        @keyframes accentBreath {
            0%   { opacity: 0.6; }
            100% { opacity: 1; }
        }

        /* Scanlines for cinematic texture */
        .bg-scanlines {
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0,0,0,0.08) 2px,
                rgba(0,0,0,0.08) 4px
            );
            pointer-events: none;
        }

        /* Film grain noise */
        .bg-noise {
            opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            background-size: 200px 200px;
            animation: grainShift 0.5s steps(1) infinite;
        }

        @keyframes grainShift {
            0%   { background-position: 0 0; }
            25%  { background-position: -50px -25px; }
            50%  { background-position: 25px 50px; }
            75%  { background-position: -25px 75px; }
            100% { background-position: 50px -50px; }
        }

        /* ── Top navbar strip ── */
        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 22px 48px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.9) 0%, transparent 100%);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-wordmark {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.2rem;
            letter-spacing: 0.08em;
            color: var(--red);
            text-shadow: 0 0 30px var(--red-glow);
            user-select: none;
        }

        .brand-badge {
            font-family: 'Cairo', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--gray-light);
            border: 1px solid var(--gray-mid);
            padding: 3px 10px;
            border-radius: 3px;
        }

        /* ── Card ── */
        .login-card {
            position: relative;
            z-index: 5;
            width: 100%;
            max-width: 440px;
            margin: 0 20px;
            background: var(--surface);
            backdrop-filter: blur(24px) saturate(1.2);
            -webkit-backdrop-filter: blur(24px) saturate(1.2);
            border-radius: 6px;
            border: 1px solid var(--border);
            padding: 52px 48px 44px;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 32px 80px rgba(0,0,0,0.8),
                0 0 60px rgba(229,9,20,0.06);
            animation: cardRise 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes cardRise {
            from {
                opacity: 0;
                transform: translateY(32px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Red top accent line */
        .card-accent-line {
            position: absolute;
            top: -1px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            border-radius: 0 0 2px 2px;
        }

        /* ── Header ── */
        .card-header {
            margin-bottom: 36px;
            animation: fadeSlide 0.6s 0.15s cubic-bezier(0.16,1,0.3,1) both;
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--white);
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: var(--gray-light);
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-subtitle::before {
            content: '';
            display: inline-block;
            width: 18px;
            height: 2px;
            background: var(--red);
            border-radius: 1px;
            flex-shrink: 0;
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateX(12px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── Error Alert ── */
        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: rgba(229, 9, 20, 0.12);
            border: 1px solid rgba(229, 9, 20, 0.4);
            border-right: 3px solid var(--red);
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 28px;
            animation: alertIn 0.4s cubic-bezier(0.16,1,0.3,1);
        }

        @keyframes alertIn {
            from { opacity: 0; transform: translateX(8px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .alert-icon {
            color: var(--red);
            font-size: 1rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .alert-text {
            font-size: 0.9rem;
            color: #ff6b75;
            line-height: 1.5;
            font-weight: 500;
        }

        /* ── Form ── */
        form {
            animation: fadeSlide 0.6s 0.25s cubic-bezier(0.16,1,0.3,1) both;
        }

        .field {
            margin-bottom: 20px;
        }

        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--gray-light);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .field-label i {
            font-size: 0.75rem;
            color: var(--red);
        }

        .input-wrap {
            position: relative;
        }

        .field-input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            color: var(--white);
            font-family: 'Cairo', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            padding: 14px 16px;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .field-input::placeholder {
            color: rgba(255,255,255,0.22);
            font-weight: 400;
        }

        .field-input:focus {
            background: var(--input-bg-focus);
            border-color: rgba(229,9,20,0.7);
            box-shadow: 0 0 0 3px rgba(229,9,20,0.12), 0 0 20px rgba(229,9,20,0.08);
        }

        .field-input:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Password toggle */
        .toggle-pw {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-mid);
            cursor: pointer;
            font-size: 0.95rem;
            padding: 4px;
            transition: color 0.2s;
            z-index: 2;
        }

        .toggle-pw:hover { color: var(--off-white); }

        #password { padding-left: 42px; }

        /* ── Attempts indicator ── */
        .attempts-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            padding: 10px 14px;
            background: rgba(245, 197, 24, 0.07);
            border: 1px solid rgba(245, 197, 24, 0.2);
            border-radius: 4px;
        }

        .attempts-dots {
            display: flex;
            gap: 5px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gray-mid);
            transition: background 0.3s;
        }

        .dot.used { background: var(--red); }

        .attempts-text {
            font-size: 0.78rem;
            color: var(--gold);
            margin-right: auto;
        }

        /* ── Submit button ── */
        .btn-submit {
            width: 100%;
            margin-top: 8px;
            padding: 16px 24px;
            background: var(--red);
            border: none;
            border-radius: 4px;
            color: var(--white);
            font-family: 'Cairo', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }

        .btn-submit:hover:not(:disabled) {
            background: #f40612;
            box-shadow: 0 8px 30px rgba(229,9,20,0.5);
            transform: translateY(-1px);
        }

        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(229,9,20,0.4);
        }

        .btn-submit:disabled {
            background: #4a0a0f;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-submit .btn-icon { font-size: 0.95rem; }

        /* Loading spinner */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Divider ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 20px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider-text {
            font-size: 0.72rem;
            color: var(--gray-mid);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* ── Footer strip ── */
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            animation: fadeSlide 0.6s 0.35s cubic-bezier(0.16,1,0.3,1) both;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--gray-mid);
        }

        .footer-item i {
            font-size: 0.72rem;
            color: var(--red);
        }

        .footer-sep {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: var(--gray-mid);
            opacity: 0.4;
        }

        /* ── Bottom bar ── */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 18px 48px;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 100%);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bottom-text {
            font-size: 0.75rem;
            color: var(--gray-mid);
            text-align: center;
        }

        /* ── Focus ring for accessibility ── */
        :focus-visible {
            outline: 2px solid var(--red);
            outline-offset: 3px;
        }

        /* ── Responsive ── */
        @media (max-width: 520px) {
            .top-bar { padding: 16px 24px; }
            .login-card { padding: 44px 28px 36px; }
            .card-title { font-size: 1.7rem; }
            .brand-wordmark { font-size: 1.8rem; }
            .bottom-bar { padding: 14px 24px; }
        }

        @media (max-width: 360px) {
            .login-card { padding: 36px 20px 28px; }
            .card-footer { flex-direction: column; gap: 8px; }
            .footer-sep { display: none; }
        }
    </style>
</head>
<body>

    <!-- Background layers -->
    <div class="bg-layer bg-grid"></div>
    <div class="bg-layer bg-vignette"></div>
    <div class="bg-layer bg-accent"></div>
    <div class="bg-layer bg-scanlines"></div>
    <div class="bg-layer bg-noise"></div>

    <!-- Top bar -->
    <header class="top-bar">
        <span class="brand-wordmark">SHASHETY</span>
        <span class="brand-badge">Admin Portal</span>
    </header>

    <!-- Login card -->
    <main class="login-card" role="main">
        <div class="card-accent-line" aria-hidden="true"></div>

        <div class="card-header">
            <h1 class="card-title">تسجيل الدخول</h1>
            <p class="card-subtitle">لوحة التحكم الإدارية — IPTV</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error" role="alert" aria-live="assertive">
            <i class="fas fa-exclamation-triangle alert-icon" aria-hidden="true"></i>
            <span class="alert-text"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($loginAttempts > 0 && $loginAttempts < $maxAttempts): ?>
        <div class="attempts-bar" role="status" aria-live="polite">
            <div class="attempts-dots" aria-hidden="true">
                <?php for ($i = 0; $i < $maxAttempts; $i++): ?>
                    <div class="dot <?php echo $i < $loginAttempts ? 'used' : ''; ?>"></div>
                <?php endfor; ?>
            </div>
            <span class="attempts-text">
                <?php echo $maxAttempts - $loginAttempts; ?> محاولات متبقية
            </span>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" id="loginForm" novalidate>
            <div class="field">
                <label class="field-label" for="username">
                    <i class="fas fa-user" aria-hidden="true"></i>
                    اسم المستخدم
                </label>
                <div class="input-wrap">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="field-input"
                        placeholder="أدخل اسم المستخدم"
                        autocomplete="username"
                        spellcheck="false"
                        required
                        autofocus
                        <?php echo ($loginAttempts >= $maxAttempts) ? 'disabled' : ''; ?>
                    >
                </div>
            </div>

            <div class="field">
                <label class="field-label" for="password">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    كلمة المرور
                </label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="field-input"
                        placeholder="أدخل كلمة المرور"
                        autocomplete="current-password"
                        required
                        <?php echo ($loginAttempts >= $maxAttempts) ? 'disabled' : ''; ?>
                    >
                    <button
                        type="button"
                        class="toggle-pw"
                        id="togglePw"
                        aria-label="إظهار/إخفاء كلمة المرور"
                        tabindex="-1"
                    >
                        <i class="fas fa-eye" id="eyeIcon" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="btn-submit"
                id="submitBtn"
                <?php echo ($loginAttempts >= $maxAttempts) ? 'disabled' : ''; ?>
            >
                <span class="spinner" id="spinner" aria-hidden="true"></span>
                <i class="fas fa-arrow-left btn-icon" id="btnIcon" aria-hidden="true"></i>
                <span id="btnLabel">دخول</span>
            </button>
        </form>

        <div class="divider" aria-hidden="true">
            <span class="divider-text">معلومات الجلسة</span>
        </div>

        <footer class="card-footer">
            <div class="footer-item">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <span>اتصال آمن</span>
            </div>
            <div class="footer-sep" aria-hidden="true"></div>
            <div class="footer-item">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <span>جلسة مشفّرة</span>
            </div>
            <div class="footer-sep" aria-hidden="true"></div>
            <div class="footer-item">
                <i class="fas fa-ban" aria-hidden="true"></i>
                <span>وصول محظور</span>
            </div>
        </footer>
    </main>

    <!-- Bottom bar -->
    <div class="bottom-bar" aria-hidden="true">
        <p class="bottom-text">© <?php echo date('Y'); ?> Shashety IPTV &mdash; جميع الحقوق محفوظة</p>
    </div>

    <script>
        /* ── Password toggle ── */
        const togglePw = document.getElementById('togglePw');
        const pwInput  = document.getElementById('password');
        const eyeIcon  = document.getElementById('eyeIcon');

        togglePw?.addEventListener('click', () => {
            const show = pwInput.type === 'password';
            pwInput.type = show ? 'text' : 'password';
            eyeIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });

        /* ── Loading state on submit ── */
        const form      = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const spinner   = document.getElementById('spinner');
        const btnIcon   = document.getElementById('btnIcon');
        const btnLabel  = document.getElementById('btnLabel');

        form?.addEventListener('submit', (e) => {
            if (submitBtn.disabled) { e.preventDefault(); return; }

            const user = document.getElementById('username').value.trim();
            const pass = pwInput.value.trim();
            if (!user || !pass) { e.preventDefault(); return; }

            submitBtn.disabled = true;
            spinner.style.display = 'block';
            btnIcon.style.display = 'none';
            btnLabel.textContent  = 'جارٍ التحقق...';
        });

        /* ── Devtools guard ── */
        (function() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'F12' ||
                    (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key)) ||
                    (e.ctrlKey && e.key === 'U')) {
                    e.preventDefault();
                }
            });
            document.addEventListener('contextmenu', (e) => e.preventDefault());
        })();
    </script>
</body>
</html>
