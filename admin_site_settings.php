<?php
/**
 * admin_site_settings.php — لوحة إعدادات الموقع الكاملة
 */
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// ── جلب الإعدادات ────────────────────────────────────────
function loadSettings($pdo): array {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $s = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $s[$row['setting_key']] = $row['setting_value'];
    }
    return $s;
}
$settings = loadSettings($pdo);

// ── معالجة الحفظ ─────────────────────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error_msg = 'طلب غير صالح. يرجى المحاولة مرة أخرى.';
    } else {
        // كل الحقول المطلوب حفظها
        $fields = [
            'site_name', 'site_description', 'site_logo',
            'welcome_title', 'welcome_subtitle', 'footer_text',
            'contact_phone', 'contact_email',
            'contact_facebook', 'contact_whatsapp',
            'contact_twitter', 'contact_telegram',
            'contact_youtube', 'contact_instagram',
            'theme_color',
            'notification_title', 'notification_body',
        ];

        $upsert = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2"
        );

        $ok = true;
        foreach ($fields as $field) {
            $val = trim($_POST[$field] ?? '');
            if ($field === 'theme_color') {
                $val = preg_match('/^#[0-9A-Fa-f]{3,6}$/', $val) ? $val : '#e50914';
            }
            try {
                $upsert->execute([':k' => $field, ':v' => $val, ':v2' => $val]);
            } catch (Exception $e) {
                $ok = false;
                $error_msg = 'خطأ في الحفظ: ' . $e->getMessage();
                break;
            }
        }

        if ($ok) {
            $settings    = loadSettings($pdo);
            $success_msg = 'تم حفظ جميع الإعدادات بنجاح ✓';
        }
    }
}

// ── CSRF ─────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function v(array $s, string $k, string $def = ''): string {
    return htmlspecialchars($s[$k] ?? $def, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>إعدادات الموقع — لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --red:#e50914; --red-h:#f40612; --red-dim:rgba(229,9,20,.13);
  --bg:#0f0f0f; --bg2:#161616; --bg3:#1e1e1e; --bg4:#252525;
  --border:rgba(255,255,255,.07); --border-h:rgba(255,255,255,.15);
  --text:#e5e5e5; --text-dim:#a3a3a3; --text-muted:#555;
  --green:#22c55e; --green-dim:rgba(34,197,94,.13);
  --r:10px; --rl:16px; --rx:22px;
  --shadow:0 8px 40px rgba(0,0,0,.6);
  --shadow-r:0 4px 24px rgba(229,9,20,.3);
  --ease:cubic-bezier(.4,0,.2,1);
  --spring:cubic-bezier(.34,1.56,.64,1);
  --sw:260px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);
  min-height:100vh;display:flex;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:inherit;cursor:pointer;border:none;background:none}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--red)}

/* ═══ SIDEBAR ═══ */
.sb{position:fixed;right:0;top:0;bottom:0;width:var(--sw);
  background:var(--bg2);border-left:1px solid var(--border);
  display:flex;flex-direction:column;z-index:200;overflow-y:auto}
.sb-hd{padding:24px 18px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:12px}
.sb-ic{width:38px;height:38px;border-radius:9px;background:var(--red-dim);
  border:1px solid rgba(229,9,20,.22);display:flex;align-items:center;
  justify-content:center;color:var(--red);font-size:1rem;flex-shrink:0}
.sb-ttl{font-size:.95rem;font-weight:800;color:var(--text)}
.sb-sub{font-size:.7rem;color:var(--text-muted);margin-top:1px}
.sb-nav{flex:1;padding:14px 10px}
.sb-lbl{font-size:.65rem;font-weight:700;color:var(--text-muted);
  letter-spacing:1px;text-transform:uppercase;padding:0 8px;margin:14px 0 5px}
.sb-a{display:flex;align-items:center;gap:10px;padding:8px 10px;
  border-radius:6px;color:var(--text-dim);font-size:.85rem;font-weight:600;
  margin-bottom:2px;transition:all .18s var(--ease);border-right:2px solid transparent}
