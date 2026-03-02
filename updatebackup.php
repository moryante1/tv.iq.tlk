<?php
/**
 * ====================================================
 *  GitHub Auto Updater - moryante1/iptv
 *  بدون ZipArchive - يعمل على جميع السيرفرات
 * ====================================================
 */

define('GITHUB_TOKEN',  '');
define('SITE_DIR',      __DIR__);
define('EXCLUDE_FILES', ['update.php', '.htaccess', 'config.php', '.env']);
define('ZIP_URL',       'https://codeload.github.com/moryante1/iptv/zip/refs/heads/main');

function msg(string $text, string $type = 'info'): void {
    $icons  = ['info' => '◈', 'success' => '◆', 'error' => '◉', 'warn' => '◇'];
    $colors = ['info' => '#60a5fa', 'success' => '#34d399', 'error' => '#f87171', 'warn' => '#fbbf24'];
    $bg     = ['info' => 'rgba(96,165,250,0.06)', 'success' => 'rgba(52,211,153,0.06)', 'error' => 'rgba(248,113,113,0.06)', 'warn' => 'rgba(251,191,36,0.06)'];
    $color  = $colors[$type] ?? '#60a5fa';
    $icon   = $icons[$type]  ?? '◈';
    $bgc    = $bg[$type]     ?? 'rgba(96,165,250,0.06)';
    echo "<div class='log-line' style='border-left-color:{$color};background:{$bgc}'>
            <span class='log-icon' style='color:{$color}'>{$icon}</span>
            <span class='log-text'>" . htmlspecialchars($text) . "</span>
          </div>\n";
    ob_flush(); flush();
}

function unzip_manual(string $zip_file, string $dest_dir): array {
    $results = ['ok' => [], 'fail' => []];
    $data    = file_get_contents($zip_file);
    if ($data === false) return $results;
    $len = strlen($data);
    $pos = 0;
    while ($pos < $len - 4) {
        if (substr($data, $pos, 4) !== "PK\x03\x04") { $pos++; continue; }
        $h = unpack('vversion/vflag/vmethod/vmtime/vmdate/Vcrc/Vcomp_size/Vuncomp_size/vname_len/vextra_len',
                    substr($data, $pos + 4, 26));
        $name       = substr($data, $pos + 30, $h['name_len']);
        $data_start = $pos + 30 + $h['name_len'] + $h['extra_len'];
        $comp_data  = substr($data, $data_start, $h['comp_size']);
        $pos        = $data_start + $h['comp_size'];
        if (substr($name, -1) === '/') continue;
        $content = false;
        if ($h['method'] === 0)     $content = $comp_data;
        elseif ($h['method'] === 8) $content = @gzinflate($comp_data);
        if ($content === false) { $results['fail'][] = $name; continue; }
        $full_path = $dest_dir . '/' . $name;
        $dir = dirname($full_path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_put_contents($full_path, $content) !== false) $results['ok'][]   = $name;
        else                                                    $results['fail'][] = $name;
    }
    return $results;
}

function download_zip(string $url): string|false {
    $tmp = sys_get_temp_dir() . '/gh_update_' . time() . '.zip';
    $headers = ['User-Agent: PHP-Updater'];
    if (GITHUB_TOKEN) $headers[] = 'Authorization: token ' . GITHUB_TOKEN;
    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => 90, 'follow_location' => true, 'header' => $headers,
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 100) return false;
    file_put_contents($tmp, $data);
    return $tmp;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    rmdir($dir);
}

function copy_to_site(string $source_dir): array {
    $done = $failed = $skipped = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel  = ltrim(str_replace($source_dir, '', $item->getPathname()), '/\\');
        $rel  = str_replace('\\', '/', $rel);
        $dest = SITE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if ($item->isDir()) { if (!is_dir($dest)) mkdir($dest, 0755, true); continue; }
        if (in_array($item->getFilename(), EXCLUDE_FILES) || in_array($rel, EXCLUDE_FILES)) {
            msg("تخطي (محمي): $rel", "warn"); $skipped++; continue;
        }
        if (str_contains($rel, '.git')) { $skipped++; continue; }
        $dd = dirname($dest);
        if (!is_dir($dd)) mkdir($dd, 0755, true);
        if (copy($item->getPathname(), $dest)) { msg("تم تحديث: $rel", "success"); $done++; }
        else                                   { msg("فشل نسخ: $rel", "error"); $failed++; }
    }
    return [$done, $failed, $skipped];
}

