<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$message = "";
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host     = $_POST['host']     ?? '';
    $user     = $_POST['user']     ?? '';
    $password = $_POST['password'] ?? '';
    $dbname   = 'iptv_db';
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === 0) {
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        try {
            $conn = new mysqli($host, $user, $password);
            if ($conn->connect_error) {
                throw new Exception("فشل الاتصال بقاعدة البيانات");
            }
            $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
            $conn->select_db($dbname);
            if ($conn->multi_query($sql)) {
                do {
                    $conn->store_result();
                    if ($conn->error) {
                        throw new Exception("خطأ في الاستعلام: " . $conn->error);
                    }
                } while ($conn->more_results() && $conn->next_result());
            } else {
                throw new Exception("فشل تنفيذ SQL");
            }
            $success = true;
            $conn->close();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    } else {
        $message = "اختر ملف SQL صحيح";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Installer</title>
<?php if($success): ?>
<meta http-equiv="refresh" content="3;url=admin_site_settings.php">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --red:      #E50914;
  --red-dark: #B20710;
  --bg:       #141414;
  --s1:       #1c1c1c;
  --s2:       #242424;
  --border:   rgba(255,255,255,0.07);
  --text:     #ffffff;
  --muted:    #888;
  --success:  #46d369;
  --bar-h:    52px;
}

html, body { height: 100%; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Cairo', sans-serif;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ─── BG ──────────────────────────────────────── */
.bg-fx {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 90% 55% at 50% -5%, rgba(229,9,20,.20) 0%, transparent 68%),
    radial-gradient(ellipse 50% 35% at 95% 95%, rgba(229,9,20,.07) 0%, transparent 55%),
    #141414;
}
.bg-grid {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.022) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.022) 1px, transparent 1px);
  background-size: 56px 56px;
  mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, #000 20%, transparent 100%);
}

/* ─── TOP BAR ─────────────────────────────────── */
.topbar {
  position: fixed; top: 0; left: 0; right: 0;
  height: var(--bar-h); z-index: 100;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 2rem;
  background: rgba(12,12,12,.92);
  backdrop-filter: blur(20px) saturate(1.5);
  border-bottom: 1px solid var(--border);
  animation: slideDown .45s ease both;
}
.topbar::after {
  content: '';
  position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--red) 40%, var(--red) 60%, transparent);
  opacity: .5;
}
.topbar-left  { display: flex; align-items: center; gap: .6rem; }
.topbar-badge {
  font-size: .58rem; font-weight: 700; letter-spacing: .2em;
  text-transform: uppercase; color: var(--muted);
  border-right: 1px solid rgba(255,255,255,.1);
  padding-right: .65rem;
}
.topbar-name {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.5rem; letter-spacing: .2em;
  color: var(--red);
  text-shadow: 0 0 24px rgba(229,9,20,.55);
  line-height: 1;
}
.topbar-right { display: flex; align-items: center; gap: .5rem; font-size: .7rem; color: var(--muted); letter-spacing: .07em; }
.pulse {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--success); box-shadow: 0 0 8px var(--success);
  animation: blink 2s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1}50%{opacity:.3} }

