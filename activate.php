<?php
/**
 * صفحة تفعيل الرخصة
 * License Activation Page
 */

session_start();
require_once 'client_config.php';

$machine_id = getMachineId();
$message = '';
$message_type = '';

// معالجة التفعيل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate'])) {
    $license_key = trim(strtoupper($_POST['license_key']));
    
    if (empty($license_key)) {
        $message = 'يرجى إدخال مفتاح الرخصة';
        $message_type = 'error';
    } else {
        $result = verifyLicenseFromServer($license_key);
        
        if ($result['success'] && $result['valid']) {
            saveLicenseKey($license_key);
            $message = 'تم التفعيل بنجاح! جاري التحويل...';
            $message_type = 'success';
            header("Refresh: 2; url=admin.php");
        } elseif (isset($result['expired']) && $result['expired']) {
            $message = 'الرخصة منتهية الصلاحية. يرجى التواصل مع المطور للتجديد';
            $message_type = 'error';
        } else {
            $message = 'مفتاح الرخصة غير صحيح. تأكد من إدخال المفتاح بشكل صحيح';
            $message_type = 'error';
        }
    }
}

$current_key = getLicenseKey();
$status = null;
if ($current_key) {
    $status = verifyLicenseFromServer($current_key);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل النظام — Shashety IPTV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red:       #e50914;
            --red-dark:  #b0060f;
            --red-glow:  rgba(229, 9, 20, 0.35);
            --bg:        #0a0a0a;
            --surface:   #141414;
            --surface2:  #1e1e1e;
            --surface3:  #2a2a2a;
            --border:    rgba(255,255,255,0.07);
            --text:      #e5e5e5;
            --muted:     #a3a3a3;
            --faint:     #525252;
            --gold:      #f5c518;
            --green:     #46d369;
            --font-ar:   'Cairo', sans-serif;
            --font-disp: 'Bebas Neue', sans-serif;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-ar);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* ── Cinematic Background ── */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }
        .bg-layer::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 50%, rgba(229,9,20,0.08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 20%, rgba(229,9,20,0.05) 0%, transparent 50%),
                radial-gradient(ellipse 100% 100% at 50% 100%, rgba(0,0,0,0.8) 0%, transparent 60%);
        }
        /* Scan lines */
        .bg-layer::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0,0,0,0.03) 2px,
                rgba(0,0,0,0.03) 4px
            );
        }

        /* Floating film grain */
        .grain {
            position: fixed;
            inset: -200%;
            width: 400%;
            height: 400%;
            z-index: 0;
            pointer-events: none;
            opacity: 0.025;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            animation: grain 0.4s steps(1) infinite;
        }
        @keyframes grain {
            0%,100%{transform:translate(0,0)}
            10%{transform:translate(-2%,-3%)}
            20%{transform:translate(3%,2%)}
            30%{transform:translate(-1%,4%)}
            40%{transform:translate(2%,-1%)}
            50%{transform:translate(-3%,3%)}
            60%{transform:translate(1%,-2%)}
            70%{transform:translate(-2%,1%)}
            80%{transform:translate(3%,-3%)}
            90%{transform:translate(-1%,2%)}
        }

        /* ── Layout ── */
        .wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            padding: 24px;
            animation: fadeUp 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(28px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Card ── */
        .card {
            background: rgba(20,20,20,0.92);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 44px 40px;
            backdrop-filter: blur(24px);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.03),
                0 40px 80px rgba(0,0,0,0.7),
                0 0 120px var(--red-glow);
            position: relative;
            overflow: hidden;
        }
        /* top accent bar */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red-dark), var(--red), #ff4444, var(--red));
            background-size: 200%;
            animation: shimmer 3s linear infinite;
        }
        @keyframes shimmer {
            0%   { background-position: 200% center; }
            100% { background-position: -200% center; }
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 36px;
        }
        .logo-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px; height: 72px;
            background: var(--red);
            border-radius: 6px;
            margin-bottom: 18px;
            position: relative;
            box-shadow: 0 0 40px var(--red-glow), inset 0 1px 0 rgba(255,255,255,0.15);
            animation: pulse-logo 3s ease-in-out infinite;
        }
        @keyframes pulse-logo {
            0%,100% { box-shadow: 0 0 40px var(--red-glow), inset 0 1px 0 rgba(255,255,255,0.15); }
            50%      { box-shadow: 0 0 60px rgba(229,9,20,0.5), inset 0 1px 0 rgba(255,255,255,0.15); }
        }
        .logo-mark i {
            font-size: 34px;
            color: white;
        }
        .brand-name {
            font-family: var(--font-disp);
            font-size: 36px;
            letter-spacing: 4px;
            color: #fff;
            line-height: 1;
        }
        .brand-name span { color: var(--red); }
        .brand-sub {
            margin-top: 6px;
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 300;
        }

        /* ── Divider ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .divider span {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
        }

        /* ── Status Banner ── */
        .status-banner {
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid;
            animation: slideIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideIn {
            from { opacity:0; transform:translateX(16px); }
            to   { opacity:1; transform:translateX(0); }
        }
        .status-banner.active {
            background: rgba(70, 211, 105, 0.07);
            border-color: rgba(70, 211, 105, 0.25);
        }
        .status-banner.expired {
            background: rgba(229,9,20,0.07);
            border-color: rgba(229,9,20,0.25);
        }
        .status-banner .icon {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .status-banner.active .icon { color: var(--green); }
        .status-banner.expired .icon { color: var(--red); }
        .status-banner .content h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .status-banner.active .content h4 { color: var(--green); }
        .status-banner.expired .content h4 { color: var(--red); }
        .status-banner .content p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 2px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .badge-green { background: rgba(70,211,105,0.15); color: var(--green); }
        .badge-red   { background: rgba(229,9,20,0.15);  color: var(--red); }

        /* ── Alert Message ── */
        .alert {
            border-radius: 4px;
            padding: 13px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid;
            animation: slideIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        .alert.success {
            background: rgba(70,211,105,0.08);
            border-color: rgba(70,211,105,0.3);
            color: var(--green);
        }
        .alert.error {
            background: rgba(229,9,20,0.08);
            border-color: rgba(229,9,20,0.3);
            color: #ff6b6b;
        }

        /* ── Machine ID Block ── */
        .machine-block {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 18px;
            margin-bottom: 24px;
            position: relative;
        }
        .machine-block-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            margin-bottom: 12px;
            font-weight: 600;
        }
        .machine-block-label i { color: var(--red); font-size: 11px; }
        .machine-id-display {
            background: var(--bg);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 3px;
            padding: 12px 14px;
            font-family: 'Courier New', monospace;
            font-size: 10.5px;
            color: var(--muted);
            word-break: break-all;
            line-height: 1.8;
            letter-spacing: 0.5px;
            position: relative;
        }
        /* blinking cursor effect */
        .machine-id-display::after {
            content: '|';
            color: var(--red);
            animation: blink 1s step-end infinite;
            margin-right: 2px;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        .copy-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: var(--surface3);
            border: 1px solid var(--border);
            border-radius: 3px;
            color: var(--muted);
            font-family: var(--font-ar);
            font-size: 12px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .copy-btn:hover {
            background: var(--surface3);
            border-color: rgba(255,255,255,0.15);
            color: #fff;
        }
        .copy-btn.copied {
            border-color: rgba(70,211,105,0.4);
            color: var(--green);
        }
        .machine-note {
            margin-top: 10px;
            font-size: 11px;
            color: var(--faint);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .machine-note i { font-size: 10px; }

        /* ── Form ── */
        .field {
            margin-bottom: 18px;
        }
        .field label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .field label i { color: var(--red); font-size: 10px; }
        .field input {
            width: 100%;
            padding: 15px 18px;
            background: var(--bg);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 3px;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            letter-spacing: 3px;
            text-align: center;
            text-transform: uppercase;
            transition: all 0.3s;
            outline: none;
            caret-color: var(--red);
        }
        .field input::placeholder {
            color: var(--faint);
            letter-spacing: 1px;
            font-size: 13px;
        }
        .field input:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px var(--red-glow), 0 0 20px var(--red-glow);
        }
        .field input:valid:not(:placeholder-shown) {
            border-color: rgba(70,211,105,0.4);
        }

        /* ── Submit Button ── */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--red);
            border: none;
            border-radius: 3px;
            color: white;
            font-family: var(--font-ar);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .submit-btn:hover {
            background: #f40612;
            transform: translateY(-1px);
            box-shadow: 0 8px 30px var(--red-glow);
        }
        .submit-btn:hover::before { opacity: 1; }
        .submit-btn:active { transform: translateY(0); }
        .submit-btn .btn-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        @keyframes ripple {
            to { transform: scale(4); opacity: 0; }
        }

        /* ── Info Steps ── */
        .info-block {
            margin-top: 24px;
            border: 1px solid var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        .info-header {
            padding: 12px 16px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--faint);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-header i { color: var(--red); }
        .steps {
            list-style: none;
        }
        .steps li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .steps li:last-child { border-bottom: none; }
        .steps li:hover { background: rgba(255,255,255,0.02); }
        .step-num {
            flex-shrink: 0;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(229,9,20,0.12);
            border: 1px solid rgba(229,9,20,0.3);
            color: var(--red);
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .steps li span {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.6;
            padding-top: 2px;
        }

        /* ── Footer ── */
        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 11px;
            color: var(--faint);
            letter-spacing: 0.5px;
        }

        /* ── Animated dots ── */
        .dot-anim::after {
            content: '';
            animation: dots 1.5s steps(3, end) infinite;
        }
        @keyframes dots {
            0%   { content: '.'; }
            33%  { content: '..'; }
            66%  { content: '...'; }
            100% { content: ''; }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
            .brand-name { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>
<div class="grain"></div>

<div class="wrapper">
    <div class="card">

        <!-- Header -->
        <div class="header">
            <div class="logo-mark">
                <i class="fas fa-play"></i>
            </div>
            <div class="brand-name">SHASHETY<span>&nbsp;IPTV</span></div>
            <div class="brand-sub">License Activation Portal</div>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <span <?php if($message_type === 'success'): ?>class="dot-anim"<?php endif; ?>><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Current License Status -->
        <?php if ($status && $status['valid']): ?>
        <div class="status-banner active">
            <div class="icon"><i class="fas fa-shield-check"></i></div>
            <div class="content">
                <h4>الرخصة نشطة</h4>
                <p>
                    النوع: <?php echo htmlspecialchars($status['license']['license_type_name']); ?> &mdash;
                    المتبقي: <strong style="color:#fff;"><?php echo $status['days_left']; ?> <?php echo isset($status['lifetime']) ? '' : 'يوم'; ?></strong>
                    <?php if (!isset($status['lifetime'])): ?>
                        &mdash; تنتهي في: <?php echo date('Y-m-d', strtotime($status['license']['expiry_date'])); ?>
                    <?php endif; ?>
                </p>
                <span class="badge badge-green">&#x25CF; ACTIVE</span>
            </div>
        </div>
        <?php elseif ($status && isset($status['expired'])): ?>
        <div class="status-banner expired">
            <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="content">
                <h4>الرخصة منتهية</h4>
                <p>يرجى التواصل مع المطور لتجديد الرخصة</p>
                <span class="badge badge-red">&#x25CF; EXPIRED</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Machine ID -->
        <div class="machine-block">
            <div class="machine-block-label">
                <i class="fas fa-fingerprint"></i>
                معرف الجهاز — Machine ID
            </div>
            <div class="machine-id-display" id="machineId"><?php echo htmlspecialchars($machine_id); ?></div>
            <button class="copy-btn" id="copyBtn" onclick="copyMachineId()">
                <i class="fas fa-copy"></i>
                نسخ المعرف
            </button>
            <div class="machine-note">
                <i class="fas fa-arrow-up-right-from-square"></i>
                أرسل هذا المعرف للمطور للحصول على مفتاح الرخصة
            </div>
        </div>

        <!-- Form -->
        <form method="POST" id="activateForm">
            <div class="field">
                <label>
                    <i class="fas fa-key"></i>
                    مفتاح الرخصة — License Key
                </label>
                <input
                    type="text"
                    name="license_key"
                    id="licenseInput"
                    placeholder="IPTV-XXXX-XXXX-XXXX"
                    maxlength="19"
                    autocomplete="off"
                    spellcheck="false"
                    required
                    autofocus>
            </div>

            <button type="submit" name="activate" class="submit-btn" id="submitBtn">
                <i class="fas fa-unlock-keyhole"></i>
                تفعيل النظام
            </button>
        </form>

        <!-- Steps -->
        <div class="info-block">
            <div class="info-header">
                <i class="fas fa-circle-info"></i>
                كيفية الحصول على مفتاح الرخصة
            </div>
            <ul class="steps">
                <li><div class="step-num">1</div><span>انسخ معرف الجهاز (Machine ID) أعلاه</span></li>
                <li><div class="step-num">2</div><span>تواصل مع المطور عبر القناة الرسمية</span></li>
                <li><div class="step-num">3</div><span>أرسل له معرف الجهاز</span></li>
                <li><div class="step-num">4</div><span>استلم مفتاح الرخصة وأدخله أعلاه</span></li>
                <li><div class="step-num">5</div><span>اضغط تفعيل وابدأ الاستخدام فوراً</span></li>
            </ul>
        </div>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Shashety IPTV &mdash; All rights reserved
        </div>
    </div>
</div>

<script>
    /* ── Copy Machine ID ── */
    function copyMachineId() {
        const text = document.getElementById('machineId').textContent;
        const btn  = document.getElementById('copyBtn');

        navigator.clipboard.writeText(text).then(() => {
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check"></i> تم النسخ بنجاح!';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="fas fa-copy"></i> نسخ المعرف';
            }, 2500);
        }).catch(() => {
            // Fallback
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        });
    }

    /* ── License Key Auto-format ── */
    const input = document.getElementById('licenseInput');
    input.addEventListener('input', function () {
        let v = this.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        let formatted = '';
        for (let i = 0; i < v.length && i < 16; i++) {
            if (i > 0 && i % 4 === 0) formatted += '-';
            formatted += v[i];
        }
        this.value = formatted;
    });

    /* ── Button ripple ── */
    document.getElementById('submitBtn').addEventListener('click', function (e) {
        const rect = this.getBoundingClientRect();
        const ripple = document.createElement('span');
        ripple.className = 'btn-ripple';
        const size = Math.max(rect.width, rect.height);
        ripple.style.width  = ripple.style.height = size + 'px';
        ripple.style.left   = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top    = (e.clientY - rect.top  - size / 2) + 'px';
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 700);
    });

    /* ── Staggered step animation ── */
    const steps = document.querySelectorAll('.steps li');
    steps.forEach((li, i) => {
        li.style.opacity = '0';
        li.style.transform = 'translateX(10px)';
        li.style.transition = `opacity 0.4s ease ${0.6 + i * 0.08}s, transform 0.4s ease ${0.6 + i * 0.08}s`;
        requestAnimationFrame(() => {
            li.style.opacity = '1';
            li.style.transform = 'translateX(0)';
        });
    });
</script>
</body>
</html>