$do_update = isset($_POST['do_update']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IPTV Updater</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #0a0a0f;
  --surface:   #0f0f18;
  --card:      #13131e;
  --border:    rgba(255,255,255,0.07);
  --border-h:  rgba(255,255,255,0.14);
  --red:       #e50914;
  --red-dim:   #b20710;
  --red-glow:  rgba(229,9,20,0.35);
  --text:      #e8e8f0;
  --muted:     #6b6b80;
  --success:   #34d399;
  --error:     #f87171;
  --warn:      #fbbf24;
  --info:      #60a5fa;
  --mono:      'JetBrains Mono', monospace;
  --sans:      'Cairo', sans-serif;
}

html { scroll-behavior: smooth; }

body {
  font-family: var(--sans);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px 60px;
  position: relative;
  overflow-x: hidden;
}

/* ── Cinematic Background ── */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 50% at 50% -10%, rgba(229,9,20,0.12) 0%, transparent 65%),
    radial-gradient(ellipse 60% 40% at 100% 80%,  rgba(229,9,20,0.05) 0%, transparent 60%),
    radial-gradient(ellipse 50% 50% at 0% 100%,   rgba(99,20,229,0.04) 0%, transparent 60%);
  pointer-events: none;
  z-index: 0;
}

/* Noise grain overlay */
body::after {
  content: '';
  position: fixed;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
  pointer-events: none;
  z-index: 0;
  opacity: .4;
}

/* ── Layout ── */
.wrapper {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 680px;
}

/* ── Header ── */
.header {
  text-align: center;
  margin-bottom: 40px;
  animation: fadeDown .7s cubic-bezier(.16,1,.3,1) both;
}

.logo-mark {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.logo-icon {
  width: 44px;
  height: 44px;
  background: var(--red);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  box-shadow: 0 0 30px var(--red-glow), 0 4px 15px rgba(0,0,0,.5);
  position: relative;
  overflow: hidden;
}

.logo-icon::after {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 60%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent);
  animation: shimmer 3s ease-in-out infinite;
}

.logo-text {
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--text);
}

.logo-text span { color: var(--red); }

h1 {
  font-size: clamp(1.8rem, 5vw, 2.5rem);
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -1px;
  background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,.6) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 10px;
}

.subtitle {
  color: var(--muted);
  font-size: .9rem;
  font-weight: 300;
  letter-spacing: .5px;
}

/* ── Card ── */
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 36px;
  position: relative;
  overflow: hidden;
  animation: fadeUp .7s cubic-bezier(.16,1,.3,1) .15s both;
  box-shadow: 0 30px 80px rgba(0,0,0,.6), 0 0 0 1px var(--border);
}

.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(229,9,20,.5), rgba(255,255,255,.1), transparent);
}

/* ── Meta Badges ── */
.meta-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 32px;
}

.meta-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: .75rem;
  font-family: var(--mono);
  color: var(--muted);
  transition: border-color .2s, color .2s;
}

.meta-badge:hover { border-color: var(--border-h); color: var(--text); }

.meta-badge .dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: var(--success);
  box-shadow: 0 0 6px var(--success);
  animation: pulse-dot 2s ease-in-out infinite;
}

/* ── Divider ── */
.divider {
  height: 1px;
  background: var(--border);
  margin: 28px 0;
  position: relative;
}

.divider::after {
  content: '';
  position: absolute;
  left: 0; top: 0;
  width: 60px; height: 1px;
  background: var(--red);
  box-shadow: 0 0 8px var(--red);
}

/* ── Form ── */
.field-label {
  display: block;
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 10px;
}

.input-wrap {
  position: relative;
  margin-bottom: 20px;
}

.input-wrap svg {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
  transition: color .2s;
}

input[type=password] {
  width: 100%;
  padding: 14px 48px 14px 18px;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-family: var(--mono);
  font-size: .95rem;
  letter-spacing: 3px;
  outline: none;
  transition: border-color .25s, background .25s, box-shadow .25s;
}

input[type=password]:focus {
  border-color: var(--red);
  background: rgba(229,9,20,.04);
  box-shadow: 0 0 0 3px rgba(229,9,20,.12);
}

input[type=password]:focus + svg { color: var(--red); }

input[type=password]::placeholder { color: var(--muted); letter-spacing: 1px; font-size: .85rem; }

/* ── Buttons ── */
.btn-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 13px 28px;
  border: none;
  border-radius: 10px;
  font-family: var(--sans);
  font-size: .9rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .25s cubic-bezier(.16,1,.3,1);
  position: relative;
  overflow: hidden;
  letter-spacing: .3px;
  text-decoration: none;
}

.btn::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, transparent 50%);
  opacity: 0;
  transition: opacity .2s;
}

.btn:hover::after { opacity: 1; }

.btn-primary {
  background: var(--red);
  color: #fff;
  box-shadow: 0 4px 20px rgba(229,9,20,.4);
}

.btn-primary:hover {
  background: #f50c1c;
  box-shadow: 0 6px 28px rgba(229,9,20,.55);
  transform: translateY(-1px);
}