/* ─── BOTTOM BAR ──────────────────────────────── */
.botbar {
  position: fixed; bottom: 0; left: 0; right: 0;
  height: var(--bar-h); z-index: 100;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 2rem;
  background: rgba(12,12,12,.92);
  backdrop-filter: blur(20px) saturate(1.5);
  border-top: 1px solid var(--border);
  animation: slideUp .45s ease .1s both;
}
.botbar::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(229,9,20,.35) 50%, transparent);
}
.botbar-copy { font-size: .62rem; letter-spacing: .15em; text-transform: uppercase; color: #333; }
.botbar-links { display: flex; align-items: center; gap: 1.2rem; }
.botbar-links a { font-size: .62rem; letter-spacing: .12em; text-transform: uppercase; color: #333; text-decoration: none; transition: color .2s; }
.botbar-links a:hover { color: var(--muted); }
.botbar-sep { width: 1px; height: 10px; background: rgba(255,255,255,.07); }

/* ─── STAGE ───────────────────────────────────── */
.stage {
  position: relative; z-index: 1; flex: 1;
  display: flex; align-items: center; justify-content: center;
  padding: calc(var(--bar-h) + 1.5rem) 1.5rem calc(var(--bar-h) + 1.5rem);
}

/* ─── CARD ────────────────────────────────────── */
.card {
  width: 100%; max-width: 460px;
  background: rgba(22,22,22,.97);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 2.2rem 2.2rem 1.8rem;
  backdrop-filter: blur(24px);
  box-shadow: 0 0 0 1px rgba(229,9,20,.08), 0 28px 70px rgba(0,0,0,.7), 0 0 100px rgba(229,9,20,.04);
  animation: fadeUp .5s ease .18s both;
  position: relative; overflow: hidden;
}
.card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, var(--red) 40%, var(--red) 60%, transparent);
}

.card-head { text-align: center; margin-bottom: 1.6rem; }
.card-icon {
  width: 44px; height: 44px;
  background: rgba(229,9,20,.1);
  border: 1px solid rgba(229,9,20,.22);
  border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.1rem; margin-bottom: .8rem;
}
.card-title { font-size: 1.4rem; font-weight: 700; margin-bottom: .25rem; }
.card-desc  { font-size: .78rem; color: var(--muted); font-weight: 300; }

/* Steps */
.steps { display: flex; gap: .3rem; margin-bottom: 1.6rem; }
.step  { flex: 1; height: 3px; background: var(--s2); border-radius: 2px; position: relative; overflow: hidden; }
.step.on::after {
  content: ''; position: absolute; inset: 0;
  background: var(--red); animation: fill .3s ease both;
}
@keyframes fill { from{transform:scaleX(0);transform-origin:right} to{transform:scaleX(1)} }