.sb-a .ic{width:26px;height:26px;border-radius:5px;display:flex;
  align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.sb-a:hover,.sb-a.on{background:var(--red-dim);color:var(--text);border-right-color:var(--red)}
.sb-a:hover .ic,.sb-a.on .ic{background:rgba(229,9,20,.18);color:var(--red)}
.sb-a.on{color:var(--red);font-weight:700}
.sb-ft{padding:14px 10px;border-top:1px solid var(--border)}
.sb-view{display:flex;align-items:center;gap:8px;padding:8px 12px;
  border-radius:6px;background:rgba(255,255,255,.04);border:1px solid var(--border);
  color:var(--text-dim);font-size:.82rem;font-weight:600;transition:all .18s}
.sb-view:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}

/* ═══ MAIN ═══ */
.main{margin-right:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column}

/* TopBar */
.tb{height:62px;padding:0 28px;background:var(--bg2);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100}
.tb-l .tb-ttl{font-size:1.05rem;font-weight:800}
.tb-l .tb-path{font-size:.72rem;color:var(--text-muted);margin-top:1px}
.tb-r{display:flex;gap:8px}
.tb-btn{width:34px;height:34px;border-radius:6px;background:var(--bg3);
  border:1px solid var(--border);color:var(--text-dim);font-size:.82rem;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;transition:all .18s;text-decoration:none}
.tb-btn:hover{background:var(--red-dim);border-color:var(--red);color:var(--red)}
.mb-btn{display:none}

/* Content */
.content{flex:1;padding:28px 28px 0}

/* Alert */
.alert{display:flex;align-items:center;gap:10px;padding:13px 16px;
  border-radius:var(--r);margin-bottom:22px;font-weight:600;font-size:.88rem;
  animation:slideD .3s var(--spring)}
@keyframes slideD{from{opacity:0;transform:translateY(-8px)}}
.alert-ok{background:var(--green-dim);border:1px solid rgba(34,197,94,.28);color:var(--green)}
.alert-err{background:var(--red-dim);border:1px solid rgba(229,9,20,.28);color:#ff6b6b}
.alert-x{margin-right:auto;cursor:pointer;opacity:.7;font-size:.85rem}
.alert-x:hover{opacity:1}

/* Grid */
.sg{display:grid;gap:18px}

/* Card */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--rx);overflow:hidden;
  animation:fadeUp .3s var(--ease) both}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}}
.card:nth-child(1){animation-delay:.04s}.card:nth-child(2){animation-delay:.08s}
.card:nth-child(3){animation-delay:.12s}.card:nth-child(4){animation-delay:.16s}
.card:nth-child(5){animation-delay:.2s}
.card:focus-within{border-color:rgba(229,9,20,.35)}
.ch{padding:16px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.015)}
.ch-ic{width:34px;height:34px;border-radius:8px;background:var(--red-dim);
  border:1px solid rgba(229,9,20,.18);display:flex;align-items:center;
  justify-content:center;color:var(--red);font-size:.85rem;flex-shrink:0}
.ch-ttl{font-size:.9rem;font-weight:800}
.ch-sub{font-size:.72rem;color:var(--text-muted);margin-top:1px}
.cb{padding:20px 22px 22px}

/* Fields */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg.one{grid-template-columns:1fr}
.full{grid-column:1/-1}
.fld{display:flex;flex-direction:column;gap:5px}
.fld label{font-size:.76rem;font-weight:700;color:var(--text-dim);
  display:flex;align-items:center;gap:6px;letter-spacing:.2px}
.fld label i{color:var(--red);font-size:.72rem}
.fld label .bdg{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:99px;
  background:var(--bg3);color:var(--text-muted);border:1px solid var(--border);margin-right:auto}

.iw{position:relative}
.iw .pi{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  color:var(--text-muted);font-size:.78rem;pointer-events:none;transition:color .18s}
.iw:focus-within .pi{color:var(--red)}

input[type=text],input[type=email],input[type=tel],input[type=url],textarea{
  width:100%;background:var(--bg3);border:1.5px solid var(--border);
  border-radius:var(--r);color:var(--text);font-family:inherit;
  font-size:.86rem;font-weight:500;
  transition:border-color .18s,box-shadow .18s,background .18s}
input[type=text],input[type=email],input[type=tel],input[type=url]{
  height:40px;padding:0 36px 0 12px}