.btn-primary:active { transform: translateY(0); }

.btn-ghost {
  background: rgba(255,255,255,.05);
  color: var(--muted);
  border: 1px solid var(--border);
}

.btn-ghost:hover {
  background: rgba(255,255,255,.09);
  color: var(--text);
  border-color: var(--border-h);
}

/* ── Error message ── */
.error-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 14px;
  padding: 10px 14px;
  background: rgba(248,113,113,.08);
  border: 1px solid rgba(248,113,113,.2);
  border-radius: 8px;
  color: var(--error);
  font-size: .85rem;
}

/* ── Ready State ── */
.ready-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  background: rgba(52,211,153,.07);
  border: 1px solid rgba(52,211,153,.18);
  border-radius: 10px;
  margin-bottom: 24px;
}

.ready-banner .ricon {
  font-size: 1.2rem;
  flex-shrink: 0;
}

.ready-banner p { color: var(--success); font-size: .9rem; font-weight: 600; }
.ready-banner small { color: var(--muted); font-size: .78rem; display: block; margin-top: 1px; }

/* ── Log Box ── */
.log-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.log-title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 8px;
}

.live-dot {
  width: 6px; height: 6px;
  background: var(--red);
  border-radius: 50%;
  box-shadow: 0 0 8px var(--red);
  animation: pulse-dot 1s ease-in-out infinite;
}

.log-box {
  background: #080810;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px;
  max-height: 400px;
  overflow-y: auto;
  font-family: var(--mono);
  scrollbar-width: thin;
  scrollbar-color: rgba(229,9,20,.3) transparent;
}

.log-box::-webkit-scrollbar { width: 4px; }
.log-box::-webkit-scrollbar-thumb { background: rgba(229,9,20,.3); border-radius: 2px; }

.log-line {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 7px 10px;
  margin-bottom: 3px;
  border-left: 2px solid;
  border-radius: 4px;
  font-size: .78rem;
  line-height: 1.5;
  animation: slideIn .2s ease both;
}

.log-icon { flex-shrink: 0; font-size: .85rem; margin-top: 1px; }
.log-text  { color: rgba(232,232,240,.8); word-break: break-all; }

/* ── Stats Bar ── */
.stats-bar {
  display: flex;
  gap: 1px;
  margin-top: 16px;
  border-radius: 8px;
  overflow: hidden;
  height: 4px;
  background: rgba(255,255,255,.06);
}

.stat-seg { height: 100%; transition: width .5s ease; }
.stat-seg.ok   { background: var(--success); }
.stat-seg.err  { background: var(--error); }
.stat-seg.skip { background: var(--warn); }

.stats-nums {
  display: flex;
  gap: 20px;
  margin-top: 14px;
}

.stat-item { display: flex; align-items: center; gap: 6px; font-size: .8rem; }
.stat-item .num { font-family: var(--mono); font-weight: 600; }
.stat-item .lbl { color: var(--muted); font-size: .72rem; }

/* ── Footer ── */
.footer {
  text-align: center;
  margin-top: 28px;
  color: var(--muted);
  font-size: .75rem;
  letter-spacing: 1px;
  animation: fadeUp .7s .4s both;
}

.footer a { color: rgba(229,9,20,.7); text-decoration: none; transition: color .2s; }
.footer a:hover { color: var(--red); }

/* ── Animations ── */
@keyframes fadeDown {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: translateX(0); }
}