/* Fields */
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
.field { margin-bottom: .85rem; }
.label {
  display: flex; align-items: center; gap: .35rem;
  font-size: .65rem; font-weight: 600; letter-spacing: .15em;
  text-transform: uppercase; color: var(--muted); margin-bottom: .38rem;
}
.label span { font-size: .75rem; opacity: .7; }
.inp {
  width: 100%;
  background: var(--s2); border: 1px solid var(--border);
  border-radius: 4px; color: var(--text);
  font-family: 'Cairo', sans-serif; font-size: .9rem;
  padding: .68rem .95rem; outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.inp:focus { border-color: var(--red); background: #282828; box-shadow: 0 0 0 3px rgba(229,9,20,.13); }
.inp::placeholder { color: #3d3d3d; }

/* Fixed DB badge */
.db-fixed {
  width: 100%;
  background: rgba(229,9,20,.07);
  border: 1px solid rgba(229,9,20,.22);
  border-radius: 4px;
  color: var(--red);
  font-family: 'Cairo', monospace;
  font-size: .88rem; font-weight: 700;
  padding: .68rem .95rem;
  letter-spacing: .06em;
  display: flex; align-items: center; gap: .5rem;
}
.db-fixed::before { content: '🗄️'; font-size: .8rem; }

/* Divider */
.divider {
  display: flex; align-items: center; gap: .75rem;
  margin: 1.1rem 0 .9rem;
  font-size: .6rem; letter-spacing: .15em;
  text-transform: uppercase; color: #333;
}
.divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--border); }

/* File drop */
.fwrap { position: relative; cursor: pointer; }
.fwrap input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
.fdrop {
  background: var(--s2); border: 1px dashed rgba(255,255,255,.1);
  border-radius: 4px; padding: 1rem;
  display: flex; flex-direction: column; align-items: center; gap: .3rem;
  text-align: center; transition: border-color .2s, background .2s;
}
.fwrap:hover .fdrop { border-color: var(--red); background: #282828; }
.ficon { font-size: 1.4rem; line-height: 1; }
.ftext { font-size: .75rem; color: var(--muted); }
.fname { font-size: .73rem; color: var(--red); font-weight: 600; display: none; }

/* Button */
.btn {
  width: 100%; padding: .82rem; margin-top: .75rem;
  background: var(--red); border: none; border-radius: 4px;
  color: #fff; font-family: 'Cairo', sans-serif;
  font-size: .92rem; font-weight: 700; letter-spacing: .06em;
  cursor: pointer; position: relative; overflow: hidden;
  transition: background .2s, transform .15s, box-shadow .2s;
}
.btn::after { content:''; position:absolute;inset:0; background:linear-gradient(rgba(255,255,255,.1),transparent); }
.btn:hover  { background: var(--red-dark); box-shadow: 0 4px 22px rgba(229,9,20,.45); transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn.loading { pointer-events:none; background:#6b0008; color:rgba(255,255,255,.45); }
.btn.loading::before {
  content:''; position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.14),transparent);
  animation: shimmer 1.1s infinite;
}
@keyframes shimmer { to{left:100%} }

/* Alert */
.alert {
  border-radius: 4px; padding: .8rem 1rem; margin-bottom: 1rem;
  font-size: .82rem; display: flex; align-items: flex-start; gap: .55rem;
  animation: fadeUp .3s ease both;
  background: rgba(229,9,20,.1); border: 1px solid rgba(229,9,20,.26); color: #ff7070;
}

/* ─── SUCCESS ─────────────────────────────────── */
.sscreen { text-align: center; padding: .6rem 0 .2rem; }
.sring {
  width: 76px; height: 76px; border-radius: 50%;
  background: rgba(70,211,105,.08); border: 2px solid var(--success);
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem; margin: 0 auto 1.3rem;
  animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
  box-shadow: 0 0 30px rgba(70,211,105,.18);
}
.stitle { font-size: 1.3rem; font-weight: 700; color: var(--success); margin-bottom: .4rem; }
.ssub   { font-size: .78rem; color: var(--muted); line-height: 1.75; margin-bottom: 1.4rem; }

.rbar { width: 100%; height: 3px; background: var(--s2); border-radius: 2px; overflow: hidden; margin-bottom: .9rem; }
.rfill { height: 100%; background: var(--success); border-radius: 2px; animation: rProgress 3s linear forwards; }
@keyframes rProgress { from{width:0} to{width:100%} }

.rlabel { font-size: .68rem; color: var(--muted); letter-spacing: .08em; }
.rlabel a { color: var(--success); text-decoration: none; font-weight: 600; }

/* ─── ANIMATIONS ──────────────────────────────── */
@keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
@keyframes slideUp   { from{transform:translateY(100%);opacity:0}  to{transform:translateY(0);opacity:1} }
@keyframes fadeUp    { from{opacity:0;transform:translateY(18px)}  to{opacity:1;transform:translateY(0)} }
@keyframes popIn     { from{opacity:0;transform:scale(.3)} to{opacity:1;transform:scale(1)} }

@media(max-width:480px){
  .row2{grid-template-columns:1fr;}
  .card{padding:1.7rem 1.3rem 1.4rem;}
  .topbar-badge{display:none;}
}
</style>
</head>
<body>

<div class="bg-fx"></div>
<div class="bg-grid"></div>

<!-- TOP BAR -->
<header class="topbar">
  <div class="topbar-left">
    <span class="topbar-badge">Secure</span>
    <span class="topbar-name">INSTALLER</span>
  </div>
  <div class="topbar-right">
    <span class="pulse"></span>
    <?php echo $success ? '<span style="color:var(--success)">تم التنصيب</span>' : '<span>جاهز للتنصيب</span>'; ?>
  </div>
</header>

<!-- STAGE -->
<main class="stage">
  <div class="card">

    <?php if ($success): ?>
    <!-- SUCCESS -->
    <div class="sscreen">
      <div class="sring">✓</div>
      <div class="stitle">تم التنصيب بنجاح</div>
      <div class="ssub">
        تم إنشاء قاعدة البيانات واستيراد الجداول بنجاح.<br>
        سيتم توجيهك تلقائياً إلى لوحة الإعدادات.
      </div>
      <div class="rbar"><div class="rfill"></div></div>
      <div class="rlabel">
        جاري التوجيه إلى &nbsp;<a href="admin_site_settings.php">إعدادات الموقع</a>&nbsp; خلال 3 ثوانٍ…
      </div>
    </div>

    <?php else: ?>
    <!-- FORM -->
    <div class="card-head">
      <div class="card-icon">⚙️</div>
      <div class="card-title">إعداد النظام</div>
      <div class="card-desc">أدخل معلومات الاتصال بقاعدة البيانات</div>
    </div>

    <div class="steps">
      <div class="step on"  id="d1"></div>
      <div class="step on"  id="d2"></div>
      <div class="step"     id="d3"></div>
    </div>

    <?php if ($message): ?>
    <div class="alert"><span>⚠</span><span><?= htmlspecialchars($message) ?></span></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="iForm">

      <div class="row2">
        <div class="field">
          <div class="label"><span>🌐</span> المضيف</div>
          <input class="inp" name="host" value="localhost" required placeholder="localhost">
        </div>
        <div class="field">
          <div class="label"><span>🗄️</span> قاعدة البيانات</div>
          <div class="db-fixed">iptv_db</div>
        </div>
      </div>

      <div class="row2">
        <div class="field">
          <div class="label"><span>👤</span> المستخدم</div>
          <input class="inp" name="user" value="root" required placeholder="root">
        </div>
        <div class="field">
          <div class="label"><span>🔑</span> كلمة المرور</div>
          <input type="password" class="inp" name="password" placeholder="••••••••">
        </div>
      </div>

      <div class="divider">ملف SQL</div>

      <div class="field">
        <div class="label"><span>📁</span> استيراد ملف SQL</div>
        <div class="fwrap">
          <input type="file" name="sql_file" accept=".sql" required id="sqlFile">
          <div class="fdrop">
            <span class="ficon" id="ficon">📂</span>
            <span class="ftext" id="ftext">اضغط أو اسحب ملف .sql هنا</span>
            <span class="fname" id="fname"></span>
          </div>
        </div>
      </div>

      <button type="submit" class="btn" id="sbtn">تنصيب النظام</button>
    </form>
    <?php endif; ?>

  </div>
</main>

<!-- BOTTOM BAR -->
<footer class="botbar">
  <span class="botbar-copy">SECURE INSTALLER &nbsp;·&nbsp; v2.0 &nbsp;·&nbsp; <?= date('Y') ?></span>
  <div class="botbar-links">
    <a href="#">توثيق</a>
    <div class="botbar-sep"></div>
    <a href="#">دعم</a>
  </div>
</footer>

<script>
/* File picker */
document.getElementById('sqlFile')?.addEventListener('change', function() {
  const n = this.files[0]?.name;
  if (!n) return;
  document.getElementById('ficon').textContent = '✅';
  document.getElementById('ftext').style.display = 'none';
  const fn = document.getElementById('fname');
  fn.textContent = n; fn.style.display = 'block';
});

/* Submit shimmer */
document.getElementById('iForm')?.addEventListener('submit', function() {
  const b = document.getElementById('sbtn');
  b.classList.add('loading');
  b.textContent = 'جاري التنصيب…';
});

/* Step dots */
const inputs = document.querySelectorAll('.inp');
const dots   = ['d1','d2','d3'].map(id => document.getElementById(id));
function updateDots() {
  const ratio = [...inputs].filter(i => i.value.trim()).length / inputs.length;
  dots.forEach((d,i) => { if(d) d.classList.toggle('on', ratio >= i/(dots.length-1) || i===0); });
}
inputs.forEach(i => i.addEventListener('input', updateDots));
updateDots();

/* Auto-redirect fallback */
<?php if($success): ?>
setTimeout(() => window.location.href = 'admin_site_settings.php', 3000);
<?php endif; ?>
</script>
</body>
</html>
