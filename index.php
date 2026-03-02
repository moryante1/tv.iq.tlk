
<?php
require_once 'config.php';
require_once 'client_config.php';

$license_key     = getLicenseKey();
$license_expired = false;

if ($license_key) {
    $license_result = verifyLicenseFromServer($license_key);
    if (!$license_result['success'] || !$license_result['valid']) {
        $license_expired = true;
    }
} else {
    $license_expired = true;
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$site_name        = $settings['site_name']        ?? 'Shashety';
$site_description = $settings['site_description'] ?? 'نظام IPTV احترافي';
$site_logo        = $settings['site_logo']        ?? '';
$welcome_title    = $settings['welcome_title']    ?? 'مرحباً بك في عالم البث المباشر';
$welcome_subtitle = $settings['welcome_subtitle'] ?? 'شاهد آلاف القنوات من جميع أنحاء العالم';
$footer_text      = $settings['footer_text']      ?? 'جميع الحقوق محفوظة © 2024 Shashety';
$theme_color      = $settings['theme_color']      ?? '#e50914';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#141414">
<title><?php echo htmlspecialchars($site_name); ?> — البث المباشر</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --red:         #e50914;
  --red-hover:   #ff1a27;
  --red-dim:     rgba(229,9,20,.2);
  --red-glow:    rgba(229,9,20,.5);
  --bg:          #0f0f0f;
  --bg2:         #181818;
  --bg3:         #202020;
  --surface:     rgba(28,28,28,.97);
  --border:      rgba(255,255,255,.1);
  --border-h:    rgba(255,255,255,.22);
  --text:        #f0f0f0;
  --text-dim:    #b8b8b8;
  --text-muted:  #707070;
  --accent:      <?php echo htmlspecialchars($theme_color); ?>;
  --white:       #ffffff;
  --radius-sm:   6px;
  --radius:      10px;
  --radius-lg:   16px;
  --radius-xl:   24px;
  --shadow:      0 10px 50px rgba(0,0,0,.8);
  --shadow-red:  0 6px 30px rgba(229,9,20,.45);
  --transition:  all .24s cubic-bezier(.4,0,.2,1);
  --ease-spring: cubic-bezier(.34,1.56,.64,1);
  --ease-out:    cubic-bezier(.16,1,.3,1);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth}
body{font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:inherit;cursor:pointer;border:none;background:none}
img{display:block;max-width:100%}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--red)}