@keyframes shimmer {
  0%   { left: -100%; }
  50%  { left: 160%; }
  100% { left: 160%; }
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .5; transform: scale(.8); }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Responsive ── */
@media (max-width: 500px) {
  .card { padding: 22px 18px; }
  .btn-row { flex-direction: column; }
  .btn  { width: 100%; justify-content: center; }
  .stats-nums { flex-wrap: wrap; gap: 12px; }
}
</style>
</head>
<body>
<div class="wrapper">

  <!-- Header -->
  <div class="header">
    <div class="logo-mark">
      <div class="logo-icon">📡</div>
      <div class="logo-text">IPTV <span>UPDATE</span></div>
    </div>
    <h1>تحديث الموقع</h1>
    <p class="subtitle">يسحب أحدث نسخة   ويستبدل ملفات الموقع تلقائياً</p>
  </div>

  <!-- Card -->
  <div class="card">

    <!-- Meta Row -->
    <div class="meta-row">
      <div class="meta-badge"><div class="dot"></div> moryante1/iptv</div>
      <div class="meta-badge">⎇ main</div>
      <div class="meta-badge">📁 <?= htmlspecialchars(basename(SITE_DIR)) ?></div>
      <div class="meta-badge">PHP <?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?></div>
    </div>

    <div class="divider"></div>

    <!-- ===== CONFIRM ===== -->
    <?php if (!$do_update): ?>

      <div class="ready-banner">
        <div class="ricon">✦</div>
        <div>
          <p>جاهز للتحديث</p>
          <small>سيتم تنزيل آخر إصدار من المستودع واستبدال ملفات الموقع</small>
        </div>
      </div>

      <form method="post">
        <input type="hidden" name="do_update" value="1">
        <div class="btn-row">
          <button class="btn btn-primary" type="submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="21" x2="12" y2="3"/></svg>
            ابدأ التحديث الآن
          </button>
        </div>
      </form>

    <!-- ===== RUNNING UPDATE ===== -->
    <?php else: ?>

      <div class="log-header">
        <div class="log-title"><div class="live-dot"></div> سجل العمليات</div>
        <div style="font-size:.72rem;color:var(--muted);font-family:var(--mono)"><?= date('H:i:s') ?></div>
      </div>

      <div class="log-box" id="logBox">
        <?php
          msg("جاري الاتصال بـ GitHub وتنزيل الملفات...", "info");
          $zip_path = download_zip(ZIP_URL);

          if (!$zip_path) {
              msg("فشل تنزيل ZIP — تحقق من الاتصال بالإنترنت أو صلاحيات GitHub Token", "error");
          } else {
              $size = round(filesize($zip_path) / 1024, 1);
              msg("تم تنزيل الملف بنجاح ({$size} KB) — جاري فك الضغط", "success");

              $extract_dir = sys_get_temp_dir() . '/gh_' . time();
              mkdir($extract_dir, 0755, true);

              $unzip = unzip_manual($zip_path, $extract_dir);
              unlink($zip_path);

              if (empty($unzip['ok'])) {
                  msg("فشل فك ضغط الملفات — تأكد من تفعيل zlib في php.ini", "error");
              } else {
                  $total_unzipped = count($unzip['ok']);
                  msg("تم فك ضغط {$total_unzipped} ملف بنجاح — جاري النسخ إلى مجلد الموقع", "success");

                  $sub = glob($extract_dir . '/*', GLOB_ONLYDIR);
                  $src = !empty($sub) ? $sub[0] : $extract_dir;

                  [$done, $failed, $skipped] = copy_to_site($src);
                  rrmdir($extract_dir);

                  $total = $done + $failed + $skipped;
                  $pok   = $total > 0 ? round($done   / $total * 100) : 0;
                  $perr  = $total > 0 ? round($failed  / $total * 100) : 0;
                  $pskip = $total > 0 ? round($skipped / $total * 100) : 0;
        ?>
      </div>

      <!-- Stats -->
      <div class="stats-bar">
        <div class="stat-seg ok"   style="width:<?= $pok ?>%"></div>
        <div class="stat-seg err"  style="width:<?= $perr ?>%"></div>
        <div class="stat-seg skip" style="width:<?= $pskip ?>%"></div>
      </div>

      <div class="stats-nums">
        <div class="stat-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span class="num" style="color:var(--success)"><?= $done ?></span>
          <span class="lbl">تم تحديثها</span>
        </div>
        <?php if ($failed > 0): ?>
        <div class="stat-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          <span class="num" style="color:var(--error)"><?= $failed ?></span>
          <span class="lbl">فشل</span>
        </div>
        <?php endif; ?>
        <div class="stat-item">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5"><path d="M5 12h14"/></svg>
          <span class="num" style="color:var(--warn)"><?= $skipped ?></span>
          <span class="lbl">تخطى</span>
        </div>
      </div>

      <div class="divider" style="margin:20px 0"></div>

      <div class="btn-row">
        <a class="btn btn-primary" href="update.php">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
          تحديث مرة أخرى
        </a>
      </div>

      <?php
              }
          }
        // Close the else block cleanly — log box already closed above for success, handle error case:
        if (!$zip_path || empty($unzip['ok'] ?? [])):
      ?>
      </div><!-- /log-box -->
      <div class="divider" style="margin:20px 0"></div>
      <div class="btn-row">
        <a class="btn btn-primary" href="update.php">إعادة المحاولة</a>
      </div>
      <?php endif; ?>

    <?php endif; ?>

  </div><!-- /card -->

  <div class="footer">
    <span>IPTV Updater &nbsp;·&nbsp; moryante1/iptv &nbsp;·&nbsp; </span>
    <a href="https://github.com/moryante1/iptv" target="_blank">GitHub ↗</a>
  </div>

</div><!-- /wrapper -->

<script>
// Auto-scroll log to bottom
const log = document.getElementById('logBox');
if (log) {
  const obs = new MutationObserver(() => log.scrollTop = log.scrollHeight);
  obs.observe(log, { childList: true, subtree: true });
  log.scrollTop = log.scrollHeight;
}
</script>
</body>
</html>