input:focus,textarea:focus{outline:none;border-color:var(--red);
  background:var(--bg4);box-shadow:0 0 0 3px var(--red-dim)}
textarea{padding:10px 12px;resize:vertical;min-height:72px;line-height:1.6}

/* Color */
.crow{display:flex;align-items:center;gap:8px}
.crow .iw{flex:1}
input[type=color]{width:40px;height:40px;padding:4px;border-radius:var(--r);
  cursor:pointer;border:1.5px solid var(--border);background:var(--bg3);flex-shrink:0}
.cprev{width:16px;height:16px;border-radius:4px;border:2px solid var(--border-h);flex-shrink:0}
.swatches{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
.sw{width:20px;height:20px;border-radius:4px;cursor:pointer;
  border:2px solid transparent;transition:all .15s;flex-shrink:0}
.sw:hover{transform:scale(1.2);border-color:rgba(255,255,255,.35)}
.sw.on{border-color:#fff;transform:scale(1.15)}

/* Logo */
.lw{display:flex;align-items:center;gap:12px;padding:12px 14px;
  background:var(--bg3);border:1.5px dashed var(--border);border-radius:var(--r);
  margin-top:6px;transition:border-color .18s}
.lw:hover{border-color:rgba(229,9,20,.35)}
.lp{width:48px;height:48px;border-radius:9px;background:var(--bg4);
  border:1px solid var(--border);display:flex;align-items:center;
  justify-content:center;overflow:hidden;flex-shrink:0}
.lp img{width:100%;height:100%;object-fit:cover}
.lp i{color:var(--text-muted);font-size:1.3rem}
.lhint{font-size:.74rem;color:var(--text-muted);line-height:1.5}
.lhint strong{color:var(--text-dim)}

/* field footer */
.ff{display:flex;align-items:center;justify-content:space-between;margin-top:4px}
.cct{font-size:.7rem;color:var(--text-muted)}
.cct.w{color:#f59e0b}.cct.ov{color:var(--red)}
.fhint{font-size:.7rem;color:var(--text-muted)}

/* Social */
.socg{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.socc{display:flex;flex-direction:column;gap:5px}
.socl{font-size:.76rem;font-weight:700;display:flex;align-items:center;gap:7px;color:var(--text-dim)}
.soci{width:22px;height:22px;border-radius:5px;display:flex;
  align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.fb{background:rgba(24,119,242,.14);color:#1877f2}
.wa{background:rgba(37,211,102,.14);color:#25d366}
.tw{background:rgba(29,161,242,.14);color:#1da1f2}
.tg{background:rgba(0,136,204,.14);color:#0088cc}
.yt{background:rgba(255,0,0,.14);color:#ff0000}
.ig{background:rgba(225,48,108,.14);color:#e1306c}

/* Notif preview */
.np{background:var(--bg3);border:1px solid var(--border);border-radius:var(--rl);
  padding:14px 16px;display:flex;align-items:flex-start;gap:11px;
  margin-top:14px;position:relative;overflow:hidden}
.np::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--red)}
.np-ic{width:40px;height:40px;border-radius:9px;background:var(--red-dim);
  border:1px solid rgba(229,9,20,.18);display:flex;align-items:center;
  justify-content:center;color:var(--red);font-size:1.1rem;flex-shrink:0}
.np-ttl{font-size:.85rem;font-weight:800;margin-bottom:3px}
.np-body{font-size:.76rem;color:var(--text-dim);line-height:1.5}
.np-lbl{position:absolute;top:8px;left:12px;font-size:.62rem;font-weight:700;
  color:var(--text-muted);background:var(--bg4);padding:1px 7px;
  border-radius:99px;border:1px solid var(--border)}

/* Hero preview */
.hprev{background:linear-gradient(135deg,#0d0d0d,#1a0a0a);
  border-radius:var(--rl);padding:24px 20px;border:1px solid var(--border);
  position:relative;overflow:hidden;margin-top:10px}
.hprev::after{content:'';position:absolute;top:0;right:0;width:180px;height:100%;
  background:radial-gradient(ellipse at right,rgba(229,9,20,.14),transparent 70%);pointer-events:none}
.hbadge{display:inline-flex;align-items:center;gap:6px;
  background:rgba(229,9,20,.14);border:1px solid rgba(229,9,20,.28);
  padding:3px 10px;border-radius:99px;margin-bottom:10px;
  font-size:.7rem;font-weight:800;color:#e50914}
.hdot{width:6px;height:6px;border-radius:50%;background:#e50914}
.hprev-ttl{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:6px;line-height:1.2}
.hprev-sub{font-size:.82rem;color:#a3a3a3}

.dv{height:1px;background:var(--border);margin:16px 0}
.prev-label{font-size:.72rem;color:var(--text-muted);font-weight:700;
  letter-spacing:.5px;margin-bottom:8px}

/* Save bar */
.savebar{position:sticky;bottom:0;
  background:linear-gradient(0deg,var(--bg) 70%,transparent);
  padding:14px 28px 22px;display:flex;align-items:center;
  justify-content:flex-end;gap:10px;
  margin:0 -28px;z-index:50}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;
  border-radius:99px;font-family:inherit;font-size:.86rem;font-weight:700;
  cursor:pointer;border:none;transition:all .2s var(--spring)}
.btn-out{background:transparent;border:1.5px solid var(--border-h);color:var(--text-dim)}
.btn-out:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}
.btn-p{background:var(--red);color:#fff;box-shadow:var(--shadow-r)}
.btn-p:hover{background:var(--red-h);transform:translateY(-1px);box-shadow:0 8px 28px rgba(229,9,20,.4)}
.btn-p:active{transform:translateY(0)}
.btn-p.ld{pointer-events:none;opacity:.75}

/* Responsive */
@media(max-width:768px){
  :root{--sw:0px}
  .sb{transform:translateX(100%);transition:transform .3s;width:260px}
  .sb.open{transform:translateX(0)}
  .main{margin-right:0}
  .content{padding:14px 14px 0}
  .fg{grid-template-columns:1fr}
  .socg{grid-template-columns:1fr}
  .tb{padding:0 14px}
  .savebar{padding:12px 14px 18px;margin:0 -14px}
  .mb-btn{display:flex!important}
}
</style>
</head>
<body>

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sb" id="sb">
  <div class="sb-hd">
    <div class="sb-ic"><i class="fas fa-satellite-dish"></i></div>
    <div>
      <div class="sb-ttl">لوحة الإدارة</div>
      <div class="sb-sub">إدارة وتحكم كامل</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="sb-lbl">الرئيسية</div>
    <a href="admin.php" class="sb-a">
      <span class="ic"><i class="fas fa-gauge-high"></i></span>لوحة التحكم
    </a>
    <div class="sb-lbl">الإعدادات</div>
    <a href="admin_site_settings.php" class="sb-a on">
      <span class="ic"><i class="fas fa-sliders"></i></span>إعدادات الموقع
    </a>
    <a href="activate.php" class="sb-a">
      <span class="ic"><i class="fas fa-shield-check"></i></span>الرخصة
    </a>
  </nav>
  <div class="sb-ft">
    <a href="index.php" class="sb-view" target="_blank">
      <i class="fas fa-arrow-up-right-from-square"></i> عرض الموقع
    </a>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<div class="main">

  <div class="tb">
    <div class="tb-l">
      <div class="tb-ttl">إعدادات الموقع</div>
      <div class="tb-path">الإدارة › الإعدادات › الموقع</div>
    </div>
    <div class="tb-r">
      <button class="tb-btn mb-btn" onclick="document.getElementById('sb').classList.toggle('open')">
        <i class="fas fa-bars"></i>
      </button>
      <a href="index.php" class="tb-btn" target="_blank" title="معاينة الموقع">
        <i class="fas fa-eye"></i>
      </a>
      <a href="admin_logout.php" class="tb-btn" title="خروج">
        <i class="fas fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <!-- Alerts -->
    <?php if ($success_msg): ?>
    <div class="alert alert-ok" id="alertBox">
      <i class="fas fa-circle-check"></i>
      <span><?= htmlspecialchars($success_msg) ?></span>
      <button class="alert-x" onclick="this.parentElement.remove()"><i class="fas fa-xmark"></i></button>
    </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-err" id="alertBox">
      <i class="fas fa-circle-exclamation"></i>
      <span><?= htmlspecialchars($error_msg) ?></span>
      <button class="alert-x" onclick="this.parentElement.remove()"><i class="fas fa-xmark"></i></button>
    </div>
    <?php endif; ?>

    <form method="POST" id="sf" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="save_settings" value="1">

      <div class="sg">

        <!-- ══ 1: هوية الموقع ══ -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-id-card"></i></div>
            <div><div class="ch-ttl">هوية الموقع</div><div class="ch-sub">الاسم والشعار والثيم</div></div>
          </div>
          <div class="cb">
            <div class="fg">

              <div class="fld">
                <label><i class="fas fa-heading"></i> اسم الموقع <span class="bdg">مطلوب</span></label>
                <div class="iw">
                  <input type="text" name="site_name" id="f_site_name" maxlength="60"
                    value="<?= v($settings,'site_name','Shashety') ?>"
                    placeholder="مثال: Shashety IPTV"
                    oninput="cc('f_site_name','cc1',60)">
                  <i class="fas fa-heading pi"></i>
                </div>
                <div class="ff">
                  <span class="fhint">يظهر في العنوان وأعلى الصفحة</span>
                  <span class="cct" id="cc1"></span>
                </div>
              </div>

              <div class="fld">
                <label><i class="fas fa-palette"></i> لون الثيم</label>
                <div class="crow">
                  <div class="iw">
                    <input type="text" name="theme_color" id="f_theme_color"
                      value="<?= v($settings,'theme_color','#e50914') ?>"
                      placeholder="#e50914" maxlength="7" oninput="syncTxt()">
                    <i class="fas fa-hashtag pi"></i>
                  </div>
                  <input type="color" id="colorPicker"
                    value="<?= v($settings,'theme_color','#e50914') ?>"
                    oninput="syncPick()">
                  <div class="cprev" id="cprev"
                    style="background:<?= v($settings,'theme_color','#e50914') ?>"></div>
                </div>
                <div class="swatches">
                  <div class="sw" style="background:#e50914" data-c="#e50914" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#ff6b35" data-c="#ff6b35" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#f59e0b" data-c="#f59e0b" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#22c55e" data-c="#22c55e" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#3b82f6" data-c="#3b82f6" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#8b5cf6" data-c="#8b5cf6" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#ec4899" data-c="#ec4899" onclick="pickSw(this)"></div>
                  <div class="sw" style="background:#06b6d4" data-c="#06b6d4" onclick="pickSw(this)"></div>
                </div>
              </div>

              <div class="fld full">
                <label><i class="fas fa-align-right"></i> وصف الموقع <span class="bdg">SEO</span></label>
                <textarea name="site_description" id="f_site_description"
                  maxlength="160" rows="2"
                  oninput="cc('f_site_description','cc2',160)"
                  placeholder="وصف مختصر يظهر في محركات البحث..."><?= v($settings,'site_description') ?></textarea>
                <div class="ff">
                  <span class="fhint">مثالي بين 120–160 حرف</span>
                  <span class="cct" id="cc2"></span>
                </div>
              </div>

              <div class="fld full">
                <label><i class="fas fa-image"></i> رابط شعار الموقع</label>
                <div class="iw">
                  <input type="url" name="site_logo" id="f_site_logo"
                    value="<?= v($settings,'site_logo') ?>"
                    placeholder="https://example.com/logo.png"
                    oninput="prevLogo()">
                  <i class="fas fa-link pi"></i>
                </div>
                <div class="lw">
                  <div class="lp" id="logoBox">
                    <?php if(!empty($settings['site_logo'])): ?>
                      <img src="<?= v($settings,'site_logo') ?>" alt="logo">
                    <?php else: ?>
                      <i class="fas fa-image"></i>
                    <?php endif; ?>
                  </div>
                  <div class="lhint">
                    <strong>معاينة الشعار</strong><br>
                    الأبعاد المثلى: <strong>200×200px</strong> — PNG/SVG
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ══ 2: نصوص الصفحة الرئيسية ══ -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-pen-to-square"></i></div>
            <div>
              <div class="ch-ttl">نصوص الصفحة الرئيسية (index.php)</div>
              <div class="ch-sub">العناوين والنصوص المعروضة للزوار</div>
            </div>
          </div>
          <div class="cb">
            <div class="fg one">

              <div class="fld">
                <label><i class="fas fa-star"></i> العنوان الترحيبي الكبير</label>
                <div class="iw">
                  <input type="text" name="welcome_title" id="f_welcome_title" maxlength="80"
                    value="<?= v($settings,'welcome_title','مرحباً بك في عالم البث المباشر') ?>"
                    placeholder="العنوان الكبير في أعلى الصفحة"
                    oninput="cc('f_welcome_title','cc3',80);updPrev()">
                  <i class="fas fa-star pi"></i>
                </div>
                <div class="ff">
                  <span class="fhint">يظهر بخط كبير في بانر الصفحة الرئيسية</span>
                  <span class="cct" id="cc3"></span>
                </div>
              </div>

              <div class="fld">
                <label><i class="fas fa-quote-right"></i> النص التعريفي تحت العنوان</label>
                <textarea name="welcome_subtitle" id="f_welcome_subtitle"
                  maxlength="200" rows="2"
                  oninput="cc('f_welcome_subtitle','cc4',200);updPrev()"
                  placeholder="شاهد آلاف القنوات من جميع أنحاء العالم..."><?= v($settings,'welcome_subtitle','شاهد آلاف القنوات من جميع أنحاء العالم') ?></textarea>
                <div class="ff">
                  <span class="fhint">يظهر أسفل العنوان مباشرة</span>
                  <span class="cct" id="cc4"></span>
                </div>
              </div>

              <div class="fld">
                <label><i class="fas fa-copyright"></i> نص حقوق الملكية (Footer)</label>
                <div class="iw">
                  <input type="text" name="footer_text" id="f_footer_text" maxlength="120"
                    value="<?= v($settings,'footer_text','جميع الحقوق محفوظة © 2024 Shashety') ?>"
                    placeholder="جميع الحقوق محفوظة © 2024 اسمك"
                    oninput="cc('f_footer_text','cc5',120)">
                  <i class="fas fa-copyright pi"></i>
                </div>
                <div class="ff"><span></span><span class="cct" id="cc5"></span></div>
              </div>

            </div>

            <!-- معاينة البانر -->
            <div class="dv"></div>
            <div class="prev-label">◎ معاينة مباشرة لبانر index.php</div>
            <div class="hprev">
              <div class="hbadge"><span class="hdot"></span> بث مباشر الآن</div>
              <div class="hprev-ttl" id="pv_title"><?= v($settings,'welcome_title','مرحباً بك في عالم البث المباشر') ?></div>
              <div class="hprev-sub"  id="pv_sub"><?= v($settings,'welcome_subtitle','شاهد آلاف القنوات من جميع أنحاء العالم') ?></div>
            </div>

          </div>
        </div>

        <!-- ══ 3: معلومات التواصل ══ -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-address-book"></i></div>
            <div><div class="ch-ttl">معلومات التواصل</div><div class="ch-sub">تظهر في أسفل الصفحة</div></div>
          </div>
          <div class="cb">
            <div class="fg">

              <div class="fld">
                <label><i class="fas fa-phone"></i> رقم الهاتف / الدعم الفني</label>
                <div class="iw">
                  <input type="tel" name="contact_phone"
                    value="<?= v($settings,'contact_phone') ?>"
                    placeholder="+966 5X XXX XXXX" dir="ltr">
                  <i class="fas fa-phone pi"></i>
                </div>
              </div>

              <div class="fld">
                <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                <div class="iw">
                  <input type="email" name="contact_email"
                    value="<?= v($settings,'contact_email') ?>"
                    placeholder="support@yourdomain.com" dir="ltr">
                  <i class="fas fa-envelope pi"></i>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ══ 4: التواصل الاجتماعي ══ -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-share-nodes"></i></div>
            <div><div class="ch-ttl">حسابات التواصل الاجتماعي</div><div class="ch-sub">أيقونات الفوتر في index.php</div></div>
          </div>
          <div class="cb">
            <div class="socg">

              <div class="socc">
                <label class="socl"><span class="soci fb"><i class="fab fa-facebook-f"></i></span> فيسبوك</label>
                <div class="iw">
                  <input type="url" name="contact_facebook"
                    value="<?= v($settings,'contact_facebook') ?>"
                    placeholder="https://facebook.com/yourpage" dir="ltr">
                  <i class="fab fa-facebook-f pi" style="color:#1877f2"></i>
                </div>
              </div>

              <div class="socc">
                <label class="socl"><span class="soci wa"><i class="fab fa-whatsapp"></i></span> واتساب</label>
                <div class="iw">
                  <input type="url" name="contact_whatsapp"
                    value="<?= v($settings,'contact_whatsapp') ?>"
                    placeholder="https://wa.me/9665XXXXXXXX" dir="ltr">
                  <i class="fab fa-whatsapp pi" style="color:#25d366"></i>
                </div>
              </div>

              <div class="socc">
                <label class="socl"><span class="soci tw"><i class="fab fa-twitter"></i></span> تويتر / X</label>
                <div class="iw">
                  <input type="url" name="contact_twitter"
                    value="<?= v($settings,'contact_twitter') ?>"
                    placeholder="https://twitter.com/username" dir="ltr">
                  <i class="fab fa-x-twitter pi" style="color:#1da1f2"></i>
                </div>
              </div>

              <div class="socc">
                <label class="socl"><span class="soci tg"><i class="fab fa-telegram"></i></span> تيليغرام</label>
                <div class="iw">
                  <input type="url" name="contact_telegram"
                    value="<?= v($settings,'contact_telegram') ?>"
                    placeholder="https://t.me/username" dir="ltr">
                  <i class="fab fa-telegram pi" style="color:#0088cc"></i>
                </div>
              </div>

              <div class="socc">
                <label class="socl"><span class="soci yt"><i class="fab fa-youtube"></i></span> يوتيوب</label>
                <div class="iw">
                  <input type="url" name="contact_youtube"
                    value="<?= v($settings,'contact_youtube') ?>"
                    placeholder="https://youtube.com/@channel" dir="ltr">
                  <i class="fab fa-youtube pi" style="color:#ff0000"></i>
                </div>
              </div>

              <div class="socc">
                <label class="socl"><span class="soci ig"><i class="fab fa-instagram"></i></span> إنستغرام</label>
                <div class="iw">
                  <input type="url" name="contact_instagram"
                    value="<?= v($settings,'contact_instagram') ?>"
                    placeholder="https://instagram.com/username" dir="ltr">
                  <i class="fab fa-instagram pi" style="color:#e1306c"></i>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ══ 5: الإشعارات ══ -->
        <div class="card">
          <div class="ch">
            <div class="ch-ic"><i class="fas fa-bell"></i></div>
            <div><div class="ch-ttl">إشعارات الموقع</div><div class="ch-sub">نص إشعار المتصفح للزوار</div></div>
          </div>
          <div class="cb">
            <div class="fg one">

              <div class="fld">
                <label><i class="fas fa-bell"></i> عنوان الإشعار</label>
                <div class="iw">
                  <input type="text" name="notification_title" id="f_notif_title" maxlength="50"
                    value="<?= v($settings,'notification_title') ?>"
                    placeholder="مثال: قنواتك المفضلة الآن متاحة!"
                    oninput="cc('f_notif_title','cc6',50);updNotif()">
                  <i class="fas fa-bell pi"></i>
                </div>
                <div class="ff"><span></span><span class="cct" id="cc6"></span></div>
              </div>

              <div class="fld">
                <label><i class="fas fa-align-right"></i> نص الإشعار</label>
                <textarea name="notification_body" id="f_notif_body"
                  maxlength="120" rows="2"
                  oninput="cc('f_notif_body','cc7',120);updNotif()"
                  placeholder="نص قصير يصف محتوى الموقع..."><?= v($settings,'notification_body') ?></textarea>
                <div class="ff">
                  <span class="fhint">يظهر كإشعار متصفح للزوار الجدد</span>
                  <span class="cct" id="cc7"></span>
                </div>
              </div>

            </div>

            <div class="np">
              <div class="np-lbl">معاينة</div>
              <div class="np-ic"><i class="fas fa-satellite-dish"></i></div>
              <div>
                <div class="np-ttl" id="pv_ntitle"><?= v($settings,'notification_title','عنوان الإشعار') ?></div>
                <div class="np-body" id="pv_nbody"><?= v($settings,'notification_body','نص الإشعار يظهر هنا...') ?></div>
              </div>
            </div>

          </div>
        </div>

      </div><!-- /sg -->

      <div class="savebar">
        <button type="button" class="btn btn-out"
          onclick="if(confirm('إعادة تحميل وفقدان التغييرات؟'))location.reload()">
          <i class="fas fa-rotate-left"></i> إعادة ضبط
        </button>
        <button type="submit" class="btn btn-p" id="saveBtn">
          <i class="fas fa-floppy-disk"></i> حفظ الإعدادات
        </button>
      </div>

    </form>
  </div><!-- /content -->
</div><!-- /main -->

<script>
/* char counter */
function cc(fid, cid, max) {
  const el = document.getElementById(fid);
  const ct = document.getElementById(cid);
  if (!el || !ct) return;
  const n = el.value.length;
  ct.textContent = n + '/' + max;
  ct.className = 'cct' + (n >= max ? ' ov' : n > max * .88 ? ' w' : '');
}
/* init on load */
[['f_site_name','cc1',60],['f_site_description','cc2',160],
 ['f_welcome_title','cc3',80],['f_welcome_subtitle','cc4',200],
 ['f_footer_text','cc5',120],['f_notif_title','cc6',50],
 ['f_notif_body','cc7',120]
].forEach(([a,b,c])=>cc(a,b,c));

/* color sync */
function applyColor(hex) {
  document.getElementById('f_theme_color').value = hex;
  document.getElementById('colorPicker').value   = hex;
  document.getElementById('cprev').style.background = hex;
  document.documentElement.style.setProperty('--red', hex);
  document.querySelectorAll('.sw').forEach(s =>
    s.classList.toggle('on', s.dataset.c.toLowerCase() === hex.toLowerCase()));
}
function syncTxt()  {
  const val = document.getElementById('f_theme_color').value.trim();
  if (/^#[0-9A-Fa-f]{6}$/.test(val)) applyColor(val);
}
function syncPick() { applyColor(document.getElementById('colorPicker').value); }
function pickSw(el) { applyColor(el.dataset.c); }
applyColor(document.getElementById('f_theme_color').value || '#e50914');

/* logo preview */
function prevLogo() {
  const url = document.getElementById('f_site_logo').value.trim();
  const box = document.getElementById('logoBox');
  if (!url) { box.innerHTML = '<i class="fas fa-image"></i>'; return; }
  const img = new Image();
  img.onload  = () => {
    img.style = 'width:100%;height:100%;object-fit:cover;border-radius:8px';
    box.innerHTML = ''; box.appendChild(img);
  };
  img.onerror = () => { box.innerHTML = '<i class="fas fa-image" style="color:var(--red)"></i>'; };
  img.src = url;
}

/* hero preview */
function updPrev() {
  const t = document.getElementById('f_welcome_title');
  const s = document.getElementById('f_welcome_subtitle');
  document.getElementById('pv_title').textContent = t ? t.value : '';
  document.getElementById('pv_sub').textContent   = s ? s.value : '';
}

/* notif preview */
function updNotif() {
  const t = document.getElementById('f_notif_title').value;
  const b = document.getElementById('f_notif_body').value;
  document.getElementById('pv_ntitle').textContent = t || 'عنوان الإشعار';
  document.getElementById('pv_nbody').textContent  = b || 'نص الإشعار يظهر هنا...';
}

/* save loading state */
document.getElementById('saveBtn').addEventListener('click', function() {
  this.classList.add('ld');
  this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ...';
});

/* unsaved warning */
let changed = false;
document.getElementById('sf').addEventListener('input', () => changed = true);
window.addEventListener('beforeunload', e => {
  if (changed) { e.preventDefault(); e.returnValue = ''; }
});
document.getElementById('sf').addEventListener('submit', () => { changed = false; });

/* auto dismiss success */
const ab = document.getElementById('alertBox');
if (ab && ab.classList.contains('alert-ok')) {
  setTimeout(() => {
    ab.style.transition = 'opacity .4s';
    ab.style.opacity = '0';
    setTimeout(() => ab && ab.remove(), 400);
  }, 4000);
}
</script>
</body>
</html>