/* ════ ANIMATIONS ════ */
@keyframes shimmer{0%{background-position:-900px 0}100%{background-position:900px 0}}
.skeleton{background:linear-gradient(90deg,#1a1a1a 0%,#252525 40%,#2d2d2d 50%,#252525 60%,#1a1a1a 100%);background-size:900px 100%;animation:shimmer 1.8s ease-in-out infinite;border-radius:var(--radius)}
@keyframes heroReveal{from{opacity:0;transform:translateX(40px) skewX(-8deg);filter:blur(6px)}to{opacity:1;transform:translateX(0) skewX(0);filter:blur(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes cardIn{from{opacity:0;transform:translateY(36px) scale(.93);filter:blur(3px)}to{opacity:1;transform:translateY(0) scale(1);filter:blur(0)}}
@keyframes navSlide{from{transform:translateY(-110%);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes statPop{0%{opacity:0;transform:scale(0.2) translateY(10px)}65%{transform:scale(1.15) translateY(-2px)}85%{transform:scale(0.97)}100%{opacity:1;transform:scale(1) translateY(0)}}
@keyframes badgeSlide{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
@keyframes floatGlow{0%,100%{transform:translateX(-50%) translateY(0) scale(1);opacity:.65}33%{transform:translateX(-48%) translateY(-18px) scale(1.06);opacity:.9}66%{transform:translateX(-52%) translateY(-8px) scale(.98);opacity:.75}}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);box-shadow:0 0 0 0 rgba(229,9,20,.7)}40%{opacity:.8;transform:scale(.8);box-shadow:0 0 0 5px rgba(229,9,20,0)}70%{box-shadow:0 0 0 8px rgba(229,9,20,0)}}
@keyframes glowPulse{0%,100%{box-shadow:0 0 8px rgba(229,9,20,.25),0 0 16px rgba(229,9,20,.1)}50%{box-shadow:0 0 18px rgba(229,9,20,.55),0 0 35px rgba(229,9,20,.2)}}
@keyframes scanPass{0%{transform:translateY(-100%);opacity:0}10%{opacity:1}90%{opacity:1}100%{transform:translateY(300%);opacity:0}}
@keyframes iconBounce{0%{transform:scale(1) rotate(0deg)}30%{transform:scale(1.22) rotate(-7deg)}55%{transform:scale(1.14) rotate(4deg)}75%{transform:scale(1.18) rotate(-3deg)}100%{transform:scale(1.18) rotate(-6deg)}}
@keyframes ripple{to{transform:scale(5);opacity:0}}
@keyframes toast-in{from{opacity:0;transform:translateX(70px) scale(.85)}to{opacity:1;transform:translateX(0) scale(1)}}
@keyframes toast-out{to{opacity:0;transform:translateX(70px) scale(.85)}}
@keyframes shakeIcon{0%,100%{transform:rotate(0deg) scale(1)}8%{transform:rotate(-10deg) scale(1.08)}18%{transform:rotate(10deg) scale(1.08)}28%{transform:rotate(-7deg) scale(1.04)}38%{transform:rotate(7deg) scale(1.04)}48%{transform:rotate(-4deg)}58%{transform:rotate(4deg)}72%{transform:rotate(-2deg)}85%{transform:rotate(2deg)}}
@keyframes lockFloat{0%,100%{transform:translateY(0) scale(1);filter:drop-shadow(0 0 30px rgba(229,9,20,.6)) drop-shadow(0 0 60px rgba(229,9,20,.3))}50%{transform:translateY(-8px) scale(1.04);filter:drop-shadow(0 0 40px rgba(229,9,20,.8)) drop-shadow(0 0 80px rgba(229,9,20,.4))}}
@keyframes lineGrow{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes seriesCardIn{from{opacity:0;transform:scale(.9) translateY(30px);filter:blur(4px)}to{opacity:1;transform:scale(1) translateY(0);filter:blur(0)}}
@keyframes epSlideIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

/* ════ TV FOCUS NAVIGATION (ANDROID TV / TCL) ════ */
.cat-card.tv-focus, .ch-card.tv-focus, .sr-card.tv-focus, .ep-card.tv-focus,
.cat-card:focus, .ch-card:focus, .sr-card:focus, .ep-card:focus {
    transform: translateY(-10px) scale(1.05);
    border-color: var(--red);
    box-shadow: 0 22px 55px rgba(229,9,20,.4), 0 0 0 3px rgba(255,255,255,.5);
    z-index: 10;
    outline: 3px solid rgba(229,9,20,.8);
    outline-offset: 2px;
}
.back-btn.tv-focus, .ctab.tv-focus,
.back-btn:focus, .ctab:focus {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(229,9,20,.5), 0 0 0 3px rgba(255,255,255,.5);
    outline: 3px solid rgba(229,9,20,.8);
    outline-offset: 2px;
}
.cat-card:focus, .ch-card:focus, .sr-card:focus, .ep-card:focus,
.back-btn:focus, .ctab:focus {
    outline-style: solid;
}
.ch-card.tv-focus .ch-thumb::before, .cat-card.tv-focus::before { opacity: 1; }
.ch-card.tv-focus .ch-play-btn, .sr-card.tv-focus .sr-enter-btn { opacity: 1; transform: scale(1) translateY(0); }
.cat-card.tv-focus .cat-icon-wrap { animation: iconBounce .45s var(--ease-spring) forwards; box-shadow: 0 0 28px rgba(229,9,20,.5); background: rgba(229,9,20,.22); }
.cat-card.tv-focus .cat-name, .ch-card.tv-focus .ch-name, .sr-card.tv-focus .sr-name { color: #fff; }

/* ════ DEVTOOLS OVERLAY ════ */
.devtools-overlay{display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.97);backdrop-filter:blur(20px) saturate(.5);align-items:center;justify-content:center;flex-direction:column;animation:fadeIn .3s ease}
.devtools-overlay.show{display:flex}
.devtools-box{background:linear-gradient(160deg,#1a0a0a,#140000,#1a0808);border:1px solid rgba(229,9,20,.35);border-radius:var(--radius-xl);padding:52px 56px;text-align:center;max-width:440px;width:90%;box-shadow:0 0 80px rgba(229,9,20,.25),0 30px 80px rgba(0,0,0,.9);position:relative;overflow:hidden;animation:cardIn .4s var(--ease-spring)}
.devtools-box::before{content:'';position:absolute;top:-60px;left:50%;transform:translateX(-50%);width:300px;height:300px;background:radial-gradient(ellipse,rgba(229,9,20,.18) 0%,transparent 70%);filter:blur(30px);pointer-events:none}
.devtools-lock-icon{font-size:4.5rem;margin-bottom:24px;display:inline-block;animation:lockFloat 3.5s ease-in-out infinite;filter:drop-shadow(0 0 30px rgba(229,9,20,.6)) drop-shadow(0 0 60px rgba(229,9,20,.3))}
.devtools-lock-icon i{color:#ff4d57}
.devtools-lock-icon.shake{animation:shakeIcon .7s ease,lockFloat 3.5s ease-in-out 0.7s infinite}
.devtools-title{font-size:1.7rem;font-weight:900;color:#fff;margin-bottom:10px;letter-spacing:-0.5px}
.devtools-sub{font-size:1rem;color:#707070;line-height:1.6;margin-bottom:28px}
.devtools-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(229,9,20,.12);border:1px solid rgba(229,9,20,.3);padding:8px 20px;border-radius:99px;font-size:.85rem;font-weight:700;color:#ff6060;margin-bottom:30px;letter-spacing:.3px}
.devtools-divider{width:60px;height:2px;background:linear-gradient(90deg,transparent,var(--red),transparent);margin:0 auto 24px}

/* ════ NAVBAR ════ */
.navbar{position:fixed;top:0;left:0;right:0;z-index:900;padding:0 4%;height:68px;display:flex;align-items:center;justify-content:space-between;gap:20px;background:linear-gradient(180deg,rgba(0,0,0,.98) 0%,rgba(0,0,0,0) 100%);animation:navSlide .5s var(--ease-out) both;transition:background .4s,box-shadow .4s}
.navbar.scrolled{background:rgba(15,15,15,.98);backdrop-filter:blur(24px) saturate(1.5);-webkit-backdrop-filter:blur(24px) saturate(1.5);border-bottom:1px solid rgba(255,255,255,.07);box-shadow:0 4px 30px rgba(0,0,0,.6)}
.nav-brand{display:flex;align-items:center;gap:12px;flex-shrink:0}
.nav-logo-img{width:40px;height:40px;border-radius:var(--radius-sm);object-fit:cover}
.nav-logo-text{font-size:1.6rem;font-weight:900;letter-spacing:-1px;color:var(--red);text-shadow:0 0 20px rgba(229,9,20,.6),0 0 50px rgba(229,9,20,.25);transition:text-shadow .3s}
.nav-logo-text:hover{text-shadow:0 0 30px rgba(229,9,20,.9),0 0 60px rgba(229,9,20,.4)}
.nav-center{flex:1;max-width:420px}
.search-wrap{position:relative}
.search-wrap input{width:100%;padding:10px 18px 10px 44px;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.12);border-radius:99px;color:var(--text);font-family:inherit;font-size:.95rem;transition:var(--transition)}
.search-wrap input::placeholder{color:var(--text-muted)}
.search-wrap input:focus{outline:none;background:rgba(255,255,255,.12);border-color:var(--red);box-shadow:0 0 0 3px var(--red-dim),0 0 20px rgba(229,9,20,.15)}
.search-wrap .si{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.9rem;pointer-events:none;transition:var(--transition)}
.search-wrap input:focus~.si{color:var(--red)}
.nav-actions{display:flex;align-items:center;gap:10px}
.nav-btn{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.12);color:var(--text-dim);display:flex;align-items:center;justify-content:center;font-size:.9rem;transition:var(--transition);position:relative;overflow:hidden; cursor:pointer;}
.nav-btn:hover{background:var(--red);border-color:var(--red);color:#fff;transform:scale(1.1)}
/* إضافات النظام للملاحة الجديدة والإشعارات */
#notifBadge{position:absolute; top:4px; right:4px; width:7px; height:7px; box-shadow: 0 0 10px #ff3040;}
.fp-panel,.np-panel{position:fixed;top:0;width:380px;height:100%;z-index:9996;background:rgba(10,10,10,.97);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;transition:.4s var(--ease-out);box-shadow:0 0 40px rgba(0,0,0,.8);}
.fp-panel{left:-420px;border-right:1px solid rgba(255,255,255,.1);}
.fp-panel.open{left:0;}
.np-panel{left:-420px;border-right:1px solid rgba(255,255,255,.1);}
.np-panel.open{left:0;}
@media(max-width:768px){.fp-panel,.np-panel{width:100%;left:-100%;}}

/* ════ HERO ════ */
.hero-banner{position:relative;min-height:440px;margin-top:0;display:flex;align-items:flex-end;overflow:hidden;border-radius:0 0 var(--radius-xl) var(--radius-xl)}
.hero-bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 70% 30%,rgba(229,9,20,.18),transparent 60%),radial-gradient(ellipse 50% 40% at 20% 70%,rgba(229,9,20,.08),transparent 50%),linear-gradient(160deg,#0a0a0a 0%,#1a0808 40%,#0f0f0f 100%)}
.hero-bg-grid{position:absolute;inset:0;opacity:.05;background-image:linear-gradient(rgba(255,255,255,.6) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.6) 1px,transparent 1px);background-size:44px 44px}
.hero-bg-scan{position:absolute;inset:0;overflow:hidden;pointer-events:none}
.hero-bg-scan::after{content:'';position:absolute;left:0;right:0;top:0;height:160px;background:linear-gradient(180deg,transparent 0%,rgba(229,9,20,.04) 50%,transparent 100%);animation:scanPass 5s ease-in-out infinite}
.hero-bg-glow{position:absolute;top:-30px;left:50%;transform:translateX(-50%);width:700px;height:350px;background:radial-gradient(ellipse,rgba(229,9,20,.25) 0%,transparent 70%);filter:blur(50px);animation:floatGlow 6s ease-in-out infinite}
.hero-bg-noise{position:absolute;inset:0;opacity:.025;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E")}
.hero-content{position:relative;z-index:2;padding:110px 4% 55px;width:100%}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.35);padding:6px 16px;border-radius:99px;margin-bottom:18px;font-size:.8rem;font-weight:700;color:#ff4d57;letter-spacing:.8px;animation:badgeSlide .6s var(--ease-out) .15s both;backdrop-filter:blur(8px)}
.live-dot{width:8px;height:8px;border-radius:50%;background:#ff3040;animation:pulse-dot 1.8s ease-in-out infinite}
.hero-title{font-size:clamp(1.9rem,4vw,3.2rem);font-weight:900;letter-spacing:-1.5px;line-height:1.08;margin-bottom:14px;background:linear-gradient(135deg,#ffffff 0%,#f0f0f0 60%,#c0c0c0 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:heroReveal .7s var(--ease-out) .2s both}
.hero-sub{font-size:1.05rem;color:#a0a0a0;line-height:1.65;max-width:500px;margin-bottom:32px;animation:fadeUp .6s var(--ease-out) .35s both}
.hero-stats{display:flex;gap:32px;flex-wrap:wrap;animation:fadeUp .6s var(--ease-out) .5s both}
.stat{display:flex;flex-direction:column}
.stat-n{font-size:1.7rem;font-weight:900;color:#ff3040;line-height:1;text-shadow:0 0 20px rgba(229,9,20,.5);animation:statPop .65s var(--ease-spring) .75s both}
.stat-l{font-size:.8rem;color:var(--text-muted);font-weight:600;margin-top:3px}

/* ════ MAIN ════ */
.main{padding:0 4% 60px}
.section{margin:44px 0}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:26px}
.section-title{display:flex;align-items:center;gap:12px;font-size:1.28rem;font-weight:800;color:var(--white);position:relative}
.section-title::after{content:'';position:absolute;bottom:-8px;right:0;width:40px;height:2px;background:linear-gradient(90deg,var(--red),transparent);transform-origin:right;animation:lineGrow .6s var(--ease-out) .3s both}
.section-title-icon{width:34px;height:34px;border-radius:var(--radius-sm);background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);display:flex;align-items:center;justify-content:center;color:#ff4d57;font-size:.88rem;box-shadow:0 0 15px rgba(229,9,20,.2)}
.section-count{font-size:.8rem;font-weight:600;color:var(--text-muted);background:var(--bg3);border:1px solid var(--border);padding:2px 10px;border-radius:99px}

/* ════ CATEGORIES ════ */
.categories-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.cat-card{animation:cardIn .55s var(--ease-out) both}
.cat-card{position:relative;overflow:hidden;background:linear-gradient(145deg,#1c1c1c,#161616);border:1.5px solid rgba(255,255,255,.09);border-radius:var(--radius-lg);padding:26px 16px 22px;text-align:center;cursor:pointer;transition:transform .35s var(--ease-spring),border-color .3s,box-shadow .3s}
.cat-card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(229,9,20,.12) 0%,transparent 60%);opacity:0;transition:opacity .4s;border-radius:var(--radius-lg)}
.cat-card:hover{transform:translateY(-10px) scale(1.025);border-color:rgba(229,9,20,.55);box-shadow:0 22px 55px rgba(229,9,20,.28),0 0 0 1px rgba(229,9,20,.15)}
.cat-card:hover::before{opacity:1}
.cat-card .ripple-el{position:absolute;border-radius:50%;background:rgba(229,9,20,.25);transform:scale(0);animation:ripple .6s linear;pointer-events:none}
.cat-icon-wrap{width:60px;height:60px;border-radius:var(--radius);background:rgba(229,9,20,.14);border:1px solid rgba(229,9,20,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.55rem;color:#ff4d57;position:relative;z-index:1;transition:transform .35s var(--ease-spring),box-shadow .3s,background .3s}
.cat-card:hover .cat-icon-wrap{animation:iconBounce .45s var(--ease-spring) forwards;box-shadow:0 0 28px rgba(229,9,20,.5),0 8px 20px rgba(229,9,20,.2);background:rgba(229,9,20,.22)}
.cat-name{font-size:1.02rem;font-weight:700;color:#f0f0f0;position:relative;z-index:1;margin-bottom:7px;transition:color .25s}
.cat-count{font-size:.8rem;color:var(--text-muted);position:relative;z-index:1;background:rgba(255,255,255,.06);display:inline-block;padding:2px 10px;border-radius:99px;border:1px solid rgba(255,255,255,.08);transition:background .25s,color .25s}
.cat-series-badge{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;color:#B36BFF;font-weight:700;background:rgba(179,107,255,.1);border:1px solid rgba(179,107,255,.2);padding:1px 8px;border-radius:99px;margin-top:5px;position:relative;z-index:1}
.cat-card:hover .cat-count{background:rgba(229,9,20,.15);color:#ff6060;border-color:rgba(229,9,20,.2)}

/* ════ INFO ACTION BTN & FAVORITES ════ */
.info-action-btn {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    color: var(--text-dim);
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .2s;
    margin-right: 8px;
    flex-shrink: 0;
}
.info-action-btn:hover {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
    transform: scale(1.1);
}
.info-action-btn.active-fav i {
    color: var(--red);
    animation: iconBounce .45s var(--ease-spring);
}
.info-action-btn.active-fav:hover { background:transparent; border-color: var(--red); }

/* ════ TMDB MODAL ════ */
.tmdb-modal-overlay {
    position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,.85); backdrop-filter: blur(8px);
    display: none; align-items: center; justify-content: center; padding: 20px;
}
.tmdb-modal-box {
    background: var(--bg2); border: 1px solid var(--border); border-radius: var(--radius-lg);
    width: 100%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column;
    box-shadow: var(--shadow); animation: cardIn .3s var(--ease-out);
}
.tmdb-modal-head {
    padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;
}
.tmdb-modal-close {
    width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
    color: #ccc; display: flex; align-items: center; justify-content: center; transition: .2s; cursor:pointer;
}
.tmdb-modal-close:hover { background: var(--red); color: #fff; border-color: var(--red); }
.tmdb-modal-body { padding: 22px; overflow-y: auto; }
.tmdb-info-wrap { display: flex; gap: 18px; flex-wrap: wrap; direction:rtl; text-align:right;}
.tmdb-info-poster { width: 140px; border-radius: var(--radius); flex-shrink: 0; border: 1px solid var(--border); object-fit: cover; background:var(--bg3); }
.tmdb-info-details { flex: 1; min-width: 200px; }
.tmdb-info-title { font-size: 1.3rem; font-weight: 800; color: #fff; margin-bottom: 8px; line-height:1.2; }
.tmdb-info-meta { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 14px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.tmdb-genre-badge { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); padding: 3px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; color: #ccc; }
.tmdb-info-overview { font-size: 0.9rem; color: #ddd; line-height: 1.7; background: rgba(0,0,0,.3); padding: 14px; border-radius: var(--radius); border: 1px solid rgba(255,255,255,.05); }

/* ════ CHANNELS ════ */
.channels-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px}
.ch-card{animation:cardIn .48s var(--ease-out) both}
.ch-card{position:relative;overflow:hidden;background:linear-gradient(160deg,#1c1c1c,#141414);border:1.5px solid rgba(255,255,255,.09);border-radius:var(--radius-lg);cursor:pointer;transition:transform .35s var(--ease-spring),border-color .3s,box-shadow .3s}
.ch-card:hover{transform:translateY(-10px) scale(1.04);border-color:rgba(229,9,20,.65);box-shadow:0 22px 50px rgba(229,9,20,.32),0 0 0 1px rgba(229,9,20,.15);z-index:2}
.ch-thumb{aspect-ratio:16/9;width:100%;background:linear-gradient(135deg,#1e1e1e,#181818);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.ch-thumb::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 50%,rgba(229,9,20,.08) 0%,transparent 70%);opacity:0;transition:opacity .4s}
.ch-card:hover .ch-thumb::before{opacity:1}
.ch-thumb img{width:65%;height:65%;object-fit:contain;transition:transform .4s var(--ease-spring),filter .3s;filter:drop-shadow(0 2px 8px rgba(0,0,0,.5))}
.ch-card:hover .ch-thumb img{transform:scale(1.1);filter:drop-shadow(0 4px 12px rgba(229,9,20,.25))}
.ch-thumb .ch-icon{font-size:2.3rem;color:#555;transition:color .3s,transform .35s var(--ease-spring)}
.ch-card:hover .ch-thumb .ch-icon{color:#ff4d57;transform:scale(1.18)}
.ch-thumb-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);display:flex;align-items:center;justify-content:center;transition:background .3s}
.ch-card:hover .ch-thumb-overlay{background:rgba(0,0,0,.45)}
.ch-play-btn{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#e50914,#ff1a27);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;opacity:0;transform:scale(.5) translateY(6px);transition:opacity .3s var(--ease-out),transform .4s var(--ease-spring);box-shadow:0 6px 25px rgba(229,9,20,.6)}
.ch-card:hover .ch-play-btn{opacity:1;transform:scale(1) translateY(0)}
.ch-live-badge{position:absolute;top:8px;right:8px;background:linear-gradient(135deg,#d00810,#e50914);color:#fff;font-size:.65rem;font-weight:800;padding:3px 9px;border-radius:4px;letter-spacing:.6px;box-shadow:0 2px 8px rgba(229,9,20,.4);animation:glowPulse 3s ease-in-out infinite}
.ch-fmt-badge{position:absolute;top:8px;left:8px;background:rgba(0,0,0,.7);color:#aaa;font-size:.6rem;font-weight:800;padding:2px 7px;border-radius:4px;letter-spacing:.5px;text-transform:uppercase;border:1px solid rgba(255,255,255,.15)}
.ch-info{padding:12px 14px 15px}
.ch-name{font-size:.92rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;transition:color .25s}
.ch-card:hover .ch-name{color:#fff}
.ch-meta{font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:6px}

/* ════ POSTER MODE OVERRIDE ════ */
.ch-thumb.poster-mode{aspect-ratio:2/3;background:#111}
.ch-thumb.poster-mode img{width:100%;height:100%;object-fit:cover;filter:none}
.ch-card:hover .ch-thumb.poster-mode img{transform:scale(1.05);filter:brightness(1.1)}

/* ════ SERIES ════ */
.series-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px}
.sr-card{position:relative;overflow:hidden;background:linear-gradient(160deg,#1c1c1c,#141414);border:1.5px solid rgba(255,255,255,.09);border-radius:var(--radius-lg);cursor:pointer;transition:transform .35s var(--ease-spring),border-color .3s,box-shadow .3s;animation:seriesCardIn .55s var(--ease-out) both}
.sr-card:hover{transform:translateY(-10px) scale(1.03);border-color:rgba(179,107,255,.65);box-shadow:0 22px 50px rgba(179,107,255,.22),0 0 0 1px rgba(179,107,255,.15);z-index:2}
.sr-poster{aspect-ratio:2/3;width:100%;background:linear-gradient(135deg,#1e1e1e,#181818);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.sr-poster img{width:100%;height:100%;object-fit:cover;transition:transform .4s var(--ease-spring)}
.sr-card:hover .sr-poster img{transform:scale(1.07)}
.sr-poster .sr-icon{font-size:2.8rem;color:#444;transition:color .3s}
.sr-card:hover .sr-poster .sr-icon{color:#B36BFF}
.sr-poster-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);display:flex;align-items:center;justify-content:center;transition:background .3s}
.sr-card:hover .sr-poster-overlay{background:rgba(0,0,0,.5)}
.sr-enter-btn{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#7B2FBE,#B36BFF);color:#fff;font-size:1.15rem;display:flex;align-items:center;justify-content:center;opacity:0;transform:scale(.5);transition:opacity .3s var(--ease-out),transform .4s var(--ease-spring);box-shadow:0 6px 25px rgba(179,107,255,.6)}
.sr-card:hover .sr-enter-btn{opacity:1;transform:scale(1)}
.sr-ep-count{position:absolute;top:8px;left:8px;background:linear-gradient(135deg,#7B2FBE,#B36BFF);color:#fff;font-size:.65rem;font-weight:800;padding:3px 9px;border-radius:4px}
.sr-info{padding:12px 14px 15px}
.sr-name{font-size:.92rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;transition:color .25s}
.sr-card:hover .sr-name{color:#fff}
.sr-meta{font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:6px}

/* ════ EPISODES ════ */
.episodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}
.ep-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    padding: 0;
    background: #181818;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--radius);
    overflow: hidden;
    height: 100%;
    cursor: pointer;
    transition: var(--transition);
    animation: epSlideIn .4s var(--ease-out) both;
}
.ep-card:hover {
    transform: translateY(-5px);
    border-color: var(--red);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
.ep-thumb-area {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    background: linear-gradient(135deg, #252525, #1a1a1a);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.ep-thumb-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 0;
    pointer-events: none;
}
.ep-thumb-icon {
    font-size: 2.5rem;
    color: rgba(255,255,255,0.6);
    transition: 0.3s;
    z-index: 2;
    position: absolute;
    filter: drop-shadow(0 0 10px rgba(0,0,0,0.8));
}
.ep-card:hover .ep-thumb-icon {
    color: var(--red);
    transform: scale(1.15);
    opacity: 0.8;
}
.ep-num-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--red);
    color: #fff;
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.5);
    z-index: 3;
}
.ep-info-box {
    padding: 12px;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.05);
    background: #151515;
}
.ep-date-text {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-weight: 600;
}

/* ════ TABS ════ */
.content-tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:99px;padding:4px;margin-bottom:26px;width:fit-content}
.ctab{padding:8px 22px;border-radius:99px;font-size:.88rem;font-weight:700;color:var(--text-muted);transition:var(--transition)}
.ctab.on{background:var(--red);color:#fff;box-shadow:0 4px 16px rgba(229,9,20,.4)}
.ctab:hover:not(.on){color:var(--text);background:rgba(255,255,255,.06)}

/* ════ BACK BTN ════ */
.back-btn{display:inline-flex;align-items:center;gap:10px;padding:10px 22px;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.14);border-radius:99px;color:var(--text);font-weight:700;font-size:.9rem;transition:var(--transition);margin-bottom:28px;position:relative;overflow:hidden}
.back-btn:hover{background:rgba(229,9,20,.12);border-color:rgba(229,9,20,.5);color:#ff4d57;transform:translateX(3px)}

/* ════ LICENSE ════ */
.license-banner{position:fixed;top:0;left:0;right:0;z-index:9999;background:linear-gradient(135deg,#9a0000,#c00,#b71c1c);padding:14px 20px;display:flex;align-items:center;justify-content:center;gap:16px;font-weight:700;font-size:.9rem;box-shadow:0 4px 25px rgba(183,28,28,.6);animation:fadeUp .4s var(--ease-out)}
.lic-renew{background:rgba(255,255,255,.2);color:#fff;padding:7px 18px;border-radius:99px;font-weight:800;transition:var(--transition);border:1px solid rgba(255,255,255,.3)}
.lic-renew:hover{background:rgba(255,255,255,.35);transform:scale(1.04)}
.license-wall{text-align:center;padding:80px 20px}
.btn-primary{display:inline-flex;align-items:center;gap:10px;padding:14px 32px;background:linear-gradient(135deg,#d00810,var(--red));color:#fff;border-radius:99px;font-weight:800;font-size:1rem;box-shadow:var(--shadow-red);transition:var(--transition)}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 10px 35px rgba(229,9,20,.55)}

/* ════ TOAST ════ */
.toasts{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;direction:rtl}
.toast{background:rgba(24,24,24,.97);color:var(--text);border:1px solid rgba(255,255,255,.1);border-right:3px solid var(--red);padding:12px 18px;border-radius:var(--radius);font-size:.86rem;font-weight:600;box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;animation:toast-in .35s var(--ease-spring);backdrop-filter:blur(20px)}
.toast.out{animation:toast-out .28s forwards}

/* ════ FOOTER ════ */
.footer{background:linear-gradient(180deg,var(--bg2) 0%,#0f0f0f 100%);border-top:1px solid rgba(255,255,255,.07);padding:44px 4%;text-align:center}
.footer-logo{font-size:1.55rem;font-weight:900;color:var(--red);margin-bottom:18px;text-shadow:0 0 20px rgba(229,9,20,.4)}
.footer-links{display:flex;align-items:center;justify-content:center;gap:22px;flex-wrap:wrap;margin-bottom:18px}
.footer-link{font-size:.86rem;color:var(--text-muted);transition:var(--transition)}
.footer-link:hover{color:#ff4d57}
.footer-copy{font-size:.8rem;color:var(--text-muted)}
.social-row{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:18px}
.soc-btn{width:40px;height:40px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);color:var(--text-muted);font-size:.95rem;display:flex;align-items:center;justify-content:center;transition:var(--transition)}
.soc-btn:hover{background:var(--red);border-color:var(--red);color:#fff;transform:translateY(-4px);box-shadow:0 8px 20px rgba(229,9,20,.35)}

.hidden{display:none!important}
.fade-in{animation:fadeIn .45s ease both}

@media(max-width:1024px){.categories-row{grid-template-columns:repeat(4,1fr);gap:12px}.series-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}}
@media(max-width:768px){.navbar{padding:0 16px;height:60px}.nav-logo-text{font-size:1.3rem}.nav-center{max-width:100%}.hero-content{padding:80px 16px 44px}.main{padding:0 16px 52px}.categories-row{grid-template-columns:repeat(2,1fr);gap:12px}.channels-row{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}.series-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}.episodes-grid{grid-template-columns:repeat(2,1fr);gap:10px}}
@media(max-width:480px){.categories-row{grid-template-columns:repeat(2,1fr);gap:10px}.channels-row{grid-template-columns:repeat(2,1fr)}.series-grid{grid-template-columns:repeat(2,1fr)}.hero-title{font-size:1.5rem}}

/* ════ PLAYER OVERLAY ════ */
@keyframes playerSlideIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
@keyframes spin2{to{transform:rotate(360deg)}}
.player-overlay{position:fixed;inset:0;z-index:9990;background:#000;display:none;flex-direction:column;width:100vw;height:100vh;height:100dvh;overflow:hidden;}
.player-overlay.active{display:flex;animation:playerSlideIn .3s var(--ease-out)}
.player-overlay.idle{cursor:none}.player-overlay.idle *{cursor:none!important}
.pv-wrap{position:relative;flex:1;width:100%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#000}
video#html5Player{width:100%;height:100%;max-width:100vw;max-height:100vh;max-height:100dvh;object-fit:contain}
video#html5Player.enh-deblock{filter:url(#enh-deblock);transform:translateZ(0);will-change:filter;}
video#html5Player.enh-hdr{filter:url(#enh-hdr);transform:translateZ(0);will-change:filter;}
video#html5Player.enh-frame{filter:url(#enh-frame);transform:translateZ(0);image-rendering:optimizeQuality;-ms-interpolation-mode:bicubic;will-change:filter,transform;}
video#html5Player.enh-full{filter:url(#enh-full);transform:translateZ(0);image-rendering:optimizeQuality;will-change:filter;}
video#html5Player.enh-off{filter:none !important;image-rendering:auto !important;}
.p-buffer{position:absolute;inset:0;display:none;align-items:center;justify-content:center;pointer-events:none;z-index:15}.p-buffer.show{display:flex}
.p-buffer-ring{width:58px;height:58px;border:4px solid rgba(255,255,255,.1);border-top-color:var(--red);border-radius:50%;animation:spin2 .75s linear infinite}
.p-flash{position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;z-index:20}
.p-flash-icon{width:80px;height:80px;border-radius:50%;background:rgba(0,0,0,.5);border:2px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;opacity:0;transform:scale(.5);transition:.3s}.p-flash-icon.show{opacity:1;transform:scale(1)}
.p-ctrl:focus,.p-ctrl.tv-focus{outline:none;background:rgba(229,9,20,.5)!important;box-shadow:0 0 0 3px rgba(229,9,20,.9)!important;transform:none!important;border-radius:8px!important}
.p-menu-item:focus,.p-menu-item.tv-focus{background:var(--red)!important;color:#fff!important;outline:none}
.p-ctrl.active-magic{color:#ff4d57;text-shadow:0 0 15px rgba(229,9,20,.8)}
.p-top{position:absolute;top:0;left:0;right:0;z-index:30;padding:30px 4% 80px;padding-top:max(30px, calc(30px + env(safe-area-inset-top)));background:linear-gradient(180deg,rgba(0,0,0,.9) 0%,rgba(0,0,0,.4) 40%,transparent 100%);display:flex;align-items:center;justify-content:space-between;transition:opacity .15s}
.p-top.hide{opacity:0;pointer-events:none}
.p-top-info{display:flex;align-items:center;gap:16px;flex:1}
.p-live-badge{background:var(--red);color:#fff;padding:4px 12px;border-radius:4px;font-size:.75rem;font-weight:800;text-transform:uppercase;box-shadow:0 0 15px var(--red-glow);animation:glowPulse 3s ease-in-out infinite}
.p-channel-name{font-size:1.25rem;font-weight:800;color:#fff;text-shadow:0 2px 10px rgba(0,0,0,.8)}
.p-fmt-tag{font-size:.7rem;font-weight:700;color:#aaa;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);padding:2px 9px;border-radius:99px;margin-right:6px;text-transform:uppercase}
.p-ep-nav{align-items:center;gap:12px}
.p-bottom{position:absolute;bottom:0;left:0;right:0;z-index:30;padding:80px 4% 30px;padding-bottom:max(30px, calc(30px + env(safe-area-inset-bottom)));background:linear-gradient(0deg,rgba(0,0,0,.9) 0%,rgba(0,0,0,.4) 40%,transparent 100%);transition:opacity .15s}
.p-bottom.hide{opacity:0;pointer-events:none}
.p-progress-wrap{margin-bottom:20px;padding:10px 0;cursor:pointer}
.p-progress-bar{position:relative;height:6px;background:rgba(255,255,255,.2);border-radius:10px;transition:height .2s}.p-progress-wrap:hover .p-progress-bar{height:8px}
.p-progress-fill{position:absolute;left:0;top:0;height:100%;background:var(--red);border-radius:10px;width:0;box-shadow:0 0 15px var(--red-glow)}
.p-progress-handle{position:absolute;right:-7px;top:50%;transform:translateY(-50%) scale(0);width:14px;height:14px;background:#fff;border-radius:50%;transition:transform .2s}.p-progress-wrap:hover .p-progress-handle{transform:translateY(-50%) scale(1)}
.p-controls{display:flex;align-items:center;justify-content:center;gap:25px;direction:ltr}
.p-ctrl{color:#fff;font-size:1.4rem;cursor:pointer;opacity:.85;transition:opacity .1s,background .1s,box-shadow .1s;display:flex;align-items:center;justify-content:center;background:none;border:none;outline:none}.p-ctrl:hover{opacity:1;transform:scale(1.15)}
.p-ctrl.play-btn{font-size:2.8rem;opacity:1;width:60px}
.p-time-display{font-size:.9rem;font-weight:700;color:rgba(255,255,255,.8);min-width:100px;text-align:center;font-family:monospace}
.p-vol-wrap{display:flex;align-items:center;gap:10px;width:130px;margin-left:10px}
.p-vol-slider{flex:1;height:4px;background:rgba(255,255,255,.2);border-radius:10px;cursor:pointer}.p-vol-fill{height:100%;background:#fff;border-radius:10px;width:100%}
.p-menu{position:absolute;bottom:110px;background:rgba(20,20,20,.95);backdrop-filter:blur(15px);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:8px;display:none;min-width:140px;box-shadow:0 10px 40px rgba(0,0,0,.5)}.p-menu.show{display:block;animation:fadeUp .3s}
.p-menu-item{padding:10px 15px;color:#ddd;font-size:.85rem;font-weight:600;cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:space-between}.p-menu-item:hover{background:rgba(255,255,255,.1);color:#fff}.p-menu-item.active{color:var(--red)}
.ep-panel{position:fixed;top:0;right:-420px;width:380px;height:100%;z-index:9995;background:rgba(10,10,10,.97);backdrop-filter:blur(20px);border-left:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;transition:right .4s var(--ease-out);box-shadow:-10px 0 40px rgba(0,0,0,.7)}.ep-panel.open{right:0}
.ep-panel-head{padding:24px 20px;background:linear-gradient(180deg,rgba(229,9,20,.08) 0%,transparent 100%);border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.ep-panel-title{font-size:1.05rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px}
.ep-panel-close{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s}.ep-panel-close:hover{background:var(--red);color:#fff;border-color:var(--red)}
.ep-panel-body{flex:1;overflow-y:auto;padding:14px}.ep-panel-body::-webkit-scrollbar{width:3px}.ep-panel-body::-webkit-scrollbar-thumb{background:rgba(229,9,20,.4);border-radius:99px}
.ep-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;cursor:pointer;margin-bottom:6px;border:1.5px solid rgba(255,255,255,.05);background:rgba(255,255,255,.03);transition:all .22s}.ep-item:hover{background:rgba(229,9,20,.1);border-color:rgba(229,9,20,.35);transform:translateX(-3px)}.ep-item.playing{background:rgba(229,9,20,.15);border-color:rgba(229,9,20,.5)}
.ep-item-num{width:38px;height:38px;border-radius:50%;background:rgba(229,9,20,.12);border:1.5px solid rgba(229,9,20,.3);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:900;color:#ff4d57;flex-shrink:0}.ep-item.playing .ep-item-num{background:var(--red);border-color:var(--red);color:#fff}
.ep-item-info{flex:1;min-width:0}.ep-item-title{font-size:.88rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}.ep-item.playing .ep-item-title{color:#fff}
.ep-item-meta{font-size:.72rem;color:#666}
.ep-item-play{width:30px;height:30px;border-radius:50%;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:#ff4d57;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0;opacity:0;transition:.2s}.ep-item:hover .ep-item-play,.ep-item.playing .ep-item-play{opacity:1}

/* ════ M3U PLAYLIST PANEL ════ */
.m3u-panel{position:fixed;top:0;left:-420px;width:380px;height:100%;z-index:9995;background:rgba(10,10,10,.97);backdrop-filter:blur(20px);border-right:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;transition:left .4s var(--ease-out);box-shadow:10px 0 40px rgba(0,0,0,.7)}.m3u-panel.open{left:0}
.m3u-panel-head{padding:24px 20px;background:linear-gradient(180deg,rgba(229,9,20,.08) 0%,transparent 100%);border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.m3u-panel-body{flex:1;overflow-y:auto;padding:14px}
.m3u-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;cursor:pointer;margin-bottom:5px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.03);transition:all .2s}
.m3u-item:hover{background:rgba(229,9,20,.1);border-color:rgba(229,9,20,.35)}.m3u-item.playing{background:rgba(229,9,20,.15);border-color:rgba(229,9,20,.5)}
.m3u-item-logo{width:36px;height:36px;border-radius:8px;object-fit:contain;background:rgba(255,255,255,.07);flex-shrink:0}
.m3u-item-name{font-size:.86rem;font-weight:700;color:#f0f0f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.m3u-item-group{font-size:.7rem;color:#666}

@media(max-width:768px){
  .p-top{padding:20px 16px 60px;padding-top:max(20px,calc(12px + env(safe-area-inset-top)))}
  .p-bottom{padding:60px 16px 20px;padding-bottom:max(20px,calc(16px + env(safe-area-inset-bottom)));bottom:0;bottom:env(safe-area-inset-bottom,0px);}
  .p-controls{gap:15px}
  .p-ctrl{font-size:1.2rem}
  .p-ctrl.play-btn{font-size:2.2rem;width:50px}
  .p-vol-wrap{width:90px}
  .ep-panel{width:100%;right:-100%}
  .m3u-panel{width:100%;left:-100%}
}
@media(max-width:480px){
  .p-controls{gap:10px}
  .p-ctrl{font-size:1.1rem;min-width:32px}
  .p-ctrl.play-btn{font-size:1.8rem;width:40px}
  .p-time-display{font-size:.75rem;min-width:70px}
  .p-vol-wrap{display:none}
  #enhanceBtn,#m3uPanelBtn,#epPanelBtn{font-size:.95rem}
}

/* ════ TV / Android Remote Extra Fixes ════ */
.cat-card, .ch-card, .sr-card, .ep-card, .back-btn, .ctab {
    -webkit-user-select: none;
    user-select: none;
}
.cat-card:focus-visible, .ch-card:focus-visible, .sr-card:focus-visible, .ep-card:focus-visible {
    outline: 3px solid var(--red) !important;
    outline-offset: 3px !important;
}
[tabindex]:focus { outline: none; }
</style>
</head>
<body>

<svg style="display:none" xmlns="http://www.w3.org/2000/svg">
  <filter id="enh-deblock" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feGaussianBlur stdDeviation="0.45" result="blurred"/>
    <feComposite in="SourceGraphic" in2="blurred" operator="arithmetic" k1="0" k2="1.6" k3="-0.6" k4="0" result="unsharp"/>
    <feBlend in="unsharp" in2="blurred" mode="normal" result="denoised"/>
    <feComposite in="denoised" in2="SourceGraphic" operator="in"/>
  </filter>
  <filter id="enh-hdr" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feColorMatrix type="saturate" values="1.1"/>
    <feComponentTransfer>
      <feFuncR type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncG type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncB type="table" tableValues="0.00 0.04 0.15 0.34 0.57 0.78 0.92 1.00"/>
    </feComponentTransfer>
  </filter>
  <filter id="enh-frame" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feConvolveMatrix order="3" preserveAlpha="true"
      kernelMatrix="-0.1 -0.15 -0.1
                   -0.15  2.1 -0.15
                   -0.1 -0.15 -0.1"
      result="edges"/>
  </filter>
  <filter id="enh-full" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
    <feGaussianBlur stdDeviation="0.35" result="soft"/>
    <feComposite in="SourceGraphic" in2="soft" operator="arithmetic" k1="0" k2="1.5" k3="-0.5" k4="0" result="deblocked"/>
    <feColorMatrix in="deblocked" type="saturate" values="1.08" result="sat"/>
    <feComponentTransfer in="sat" result="hdr">
      <feFuncR type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncG type="table" tableValues="0.00 0.05 0.18 0.38 0.60 0.80 0.93 1.00"/>
      <feFuncB type="table" tableValues="0.00 0.04 0.15 0.34 0.57 0.78 0.92 1.00"/>
    </feComponentTransfer>
    <feConvolveMatrix in="hdr" order="3" preserveAlpha="true"
      kernelMatrix="-0.08 -0.12 -0.08
                   -0.12  1.8  -0.12
                   -0.08 -0.12 -0.08"/>
  </filter>
</svg>

<!-- ════ DEVTOOLS OVERLAY ════ -->
<div class="devtools-overlay" id="devtoolsOverlay">
  <div class="devtools-box">
    <div class="devtools-lock-icon" id="lockIcon"><i class="fas fa-shield-halved"></i></div>
    <div class="devtools-title">السيرفر محمي</div>
    <div class="devtools-divider"></div>
    <div class="devtools-badge"><i class="fas fa-lock" style="font-size:.75rem"></i>حماية متقدمة مفعّلة</div>
    <div class="devtools-sub">هذا النظام محمي بالكامل.<br>لا يُسمح بالوصول إلى أدوات المطور.</div>
  </div>
</div>

<?php if($license_expired): ?>
<div class="license-banner">
  <i class="fas fa-shield-xmark"></i>
  <span>الرخصة منتهية — يرجى التجديد للوصول للمحتوى</span>
  <a href="activate.php" class="lic-renew"><i class="fas fa-rotate"></i> تجديد الآن</a>
</div>
<div style="height:50px"></div>
<?php endif; ?>

<!-- ════ NAVBAR ════ -->
<nav class="navbar" id="navbar">
  <div class="nav-brand">
    <?php if($site_logo): ?>
    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="Logo" class="nav-logo-img">
    <?php endif; ?>
    <span class="nav-logo-text"><?php echo htmlspecialchars($site_name); ?></span>
  </div>
  <div class="nav-center">
    <div class="search-wrap">
      <input type="text" id="searchInput" placeholder="ابحث عن قناة أو VOD..." onkeyup="handleSearch()">
      <i class="fas fa-search si"></i>
    </div>
  </div>
  <div class="nav-actions">
    <!-- الأيقونات المطلوبة تم إدراجها بدون التأثير على المتبقي -->
    <a href="admin.php" class="nav-btn" title="لوحة التحكم / الحساب" style="background:var(--red); color:#fff; border-color:var(--red);"><i class="fas fa-user-shield"></i></a>
    <button class="nav-btn" title="الإشعارات" onclick="toggleNotifPanel()">
        <i class="fas fa-bell"></i>
        <span id="notifBadge" class="live-dot" style="display:none"></span>
    </button>
    <button class="nav-btn" title="المفضلة الخاصة بي" onclick="toggleFavPanel()"><i class="fas fa-heart"></i></button>
  </div>
</nav>

<!-- ════ HERO ════ -->
<div class="hero-banner" id="heroBanner">
  <div class="hero-bg"></div>
  <div class="hero-bg-grid"></div>
  <div class="hero-bg-scan"></div>
  <div class="hero-bg-glow"></div>
  <div class="hero-bg-noise"></div>
  <div class="hero-content fade-in">
    <div class="hero-badge"><span class="live-dot"></span>بث مباشر الآن</div>
    <h1 class="hero-title"><?php echo htmlspecialchars($welcome_title); ?></h1>
    <p class="hero-sub"><?php echo htmlspecialchars($welcome_subtitle); ?></p>
    <div class="hero-stats" id="heroStats">
      <div class="stat"><span class="stat-n" id="totalCh">—</span><span class="stat-l">قناة مباشرة</span></div>
      <div class="stat"><span class="stat-n" id="totalCat">—</span><span class="stat-l">قسم متنوع</span></div>
      <div class="stat"><span class="stat-n" id="totalSeries">—</span><span class="stat-l">VOD</span></div>
    </div>
  </div>
</div>

<!-- ════ MAIN ════ -->
<main class="main">
  <div class="section" id="catSection">
    <div class="section-header">
      <div class="section-title">
        <div class="section-title-icon"><i class="fas fa-th-large"></i></div>
        الأقسام
        <span class="section-count" id="catCount">...</span>
      </div>
    </div>
    <div class="categories-row" id="catGrid">
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
      <div class="skeleton" style="height:124px;border-radius:16px"></div>
    </div>
  </div>

  <div class="section hidden" id="chSection">
    <button class="back-btn" onclick="backToCategories()"><i class="fas fa-chevron-right"></i> الرئيسية</button>
    <div class="section-header">
      <div class="section-title">
        <div class="section-title-icon" id="chSecIcon"><i class="fas fa-satellite-dish"></i></div>
        <span id="chSectionTitle">القنوات</span>
        <span class="section-count" id="chCount">0</span>
      </div>
    </div>
    <div class="content-tabs" id="contentTabs" style="display:none">
      <button class="ctab on" id="tabChannels" onclick="switchTab('channels')"><i class="fas fa-tv"></i> القنوات</button>
      <button class="ctab" id="tabSeries" onclick="switchTab('series')"><i class="fas fa-film"></i> شاشتي</button>
    </div>
    <div class="channels-row" id="chGrid"></div>
    <div class="series-grid hidden" id="srGrid"></div>
  </div>

  <div class="section hidden" id="epSection">
    <button class="back-btn" id="epBackBtn" onclick="backToChannels()"><i class="fas fa-chevron-right"></i> <span id="epBackLabel">رجوع</span></button>
    <div class="section-header">
      <div class="section-title">
        <div class="section-title-icon" style="background:rgba(179,107,255,.15);border-color:rgba(179,107,255,.3)"><i class="fas fa-list" style="color:#B36BFF"></i></div>
        <span id="epSectionTitle">الحلقات</span>
        <span class="section-count" id="epCount">0</span>
      </div>
    </div>
    <div class="episodes-grid" id="epGrid"></div>
  </div>
</main>

<!-- ════ FOOTER ════ -->
<footer class="footer" id="siteFooter">
  <div class="footer-logo"><i class="fas fa-satellite-dish"></i> <?php echo htmlspecialchars($site_name); ?></div>
  <div class="footer-links">
    <a class="footer-link" href="#">البث المباشر</a>
    <a class="footer-link" href="#">الدعم الفني</a>
  </div>
  <?php if(!empty($settings['contact_phone'])): ?>
  <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:8px"><i class="fas fa-phone" style="color:#ff4d57;margin-left:6px"></i><?php echo htmlspecialchars($settings['contact_phone']); ?></p>
  <?php endif; ?>
  <?php if(!empty($settings['contact_email'])): ?>
  <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:8px"><i class="fas fa-envelope" style="color:#ff4d57;margin-left:6px"></i><a href="mailto:<?php echo htmlspecialchars($settings['contact_email']); ?>" style="color:inherit"><?php echo htmlspecialchars($settings['contact_email']); ?></a></p>
  <?php endif; ?>
  <div class="social-row">
    <?php if(!empty($settings['contact_facebook'])): ?>
    <a href="<?php echo htmlspecialchars($settings['contact_facebook']); ?>" target="_blank" class="soc-btn"><i class="fab fa-facebook-f"></i></a>
    <?php endif; ?>
    <?php if(!empty($settings['contact_whatsapp'])): ?>
    <a href="<?php echo htmlspecialchars($settings['contact_whatsapp']); ?>" target="_blank" class="soc-btn"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>
  </div>
  <p class="footer-copy" style="margin-top:22px"><?php echo htmlspecialchars($footer_text); ?></p>
</footer>

<div class="toasts" id="toastContainer"></div>

<!-- ════ TMDB INFO MODAL FOR FRONTEND ════ -->
<div class="tmdb-modal-overlay" id="tmdbInfoM" onclick="if(event.target===this) { this.style.display='none'; document.body.style.overflow=''; }">
  <div class="tmdb-modal-box">
    <div class="tmdb-modal-head">
      <div style="font-size:1.05rem;font-weight:800;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-info-circle" style="color:var(--red)"></i> تفاصيل العمل
      </div>
      <button class="tmdb-modal-close" onclick="document.getElementById('tmdbInfoM').style.display='none'; document.body.style.overflow='';"><i class="fas fa-times"></i></button>
    </div>
    <div class="tmdb-modal-body" id="tmdbInfoBody"></div>
  </div>
</div>

<!-- ════ PLAYER OVERLAY ════ -->
<div class="player-overlay" id="playerOverlay">
  <div class="pv-wrap" id="pvWrap">
    <video id="html5Player" playsinline preload="auto"></video>
    <div class="p-buffer" id="pBuffer"><div class="p-buffer-ring"></div></div>
    <div class="p-flash"><div class="p-flash-icon" id="pFlash"><i class="fas fa-play"></i></div></div>
  </div>
  <div class="p-top" id="pTop">
    <div class="p-top-info">
      <button class="p-ctrl" onclick="closePlayer()" style="margin-left:10px"><i class="fas fa-arrow-right"></i></button>
      <span class="p-live-badge" id="pBadge"><span id="pBadgeLabel">LIVE</span></span>
      <span class="p-fmt-tag" id="pFmtTag">HLS</span>
      <span class="p-channel-name" id="pChannelName">—</span>
    </div>
    <div class="p-ep-nav" id="pEpNav" style="display:none">
      <button class="p-ctrl" onclick="navEpisode(-1)" id="pPrevEp"><i class="fas fa-backward-step"></i></button>
      <span id="pEpLabel" style="font-weight:700;color:#fff;min-width:80px;text-align:center">الحلقة 1</span>
      <button class="p-ctrl" onclick="navEpisode(1)" id="pNextEp"><i class="fas fa-forward-step"></i></button>
    </div>
  </div>
  <div class="p-bottom" id="pBottom">
    <div class="p-progress-wrap" id="pProgress" onclick="seekTo(event)">
      <div class="p-progress-bar"><div class="p-progress-fill" id="pFill"><div class="p-progress-handle"></div></div></div>
    </div>
    <div class="p-controls">
      <button class="p-ctrl" onclick="toggleSubtitle()" id="subBtn"><i class="fas fa-closed-captioning"></i></button>
      <button class="p-ctrl" onclick="toggleEnhancements()" id="enhanceBtn" title="تحسين الصورة" style="flex-direction:column;gap:2px;font-size:1.1rem;width:auto;padding:0 8px">
        <i class="fas fa-tv"></i>
        <span id="enhLabel" style="font-size:.48rem;font-weight:800;letter-spacing:.5px;color:rgba(255,255,255,.55);line-height:1">قياسي</span>
      </button>
      <button class="p-ctrl" onclick="toggleEpPanel()" id="epPanelBtn" style="display:none"><i class="fas fa-list-ul"></i></button>
      <button class="p-ctrl" onclick="toggleM3UPanel()" id="m3uPanelBtn" style="display:none" title="قائمة M3U"><i class="fas fa-list-ol"></i></button>
      <button class="p-ctrl" onclick="skip(-10)"><i class="fas fa-rotate-left"></i></button>
      <button class="p-ctrl play-btn" id="playBtn" onclick="togglePlay()"><i class="fas fa-pause"></i></button>
      <button class="p-ctrl" onclick="skip(10)"><i class="fas fa-rotate-right"></i></button>
      <div class="p-time-display" id="pTime">00:00 / 00:00</div>
      <div class="p-vol-wrap">
        <i class="fas fa-volume-up p-ctrl" id="muteIcon" onclick="toggleMute()" style="font-size:1.1rem"></i>
        <div class="p-vol-slider" onclick="setVolume(event)"><div class="p-vol-fill" id="volFill"></div></div>
      </div>
      <button class="p-ctrl" onclick="toggleFullscreen()"><i class="fas fa-expand"></i></button>
    </div>
  </div>
</div>

<!-- Episodes Sidebar -->
<div class="ep-panel" id="epPanel">
  <div class="ep-panel-head">
    <div class="ep-panel-title"><i class="fas fa-film" style="color:#B36BFF"></i><span id="epPanelTitle">الحلقات</span></div>
    <button class="ep-panel-close" onclick="toggleEpPanel()"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="ep-panel-body" id="epPanelBody"></div>
</div>

<!-- M3U Playlist Sidebar -->
<div class="m3u-panel" id="m3uPanel">
  <div class="ep-panel-head">
    <div class="ep-panel-title"><i class="fas fa-list-ol" style="color:#ff4d57"></i><span id="m3uPanelHead">قائمة التشغيل</span></div>
    <button class="ep-panel-close" onclick="toggleM3UPanel()"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m3u-panel-body" id="m3uPanelBody"></div>
</div>

<!-- ════ المكونات الإضافية الجديدة المدمجة بالأنظمة الخاصة بك ════ -->

<!-- Favorites Sidebar (لجهازك الشخصي فقط) -->
<div class="fp-panel" id="favPanel">
  <div class="ep-panel-head">
    <div class="ep-panel-title"><i class="fas fa-heart" style="color:#ff4d57"></i><span id="favPanelHead">مفضلتي (الخاصة بي)</span></div>
    <button class="ep-panel-close" onclick="toggleFavPanel()"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m3u-panel-body" id="favPanelBody"></div>
</div>

<!-- Notifications Sidebar (المُحدّث بالأسماء والتفاصيل والفتح المباشر) -->
<div class="np-panel" id="notifPanel">
  <div class="ep-panel-head">
    <div class="ep-panel-title"><i class="fas fa-bell" style="color:var(--gold,#ffb020)"></i><span id="notifPanelHead">المحتوى المُضاف حديثاً</span></div>
    <button class="ep-panel-close" onclick="toggleNotifPanel()"><i class="fas fa-xmark"></i></button>
  </div>
  <div class="m3u-panel-body" id="notifPanelBody"></div>
</div>


<script>
/* ════ DEVTOOLS PROTECTION ════ */
(function(){
  'use strict';
  const overlay=document.getElementById('devtoolsOverlay'),lockIcon=document.getElementById('lockIcon');
  function show(){overlay.classList.add('show');lockIcon.classList.remove('shake');void lockIcon.offsetWidth;lockIcon.classList.add('shake')}
  document.addEventListener('keydown',function(e){
    if(e.keyCode===123||e.ctrlKey&&e.shiftKey&&(e.keyCode===73||e.keyCode===74||e.keyCode===67)||e.ctrlKey&&e.keyCode===85){e.preventDefault();e.stopPropagation();show();return false}
  },true);
  
  let open=false;
  setInterval(function(){
    // استثناء الهواتف (iOS و Android) من فحص أبعاد الشاشة لتجنب الإنذار الكاذب
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    let w = false;
    
    if(!isMobile) {
      w = (window.outerWidth - window.innerWidth > 160) || (window.outerHeight - window.innerHeight > 160);
    }
    
    if(w && !open){
      open=true; show();
    } else if(!w && open){
      open=false; overlay.classList.remove('show');
    }
  },800);
  
  document.addEventListener('contextmenu',function(e){e.preventDefault();return false});
  ['log','debug','warn','info','dir','table','trace','error'].forEach(function(m){try{console[m]=function(){}}catch(e){}});
})();

/* ════ APP STATE ════ */
const App={currentType:'',allChannels:[],allSeries:[],allEpisodes:[],currentCatId:0,currentCatName:'',currentSeriesId:0,currentSeriesName:'',license:<?php echo $license_expired?'true':'false';?>};

/* ════ نظام المفضلة الخاص بالهاتف المدمج بنجاح ════ */
const MyFavs = JSON.parse(localStorage.getItem('shashety_favs_v1') || '{"channels":[], "series":[]}');
let isFavPanelOpen = false;

function saveFavsLocally(){ localStorage.setItem('shashety_favs_v1', JSON.stringify(MyFavs)); }
function toggleMyFav(id, name, type, icon_url, streamUrl='', subtitleUrl='') {
    let list = MyFavs[type];
    // تم إصلاح المطابقة عن طريق التحويل إلى نصوص للرقم من واجهة السيرفر
    let existIdx = list.findIndex(x => String(x.id) === String(id)); 
    if(existIdx >= 0) { list.splice(existIdx, 1); toast('تم المسح من قائمة المفضلة','fas fa-trash'); }
    else { list.push({id, name, icon_url, stream_url:streamUrl, subtitle_url:subtitleUrl, t_stamp:Date.now()}); toast('تم الحفظ إلى هاتفك بنجاح','fas fa-heart'); }
    saveFavsLocally();
    buildFavPanel(); // تحديث البانيل
    
    // التحديث البصري للقلوب المعروضة إن وجدت 
    const chCardsRendered = App.allChannels.length && type === 'channels';
    if(chCardsRendered) { renderChannels(App.allChannels); return; }
    const srCardsRendered = App.allSeries.length && type === 'series';
    if(srCardsRendered) { renderSeries(App.allSeries); return; }
}

function buildFavPanel() {
    const b = document.getElementById('favPanelBody'); b.innerHTML = '';
    const merged = [];
    MyFavs.channels.forEach(c=> merged.push({...c, favType: 'channels'}));
    MyFavs.series.forEach(s=> merged.push({...s, favType: 'series'}));
    merged.sort((a,b) => (b.t_stamp||0) - (a.t_stamp||0)); // الأحدث فالأحدث

    if(merged.length === 0) {
        b.innerHTML = '<p style="color:var(--text-muted);text-align:center;margin-top:20px"><i class="fas fa-heart-broken" style="display:block;margin-bottom:10px;font-size:2rem;opacity:.5"></i>قائمة المفضلة لديك فارغة</p>';
        return;
    }

    merged.forEach(item => {
        const d = document.createElement('div');
        d.className = 'm3u-item'; // نستعير تصميم العنصر المتميز
        const iconSrc = item.icon_url ? `<img class="m3u-item-logo" src="${esc(item.icon_url)}" loading="lazy">` : `<div class="m3u-item-logo"><i class="fas ${item.favType==='series'?'fa-film':'fa-tv'}"></i></div>`;
        const actionBtn = `<div onclick="event.stopPropagation(); toggleMyFav('${item.id}', '','${item.favType}')" style="background:rgba(229,9,20,.15);border-radius:6px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:#ff4d57;font-size:.9rem; cursor:pointer;"><i class="fas fa-trash"></i></div>`;
        d.innerHTML = `${iconSrc}<div style="flex:1"><div class="m3u-item-name">${esc(item.name)}</div><div class="m3u-item-group">${item.favType==='channels'?'بث مباشر':'مسلسلات وأفلام'}</div></div>${actionBtn}`;
        d.onclick = () => {
             if(window.innerWidth <= 768) toggleFavPanel();
             if(item.favType === 'channels') { openPlayerChannel({id: item.id, name: item.name, stream_url: item.stream_url, subtitle_url: item.subtitle_url}); }
             else { openSeriesEpisodes(item.id, item.name); }
        };
        b.appendChild(d);
    });
}
function toggleFavPanel(){ isFavPanelOpen = !isFavPanelOpen; document.getElementById('favPanel').classList.toggle('open', isFavPanelOpen); buildFavPanel(); }


/* ════ نظام المزامنة الذكي والإشعارات بالصور وأسماء المحتوى المُحدث (v4) ════ */
let isNotifPanelOpen = false;
const PendingNotifsKey = 'shashety_pending_notifs_v4'; 
let MyNotifsQueue = JSON.parse(localStorage.getItem(PendingNotifsKey) || '[]');

function updateNotifBadge() {
   const b = document.getElementById('notifBadge');
   if(b) b.style.display = MyNotifsQueue.length > 0 ? 'block' : 'none';
}

// مُحرّك الاستكشاف الذي يتمحور حول التقاط الأعمال الجديدة أو التقاط أحدث عمل إذا كان المستخدم جديد كلياً
async function syncAdvancedNotifications(categories) {
   const SyncStateKey = 'shashety_sync_state_v4'; 
   let isFirstTime = !localStorage.getItem(SyncStateKey); 
   let state = JSON.parse(localStorage.getItem(SyncStateKey) || '{}');
   let newlyDiscovered = [];

   // متغيرات للمستخدم الجديد لتحديد ما هو أعلى شيء تم رفعه كتعريف له بأن الموقع محدّث
   let allFetchedSeries = [];
   let allFetchedChannels = [];

   for (const cat of categories) {
       let catId = cat.id;
       if (!state[catId]) state[catId] = { srSeen: [], chSeen: [], srCount: 0, chCount: 0 };

       let stored = state[catId];
       let curSr = parseInt(cat.series_count || 0);
       let curCh = parseInt(cat.channel_count || 0);

       if (curSr > stored.srCount || isFirstTime) {
           try {
               let r = await fetch('api.php?action=series&category_id='+catId);
               let d = await r.json();
               let seriesArr = d.series || [];
               for (const s of seriesArr) {
                   if (isFirstTime) allFetchedSeries.push({...s, catName: cat.name}); 
                   
                   if (!stored.srSeen.includes(s.id)) {
                       if(!isFirstTime) newlyDiscovered.push({ id: s.id, type: 'series', name: s.name, img: (s.poster_url||''), catName: cat.name });
                       stored.srSeen.push(s.id);
                   }
               }
               stored.srCount = curSr;
           } catch(e) {}
       }
       if (curCh > stored.chCount || isFirstTime) {
           try {
               let r = await fetch('api.php?action=channels&category_id='+catId);
               let d = await r.json();
               let chArr = d.channels || [];
               for (const c of chArr) {
                   if (isFirstTime) allFetchedChannels.push({...c, catName: cat.name});
                   
                   if (!stored.chSeen.includes(c.id)) {
                       if(!isFirstTime) newlyDiscovered.push({ id: c.id, type: 'channel', name: c.name, img: (c.logo_url||''), catName: cat.name, streamUrl: c.stream_url, subUrl: c.subtitle_url});
                       stored.chSeen.push(c.id);
                   }
               }
               stored.chCount = curCh;
           } catch(e) {}
       }
   }
   
   /* برمجة ذكية: لو كانت المرة الأولى للهاتف أو المتصفح بالدخول، 
   نقوم بجلب أحدث الأفلام والمسلسلات أو القنوات في السيرفر لنريه في الإشعارات أنه يوجد شيء نزل حديثاً */
   if (isFirstTime) {
       if(allFetchedSeries.length > 0) {
           allFetchedSeries.sort((a,b) => parseInt(b.id||0) - parseInt(a.id||0)); 
           let s = allFetchedSeries[0];
           newlyDiscovered.push({ id: s.id, type: 'series', name: s.name, img: (s.poster_url||''), catName: s.catName });
       }
       if(allFetchedChannels.length > 0) {
           allFetchedChannels.sort((a,b) => parseInt(b.id||0) - parseInt(a.id||0)); 
           let c = allFetchedChannels[0];
           newlyDiscovered.push({ id: c.id, type: 'channel', name: c.name, img: (c.logo_url||''), catName: c.catName, streamUrl: c.stream_url, subUrl: c.subtitle_url});
       }
   }

   localStorage.setItem(SyncStateKey, JSON.stringify(state));

   if(newlyDiscovered.length > 0) {
       let finalQueue = [];
       newlyDiscovered.forEach(nd => {
           if(!MyNotifsQueue.some(x => String(x.id) === String(nd.id))) finalQueue.push(nd);
       });
       MyNotifsQueue = [...finalQueue, ...MyNotifsQueue];
       localStorage.setItem(PendingNotifsKey, JSON.stringify(MyNotifsQueue));
   }
   updateNotifBadge();
   if(isNotifPanelOpen) buildNotifPanel();
}

// الضغط من داخل الاشعار للانتقال للفيلم المكتشف أو القناة
function openFromNotif(id, type, name, sUrl='', subUrl='') {
     if(window.innerWidth <= 768) toggleNotifPanel();
     if(type === 'channel') {
         openPlayerChannel({ id: id, name: name, stream_url: sUrl, subtitle_url: subUrl });
     } else {
         openSeriesEpisodes(id, name);
     }
}

// مسح إشعار من لوحة الاشعارات الشخصية (تم إصلاح الخلل بتحويل أنواع البيانات بدقة صارمة)
function removeLocalNotif(uniqueObjId) {
    MyNotifsQueue = MyNotifsQueue.filter(n => String(n.id) !== String(uniqueObjId));
    localStorage.setItem(PendingNotifsKey, JSON.stringify(MyNotifsQueue));
    buildNotifPanel();
    updateNotifBadge();
}

function buildNotifPanel() {
    const b = document.getElementById('notifPanelBody'); b.innerHTML = '';
    if(MyNotifsQueue.length === 0) {
        b.innerHTML = '<p style="color:var(--text-muted);text-align:center;margin-top:20px"><i class="fas fa-bell-slash" style="display:block;margin-bottom:10px;font-size:2rem;opacity:.5"></i>أنت على إطلاع بكل جديد، لا توجد إشعارات</p>';
        return;
    }

    MyNotifsQueue.forEach(item => {
        const d = document.createElement('div');
        d.className = 'm3u-item'; // نستعمل كلاس التصميم الأنيق للبانيل
        d.style.cssText = 'background:#1a1a1a; padding:12px; gap:12px; border:1px solid rgba(229,9,20,.15); align-items:flex-start; margin-bottom:8px; border-radius:10px; transition:var(--transition); position:relative;';
        
        let posterHtml = item.img ? `<img src="${esc(item.img)}" style="width:50px; height:70px; object-fit:cover; border-radius:6px; flex-shrink:0; background:#222">` 
                                  : `<div style="width:50px; height:70px; display:flex; align-items:center; justify-content:center; background:#222; border-radius:6px; flex-shrink:0; font-size:1.5rem; color:#666"><i class="fas ${item.type==='channel'?'fa-tv':'fa-film'}"></i></div>`;
                                  
        let actionParams = `openFromNotif('${item.id}','${item.type}','${escA(item.name)}','${escA(item.streamUrl||'')}','${escA(item.subUrl||'')}')`;

        d.innerHTML = `
            ${posterHtml}
            <div style="flex:1; min-width:0; margin-top:-2px;">
                <div style="font-weight:bold; font-size:.9rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px;">${esc(item.name)}</div>
                <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:10px;">متوفر حديثاً في <span style="color:#B36BFF">${esc(item.catName||'النظام')}</span></div>
                <button onclick="event.stopPropagation(); ${actionParams}" style="background:var(--red); color:#fff; border:none; padding:4px 10px; border-radius:6px; font-size:.75rem; font-weight:700; cursor:pointer;"><i class="fas fa-play" style="font-size:.65rem; margin-left:4px"></i> تشغيل / انتقال</button>
            </div>
            <button onclick="event.stopPropagation(); removeLocalNotif('${item.id}')" style="position:absolute; top:8px; left:8px; background:rgba(255,255,255,.07); color:#ccc; border:none; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fas fa-times" style="font-size:.7rem;"></i></button>
        `;
        b.appendChild(d);
    });
}
function toggleNotifPanel(){ isNotifPanelOpen = !isNotifPanelOpen; document.getElementById('notifPanel').classList.toggle('open', isNotifPanelOpen); buildNotifPanel(); }



/* ════ FORMAT DETECTION ════ */
function detectFmt(url){
  const clean=(url||'').split('?')[0].split('#')[0].toLowerCase().trim();
  if(clean.endsWith('.m3u8')||clean.endsWith('.m3u'))return 'hls';
  if(clean.endsWith('.mpd'))return 'dash';
  if(clean.endsWith('.flv'))return 'flv';
  if(clean.endsWith('.mp4')||clean.endsWith('.m4v'))return 'mp4';
  if(clean.endsWith('.mkv'))return 'mkv';
  if(clean.endsWith('.avi'))return 'avi';
  if(clean.endsWith('.ts')||clean.endsWith('.mts')||clean.endsWith('.m2ts'))return 'ts';
  if(clean.endsWith('.webm'))return 'webm';
  if(clean.endsWith('.ogg')||clean.endsWith('.ogv'))return 'ogg';
  return 'hls';
}
function fmtLabel(url){
  const f=detectFmt(url);
  const map={hls:'HLS',dash:'DASH',flv:'FLV',mp4:'MP4',mkv:'MKV',avi:'AVI',ts:'TS',webm:'WebM',ogg:'OGG'};
  return map[f]||'HLS';
}
function isLiveFormat(url){
  const f=detectFmt(url);
  return f==='hls'||f==='dash'||f==='flv'||f==='ts';
}

/* ════ NAVBAR SCROLL ════ */
window.addEventListener('scroll',()=>document.getElementById('navbar').classList.toggle('scrolled',window.scrollY>10),{passive:true});

/* ════ CATEGORIES ════ */
async function loadCategories(){
  if(App.license){showLicenseWall();return}
  try{
    updateNotifBadge(); // فحص الأيقونة فور تحميل الصفحة من التخزين المؤقت السابق

    const[catRes,statsRes]=await Promise.all([fetch('api.php?action=all_content'),fetch('api.php?action=stats')]);
    const catData=await catRes.json(),statsData=await statsRes.json();
    const cats=catData.categories||[];
    
    // إحصائيات دقيقة تحسب من الأقسام
    let totalCh = parseInt(statsData.channels) || 0;
    let totalSr = parseInt(statsData.series) || 0;
    if (totalCh === 0 && totalSr === 0 && cats.length > 0) {
        cats.forEach(c => {
            totalCh += parseInt(c.channel_count || 0);
            totalSr += parseInt(c.series_count || 0);
        });
    }

    document.getElementById('catCount').textContent=cats.length;
    document.getElementById('totalCat').textContent=cats.length;
    document.getElementById('totalCh').textContent=totalCh;
    document.getElementById('totalSeries').textContent=totalSr;

    /* إقلاع محرك البحث السريع للأعمال والأفلام المضافة للإشعار بها بصريا بالصور 📸 🎬  */
    syncAdvancedNotifications(cats);
    
    const grid=document.getElementById('catGrid');
    grid.innerHTML='';
    cats.forEach(c=>{
      const card=document.createElement('div');
      card.className='cat-card';
      const sb=parseInt(c.series_count)>0?`<div class="cat-series-badge"><i class="fas fa-film"></i> ${c.series_count} VOD</div>`:'';
      card.innerHTML=`<div class="cat-icon-wrap"><i class="${c.icon||'fas fa-tv'}"></i></div><div class="cat-name">${esc(c.name)}</div><div class="cat-count">${c.channel_count} قناة</div>${sb}`;
      card.setAttribute('tabindex','0'); card.onkeydown=function(e){if(e.keyCode===13||e.keyCode===32){addRipple(card,e);openCategory(c.id,c.name,parseInt(c.channel_count),parseInt(c.series_count||0));}}; card.onclick=function(e){addRipple(card,e);openCategory(c.id,c.name,parseInt(c.channel_count),parseInt(c.series_count||0))};
      grid.appendChild(card);
    });
  }catch(e){document.getElementById('catGrid').innerHTML='<p style="color:#ff4d57;padding:20px;grid-column:1/-1">تعذّر تحميل الأقسام.</p>'}
}

async function openCategory(catId,catName,chCount,srCount){
  App.currentCatId=catId;App.currentCatName=catName;
  document.getElementById('catSection').classList.add('hidden');
  document.getElementById('heroBanner').classList.add('hidden');
  document.getElementById('epSection').classList.add('hidden');
  const sec=document.getElementById('chSection');sec.classList.remove('hidden');
  document.getElementById('chSectionTitle').textContent=catName;
  const tabs=document.getElementById('contentTabs');
  const hasBoth=chCount>0&&srCount>0;
  tabs.style.display=hasBoth?'flex':'none';
  if(srCount>0&&chCount===0){
    document.getElementById('chGrid').classList.add('hidden');document.getElementById('srGrid').classList.remove('hidden');
    document.getElementById('chSecIcon').innerHTML='<i class="fas fa-film"></i>';
    await loadChannelsAndSeries(catId,'series');
  }else{
    document.getElementById('chGrid').classList.remove('hidden');document.getElementById('srGrid').classList.add('hidden');
    document.getElementById('tabChannels')&&(document.getElementById('tabChannels').classList.add('on'));
    document.getElementById('tabSeries')&&(document.getElementById('tabSeries').classList.remove('on'));
    document.getElementById('chSecIcon').innerHTML='<i class="fas fa-satellite-dish"></i>';
    await loadChannelsAndSeries(catId,'channels');
  }
  // TV Focus: auto-focus أول عنصر بعد التحميل
  setTimeout(function(){
    var first = document.querySelector('#chSection .ch-card, #chSection .sr-card');
    if(first){ first.focus({preventScroll:false}); if(typeof setMainFocus==='function') setMainFocus(first); }
  }, 400);
}

async function loadChannelsAndSeries(catId,which){
  const chGrid=document.getElementById('chGrid'),srGrid=document.getElementById('srGrid');
  if(which==='channels'){
    chGrid.innerHTML='<div class="skeleton" style="height:164px;border-radius:16px"></div>'.repeat(4);
    try{const res=await fetch(`api.php?action=channels&category_id=${catId}`),data=await res.json();App.allChannels=data.channels||[];document.getElementById('chCount').textContent=App.allChannels.length;renderChannels(App.allChannels)}
    catch(e){chGrid.innerHTML='<p style="color:#ff4d57;padding:20px;grid-column:1/-1">تعذّر تحميل القنوات.</p>'}
  }else{
    srGrid.innerHTML='<div class="skeleton" style="height:200px;border-radius:16px"></div>'.repeat(3);
    try{const res=await fetch(`api.php?action=series&category_id=${catId}`),data=await res.json();App.allSeries=data.series||[];document.getElementById('chCount').textContent=App.allSeries.length;renderSeries(App.allSeries)}
    catch(e){srGrid.innerHTML='<p style="color:#ff4d57;padding:20px;grid-column:1/-1">تعذّر تحميل شاشتي.</p>'}
  }
}

function switchTab(tab){
  document.getElementById('tabChannels').classList.toggle('on',tab==='channels');
  document.getElementById('tabSeries').classList.toggle('on',tab==='series');
  const cg=document.getElementById('chGrid'),sg=document.getElementById('srGrid');
  if(tab==='channels'){cg.classList.remove('hidden');sg.classList.add('hidden');document.getElementById('chSecIcon').innerHTML='<i class="fas fa-satellite-dish"></i>';if(!App.allChannels.length)loadChannelsAndSeries(App.currentCatId,'channels');else{document.getElementById('chCount').textContent=App.allChannels.length;renderChannels(App.allChannels)}}
  else{sg.classList.remove('hidden');cg.classList.add('hidden');document.getElementById('chSecIcon').innerHTML='<i class="fas fa-film"></i>';if(!App.allSeries.length)loadChannelsAndSeries(App.currentCatId,'series');else{document.getElementById('chCount').textContent=App.allSeries.length;renderSeries(App.allSeries)}}
}

/* ════ TMDB CLIENT SEARCH LOGIC ════ */
function getTmdbKeyClient(){
    const serverKey = "<?php echo htmlspecialchars($settings['tmdb_api_key'] ?? ''); ?>";
    if (serverKey && serverKey.trim() !== '') {
        return serverKey.trim();
    }
    let localKey = localStorage.getItem('tmdb_api_key');
    if(localKey && localKey.trim() !== '') {
        return localKey.trim();
    }
    return null;
}

async function showTmdbInfoClient(query, defaultType) {
    const key = getTmdbKeyClient();
    const modal = document.getElementById('tmdbInfoM');
    const body = document.getElementById('tmdbInfoBody');
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    if (!key) { 
        body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-info-circle fa-2x" style="margin-bottom:10px;color:var(--red)"></i><br>عذراً، ميزة جلب التفاصيل الإضافية غير مفعلة حالياً من قِبل إدارة الموقع.</div>';
        return; 
    }

    body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><div class="pspin" style="margin:0 auto 12px;border-top-color:var(--red)"></div>جاري جلب التفاصيل...</div>';

    try {
        const cleanQuery = query.replace(/(1080p|720p|4k|fhd|hd|ar|en)/gi, '').trim();
        const searchRes = await fetch(`https://api.themoviedb.org/3/search/multi?api_key=${key}&query=${encodeURIComponent(cleanQuery)}&language=ar`);
        
        if (searchRes.status === 401) {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:#ff4d57"><i class="fas fa-key fa-2x" style="margin-bottom:10px"></i><br>مفتاح API الخاص بالموقع غير صحيح، يرجى مراجعة الإدارة.</div>';
            return;
        }

        const searchData = await searchRes.json();

        if (!searchData.results || searchData.results.length === 0) {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-search fa-2x" style="margin-bottom:10px"></i><br>لم يتم العثور على معلومات مفصلة لهذا العمل في قاعدة البيانات العالمية.</div>';
            return;
        }

        const item = searchData.results.find(i => i.media_type === 'movie' || i.media_type === 'tv') || searchData.results[0];
        const type = item.media_type || defaultType;
        const id = item.id;

        let res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${key}&language=ar`);
        let data = await res.json();

        if (!data.overview) {
            let resEn = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${key}&language=en-US`);
            let dataEn = await resEn.json();
            data.overview = dataEn.overview;
        }

        const title = data.title || data.name || cleanQuery;
        const poster = data.poster_path ? `https://image.tmdb.org/t/p/w300${data.poster_path}` : '';
        const year = (data.release_date || data.first_air_date || '').substring(0, 4);
        const rating = data.vote_average ? data.vote_average.toFixed(1) : '—';
        const genres = (data.genres || []).map(g => `<span class="tmdb-genre-badge">${g.name}</span>`).join(' ');
        const overview = data.overview || 'لا توجد قصة متوفرة لهذا العمل في الوقت الحالي.';
        const status = data.status || '—';
        const runTime = data.runtime ? `${data.runtime} دقيقة` : (data.episode_run_time && data.episode_run_time[0] ? `${data.episode_run_time[0]} دقيقة للحلقة` : '');

        body.innerHTML = `
            <div class="tmdb-info-wrap">
                ${poster ? `<img src="${poster}" class="tmdb-info-poster">` : `<div class="tmdb-info-poster" style="display:flex;align-items:center;justify-content:center;height:195px"><i class="fas fa-film fa-2x"></i></div>`}
                <div class="tmdb-info-details">
                    <div class="tmdb-info-title">${title} ${year ? `(${year})` : ''}</div>
                    <div class="tmdb-info-meta">
                        <span style="color:var(--gold);font-weight:bold;"><i class="fas fa-star"></i> ${rating}</span>
                        ${runTime ? `<span><i class="fas fa-clock"></i> ${runTime}</span>` : ''}
                        <span style="color:var(--text-dim)">الحالة: ${status}</span>
                    </div>
                    <div style="margin-bottom:14px">${genres}</div>
                    <div style="font-size:0.8rem;font-weight:bold;margin-bottom:6px;color:var(--text-dim)">القصة:</div>
                    <div class="tmdb-info-overview">${overview}</div>
                </div>
            </div>
        `;
    } catch (e) {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#ff4d57"><i class="fas fa-exclamation-triangle fa-2x" style="margin-bottom:10px"></i><br>حدث خطأ أثناء الاتصال بالخوادم. يرجى المحاولة لاحقاً.</div>';
    }
}

/* ════ CHANNELS ════ */
function renderChannels(chs){
  const grid=document.getElementById('chGrid');grid.innerHTML='';
  if(!chs.length){grid.innerHTML='<p style="color:var(--text-muted);padding:20px;grid-column:1/-1">لا توجد قنوات.</p>';return}
  chs.forEach(ch=>{
    /* تطبيق تفاعل المفضلة للزر داخل كارت القنوات  */
    const isFavLocally = MyFavs.channels.some(f => String(f.id) === String(ch.id));

    const fmt=fmtLabel(ch.stream_url||'');
    const isLive=isLiveFormat(ch.stream_url||'');
    const badge=isLive
      ?`<span class="ch-live-badge">LIVE</span>`
      :`<span class="ch-live-badge" style="background:linear-gradient(135deg,#1a5276,#2980b9)">${fmt}</span>`;
    const card=document.createElement('div');card.className='ch-card'; card.setAttribute('tabindex','0');
    const hasPoster=ch.logo_url&&ch.logo_url.trim()!=='';
    const thumbClass=hasPoster?'ch-thumb poster-mode':'ch-thumb';
    const logo=hasPoster
      ?`<img src="${esc(ch.logo_url)}" alt="${esc(ch.name)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><i class="fas fa-tv ch-icon" style="display:none"></i>`
      :`<i class="fas fa-${ch.logo_icon||'tv'} ch-icon"></i>`;
      
    card.innerHTML=`
      <div class="${thumbClass}">${logo}
        <div class="ch-thumb-overlay">
          <div class="ch-play-btn"><i class="fas fa-play"></i></div>
        </div>
        ${badge}<span class="ch-fmt-badge">${fmt}</span>
      </div>
      <div class="ch-info">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div style="min-width:0; flex:1;">
            <div class="ch-name">${esc(ch.name)}</div>
            <div class="ch-meta"><i class="fas fa-circle" style="font-size:.4rem;color:#ff4d57"></i><span>${isLive?'مباشر الآن':fmt}</span></div>
          </div>
          <div style="display:flex;">
             <button type="button" class="info-action-btn ${isFavLocally?'active-fav':''}" onclick="event.stopPropagation(); toggleMyFav('${ch.id}', '${escA(ch.name)}', 'channels', '${escA(ch.logo_url||'')}', '${escA(ch.stream_url||'')}', '${escA(ch.subtitle_url||'')}')" title="أضف למع المفضلة لديك"><i class="fas fa-heart"></i></button>
             <button type="button" class="info-action-btn" onclick="event.stopPropagation(); showTmdbInfoClient('${escA(ch.name)}', 'movie')" title="معلومات تفصيلية"><i class="fas fa-info-circle"></i></button>
          </div>
        </div>
      </div>
    `;
    card.onclick=()=>openPlayerChannel(ch);
    card.onkeydown=function(e){if(e.keyCode===13||e.keyCode===32){e.preventDefault();openPlayerChannel(ch);}};
    card.onfocus=function(){if(typeof setMainFocus==='function')setMainFocus(card);};
    grid.appendChild(card);
  });
}

function renderSeries(seriesList){
  const grid=document.getElementById('srGrid');grid.innerHTML='';
  if(!seriesList.length){grid.innerHTML='<p style="color:var(--text-muted);padding:20px;grid-column:1/-1">لا توجد VODات.</p>';return}
  seriesList.forEach(s=>{
    /* تفاعل قلب الأفلام / VOD */
    const isFavLocally = MyFavs.series.some(f => String(f.id) === String(s.id));

    const card=document.createElement('div');card.className='sr-card'; card.setAttribute('tabindex','0');
    const poster=s.poster_url?`<img src="${esc(s.poster_url)}" alt="${esc(s.name)}" loading="lazy" onerror="this.parentElement.querySelector('.sr-icon').style.display='flex';this.style.display='none'">`:''
    
    card.innerHTML=`
      <div class="sr-poster">${poster}
        <i class="fas fa-film sr-icon" style="${s.poster_url?'display:none':''}"></i>
        <div class="sr-poster-overlay"><div class="sr-enter-btn"><i class="fas fa-list"></i></div></div>
        <span class="sr-ep-count">${s.ep_count||0} حلقة</span>
      </div>
      <div class="sr-info">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div style="min-width:0; flex:1;">
            <div class="sr-name">${esc(s.name)}</div>
            <div class="sr-meta"><i class="fas fa-circle" style="font-size:.4rem;color:#B36BFF"></i><span>${esc(s.cat_name||'')}</span></div>
          </div>
          <div style="display:flex;">
             <button type="button" class="info-action-btn ${isFavLocally?'active-fav':''}" onclick="event.stopPropagation(); toggleMyFav('${s.id}', '${escA(s.name)}', 'series', '${escA(s.poster_url||'')}')" title="أضف למع المفضلة لديك"><i class="fas fa-heart"></i></button>
             <button type="button" class="info-action-btn" onclick="event.stopPropagation(); showTmdbInfoClient('${escA(s.name)}', 'tv')" title="معلومات تفصيلية"><i class="fas fa-info-circle"></i></button>
          </div>
        </div>
      </div>
    `;
    card.onclick=()=>openSeriesEpisodes(s.id,s.name);
    card.onkeydown=function(e){if(e.keyCode===13||e.keyCode===32){e.preventDefault();openSeriesEpisodes(s.id,s.name);}};
    card.onfocus=function(){if(typeof setMainFocus==='function')setMainFocus(card);};
    grid.appendChild(card);
  });
}

async function openSeriesEpisodes(seriesId,seriesName){
  App.currentSeriesId=seriesId;App.currentSeriesName=seriesName;
  document.getElementById('chSection').classList.add('hidden');document.getElementById('catSection').classList.add('hidden');document.getElementById('heroBanner').classList.add('hidden');
  const sec=document.getElementById('epSection');sec.classList.remove('hidden');
  document.getElementById('epSectionTitle').textContent=seriesName;
  document.getElementById('epBackLabel').textContent=App.currentCatName||'رجوع';
  const grid=document.getElementById('epGrid');
  grid.innerHTML='<div class="skeleton" style="height:72px;border-radius:10px"></div>'.repeat(3);
  try{const res=await fetch(`api.php?action=episodes&series_id=${seriesId}`),data=await res.json();App.allEpisodes=data.episodes||[];document.getElementById('epCount').textContent=App.allEpisodes.length;renderEpisodes(App.allEpisodes);setTimeout(function(){var first=document.querySelector('#epGrid .ep-card');if(first){first.focus({preventScroll:false});if(typeof setMainFocus==='function')setMainFocus(first);}},400);fetch(`api.php?action=increment_view&id=${seriesId}&type=series`).catch(()=>{})}
  catch(e){grid.innerHTML='<p style="color:#ff4d57;padding:20px;grid-column:1/-1">تعذّر تحميل الحلقات.</p>'}
}

/* ════ EPISODES RENDERER ════ */
function renderEpisodes(eps){
  const grid=document.getElementById('epGrid');
  grid.innerHTML='';

  if(!eps.length){
      grid.innerHTML='<p style="color:var(--text-muted);padding:20px;grid-column:1/-1">لا توجد حلقات بعد.</p>';
      return;
  }

  eps.forEach((ep,idx)=>{
    // عدم استخدام الفيديو المخفي لسحب الداتا لضمان الأمان والسرعة.
    const hasImg = ep.image_url && ep.image_url.trim() !== '';
    let thumbContent;

    if(hasImg) {
        thumbContent = `<img src="${esc(ep.image_url)}" class="ep-thumb-video" loading="lazy">`;
    } else {
        thumbContent = `
          <div style="width:100%;height:100%;background:linear-gradient(45deg,#111,#222);display:flex;align-items:center;justify-content:center;position:absolute;inset:0;">
              <i class="fas fa-video" style="font-size:3rem;color:rgba(255,255,255,0.05)"></i>
          </div>
        `;
    }

    const dateAdded = ep.added || ep.created_at || ep.date || '----/--/--';

    const card=document.createElement('div');
    card.className='ep-card'; card.setAttribute('tabindex','0');
    card.innerHTML=`
      <div class="ep-thumb-area" style="position:relative; background: #1a1a1a;">
          ${thumbContent}
          <i class="fas fa-play-circle ep-thumb-icon" style="z-index:2"></i>
          <div class="ep-num-badge">حلقة ${ep.episode_number}</div>
      </div>
      <div class="ep-info-box">
          <div class="ep-date-text">
            <i class="far fa-calendar-alt"></i> ${dateAdded}
          </div>
      </div>
    `;

    card.onclick=()=>openPlayerEpisode(idx);
    card.onkeydown=function(e){if(e.keyCode===13||e.keyCode===32){e.preventDefault();openPlayerEpisode(idx);}};
    card.onfocus=function(){if(typeof setMainFocus==='function')setMainFocus(card);};
    grid.appendChild(card);
  });
}

function backToCategories(){
  App.allChannels=[];App.allSeries=[];
  if(_savedPlayer.active){destroyPlayer();_savedPlayer.active=false;_savedPlayer.url='';}
  document.getElementById('chSection').classList.add('hidden');
  document.getElementById('epSection').classList.add('hidden');
  document.getElementById('catSection').classList.remove('hidden');
  document.getElementById('heroBanner').classList.remove('hidden');
  document.getElementById('searchInput').value='';
  currentMainFocus = null;
  setTimeout(function(){
    var first = document.querySelector('#catGrid .cat-card');
    if(first) setMainFocus(first);
  }, 200);
}
function backToChannels(){App.allEpisodes=[];document.getElementById('epSection').classList.add('hidden');document.getElementById('chSection').classList.remove('hidden');document.getElementById('heroBanner').classList.add('hidden')}

function handleSearch(){
  const q=document.getElementById('searchInput').value.toLowerCase().trim();
  if(App.allChannels.length){renderChannels(q?App.allChannels.filter(c=>c.name.toLowerCase().includes(q)):App.allChannels);return}
  if(App.allSeries.length){renderSeries(q?App.allSeries.filter(s=>s.name.toLowerCase().includes(q)):App.allSeries);return}
  if(q.length<2)return;
  fetch('api.php?action=search&q='+encodeURIComponent(q)).then(r=>r.json()).then(data=>{
    const hasC=data.channels&&data.channels.length,hasS=data.series&&data.series.length;
    if(!hasC&&!hasS){toast('لا توجد نتائج','fas fa-search');return}
    document.getElementById('catSection').classList.add('hidden');document.getElementById('heroBanner').classList.add('hidden');document.getElementById('epSection').classList.add('hidden');
    document.getElementById('chSection').classList.remove('hidden');document.getElementById('chSectionTitle').textContent='نتائج البحث';
    const tabs=document.getElementById('contentTabs');
    if(hasC&&hasS){tabs.style.display='flex';App.allChannels=data.channels;App.allSeries=data.series;document.getElementById('chGrid').classList.remove('hidden');document.getElementById('srGrid').classList.add('hidden');document.getElementById('tabChannels').classList.add('on');document.getElementById('tabSeries').classList.remove('on');document.getElementById('chCount').textContent=data.channels.length;renderChannels(data.channels)}
    else if(hasC){tabs.style.display='none';App.allChannels=data.channels;document.getElementById('chGrid').classList.remove('hidden');document.getElementById('srGrid').classList.add('hidden');document.getElementById('chCount').textContent=data.channels.length;renderChannels(data.channels)}
    else{tabs.style.display='none';App.allSeries=data.series;document.getElementById('srGrid').classList.remove('hidden');document.getElementById('chGrid').classList.add('hidden');document.getElementById('chCount').textContent=data.series.length;renderSeries(data.series)}
  });
}

/* ════ PLAYER STATE ════ */
const PL={hls:null,dash:null,flv:null,vol:1,muted:false,idle:null,subtitleOn:false,enhanceOn:false,epPanelOpen:false,m3uPanelOpen:false,currentFocusIndex:-1,focusableElements:[],m3uEntries:[],m3uIdx:-1};

/* ════ M3U PARSER ════ */
async function parseM3U(urlOrText){
  let text=urlOrText;
  if(urlOrText.startsWith('http')||urlOrText.startsWith('//')){
    try{const r=await fetch(urlOrText);text=await r.text()}
    catch(e){toast('تعذّر تحميل قائمة M3U','fas fa-triangle-exclamation');return[]}
  }
  const lines=text.split('\n').map(l=>l.trim()).filter(Boolean);
  const entries=[];let cur={};
  for(const line of lines){
    if(line.startsWith('#EXTM3U'))continue;
    if(line.startsWith('#EXTINF')){
      cur={};
      const comma=line.lastIndexOf(',');
      cur.name=comma>=0?line.slice(comma+1).trim():'بدون اسم';
      const logoM=line.match(/tvg-logo="([^"]+)"/i);
      cur.logo=logoM?logoM[1]:'';
      const grpM=line.match(/group-title="([^"]+)"/i);
      cur.group=grpM?grpM[1]:'';
    }else if(!line.startsWith('#')&&(line.startsWith('http')||line.startsWith('/'))){
      cur.url=line;
      entries.push({...cur});
      cur={};
    }
  }
  return entries;
}

/* ════ فتح البلاير ════ */
function openPlayerChannel(ch){
  App.currentType='channel';App.currentEpisodeIdx=-1;
  document.getElementById('pEpNav').style.display='none';
  document.getElementById('epPanelBtn').style.display='none';
  document.getElementById('m3uPanelBtn').style.display='none';
  const fmt=fmtLabel(ch.stream_url||'');
  const isLive=isLiveFormat(ch.stream_url||'');
  document.getElementById('pBadgeLabel').textContent=isLive?'LIVE':fmt;
  document.getElementById('pBadge').style.background=isLive?'var(--red)':'#1a5276';
  document.getElementById('pChannelName').textContent=ch.name;
  document.getElementById('pFmtTag').textContent=fmt;
  document.getElementById('pTime').textContent=isLive?'بث مباشر':'00:00 / 00:00';
  const f=detectFmt(ch.stream_url||'');
  if(f==='hls'&&(ch.stream_url.toLowerCase().endsWith('.m3u'))){
    _openOverlay('',ch.subtitle_url||'');
    toast('جارٍ تحميل قائمة M3U...','fas fa-list');
    parseM3U(ch.stream_url).then(entries=>{
      if(!entries.length){toast('القائمة فارغة','fas fa-exclamation');return}
      PL.m3uEntries=entries;PL.m3uIdx=0;
      buildM3UPanel();document.getElementById('m3uPanelBtn').style.display='flex';
      toggleM3UPanel();
      playM3UEntry(0);
    });
    return;
  }
  _openOverlay(ch.stream_url,ch.subtitle_url||'');
  if(ch.id) fetch('api.php?action=increment_view&id='+ch.id+'&type=channel').catch(()=>{});
}

function openPlayerEpisode(idx){
  App.currentType='episode';App.currentEpisodeIdx=idx;
  const ep=App.allEpisodes[idx];if(!ep)return;
  const fmt=fmtLabel(ep.stream_url||'');
  const isLive=isLiveFormat(ep.stream_url||'');
  document.getElementById('pBadgeLabel').textContent=isLive?'EP':fmt;
  document.getElementById('pBadge').style.background=isLive?'var(--red)':'#7B2FBE';
  document.getElementById('pChannelName').textContent=App.currentSeriesName;
  document.getElementById('pFmtTag').textContent=fmt;
  document.getElementById('pEpLabel').textContent='الحلقة '+ep.episode_number;
  document.getElementById('pEpNav').style.display='flex';
  document.getElementById('pPrevEp').disabled=(idx===0);
  document.getElementById('pNextEp').disabled=(idx===App.allEpisodes.length-1);
  document.getElementById('epPanelBtn').style.display='flex';
  document.getElementById('m3uPanelBtn').style.display='none';
  _openOverlay(ep.stream_url,ep.subtitle_url||'');
  buildEpPanel();
  fetch('api.php?action=increment_view&id='+ep.id+'&type=episode').catch(()=>{});
}

function navEpisode(dir){const ni=App.currentEpisodeIdx+dir;if(ni>=0&&ni<App.allEpisodes.length)openPlayerEpisode(ni)}

const _savedPlayer={active:false,url:'',subUrl:'',time:0,type:'',epIdx:-1,seriesId:0,chObj:null};

function _openOverlay(url,subUrl){
  const overlay=document.getElementById('playerOverlay');
  const sameEp=_savedPlayer.active
    &&_savedPlayer.type===App.currentType
    &&(App.currentType==='channel'
      ?_savedPlayer.url===url
      :_savedPlayer.epIdx===App.currentEpisodeIdx&&_savedPlayer.seriesId===App.currentSeriesId);
  overlay.classList.add('active');
  document.body.style.overflow='hidden';
  window.history.pushState({player:'active'},'');
  if(typeof fixPlayerHeight==='function')fixPlayerHeight();
  if(sameEp){
    const v=document.getElementById('html5Player');
    if(v&&v.paused)v.play().catch(()=>{});
  }else{
    if(url)initStream(url,subUrl);
    _savedPlayer.active=true;_savedPlayer.url=url;_savedPlayer.subUrl=subUrl;
    _savedPlayer.type=App.currentType;_savedPlayer.epIdx=App.currentEpisodeIdx;_savedPlayer.seriesId=App.currentSeriesId;
  }
  showControls();
}

function closePlayer(){
  if(document.fullscreenElement)document.exitFullscreen();
  const v=document.getElementById('html5Player');
  if(v&&!isNaN(v.currentTime)){_savedPlayer.time=v.currentTime;try{v.pause()}catch(e){}}
  document.getElementById('playerOverlay').classList.remove('active');
  document.getElementById('epPanel').classList.remove('open');
  document.getElementById('m3uPanel').classList.remove('open');
  PL.epPanelOpen=false;PL.m3uPanelOpen=false;
  document.body.style.overflow='';
  PL.currentFocusIndex=-1;
  document.querySelectorAll('.tv-focus').forEach(el=>el.classList.remove('tv-focus'));
  if(window.history.state&&window.history.state.player==='active')window.history.back();
}

/* ════ initStream ════ */
function initStream(url,subUrl){
  const v=document.getElementById('html5Player');
  destroyPlayer();v.innerHTML='';
  if(subUrl){
    const t=document.createElement('track');t.kind='subtitles';t.label='العربية';t.srclang='ar';t.src=subUrl;t.default=true;v.appendChild(t);
    document.getElementById('subBtn').style.opacity='1';PL.subtitleOn=true;
  }else{document.getElementById('subBtn').style.opacity='0.4';PL.subtitleOn=false}
  const fmt=detectFmt(url);
  showBuf(true);
  if(fmt==='hls'){
    if(typeof Hls!=='undefined'&&Hls.isSupported()){
      PL.hls=new Hls({enableWorker:true,lowLatencyMode:true,capLevelToPlayerSize:false,maxMaxBufferLength:60});
      PL.hls.loadSource(url);PL.hls.attachMedia(v);
      PL.hls.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));
      PL.hls.on(Hls.Events.ERROR,(e,d)=>{if(d.fatal){toast('خطأ في بث HLS','fas fa-triangle-exclamation');showBuf(false)}});
    }else if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=url;v.play().catch(()=>{});}
    else{v.src=url;v.play().catch(()=>{})}
  }else if(fmt==='dash'){
    if(typeof dashjs!=='undefined'){PL.dash=dashjs.MediaPlayer().create();PL.dash.initialize(v,url,true);}
    else{v.src=url;v.play().catch(()=>{})}
  }else if(fmt==='flv'){
    if(typeof flvjs!=='undefined'&&flvjs.isSupported()){
      PL.flv=flvjs.createPlayer({type:'flv',url,enableWorker:true,enableStashBuffer:false});
      PL.flv.attachMediaElement(v);PL.flv.load();PL.flv.play();
      PL.flv.on(flvjs.Events.ERROR,()=>{toast('خطأ في FLV','fas fa-triangle-exclamation');showBuf(false)});
    }else{toast('المتصفح لا يدعم FLV','fas fa-circle-info');showBuf(false)}
  }else if(fmt==='ts'){
    if(typeof Hls!=='undefined'&&Hls.isSupported()){
      PL.hls=new Hls({enableWorker:true,lowLatencyMode:true});
      PL.hls.loadSource(url);PL.hls.attachMedia(v);
      PL.hls.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));
      PL.hls.on(Hls.Events.ERROR,(e,d)=>{if(d.fatal){PL.hls.destroy();PL.hls=null;v.src=url;v.play().catch(()=>{toast('تعذّر تشغيل TS','fas fa-triangle-exclamation')});}});
    }else{v.src=url;v.play().catch(()=>{})}
  }else{
    v.src=url;
    v.play().catch(err=>{if(fmt==='mkv'||fmt==='avi'){toast('تنبيه: '+fmt.toUpperCase()+' قد لا يُدعم في هذا المتصفح','fas fa-circle-info');}});
  }
  v.volume=PL.vol;v.muted=PL.muted;
  v.ontimeupdate=updateProgress;
  v.onwaiting=()=>showBuf(true);
  v.onplaying=()=>{showBuf(false);setPlayIcon(false)};
  v.onpause=()=>setPlayIcon(true);
  v.onloadeddata=()=>showBuf(false);
  v.onerror=()=>{showBuf(false);toast('تعذّر تحميل الفيديو — تحقق من الرابط','fas fa-triangle-exclamation');};
  v.onended=()=>{
    if(App.currentType==='episode'&&App.currentEpisodeIdx<App.allEpisodes.length-1){toast('انتقال للحلقة التالية...','fas fa-forward');setTimeout(()=>navEpisode(1),2000);}
    if(PL.m3uEntries.length&&PL.m3uIdx<PL.m3uEntries.length-1){playM3UEntry(PL.m3uIdx+1);}
  };
}

function destroyPlayer(){
  const v=document.getElementById('html5Player');
  if(PL.hls){try{PL.hls.destroy()}catch(e){}PL.hls=null}
  if(PL.dash){try{PL.dash.reset()}catch(e){}PL.dash=null}
  if(PL.flv){try{PL.flv.destroy()}catch(e){}PL.flv=null}
  try{v.pause();v.removeAttribute('src');v.load()}catch(e){}
  showBuf(false);
}

/* ════ M3U PANEL ════ */
function playM3UEntry(idx){
  if(idx<0||idx>=PL.m3uEntries.length)return;
  PL.m3uIdx=idx;
  const entry=PL.m3uEntries[idx];
  document.getElementById('pChannelName').textContent=entry.name;
  const fmt=fmtLabel(entry.url);
  document.getElementById('pFmtTag').textContent=fmt;
  document.getElementById('pBadgeLabel').textContent=isLiveFormat(entry.url)?'LIVE':fmt;
  initStream(entry.url,'');
  document.querySelectorAll('.m3u-item').forEach((el,i)=>el.classList.toggle('playing',i===idx));
  toast('▶ '+entry.name,'fas fa-music');
}
function buildM3UPanel(){
  document.getElementById('m3uPanelHead').textContent='قائمة التشغيل ('+PL.m3uEntries.length+')';
  const b=document.getElementById('m3uPanelBody');b.innerHTML='';
  PL.m3uEntries.forEach((e,idx)=>{
    const d=document.createElement('div');d.className='m3u-item'+(idx===PL.m3uIdx?' playing':'');
    const logoHtml=e.logo?`<img class="m3u-item-logo" src="${esc(e.logo)}" onerror="this.src=''" loading="lazy">`:`<div class="m3u-item-logo" style="display:flex;align-items:center;justify-content:center"><i class="fas fa-tv" style="color:#555"></i></div>`;
    d.innerHTML=`${logoHtml}<div><div class="m3u-item-name">${esc(e.name)}</div><div class="m3u-item-group">${esc(e.group||fmtLabel(e.url))}</div></div>`;
    d.onclick=()=>playM3UEntry(idx);
    b.appendChild(d);
  });
}
function toggleM3UPanel(){PL.m3uPanelOpen=!PL.m3uPanelOpen;document.getElementById('m3uPanel').classList.toggle('open',PL.m3uPanelOpen)}

/* ════ EP PANEL ════ */
function buildEpPanel(){
  document.getElementById('epPanelTitle').textContent=App.currentSeriesName;
  const b=document.getElementById('epPanelBody');b.innerHTML='';
  App.allEpisodes.forEach((ep,idx)=>{
    const d=document.createElement('div');d.className='ep-item'+(idx===App.currentEpisodeIdx?' playing':'');d.id='epi'+idx;d.style.animationDelay=(idx*.04)+'s';
    const sb=ep.subtitle_url?'<span style="font-size:.65rem;color:#4CC9F0"><i class="fas fa-closed-captioning"></i></span>':'';
    const fmt=fmtLabel(ep.stream_url||'');
    d.innerHTML='<div class="ep-item-num">'+ep.episode_number+'</div><div class="ep-item-info"><div class="ep-item-title">'+esc(ep.title)+'</div><div class="ep-item-meta">'+(ep.duration||'')+sb+' <span style="font-size:.63rem;color:#777;background:rgba(255,255,255,.06);padding:1px 6px;border-radius:4px">'+fmt+'</span></div></div><div class="ep-item-play"><i class="fas fa-play"></i></div>';
    d.onclick=()=>{openPlayerEpisode(idx);if(window.innerWidth<=768)toggleEpPanel()};
    b.appendChild(d);
  });
}
function toggleEpPanel(){PL.epPanelOpen=!PL.epPanelOpen;document.getElementById('epPanel').classList.toggle('open',PL.epPanelOpen)}

/* ════ CONTROLS ════ */
function updateProgress(){const v=document.getElementById('html5Player');if(!v.duration||isNaN(v.duration))return;const p=(v.currentTime/v.duration)*100;document.getElementById('pFill').style.width=p+'%';document.getElementById('pTime').textContent=ft(v.currentTime)+' / '+ft(v.duration)}
function seekTo(e){const v=document.getElementById('html5Player');if(!v.duration||isNaN(v.duration))return;const r=document.getElementById('pProgress').getBoundingClientRect();v.currentTime=((e.clientX-r.left)/r.width)*v.duration;updateProgress()}
function setVolume(e){const r=e.currentTarget.getBoundingClientRect(),p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));document.getElementById('html5Player').volume=p;PL.vol=p;document.getElementById('volFill').style.width=(p*100)+'%';document.getElementById('muteIcon').className=p===0?'fas fa-volume-mute p-ctrl':'fas fa-volume-up p-ctrl'}
function changeVol(d){const nv=Math.max(0,Math.min(1,PL.vol+d));document.getElementById('html5Player').volume=nv;PL.vol=nv;document.getElementById('volFill').style.width=(nv*100)+'%';toast('الصوت: '+Math.round(nv*100)+'%',nv===0?'fas fa-volume-mute':'fas fa-volume-up')}
function toggleMute(){const v=document.getElementById('html5Player');PL.muted=!PL.muted;v.muted=PL.muted;document.getElementById('muteIcon').className=PL.muted?'fas fa-volume-mute p-ctrl':'fas fa-volume-up p-ctrl';toast(PL.muted?'كتم الصوت':'تفعيل الصوت',PL.muted?'fas fa-volume-mute':'fas fa-volume-up')}
function togglePlay(){const v=document.getElementById('html5Player');if(v.paused)v.play();else v.pause();flash(v.paused?'pause':'play')}
function setPlayIcon(p){document.getElementById('playBtn').innerHTML=p?'<i class="fas fa-play"></i>':'<i class="fas fa-pause"></i>'}
function flash(t){const el=document.getElementById('pFlash');el.innerHTML='<i class="fas fa-'+t+'"></i>';el.classList.add('show');setTimeout(()=>el.classList.remove('show'),400)}
function skip(s){const v=document.getElementById('html5Player');v.currentTime=Math.max(0,Math.min(v.currentTime+s,v.duration||0));updateProgress();flash(s>0?'forward':'backward')}
function ft(s){const m=Math.floor(s/60),ss=Math.floor(s%60);return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0')}
function toggleFullscreen(){const el=document.getElementById('playerOverlay');if(!document.fullscreenElement)el.requestFullscreen();else document.exitFullscreen()}
function toggleSubtitle(){const t=document.getElementById('html5Player').textTracks[0];if(t){PL.subtitleOn=!PL.subtitleOn;t.mode=PL.subtitleOn?'showing':'hidden';toast(PL.subtitleOn?'تشغيل الترجمة':'إيقاف الترجمة')}}

const ENH_MODES=[
  {cls:'',label:'قياسي',icon:'fas fa-tv',msg:'وضع قياسي — بدون تحسين'},
  {cls:'enh-deblock',label:'De-Block',icon:'fas fa-border-none',msg:'De-Block — إزالة تشوهات البكسل'},
  {cls:'enh-hdr',label:'HDR',icon:'fas fa-sun',msg:'HDR — تحسين الألوان والتباين'},
  {cls:'enh-frame',label:'Frame+',icon:'fas fa-film',msg:'Frame+ — تحسين وضوح الفريمات'},
  {cls:'enh-full',label:'Ultra',icon:'fas fa-wand-magic-sparkles',msg:'Ultra — De-Block + HDR + Frame شامل'}
];
let _enhIdx=0;
function toggleEnhancements(){
  const v=document.getElementById('html5Player');
  const b=document.getElementById('enhanceBtn');
  const lbl=document.getElementById('enhLabel');
  ENH_MODES.forEach(m=>{if(m.cls)v.classList.remove(m.cls)});
  v.style.filter='';
  _enhIdx=(_enhIdx+1)%ENH_MODES.length;
  const mode=ENH_MODES[_enhIdx];
  if(mode.cls)v.classList.add(mode.cls);
  b.innerHTML='<i class="'+mode.icon+'"></i>';
  if(_enhIdx===0){b.classList.remove('active-magic');b.style.opacity='0.6';}
  else{b.classList.add('active-magic');b.style.opacity='1';}
  if(lbl)lbl.textContent=mode.label;
  PL.enhanceOn=_enhIdx>0;
  toast(mode.msg,mode.icon);
}

function showBuf(s){document.getElementById('pBuffer').classList.toggle('show',s)}
function showControls(){
  const r=document.getElementById('playerOverlay');
  r.classList.remove('idle');
  document.getElementById('pTop').classList.remove('hide');
  document.getElementById('pBottom').classList.remove('hide');
  clearTimeout(PL.idle);
  PL.idle=setTimeout(()=>{
    if(!document.getElementById('html5Player').paused&&!PL.epPanelOpen&&!PL.m3uPanelOpen){
      document.getElementById('pTop').classList.add('hide');
      document.getElementById('pBottom').classList.add('hide');
      r.classList.add('idle');
    }
  },4000);
}

document.addEventListener('DOMContentLoaded',function(){
  const wrap=document.getElementById('pvWrap');
  const overlay=document.getElementById('playerOverlay');
  let _lastTap=0;
  wrap.addEventListener('touchstart',function(e){
    const now=Date.now();const diff=now-_lastTap;_lastTap=now;
    if(diff<280&&diff>0){
      e.preventDefault();
      const t=e.changedTouches[0];const rect=wrap.getBoundingClientRect();const x=t.clientX-rect.left;
      if(x<rect.width/3)skip(-10);else if(x>(rect.width/3)*2)skip(10);else togglePlay();
    }else{showControls();}
  },{passive:false});
  wrap.addEventListener('click',showControls);
  wrap.addEventListener('dblclick',function(e){
    const rect=wrap.getBoundingClientRect();const x=e.clientX-rect.left;
    if(x<rect.width/3)skip(-10);else if(x>(rect.width/3)*2)skip(10);else togglePlay();
  });
  overlay.addEventListener('mousemove',showControls,{passive:true});
  function fixPlayerHeight(){
    const el=document.getElementById('playerOverlay');if(!el)return;
    const h=window.innerHeight;el.style.height=h+'px';el.style.minHeight=h+'px';
  }
  window.addEventListener('resize',fixPlayerHeight,{passive:true});
  window.addEventListener('orientationchange',function(){setTimeout(fixPlayerHeight,300);},{passive:true});
  document.getElementById('playerOverlay').addEventListener('animationstart',fixPlayerHeight);
  fixPlayerHeight();
});

window.addEventListener('popstate',function(){if(document.getElementById('playerOverlay').classList.contains('active'))closePlayer()});

function updFocus(){PL.focusableElements=Array.from(document.querySelectorAll('.player-overlay .p-ctrl,.player-overlay .p-menu-item')).filter(el=>window.getComputedStyle(el).display!=='none')}
function moveFocus(dir){
  showControls();updFocus();if(!PL.focusableElements.length)return;
  if(PL.currentFocusIndex===-1)PL.currentFocusIndex=0;
  else PL.currentFocusIndex=dir==='next'?(PL.currentFocusIndex+1)%PL.focusableElements.length:(PL.currentFocusIndex-1+PL.focusableElements.length)%PL.focusableElements.length;
  PL.focusableElements.forEach(el=>el.classList.remove('tv-focus'));
  const t=PL.focusableElements[PL.currentFocusIndex];
  if(t){t.classList.add('tv-focus');try{t.focus({preventScroll:true})}catch(e){}}
}

/* ════ EVENT LISTENER FOR PLAYER TV NAVIGATION ════ */
document.addEventListener('keydown',function(e){
  if(!document.getElementById('playerOverlay').classList.contains('active'))return;
  var kc=e.keyCode||e.which||0;
  var ks=e.key||'';
  var K={
    UP:   ks==='ArrowUp'   ||kc===38||kc===19,
    DOWN: ks==='ArrowDown' ||kc===40||kc===20,
    LEFT: ks==='ArrowLeft' ||kc===37||kc===21,
    RIGHT:ks==='ArrowRight'||kc===39||kc===22,
    OK:   ks==='Enter'||ks==='Select'||ks===' '||kc===13||kc===23||kc===29||kc===66,
    BACK: ks==='Escape'||ks==='Backspace'||ks==='BrowserBack'||kc===27||kc===8||kc===4||kc===10009,
    PP:   ks==='MediaPlayPause'||kc===179||kc===85||kc===126||kc===127,
    VU:   ks==='AudioVolumeUp'||kc===175||kc===24,
    VD:   ks==='AudioVolumeDown'||kc===174||kc===25,
    MU:   ks==='AudioVolumeMute'||kc===173||kc===164,
    CU:   ks==='ChannelUp'||kc===427||kc===166,
    CD:   ks==='ChannelDown'||kc===428||kc===167
  };
  var isHidden=document.getElementById('pBottom').classList.contains('hide');
  if(K.RIGHT){isHidden?skip(10):moveFocus('next');e.preventDefault();}
  else if(K.LEFT){isHidden?skip(-10):moveFocus('prev');e.preventDefault();}
  else if(K.UP||K.DOWN){showControls();e.preventDefault();}
  else if(K.OK){
    e.preventDefault();
    var f=document.querySelector('.player-overlay .tv-focus')||document.activeElement;
    if(f&&(f.classList.contains('p-ctrl')||f.classList.contains('p-menu-item')||f.classList.contains('ep-item')||f.classList.contains('m3u-item'))){f.click();}
    else{togglePlay();}
  }
  else if(K.BACK){e.preventDefault();closePlayer();}
  else if(K.PP){togglePlay();e.preventDefault();}
  else if(K.VU){changeVol(.1);e.preventDefault();}
  else if(K.VD){changeVol(-.1);e.preventDefault();}
  else if(K.MU){toggleMute();e.preventDefault();}
  else if(K.CU){if(App.currentType==='episode')navEpisode(1);else if(PL.m3uEntries.length)playM3UEntry(PL.m3uIdx+1);e.preventDefault();}
  else if(K.CD){if(App.currentType==='episode')navEpisode(-1);else if(PL.m3uEntries.length)playM3UEntry(PL.m3uIdx-1);e.preventDefault();}
});

/* ════ MAIN UI TV NAVIGATION (OUTSIDE PLAYER) ════ */
let currentMainFocus = null;
document.addEventListener('keydown', function(e) {
    if(document.getElementById('playerOverlay').classList.contains('active')) return;
    if(document.getElementById('tmdbInfoM').style.display === 'flex') return;

    var kc=e.keyCode||e.which||0;
    var ks=e.key||'';
    var K = {
        UP:   ks==='ArrowUp'   ||kc===38||kc===19,
        DOWN: ks==='ArrowDown' ||kc===40||kc===20,
        LEFT: ks==='ArrowLeft' ||kc===37||kc===21,
        RIGHT:ks==='ArrowRight'||kc===39||kc===22,
        OK:   ks==='Enter'||ks==='Select'||ks===' '||kc===13||kc===23||kc===29,
        BACK: ks==='Escape'||ks==='Backspace'||kc===27||kc===8||kc===4||kc===10009
    };

    if(K.BACK) {
        if(!document.getElementById('epSection').classList.contains('hidden')) { backToChannels(); e.preventDefault(); return; }
        if(!document.getElementById('chSection').classList.contains('hidden')) { backToCategories(); e.preventDefault(); return; }
        return;
    }

    if(!K.UP && !K.DOWN && !K.LEFT && !K.RIGHT && !K.OK) return;

    const selectors = '.cat-card, .ch-card, .sr-card, .ep-card, .back-btn, .ctab, .m3u-item'; // Added sidepanels focus elements mapping implicitly through .m3u-item 
    let focusables = Array.from(document.querySelectorAll(selectors)).filter(el => {
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && !el.closest('.hidden');
    });

    if(focusables.length === 0) return;

    if(K.OK) {
        if(currentMainFocus && focusables.includes(currentMainFocus)) {
            currentMainFocus.click();
            e.preventDefault();
        }
        return;
    }

    e.preventDefault();

    if(!currentMainFocus || !focusables.includes(currentMainFocus)) {
        setMainFocus(focusables[0]);
        return;
    }

    const currentRect = currentMainFocus.getBoundingClientRect();
    let bestNext = null;
    let minDistance = Infinity;

    focusables.forEach(el => {
        if(el === currentMainFocus) return;
        const rect = el.getBoundingClientRect();
        
        let isDirectionMatch = false;
        let dist = 0;

        if(K.RIGHT) {
            if(rect.left > currentRect.right - 10) { isDirectionMatch = true; dist = Math.abs(rect.left - currentRect.right) + Math.abs(rect.top - currentRect.top); }
        }
        if(K.LEFT) {
            if(rect.right < currentRect.left + 10) { isDirectionMatch = true; dist = Math.abs(currentRect.left - rect.right) + Math.abs(rect.top - currentRect.top); }
        }
        if(K.DOWN) {
            if(rect.top > currentRect.bottom - 10) { isDirectionMatch = true; dist = Math.abs(rect.top - currentRect.bottom) + Math.abs(rect.left - currentRect.left)*2; }
        }
        if(K.UP) {
            if(rect.bottom < currentRect.top + 10) { isDirectionMatch = true; dist = Math.abs(currentRect.top - rect.bottom) + Math.abs(rect.left - currentRect.left)*2; }
        }

        if(isDirectionMatch && dist < minDistance) {
            minDistance = dist;
            bestNext = el;
        }
    });

    if(bestNext) {
        setMainFocus(bestNext);
    }
});

function setMainFocus(el) {
    if(currentMainFocus) currentMainFocus.classList.remove('tv-focus');
    currentMainFocus = el;
    currentMainFocus.classList.add('tv-focus');
    currentMainFocus.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try { currentMainFocus.focus({ preventScroll: true }); } catch(e) {}
}


function showLicenseWall(){document.getElementById('catGrid').innerHTML=`<div class="license-wall" style="grid-column:1/-1"><div style="font-size:5rem;color:#ff4d57;margin-bottom:20px;filter:drop-shadow(0 0 25px rgba(229,9,20,.5))"><i class="fas fa-lock"></i></div><h2 style="font-size:1.8rem;font-weight:900;margin-bottom:12px">الرخصة منتهية</h2><p style="color:var(--text-muted);margin-bottom:28px;font-size:1rem">يرجى تجديد الرخصة للوصول إلى القنوات والمحتوى</p><a href="activate.php" class="btn-primary"><i class="fas fa-rotate"></i> تجديد الرخصة</a></div>`}

function toast(msg,icon='fas fa-circle-info'){const c=document.getElementById('toastContainer'),t=document.createElement('div');t.className='toast';t.innerHTML=`<i class="${icon}" style="color:#ff4d57;flex-shrink:0"></i>${msg}`;c.appendChild(t);setTimeout(()=>{t.classList.add('out');t.addEventListener('animationend',()=>t.remove())},3200)}
function addRipple(el,e){const rect=el.getBoundingClientRect(),r=document.createElement('span'),size=Math.max(rect.width,rect.height);r.className='ripple-el';r.style.cssText=`width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;el.appendChild(r);setTimeout(()=>r.remove(),650)}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function escA(s){return(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'")}

loadCategories();

/* ══════════════════════════════════════════════════════════════
   BUG-FIX PATCHES
   ══════════════════════════════════════════════════════════════ */
var _lastUrl='';var _stallTicks=0;var _watchdogInt=null;var _bgPauseTimer=null;var _hiddenAt=0;

function _watchdogStart(){
  if(_watchdogInt)clearInterval(_watchdogInt);
  _stallTicks=0;var _prevTime=-1;
  _watchdogInt=setInterval(function(){
    var v=document.getElementById('html5Player');
    var overlay=document.getElementById('playerOverlay');
    if(!v||!overlay||!overlay.classList.contains('active')){clearInterval(_watchdogInt);_watchdogInt=null;return;}
    if(v.paused||v.ended||v.readyState===0){_stallTicks=0;return;}
    if(v.currentTime===_prevTime&&v.readyState<3){
      _stallTicks++;
      if(_stallTicks>=5){_stallTicks=0;if(_lastUrl){toast('إعادة الاتصال تلقائياً...','fas fa-rotate');var sub=document.querySelector('#html5Player track[kind="subtitles"]');initStream(_lastUrl,sub?sub.src:'');}}
    }else{_stallTicks=0;}
    _prevTime=v.currentTime;
  },2000);
}
function _watchdogStop(){if(_watchdogInt){clearInterval(_watchdogInt);_watchdogInt=null;}}

function _resumeKey(){if(App.currentType==='episode'){return'resume_ep_'+App.currentSeriesId+'_'+App.currentEpisodeIdx;}return null;}
function _resumeSave(){
  var key=_resumeKey();if(!key)return;
  var v=document.getElementById('html5Player');
  if(!v||!v.duration||isNaN(v.duration)||v.currentTime<5)return;
  if(v.duration-v.currentTime<10){_resumeDelete();return;}
  try{localStorage.setItem(key,JSON.stringify({t:Math.floor(v.currentTime),d:Math.floor(v.duration),ts:Date.now()}));}catch(e){}
}
function _resumeGet(){
  var key=_resumeKey();if(!key)return null;
  try{var raw=localStorage.getItem(key);if(!raw)return null;var obj=JSON.parse(raw);if(Date.now()-obj.ts>30*24*3600*1000){localStorage.removeItem(key);return null;}return obj;}
  catch(e){return null;}
}
function _resumeDelete(){var key=_resumeKey();if(key)try{localStorage.removeItem(key);}catch(e){}}
function _resumeOffer(savedPos,duration){
  var old=document.getElementById('resumeBar');if(old)old.remove();
  var pct=Math.round((savedPos/duration)*100);
  var bar=document.createElement('div');bar.id='resumeBar';
  bar.innerHTML='<div style="flex:1;min-width:0"><span style="font-weight:800;color:#fff">استئناف من '+ft(savedPos)+'</span><div style="height:3px;background:rgba(255,255,255,.15);border-radius:99px;margin-top:6px"><div style="width:'+pct+'%;height:100%;background:var(--red);border-radius:99px"></div></div></div><button id="resumeYes" style="background:var(--red);color:#fff;border:none;padding:8px 18px;border-radius:99px;font-weight:800;font-size:.85rem;cursor:pointer;font-family:inherit;flex-shrink:0">استئناف</button><button id="resumeNo" style="background:rgba(255,255,255,.1);color:#ccc;border:none;padding:8px 14px;border-radius:99px;font-weight:700;font-size:.85rem;cursor:pointer;font-family:inherit;flex-shrink:0">من البداية</button>';
  bar.style.cssText='position:absolute;bottom:110px;left:4%;right:4%;z-index:9999;background:rgba(18,18,18,.96);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.1);border-right:3px solid var(--red);border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 30px rgba(0,0,0,.6);animation:fadeUp .35s ease both;direction:rtl;';
  document.getElementById('playerOverlay').appendChild(bar);
  document.getElementById('resumeYes').onclick=function(){
    var v=document.getElementById('html5Player');
    if(v){function doSeek(){v.currentTime=savedPos;bar.remove();}if(v.readyState>=2){doSeek();}else{v.addEventListener('canplay',function s(){v.removeEventListener('canplay',s);doSeek();});}}
    bar.remove();
  };
  document.getElementById('resumeNo').onclick=function(){_resumeDelete();bar.remove();};
  setTimeout(function(){if(bar.parentNode)bar.remove();},12000);
}
var _resumeInterval=null;
function _resumeStartSaving(){if(_resumeInterval)clearInterval(_resumeInterval);_resumeInterval=setInterval(_resumeSave,5000);}
function _resumeStopSaving(){if(_resumeInterval){clearInterval(_resumeInterval);_resumeInterval=null;}_resumeSave();}
document.addEventListener('play',function(e){if(!e.target||e.target.id!=='html5Player')return;_resumeStartSaving();},true);
document.addEventListener('pause',function(e){if(!e.target||e.target.id!=='html5Player')return;_resumeSave();},true);
document.addEventListener('ended',function(e){if(!e.target||e.target.id!=='html5Player')return;_resumeStopSaving();_resumeDelete();},true);
document.getElementById('playerOverlay').addEventListener('animationend',function onOpen(e){
  if(e.animationName!=='playerSlideIn')return;
  if(App.currentType!=='episode')return;
  var v=document.getElementById('html5Player');
  function tryOffer(){
    var saved=_resumeGet();if(!saved||saved.t<5)return;
    if(v.duration&&!isNaN(v.duration)){_resumeOffer(saved.t,v.duration);}
    else{v.addEventListener('loadedmetadata',function meta(){v.removeEventListener('loadedmetadata',meta);var saved2=_resumeGet();if(saved2&&saved2.t>=5)_resumeOffer(saved2.t,v.duration||saved2.d);});}
  }
  setTimeout(tryOffer,600);
});
var _origClosePlayer_resume=closePlayer;
window.closePlayer=function(){_resumeStopSaving();var bar=document.getElementById('resumeBar');if(bar)bar.remove();_origClosePlayer_resume.call(this);};

document.addEventListener('play',function(e){
  if(!e.target||e.target.id!=='html5Player')return;
  var v=e.target;if(v.src&&v.src!==window.location.href)_lastUrl=v.src;
  _watchdogStart();
},true);
document.addEventListener('pause',function(e){if(e.target&&e.target.id==='html5Player')_watchdogStop();},true);
document.addEventListener('ended',function(e){if(e.target&&e.target.id==='html5Player')_watchdogStop();},true);
document.addEventListener('error',function(e){if(e.target&&e.target.id==='html5Player')_watchdogStop();},true);

(function(){
  var isIOS=/iPad|iPhone|iPod/.test(navigator.userAgent);
  var isSafari=/^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  if(!isIOS&&!isSafari)return;
  document.addEventListener('play',function(e){
    if(!e.target||e.target.id!=='html5Player')return;
    var v=e.target;if(v.muted&&!PL.muted){setTimeout(function(){v.muted=false;v.volume=PL.vol||1;},400);}
  },true);
})();

document.getElementById('playerOverlay').addEventListener('transitionend',function(){
  if(this.classList.contains('active'))return;
  _watchdogStop();
  if(_bgPauseTimer){clearTimeout(_bgPauseTimer);_bgPauseTimer=null;}
});

document.addEventListener('visibilitychange',function(){
  var overlay=document.getElementById('playerOverlay');
  var v=document.getElementById('html5Player');
  if(!overlay||!overlay.classList.contains('active')||!v)return;
  var ua=navigator.userAgent.toLowerCase();
  var isTV=ua.indexOf('tv')>=0||ua.indexOf('tizen')>=0||ua.indexOf('webos')>=0;
  if(document.hidden){
    _hiddenAt=Date.now();
    if(isTV){try{v.pause();}catch(e){}}
    else{_bgPauseTimer=setTimeout(function(){if(document.hidden&&!v.paused){try{v.pause();}catch(e){}toast('البث متوقف — التبويب مخفي','fas fa-moon');}},30000);}
  }else{
    if(_bgPauseTimer){clearTimeout(_bgPauseTimer);_bgPauseTimer=null;}
    var ms=Date.now()-_hiddenAt;
    if(v.paused&&ms>800){
      if(ms>120000&&_lastUrl){toast('استئناف البث...','fas fa-play');var sub=document.querySelector('#html5Player track[kind="subtitles"]');initStream(_lastUrl,sub?sub.src:'');}
      else{v.play().catch(function(){});}
    }
  }
});

window.addEventListener('beforeunload',function(){
  try{_watchdogStop();}catch(e){}
  try{var v=document.getElementById('html5Player');if(v){v.pause();v.removeAttribute('src');v.load();}if(PL.hls){PL.hls.destroy();PL.hls=null;}if(PL.dash){try{PL.dash.reset()}catch(e){}PL.dash=null;}if(PL.flv){PL.flv.destroy();PL.flv=null;}}catch(e){}
});
</script>

<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flv.js@latest/dist/flv.min.js"></script>

<script>
/* ════ TCL Android TV — Tabindex & Focus CSS ════ */
(function(){
  function applyTabindex(){
    document.querySelectorAll('.cat-card,.ch-card,.sr-card,.ep-card,.back-btn,.ctab,.p-ctrl,.ep-item,.m3u-item,.nav-btn').forEach(function(el){
      if(!el.getAttribute('tabindex'))el.setAttribute('tabindex','0');
    });
  }
  if(window.MutationObserver){
    var obs=new MutationObserver(function(ms){
      var c=false;ms.forEach(function(m){if(m.addedNodes.length)c=true;});
      if(c){clearTimeout(obs._t);obs._t=setTimeout(applyTabindex,200);}
    });
    obs.observe(document.body,{childList:true,subtree:true});
  }
  setTimeout(applyTabindex,800);
  setTimeout(applyTabindex,2500);
  var s=document.createElement('style');
  s.textContent=
    '.cat-card:focus,.ch-card:focus,.sr-card:focus,.ep-card:focus{outline:none!important;transform:translateY(-10px) scale(1.05)!important;border-color:rgba(229,9,20,.8)!important;box-shadow:0 22px 55px rgba(229,9,20,.5),0 0 0 4px #fff!important;z-index:10;}'+
    '.back-btn:focus,.ctab:focus,.nav-btn:focus{outline:none!important;background:var(--red)!important;color:#fff!important;border-color:var(--red)!important;box-shadow:0 0 0 3px #fff!important;}'+
    '.p-ctrl:focus,.p-ctrl.tv-focus{outline:none!important;background:rgba(229,9,20,.7)!important;box-shadow:0 0 0 4px #fff!important;border-radius:8px!important;transform:scale(1.15)!important;}'+
    '.m3u-item:focus{outline:3px solid var(--red)!important; transform:translateX(-3px);}';
  document.head.appendChild(s);
})();
</script>
</body>
</html>
