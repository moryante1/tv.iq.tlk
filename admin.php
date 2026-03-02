<?php
require_once 'config.php';
require_once 'client_config.php';

$license_key = getLicenseKey();
if (!$license_key) { header('Location: activate.php'); exit; }
$license_result = verifyLicenseFromServer($license_key);
if (!$license_result['success'] || !$license_result['valid']) { header('Location: activate.php'); exit; }
$_SESSION['license_info'] = $license_result['license'] ?? [];
$_SESSION['license_days_left'] = $license_result['license']['days_left'] ?? 0;
if(!isAdminLoggedIn()) { redirect('login.php'); }

// التأكد من وجود جدول الإعدادات
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT
    )");
} catch(PDOException $e) {}

// جلب الإعدادات الحالية من قاعدة البيانات
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}


// ══ نظام إدارة المستخدمين والصلاحيات ══
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) DEFAULT '',
        role ENUM('administrator','super','normal','custom') DEFAULT 'normal',
        allowed_sections TEXT DEFAULT '[]',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )");
    // بذر المدير الأول إن كان الجدول فارغاً
    $__au_cnt = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($__au_cnt == 0 && !empty($_SESSION['admin_username'])) {
        $__au_hash = password_hash('admin', PASSWORD_DEFAULT);
        $__au_name = $_SESSION['admin_username'];
        $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, allowed_sections) VALUES (?, ?, ?, 'administrator', '[]')")
            ->execute([$__au_name, $__au_hash, $__au_name]);
    }
} catch(PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE channels ADD COLUMN subtitle_url VARCHAR(1000) DEFAULT '' AFTER stream_url");
} catch(PDOException $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS series (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        poster_url VARCHAR(500),
        logo_icon VARCHAR(100) DEFAULT 'fas fa-film',
        display_order INT DEFAULT 0,
        views_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS episodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        series_id INT NOT NULL,
        episode_number INT DEFAULT 1,
        title VARCHAR(255) NOT NULL,
        stream_url VARCHAR(1000) NOT NULL,
        subtitle_url VARCHAR(1000),
        duration VARCHAR(50),
        description TEXT,
        display_order INT DEFAULT 0,
        views_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {}

define('VID_UPLOAD_DIR',   __DIR__ . '/uploads/videos/');
define('VID_SUB_DIR',      __DIR__ . '/uploads/subtitles/');
define('VID_MERGED_DIR',   __DIR__ . '/uploads/merged/');
define('SERIES_DIR',       __DIR__ . '/uploads/series/');

$_base = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
define('VID_UPLOAD_URL',   $_base . '/uploads/videos/');
define('VID_SUB_URL',      $_base . '/uploads/subtitles/');
define('VID_MERGED_URL',   $_base . '/uploads/merged/');
define('SERIES_URL',       $_base . '/uploads/series/');
define('POSTERS_DIR',      __DIR__ . '/uploads/posters/');
define('POSTERS_URL',      $_base . '/uploads/posters/');
define('OS_API', 'https://api.opensubtitles.com/api/v1');

foreach ([VID_UPLOAD_DIR,VID_SUB_DIR,VID_MERGED_DIR,SERIES_DIR,POSTERS_DIR] as $_d)
    if(!is_dir($_d)) @mkdir($_d,0755,true);

if (isset($_POST['ajax_action'])) {
    while(ob_get_level()) ob_end_clean();
    ob_start();
    @set_time_limit(0);
    @ini_set('memory_limit','-1');
    @ini_set('upload_max_filesize','0');
    @ini_set('post_max_size','0');
    @ini_set('max_input_time','-1');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    $act = $_POST['ajax_action'];

    if($act==='debug_upload'){
        $maxPost=ini_get('post_max_size');
        $maxFile=ini_get('upload_max_filesize');
        $uploadDir=VID_UPLOAD_DIR;
        $dirExists=is_dir($uploadDir);
        $dirWrite=$dirExists&&is_writable($uploadDir);
        jOk(['post_max_size'=>$maxPost,'upload_max_filesize'=>$maxFile,'upload_dir'=>$uploadDir,'dir_exists'=>$dirExists,'dir_writable'=>$dirWrite,'php_version'=>PHP_VERSION,'extensions'=>['fileinfo'=>extension_loaded('fileinfo'),'gd'=>extension_loaded('gd')]]);
    }

    function jOk($d=[]){while(ob_get_level())ob_end_clean();echo json_encode(array_merge(['success'=>true],$d));exit;}
    function jErr($e,$dbg=''){while(ob_get_level())ob_end_clean();$r=['success'=>false,'error'=>$e];if($dbg)$r['debug']=$dbg;echo json_encode($r);exit;}
    function mvFile($tmp,$dest){return move_uploaded_file($tmp,$dest);}
    function slugU($s){return strtolower(trim(preg_replace('/[^a-z0-9\-]/','',str_replace([' ','_'],'-',$s)),'-'));}
    function osH($auth=true){
        $h=['Content-Type: application/json','Accept: application/json',
            'Api-Key: '.($_SESSION['os_api_key']??''),'User-Agent: ShashetyIPTV/2.0'];
        if($auth&&!empty($_SESSION['os_token']))$h[]='Authorization: Bearer '.$_SESSION['os_token'];
        return $h;
    }
    function osReq($url,$m='GET',$body=null,$auth=true){
        $ch=curl_init($url);
        $o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>osH($auth)];
        if($m==='POST'){$o[CURLOPT_POST]=true;$o[CURLOPT_POSTFIELDS]=$body?json_encode($body):'{}';}
        elseif($m==='DELETE')$o[CURLOPT_CUSTOMREQUEST]='DELETE';
        curl_setopt_array($ch,$o);
        $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);$e=curl_error($ch);curl_close($ch);
        return[$c,$r,$e];
    }

    if($act==='save_api_settings'){
        global $pdo;
        $tmdb = $_POST['tmdb_key'] ?? '';
        $os_user = $_POST['os_user'] ?? '';
        $os_pass = $_POST['os_pass'] ?? '';
        $os_key = $_POST['os_key'] ?? '';
        $omdb = $_POST['omdb_key'] ?? '';

        $upsert = function($k, $v) use ($pdo) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$k]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            } else {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            }
        };

        $upsert('tmdb_api_key', $tmdb);
        $upsert('os_username', $os_user);
        $upsert('os_password', $os_pass);
        $upsert('os_api_key', $os_key);
        $upsert('omdb_api_key', $omdb);
        jOk(['message' => 'تم حفظ إعدادات الـ API بنجاح']);
    }

    // ══ وظائف التحميل الذكي (صُححت وباتت أكثر كفاءة) ══
    if($act === 'abort_smart_dl') {
        $n = trim($_POST['filename'] ?? '');
        if($n) @file_put_contents(VID_UPLOAD_DIR . $n . '.abort', '1');
        jOk();
    }

    if($act === 'prep_smart_dl') {
        $url = trim($_POST['url'] ?? '');
        if(!$url || !filter_var($url, FILTER_VALIDATE_URL)) jErr('الرابط المُدخل غير صالح!');
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if(!$ext || !in_array($ext, ['mp4','mkv','avi','mov','webm','ts','flv'])) $ext = 'mp4'; 

        $n = uniqid('vid_dl_').'.'.$ext;
        
        $headers = @get_headers($url, true);
        $totalSize = 0;
        if($headers) {
            $cl = $headers['Content-Length'] ?? ($headers['content-length'] ?? 0);
            $totalSize = is_array($cl) ? end($cl) : $cl;
        }

        $progFile = VID_UPLOAD_DIR . $n . '.prog';
        @file_put_contents($progFile, json_encode(['total' => (int)$totalSize, 'loaded' => 0]));
        @unlink(VID_UPLOAD_DIR . $n . '.abort'); 

        $original = basename(parse_url($url, PHP_URL_PATH));
        if(!$original || strlen($original) < 3) $original = $n;

        jOk(['filename' => $n, 'original' => $original, 'total' => (int)$totalSize]);
    }

    if($act === 'do_smart_dl') {
        @session_write_close(); // إغلاق الجلسة مهم ليعمل السيرفر بالخلفية بشكل متوازي

        $url = trim($_POST['url'] ?? '');
        $n = trim($_POST['filename'] ?? '');
        if(!$url || !$n) jErr('البيانات المُرسلة للتحميل غير مكتملة.');

        $dest = VID_UPLOAD_DIR . $n;
        $progFile = VID_UPLOAD_DIR . $n . '.prog';
        $abortFile = VID_UPLOAD_DIR . $n . '.abort';

        $ch = curl_init($url);
        $wh = @fopen($dest, 'wb');
        
        if(!$wh) { @unlink($progFile); jErr('المسار المختار على السيرفر لا يدعم الكتابة!'); }

        $total = 0;
        $lastUpdate = time();

        if(file_exists($progFile)) {
            $pf = json_decode(@file_get_contents($progFile), true);
            $total = $pf['total'] ?? 0;
        }

        curl_setopt($ch, CURLOPT_FILE, $wh);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
        curl_setopt($ch, CURLOPT_ENCODING, ""); 
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 1048576); 
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $total_dls, $dl, $total_uls, $ul) use ($progFile, $abortFile, &$lastUpdate, $total) {
            if(file_exists($abortFile)) return 1; // 1 يوقف الـ curl نهائياً
            
            $now = microtime(true);
            if($now - $lastUpdate >= 0.5) { 
                $realTotal = ($total > 0) ? $total : $total_dls; 
                @file_put_contents($progFile, json_encode(['total' => $realTotal, 'loaded' => $dl]));
                $lastUpdate = $now;
            }
        });

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        @fclose($wh);

        // إن أوقف المستخدم التحميل ✖
        if(file_exists($abortFile)) {
            @unlink($abortFile);
            @unlink($progFile);
            @unlink($dest);
            jErr('أمرت السيرفر بـ إيقاف وحذف التحميل الخاص بك بنجاح!');
        }

        $size = @filesize($dest);
        @unlink($progFile); 
        
        if($err || $size < 1024) { 
            @unlink($dest); 
            jErr('خطأ: تعذر الاتصال بالرابط المصدر. قد يكون محظوراً أو مدمجاً بالحماية.'); 
        }

        jOk(['filename' => $n, 'url' => VID_UPLOAD_URL.$n, 'size' => $size]);
    }

    if($act === 'check_smart_dl') {
        $n = trim($_POST['filename'] ?? '');
        if(!$n) jErr('مفقود');
        
        $progFile = VID_UPLOAD_DIR . $n . '.prog';
        if(file_exists($progFile)) {
            $d = json_decode(@file_get_contents($progFile), true);
            jOk(['total' => $d['total'] ?? 0, 'loaded' => $d['loaded'] ?? 0]);
        }
        jOk(['status' => 'waiting']); 
    }

    // ══ Upload & Save Handlers ══
    if($act==='upload_video'){
        $ferr = $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE;
        if($ferr === UPLOAD_ERR_INI_SIZE || $ferr === UPLOAD_ERR_FORM_SIZE)
            jErr('الملف أكبر من الحد المسموح به في إعدادات الخادم (upload_max_filesize).');
        if($ferr === UPLOAD_ERR_PARTIAL) jErr('تم رفع الملف جزئياً.');
        if($ferr === UPLOAD_ERR_NO_FILE) jErr('لم يتم إرسال أي ملف');
        if($ferr !== UPLOAD_ERR_OK) jErr('خطأ في رفع الملف (كود: '.$ferr.')');
        
        $f=$_FILES['video'];
        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['mp4','mkv','avi','mov','webm','ts','flv'])) jErr('صيغة غير مدعومة.');
            
        if(!is_dir(VID_UPLOAD_DIR)) @mkdir(VID_UPLOAD_DIR,0755,true);
        $n=uniqid('vid_').'.'.$ext;
        if(!mvFile($f['tmp_name'],VID_UPLOAD_DIR.$n)){ jErr('فشل في نقل الملف إلى الخادم'); }
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>VID_UPLOAD_URL.$n,'size'=>$f['size']]);
    }

    if($act==='upload_subtitle_file'){
        if(empty($_FILES['subtitle']))jErr('لم يتم إرسال ملف');
        $f=$_FILES['subtitle'];$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['srt','ass','ssa','vtt']))jErr('صيغة غير مدعومة');
        $n=uniqid('sub_').'.'.$ext;
        if(!mvFile($f['tmp_name'],VID_SUB_DIR.$n))jErr('فشل في رفع ملف الترجمة');
        
        $url = VID_SUB_URL.$n;
        if($ext === 'srt'){
            $vttN = uniqid('sub_').'.vtt';
            $srt = file_get_contents(VID_SUB_DIR.$n);
            $vtt = "WEBVTT\n\n" . preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '\1.\2', $srt);
            file_put_contents(VID_SUB_DIR.$vttN, $vtt);
            $url = VID_SUB_URL.$vttN; 
        }
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>$url]);
    }

    if($act==='merge_subtitle'){
        $vf=basename($_POST['video_file']??'');
        $sf=basename($_POST['subtitle_file']??'');
        if(!$vf||!$sf)jErr('ملفات ناقصة');
        $vpath=VID_UPLOAD_DIR.$vf;
        $spath=VID_SUB_DIR.$sf;
        if(!file_exists($vpath))jErr('ملف الفيديو غير موجود');
        if(!file_exists($spath))jErr('ملف الترجمة غير موجود');
        $subExt=strtolower(pathinfo($sf,PATHINFO_EXTENSION));
        $subUrl=VID_SUB_URL.$sf;
        if($subExt==='srt'){
            $vttN=uniqid('sub_').'.vtt';
            $srt=file_get_contents($spath);
            $vtt="WEBVTT\n\n".preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/','\1.\2',$srt);
            file_put_contents(VID_SUB_DIR.$vttN,$vtt);
            $subUrl=VID_SUB_URL.$vttN;
        }
        jOk(['filename'=>$vf,'url'=>VID_UPLOAD_URL.$vf,'subtitle_url'=>$subUrl,'size'=>round(filesize($vpath)/1024/1024,2).' MB','method'=>'no_ffmpeg']);
    }

    // الدالتين الجديتان: للحفظ كجديد كلياً، أو إضافته داخل مجلد المسلسلات الموجود مُسبقاً.
    if($act === 'save_to_shashety_auto') {
        global $pdo;
        $cid = intval($_POST['category_id'] ?? 0);
        $name = htmlspecialchars(strip_tags($_POST['name'] ?? ''));
        $url = $_POST['url'] ?? '';
        $sub = $_POST['subtitle_url'] ?? '';
        $target_series = intval($_POST['target_series_id'] ?? 0); 
        
        if(!$url) jErr('لا يوجد رابط للفيديو!');
        if(!$name && $target_series == 0) jErr('أدخل الاسم أولاً للعمل الجديد');
        if(!$name) $name = "فيديو / حلقة جديدة";

        try {
            if ($target_series > 0) {
                // حفظ الفيديو كحلقة جديدة داخل المجلد الحالي في شاشتي
                $stmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $target_series");
                $next_num = intval($stmt->fetchColumn()) + 1; 
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$target_series, $next_num, $name, $url, $sub, $next_num]);
                jOk(['series_id' => $target_series]);
            } else {
                // إنشاء مجلد مسلسلات جديد ثم وضع الحلقة فيه!
                if(!$cid) jErr('اختر القسم للمجلد الجديد');
                $slug = slugU($name).'-'.uniqid();
                $pdo->prepare("INSERT INTO series (category_id, name, slug) VALUES (?, ?, ?)")->execute([$cid, $name, $slug]);
                $sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, 1, ?, ?, ?, 1)")
                    ->execute([$sid, $name, $url, $sub]);
                jOk(['series_id' => $sid]);
            }
        } catch(PDOException $e) { jErr('مشكلة قواعد البيانات: '.$e->getMessage()); }
    }

    if($act==='save_video_manual'){
        global $pdo;
        $fn=basename($_POST['filename']??'');
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $cid=intval($_POST['category_id']??0);
        $vt=$_POST['video_type']??'uploaded';
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??'')); 
        $target_series = intval($_POST['target_series_id'] ?? 0); 
        
        if(!$fn)jErr('مفقود: لم تحدد ملف لحفظه');
        if(!$title && $target_series == 0) jErr('مفقود: أسم العمل الجديد');
        if(!$title) $title = "إضافة مسار فيديو";

        if($vt==='merged') { $vdir = VID_MERGED_DIR; $vurlBase = VID_MERGED_URL; }
        elseif($vt==='series') { $vdir = SERIES_DIR; $vurlBase = SERIES_URL; }
        else { $vdir = VID_UPLOAD_DIR; $vurlBase = VID_UPLOAD_URL; } // المتبقي هو uploaded
        
        $vpath = $vdir.$fn;
        $vurl = $vurlBase.$fn;
        if(!file_exists($vpath)) jErr('الملف الأصلي المعني، حُذف أو غير متوفر حاليا.');
        
        try {
            if ($target_series > 0) {
                $stmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $target_series");
                $next_num = intval($stmt->fetchColumn()) + 1;
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$target_series, $next_num, $title, $vurl, $sub, $next_num]);
            } else {
                if(!$cid) jErr('يجب وضع قسم رئيسي للملف لعمل التصنيف الخاص بك.');
                $slug=slugU($title).'-'.uniqid();
                $pdo->prepare("INSERT INTO series (category_id, name, slug) VALUES (?, ?, ?)")->execute([$cid, $title, $slug]);
                $sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, 1, ?, ?, ?, 1)")
                    ->execute([$sid, $title, $vurl, $sub]);
            }
            jOk(['url'=>$vurl]);
        } catch(PDOException $e) { jErr('قاعدة البيانات رفضت: '.$e->getMessage()); }
    }

    // نظام (نقل المجلد وتصحيح قاعدة البيانات المعزّز). تم إزالة تحويل. 
    // فيديوز <==> المسلسلات الفعليّة بقواعد شاشتي
    if($act === 'move_video_file') {
        global $pdo;
        $fn = basename($_POST['filename'] ?? '');
        $type = $_POST['type'] ?? '';
        $target = $_POST['target_folder'] ?? ''; 
        
        if(!$fn || !$type) jErr('خطأ اتصال: الملف غير واضح.');
        
        // استخلاص المسار القديم
        $srcDir = VID_UPLOAD_DIR; $srcUrl = VID_UPLOAD_URL;
        if($type === 'merged') { $srcDir = VID_MERGED_DIR; $srcUrl = VID_MERGED_URL; }
        elseif($type === 'series') { $srcDir = SERIES_DIR; $srcUrl = SERIES_URL; }
        
        $srcPath = $srcDir . $fn;
        if(!file_exists($srcPath)) jErr('الملف الفيزيائي ممسوح حاليا.');
        
        $is_db_series = false;
        $series_id = 0;

        if($target === 'videos') {
            // الرجوع به إلى الرفع العادي
            $destDir = VID_UPLOAD_DIR; $destUrl = VID_UPLOAD_URL;
        } else {
            // إلحاق هذا المجلد برقم مسلسل أو عمل تم اختياره من القائمة 
            $series_id = intval($target);
            if ($series_id <= 0) jErr('رقم المجلد الهدف في قاعدة شاشتي غير دقيق.');
            $destDir = SERIES_DIR; $destUrl = SERIES_URL;
            $is_db_series = true;
        }

        $destPath = $destDir . $fn;
        
        if($srcPath !== $destPath) {
            if(!rename($srcPath, $destPath)) jErr('الصلاحية ضعيفة بالخادم لنقل الملف جسدياً من '.$type);
        }
        
        $oldDbUrl = $srcUrl . $fn;
        $newDbUrl = $destUrl . $fn;
        
        // تحديث كل الحلقات القديمة لعنوان النطاق والامتداد الجديد إن كان فيه مسلسل
        if($oldDbUrl !== $newDbUrl) {
            $pdo->prepare("UPDATE episodes SET stream_url = ? WHERE stream_url = ?")->execute([$newDbUrl, $oldDbUrl]);
        }
        
        // هل أمره المستخدم بالارتباط بحلقة لمسلسل شاشتي معين للتو ؟
        if($is_db_series && $series_id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM episodes WHERE stream_url = ?");
            $stmt->execute([$newDbUrl]);
            if($stmt->fetch()) {
                // مرتبط ولكن بقسم خاطئ.. يتم تغيير معرفه إلى مسلسلك الهدف!
                $pdo->prepare("UPDATE episodes SET series_id = ? WHERE stream_url = ?")->execute([$series_id, $newDbUrl]);
            } else {
                // ادخاله لاول مره بعد استخراجه 
                $titleName = str_replace(['_', '-'], ' ', pathinfo($fn, PATHINFO_FILENAME));
                $nstmt = $pdo->query("SELECT MAX(episode_number) FROM episodes WHERE series_id = $series_id");
                $next_num = intval($nstmt->fetchColumn()) + 1;
                $pdo->prepare("INSERT INTO episodes (series_id, episode_number, title, stream_url, subtitle_url, display_order) VALUES (?, ?, ?, ?, '', ?)")
                    ->execute([$series_id, $next_num, $titleName, $newDbUrl, $next_num]);
            }
        } elseif ($target === 'videos') {
            // سحب المسار من النظام وتركه لعمله الخاص (عزله بالعام)، نقوم بحذفه كلياً من لوحة شاشتي (Episodes table)
            $pdo->prepare("DELETE FROM episodes WHERE stream_url = ?")->execute([$newDbUrl]);
        }
        
        jOk(['new_url' => $newDbUrl, 'message' => 'عظيم! تم النقل ومزامنة وتغيير وجهات الخادم بشكل قطعي.']);
    }

    if($act==='list_videos'){
        $vids=[];
        foreach([
            'uploaded'=>[VID_UPLOAD_DIR,VID_UPLOAD_URL],
            'merged'=>[VID_MERGED_DIR,VID_MERGED_URL],
            'series'=>[SERIES_DIR,SERIES_URL] 
        ] as $t=>[$d,$u]){
            if(!is_dir($d))continue;
            foreach(glob($d.'*.{mp4,mkv,avi,mov,webm}',GLOB_BRACE)as $f){
                $fn=basename($f);
                $vids[]=['filename'=>$fn,'url'=>$u.$fn,'size_mb'=>round(filesize($f)/1024/1024,2),'type'=>$t,'date'=>date('Y-m-d H:i',filemtime($f)),'ts'=>filemtime($f)];
            }
        }
        usort($vids,fn($a,$b)=>$b['ts']-$a['ts']);
        jOk(['videos'=>$vids]);
    }

    if($act==='delete_video'){
        $fn=basename($_POST['filename']??'');
        $t=$_POST['type']??'uploaded';
        if(!$fn)jErr('الاسم للهدف مسح مفقود');
        
        $p = VID_UPLOAD_DIR;
        if($t==='merged') $p = VID_MERGED_DIR;
        elseif($t==='series') $p = SERIES_DIR;
        
        $p .= $fn;
        if(!file_exists($p))jErr('لا أعثر عليه حاليا!');
        jOk(['deleted'=>@unlink($p)]);
    }

    // ══ Series Handlers ══
    if($act==='get_series'){
        global $pdo;
        $cid=intval($_POST['category_id']??0);
        $where=$cid?"WHERE s.category_id=$cid":"";
        $rows=$pdo->query("SELECT s.*,c.name as cat_name,COUNT(e.id) as ep_count FROM series s LEFT JOIN categories c ON s.category_id=c.id LEFT JOIN episodes e ON e.series_id=s.id $where GROUP BY s.id ORDER BY s.display_order,s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        jOk(['data'=>$rows]);
    }
    if($act==='add_series'){
        global $pdo;
        $cid=intval($_POST['category_id']??0);
        $name=htmlspecialchars(strip_tags($_POST['name']??''));
        $desc=htmlspecialchars(strip_tags($_POST['description']??''));
        $poster=htmlspecialchars(strip_tags($_POST['poster_url']??''));
        if(!$cid||!$name)jErr('ناقص');
        $slug=slugU($name).'-'.uniqid();
        try{
            $pdo->prepare("INSERT INTO series (category_id,name,slug,description,poster_url) VALUES (?,?,?,?,?)")->execute([$cid,$name,$slug,$desc,$poster]);
            jOk(['id'=>$pdo->lastInsertId()]);
        }catch(PDOException $e){jErr('تنبيه: '.$e->getMessage());}
    }
    if($act==='edit_series'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $cid=intval($_POST['category_id']??0);
        $name=htmlspecialchars(strip_tags($_POST['name']??''));
        $desc=htmlspecialchars(strip_tags($_POST['description']??''));
        $poster=htmlspecialchars(strip_tags($_POST['poster_url']??''));
        $pdo->prepare("UPDATE series SET category_id=?,name=?,description=?,poster_url=? WHERE id=?")->execute([$cid,$name,$desc,$poster,$id]);
        jOk();
    }
    if($act==='delete_series'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $eps=$pdo->query("SELECT stream_url FROM episodes WHERE series_id=$id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($eps as $ep){
            $p=str_replace(SERIES_URL,SERIES_DIR,$ep['stream_url']);
            if(file_exists($p)&&strpos(realpath($p),realpath(SERIES_DIR))===0)@unlink($p);
        }
        $pdo->prepare("DELETE FROM episodes WHERE series_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM series WHERE id=?")->execute([$id]);
        jOk();
    }
    if($act==='get_episodes'){
        global $pdo;
        $sid=intval($_POST['series_id']??0);
        $rows=$pdo->query("SELECT * FROM episodes WHERE series_id=$sid ORDER BY episode_number,display_order,id")->fetchAll(PDO::FETCH_ASSOC);
        jOk(['data'=>$rows]);
    }
    if($act==='add_episode'){
        global $pdo;
        $sid=intval($_POST['series_id']??0);
        $num=intval($_POST['episode_number']??1);
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $url=htmlspecialchars(strip_tags($_POST['stream_url']??''));
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??''));
        $dur=htmlspecialchars(strip_tags($_POST['duration']??''));
        if(!$sid||!$title||!$url)jErr('خاطيء في القيم المرسلة');
        $pdo->prepare("INSERT INTO episodes (series_id,episode_number,title,stream_url,subtitle_url,duration,display_order) VALUES (?,?,?,?,?,?,?)")
            ->execute([$sid,$num,$title,$url,$sub,$dur,$num]);
        jOk(['id'=>$pdo->lastInsertId()]);
    }
    if($act==='edit_episode'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $num=intval($_POST['episode_number']??1);
        $title=htmlspecialchars(strip_tags($_POST['title']??''));
        $url=htmlspecialchars(strip_tags($_POST['stream_url']??''));
        $sub=htmlspecialchars(strip_tags($_POST['subtitle_url']??''));
        $dur=htmlspecialchars(strip_tags($_POST['duration']??''));
        $new_series_id = intval($_POST['series_id']??0);
        
        if($new_series_id > 0) {
            $pdo->prepare("UPDATE episodes SET series_id=?, episode_number=?,title=?,stream_url=?,subtitle_url=?,duration=? WHERE id=?")
                ->execute([$new_series_id, $num, $title, $url, $sub, $dur, $id]);
        } else {
            $pdo->prepare("UPDATE episodes SET episode_number=?,title=?,stream_url=?,subtitle_url=?,duration=? WHERE id=?")
                ->execute([$num, $title, $url, $sub, $dur, $id]);
        }
        jOk();
    }
    if($act==='delete_episode'){
        global $pdo;
        $id=intval($_POST['id']??0);
        $ep=$pdo->query("SELECT stream_url FROM episodes WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        if($ep){
            $p=str_replace(SERIES_URL,SERIES_DIR,$ep['stream_url']);
            if(file_exists($p)&&strpos($p,SERIES_DIR)===0)@unlink($p);
        }
        $pdo->prepare("DELETE FROM episodes WHERE id=?")->execute([$id]);
        jOk();
    }

    if($act==='upload_episode_video'){
        if(empty($_FILES['episode']))jErr('لا توجد صوره');
        $f=$_FILES['episode']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['mp4','mkv','avi','mov','webm']))jErr('صيغتك للاسف لا نعمل بها');
        $sid=intval($_POST['series_id']??0);
        $n='ep_'.$sid.'_'.uniqid().'.'.$ext;
        if(!mvFile($f['tmp_name'],SERIES_DIR.$n))jErr('الخطا تم نقله للاستضافه بنسبة للاحصاء.');
        $url=SERIES_URL.$n;
        $epNum=1; if(preg_match('/[Ee]p?(\d+)|[_\s\-](\d+)\./i',$f['name'],$m)) $epNum=intval($m[1]??$m[2]??1);
        jOk(['filename'=>$n,'original'=>$f['name'],'url'=>$url,'size'=>$f['size'],'episode_number'=>$epNum]);
    }
   if($act==='upload_channel_logo'){
    if(empty($_FILES['logo']))jErr('لا توجد صورة'); 
    $f=$_FILES['logo']; 
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['jpg','jpeg','png','webp','gif']))jErr('صيغة غير صالحة.');
    
    $n=uniqid('logo_').'.'.$ext;
    if(!mvFile($f['tmp_name'],POSTERS_DIR.$n))jErr('فشل في الرفع، حاول ثانية');
    
    jOk(['filename'=>$n,'url'=>POSTERS_URL.$n,'original'=>$f['name'],'size'=>$f['size']]);
}

    if($act==='os_login'){
        $u=trim($_POST['username']??'');$p=trim($_POST['password']??'');$k=trim($_POST['api_key']??'');
        if(!$u||!$p)jErr('ادخل المطلوب!'); if(!$k)jErr('اين توكين ال api!'); $_SESSION['os_api_key']=$k;
        [$c,$r,$e]=osReq(OS_API.'/login','POST',['username'=>$u,'password'=>$p],false);
        if($e){unset($_SESSION['os_api_key']);jErr('شبكة : '.$e);}
        $d=json_decode($r,true);
        if($c===200&&!empty($d['token'])){
            $_SESSION['os_token']=$d['token'];$_SESSION['os_username']=$u; jOk(['username'=>$u,'allowed'=>$d['allowed_downloads']??'?']);
        }
        unset($_SESSION['os_api_key']);
        jErr($d['message']??$d['error']??"مشكلة رمز اتصال سيرفرهم  $c");
    }
    if($act==='os_logout'){
        if(!empty($_SESSION['os_token']))osReq(OS_API.'/logout','DELETE');
        unset($_SESSION['os_token'],$_SESSION['os_username'],$_SESSION['os_api_key']); jOk();
    }
    if($act==='search_subtitles'){
        $q=trim($_POST['query']??'');$lang=trim($_POST['language']??'ar'); if(!$q)jErr('لا يمكنك تركة خالي.');
        $params=http_build_query(['query'=>$q,'languages'=>$lang,'order_by'=>'download_count','order_direction'=>'desc','per_page'=>20]);
        [$c,$r,$e]=osReq(OS_API.'/subtitles?'.$params);
        if($e)jErr('توصيلات انقطعت: '.$e); if($c!==200)jErr("خاطئ برقم الاستعلام $c");
        $d=json_decode($r,true); if(empty($d['data']))jErr('زيروو محصلة من الاسم.');
        $subs=[];
        foreach($d['data'] as $s){
            $a=$s['attributes'];$files=$a['files']??[];if(empty($files))continue;
            $subs[]=['id'=>$s['id'],'title'=>$a['feature_details']['title']??$a['release']??'—','year'=>$a['feature_details']['year']??'','language'=>$a['language']??'','downloads'=>$a['download_count']??0,'release'=>$a['release']??'','file_id'=>$files[0]['file_id']??null,'filename'=>$files[0]['file_name']??'subtitle.srt'];
        } jOk(['data'=>$subs,'total'=>$d['total_count']??count($subs)]);
    }
    if($act==='download_subtitle'){
        $fid=intval($_POST['file_id']??0); if(!$fid)jErr('لا توجد شفرة المعرّف الخاص بالباقة.');
        [$c,$r,$e]=osReq(OS_API.'/download','POST',['file_id'=>$fid,'sub_format'=>'srt']);
        if($e)jErr('بورت سُد عليك! : '.$e);
        $d=json_decode($r,true);
        if($c!==200||empty($d['link']))jErr($d['message']??$d['errors'][0]['message']??"غير مخول حاليا لك بهذا السجل $c");
        $ch2=curl_init($d['link']);
        curl_setopt_array($ch2,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false]);
        $srt=curl_exec($ch2);$dlErr=curl_error($ch2);curl_close($ch2);
        if(!$srt||strlen(trim($srt))<5)jErr('محتواه اصبح غير مطابق وتالف.. '.$dlErr);
        $srtN=uniqid('sub_').'.srt'; file_put_contents(VID_SUB_DIR.$srtN,$srt);
        $vttN=uniqid('sub_').'.vtt'; $vtt="WEBVTT\n\n".preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/','\1.\2',$srt); file_put_contents(VID_SUB_DIR.$vttN,$vtt);
        jOk(['filename'=>$srtN,'vtt_filename'=>$vttN,'remaining'=>$d['remaining_downloads']??'?','url'=>VID_SUB_URL.$srtN,'vtt_url'=>VID_SUB_URL.$vttN]);
    }

    // ══ معالجات إدارة المستخدمين ══
    if($act==='get_admin_users'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? 'normal';
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية لعرض المستخدمين');
        $rows = $pdo->query("SELECT id, username, display_name, role, allowed_sections, is_active, created_at, last_login FROM admin_users ORDER BY FIELD(role,'administrator','super','normal','custom'), id")->fetchAll(PDO::FETCH_ASSOC);
        // Super لا يرى تفاصيل Administrator
        if($myRole === 'super') {
            $rows = array_values(array_filter($rows, function($r){ return $r['role'] !== 'administrator'; }));
        }
        jOk(['data' => $rows]);
    }

    if($act==='add_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $display_name = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'normal';
        $sections = $_POST['allowed_sections'] ?? '[]';
        if(!$username || !$password) jErr('اسم المستخدم وكلمة المرور مطلوبان');
        if(strlen($password) < 4) jErr('كلمة المرور يجب أن تكون 4 أحرف على الأقل');
        // Super لا يستطيع إنشاء Administrator
        if($myRole === 'super' && $role === 'administrator') jErr('لا يمكنك إنشاء مستخدم بصلاحية مدير عام');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->fetchColumn() > 0) jErr('اسم المستخدم مُستخدم مسبقاً');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, allowed_sections) VALUES (?, ?, ?, ?, ?)")
            ->execute([$username, $hash, $display_name ?: $username, $role, $sections]);
        $newId = $pdo->lastInsertId();
        // إدراج في جدول users أيضاً للتوافق مع login.php
        try { $pdo->prepare("INSERT IGNORE INTO users (username, password) VALUES (?, ?)")->execute([$username, $hash]); } catch(PDOException $e) {}
        jOk(['id' => $newId]);
    }

    if($act==='edit_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        $myId = $_SESSION['admin_user_id'] ?? 0;
        if(!in_array($myRole, ['administrator','super'])) jErr('ليس لديك صلاحية');
        $id = intval($_POST['id'] ?? 0);
        $display_name = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'normal';
        $sections = $_POST['allowed_sections'] ?? '[]';
        $is_active = intval($_POST['is_active'] ?? 1);
        $new_pass = $_POST['new_password'] ?? '';
        if(!$id) jErr('معرّف المستخدم مفقود');
        // جلب بيانات المستخدم المستهدف
        $target = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $target->execute([$id]);
        $targetRow = $target->fetch(PDO::FETCH_ASSOC);
        if(!$targetRow) jErr('المستخدم غير موجود');
        // Super لا يعدّل على Administrator
        if($myRole === 'super' && $targetRow['role'] === 'administrator') jErr('لا يمكنك التعديل على مدير عام');
        if($myRole === 'super' && $role === 'administrator') jErr('لا يمكنك ترقية مستخدم لمدير عام');
        // لا يمكن تعطيل نفسك
        if($id == $myId && $is_active == 0) jErr('لا يمكنك تعطيل حسابك');
        $pdo->prepare("UPDATE admin_users SET display_name=?, role=?, allowed_sections=?, is_active=? WHERE id=?")
            ->execute([$display_name, $role, $sections, $is_active, $id]);
        // تحديث كلمة المرور إن وُجدت
        if($new_pass && strlen($new_pass) >= 4){
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([$hash, $id]);
            // تحديث في users أيضاً
            $uname = $pdo->prepare("SELECT username FROM admin_users WHERE id=?");
            $uname->execute([$id]);
            $un = $uname->fetchColumn();
            if($un) try { $pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([$hash, $un]); } catch(PDOException $e) {}
        }
        jOk();
    }

    if($act==='delete_admin_user'){
        global $pdo;
        $myRole = $_SESSION['admin_role'] ?? '';
        $myId = $_SESSION['admin_user_id'] ?? 0;
        if($myRole !== 'administrator') jErr('فقط المدير العام يمكنه الحذف');
        $id = intval($_POST['id'] ?? 0);
        if(!$id) jErr('معرّف مفقود');
        if($id == $myId) jErr('لا يمكنك حذف نفسك');
        $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
        jOk();
    }

    // --- الكود الجديد الذي تم إضافته لرفع بوستر شاشتي ---
    if($act === 'upload_series_poster'){
        if(empty($_FILES['poster'])) jErr('لا توجد صورة'); 
        $f = $_FILES['poster']; 
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            jErr('صيغة غير صالحة. المدعوم: jpg, png, webp, gif');
        }
        
        $n = uniqid('poster_') . '.' . $ext;
        if(!mvFile($f['tmp_name'], POSTERS_DIR . $n)) {
            jErr('فشل في الرفع، يرجى التحقق من تصاريح المجلد');
        }
        
        jOk([
            'filename' => $n,
            'url' => POSTERS_URL . $n,
            'original' => $f['name'],
            'size' => $f['size']
        ]);
    }
    // ------------------------------------------------

        jErr('عذرا الكود المبدئ في الاستمارة لا يمت لاوامري.');
}

// ══ Categories Handlers (إدارة الأقسام) ══
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])){
    try {
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));
        $desc = htmlspecialchars(strip_tags($_POST['description'] ?? ''));
        
        $slug_new = "cat-".time()."-".rand(100,999);
        $pdo->prepare("INSERT INTO categories (name, parent_id, icon, description, slug) VALUES (?, ?, ?, ?, ?)")->execute([$name, $parent_id, $icon, $desc, $slug_new]);
        $_SESSION['success'] = '✅ تم إضافة القسم بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'خطأ قاعدة البيانات: ' . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#categories'); 
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])){
    try {
        $id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));
        
        $pdo->prepare("UPDATE categories SET name=?, parent_id=?, icon=? WHERE id=?")->execute([$name, $parent_id, $icon, $id]);
        $_SESSION['success'] = '✅ تم تعديل القسم بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'خطأ قاعدة البيانات: ' . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#categories'); 
    exit;
}

if(isset($_GET['delete_category'])){
    try {
        $id = (int)$_GET['delete_category'];
        // حذف القسم
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        $_SESSION['success'] = '✅ تم حذف القسم بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'لا يمكن الحذف (قد يكون هناك قنوات مرتبطة بهذا القسم).';
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#categories'); 
    exit;
}

// ══ Channels Handlers ══

// إنشاء جدول القنوات (في حال لم يكن موجوداً) لتفادي الأخطاء
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        stream_url VARCHAR(1000) NOT NULL,
        subtitle_url VARCHAR(1000) DEFAULT '',
        logo_icon VARCHAR(100) DEFAULT 'fas fa-tv',
        logo_url VARCHAR(1000) DEFAULT '',
        views_count INT DEFAULT 0,
        display_order INT DEFAULT 0
    )");
} catch(PDOException $e) {}

// --- ضع هذا الكود هنا (تحديث الجداول قبل عمليات الإضافة والتعديل) ---
try { $pdo->exec("ALTER TABLE channels ADD COLUMN logo_url VARCHAR(1000) DEFAULT ''"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE channels ADD COLUMN logo_icon VARCHAR(100) DEFAULT 'fas fa-tv'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN icon VARCHAR(100) DEFAULT 'fas fa-th-large'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN description TEXT"); } catch(PDOException $e) {}
// -------------------------------------------------------------------

// كود: إضافة قناة جديدة
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_channel'])){
    try {
        $cat_id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['channel_name']));
        $url = $_POST['stream_url'] ?? '';
        $icon = htmlspecialchars(strip_tags($_POST['logo_icon'] ?? 'fas fa-tv'));
        $logo = $_POST['logo_url'] ?? '';
        
        $pdo->prepare("INSERT INTO channels (category_id, name, stream_url, logo_icon, logo_url) VALUES (?, ?, ?, ?, ?)")->execute([$cat_id, $name, $url, $icon, $logo]);
        $_SESSION['success'] = '✅ تم إضافة القناة بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'خطأ قاعدة البيانات: ' . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#channels'); 
    exit;
}

// كود: تعديل قناة موجودة
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_channel'])){
    try {
        $id = (int)$_POST['channel_id'];
        $cat_id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['channel_name']));
        $url = $_POST['stream_url'] ?? '';
        $icon = htmlspecialchars(strip_tags($_POST['logo_icon'] ?? 'fas fa-tv'));
        $logo = $_POST['logo_url'] ?? '';
        
        $pdo->prepare("UPDATE channels SET category_id=?, name=?, stream_url=?, logo_icon=?, logo_url=? WHERE id=?")->execute([$cat_id, $name, $url, $icon, $logo, $id]);
        $_SESSION['success'] = '✅ تم تعديل القناة بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'خطأ قاعدة البيانات: ' . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#channels'); 
    exit;
}

// كود: حذف القناة
if(isset($_GET['delete_channel'])){
    try {
        $id = (int)$_GET['delete_channel'];
        $pdo->prepare("DELETE FROM channels WHERE id=?")->execute([$id]);
        $_SESSION['success'] = '✅ تم حذف القناة بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'حدث خطأ أثناء الحذف.';
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '#channels'); 
    exit;
}

// تحديث جدول القنوات لإضافة أعمدة الشعار
try { $pdo->exec("ALTER TABLE channels ADD COLUMN logo_url VARCHAR(1000) DEFAULT ''"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE channels ADD COLUMN logo_icon VARCHAR(100) DEFAULT 'fas fa-tv'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE channels MODIFY slug VARCHAR(255) NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN icon VARCHAR(100) DEFAULT 'fas fa-th-large'"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE categories ADD COLUMN description TEXT"); } catch(PDOException $e) {}

$stats=[];
$stats['cats']=$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$stats['channels']=$pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$stats['views']=$pdo->query("SELECT COALESCE(SUM(views_count),0) FROM channels")->fetchColumn();
try{$stats['series']=$pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();}catch(PDOException $e){$stats['series']=0;}
try{$stats['users']=$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();}catch(PDOException $e){$stats['users']=1;}

$categories=$pdo->query("SELECT c.id,c.name,c.parent_id,c.icon,c.description,COALESCE(c.display_order,0) as display_order,COUNT(ch.id) as channel_count FROM categories c LEFT JOIN channels ch ON c.id=ch.category_id GROUP BY c.id,c.name,c.parent_id,c.icon,c.description,c.display_order ORDER BY COALESCE(c.display_order,0),c.id")->fetchAll(PDO::FETCH_ASSOC);
$channels=$pdo->query("SELECT ch.*,c.name as cat_name FROM channels ch LEFT JOIN categories c ON ch.category_id=c.id ORDER BY ch.category_id,ch.display_order,ch.id")->fetchAll(PDO::FETCH_ASSOC);

$os_logged=!empty($_SESSION['os_token']);
$os_user=$_SESSION['os_username']??'';


// ══ جلب دور المستخدم الحالي ══
$_admin_role = 'normal';
$_admin_sections = [];
$_admin_user_id = 0;
$_admin_display = $_SESSION['admin_username'] ?? 'مدير';
try {
    $__au_stmt = $pdo->prepare("SELECT id, role, allowed_sections, display_name FROM admin_users WHERE username = ? AND is_active = 1");
    $__au_stmt->execute([$_SESSION['admin_username'] ?? '']);
    $__au_row = $__au_stmt->fetch(PDO::FETCH_ASSOC);
    if ($__au_row) {
        $_admin_role = $__au_row['role'];
        $_admin_user_id = $__au_row['id'];
        $_admin_display = $__au_row['display_name'] ?: ($_SESSION['admin_username'] ?? '');
        $_admin_sections = json_decode($__au_row['allowed_sections'] ?: '[]', true) ?: [];
        // تحديث وقت آخر دخول
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$__au_row['id']]);
    }
    $_SESSION['admin_role'] = $_admin_role;
    $_SESSION['admin_sections'] = $_admin_sections;
    $_SESSION['admin_user_id'] = $_admin_user_id;
} catch(PDOException $e) {}

// قائمة كل المستخدمين المسؤولين (لاستعمالها لاحقاً)
$_all_admin_users = [];
try {
    $_all_admin_users = $pdo->query("SELECT id, username, display_name, role, allowed_sections, is_active, created_at, last_login FROM admin_users ORDER BY FIELD(role,'administrator','super','normal','custom'), id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}


$all_folders_list = $pdo->query("SELECT id, name FROM series ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Shashety Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--red:#E50914;--redg:rgba(229,9,20,.35);--gold:#F5A623;--s0:#0a0a0a;--s1:#111;--s2:#1a1a1a;--s3:#242424;--s4:#2e2e2e;--br:rgba(255,255,255,.07);--brh:rgba(255,255,255,.14);--t1:#fff;--t2:#b3b3b3;--t3:#737373;--sw:260px;--th:68px;--r1:6px;--r2:12px;--r3:20px;--ease:cubic-bezier(.4,0,.2,1)}
*,::before,::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Tajawal',sans-serif;background:var(--s0);color:var(--t1);min-height:100vh;overflow-x:hidden}
a{color:inherit;text-decoration:none}
.sidebar{position:fixed;right:0;top:0;width:var(--sw);height:100vh;background:var(--s1);border-left:1px solid var(--br);display:flex;flex-direction:column;z-index:100;transition:transform .3s var(--ease)}
.sidebar::after{content:'';position:absolute;top:0;right:0;left:0;height:3px;background:var(--red)}
.sbrand{padding:26px 20px 22px;border-bottom:1px solid var(--br);display:flex;align-items:center;gap:12px}
.sbrand-icon{width:40px;height:40px;background:var(--red);border-radius:var(--r1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 0 20px var(--redg);flex-shrink:0}
.sbrand-name{font-size:1.05rem;font-weight:800}
.sbrand-sub{font-size:.65rem;color:var(--t3);text-transform:uppercase;letter-spacing:.12em}
.snav{flex:1;overflow-y:auto;padding:12px 10px}
.snav::-webkit-scrollbar{width:3px}.snav::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.snl{font-size:.62rem;font-weight:700;color:var(--t3);letter-spacing:.15em;text-transform:uppercase;padding:14px 12px 6px}
.si{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:var(--r1);color:var(--t2);font-size:.875rem;font-weight:500;cursor:pointer;border:none;background:none;width:100%;text-align:right;transition:all .18s var(--ease);position:relative;margin-bottom:1px}
.si:hover{background:var(--s3);color:var(--t1)}
.si.on{background:rgba(229,9,20,.12);color:var(--t1)}
.si.on::before{content:'';position:absolute;right:0;top:20%;bottom:20%;width:3px;background:var(--red);border-radius:2px 0 0 2px}
.si.on .si-ic{color:var(--red)}
.si-ic{width:17px;text-align:center;font-size:.8rem;flex-shrink:0}
.sfoot{padding:14px 10px;border-top:1px solid var(--br)}
.slogout{display:flex;align-items:center;gap:11px;width:100%;padding:10px 12px;background:transparent;border:1px solid var(--br);border-radius:var(--r1);color:var(--t2);font-family:'Tajawal',sans-serif;font-size:.875rem;font-weight:500;cursor:pointer;transition:all .18s;text-align:right}
.slogout:hover{background:rgba(229,9,20,.08);border-color:rgba(229,9,20,.35);color:#ff6b6b}
.main{margin-right:var(--sw);min-height:100vh;display:flex;flex-direction:column;transition:margin .3s var(--ease)}
.topbar{height:var(--th);background:rgba(10,10,10,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--br);display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:90}
.tbtitle{font-size:1rem;font-weight:700}
.tbr{display:flex;align-items:center;gap:18px}
.mob-menu-btn{display:none;width:36px;height:36px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);color:var(--t2);font-size:.9rem;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99;backdrop-filter:blur(3px)}
.sb-overlay.on{display:block}
.lic-b{display:flex;align-items:center;gap:9px;background:var(--s2);border:1px solid var(--br);border-radius:100px;padding:5px 14px 5px 10px;font-size:.78rem;color:var(--t2)}
.lic-dot{width:7px;height:7px;background:#00D084;border-radius:50%;box-shadow:0 0 8px #00D084}
.uavt{width:34px;height:34px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;box-shadow:0 0 12px var(--redg);flex-shrink:0}
.pcont{flex:1;padding:32px;max-width:1440px;width:100%}
.sec{display:none}.sec.on{display:block;animation:fu .3s var(--ease) both}
@keyframes fu{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.shdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:10px}
.stitle{font-size:1.5rem;font-weight:800;letter-spacing:-.02em}
.stitle span{color:var(--red)}
.al{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:var(--r2);margin-bottom:20px;font-size:.875rem;font-weight:600;animation:sd .3s var(--ease)}
@keyframes sd{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.al-s{background:rgba(0,208,132,.1);border:1px solid rgba(0,208,132,.3);color:#00D084}
.al-e{background:rgba(229,9,20,.1);border:1px solid rgba(229,9,20,.3);color:#ff6b6b}
.al-i{background:rgba(76,201,240,.1);border:1px solid rgba(76,201,240,.3);color:#4CC9F0}
.sgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:28px}
.sc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);padding:22px;transition:border-color .2s,transform .2s}
.sc:hover{border-color:var(--brh);transform:translateY(-2px)}
.sc-ic{width:38px;height:38px;border-radius:var(--r1);display:flex;align-items:center;justify-content:center;font-size:.95rem;margin-bottom:14px}
.r .sc-ic{background:rgba(229,9,20,.14);color:var(--red)}
.g .sc-ic{background:rgba(0,208,132,.14);color:#00D084}
.go .sc-ic{background:rgba(245,166,35,.14);color:var(--gold)}
.b .sc-ic{background:rgba(76,201,240,.14);color:#4CC9F0}
.p .sc-ic{background:rgba(179,107,255,.14);color:#B36BFF}
.sc-v{font-size:2rem;font-weight:900;line-height:1;margin-bottom:3px}
.sc-l{font-size:.74rem;color:var(--t3);font-weight:500;text-transform:uppercase;letter-spacing:.05em}
.dgrid{display:grid;grid-template-columns:1fr 310px;gap:18px}
.card{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden}
.chdr{padding:18px 22px;border-bottom:1px solid var(--br);display:flex;align-items:center;justify-content:space-between}
.ctitle{font-size:.9rem;font-weight:700}
.cbody{padding:18px 22px}
.qa-list{display:flex;flex-direction:column;gap:7px}
.qa{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;transition:all .18s var(--ease);color:var(--t2);font-size:.855rem;font-weight:500}
.qa:hover{background:var(--s3);border-color:var(--brh);color:var(--t1);transform:translateX(-2px)}
.qa-ic{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0}
.qa.r .qa-ic{background:rgba(229,9,20,.14);color:var(--red)}
.qa.g .qa-ic{background:rgba(0,208,132,.14);color:#00D084}
.qa.b .qa-ic{background:rgba(76,201,240,.14);color:#4CC9F0}
.qa.go .qa-ic{background:rgba(245,166,35,.14);color:var(--gold)}
.qa.p .qa-ic{background:rgba(179,107,255,.14);color:#B36BFF}
.ri{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--br)}
.ri:last-child{border-bottom:none}
.ri-ic{width:36px;height:36px;background:var(--s3);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--t3);flex-shrink:0}
.ri-name{font-size:.855rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ri-meta{font-size:.74rem;color:var(--t3);margin-top:1px}
.tw{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden}
.tt{padding:14px 18px;border-bottom:1px solid var(--br);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tsrch{display:flex;align-items:center;gap:7px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);padding:7px 12px;flex:1;max-width:260px}
.tsrch i{color:var(--t3);font-size:.75rem}
.tsrch input{background:none;border:none;outline:none;color:var(--t1);font-family:'Tajawal',sans-serif;font-size:.855rem;flex:1;min-width:0}
table{width:100%;border-collapse:collapse}
thead tr{background:var(--s2)}
th{padding:11px 14px;font-size:.7rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em;text-align:right;white-space:nowrap}
td{padding:12px 14px;font-size:.855rem;color:var(--t2);border-top:1px solid var(--br);vertical-align:middle}
tr:hover td{background:rgba(255,255,255,.02)}
.cn{display:flex;align-items:center;gap:10px}
.nic{width:34px;height:34px;background:var(--s3);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:var(--t2);flex-shrink:0}
.bdg{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;font-size:.72rem;font-weight:600}
.bc{background:rgba(76,201,240,.1);color:#4CC9F0;border:1px solid rgba(76,201,240,.2)}
.bp{background:rgba(179,107,255,.1);color:#B36BFF;border:1px solid rgba(179,107,255,.2)}
.bg{background:rgba(0,208,132,.1);color:#00D084;border:1px solid rgba(0,208,132,.2)}
.acts{display:flex;align-items:center;gap:5px;white-space:nowrap}
.ib{width:30px;height:30px;border-radius:var(--r1);border:1px solid var(--br);background:var(--s2);color:var(--t2);font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
.ib:hover{background:var(--s3);border-color:var(--brh);color:var(--t1)}
.ib.pl:hover{background:rgba(0,208,132,.12);color:#00D084;border-color:rgba(0,208,132,.3)}
.ib.ed:hover{background:rgba(245,166,35,.12);color:var(--gold);border-color:rgba(245,166,35,.3)}
.ib.dl:hover{background:rgba(229,9,20,.12);color:var(--red);border-color:rgba(229,9,20,.3)}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--r1);font-family:'Tajawal',sans-serif;font-size:.855rem;font-weight:700;cursor:pointer;border:none;transition:all .18s var(--ease);white-space:nowrap}
.btn-p{background:var(--red);color:#fff;box-shadow:0 4px 14px var(--redg)}
.btn-p:hover{background:#f01020;transform:translateY(-1px)}
.btn-p:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-g{background:var(--s3);color:var(--t2);border:1px solid var(--br)}
.btn-g:hover{background:var(--s4);color:var(--t1)}
.btn-s{background:#00D084;color:#fff}
.btn-s:hover{background:#00b872;transform:translateY(-1px)}
.btn-b{background:rgba(76,201,240,.14);color:#4CC9F0;border:1px solid rgba(76,201,240,.25)}
.btn-b:hover{background:rgba(76,201,240,.25)}
.btn-v{background:rgba(179,107,255,.14);color:#B36BFF;border:1px solid rgba(179,107,255,.25)}
.btn-v:hover{background:rgba(179,107,255,.25)}
.bsm{padding:6px 12px;font-size:.78rem}
.srgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:10px}
.src{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden;display:flex;transition:all .18s var(--ease)}
.src:hover{border-color:var(--brh);transform:translateY(-1px)}
.src-poster{width:56px;height:68px;background:var(--s3);display:flex;align-items:center;justify-content:center;color:var(--t3);font-size:1.4rem;overflow:hidden;flex-shrink:0}
.src-poster img{width:100%;height:100%;object-fit:cover}
.src-body{flex:1;padding:9px 12px;min-width:0;cursor:pointer}
.src-name{font-size:.88rem;font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.src-meta{font-size:.74rem;color:var(--t3);display:flex;gap:8px;flex-wrap:wrap}
.src-acts{display:flex;flex-direction:column;gap:3px;padding:7px;justify-content:center;border-right:1px solid var(--br)}
.uz{border:2px dashed var(--br);border-radius:var(--r2);padding:36px 20px;text-align:center;cursor:pointer;transition:all .22s;position:relative}
.uz.dz{border-color:var(--red);background:rgba(229,9,20,.04)}
.uz:hover{border-color:var(--brh)}
.uz i{font-size:2rem;color:var(--t3);margin-bottom:12px;display:block;transition:color .2s}
.uz:hover i{color:var(--red)}
.uz h3{font-size:.95rem;font-weight:700;margin-bottom:5px}
.uz p{font-size:.78rem;color:var(--t3)}
.uz input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.pw{background:var(--s3);border-radius:100px;height:5px;overflow:hidden}
.pb{height:100%;background:var(--red);border-radius:100px;width:0;transition:width .3s}
.fg{margin-bottom:18px}
.fl{display:block;font-size:.78rem;font-weight:700;color:var(--t2);letter-spacing:.05em;text-transform:uppercase;margin-bottom:7px}
.fi{width:100%;padding:10px 13px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);color:var(--t1);font-family:'Tajawal',sans-serif;font-size:.875rem;outline:none;transition:border-color .18s,box-shadow .18s}
.fi:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(229,9,20,.1)}
.fi::placeholder{color:var(--t3)}
.fs{width:100%;padding:10px 13px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);color:var(--t1);font-family:'Tajawal',sans-serif;font-size:.875rem;cursor:pointer;outline:none}
.fs:focus{border-color:var(--red)}
.fs option{background:var(--s2)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.mbd{position:fixed;inset:0;background:rgba(0,0,0,.88);backdrop-filter:blur(8px);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.mbd.op{display:flex}
.mbox{background:var(--s1);border:1px solid var(--brh);border-radius:var(--r3);width:100%;max-width:560px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column;animation:mp .28s var(--ease);box-shadow:0 40px 80px rgba(0,0,0,.7)}
.mbox.w{max-width:660px}
.mbox.xw{max-width:820px}
@keyframes mp{from{opacity:0;transform:scale(.92) translateY(18px)}to{opacity:1;transform:scale(1) translateY(0)}}
.mhd{padding:18px 22px;border-bottom:1px solid var(--br);display:flex;align-items:center;justify-content:space-between;background:var(--s2);flex-shrink:0}
.mhd-title{font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:9px}
.mhd-title i{color:var(--red)}
.mclose{width:30px;height:30px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;transition:all .15s}
.mclose:hover{background:rgba(229,9,20,.12);color:var(--red)}
.mbody{padding:22px;overflow-y:auto;flex:1}
.mbody::-webkit-scrollbar{width:3px}.mbody::-webkit-scrollbar-thumb{background:var(--s4)}
.mfooter{padding:16px 22px;border-top:1px solid var(--br);display:flex;gap:8px;justify-content:flex-end;background:var(--s2);flex-shrink:0}
.vsteps{display:flex;border-radius:var(--r2);overflow:hidden;border:1px solid var(--br);margin-bottom:24px}
.vs{flex:1;display:flex;align-items:center;gap:9px;padding:13px 16px;background:var(--s1);color:var(--t3);font-size:.83rem;font-weight:700;border-left:1px solid var(--br);transition:all .2s;position:relative;cursor:default}
.vs:last-child{border-left:none}
.vs.done{color:#00D084}
.vs.act{background:rgba(229,9,20,.09);color:var(--t1)}
.vs.act::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--red)}
.vs-n{width:24px;height:24px;border-radius:50%;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0}
.vs.act .vs-n{background:var(--red)}.vs.done .vs-n{background:#00D084;color:#000}
.vp{display:none}.vp.act{display:block;animation:fu .3s var(--ease) both}
.vc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden;margin-bottom:18px}
.vchd{padding:14px 20px;border-bottom:1px solid var(--br);display:flex;align-items:center;justify-content:space-between;background:var(--s2)}
.vchd-title{font-size:.875rem;font-weight:700;display:flex;align-items:center;gap:9px}
.vchd-title i{color:var(--red)}
.vcbody{padding:20px}
.chip{display:none;align-items:center;gap:10px;padding:12px 14px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.22);border-radius:var(--r1);margin-top:12px}
.sub-opts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.so{background:var(--s2);border:2px solid var(--br);border-radius:var(--r2);padding:16px;cursor:pointer;transition:all .2s;text-align:center}
.so:hover{border-color:var(--brh)}
.so.sel{border-color:var(--red);background:rgba(229,9,20,.07)}
.so-ic{font-size:1.5rem;margin-bottom:8px}
.so-lbl{font-size:.855rem;font-weight:700;margin-bottom:2px}
.so-desc{font-size:.72rem;color:var(--t3)}
.srow{display:flex;gap:7px}
.sinp{flex:1;display:flex;align-items:center;gap:7px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);padding:9px 12px;transition:border-color .18s;min-width:0}
.sinp:focus-within{border-color:var(--red)}
.sinp i{color:var(--t3);font-size:.75rem}
.sinp input{background:none;border:none;outline:none;color:var(--t1);font-family:'Tajawal',sans-serif;font-size:.855rem;flex:1;min-width:0}
.lsel{padding:9px 11px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);color:var(--t1);font-family:'Tajawal',sans-serif;font-size:.78rem;cursor:pointer;outline:none}
.sub-rl{display:none;flex-direction:column;gap:6px;margin-top:12px;max-height:360px;overflow-y:auto}
.sri{display:flex;align-items:center;gap:9px;padding:10px 12px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;transition:all .15s}
.sri:hover{border-color:var(--brh);background:var(--s3)}
.sri.sel{border-color:#00D084;background:rgba(0,208,132,.07)}
.sri-main{flex:1;min-width:0}
.sri-title{font-weight:700;font-size:.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sri-meta{font-size:.72rem;color:var(--t3);margin-top:2px;display:flex;gap:6px;flex-wrap:wrap}
.stag{display:inline-flex;padding:1px 6px;border-radius:100px;font-size:.66rem;font-weight:700}
.stag-l{background:rgba(76,201,240,.12);color:#4CC9F0}
.stag-ai{background:rgba(179,107,255,.12);color:#B36BFF}
.sub-chip{display:none;align-items:center;gap:9px;padding:10px 13px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.25);border-radius:var(--r1);margin-top:10px;font-size:.83rem}
.sub-chip i{color:#00D084}
.merge-sum{background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);padding:14px 16px;margin-bottom:16px}
.mr{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--br)}
.mr:last-child{border-bottom:none}
.ml{font-size:.73rem;color:var(--t3);font-weight:700;width:75px;flex-shrink:0;text-transform:uppercase}
.mv{font-size:.855rem;color:var(--t1);flex:1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vnavb{display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid var(--br)}
.ffnote{background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.2);border-radius:var(--r1);padding:12px 16px;margin-bottom:14px;font-size:.82rem;color:var(--t2);display:none}
.ffnote i{color:#00D084;margin-left:6px}
.vmgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.vmc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden;display:flex;flex-direction:column;transition:border-color .18s,transform .18s}
.vmc:hover{border-color:var(--brh);transform:translateY(-2px)}
.vmt{position:relative;background:#000;aspect-ratio:16/9;overflow:hidden;cursor:pointer;display:flex;align-items:center;justify-content:center}
.vmt video{width:100%;height:100%;object-fit:cover;opacity:.65;pointer-events:none}
.vmt-ic{position:absolute;width:44px;height:44px;background:rgba(229,9,20,.85);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem}
.vmbdg{position:absolute;top:7px;right:7px;font-size:.62rem;font-weight:800;padding:2px 8px;border-radius:100px;text-transform:uppercase}
.vminfo{padding:12px 14px;flex:1}
.vmname{font-size:.875rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
.vmmeta{font-size:.72rem;color:var(--t3);display:flex;gap:9px}
.vmacts{display:flex;gap:5px;padding:9px 14px;border-top:1px solid var(--br);background:var(--s2)}
.vmb{flex:1;display:flex;align-items:center;justify-content:center;gap:4px;padding:7px 5px;border-radius:var(--r1);border:1px solid var(--br);background:var(--s3);color:var(--t2);font-family:'Tajawal',sans-serif;font-size:.74rem;font-weight:700;cursor:pointer;transition:all .15s}
.vmb.pl:hover{background:rgba(0,208,132,.12);color:#00D084;border-color:rgba(0,208,132,.3)}
.vmb.sv:hover{background:rgba(229,9,20,.12);color:var(--red);border-color:rgba(229,9,20,.3)}
.vmb.dl:hover{background:rgba(229,9,20,.12);color:var(--red);border-color:rgba(229,9,20,.3)}
.vmb.sub:hover{background:rgba(0,208,132,.12);color:#00D084;border-color:rgba(0,208,132,.3)}
.vmb.mv:hover{background:rgba(245,166,35,.12);color:var(--gold);border-color:rgba(245,166,35,.3)}
#pm{position:fixed;inset:0;background:rgba(0,0,0,.97);backdrop-filter:blur(24px);z-index:2000;display:none;align-items:center;justify-content:center;padding:20px}
#pm.op{display:flex;animation:fi .22s var(--ease)}
@keyframes fi{from{opacity:0}to{opacity:1}}
.pbox{background:#000;border-radius:var(--r3);overflow:hidden;width:100%;max-width:1040px;border:1px solid var(--brh);box-shadow:0 0 80px rgba(229,9,20,.2),0 40px 80px rgba(0,0,0,.8)}
.phd{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--s1);border-bottom:1px solid var(--br);gap:10px}
.phd-l{display:flex;align-items:center;gap:9px;flex:1;min-width:0}
.ptitle{font-size:.875rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pdot{width:7px;height:7px;background:var(--red);border-radius:50%;box-shadow:0 0 8px var(--red);animation:bk 1.5s ease infinite;flex-shrink:0}
.pdot.ok{background:#00D084;animation:none}.pdot.err{background:#f44;animation:none}
@keyframes bk{0%,100%{opacity:1}50%{opacity:.25}}
.pwrap{position:relative;background:#000}
#tv{width:100%;height:500px;display:block;background:#000}
.pload{position:absolute;inset:0;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;z-index:5;transition:opacity .3s}
.pload.hid{opacity:0;pointer-events:none}
.pspin{width:44px;height:44px;border:3px solid rgba(229,9,20,.2);border-top-color:var(--red);border-radius:50%;animation:sp .8s linear infinite}
@keyframes sp{to{transform:rotate(360deg)}}
.perr{position:absolute;inset:0;background:#000;display:none;flex-direction:column;align-items:center;justify-content:center;gap:12px;z-index:6;text-align:center;padding:40px}
.perr.sh{display:flex}
.pft{display:flex;align-items:center;justify-content:space-between;padding:9px 18px;background:var(--s1);border-top:1px solid var(--br);gap:10px;flex-wrap:wrap}
.purl{font-size:.7rem;color:var(--t3);font-family:'Courier New',monospace;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pbtns{display:flex;gap:7px}
.pbtn{display:flex;align-items:center;gap:5px;padding:5px 11px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);color:var(--t2);font-family:'Tajawal',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s}
.pbtn:hover{background:var(--s4);color:var(--t1)}
.psubbar{display:flex;align-items:center;gap:7px;padding:7px 18px;background:rgba(76,201,240,.05);border-top:1px solid rgba(76,201,240,.12);font-size:.75rem;color:var(--t2)}
.tgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.tc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);padding:24px 20px;cursor:pointer;transition:all .2s}
.tc:hover{border-color:var(--brh);transform:translateY(-2px)}
.tc-ic{width:42px;height:42px;border-radius:var(--r1);display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:14px}
.tc.r .tc-ic{background:rgba(229,9,20,.14);color:var(--red)}
.tc.g .tc-ic{background:rgba(0,208,132,.14);color:#00D084}
.tc.go .tc-ic{background:rgba(245,166,35,.14);color:var(--gold)}
.tc.b .tc-ic{background:rgba(76,201,240,.14);color:#4CC9F0}
.tc.p .tc-ic{background:rgba(179,107,255,.14);color:#B36BFF}
.tc-name{font-size:.95rem;font-weight:700;margin-bottom:5px}
.tc-desc{font-size:.8rem;color:var(--t3);line-height:1.5}
.sw-wrap{max-width:540px}
.swc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden;margin-bottom:18px}
.swc-hd{padding:18px 22px;border-bottom:1px solid var(--br)}
.swc-title{font-size:.9rem;font-weight:700}
.swc-body{padding:22px}
.info-b{background:rgba(245,166,35,.07);border:1px solid rgba(245,166,35,.18);border-radius:var(--r1);padding:14px;margin-top:18px}
.info-b-title{font-size:.78rem;font-weight:700;color:var(--gold);margin-bottom:7px}
.bkgrid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.bkc{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);padding:24px}
.bkc-title{font-size:.95rem;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:9px}
.bkc-title i{color:var(--red)}
.ep-item{display:flex;align-items:center;gap:9px;padding:9px 11px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);font-size:.8rem;margin-bottom:6px}
.ep-nbdg{width:26px;height:26px;border-radius:50%;background:var(--red);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0}
.ep-stat{margin-right:auto;font-size:.72rem}
.ep-stat.ok{color:#00D084}.ep-stat.err{color:#ff6b6b}.ep-stat.up{color:var(--gold)}
.os-info{background:rgba(76,201,240,.06);border:1px solid rgba(76,201,240,.18);border-radius:var(--r1);padding:11px 14px;margin-bottom:12px;font-size:.78rem;color:var(--t2);line-height:1.7}
.empty{text-align:center;padding:50px 20px;color:var(--t3)}
.empty i{font-size:2.2rem;margin-bottom:10px;display:block}
.sp{display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;vertical-align:middle}
.sp-g{border-color:rgba(0,208,132,.3);border-top-color:#00D084}
.orsep{display:flex;align-items:center;gap:9px;margin:12px 0;color:var(--t3);font-size:.75rem}
.orsep::before,.orsep::after{content:'';flex:1;height:1px;background:var(--br)}
.etabs{display:flex;background:var(--s2);padding:3px;border-radius:var(--r1);margin-bottom:14px}
.etab{flex:1;padding:7px 10px;background:none;border:none;border-radius:6px;color:var(--t3);font-family:'Tajawal',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .15s}
.etab.on{background:var(--s4);color:var(--t1)}
.tmdb-results{position:absolute;z-index:500;background:var(--s2);border:1px solid var(--brh);border-radius:var(--r2);width:100%;max-height:220px;overflow-y:auto;display:none;box-shadow:0 8px 24px rgba(0,0,0,.6);top:calc(100% + 4px);right:0}
.tmdb-item{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--br);transition:background .15s}
.tmdb-item:last-child{border-bottom:none}
.tmdb-item:hover{background:var(--s3)}
.tmdb-item img{width:34px;height:48px;object-fit:cover;border-radius:4px;flex-shrink:0;background:var(--s3)}
.tmdb-item-info{flex:1;min-width:0}
.tmdb-item-title{font-size:.83rem;font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tmdb-item-year{font-size:.72rem;color:var(--t3)}
.tmdb-fetch-btn{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(245,166,35,.15);border:1px solid rgba(245,166,35,.3);border-radius:var(--r1);color:var(--gold);font-family:'Tajawal',sans-serif;font-size:.72rem;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0}
.tmdb-fetch-btn:hover{background:rgba(245,166,35,.28)}
.fg-rel{position:relative}
.tmdb-poster-prev{display:none;margin-top:8px}
.tmdb-poster-prev img{height:80px;border-radius:var(--r1);border:2px solid rgba(0,208,132,.3)}
.image-preview{display:none;margin-top:8px}
.image-preview img{width:80px;height:80px;object-fit:cover;border-radius:var(--r1);border:2px solid var(--br)}
.image-upload-row{display:flex;gap:8px;align-items:flex-start;margin-bottom:8px}
.upload-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);transition:all .15s;white-space:nowrap}
.upload-btn:hover{border-color:var(--brh);color:var(--t1)}
.upload-btn i{color:var(--red)}

.tmdb-info-wrap { display: flex; gap: 16px; flex-wrap: wrap; }
.tmdb-info-poster { width: 130px; border-radius: var(--r2); flex-shrink: 0; border: 1px solid var(--br); background: var(--s3); }
.tmdb-info-details { flex: 1; min-width: 200px; }
.tmdb-info-title { font-size: 1.2rem; font-weight: 800; color: var(--t1); margin-bottom: 8px; }
.tmdb-info-meta { font-size: 0.85rem; color: var(--t3); margin-bottom: 12px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.tmdb-info-overview { font-size: 0.9rem; color: var(--t2); line-height: 1.7; background: var(--s2); padding: 14px; border-radius: var(--r1); border: 1px solid var(--br); }
.tmdb-info-btn { background: rgba(76,201,240,.1); color: #4CC9F0; border: 1px solid rgba(76,201,240,.2); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0; margin-right: auto; }
.tmdb-info-btn:hover { background: #4CC9F0; color: #000; transform: scale(1.05); }

@media(max-width:1024px){:root{--sw:240px}.sgrid{grid-template-columns:repeat(3,1fr)}.dgrid{grid-template-columns:1fr}.tgrid{grid-template-columns:repeat(2,1fr)}.bkgrid{grid-template-columns:1fr}#tv{height:380px}}
@media(max-width:768px){:root{--sw:260px;--th:58px}.sidebar{transform:translateX(100%);box-shadow:none}.sidebar.open{transform:translateX(0);box-shadow:-20px 0 60px rgba(0,0,0,.8)}.main{margin-right:0}.mob-menu-btn{display:flex}.topbar{padding:0 16px;gap:10px}.tbtitle{font-size:.9rem}.lic-b{display:none}.tbr{gap:10px}.tbr > span{display:none}.pcont{padding:16px}.sgrid{grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:20px}.sc{padding:16px}.sc-v{font-size:1.6rem}.dgrid{grid-template-columns:1fr;gap:14px}.stitle{font-size:1.2rem}.tw{overflow-x:auto}table{min-width:600px}.row2{grid-template-columns:1fr}.tgrid{grid-template-columns:repeat(2,1fr);gap:10px}.tc{padding:18px 14px}.bkgrid{grid-template-columns:1fr;gap:14px}.sw-wrap{max-width:100%}.srgrid{grid-template-columns:1fr}.vsteps{flex-direction:column}.vs{border-left:none;border-bottom:1px solid var(--br)}.vs:last-child{border-bottom:none}.sub-opts{grid-template-columns:1fr}#tv{height:240px}.pbox{border-radius:var(--r2)}.pft{flex-direction:column;align-items:flex-start;gap:8px}.pbtns{flex-wrap:wrap}.mbd{padding:10px}.mbox,.mbox.w,.mbox.xw{max-width:100%;max-height:96vh;border-radius:var(--r2)}.mbody{padding:16px}.mhd{padding:14px 16px}.mfooter{padding:12px 16px}.shdr{flex-direction:row;flex-wrap:wrap;gap:8px}.shdr > div{flex-wrap:wrap}#srFilterBar{flex-direction:column;align-items:stretch}#srFilterBar select,#srFilterBar .tsrch{max-width:100%;width:100%}.srow{flex-wrap:wrap}.lsel{width:100%}.vmgrid{grid-template-columns:1fr}.tt{flex-direction:column;align-items:stretch;gap:8px}.tsrch{max-width:100%}#srBreadcrumb{flex-wrap:wrap}}
@media(max-width:480px){.sgrid{grid-template-columns:1fr 1fr;gap:8px}.sc{padding:14px 12px}.sc-v{font-size:1.4rem}.sc-ic{width:32px;height:32px;font-size:.8rem;margin-bottom:10px}.tgrid{grid-template-columns:1fr 1fr}.tc{padding:14px 10px}.tc-ic{width:36px;height:36px;margin-bottom:10px}.btn{font-size:.78rem;padding:8px 12px;gap:5px}.stitle{font-size:1.1rem}}
/* ═══ Multi-Source Search (TMDB/AniList/OMDb) ═══ */
.source-tabs{display:flex;background:var(--s2);padding:3px;border-radius:var(--r1);margin-bottom:14px;border:1px solid var(--br)}
.source-tab{flex:1;padding:8px 10px;background:none;border:none;border-radius:5px;color:var(--t3);font-family:'Tajawal',sans-serif;font-size:.78rem;font-weight:700;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:6px}
.source-tab:hover{color:var(--t2);background:var(--s3)}
.source-tab.active{color:var(--t1);background:var(--s4)}
.source-tab.active.tmdb-active{background:rgba(1,180,228,.15);color:#01B4E4}
.source-tab.active.anilist-active{background:rgba(76,201,240,.15);color:#4CC9F0}
.source-tab.active.omdb-active{background:rgba(245,166,35,.15);color:var(--gold)}
.source-tab i{font-size:.7rem}
.media-search-wrap{position:relative}
.media-search-row{display:flex;gap:8px;align-items:center}
.media-search-row .fi{flex:1}
.media-search-results{position:absolute;z-index:500;background:var(--s2);border:1px solid var(--brh);border-radius:var(--r2);width:100%;max-height:260px;overflow-y:auto;display:none;box-shadow:0 8px 24px rgba(0,0,0,.6);top:calc(100% + 4px);right:0}
.media-search-results::-webkit-scrollbar{width:3px}.media-search-results::-webkit-scrollbar-thumb{background:var(--s4)}
.media-result-item{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--br);transition:background .15s}
.media-result-item:last-child{border-bottom:none}
.media-result-item:hover{background:var(--s3)}
.media-result-item img{width:36px;height:50px;object-fit:cover;border-radius:4px;flex-shrink:0;background:var(--s3)}
.media-result-info{flex:1;min-width:0}
.media-result-title{font-size:.83rem;font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.media-result-meta{font-size:.72rem;color:var(--t3);display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.source-badge{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:100px;font-size:.62rem;font-weight:800}
.source-badge.tmdb{background:rgba(1,180,228,.12);color:#01B4E4}
.source-badge.anilist{background:rgba(76,201,240,.12);color:#4CC9F0}
.source-badge.omdb{background:rgba(245,166,35,.12);color:var(--gold)}

/* ═══ User Management ═══ */
.usr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.usr-card{background:var(--s1);border:1px solid var(--br);border-radius:var(--r2);overflow:hidden;transition:all .18s}
.usr-card:hover{border-color:var(--brh);transform:translateY(-2px)}
.usr-card-hd{padding:16px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--br)}
.usr-avt{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;flex-shrink:0}
.usr-avt.admin{background:rgba(229,9,20,.2);color:var(--red)}
.usr-avt.super{background:rgba(245,166,35,.2);color:var(--gold)}
.usr-avt.normal{background:rgba(76,201,240,.2);color:#4CC9F0}
.usr-avt.custom{background:rgba(179,107,255,.2);color:#B36BFF}
.usr-name{font-size:.92rem;font-weight:700;color:var(--t1)}
.usr-uname{font-size:.72rem;color:var(--t3)}
.usr-role-bdg{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:100px;font-size:.68rem;font-weight:800}
.usr-role-bdg.admin{background:rgba(229,9,20,.12);color:var(--red);border:1px solid rgba(229,9,20,.25)}
.usr-role-bdg.super{background:rgba(245,166,35,.12);color:var(--gold);border:1px solid rgba(245,166,35,.25)}
.usr-role-bdg.normal{background:rgba(76,201,240,.12);color:#4CC9F0;border:1px solid rgba(76,201,240,.25)}
.usr-role-bdg.custom{background:rgba(179,107,255,.12);color:#B36BFF;border:1px solid rgba(179,107,255,.25)}
.usr-card-body{padding:14px 18px}
.usr-meta{font-size:.74rem;color:var(--t3);display:flex;flex-direction:column;gap:4px}
.usr-meta span i{width:18px;text-align:center;margin-left:4px}
.usr-card-ft{display:flex;gap:5px;padding:10px 18px;border-top:1px solid var(--br);background:var(--s2)}
.usr-inactive{opacity:.5;filter:grayscale(.6)}
.perm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:12px}
.perm-item{display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--s2);border:2px solid var(--br);border-radius:var(--r1);cursor:pointer;transition:all .18s;user-select:none}
.perm-item:hover{border-color:var(--brh);background:var(--s3)}
.perm-item.on{border-color:#00D084;background:rgba(0,208,132,.06)}
.perm-item .pi-ic{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.8rem;background:var(--s3);color:var(--t3);transition:all .18s;flex-shrink:0}
.perm-item.on .pi-ic{background:rgba(0,208,132,.15);color:#00D084}
.perm-item .pi-chk{width:18px;height:18px;border-radius:4px;border:2px solid var(--br);display:flex;align-items:center;justify-content:center;font-size:.65rem;color:transparent;transition:all .18s;margin-right:auto;flex-shrink:0}
.perm-item.on .pi-chk{border-color:#00D084;background:#00D084;color:#fff}
.perm-item .pi-name{font-size:.82rem;font-weight:600;color:var(--t2)}
.perm-item.on .pi-name{color:var(--t1)}
</style>
</head>
<body>
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sbrand"><div class="sbrand-icon"><i class="fas fa-satellite-dish"></i></div><div><div class="sbrand-name">Shashety IPTV</div><div class="sbrand-sub">Control Center</div></div></div>
  <nav class="snav">
    <div class="snl">الرئيسية</div>
    <button class="si on" onclick="S('dashboard');closeSidebar()"><span class="si-ic"><i class="fas fa-home"></i></span>لوحة التحكم</button>
    <div class="snl">المحتوى</div>
    <button class="si" onclick="S('categories');closeSidebar()"><span class="si-ic"><i class="fas fa-th-large"></i></span>الأقسام</button>
    <button class="si" onclick="S('channels');closeSidebar()"><span class="si-ic"><i class="fas fa-tv"></i></span>القنوات</button>
    <button class="si" onclick="S('series');loadSeries();closeSidebar()"><span class="si-ic"><i class="fas fa-film"></i></span>شاشتي</button>
    <button class="si" onclick="S('vupload');closeSidebar()"><span class="si-ic"><i class="fas fa-cloud-upload-alt"></i></span>رفع الأفلام</button>
    <button class="si" onclick="S('vmanage');vmLoad();closeSidebar()"><span class="si-ic"><i class="fas fa-photo-video"></i></span>إدارة الفيديوهات</button>
    <div class="snl">الإدارة</div>
    <button class="si" onclick="S('api-settings');closeSidebar()"><span class="si-ic"><i class="fas fa-plug"></i></span>إعدادات API</button>
    <button class="si" onclick="S('site-settings');closeSidebar()"><span class="si-ic"><i class="fas fa-cog"></i></span>إعدادات الموقع</button>
    <button class="si" onclick="S('change-password');closeSidebar()"><span class="si-ic"><i class="fas fa-key"></i></span>كلمة المرور</button>
    <button class="si" onclick="S('system-tools');closeSidebar()"><span class="si-ic"><i class="fas fa-tools"></i></span>صيانة النظام</button>
    <button class="si" onclick="S('backup');closeSidebar()"><span class="si-ic"><i class="fas fa-database"></i></span>النسخ الاحتياطي</button>
    <button class="si" onclick="S('users');loadUsers();closeSidebar()"><span class="si-ic"><i class="fas fa-users-cog"></i></span>إدارة المستخدمين</button>
  </nav>
  <div class="sfoot"><button class="slogout" onclick="if(confirm('تسجيل الخروج؟'))location.href='logout.php'"><i class="fas fa-sign-out-alt" style="color:var(--red)"></i>تسجيل الخروج</button></div>
</aside>

<div class="main">
<header class="topbar">
  <button class="mob-menu-btn" onclick="toggleSidebar()" aria-label="القائمة"><i class="fas fa-bars"></i></button>
  <span class="tbtitle" id="tbTitle">لوحة التحكم</span>
  <div class="tbr">
    <div class="lic-b"><span class="lic-dot"></span><?php $lt=htmlspecialchars($_SESSION['license_info']['license_type_name']??'نشطة');$dl=$_SESSION['license_days_left']??0;$ds=($dl==='unlimited'||$dl>9999)?'∞':"$dl يوم";echo "$lt · $ds"; ?></div>
    <div style="display:flex;align-items:center;gap:9px">
      <div class="uavt"><?php echo strtoupper(substr($_SESSION['admin_username']??'A',0,1)); ?></div>
      <span style="font-size:.83rem;font-weight:600"><?php echo htmlspecialchars($_SESSION['admin_username']??'المدير'); ?></span>
    </div>
  </div>
</header>
<div class="pcont">
<?php if(isset($_SESSION['success'])): ?><div class="al al-s"><i class="fas fa-check-circle"></i><?php echo $_SESSION['success'];unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="al al-e"><i class="fas fa-exclamation-circle"></i><?php echo $_SESSION['error'];unset($_SESSION['error']); ?></div><?php endif; ?>


<!-- DASHBOARD -->
<section id="dashboard" class="sec on">
  <div class="shdr"><h1 class="stitle">نظرة <span>عامة</span></h1></div>
  <div class="sgrid">
    <div class="sc r"><div class="sc-ic"><i class="fas fa-th-large"></i></div><div class="sc-v"><?php echo $stats['cats']; ?></div><div class="sc-l">الأقسام</div></div>
    <div class="sc g"><div class="sc-ic"><i class="fas fa-tv"></i></div><div class="sc-v"><?php echo $stats['channels']; ?></div><div class="sc-l">القنوات</div></div>
    <div class="sc p"><div class="sc-ic"><i class="fas fa-film"></i></div><div class="sc-v"><?php echo $stats['series']; ?></div><div class="sc-l">شاشتي</div></div>
    <div class="sc go"><div class="sc-ic"><i class="fas fa-eye"></i></div><div class="sc-v"><?php echo number_format($stats['views']); ?></div><div class="sc-l">المشاهدات</div></div>
    <div class="sc b"><div class="sc-ic"><i class="fas fa-users"></i></div><div class="sc-v"><?php echo $stats['users']; ?></div><div class="sc-l">المستخدمين</div></div>
  </div>
  <div class="dgrid">
    <div class="card">
      <div class="chdr"><span class="ctitle"><i class="fas fa-tv" style="color:var(--red);margin-left:7px"></i>آخر القنوات</span><button class="btn btn-g bsm" onclick="S('channels')">الكل</button></div>
      <div class="cbody">
        <?php $rc=array_slice($channels,0,6);if($rc):foreach($rc as $ch): ?>
        <div class="ri"><div class="ri-ic"><i class="<?php echo htmlspecialchars($ch['logo_icon']); ?>"></i></div><div style="flex:1;min-width:0"><div class="ri-name"><?php echo htmlspecialchars($ch['name']); ?></div><div class="ri-meta"><?php echo htmlspecialchars($ch['cat_name']); ?></div></div><span style="font-size:.75rem;color:var(--t3)"><i class="fas fa-eye"></i> <?php echo $ch['views_count']; ?></span></div>
        <?php endforeach;else: ?><div class="empty"><i class="fas fa-tv"></i><p>لا توجد قنوات</p></div><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="chdr"><span class="ctitle"><i class="fas fa-bolt" style="color:var(--gold);margin-left:7px"></i>إجراءات سريعة</span></div>
      <div class="cbody">
        <div class="qa-list">
          <a class="qa r" onclick="S('channels');setTimeout(()=>OM('addChM'),200)"><div class="qa-ic"><i class="fas fa-plus"></i></div>إضافة قناة</a>
          <a class="qa b" onclick="S('categories');setTimeout(()=>OM('addCatM'),200)"><div class="qa-ic"><i class="fas fa-folder-plus"></i></div>إضافة قسم</a>
          <a class="qa p" onclick="S('series');loadSeries();setTimeout(()=>OM('addSeriesM'),300)"><div class="qa-ic"><i class="fas fa-film"></i></div>إضافة مسلسل</a>
          <a class="qa go" onclick="S('vupload')"><div class="qa-ic"><i class="fas fa-cloud-upload-alt"></i></div>رفع فيلم</a>
          <a class="qa g" href="backup_system.php?action=export_full"><div class="qa-ic"><i class="fas fa-download"></i></div>تصدير نسخة احتياطية</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CATEGORIES -->
<section id="categories" class="sec">
  <div class="shdr"><h1 class="stitle">إدارة <span>الأقسام</span></h1><button class="btn btn-p" onclick="OM('addCatM')"><i class="fas fa-plus"></i>قسم جديد</button></div>
  <div class="tw">
    <div class="tt"><div class="tsrch"><i class="fas fa-search"></i><input type="text" placeholder="بحث..." oninput="FT(this,'catTbl')"></div><span style="font-size:.78rem;color:var(--t3)"><?php echo count($categories); ?> قسم</span></div>
    <?php if($categories): ?>
    <table id="catTbl"><thead><tr><th>ID</th><th>القسم</th><th>القسم الأب</th><th>الأيقونة</th><th>القنوات</th><th>إجراءات</th></tr></thead><tbody>
    <?php foreach($categories as $cat): ?>
    <tr><td style="color:var(--t3);font-size:.75rem">#<?php echo $cat['id']; ?></td><td><div class="cn"><div class="nic"><i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i></div><strong style="color:var(--t1)"><?php echo htmlspecialchars($cat['name']); ?></strong></div></td>
    <td><?php $pid=$cat['parent_id']??null;if($pid){foreach($categories as $pc){if($pc['id']==$pid){echo '<span class="bdg bc">'.htmlspecialchars($pc['name']).'</span>';break;}}}else echo '<span style="color:var(--t3);font-size:.75rem">—</span>'; ?></td>
    <td><code style="font-size:.72rem;color:var(--t3);background:var(--s3);padding:2px 7px;border-radius:4px"><?php echo htmlspecialchars($cat['icon']); ?></code></td>
    <td><span class="bdg bc"><?php echo $cat['channel_count']; ?></span></td>
    <td><div class="acts"><button class="ib ed" onclick='editCat(<?php echo json_encode(['id'=>$cat['id'],'name'=>$cat['name'],'icon'=>$cat['icon'],'parent_id'=>$cat['parent_id']??null,'description'=>$cat['description']??'']); ?>)'><i class="fas fa-pen"></i></button><button class="ib dl" onclick="if(confirm('حذف القسم؟'))location.href='?delete_category=<?php echo $cat['id']; ?>'"><i class="fas fa-trash"></i></button></div></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="empty"><i class="fas fa-th-large"></i><p>لا توجد أقسام</p></div><?php endif; ?>
  </div>
</section>

<!-- CHANNELS -->
<section id="channels" class="sec">
  <div class="shdr"><h1 class="stitle">إدارة <span>القنوات</span></h1><button class="btn btn-p" onclick="OM('addChM')"><i class="fas fa-plus"></i>قناة جديدة</button></div>
  <div class="tw">
    <div class="tt"><div class="tsrch"><i class="fas fa-search"></i><input type="text" placeholder="بحث..." oninput="FT(this,'chTbl')"></div><span style="font-size:.78rem;color:var(--t3)"><?php echo count($channels); ?> قناة</span></div>
    <?php if($channels): ?>
    <table id="chTbl"><thead><tr><th>ID</th><th>القناة</th><th>القسم</th><th>ترجمة؟</th><th>المشاهدات</th><th>إجراءات</th></tr></thead><tbody>
    <?php foreach($channels as $ch): ?>
    <tr><td style="color:var(--t3);font-size:.75rem">#<?php echo $ch['id']; ?></td>
    <td><div class="cn"><?php if($ch['logo_url']): ?><img src="<?php echo htmlspecialchars($ch['logo_url']); ?>" style="width:34px;height:34px;object-fit:cover;border-radius:7px" onerror="this.style.display='none'"><?php else: ?><div class="nic"><i class="<?php echo htmlspecialchars($ch['logo_icon']); ?>"></i></div><?php endif; ?><strong style="color:var(--t1)"><?php echo htmlspecialchars($ch['name']); ?></strong></div></td>
    <td><span class="bdg bc"><?php echo htmlspecialchars($ch['cat_name']); ?></span></td>
    <td><?php echo (!empty($ch['subtitle_url']) ? '<span class="bdg bg"><i class="fas fa-closed-captioning"></i> نعم</span>' : '<span style="color:var(--t3);font-size:.75rem">—</span>'); ?></td>
    <td><span style="font-size:.75rem;color:var(--t3)"><i class="fas fa-eye"></i> <?php echo $ch['views_count']; ?></span></td>
    <td><div class="acts"><button class="ib pl" onclick='testChannel("<?php echo htmlspecialchars($ch["stream_url"],ENT_QUOTES); ?>","<?php echo htmlspecialchars($ch["name"],ENT_QUOTES); ?>","<?php echo htmlspecialchars($ch["subtitle_url"]??'',ENT_QUOTES); ?>")'><i class="fas fa-play"></i></button><button class="ib ed" onclick='editCh(<?php echo json_encode(['id'=>$ch['id'],'category_id'=>$ch['category_id'],'name'=>$ch['name'],'stream_url'=>$ch['stream_url'],'logo_icon'=>$ch['logo_icon'],'logo_url'=>$ch['logo_url']]); ?>)'><i class="fas fa-pen"></i></button><button class="ib dl" onclick="if(confirm('حذف القناة؟'))location.href='?delete_channel=<?php echo $ch['id']; ?>'"><i class="fas fa-trash"></i></button></div></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="empty"><i class="fas fa-tv"></i><p>لا توجد قنوات</p></div><?php endif; ?>
  </div>
</section>

<!-- API SETTINGS -->
<section id="api-settings" class="sec">
  <div class="shdr"><h1 class="stitle">إعدادات <span>API</span></h1></div>
  <div class="sw-wrap" style="max-width:800px">
    <div class="swc">
      <div class="swc-hd"><div class="swc-title"><i class="fas fa-film" style="color:var(--gold);margin-left:8px"></i>TMDB API (جلب معلومات الأفلام)</div></div>
      <div class="swc-body">
        <p style="color:var(--t3);font-size:0.85rem;margin-bottom:15px">استخدم هذا المفتاح لجلب بوسترات ومعلومات الأفلام والمسلسلات تلقائياً من موقع TMDB.</p>
        <div class="fg">
          <label class="fl">مفتاح TMDB API</label>
          <input type="text" id="api_tmdb_key" class="fi" placeholder="أدخل مفتاح TMDB API هنا" value="<?php echo htmlspecialchars($settings['tmdb_api_key'] ?? ''); ?>" style="direction:ltr">
        </div>
      </div>
    </div>
    <div class="swc">
      <div class="swc-hd"><div class="swc-title"><i class="fas fa-closed-captioning" style="color:#4CC9F0;margin-left:8px"></i>OpenSubtitles API (جلب الترجمات)</div></div>
      <div class="swc-body">
        <p style="color:var(--t3);font-size:0.85rem;margin-bottom:15px">استخدم هذه الإعدادات لتسجيل الدخول التلقائي والبحث عن الترجمات من موقع OpenSubtitles.</p>
        <div class="row2">
            <div class="fg">
              <label class="fl">اسم المستخدم</label>
              <input type="text" id="api_os_user" class="fi" placeholder="username" value="<?php echo htmlspecialchars($settings['os_username'] ?? ''); ?>" style="direction:ltr">
            </div>
            <div class="fg">
              <label class="fl">كلمة المرور</label>
              <input type="password" id="api_os_pass" class="fi" placeholder="••••••••" value="<?php echo htmlspecialchars($settings['os_password'] ?? ''); ?>" style="direction:ltr">
            </div>
        </div>
        <div class="fg">
          <label class="fl">مفتاح API</label>
          <input type="text" id="api_os_key" class="fi" placeholder="aBcDeF..." value="<?php echo htmlspecialchars($settings['os_api_key'] ?? ''); ?>" style="direction:ltr">
        </div>
      </div>
    </div>
        <!-- OMDb API Card -->
    <div class="swc">
      <div class="swc-hd"><div class="swc-title"><i class="fas fa-database" style="color:var(--gold);margin-left:8px"></i>OMDb API</div></div>
      <div class="swc-body">
        <p style="color:var(--t3);font-size:0.85rem;margin-bottom:15px">مفتاح للبحث عن الأفلام والمسلسلات من OMDb.</p>
        <div class="fg">
          <label class="fl">مفتاح OMDb API</label>
          <input type="text" id="api_omdb_key" class="fi" placeholder="أدخل مفتاح OMDb API" value="<?php echo htmlspecialchars($settings['omdb_api_key'] ?? ''); ?>" style="direction:ltr">
        </div>
        <div style="font-size:.75rem;color:var(--t3);background:var(--s2);padding:10px;border-radius:var(--r1);border:1px solid var(--br)">
          <i class="fas fa-info-circle" style="color:var(--gold)"></i>
          مفتاح مجاني من <a href="https://www.omdbapi.com/apikey.aspx" target="_blank" style="color:#4CC9F0;text-decoration:underline">omdbapi.com</a>
        </div>
      </div>
    </div>

    <button class="btn btn-p" onclick="saveApiSettings()" style="width:100%;justify-content:center;padding:12px"><i class="fas fa-save"></i> حفظ جميع إعدادات API</button>
    <div id="apiSaveAlert" style="margin-top:14px"></div>
  </div>
</section>

<!-- SERIES -->
<section id="series" class="sec">
  <div class="shdr"><h1 class="stitle">إدارة <span>شاشتي</span></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-g" id="srBackBtn" style="display:none" onclick="srBack()"><i class="fas fa-arrow-right"></i>رجوع</button>
      <button class="btn btn-v" id="srBulkBtn" style="display:none" onclick="OM('bulkM')"><i class="fas fa-folder-open"></i>رفع مجلد كامل</button>
      <button class="btn btn-p" id="srAddBtn" onclick="OM('addSeriesM')"><i class="fas fa-plus"></i>مسلسل / فيلم جديد</button>
    </div>
  </div>
  <div id="srBreadcrumb" style="display:none;align-items:center;gap:8px;margin-bottom:18px;font-size:.855rem;color:var(--t3)"><span style="cursor:pointer;color:#4CC9F0" onclick="srBack()">شاشتي</span><i class="fas fa-chevron-left" style="font-size:.62rem"></i><strong id="srBCName" style="color:var(--t1)"></strong><span class="bdg bp" id="srBCCount" style="margin-right:6px"></span></div>
  <div id="srFilterBar" style="display:flex;gap:8px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <select class="fs" id="srCatFilter" style="max-width:200px" onchange="loadSeries()"><option value="">كل الأقسام</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select>
    <div class="tsrch" style="max-width:230px;flex:1"><i class="fas fa-search"></i><input type="text" id="srSearch" placeholder="بحث عن فيلم/مسلسل..." oninput="srFilter()"></div>
    <span id="srCount" style="font-size:.78rem;color:var(--t3);margin-right:auto"></span>
  </div>
  <div id="srLoading" style="display:none;text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p>جارٍ التحميل…</p></div>
  <div id="srGrid" class="srgrid"></div>
  <div id="srEmpty" style="display:none" class="empty"><i class="fas fa-film"></i><p>لا توجد بيانات بعد</p></div>
  <div id="epsPanel" style="display:none">
    <div class="tw">
      <div class="tt"><span style="font-weight:700;font-size:.875rem">الفيديوهات / الحلقات</span><button class="btn btn-p bsm" style="margin-right:auto" onclick="OM('addEpM')"><i class="fas fa-plus"></i>إضافة فيديو</button></div>
      <table><thead><tr><th>#</th><th>العنوان</th><th>الرابط</th><th>ترجمة</th><th>المدة</th><th>إجراءات</th></tr></thead><tbody id="epsTbody"></tbody></table>
      <div id="epsEmpty" style="display:none" class="empty"><i class="fas fa-film"></i><p>لا توجد حلقات/فيديوهات</p></div>
    </div>
  </div>
</section>

<!-- VIDEO UPLOAD -->
<section id="vupload" class="sec">
  <div class="shdr"><h1 class="stitle">رفع <span>الأفلام</span></h1></div>
  <div class="vsteps"><div class="vs act" id="vs1"><div class="vs-n">1</div>رفع الفيديو</div><div class="vs" id="vs2"><div class="vs-n">2</div>الترجمة</div><div class="vs" id="vs3"><div class="vs-n">3</div>الحفظ</div></div>
  <div class="vp act" id="vp1">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-cloud-upload-alt"></i>اختر ملف الفيديو</div></div>
      <div class="vcbody">
        
        <div class="etabs" style="margin-bottom:14px">
          <button class="etab on" onclick="vtab('file')"><i class="fas fa-file-video"></i> من الجهاز</button>
          <button class="etab" onclick="vtab('url')"><i class="fas fa-cloud-download-alt"></i> تحميل ذكي من رابط (أقصى سرعة)</button>
        </div>

        <div id="vtab-file">
          <div class="uz" id="vidDZ"><input type="file" id="vidFileIn" accept="video/*" onchange="vidUpload(this)"><i class="fas fa-film"></i><h3>اسحب الفيديو هنا أو انقر للاختيار</h3><p>MP4 · MKV · AVI · MOV · WebM</p></div>
        </div>

        <div id="vtab-url" style="display:none">
          <div style="background:var(--s2);border:1px dashed var(--br);border-radius:var(--r2);padding:24px;text-align:center">
            <i class="fas fa-tachometer-alt" style="font-size:2rem;color:var(--red);margin-bottom:12px"></i>
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px">رابط مباشر للفيديو</h3>
            <p style="font-size:.8rem;color:var(--t3);margin-bottom:18px">سيقوم الخادم بسحب الملف مباشرة وبسرعة غير محدودة دون استهلاك باقة الإنترنت الخاصة بك.</p>
            <div class="srow">
              <div class="sinp"><i class="fas fa-globe"></i><input type="text" id="smartUrlInp" placeholder="https://example.com/movie.mp4" onkeydown="if(event.key==='Enter')vidSmartDl()"></div>
              <button class="btn btn-p" id="smartDlBtn" onclick="vidSmartDl()"><i class="fas fa-download"></i>سحب الآن</button>
            </div>
          </div>
        </div>

        <div id="vidProg" style="display:none;margin-top:12px">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
                <span class="sp" id="vidProgSp"></span>
                <span id="vidPLabel" style="font-size:.8rem;color:var(--t2);flex:1">جارٍ الرفع…</span>
                <span id="vidPct" style="font-size:.75rem;color:var(--t3)">0%</span>
                <button class="btn btn-g bsm" id="cancelDlBtn" style="display:none;padding:2px 8px;font-size:0.7rem;color:#ff6b6b;border-color:rgba(229,9,20,.3)"><i class="fas fa-times"></i> إلغاء</button>
            </div>
            <div class="pw"><div class="pb" id="vidPBar"></div></div>
        </div>
        <div class="chip" id="vidChip"><div style="width:36px;height:36px;background:rgba(229,9,20,.1);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--red);flex-shrink:0"><i class="fas fa-film"></i></div><div style="flex:1;min-width:0"><div id="vidChipName" style="font-weight:700;font-size:.855rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">—</div><div id="vidChipSize" style="font-size:.74rem;color:var(--t3)">—</div></div><div onclick="vidReset()" style="width:26px;height:26px;background:var(--s3);border:1px solid var(--br);border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);flex-shrink:0"><i class="fas fa-times"></i></div></div>
        <div id="v1alert" style="margin-top:10px"></div>
        <div style="margin-top:8px;text-align:left"><button class="btn btn-g bsm" onclick="vidDebug()" style="font-size:.72rem;opacity:.6"><i class="fas fa-bug"></i> فحص إعدادات الخادم</button></div>
        <div id="v1debug" style="display:none;margin-top:8px;background:var(--s2);border:1px solid var(--br);border-radius:var(--r1);padding:12px;font-size:.75rem;font-family:monospace;color:var(--t3)"></div>
      </div>
    </div>
    <div class="vnavb"><span></span><button class="btn btn-p" id="vNext1" disabled onclick="vidGo(2)">التالي: الترجمة <i class="fas fa-arrow-left"></i></button></div>
  </div>
  <div class="vp" id="vp2">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-closed-captioning"></i>خيارات الترجمة</div></div>
      <div class="vcbody">
        <div class="sub-opts">
          <div class="so sel" id="so-none" onclick="vidSubOpt('none')"><div class="so-ic">🎬</div><div class="so-lbl">بدون ترجمة</div><div class="so-desc">حفظ بدون ترجمة</div></div>
          <div class="so" id="so-search" onclick="vidSubOpt('search')"><div class="so-ic">🔍</div><div class="so-lbl">بحث OpenSubtitles</div><div class="so-desc">ابحث بالاسم</div></div>
          <div class="so" id="so-upload" onclick="vidSubOpt('upload')"><div class="so-ic">📁</div><div class="so-lbl">رفع ترجمة</div><div class="so-desc">SRT · ASS · VTT</div></div>
        </div>
      </div>
    </div>
    <div class="vc" id="osCard" style="display:none">
      <div class="vchd"><div class="vchd-title"><i class="fas fa-search"></i>OpenSubtitles</div></div>
      <div class="vcbody">
        <div id="osNL" style="display:<?php echo $os_logged?'none':'block'; ?>">
          <div class="os-info"><i class="fas fa-key" style="color:#4CC9F0;margin-left:5px"></i>يتم سحب البيانات من قسم (إعدادات API) لتسجيل الدخول التلقائي.</div>
          <div class="row2">
            <div><label class="fl">اسم المستخدم</label><input type="text" class="fi" id="osU" placeholder="username" value="<?php echo htmlspecialchars($settings['os_username'] ?? ''); ?>"></div>
            <div><label class="fl">كلمة المرور</label><input type="password" class="fi" id="osP" placeholder="••••••••" value="<?php echo htmlspecialchars($settings['os_password'] ?? ''); ?>" onkeydown="if(event.key==='Enter')osLogin()"></div>
          </div>
          <div class="fg" style="margin-top:12px"><label class="fl">مفتاح API</label><input type="text" class="fi" id="osApiKey" placeholder="aBcDeF..." value="<?php echo htmlspecialchars($settings['os_api_key'] ?? ''); ?>"></div>
          <button class="btn btn-p" style="width:100%;justify-content:center;padding:11px" onclick="osLogin()" id="osLBtn"><i class="fas fa-sign-in-alt"></i>تسجيل الدخول</button>
          <div id="osLAlert" style="margin-top:8px"></div>
        </div>
        <div id="osL" style="display:<?php echo $os_logged?'flex':'none'; ?>;align-items:center;gap:9px;margin-bottom:12px;padding:10px 13px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.22);border-radius:var(--r1)">
          <i class="fas fa-check-circle" style="color:#00D084;font-size:1.1rem"></i>
          <span style="flex:1;font-size:.83rem">مسجّل: <strong id="osLUser"><?php echo htmlspecialchars($os_user); ?></strong></span>
          <button class="btn btn-g bsm" onclick="osLogout()"><i class="fas fa-sign-out-alt"></i>خروج</button>
        </div>
        <label class="fl">اسم الفيلم للبحث</label>
        <div class="srow">
          <div class="sinp"><i class="fas fa-film"></i><input type="text" id="osQ" placeholder="اسم الفيلم…" onkeydown="if(event.key==='Enter')osSearch()"></div>
          <select class="lsel" id="osLang"><option value="ar">🇸🇦 عربي</option><option value="en">🇬🇧 English</option><option value="fr">🇫🇷 Français</option><option value="es">🇪🇸 Español</option><option value="de">🇩🇪 Deutsch</option><option value="tr">🇹🇷 Türkçe</option></select>
          <button class="btn btn-p" onclick="osSearch()" id="osSearchBtn"><i class="fas fa-search"></i>بحث</button>
        </div>
        <div id="osAl" style="margin-top:8px"></div>
        <div class="sub-rl" id="osRes"></div>
        <div class="sub-chip" id="selSubChip"><i class="fas fa-check-circle"></i><strong id="selSubName">—</strong><button class="btn btn-g bsm" onclick="clearSub()" style="margin-right:auto"><i class="fas fa-times"></i>إلغاء</button></div>
      </div>
    </div>
    <div class="vc" id="subUpCard" style="display:none">
      <div class="vchd"><div class="vchd-title"><i class="fas fa-upload"></i>رفع ملف ترجمة</div></div>
      <div class="vcbody">
        <div class="uz" style="padding:26px"><input type="file" id="subFileIn" accept=".srt,.ass,.ssa,.vtt" onchange="subFileUpload(this)"><i class="fas fa-file-alt"></i><h3>اختر ملف الترجمة</h3><p>SRT · ASS · SSA · VTT</p></div>
        <div class="sub-chip" id="upSubChip" style="margin-top:10px"><i class="fas fa-check-circle"></i><strong id="upSubName">—</strong></div>
        <div id="subAl" style="margin-top:8px"></div>
      </div>
    </div>
    <div class="vnavb"><button class="btn btn-g" onclick="vidGo(1)"><i class="fas fa-arrow-right"></i>السابق</button><button class="btn btn-p" onclick="vidGo(3)">التالي <i class="fas fa-arrow-left"></i></button></div>
  </div>
  <div class="vp" id="vp3">
    <div class="vc"><div class="vchd"><div class="vchd-title"><i class="fas fa-save"></i>الحفظ في شاشتي</div></div>
      <div class="vcbody">
        <div class="merge-sum"><div class="mr"><div class="ml">الفيديو</div><div class="mv" id="mSumV">—</div></div><div class="mr"><div class="ml">الترجمة</div><div class="mv" id="mSumS">بدون ترجمة</div></div></div>
        
        <div class="fg" style="background:rgba(245,166,35,.07);padding:14px;border:1px solid rgba(245,166,35,.2);border-radius:var(--r1)">
            <label class="fl" style="color:var(--gold);margin-bottom:10px;"><i class="fas fa-folder"></i> تحديد المجلد الوجهة في شاشتي</label>
            <select class="fs" id="vTargetSeries" onchange="vToggleSeriesFields(this.value, 'upload')"></select>
        </div>

        <div class="fg"><label class="fl" id="vNameLabel">اسم العمل/المجلد الجديد <small style='color:#00D084'>(مطلوب)</small></label><input type="text" class="fi" id="vChanName" placeholder="أدخل اسم الفيلم / عنوان الحلقة"></div>
        <div class="fg" id="vCatDiv"><label class="fl">القسم (التصنيف)</label><select class="fs" id="vChanCat"><option value="">— اختر القسم —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
        <div id="v3alert" style="margin-bottom:10px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-s" style="flex:1;justify-content:center;padding:12px" onclick="vidSave()"><i class="fas fa-check"></i>حفظ في شاشتي الآن</button></div>
        
        <div id="vidResult" style="display:none;margin-top:14px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.25);border-radius:var(--r2);padding:20px;text-align:center"><div style="font-size:2rem;color:#00D084;margin-bottom:8px"><i class="fas fa-check-circle"></i></div><h3 style="margin-bottom:4px">تم الحفظ بنجاح! 🎉</h3><p id="vidResultInfo" style="font-size:.8rem;color:var(--t3);margin-bottom:14px"></p><button class="btn btn-g" onclick="S('series');loadSeries();"><i class="fas fa-film"></i>انتقل لإدارة شاشتي</button></div>
      </div>
    </div>
    <div class="vnavb"><button class="btn btn-g" onclick="vidGo(2)"><i class="fas fa-arrow-right"></i>السابق</button><span></span></div>
  </div>
</section>

<!-- VIDEO MANAGE -->
<section id="vmanage" class="sec">
  <div class="shdr"><h1 class="stitle">إدارة <span>الفيديوهات</span></h1><button class="btn btn-p" onclick="S('vupload')"><i class="fas fa-plus"></i>رفع جديد</button></div>
  <div style="display:flex;gap:9px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <div class="tsrch" style="max-width:250px;flex:1"><i class="fas fa-search"></i><input type="text" id="vmSearch" placeholder="بحث…" oninput="vmFilter()"></div>
    <select class="fs" id="vmType" style="width:150px" onchange="vmFilter()">
        <option value="all">كل المجلدات الفعالة</option>
        <option value="uploaded">الرفع العام (المُعلقة)</option>
        <option value="merged">المدمجة والمعدلة</option>
        <option value="series">شاشتي (المسلسلات)</option>
    </select>
    <button class="btn btn-g" onclick="vmLoad()"><i class="fas fa-sync-alt"></i>تحديث</button>
    <span id="vmCnt" style="font-size:.78rem;color:var(--t3);margin-right:auto"></span>
  </div>
  <div id="vmLoad" style="text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p>جارٍ التحميل…</p></div>
  <div id="vmEmpty" style="display:none" class="empty"><i class="fas fa-film"></i><p>لا توجد فيديوهات</p><button class="btn btn-p" style="margin-top:14px" onclick="S('vupload')"><i class="fas fa-plus"></i>ارفع الآن</button></div>
  <div id="vmGrid" class="vmgrid" style="display:none"></div>
  <input type="file" id="vmSubUp" accept=".srt,.ass,.ssa,.vtt" style="display:none" onchange="vmHandleSubUp(this)">
</section>

<!-- SETTINGS -->
<section id="site-settings" class="sec">
  <div class="shdr"><h1 class="stitle">إعدادات <span>الموقع</span></h1></div>
  <div style="display:flex;align-items:center;justify-content:center;min-height:280px"><div style="text-align:center"><div style="width:64px;height:64px;background:rgba(229,9,20,.1);border-radius:var(--r3);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:var(--red);margin:0 auto 18px"><i class="fas fa-cog"></i></div><h3 style="margin-bottom:7px">إعدادات الموقع</h3><p style="color:var(--t3);margin-bottom:20px">تخصيص شامل للموقع</p><a href="admin_site_settings.php" class="btn btn-p"><i class="fas fa-external-link-alt"></i>فتح الإعدادات</a></div></div>
</section>

<!-- PASSWORD -->
<section id="change-password" class="sec">
  <div class="shdr"><h1 class="stitle">كلمة <span>المرور</span></h1></div>
  <?php if(isset($_SESSION['pw_ok'])): ?><div class="al al-s"><i class="fas fa-check-circle"></i><?php echo $_SESSION['pw_ok'];unset($_SESSION['pw_ok']); ?></div><?php endif; ?>
  <?php if(isset($_SESSION['pw_err'])): ?><div class="al al-e"><i class="fas fa-exclamation-circle"></i><?php echo $_SESSION['pw_err'];unset($_SESSION['pw_err']); ?></div><?php endif; ?>
  <div class="sw-wrap"><div class="swc"><div class="swc-hd"><div class="swc-title">تغيير كلمة المرور</div></div><div class="swc-body">
    <form method="POST"><div class="fg"><label class="fl">كلمة المرور الحالية</label><input type="password" name="current_password" class="fi" required placeholder="••••••••"></div><div class="fg"><label class="fl">كلمة المرور الجديدة</label><input type="password" name="new_password" class="fi" required minlength="6" placeholder="6 أحرف على الأقل"></div><div class="fg"><label class="fl">تأكيد كلمة المرور</label><input type="password" name="confirm_password" class="fi" required minlength="6" placeholder="أعد الكتابة"></div><button type="submit" name="change_password" class="btn btn-p" style="width:100%;justify-content:center;padding:12px"><i class="fas fa-save"></i>حفظ</button></form>
    <div class="info-b"><div class="info-b-title"><i class="fas fa-shield-alt"></i> نصائح الأمان</div><p style="font-size:.8rem;color:var(--t3)">• 6 أحرف على الأقل<br>• امزج أحرفاً وأرقاماً ورموزاً</p></div>
  </div></div></div>
</section>

<!-- TOOLS -->
<section id="system-tools" class="sec">
  <div class="shdr"><h1 class="stitle">صيانة <span>النظام</span></h1></div>
  <div class="tgrid">
    <div class="tc b" onclick="location.href='upgrade.php'"><div class="tc-ic"><i class="fas fa-sync-alt"></i></div><div class="tc-name">تحديث النظام</div><div class="tc-desc">الترقية إلى أحدث إصدار متاح</div></div>
    <div class="tc r" onclick="location.href='update_v1.0.4_auto.php'"><div class="tc-ic"><i class="fas fa-magic"></i></div><div class="tc-name">إصلاح النظام</div><div class="tc-desc">إصلاح الجداول والاخطاء (V1.0.4)</div></div>
    <div class="tc g" onclick="location.href='backup_system.php?action=export_full'"><div class="tc-ic"><i class="fas fa-database"></i></div><div class="tc-name">نسخ احتياطي</div><div class="tc-desc">تصدير كامل لقاعدة البيانات</div></div>
    <div class="tc go" onclick="location.href='loader.php'"><div class="tc-ic"><i class="fas fa-tools"></i></div><div class="tc-name">أدوات متقدمة</div><div class="tc-desc">صيانة وإصلاح قاعدة البيانات</div></div>
    <div class="tc p" onclick="S('backup')"><div class="tc-ic"><i class="fas fa-upload"></i></div><div class="tc-name">استيراد نسخة</div><div class="tc-desc">استعادة البيانات من ملف SQL</div></div>
    <div class="tc r" onclick="location.href='activate.php'"><div class="tc-ic"><i class="fas fa-key"></i></div><div class="tc-name">الترخيص</div><div class="tc-desc">عرض وتجديد الترخيص</div></div>
  </div>
</section>

<!-- BACKUP -->
<section id="backup" class="sec">
  <div class="shdr"><h1 class="stitle">النسخ <span>الاحتياطي</span></h1></div>
  <div class="bkgrid">
    <div class="bkc"><div class="bkc-title"><i class="fas fa-upload"></i> استعادة نسخة احتياطية</div><p style="color:var(--t3);font-size:.83rem;margin-bottom:18px">اختر ملف SQL لاسترجاع كافة البيانات.</p><form action="backup_system.php?action=import" method="POST" enctype="multipart/form-data" style="border:2px dashed var(--br);padding:20px;border-radius:10px;text-align:center"><input type="file" name="sql_file" accept=".sql" required style="margin-bottom:10px;display:block;width:100%"><button type="submit" class="btn btn-p" style="width:100%;justify-content:center"><i class="fas fa-upload"></i> بدء الاستيراد الآن</button></form></div>
    <div class="bkc"><div class="bkc-title"><i class="fas fa-download"></i> تصدير نسخة جديدة</div><p style="color:var(--t3);font-size:.83rem;margin-bottom:18px">للحفاظ على بياناتك، قم بتحميل نسخة SQL دورياً.</p><a href="backup_system.php?action=export_full" class="btn btn-g" style="width:100%;justify-content:center;padding:12px"><i class="fas fa-download"></i> تحميل النسخة الاحتياطية</a></div>
  </div>
</section>


<!-- USERS MANAGEMENT -->
<section id="users" class="sec">
  <div class="shdr">
    <h1 class="stitle"><i class="fas fa-users-cog" style="color:var(--red)"></i> إدارة <span>المستخدمين</span></h1>
    <button class="btn btn-p" onclick="OM('addUserM')"><i class="fas fa-user-plus"></i>مستخدم جديد</button>
  </div>
  <div style="display:flex;gap:9px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
    <div class="tsrch" style="max-width:250px;flex:1"><i class="fas fa-search"></i><input type="text" id="usrSearch" placeholder="بحث..." oninput="usrFilter()"></div>
    <select class="fs" id="usrRoleFilter" style="width:160px" onchange="usrFilter()">
      <option value="all">كل الأدوار</option>
      <option value="administrator">مدير عام</option>
      <option value="super">مشرف</option>
      <option value="normal">عادي</option>
      <option value="custom">مخصص</option>
    </select>
    <button class="btn btn-g bsm" onclick="loadUsers()"><i class="fas fa-sync-alt"></i></button>
    <span id="usrCount" style="font-size:.78rem;color:var(--t3);margin-right:auto"></span>
  </div>
  <div id="usrLoading" style="display:none;text-align:center;padding:50px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div><p>جارٍ التحميل…</p></div>
  <div id="usrGrid" class="usr-grid"></div>
  <div id="usrEmpty" style="display:none" class="empty"><i class="fas fa-users"></i><p>لا يوجد مستخدمون</p></div>
</section>

</div></div>

<!-- MODALS -->
<!-- PLAYER -->
<div id="pm">
  <div class="pbox"><div class="phd"><div class="phd-l"><div class="pdot" id="pdot"></div><div class="ptitle" id="ptitle">جارٍ التحميل…</div></div><div style="display:flex;align-items:center;gap:8px"><span id="pfmt" style="display:none;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:var(--red)">HLS</span><button class="mclose" onclick="closePlayer()"><i class="fas fa-times"></i></button></div></div>
  <div class="pwrap"><video id="tv" controls playsinline crossorigin="anonymous"></video><div class="pload" id="pload"><div class="pspin"></div><p style="font-size:.83rem;color:var(--t3)">جارٍ تحميل الفيديو…</p></div><div class="perr" id="perr"><div style="font-size:2.5rem;color:var(--red)"><i class="fas fa-exclamation-triangle"></i></div><h3>تعذّر تشغيل الفيديو</h3><p id="perrMsg" style="color:var(--t3);font-size:.83rem;max-width:360px">تحقق من الرابط أو تنسيق الملف</p><div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;justify-content:center"><button class="btn btn-p" onclick="pRetry()"><i class="fas fa-redo"></i>إعادة المحاولة</button><button class="btn btn-g" onclick="pOpenNew()"><i class="fas fa-external-link-alt"></i>فتح في تبويب</button></div></div></div>
  <div class="psubbar" id="psubbar" style="display:none"><i class="fas fa-closed-captioning" style="color:#4CC9F0"></i><span id="psubLabel" style="flex:1;font-size:.75rem">ترجمة نشطة</span><button class="pbtn" onclick="pToggleSub()"><i class="fas fa-toggle-on" id="psubToggleIc"></i><span id="psubToggleTxt">إخفاء</span></button></div>
  <div class="pft"><span class="purl" id="purl">—</span><div class="pbtns"><button class="pbtn" onclick="pCopyUrl()"><i class="fas fa-copy"></i>نسخ</button><button class="pbtn" onclick="pOpenNew()"><i class="fas fa-external-link-alt"></i>جديد</button><button class="pbtn" style="background:rgba(229,9,20,.1);color:var(--red);border-color:rgba(229,9,20,.2)" onclick="closePlayer()"><i class="fas fa-times"></i>إغلاق</button></div></div>
  </div>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="mbd" id="addCatM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-plus"></i>قسم جديد</div><button class="mclose" onclick="CM('addCatM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><div class="mbody"><div class="fg"><label class="fl">اسم القسم</label><input type="text" name="category_name" class="fi" required placeholder="مثال: أفلام عربية"></div><div class="fg"><label class="fl">القسم الأب (اختياري)</label><select name="parent_id" class="fs"><option value="">بدون — قسم رئيسي</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div><div class="fg"><label class="fl">الأيقونة</label><input type="text" name="category_icon" class="fi" value="fas fa-th-large" placeholder="fas fa-film"></div><div class="fg"><label class="fl">الوصف (اختياري)</label><input type="text" name="description" class="fi" placeholder="وصف مختصر"></div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('addCatM')">إلغاء</button><button type="submit" name="add_category" class="btn btn-p"><i class="fas fa-check"></i>إضافة</button></div></form></div></div>

<!-- EDIT CATEGORY MODAL -->
<div class="mbd" id="editCatM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i>تعديل القسم</div><button class="mclose" onclick="CM('editCatM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><div class="mbody"><input type="hidden" name="category_id" id="eCatId"><div class="fg"><label class="fl">اسم القسم</label><input type="text" name="category_name" id="eCatName" class="fi" required></div><div class="fg"><label class="fl">القسم الأب</label><select name="parent_id" id="eCatParent" class="fs"><option value="">بدون — قسم رئيسي</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div><div class="fg"><label class="fl">الأيقونة</label><input type="text" name="category_icon" id="eCatIcon" class="fi"></div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('editCatM')">إلغاء</button><button type="submit" name="edit_category" class="btn btn-p"><i class="fas fa-check"></i>حفظ</button></div></form></div></div>

<!-- ADD CHANNEL MODAL -->
<div class="mbd" id="addChM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-plus"></i>قناة جديدة</div><button class="mclose" onclick="CM('addChM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><div class="mbody"><div class="fg"><label class="fl">القسم</label><select name="category_id" class="fs" required><option value="">— اختر القسم —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg fg-rel"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px"><label class="fl" style="margin:0">اسم القناة</label></div><input type="text" name="channel_name" id="addChName" class="fi" required placeholder="مثال: MBC1"></div>
<div class="fg"><label class="fl">رابط البث</label><input type="text" name="stream_url" class="fi" required placeholder="https://..."></div>
<div class="fg"><label class="fl">الأيقونة</label><input type="text" name="logo_icon" class="fi" value="fas fa-tv"></div>
<div class="fg">
      <label class="fl">رابط الشعار</label>
      <div class="image-upload-row">
        <div style="flex:1">
          <input type="text" name="logo_url" id="addChLogo" class="fi" placeholder="https://example.com/logo.png" oninput="previewImage('addPrev',this.value)">
        </div>
        <label class="upload-btn">
          <i class="fas fa-upload"></i>رفع صورة
          <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" style="display:none" onchange="uploadChannelLogo(this,'addChLogo','addPrev','addLogoStatus')">
        </label>
      </div>
      <div id="addLogoStatus" style="font-size:.75rem;margin-top:4px"></div>
      <div class="image-preview" id="addPrev"><img src="" alt="معاينة"></div>
    </div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('addChM')">إلغاء</button><button type="submit" name="add_channel" class="btn btn-p"><i class="fas fa-check"></i>إضافة</button></div></form></div></div>

<!-- EDIT CHANNEL MODAL -->
<div class="mbd" id="editChM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i>تعديل القناة</div><button class="mclose" onclick="CM('editChM')"><i class="fas fa-times"></i></button></div>
<form method="POST"><div class="mbody"><input type="hidden" name="channel_id" id="eChId">
<div class="fg fg-rel"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px"><label class="fl" style="margin:0">اسم القناة</label></div><input type="text" name="channel_name" id="eChName" class="fi" required></div>
<div class="fg"><label class="fl">القسم</label><select name="category_id" id="eChCat" class="fs" required><option value="">— اختر —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl">رابط البث</label><input type="text" name="stream_url" id="eChUrl" class="fi" required></div>
<div class="fg"><label class="fl">الأيقونة</label><input type="text" name="logo_icon" id="eChIcon" class="fi"></div>
<div class="fg">
      <label class="fl">رابط الشعار</label>
      <div class="image-upload-row">
        <div style="flex:1">
          <input type="text" name="logo_url" id="eChLogo" class="fi" placeholder="https://..." oninput="previewImage('editPrev',this.value)">
        </div>
        <label class="upload-btn">
          <i class="fas fa-upload"></i>رفع صورة
          <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" style="display:none" onchange="uploadChannelLogo(this,'eChLogo','editPrev','editLogoStatus')">
        </label>
      </div>
      <div id="editLogoStatus" style="font-size:.75rem;margin-top:4px"></div>
      <div class="image-preview" id="editPrev"><img src="" alt="معاينة"></div>
    </div></div>
<div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('editChM')">إلغاء</button><button type="submit" name="edit_channel" class="btn btn-p"><i class="fas fa-check"></i>حفظ</button></div></form></div></div>

<!-- ADD SERIES MODAL -->
<div class="mbd" id="addSeriesM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-film"></i>مسلسل / فيلم جديد</div><button class="mclose" onclick="CM('addSeriesM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div class="fg"><label class="fl"><i class="fas fa-globe" style="color:#4CC9F0"></i> مصدر البحث</label>
  <div class="source-tabs" id="addSrSourceTabs">
    <button type="button" class="source-tab active tmdb-active" onclick="switchSource('add','tmdb',this)"><i class="fas fa-film"></i> TMDB</button>
    <button type="button" class="source-tab" onclick="switchSource('add','anilist',this)"><i class="fas fa-dragon"></i> AniList</button>
    <button type="button" class="source-tab" onclick="switchSource('add','omdb',this)"><i class="fas fa-database"></i> OMDb</button>
  </div>
</div><div class="fg media-search-wrap"><label class="fl">الاسم</label><div class="media-search-row"><input type="text" class="fi" id="srName" placeholder="ابحث عن اسم الفيلم أو المسلسل..." oninput="mediaAutoSearch('add',this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();mediaSearch('add')}"><button type="button" class="btn btn-g bsm" onclick="mediaSearch('add')"><i class="fas fa-search"></i></button></div><div class="media-search-results" id="mediaRes_add"></div></div><div class="fg"><label class="fl">القسم</label><select class="fs" id="srCat"><option value="">— اختر القسم —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl">صورة البوستر</label><div style="display:flex;gap:8px;align-items:flex-start"><div style="flex:1"><input type="text" class="fi" id="srPoster" placeholder="https://example.com/poster.jpg" oninput="srPosterPreview('srPosterThumb',this.value)"></div><label style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:9px 13px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);transition:all .15s;white-space:nowrap"><i class="fas fa-upload" style="color:var(--red)"></i>رفع صورة<input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none" onchange="srPosterUpload(this,'srPoster','srPosterThumb','srPosterStatus')"></label></div><div id="srPosterStatus" style="margin-top:6px;font-size:.75rem"></div><div id="srPosterThumb" style="margin-top:8px;display:none"><img src="" style="width:80px;height:110px;object-fit:cover;border-radius:var(--r1);border:2px solid var(--br)"></div></div>
<div class="fg"><label class="fl">الوصف (اختياري)</label><textarea class="fi" id="srDesc" rows="3" style="resize:vertical" placeholder="وصف مختصر…"></textarea></div><div id="srAddAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addSeriesM')">إلغاء</button><button class="btn btn-p" onclick="srAdd()"><i class="fas fa-check"></i>إضافة</button></div></div></div>

<!-- EDIT SERIES MODAL -->
<div class="mbd" id="editSeriesM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i>تعديل شاشتي</div><button class="mclose" onclick="CM('editSeriesM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><input type="hidden" id="eSrId"><div class="fg"><label class="fl"><i class="fas fa-globe" style="color:#4CC9F0"></i> مصدر البحث</label>
  <div class="source-tabs" id="editSrSourceTabs">
    <button type="button" class="source-tab active tmdb-active" onclick="switchSource('edit','tmdb',this)"><i class="fas fa-film"></i> TMDB</button>
    <button type="button" class="source-tab" onclick="switchSource('edit','anilist',this)"><i class="fas fa-dragon"></i> AniList</button>
    <button type="button" class="source-tab" onclick="switchSource('edit','omdb',this)"><i class="fas fa-database"></i> OMDb</button>
  </div>
</div><div class="fg media-search-wrap"><label class="fl">الاسم</label><div class="media-search-row"><input type="text" class="fi" id="eSrName"  oninput="mediaAutoSearch('edit',this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();mediaSearch('edit')}"><button type="button" class="btn btn-g bsm" onclick="mediaSearch('edit')"><i class="fas fa-search"></i></button></div><div class="media-search-results" id="mediaRes_edit"></div></div><div class="fg"><label class="fl">القسم</label><select class="fs" id="eSrCat"><option value="">— اختر —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label class="fl">صورة البوستر</label><div style="display:flex;gap:8px;align-items:flex-start"><div style="flex:1"><input type="text" class="fi" id="eSrPoster" placeholder="https://..." oninput="srPosterPreview('eSrPosterThumb',this.value)"></div><label style="flex-shrink:0;display:flex;align-items:center;gap:6px;padding:9px 13px;background:var(--s3);border:1px solid var(--br);border-radius:var(--r1);cursor:pointer;font-size:.78rem;font-weight:700;color:var(--t2);transition:all .15s;white-space:nowrap"><i class="fas fa-upload" style="color:var(--red)"></i>رفع صورة<input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none" onchange="srPosterUpload(this,'eSrPoster','eSrPosterThumb','eSrPosterStatus')"></label></div><div id="eSrPosterStatus" style="margin-top:6px;font-size:.75rem"></div><div id="eSrPosterThumb" style="margin-top:8px;display:none"><img src="" style="width:80px;height:110px;object-fit:cover;border-radius:var(--r1);border:2px solid var(--br)"></div></div>
<div class="fg"><label class="fl">الوصف</label><textarea class="fi" id="eSrDesc" rows="3" style="resize:vertical"></textarea></div><div id="eSrAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editSeriesM')">إلغاء</button><button class="btn btn-p" onclick="srEditSave()"><i class="fas fa-check"></i>حفظ</button></div></div></div>

<!-- ADD EPISODE MODAL -->
<div class="mbd" id="addEpM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-plus"></i>إضافة فيديو/حلقة</div><button class="mclose" onclick="CM('addEpM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div class="row2"><div class="fg"><label class="fl">رقم الحلقة</label><input type="number" class="fi" id="epNum" value="1" min="1"></div><div class="fg"><label class="fl">العنوان</label><input type="text" class="fi" id="epTitle" placeholder="الحلقة 1 أو اسم الفيلم"></div></div>
<div class="etabs"><button class="etab on" onclick="etab('url')">رابط مباشر</button><button class="etab" onclick="etab('file')">رفع ملف</button></div>
<div id="etab-url"><div class="fg"><label class="fl">رابط الفيديو</label><input type="text" class="fi" id="epUrl" placeholder="https://..."></div></div>
<div id="etab-file" style="display:none"><div class="fg"><label class="fl">رفع ملف الفيديو</label><div class="uz" style="padding:22px"><input type="file" accept="video/*" onchange="epFileUpload(this)"><i class="fas fa-video"></i><h3>اختر ملف الفيديو</h3><p>MP4 · MKV · AVI</p></div><div id="epFileChip" style="display:none;margin-top:8px;padding:9px 12px;background:rgba(0,208,132,.07);border:1px solid rgba(0,208,132,.22);border-radius:var(--r1);font-size:.8rem;align-items:center;gap:8px"><i class="fas fa-check-circle" style="color:#00D084"></i><span id="epFileChipName">—</span></div><div id="epFileProgress" style="display:none;margin-top:8px"><div class="pw"><div class="pb" id="epFilePBar"></div></div></div><input type="hidden" id="epUploadedUrl"></div></div>
<div class="fg"><label class="fl">رابط الترجمة (اختياري)</label><input type="text" class="fi" id="epSubUrl" placeholder="https://... (SRT أو VTT)"></div>
<div class="orsep">أو</div>
<div class="fg"><label class="fl">رفع ملف ترجمة</label><div class="uz" style="padding:18px"><input type="file" accept=".srt,.ass,.vtt,.ssa" onchange="epSubUpload(this)"><i class="fas fa-file-alt"></i><h3>اختر ملف الترجمة</h3><p>SRT · VTT · ASS</p></div><div id="epSubChip" style="display:none;margin-top:8px;padding:9px 12px;background:rgba(76,201,240,.07);border:1px solid rgba(76,201,240,.22);border-radius:var(--r1);font-size:.8rem;align-items:center;gap:8px"><i class="fas fa-closed-captioning" style="color:#4CC9F0"></i><span id="epSubChipName">—</span></div></div>
<div class="fg"><label class="fl">المدة (اختياري)</label><input type="text" class="fi" id="epDur" placeholder="45:00"></div><div id="addEpAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addEpM')">إلغاء</button><button class="btn btn-p" onclick="epAdd()"><i class="fas fa-check"></i>إضافة</button></div></div></div>

<!-- EDIT EPISODE MODAL -->
<div class="mbd" id="editEpM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-pen"></i>تعديل ونقل الفيديو</div><button class="mclose" onclick="CM('editEpM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
    <input type="hidden" id="eEpId">
    
    <div style="background:rgba(245,166,35,.07);border:1px solid rgba(245,166,35,.18);border-radius:var(--r1);padding:11px 14px;margin-bottom:16px;">
       <label class="fl" style="color:var(--gold)"><i class="fas fa-folder-open"></i> المجلد الوجهة بداخل إدارة شاشتي</label>
       <select class="fs" id="eEpSeriesId"></select>
    </div>

    <div class="row2">
        <div class="fg"><label class="fl">رقم الحلقة</label><input type="number" class="fi" id="eEpNum" min="1"></div>
        <div class="fg"><label class="fl">العنوان</label><input type="text" class="fi" id="eEpTitle"></div>
    </div>
    <div class="fg"><label class="fl">رابط الفيديو</label><input type="text" class="fi" id="eEpUrl"></div>
    <div class="fg"><label class="fl">رابط الترجمة</label><input type="text" class="fi" id="eEpSub" placeholder="https://..."></div>
    <div class="fg"><label class="fl">المدة</label><input type="text" class="fi" id="eEpDur" placeholder="45:00"></div>
    <div id="eEpAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editEpM')">إلغاء</button><button class="btn btn-p" onclick="epEditSave()"><i class="fas fa-save"></i>حفظ التعديلات</button></div></div></div>

<!-- BULK UPLOAD MODAL -->
<div class="mbd" id="bulkM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-open"></i>رفع مجلد مسلسل كامل</div><button class="mclose" onclick="CM('bulkM')"><i class="fas fa-times"></i></button></div>
<div class="mbody"><div style="background:rgba(76,201,240,.06);border:1px solid rgba(76,201,240,.18);border-radius:var(--r1);padding:11px 14px;margin-bottom:16px;font-size:.8rem;color:var(--t2)"><i class="fas fa-info-circle" style="color:#4CC9F0;margin-left:5px"></i>اختر جميع ملفات حلقات المسلسل دفعة واحدة.</div>
<div class="fg"><label class="fl">اختر ملفات الحلقات (متعددة)</label><div class="uz" id="bulkDZ"><input type="file" id="bulkFiles" accept="video/*" multiple onchange="bulkPreview(this.files)"><i class="fas fa-folder-open"></i><h3>اختر ملفات الحلقات</h3><p>اضغط لاختيار أكثر من ملف</p></div></div>
<div id="bulkPreviewList" style="display:none;margin-bottom:14px"><div style="font-size:.78rem;font-weight:700;color:var(--t2);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between"><span id="bulkPreviewTitle"></span><span id="bulkTotalSize" style="color:var(--t3)"></span></div><div id="bulkItems" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:5px"></div></div>
<div id="bulkProgress" style="display:none;margin-bottom:14px"><div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:6px"><span id="bulkProgLabel" style="color:var(--t2)">رفع الحلقات…</span><span id="bulkProgPct" style="color:var(--t3)">0%</span></div><div class="pw"><div class="pb" id="bulkPBar"></div></div><div id="bulkCurFile" style="font-size:.72rem;color:var(--t3);margin-top:5px"></div></div>
<div id="bulkResult" style="display:none"></div><div id="bulkAlert"></div></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('bulkM')">إغلاق</button><button class="btn btn-p" id="bulkStartBtn" style="display:none" onclick="bulkUpload()"><i class="fas fa-upload"></i>ابدأ الرفع</button></div></div></div>

<!-- VM SAVE MODAL -->
<div class="mbd" id="vmSaveM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-save"></i>حفظ الفيديو في شاشتي</div><button class="mclose" onclick="CM('vmSaveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <p id="vmSaveFile" style="font-size:.78rem;color:var(--t3);margin-bottom:8px"></p>
  <div id="vmSaveSub" style="display:none;font-size:.78rem;color:#00D084;margin-bottom:16px;background:rgba(0,208,132,.07);padding:8px 12px;border-radius:4px;border:1px solid rgba(0,208,132,.2)"><i class="fas fa-check-circle"></i> تم إرفاق ملف ترجمة بنجاح</div>
  <input type="hidden" id="vmSaveSubUrl">
  
  <div class="fg" style="background:rgba(245,166,35,.07);padding:14px;border:1px dashed rgba(245,166,35,.3);border-radius:var(--r1)">
     <label class="fl" style="color:var(--gold)"><i class="fas fa-folder"></i> إرسال هذا الملف إلى:</label>
     <select class="fs" id="vmSaveTargetSeries" onchange="vToggleSeriesFields(this.value, 'manage')"></select>
  </div>

  <div class="fg"><label class="fl" id="vmNameLabel">الاسم أو العنوان</label><input type="text" class="fi" id="vmSaveTitle" placeholder="اسم الفيلم / أو عنوان الحلقة المضافة"></div>
  <div class="fg" id="vmCatDiv"><label class="fl">قسم العمل (مطلوب)</label><select class="fs" id="vmSaveCat"><option value="">— اختر القسم —</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
  <div id="vmSaveAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('vmSaveM')">إلغاء</button><button class="btn btn-p" onclick="vmDoSave()"><i class="fas fa-check"></i>حفظ في شاشتي</button></div></div></div>

<!-- VM MOVE MODAL -->
<div class="mbd" id="vmMoveM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-open"></i>نقل مسار الفيديو</div><button class="mclose" onclick="CM('vmMoveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="info-b" style="margin-top:0;margin-bottom:16px"><div class="info-b-title"><i class="fas fa-info-circle"></i> تنبيه هام</div><p style="font-size:.8rem;color:var(--t3)">أنت تقوم الآن بإعادة تعيين هذا الفيديو (التحكم بالملف في الخادم والواجهة الأمامية في نفس الوقت). نقل الملف لن يكسر الروابط!</p></div>
  <p id="vmMoveFile" style="font-size:.8rem;color:var(--t1);margin-bottom:16px;font-weight:bold"></p>
  <div class="fg">
    <label class="fl">إلى أين تريد نقله؟</label>
    <select class="fs" id="vmMoveTarget">
        <!-- ستتم تعبئة الخيارات من الـ JS بالأسفل -->
    </select>
  </div>
  <div id="vmMoveAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('vmMoveM')">إلغاء</button><button class="btn btn-p" onclick="vmDoMove()"><i class="fas fa-exchange-alt"></i>نقل الفيديو الآن</button></div></div></div>

<!-- TMDB INFO MODAL -->
<div class="mbd" id="tmdbInfoM" style="z-index: 2000;">
  <div class="mbox w">
    <div class="mhd">
      <div class="mhd-title"><i class="fas fa-info-circle" style="color:#4CC9F0"></i> تفاصيل العمل</div>
      <button class="mclose" onclick="CM('tmdbInfoM')"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody" id="tmdbInfoBody"></div>
  </div>
</div>


<!-- ADD USER MODAL -->
<div class="mbd" id="addUserM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-user-plus"></i>مستخدم جديد</div><button class="mclose" onclick="CM('addUserM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="row2">
    <div class="fg"><label class="fl">اسم المستخدم (للدخول)</label><input type="text" class="fi" id="auUsername" placeholder="username" style="direction:ltr"></div>
    <div class="fg"><label class="fl">الاسم المعروض</label><input type="text" class="fi" id="auDisplay" placeholder="أحمد محمد"></div>
  </div>
  <div class="row2">
    <div class="fg"><label class="fl">كلمة المرور</label><input type="password" class="fi" id="auPassword" placeholder="••••••••"></div>
    <div class="fg"><label class="fl">الدور / الصلاحية</label>
      <select class="fs" id="auRole" onchange="auRoleChange()">
        <option value="normal">عادي (رفع فقط)</option>
        <option value="custom">مخصص (اختر الأقسام)</option>
        <option value="super">مشرف (كل شيء عدا إدارة المدراء)</option>
        <option value="administrator">مدير عام (تحكم كامل)</option>
      </select>
    </div>
  </div>
  <div id="auPermsWrap" style="display:none">
    <label class="fl" style="margin-top:6px"><i class="fas fa-shield-alt" style="color:#00D084"></i> الأقسام المسموحة</label>
    <div class="perm-grid" id="auPermsGrid"></div>
  </div>
  <div id="auAlert" style="margin-top:12px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('addUserM')">إلغاء</button><button class="btn btn-p" onclick="addUser()"><i class="fas fa-check"></i>إنشاء المستخدم</button></div></div></div>

<!-- EDIT USER MODAL -->
<div class="mbd" id="editUserM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-user-edit"></i>تعديل المستخدم</div><button class="mclose" onclick="CM('editUserM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <input type="hidden" id="euId">
  <div class="row2">
    <div class="fg"><label class="fl">اسم المستخدم</label><input type="text" class="fi" id="euUsername" disabled style="direction:ltr;opacity:.6"></div>
    <div class="fg"><label class="fl">الاسم المعروض</label><input type="text" class="fi" id="euDisplay"></div>
  </div>
  <div class="row2">
    <div class="fg"><label class="fl">كلمة مرور جديدة <small style="color:var(--t3)">(اتركها فارغة للإبقاء)</small></label><input type="password" class="fi" id="euPassword" placeholder="••••••••"></div>
    <div class="fg"><label class="fl">الدور / الصلاحية</label>
      <select class="fs" id="euRole" onchange="euRoleChange()">
        <option value="normal">عادي (رفع فقط)</option>
        <option value="custom">مخصص (اختر الأقسام)</option>
        <option value="super">مشرف</option>
        <option value="administrator">مدير عام</option>
      </select>
    </div>
  </div>
  <div class="fg">
    <label class="fl">الحالة</label>
    <select class="fs" id="euActive">
      <option value="1">نشط ✅</option>
      <option value="0">معطّل ⛔</option>
    </select>
  </div>
  <div id="euPermsWrap" style="display:none">
    <label class="fl" style="margin-top:6px"><i class="fas fa-shield-alt" style="color:#00D084"></i> الأقسام المسموحة</label>
    <div class="perm-grid" id="euPermsGrid"></div>
  </div>
  <div id="euAlert" style="margin-top:12px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('editUserM')">إلغاء</button><button class="btn btn-p" onclick="editUser()"><i class="fas fa-save"></i>حفظ التعديلات</button></div></div></div>


<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
const _allFoldersGlobal = <?php echo json_encode($all_folders_list ?? []); ?>;

function initFolderSelects() {
    let opts = '<option value="0" style="color:#00D084;font-weight:bold;">✨ + إنشاء (عمل / مجلد) جديد ومستقل</option>';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(f => {
            opts += `<option value="${f.id}">📂 حفظ بداخل مجلد: ${esc(f.name)}</option>`;
        });
    }
    if($('vTargetSeries')) $('vTargetSeries').innerHTML = opts;
    if($('vmSaveTargetSeries')) $('vmSaveTargetSeries').innerHTML = opts;
}

document.addEventListener("DOMContentLoaded", initFolderSelects);

function vToggleSeriesFields(val, context) {
    let nameLblId = (context === 'upload') ? 'vNameLabel' : 'vmNameLabel';
    let catDivId =  (context === 'upload') ? 'vCatDiv'    : 'vmCatDiv';
    
    if (val == "0") {
        $(nameLblId).innerHTML = "اسم العمل/المجلد الجديد <small style='color:#00D084'>(مطلوب)</small>";
        $(catDivId).style.display = 'block';
    } else {
        $(nameLblId).innerHTML = "عنوان الحلقة أو الفيديو (سيوضع بداخل المجلد المختار) <small style='color:var(--t3)'>(اختياري/يمكنك تعديله)</small>";
        $(catDivId).style.display = 'none'; 
    }
}

const $=id=>document.getElementById(id);
function api(data){const fd=new FormData();for(const[k,v]of Object.entries(data))fd.append(k,String(v??''));return fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).catch(()=>({success:false,error:'خطأ في الاتصال'}));}
function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function escA(s){return(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'")}
function fmtSz(b){if(b>=1073741824)return(b/1073741824).toFixed(1)+' GB';if(b>=100048576)return(b/100048576).toFixed(1)+' MB';if(b>=100024)return(b/100024).toFixed(0)+' KB';return b+' B'}
function al(id,msg,type){const icons={s:'check-circle',e:'exclamation-circle',i:'info-circle'};const cls={s:'al-s',e:'al-e',i:'al-i'};const el=$(id);if(!el)return;if(!msg){el.innerHTML='';return;}el.innerHTML=`<div class="al ${cls[type]||'al-i'}" style="margin:0"><i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}</div>`;}
const titles={dashboard:'لوحة التحكم',categories:'الأقسام',channels:'القنوات',series:'شاشتي',vupload:'رفع الأفلام',vmanage:'إدارة الفيديوهات','api-settings':'إعدادات API','site-settings':'إعدادات الموقع','change-password':'كلمة المرور','system-tools':'صيانة النظام',backup:'النسخ الاحتياطي',users:'إدارة المستخدمين'};
function S(id){document.querySelectorAll('.sec').forEach(s=>{s.classList.remove('on')});document.querySelectorAll('.si').forEach(s=>{s.classList.remove('on')});const sec=$(id);if(sec)sec.classList.add('on');$('tbTitle').textContent=titles[id]||'';document.querySelectorAll('.si').forEach(b=>{if(b.getAttribute('onclick')&&b.getAttribute('onclick').includes(`'${id}'`))b.classList.add('on')});}
function OM(id){const m=$(id);if(m){m.classList.add('op');document.body.style.overflow='hidden'}}
function CM(id){const m=$(id);if(m){m.classList.remove('op');document.body.style.overflow=''}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.mbd.op').forEach(m=>{m.classList.remove('op')});document.body.style.overflow='';closePlayer()}});
document.querySelectorAll('.mbd').forEach(m=>m.addEventListener('click',e=>{if(e.target===m){m.classList.remove('op');document.body.style.overflow=''}}));
function FT(inp,tblId){const q=inp.value.toLowerCase();document.querySelectorAll('#'+tblId+' tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
function toggleSidebar(){const sb=$('sidebar'),ov=$('sbOverlay');const isOpen=sb.classList.contains('open');if(isOpen){closeSidebar();}else{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden';}}
function closeSidebar(){$('sidebar').classList.remove('open');$('sbOverlay').classList.remove('on');document.body.style.overflow='';}

function uploadChannelLogo(inp,inputId,previewId,statusId){
    const f=inp.files[0];if(!f)return;
    const statusEl=$(statusId),previewEl=$(previewId);
    statusEl.innerHTML='<span class="sp"></span> جاري رفع الصورة...';
    const fd=new FormData();fd.append('ajax_action','upload_channel_logo');fd.append('logo',f);
    const xhr=new XMLHttpRequest();
    xhr.upload.onprogress=e=>{if(e.lengthComputable)statusEl.innerHTML=`<span class="sp"></span> ${Math.round(e.loaded/e.total*100)}%`;};
    xhr.onload=()=>{
        try{
            const d=JSON.parse(xhr.responseText);
            if(d.success){$(inputId).value=d.url;statusEl.innerHTML=`<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الصورة</span>`;previewEl.style.display='block';previewEl.querySelector('img').src=d.url;}
            else statusEl.innerHTML=`<span style="color:#ff6b6b">${d.error||'خطأ في الرفع'}</span>`;
        }catch(e){statusEl.innerHTML=`<span style="color:#ff6b6b">خطأ</span>`;}
        inp.value='';
    };
    xhr.onerror=()=>{statusEl.innerHTML=`<span style="color:#ff6b6b">انقطع الاتصال</span>`;};
    xhr.open('POST',location.href);xhr.send(fd);
}

function previewImage(previewId,url){
    const el=$(previewId);if(!el)return;
    if(!url){el.style.display='none';return;}
    el.style.display='block';
    const img=el.querySelector('img');
    img.src=url;
    img.onerror=()=>el.style.display='none';
}

function saveApiSettings(){
    const tmdb_key = $('api_tmdb_key').value.trim();
    const os_user  = $('api_os_user').value.trim();
    const os_pass  = $('api_os_pass').value.trim();
    const os_key   = $('api_os_key').value.trim();
    const omdb_key = $('api_omdb_key').value.trim();
    
    al('apiSaveAlert', '<span class="sp"></span> جاري حفظ الإعدادات...', 'i');
    
    api({
        ajax_action: 'save_api_settings',
        tmdb_key: tmdb_key,
        os_user: os_user,
        os_pass: os_pass,
        os_key: os_key,
        omdb_key: omdb_key
    }).then(d => {
        if(d.success){
            al('apiSaveAlert', '✅ تم حفظ إعدادات الـ API بنجاح في قاعدة البيانات', 's');
            $('osU').value = os_user;
            $('osP').value = os_pass;
            $('osApiKey').value = os_key;
        }else{
            al('apiSaveAlert', d.error || 'حدث خطأ أثناء الحفظ', 'e');
        }
    });
}

let _tmdbTimer={};
const SERVER_TMDB_KEY = "<?php echo addslashes($settings['tmdb_api_key'] ?? ''); ?>";
function getTmdbKey(){ return SERVER_TMDB_KEY; }
const SERVER_OMDB_KEY = "<?php echo addslashes($settings['omdb_api_key'] ?? ''); ?>";
function getOmdbKey(){ return SERVER_OMDB_KEY; }
let _currentSource = { add: 'tmdb', edit: 'tmdb' };
let _mediaSearchTimer = {};

function tmdbAutoSearch(ctx,val){clearTimeout(_tmdbTimer[ctx]);const res=$('tmdbRes_'+ctx);if(!val||val.length<3){res.style.display='none';return;}_tmdbTimer[ctx]=setTimeout(()=>_tmdbSearch(ctx,val),600);}
async function tmdbFetch(ctx){const nameId=ctx==='add'?'addChName':'eChName';const val=$(nameId).value.trim();if(!val){$(nameId).focus();return;}if(!getTmdbKey()){tmdbAskKey(ctx,val);return;}await _tmdbSearch(ctx,val);}

function tmdbAskKey(ctx, pendingQuery){
    alert('يرجى إضافة مفتاح TMDB API في قسم "إعدادات API" أولاً لكي تعمل هذه الميزة.');
    S('api-settings');
    closeSidebar();
}

async function _tmdbSearch(ctx,q){const key=getTmdbKey();if(!key){tmdbAskKey(ctx,q);return;}const res=$('tmdbRes_'+ctx);res.style.display='block';res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:var(--t3)"><span class="sp"></span> جارٍ البحث في TMDB…</div></div>';try{const[rAr,rEn]=await Promise.all([fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}&language=ar`),fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}&language=en-US`)]);if(rAr.status===401||rEn.status===401){res.innerHTML='<div class="tmdb-item" onclick="S(\'api-settings\')" style="cursor:pointer"><div class="tmdb-item-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح API غير صحيح — انقر هنا لتعديله</span></div></div>';return;}const[dAr,dEn]=await Promise.all([rAr.json(),rEn.json()]);const seen=new Set();const combined=[...(dAr.results||[]),...(dEn.results||[])].filter(item=>{const id=item.id;if(seen.has(id))return false;seen.add(id);return(item.title||item.name)&&item.poster_path;}).slice(0,8);if(!combined.length){const rFallback=await fetch(`https://api.themoviedb.org/3/search/multi?api_key=${encodeURIComponent(key)}&query=${encodeURIComponent(q)}`);const dFallback=await rFallback.json();const fallbackItems=(dFallback.results||[]).filter(i=>i.title||i.name).slice(0,8);if(!fallbackItems.length){res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا توجد نتائج — جرب اسم آخر أو بالإنجليزية</div></div>';return;}renderTmdbResults(res,fallbackItems,ctx);return;}renderTmdbResults(res,combined,ctx);}catch(e){res.innerHTML='<div class="tmdb-item"><div class="tmdb-item-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ في الاتصال — تحقق من الإنترنت</div></div>';}}

function renderTmdbResults(res,items,ctx){
    res.innerHTML=items.map(item=>{
        const title=item.title||item.name||'';
        const year=(item.release_date||item.first_air_date||'').substring(0,4);
        const poster=item.poster_path?`https://image.tmdb.org/t/p/w92${item.poster_path}`:'';
        const posterFull=item.poster_path?`https://image.tmdb.org/t/p/w500${item.poster_path}`:'';
        const mediaType=item.media_type||'movie';
        const typeHtml=mediaType==='tv'?'<span class="bdg bp" style="font-size:.6rem">مسلسل</span>':'<span class="bdg bc" style="font-size:.6rem">فيلم</span>';
        const rating=item.vote_average?`<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> ${item.vote_average.toFixed(1)}</span>`:'';
        return `<div class="tmdb-item" onclick="tmdbPick('${ctx}','${escA(title)}','${escA(posterFull)}')">
            <img src="${esc(poster)}" onerror="this.style.opacity='.2'">
            <div class="tmdb-item-info">
                <div class="tmdb-item-title">${esc(title)}</div>
                <div class="tmdb-item-year" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">${year||'—'} ${typeHtml} ${rating}</div>
            </div>
            <button type="button" class="tmdb-info-btn" onclick="event.preventDefault(); event.stopPropagation(); showTmdbInfo(${item.id}, '${mediaType}')" title="التفاصيل"><i class="fas fa-info"></i></button>
        </div>`;
    }).join('');
}

async function showTmdbInfo(id, type) {
    const key = getTmdbKey();
    if (!key) { alert('مفتاح TMDB مفقود! يرجى إضافته في الإعدادات.'); return; }
    OM('tmdbInfoM');
    const body = $('tmdbInfoBody');
    body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--t3)"><div class="pspin" style="margin:0 auto 12px"></div>جاري جلب التفاصيل...</div>';
    try {
        let res = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${encodeURIComponent(key)}&language=ar`);
        let data = await res.json();
        if (!data.overview) {
            let resEn = await fetch(`https://api.themoviedb.org/3/${type}/${id}?api_key=${encodeURIComponent(key)}&language=en-US`);
            let dataEn = await resEn.json();
            data.overview = dataEn.overview;
        }
        const title = data.title || data.name || 'بدون عنوان';
        const poster = data.poster_path ? `https://image.tmdb.org/t/p/w300${data.poster_path}` : '';
        const year = (data.release_date || data.first_air_date || '').substring(0, 4);
        const rating = data.vote_average ? data.vote_average.toFixed(1) : '—';
        const genres = (data.genres || []).map(g => `<span class="bdg bc">${g.name}</span>`).join(' ');
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
                        <span style="color:var(--t2)">الحالة: ${status}</span>
                    </div>
                    <div style="margin-bottom:14px">${genres}</div>
                    <div style="font-size:0.8rem;font-weight:bold;margin-bottom:6px;color:var(--t2)">القصة:</div>
                    <div class="tmdb-info-overview">${overview}</div>
                </div>
            </div>
        `;
    } catch (e) {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#ff6b6b"><i class="fas fa-exclamation-triangle fa-2x"></i><br><br>حدث خطأ أثناء الاتصال بخوادم TMDB.</div>';
    }
}

function tmdbPick(ctx,title,poster){const res=$('tmdbRes_'+ctx);if(ctx==='add'){$('addChName').value=title;if(poster){$('addChLogo').value=poster;previewImage('addPrev',poster);}}else{$('eChName').value=title;if(poster){$('eChLogo').value=poster;previewImage('editPrev',poster);}}res.style.display='none';}
function tmdbPreviewUrl(elId,url){const el=$(elId);if(!el)return;if(!url||(!url.startsWith('http')&&!url.startsWith('/'))){el.style.display='none';return;}el.style.display='block';const img=el.querySelector('img');img.src=url;img.onerror=()=>{el.style.display='none';};}
document.addEventListener('click',e=>{if(!e.target.closest('.fg-rel'))document.querySelectorAll('.tmdb-results').forEach(r=>r.style.display='none');});

/* ════ PLAYER STATE — FIXED v2 ════ */
let _hls = null, _pUrl = '', _pSub = '';
let _watchdogTimer = null;
let _lastTime = -1;
let _frozenCount = 0;

/* اكتشاف صيغة الرابط */
function detectFmt(url) {
    if (!url) return 'hls';
    const clean = url.split('?')[0].split('#')[0].toLowerCase();
    if (clean.endsWith('.m3u8') || clean.endsWith('.m3u')) return 'hls';
    if (clean.includes('m3u8') || clean.includes('/hls/') || clean.includes('type=m3u')) return 'hls';
    if (clean.endsWith('.ts') || clean.endsWith('.mts')) return 'hls';
    if (clean.endsWith('.mpd')) return 'dash';
    if (clean.endsWith('.mp4') || clean.endsWith('.m4v')) return 'mp4';
    if (clean.endsWith('.mkv') || clean.endsWith('.avi') || clean.endsWith('.webm')) return 'direct';
    return 'hls'; // افتراضي دائماً HLS
}

/* تدمير كامل للبلاير السابق */
function _destroyAll() {
    // إيقاف watchdog
    if (_watchdogTimer) { clearInterval(_watchdogTimer); _watchdogTimer = null; }
    _lastTime = -1; _frozenCount = 0;
    // تدمير HLS
    if (_hls) { try { _hls.destroy(); } catch(e) {} _hls = null; }
    // تنظيف عنصر الفيديو
    const vid = $('tv');
    if (!vid) return;
    vid.oncanplay = null; vid.onwaiting = null; vid.onplaying = null;
    vid.onstalled = null; vid.onerror = null;
    try {
        vid.pause();
        // إزالة كل المصادر والـ tracks
        while (vid.firstChild) vid.removeChild(vid.firstChild);
        vid.removeAttribute('src');
        vid.load();
    } catch(e) {}
}

/* watchdog: يراقب التجمّد ويُعيد التشغيل تلقائياً */
function _startWatchdog(url, name, sub) {
    if (_watchdogTimer) clearInterval(_watchdogTimer);
    _lastTime = -1; _frozenCount = 0;
    _watchdogTimer = setInterval(function() {
        const vid = $('tv');
        if (!vid || vid.paused || vid.ended) return;
        const ct = vid.currentTime;
        if (ct === _lastTime) {
            _frozenCount++;
            if (_frozenCount >= 5) { // 10 ثوانٍ تجمّد → إعادة تشغيل
                _frozenCount = 0;
                console.warn('Watchdog: stream frozen, reconnecting...');
                testChannel(url, name, sub);
            }
        } else {
            _frozenCount = 0;
            _lastTime = ct;
        }
    }, 2000);
}

function testChannel(url, name, subUrl) {
    _pUrl = url || '';
    _pSub = subUrl || '';

    // تحديث الواجهة
    $('ptitle').textContent = name || url;
    $('purl').textContent = url;
    $('pm').classList.add('op');
    document.body.style.overflow = 'hidden';
    $('pload').classList.remove('hid');
    $('perr').classList.remove('sh');
    $('pdot').className = 'pdot';
    $('pfmt').style.display = 'none';

    // ═══ تدمير كل شيء قبل البدء ═══
    _destroyAll();

    const vid = $('tv');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.removeAttribute('crossorigin'); // قد يسبب CORS مشاكل مع بعض السيرفرات

    // ═══ الترجمة ═══
    if (_pSub && _pSub.trim()) {
        $('psubbar').style.display = 'flex';
        $('psubLabel').textContent = 'ترجمة: ' + _pSub.split('/').pop();
        const tr = document.createElement('track');
        tr.kind = 'subtitles'; tr.srclang = 'ar'; tr.label = 'عربي';
        tr.src = _pSub; tr.default = true;
        vid.appendChild(tr);
        setTimeout(function() {
            if (vid.textTracks[0]) vid.textTracks[0].mode = 'showing';
        }, 800);
        $('psubToggleIc').className = 'fas fa-toggle-on';
        $('psubToggleTxt').textContent = 'إخفاء';
    } else {
        $('psubbar').style.display = 'none';
    }

    const fmt = detectFmt(url);

    // ══════════════ HLS ══════════════
    if (fmt === 'hls') {
        $('pfmt').style.display = '';
        $('pfmt').textContent = 'HLS';
        $('pfmt').style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(229,9,20,.15);border:1px solid rgba(229,9,20,.3);color:var(--red)';

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            _hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,         // ← السبب الرئيسي للتوقف بعد ثانية — أُوقف
                capLevelToPlayerSize: false,
                maxMaxBufferLength: 120,        // ← بفر أكبر = استقرار أفضل
                maxBufferLength: 60,
                maxBufferSize: 60 * 1000 * 1000,
                backBufferLength: 30,
                startLevel: -1,                 // اختيار جودة تلقائي
                abrEwmaDefaultEstimate: 1000000,
                // إعادة المحاولة عند فشل التحميل
                fragLoadingMaxRetry: 8,
                manifestLoadingMaxRetry: 6,
                levelLoadingMaxRetry: 6,
                fragLoadingRetryDelay: 1500,
                manifestLoadingRetryDelay: 1000,
                levelLoadingRetryDelay: 1000,
                // لا تضغط بيانات manifest
                xhrSetup: function(xhr) {
                    xhr.withCredentials = false;
                }
            });

            _hls.loadSource(url);
            _hls.attachMedia(vid);

            _hls.on(Hls.Events.MANIFEST_PARSED, function() {
                vid.play().catch(function() {});
                _startWatchdog(url, name, subUrl);
            });

            _hls.on(Hls.Events.FRAG_LOADED, function() {
                $('pload').classList.add('hid');
                $('pdot').className = 'pdot ok';
            });

            var _mediaErrCount = 0;
            _hls.on(Hls.Events.ERROR, function(event, data) {
                console.warn('HLS Error:', data.type, data.details, 'fatal:', data.fatal);
                if (!data.fatal) return; // تجاهل الأخطاء غير المميتة تماماً

                if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    // استئناف تلقائي عند خطأ شبكة
                    setTimeout(function() {
                        if (_hls) { try { _hls.startLoad(); } catch(e) {} }
                    }, 2000);
                } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                    _mediaErrCount++;
                    if (_mediaErrCount <= 3) {
                        try { _hls.recoverMediaError(); } catch(e) {}
                    } else {
                        pShowErr('خطأ في فك ترميز الفيديو');
                    }
                } else {
                    pShowErr('خطأ HLS: ' + data.details);
                }
            });

        } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / iOS — HLS أصلي
            vid.src = url;
            vid.play().catch(function() {});
        } else {
            vid.src = url;
            vid.play().catch(function() {});
        }

    // ══════════════ MP4 / Direct ══════════════
    } else {
        $('pfmt').style.display = '';
        $('pfmt').textContent = fmt.toUpperCase();
        $('pfmt').style.cssText = 'display:inline;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:4px;background:rgba(0,208,132,.15);border:1px solid rgba(0,208,132,.3);color:#00D084';
        vid.src = url;
        vid.play().catch(function() {});
    }

    // ═══ أحداث الفيديو ═══
    vid.oncanplay = function() {
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
    };
    vid.onwaiting = function() {
        $('pload').classList.remove('hid');
    };
    vid.onplaying = function() {
        $('pload').classList.add('hid');
        $('pdot').className = 'pdot ok';
    };
    vid.onstalled = function() {
        // محاولة استئناف تلقائية عند توقف البفر
        setTimeout(function() {
            if (vid.paused && _pUrl) { vid.play().catch(function() {}); }
        }, 3000);
    };
    vid.onerror = function() {
        pShowErr('تعذر تشغيل الفيديو — تحقق من الرابط');
    };
}

function pShowErr(msg) {
    $('pload').classList.add('hid');
    $('perr').classList.add('sh');
    $('pdot').className = 'pdot err';
    var em = document.getElementById('perrMsg');
    if (em) em.textContent = msg || 'تعذر تشغيل الفيديو';
}
function pRetry() { testChannel(_pUrl, $('ptitle').textContent, _pSub); }
function pOpenNew() { if (_pUrl) window.open(_pUrl, '_blank'); }
function pCopyUrl() {
    if (!_pUrl) return;
    navigator.clipboard && navigator.clipboard.writeText(_pUrl).then(function() {
        var b = document.querySelector('#pm .pbtn');
        if (b) { var old = b.innerHTML; b.innerHTML = '<i class="fas fa-check"></i> نُسخ'; setTimeout(function() { b.innerHTML = old; }, 1500); }
    });
}
function pToggleSub() {
    const vid = $('tv'), trk = vid.querySelector('track');
    if (!trk) return;
    const on = trk.track.mode === 'showing';
    trk.track.mode = on ? 'disabled' : 'showing';
    $('psubToggleIc').className = on ? 'fas fa-toggle-off' : 'fas fa-toggle-on';
    $('psubToggleTxt').textContent = on ? 'إظهار' : 'إخفاء';
}
function closePlayer() {
    $('pm').classList.remove('op');
    document.body.style.overflow = '';
    _destroyAll();
}

function editCat(d){$('eCatId').value=d.id;$('eCatName').value=d.name;$('eCatIcon').value=d.icon||'fas fa-th-large';const sel=$('eCatParent');for(let o of sel.options)o.selected=(o.value===(d.parent_id||'').toString());OM('editCatM');}
function editCh(d){$('eChId').value=d.id;$('eChName').value=d.name;$('eChUrl').value=d.stream_url;$('eChIcon').value=d.logo_icon||'fas fa-tv';$('eChLogo').value=d.logo_url||'';const sel=$('eChCat');for(let o of sel.options)o.selected=(o.value===d.category_id.toString());if(d.logo_url)previewImage('editPrev',d.logo_url);else $('editPrev').style.display='none';OM('editChM');}
let _srAll=[],_srCurId=0,_srCurName='';
function loadSeries(){$('srGrid').style.display='none';$('srEmpty').style.display='none';$('epsPanel').style.display='none';$('srLoading').style.display='block';$('srBackBtn').style.display='none';$('srBulkBtn').style.display='none';$('srBreadcrumb').style.display='none';$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';$('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");const cid=$('srCatFilter').value;api({ajax_action:'get_series',category_id:cid}).then(d=>{$('srLoading').style.display='none';if(!d.success){return;}_srAll=d.data||[];srRender(_srAll);});}
function srFilter(){const q=$('srSearch').value.toLowerCase();srRender(_srAll.filter(s=>s.name.toLowerCase().includes(q)));}
function srRender(arr){const g=$('srGrid'),e=$('srEmpty');$('srCount').textContent=arr.length+' مسلسلات/أفلام';if(!arr.length){g.style.display='none';e.style.display='block';return;}e.style.display='none';g.style.display='grid';g.innerHTML=arr.map(s=>`<div class="src" id="sr-${s.id}"><div class="src-poster" onclick="srOpen(${s.id},'${escA(s.name)}')">${s.poster_url?`<img src="${esc(s.poster_url)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-film\\'></i>'">`:'<i class="fas fa-film"></i>'}</div><div class="src-body" onclick="srOpen(${s.id},'${escA(s.name)}')"><div class="src-name">${esc(s.name)}</div><div class="src-meta"><span class="bdg bc">${esc(s.cat_name||'—')}</span><span class="bdg bp">${s.ep_count||0} فيديو</span></div></div><div class="src-acts"><button class="ib ed" onclick='srEdit(${JSON.stringify(s)})' ><i class="fas fa-pen"></i></button><button class="ib dl" onclick="srDel(${s.id},'${escA(s.name)}')"><i class="fas fa-trash"></i></button></div></div>`).join('');}
function srOpen(id,name){_srCurId=id;_srCurName=name;$('srGrid').style.display='none';$('srEmpty').style.display='none';$('srFilterBar').style.display='none';$('epsPanel').style.display='block';$('srBackBtn').style.display='';$('srBulkBtn').style.display='';$('srBreadcrumb').style.display='flex';$('srBCName').textContent=name;$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>إضافة فيديو';$('srAddBtn').setAttribute('onclick',"OM('addEpM')");loadEps();}
function srBack(){$('epsPanel').style.display='none';$('srBackBtn').style.display='none';$('srBulkBtn').style.display='none';$('srBreadcrumb').style.display='none';$('srFilterBar').style.display='flex';$('srAddBtn').innerHTML='<i class="fas fa-plus"></i>مسلسل / فيلم جديد';$('srAddBtn').setAttribute('onclick',"OM('addSeriesM')");loadSeries();}
function srAdd(){const n=$('srName').value.trim(),cid=$('srCat').value,desc=$('srDesc').value.trim(),poster=$('srPoster').value.trim();if(!n||!cid){al('srAddAlert','أدخل الاسم واختر القسم','e');return;}api({ajax_action:'add_series',name:n,category_id:cid,description:desc,poster_url:poster}).then(d=>{if(d.success){CM('addSeriesM');loadSeries();$('srName').value='';$('srCat').value='';$('srDesc').value='';$('srPoster').value='';$('srPosterThumb').style.display='none';$('srPosterStatus').innerHTML='';}else al('srAddAlert',d.error||'خطأ','e');});}
function srEdit(s){$('eSrId').value=s.id;$('eSrName').value=s.name;$('eSrDesc').value=s.description||'';$('eSrPoster').value=s.poster_url||'';const sel=$('eSrCat');for(let o of sel.options)o.selected=(o.value===s.category_id.toString());const thumbEl=$('eSrPosterThumb'),statusEl=$('eSrPosterStatus');if(s.poster_url){thumbEl.style.display='block';thumbEl.querySelector('img').src=s.poster_url;statusEl.innerHTML='';}else{thumbEl.style.display='none';statusEl.innerHTML='';}OM('editSeriesM');}
function srEditSave(){const id=$('eSrId').value,n=$('eSrName').value.trim(),cid=$('eSrCat').value,desc=$('eSrDesc').value.trim(),poster=$('eSrPoster').value.trim();if(!n||!cid){al('eSrAlert','البيانات ناقصة','e');return;}api({ajax_action:'edit_series',id,name:n,category_id:cid,description:desc,poster_url:poster}).then(d=>{if(d.success){CM('editSeriesM');loadSeries();}else al('eSrAlert',d.error||'خطأ','e');});}
function srDel(id,name){if(!confirm(`حذف "${name}" مع جميع فيديوهاته/حلقاته؟`))return;api({ajax_action:'delete_series',id}).then(d=>{if(d.success)loadSeries();});}

function loadEps(){$('epsTbody').innerHTML='<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--t3)"><span class="sp"></span> جارٍ التحميل…</td></tr>';$('epsEmpty').style.display='none';api({ajax_action:'get_episodes',series_id:_srCurId}).then(d=>{const eps=d.data||[];$('srBCCount').textContent=eps.length+' فيديو';if(!eps.length){$('epsTbody').innerHTML='';$('epsEmpty').style.display='block';return;}$('epsTbody').innerHTML=eps.map(e=>`<tr><td><span class="bdg bp">${e.episode_number}</span></td><td style="color:var(--t1);font-weight:600">${esc(e.title)}</td><td style="font-size:.72rem;color:var(--t3);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="${esc(e.stream_url)}" target="_blank" style="color:var(--t3)">${esc(e.stream_url.split('/').pop())}</a></td><td>${e.subtitle_url?'<span class="bdg bg"><i class="fas fa-closed-captioning"></i> نعم</span>':'<span style="color:var(--t3);font-size:.75rem">—</span>'}</td><td style="color:var(--t3);font-size:.8rem">${e.duration||'—'}</td><td><div class="acts"><button class="ib pl" onclick='testChannel("${escA(e.stream_url)}","${escA(e.title)}","${escA(e.subtitle_url||'')}")'><i class="fas fa-play"></i></button><button class="ib ed" onclick='epEdit(${JSON.stringify(e)})'><i class="fas fa-pen"></i></button><button class="ib dl" onclick="epDel(${e.id},'${escA(e.title)}')"><i class="fas fa-trash"></i></button></div></td></tr>`).join('');});}
function etab(t){document.querySelectorAll('#addEpM .etab').forEach(b=>b.classList.remove('on'));event.target.classList.add('on');$('etab-url').style.display=t==='url'?'':'none';$('etab-file').style.display=t==='file'?'':'none';}
let _epSubUpUrl='',_epFileUpUrl='';
function epFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);$('epFilePBar').style.width='0%';$('epFileProgress').style.display='block';$('epFileChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('epFilePBar').style.width=p+'%';}};xhr.onload=()=>{$('epFileProgress').style.display='none';try{const d=JSON.parse(xhr.responseText);if(d.success){_epFileUpUrl=d.url;$('epUploadedUrl').value=d.url;$('epFileChip').style.display='flex';$('epFileChipName').textContent=d.original;$('epNum').value=d.episode_number||1;if(!$('epTitle').value.trim())$('epTitle').value='الحلقة '+(d.episode_number||1);}else al('addEpAlert',d.error||'خطأ في الرفع','e');}catch(e){al('addEpAlert','خطأ في الاستجابة','e');}};xhr.onerror=()=>{$('epFileProgress').style.display='none';al('addEpAlert','انقطع الاتصال','e');};xhr.open('POST',location.href);xhr.send(fd);}
function epSubUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_episode_subtitle');fd.append('subtitle',f);fd.append('series_id',_srCurId);fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){_epSubUpUrl=d.url;$('epSubUrl').value=d.url;$('epSubChip').style.display='flex';$('epSubChipName').textContent=f.name;}else al('addEpAlert',d.error||'خطأ','e');});}
function epAdd(){const num=parseInt($('epNum').value)||1;const title=$('epTitle').value.trim()||'الحلقة '+num;const urlTab=$('etab-url').style.display!=='none';let url=urlTab?$('epUrl').value.trim():($('epUploadedUrl').value.trim());const sub=$('epSubUrl').value.trim()||_epSubUpUrl;const dur=$('epDur').value.trim();if(!url){al('addEpAlert','أدخل رابط الفيديو أو ارفع ملفاً','e');return;}api({ajax_action:'add_episode',series_id:_srCurId,episode_number:num,title,stream_url:url,subtitle_url:sub,duration:dur}).then(d=>{if(d.success){CM('addEpM');loadEps();$('epNum').value=parseInt($('epNum').value)+1;$('epTitle').value='';$('epUrl').value='';$('epSubUrl').value='';$('epDur').value='';$('epUploadedUrl').value='';$('epFileChip').style.display='none';$('epSubChip').style.display='none';_epSubUpUrl='';_epFileUpUrl='';}else al('addEpAlert',d.error||'خطأ','e');});}

function epEdit(e){
    $('eEpId').value=e.id;
    $('eEpNum').value=e.episode_number;
    $('eEpTitle').value=e.title;
    $('eEpUrl').value=e.stream_url;
    $('eEpSub').value=e.subtitle_url||'';
    $('eEpDur').value=e.duration||'';
    
    let folderOpts = '';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(folder => {
            let isSelected = (folder.id == e.series_id) ? 'selected' : '';
            folderOpts += `<option value="${folder.id}" ${isSelected}>${esc(folder.name)}</option>`;
        });
    }
    $('eEpSeriesId').innerHTML = folderOpts;
    OM('editEpM');
}

function epEditSave(){
    const id=$('eEpId').value,
          num=parseInt($('eEpNum').value)||1,
          title=$('eEpTitle').value.trim(),
          url=$('eEpUrl').value.trim(),
          sub=$('eEpSub').value.trim(),
          dur=$('eEpDur').value.trim(),
          newSeriesId=$('eEpSeriesId').value;
          
    if(!title||!url){al('eEpAlert','البيانات ناقصة','e');return;}
    
    api({
        ajax_action: 'edit_episode',
        id: id,
        episode_number: num,
        title: title,
        stream_url: url,
        subtitle_url: sub,
        duration: dur,
        series_id: newSeriesId
    }).then(d=>{
        if(d.success){
            CM('editEpM');
            if (newSeriesId != _srCurId) { alert("✅ تم سحب ونقل هذا الملف إلى المسلسل الآخر ببراعة!"); }
            loadEps();
        } else al('eEpAlert', d.error||'خطأ','e');
    });
}

function epDel(id,name){if(!confirm(`حذف الفيديو/الحلقة "${name}"؟`))return;api({ajax_action:'delete_episode',id}).then(d=>{if(d.success)loadEps();});}

let _bulkFiles=[];
function bulkPreview(files){_bulkFiles=Array.from(files);if(!_bulkFiles.length)return;$('bulkStartBtn').style.display='';$('bulkPreviewList').style.display='block';$('bulkPreviewTitle').textContent=_bulkFiles.length+' ملف محدد';const totalSz=_bulkFiles.reduce((s,f)=>s+f.size,0);$('bulkTotalSize').textContent=fmtSz(totalSz);$('bulkItems').innerHTML=_bulkFiles.map((f,i)=>{let epNum=i+1;const m=f.name.match(/[Ee]p?(\d+)|[_\s\-](\d+)\./i);if(m)epNum=parseInt(m[1]||m[2]);return`<div class="ep-item" id="bitem-${i}"><div class="ep-nbdg">${epNum}</div><div style="flex:1;min-width:0;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(f.name)}</div><span style="font-size:.72rem;color:var(--t3)">${fmtSz(f.size)}</span><span class="ep-stat" id="bstat-${i}">انتظار</span></div>`;}).join('');}
async function bulkUpload(){if(!_bulkFiles.length||!_srCurId){al('bulkAlert','لا توجد ملفات أو لم يتم اختيار مسلسل','e');return;}$('bulkStartBtn').disabled=true;$('bulkProgress').style.display='block';let done=0,errs=0;for(let i=0;i<_bulkFiles.length;i++){const f=_bulkFiles[i];let epNum=i+1;const m=f.name.match(/[Ee]p?(\d+)|[_\s\-](\d+)\./i);if(m)epNum=parseInt(m[1]||m[2]);$('bstat-'+i).textContent='جارٍ الرفع…';$('bstat-'+i).className='ep-stat up';$('bulkCurFile').textContent='الملف الحالي: '+f.name;const pct=Math.round(i/_bulkFiles.length*100);$('bulkPBar').style.width=pct+'%';$('bulkProgPct').textContent=pct+'%';const fd=new FormData();fd.append('ajax_action','upload_episode_video');fd.append('episode',f);fd.append('series_id',_srCurId);try{const r=await fetch(location.href,{method:'POST',body:fd});const d=await r.json();if(d.success){const d2=await api({ajax_action:'add_episode',series_id:_srCurId,episode_number:d.episode_number||epNum,title:'الحلقة '+(d.episode_number||epNum),stream_url:d.url,subtitle_url:'',duration:''});if(d2.success){done++;$('bstat-'+i).textContent='✅ تم';$('bstat-'+i).className='ep-stat ok';}else{errs++;$('bstat-'+i).textContent='❌ فشل DB';$('bstat-'+i).className='ep-stat err';}}else{errs++;$('bstat-'+i).textContent='❌ فشل';$('bstat-'+i).className='ep-stat err';}}catch(e){errs++;$('bstat-'+i).textContent='❌ خطأ';$('bstat-'+i).className='ep-stat err';}}$('bulkPBar').style.width='100%';$('bulkProgPct').textContent='100%';$('bulkCurFile').textContent='';$('bulkResult').style.display='block';$('bulkResult').innerHTML=`<div class="al ${errs?'al-i':'al-s'}" style="margin:0"><i class="fas fa-${errs?'info-circle':'check-circle'}"></i> تم رفع ${done} حلقة${errs?' ('+errs+' فشلت)':' بنجاح 🎉'}</div>`;$('bulkStartBtn').disabled=false;_bulkFiles=[];if(_srCurId)loadEps();}

let VID={file:null,filename:'',url:'',subFile:'',subUrl:'',subVttUrl:'',opt:'none'};
let smartDlInterval = null;
let currentSmartDlFile = '';

function vtab(t){
    document.querySelectorAll('#vp1 .etab').forEach(b=>b.classList.remove('on'));
    event.target.classList.add('on');
    $('vtab-url').style.display = t==='url'?'':'none';
    $('vtab-file').style.display = t==='file'?'':'none';
}

function vidSmartDl(){
    const url = $('smartUrlInp').value.trim();
    if(!url){ al('v1alert','أدخل رابط مباشر صالح','e'); return; }

    const btn = $('smartDlBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="sp"></span> جاري تهيئة الاتصال بالرابط...';

    $('vidProg').style.display = 'block';
    $('vidPBar').style.width = '0%';
    $('vidPBar').style.animation = 'none'; 
    $('vidPct').textContent = '0%';
    $('vidPLabel').textContent = 'جاري سحب خصائص الرابط...';
    $('cancelDlBtn').style.display = 'none';
    $('vidProgSp').style.display = 'inline-block';
    $('vidChip').style.display = 'none';
    $('vNext1').disabled = true;
    al('v1alert', '', '');

    // إرسال طلب تجهيز الاستيراد الذكي أولاً 
    api({ajax_action:'prep_smart_dl', url: url}).then(initData => {
        if(!initData.success) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> محاولة سحب الرابط مجدداً';
            $('vidProg').style.display = 'none';
            al('v1alert', initData.error || 'عذراً الرابط يرفض الاتصال، قم بتجربة رابط آخر مباشر!', 'e');
            return;
        }

        const fname = initData.filename;
        const originalName = initData.original;
        let expectedTotalSize = initData.total || 0;
        
        currentSmartDlFile = fname;
        $('cancelDlBtn').style.display = 'inline-block';
        $('cancelDlBtn').onclick = () => cancelSmartDl(fname);
        btn.innerHTML = '<span class="sp"></span> السيرفر يسحب الرابط بأقصى سرعته!';

        let lastLoaded = 0; let lastTime = performance.now();

        // نبض الاستعلام الحي لتوضيح حالة التحميل على الشاشة
        smartDlInterval = setInterval(() => {
            api({ajax_action: 'check_smart_dl', filename: fname}).then(pd => {
                if(pd.success && typeof pd.loaded !== 'undefined') {
                    let curLoaded = pd.loaded || 0; let tot = pd.total || expectedTotalSize;
                    let nowTime = performance.now(); let timeDiff = (nowTime - lastTime) / 1000; 
                    let loadedDiff = curLoaded - lastLoaded; 
                    
                    let speedTxt = "جاري الحساب";
                    if(timeDiff > 0 && loadedDiff > 0) { speedTxt = fmtSz(loadedDiff / timeDiff) + '/ث'; }
                    lastLoaded = curLoaded; lastTime = nowTime;

                    let pct = 0;
                    if(tot > 0) {
                        pct = Math.round((curLoaded / tot) * 100);
                        if(pct > 100) pct = 100;
                        $('vidPLabel').innerHTML = `<span style="color:#00D084;font-weight:bold;margin-left:8px;" dir="ltr">[ سرعة السيرفر: ${speedTxt} ]</span> <span dir="ltr">${fmtSz(curLoaded)} / ${fmtSz(tot)}</span>`;
                        $('vidPBar').style.width = pct + '%';
                        $('vidPct').textContent = pct + '%';
                    } else {
                        // الحجم غير معلن (رابط مُشفر لكنه يعمل) يتم اظهار انه يسحب فقط
                        $('vidPBar').style.width = '100%';
                        $('vidPBar').style.animation = 'bk 1.5s ease infinite'; 
                        $('vidPLabel').innerHTML = `<span style="color:#00D084;font-weight:bold;margin-left:8px;" dir="ltr">[ سرعة السيرفر: ${speedTxt} ]</span> <span dir="ltr">سحب إلى الان: ${fmtSz(curLoaded)}</span>`;
                        $('vidPct').textContent = 'جارٍ...';
                    }
                }
            }).catch(()=>{}); // منع تدمير المتصفح من الاخطاء الدورية 
        }, 1500);

        // هنا السحب الرئيسي بالخلفية
        api({ajax_action:'do_smart_dl', url: url, filename: fname}).then(d => {
            clearInterval(smartDlInterval);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i>سحب فيلم آخر جديد';
            $('vidProg').style.display = 'none';
            $('cancelDlBtn').style.display = 'none';

            if(d.success) {
                VID.filename = d.filename; VID.url = d.url; VID.file = null;
                $('vidChip').style.display = 'flex'; $('vidChipName').textContent = originalName;
                $('vidChipSize').textContent = fmtSz(d.size); $('vNext1').disabled = false;
                const title = originalName.replace(/\.[^.]+$/,'').replace(/[._\-]/g,' ').replace(/\b(720p|1080p|4k|bdrip|web|hdtv|bluray)\b/gi,'').trim();
                $('osQ').value = title; $('vChanName').value = title;
                al('v1alert', '🚀 انتهى الحفظ تماماً وأصبح الملف في قلب خوادمك!', 's');
                $('smartUrlInp').value = '';
            } else { al('v1alert', d.error || 'لقد أمرت النظام بوقف التحميل أو توقف المزوّد.', 'e'); }
        }).catch(err =>{
             clearInterval(smartDlInterval); btn.disabled = false; btn.innerHTML = '<i class="fas fa-download"></i>حاول مرة اخرى';
             $('vidProg').style.display='none'; $('cancelDlBtn').style.display = 'none';
             al('v1alert','انتهت مهلة المراقبة في المتصفح، ولكن التحميل الفعلي قد يكون شغال خلف الكواليس داخل إدارة الفيديوهات.', 'i');
        });
    });
}

function cancelSmartDl(fname) {
    if(!confirm('سيتسبب هذا بقطع تدفق السحب الخارجي وحذف بقاياه. متابعة؟')) return;
    $('cancelDlBtn').disabled = true;
    $('cancelDlBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> أبلغنا السيرفر.. يتم الإعدام!';
    api({ajax_action: 'abort_smart_dl', filename: fname}); // إلقاء إشارة الإيقاف القسرية للمتغير
}

function vidUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_video');fd.append('video',f);$('vidProg').style.display='block';$('cancelDlBtn').style.display='none';$('vidProgSp').style.display='inline-block';$('vidPBar').style.animation='none';$('vidChip').style.display='none';const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);$('vidPBar').style.width=p+'%';$('vidPct').textContent=p+'%';$('vidPLabel').textContent=p<100?'رفع '+fmtSz(e.loaded)+' / '+fmtSz(f.size):'معالجة…';}};xhr.onload=()=>{$('vidProg').style.display='none';const raw=xhr.responseText.trim();if(!raw){al('v1alert','الخادم لم يُرجع رداً — تحقق من إعدادات PHP','e');return;}let d;try{d=JSON.parse(raw);}catch(ex){const preview=raw.replace(/<[^>]+>/g,'').substring(0,300);al('v1alert','خطأ في الاستجابة: '+preview,'e');return;}if(d.success){VID.filename=d.filename;VID.url=d.url;VID.file=f;$('vidChip').style.display='flex';$('vidChipName').textContent=d.original;$('vidChipSize').textContent=fmtSz(f.size);$('vNext1').disabled=false;const title=d.original.replace(/\.[^.]+$/,'').replace(/[._\-]/g,' ').replace(/\b(720p|1080p|4k|bdrip|web|hdtv|bluray)\b/gi,'').trim();$('osQ').value=title;$('vChanName').value=title;al('v1alert','✅ تم رفع الفيديو بنجاح','s');}else{let msg=d.error||'خطأ غير معروف';if(d.debug)msg+=' — '+d.debug;al('v1alert',msg,'e');}};xhr.onerror=()=>{$('vidProg').style.display='none';al('v1alert','انقطع الاتصال بالخادم','e');};xhr.open('POST',location.href);xhr.send(fd);}
function vidDebug(){api({ajax_action:'debug_upload'}).then(d=>{const dbg=$('v1debug');dbg.style.display='block';if(d.success){const ok='✅',no='❌';dbg.innerHTML=`<strong>إعدادات PHP:</strong><br>upload_max_filesize: <b>${d.upload_max_filesize}</b><br>post_max_size: <b>${d.post_max_size}</b><br>مجلد الرفع: <b>${d.upload_dir}</b><br>المجلد موجود: ${d.dir_exists?ok:no}<br>قابل للكتابة: ${d.dir_writable?ok:no}<br>PHP: ${d.php_version}<br><br><small style="color:var(--t3)">إذا كانت القيم 8M أو أقل، أضف للـ .htaccess:<br>php_value upload_max_filesize 2048M<br>php_value post_max_size 2048M</small>`;}else dbg.innerHTML='خطأ: '+d.error;});}
function vidReset(){VID={file:null,filename:'',url:'',subFile:'',subUrl:'',subVttUrl:'',opt:'none'};$('vidChip').style.display='none';$('vidFileIn').value='';$('vNext1').disabled=true;al('v1alert','','');}
function vidGo(step){if(step===3){$('mSumV').textContent=VID.filename||'—';$('mSumS').textContent=VID.subFile?(VID.subFile+' ✅'):'بدون ترجمة';}document.querySelectorAll('.vp').forEach(p=>p.classList.remove('act'));document.querySelectorAll('.vs').forEach(v=>v.classList.remove('act'));$('vp'+step).classList.add('act');$('vs'+step).classList.add('act');for(let i=1;i<step;i++)$('vs'+i).classList.add('done');}
function vidSubOpt(opt){VID.opt=opt;document.querySelectorAll('.so').forEach(s=>s.classList.remove('sel'));$('so-'+opt).classList.add('sel');$('osCard').style.display=opt==='search'?'block':'none';$('subUpCard').style.display=opt==='upload'?'block':'none';}
function subFileUpload(inp){const f=inp.files[0];if(!f)return;const fd=new FormData();fd.append('ajax_action','upload_subtitle_file');fd.append('subtitle',f);al('subAl','<span class="sp"></span> جارٍ الرفع…','i');fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){VID.subFile=d.filename;VID.subUrl=d.url;$('upSubChip').style.display='flex';$('upSubName').textContent=f.name;al('subAl','✅ تم','s');}else al('subAl',d.error||'خطأ','e');});}

function osLogin(){
    const u=$('osU').value.trim(),p=$('osP').value.trim(),k=$('osApiKey').value.trim();
    if(!u||!p){al('osLAlert','أدخل اسم المستخدم وكلمة المرور','e');return;}
    if(!k){al('osLAlert','أدخل مفتاح API','e');return;}
    $('osLBtn').disabled=true;$('osLBtn').innerHTML='<span class="sp"></span>';
    api({ajax_action:'os_login',username:u,password:p,api_key:k}).then(d=>{
        $('osLBtn').disabled=false;$('osLBtn').innerHTML='<i class="fas fa-sign-in-alt"></i>تسجيل الدخول';
        if(d.success){
            $('osNL').style.display='none';$('osL').style.display='flex';$('osLUser').textContent=d.username;
        }else al('osLAlert',d.error||'خطأ','e');
    });
}
function osLogout(){
    api({ajax_action:'os_logout'}).then(()=>{
        $('osNL').style.display='block';$('osL').style.display='none';$('osRes').innerHTML='';$('osLUser').textContent='';
    });
}

function osSearch(){const q=$('osQ').value.trim(),lang=$('osLang').value;if(!q){al('osAl','أدخل اسم الفيلم','e');return;}$('osSearchBtn').disabled=true;$('osSearchBtn').innerHTML='<span class="sp"></span>';$('osRes').style.display='flex';$('osRes').innerHTML=`<div style="padding:14px;color:var(--t3);text-align:center"><span class="sp"></span> جارٍ البحث…</div>`;al('osAl','','');api({ajax_action:'search_subtitles',query:q,language:lang}).then(d=>{$('osSearchBtn').disabled=false;$('osSearchBtn').innerHTML='<i class="fas fa-search"></i>بحث';if(!d.success){al('osAl',d.error||'لا توجد نتائج','e');$('osRes').style.display='none';return;}$('osRes').innerHTML=d.data.map((s,i)=>`<div class="sri" id="sri-${i}" onclick="srClick(${i},${s.file_id},'${escA(s.filename)}')"><div class="sri-main"><div class="sri-title">${esc(s.title)} ${s.year?`(${s.year})`:''}</div><div class="sri-meta"><span>${esc(s.release||'')}</span><span class="stag stag-l">${esc(s.language)}</span><span>${s.downloads} تنزيل</span></div></div><button class="btn btn-g bsm" onclick="event.stopPropagation();dlSub(${s.file_id},'${escA(s.filename)}')"><i class="fas fa-download"></i></button></div>`).join('');});}
function srClick(i,fid,fname){document.querySelectorAll('.sri').forEach(s=>s.classList.remove('sel'));$('sri-'+i)&&$('sri-'+i).classList.add('sel');dlSub(fid,fname);}
function dlSub(fid,fname){al('osAl','<span class="sp"></span> جارٍ تنزيل الترجمة…','i');api({ajax_action:'download_subtitle',file_id:fid}).then(d=>{if(!d.success){al('osAl',d.error||'خطأ','e');return;}VID.subFile=d.filename;VID.subUrl=d.url;VID.subVttUrl=d.vtt_url||d.url;$('selSubChip').style.display='flex';$('selSubName').textContent=fname;al('osAl','✅ تم تنزيل الترجمة — باقي '+d.remaining+' تنزيل اليوم','s');});}
function clearSub(){VID.subFile='';VID.subUrl='';VID.subVttUrl='';$('selSubChip').style.display='none';}

function vidSave(){
    const name=$('vChanName').value.trim();
    const cid=$('vChanCat').value;
    const targetId=$('vTargetSeries').value; 
    
    if(targetId == "0" && !cid){ al('v3alert','يُرجى إختيار أي قسم ليتأسس العمل فيه.','e');return;}
    if(!name && targetId == "0"){ al('v3alert','ما هو أسم فيلمك؟ أدخله بوضوح.','e');return;}
    if(!name && targetId > "0"){ $('vChanName').value = 'عنصر / حلقة تابعه للمسلسل المختار'; }

    if(!VID.url){al('v3alert','ألم ترفع أي فيديو إلى الان! عُد لليمين للخطوات.','e');return;}
    const btn=document.querySelector('#vp3 .btn-s');
    if(btn){btn.disabled=true;btn.innerHTML='<span class="sp"></span> أرقام القاعدة تقيّد إعداداتك حالياً...';}
    
    if(VID.subFile){
        al('v3alert','<span class="sp"></span> المبرمج يدمج سطورك لملفك، انتظر للحظة…','i');
        api({ajax_action:'merge_subtitle',video_file:VID.filename,subtitle_file:VID.subFile}).then(d=>{
            if(!d.success){
                al('v3alert',d.error||'خطأ بملف الترجمة','e');
                if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i>حاول إصلاحه وانقره';} return;
            }
            api({
               ajax_action:'save_to_shashety_auto', 
               category_id: cid, name:$('vChanName').value.trim(), 
               url:VID.url, subtitle_url:(d.method==='no_ffmpeg')?(d.subtitle_url||VID.subUrl):'',
               target_series_id: targetId 
            }).then(d2=>{
                if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> حفظ جديد لمكتتبك';}
                if(d2.success){
                    $('vidResult').style.display='block';
                    $('vidResultInfo').innerHTML= (targetId=="0") ? 'الفيلم الان حر طليق بعمل جديد وخاص.' : 'طاعة المطور اكتملت واصطُف بجوار بقية اخوانه للمسلسل المحدد!';
                    al('v3alert','','');
                }else al('v3alert',d2.error||'انقطعت شاشتك بالقواعد المبرمجة','e');
            });
        });
    }else{
        api({
           ajax_action:'save_to_shashety_auto',
           category_id:cid, name:$('vChanName').value.trim(), url:VID.url, subtitle_url:'',
           target_series_id: targetId 
        }).then(d=>{
            if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> تسجيل ملفه وإرسائه.';}
            if(d.success){
                $('vidResult').style.display='block';
                $('vidResultInfo').innerHTML= (targetId=="0") ? 'سيبدأ الجمهور رؤية فيلمك، شاهده بادارة شاشتي.' : 'أضيفت الحلقة في شاشتك بنجاح لتشكيلات مسلسلك المطلوب.';
                al('v3alert','','');
            }else al('v3alert',d.error||'حدث امر خارجي بمنظومات الخوادم','e');
        });
    }
}

// ══ SERIES POSTER UPLOAD ══
function srPosterUpload(inp,urlInputId,thumbId,statusId){const f=inp.files[0];if(!f)return;const statusEl=$(statusId),thumbEl=$(thumbId);statusEl.innerHTML='<span class="sp"></span> <span style="color:var(--t2)">جارٍ رفع الصورة…</span>';const fd=new FormData();fd.append('ajax_action','upload_series_poster');fd.append('poster',f);const xhr=new XMLHttpRequest();xhr.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);statusEl.innerHTML=`<span class="sp"></span> <span style="color:var(--gold)">${p}%</span>`;}};xhr.onload=()=>{try{const d=JSON.parse(xhr.responseText);if(d.success){$(urlInputId).value=d.url;statusEl.innerHTML=`<span style="color:#00D084"><i class="fas fa-check-circle"></i> تم رفع الصورة بنجاح — ${fmtSz(d.size)}</span>`;thumbEl.style.display='block';thumbEl.querySelector('img').src=d.url;thumbEl.querySelector('img').style.borderColor='#00D084';}else statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> ${d.error||'خطأ في الرفع'}</span>`;}catch(e){statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> خطأ في الاستجابة</span>`;}inp.value='';};xhr.onerror=()=>{statusEl.innerHTML=`<span style="color:#ff6b6b"><i class="fas fa-exclamation-circle"></i> انقطع الاتصال</span>`;};xhr.open('POST',location.href);xhr.send(fd);}
function srPosterPreview(thumbId,url){const thumbEl=$(thumbId);if(!url||!url.startsWith('http')){thumbEl.style.display='none';return;}thumbEl.style.display='block';const img=thumbEl.querySelector('img');img.src=url;img.onerror=()=>{thumbEl.style.display='none';};}

// ══ VIDEO MANAGE (With isolated moves directly interacting Shashety vs Public Videos without transferring dir variables logic directly mapping physical files internally easily managed visually by CSS filtering.)
let _vmAll=[],_vmCtx={},_vmMoveCtx={};

function vmTriggerSub(fn, type){
    _vmCtx = {fn, type};
    $('vmSubUp').click();
}

function vmHandleSubUp(inp){
    const f=inp.files[0]; if(!f) return;
    al('vmLoad','<span class="sp"></span> جارٍ رفع الترجمة وتجهيزها...','i'); 
    $('vmLoad').style.display='block';
    const fd=new FormData(); fd.append('ajax_action','upload_subtitle_file'); fd.append('subtitle',f);
    fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        $('vmLoad').style.display='none';
        inp.value='';
        if(d.success) vmOpenSave(_vmCtx.fn, _vmCtx.type, d.url);
        else alert('خطأ في الترجمة السريعة: ' + (d.error||''));
    });
}

function vmLoad(){$('vmGrid').style.display='none';$('vmEmpty').style.display='none';$('vmLoad').style.display='block';api({ajax_action:'list_videos'}).then(d=>{$('vmLoad').style.display='none';if(!d.success)return;_vmAll=d.videos||[];vmRender(_vmAll);});}
function vmFilter(){const q=$('vmSearch').value.toLowerCase(),t=$('vmType').value;vmRender(_vmAll.filter(v=>(!q||v.filename.toLowerCase().includes(q))&&(t==='all'||v.type===t)));}

function vmRender(vids){
    const g=$('vmGrid'),e=$('vmEmpty');
    $('vmCnt').textContent=vids.length+' ملف بالخادم المربوطة.';
    if(!vids.length){g.style.display='none';e.style.display='block';return;}
    e.style.display='none';g.style.display='grid';
    
    // المسميات بالالوان تريح النفسية وتفرق المجلدات بسهولة للتحكم المستقر.
    const typeLabels = {uploaded:'تم استخراجة للعام (حراً طليقا)', merged:'خاضع لملف الترجمة المستقل', series:'الآن يسكن متجذر لشاشتي (حلقة ومسلسلات).'};
    const typeColors = {uploaded:'rgba(76,201,240,.9)', merged:'rgba(0,208,132,.9)', series:'rgba(245,166,35,.9)'};
    
    g.innerHTML=vids.map(v=>`<div class="vmc" id="vmc-${esc(v.filename)}">
        <div class="vmt" onclick='testChannel("${escA(v.url)}","${escA(v.filename)}")'>
            <video src="${esc(v.url)}#t=3" preload="metadata" muted style="pointer-events:none"></video>
            <div class="vmt-ic"><i class="fas fa-play"></i></div>
            <span class="vmbdg" style="background:${typeColors[v.type]||'#333'};color:${v.type==='uploaded'?'#000':'#fff'}">${typeLabels[v.type]||v.type}</span>
        </div>
        <div class="vminfo">
            <div class="vmname" title="${esc(v.filename)}">${esc(v.filename)}</div>
            <div class="vmmeta"><span><i class="fas fa-hdd"></i> ${v.size_mb} MB</span><span>${esc(v.date)}</span></div>
        </div>
        <div class="vmacts">
            <button class="vmb pl" onclick='testChannel("${escA(v.url)}","${escA(v.filename)}")' title="إلعب مقطعاً مرئياً من داخل هذا الفيديو الان!"><i class="fas fa-play"></i></button>
            <button class="vmb sub" onclick="vmTriggerSub('${escA(v.filename)}','${v.type}')" title="أوّل ما تلحق بهذا المسار ملفات ال vtt الخاص بك سينظر إليها اللاعب باختيار اللمس!"><i class="fas fa-closed-captioning"></i></button>
            <button class="vmb mv" onclick="vmOpenMove('${escA(v.filename)}','${v.type}')" title="تحول جسدي داخلي للـ Filesystem الخاص بنا بالاعماق للملف للقفز للمسلسل أو خارج المجمعات الكتلية.."><i class="fas fa-exchange-alt"></i></button>
            <button class="vmb sv" onclick="vmOpenSave('${escA(v.filename)}','${v.type}')" title="طِور المسار الموثق وإرمه الى داخل مسلسلك المراد !"><i class="fas fa-save"></i></button>
            <button class="vmb dl" onclick="vmDel('${escA(v.filename)}','${v.type}')" title="الإتلاف الكُلي المروع من الجُذر للقرص الصلب السرفر خاصتك!"><i class="fas fa-trash-alt"></i></button>
        </div>
    </div>`).join('');
}

function vmOpenMove(fn, type){
    _vmMoveCtx = {fn, type};
    $('vmMoveFile').textContent = 'هندسة نقل جذر هذا الملف ببراعة السرفرات: ' + fn;
    
    let folderOpts = '<optgroup label="نقل لعراء السيرفر الرئيسي وخروجه للـ Public Videos File!">';
    folderOpts += '<option value="videos">🌐 استخراج وتعريه الملف للرفع العام (أقضي أرسالاتة ومسيراته لشبكة خارج ال Series.)</option>';
    folderOpts += '</optgroup>';
    
    folderOpts += '<optgroup label="إدخاله وحصاره داخل مجمع لجدول مسلسلات شاشتي.">';
    if (typeof _allFoldersGlobal !== 'undefined') {
        _allFoldersGlobal.forEach(folder => {
            folderOpts += `<option value="${folder.id}">🎬 دمجة وإيواه فوراً كحلقة مستجدة لمجمع ومجلد : ${esc(folder.name)}</option>`;
        });
    }
    folderOpts += '</optgroup>';
    
    $('vmMoveTarget').innerHTML = folderOpts;
    
    al('vmMoveAlert','','');
    OM('vmMoveM');
}

function vmDoMove(){
    const target = $('vmMoveTarget').value;
    al('vmMoveAlert', '<span class="sp"></span> يُتخذُ هذا الإيعاز حاسوبياً بمركز الاتصال الخاصك.. جاري توجية ال Path للـ Route الجديد وقطع الارتباط السابق، ابقه متفتح.', 'i');
    
    api({ajax_action: 'move_video_file', filename: _vmMoveCtx.fn, type: _vmMoveCtx.type, target_folder: target}).then(d => {
        if(d.success) {
            al('vmMoveAlert', '✅ ' + d.message, 's');
            setTimeout(() => { CM('vmMoveM'); vmLoad(); }, 1600);
        } else {
            al('vmMoveAlert', d.error || 'عقدة مستعصية جارية حدثت ولم يتحول.', 'e');
        }
    });
}

function vmOpenSave(fn,type, subUrl=''){
    _vmCtx={fn,type};
    $('vmSaveFile').textContent='ترخيص: '+fn;
    $('vmSaveTitle').value=fn.replace(/^(vid_|merged_|vid_dl_|ep_)[a-z0-9]+_?/i,'').replace(/\.[^.]+$/,'').replace(/[_\-.]/g,' ').trim();
    $('vmSaveSubUrl').value = subUrl;
    $('vmSaveSub').style.display = subUrl ? 'block' : 'none';
    al('vmSaveAlert','','');
    vToggleSeriesFields($('vmSaveTargetSeries').value, 'manage');
    OM('vmSaveM');
}

function vmDoSave(){
    const title = $('vmSaveTitle').value.trim(), 
          cid = $('vmSaveCat').value, 
          subUrl = $('vmSaveSubUrl').value,
          targetId = $('vmSaveTargetSeries').value; 

    if(targetId == "0" && (!title || !cid)) { al('vmSaveAlert','الترسانة العصبية المجهزة بالمكتب تمنع حفظها الا عند جردك لإمضاء الفصول للفيلم او الحلقه للنوع الجديد.','e'); return;}
    
    al('vmSaveAlert', '<span class="sp"></span> تدريجات الاضافات تعمل لحفر الداتا بالأساس...', 'i');
    
    api({
        ajax_action:'save_video_manual', 
        filename: _vmCtx.fn, 
        video_type: _vmCtx.type, 
        title: title || 'انشاء مستحدث من إدارة المحرر.', 
        category_id: cid, 
        subtitle_url: subUrl,
        target_series_id: targetId
    }).then(d=>{
        if(d.success){
            CM('vmSaveM');
            alert(targetId == "0" ? "تشريع النظام للمجلد المُنشئ تُم بشكل قوي، أُحتسب هذا المسار!" : "تم رعاية المُختار لمربوطه الساسي وأرسُل لبر المجمع الآلي المُسبق الحفظ شاشتي!");
            setTimeout(()=>{ S('series'); loadSeries(); }, 500);
        }else al('vmSaveAlert', d.error||'تعذر وصول السرديات للمكتب القُدير', 'e');
    });
}

function vmDel(fn,type){if(!confirm('خطر الازالة: سوف تُنسف الذكريات كاملة عن القرص السحب الثابثة الخاصة بهذا المسير ('+fn+') ؟'))return;api({ajax_action:'delete_video',filename:fn,type}).then(d=>{if(d.success){const c=document.getElementById('vmc-'+fn);if(c){c.style.opacity='0';c.style.transition='all .3s';setTimeout(()=>c.remove(),300);}_vmAll=_vmAll.filter(v=>v.filename!==fn);$('vmCnt').textContent=_vmAll.length+' جِرد مقطعيّ وحيد الان متوفّر بالسيرفر.';}else alert('❌ '+(d.error||'استغاثة لملكية السرفر غير خاضعة.'));});}

// ═══════════════════════════════════════════════════════════════
// نظام البحث المتعدد المصادر (TMDB + AniList + OMDb) v3
// ═══════════════════════════════════════════════════════════════
function switchSource(ctx,source,btn){
    _currentSource[ctx]=source;
    var t=$((ctx==='add'?'add':'edit')+'SrSourceTabs');
    if(!t)return;
    t.querySelectorAll('.source-tab').forEach(function(b){b.classList.remove('active','tmdb-active','anilist-active','omdb-active');});
    btn.classList.add('active',source+'-active');
    var r=$('mediaRes_'+ctx);if(r)r.style.display='none';
}
function mediaAutoSearch(ctx,val){
    clearTimeout(_mediaSearchTimer[ctx]);
    var r=$('mediaRes_'+ctx);
    if(!val||val.length<3){if(r)r.style.display='none';return;}
    _mediaSearchTimer[ctx]=setTimeout(function(){mediaSearch(ctx);},700);
}
function mediaSearch(ctx){
    var nid=ctx==='add'?'srName':'eSrName';
    var val=$(nid).value.trim();
    if(!val||val.length<2)return;
    var src=_currentSource[ctx];
    var r=$('mediaRes_'+ctx);
    r.style.display='block';
    r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><span class="sp"></span> جارٍ البحث في '+src.toUpperCase()+'…</div></div>';
    if(src==='tmdb')searchTMDB_ms(ctx,val);
    else if(src==='anilist')searchAniList_ms(ctx,val);
    else if(src==='omdb')searchOMDb_ms(ctx,val);
}
async function searchTMDB_ms(ctx,q){
    var key=getTmdbKey(),r=$('mediaRes_'+ctx);
    if(!key){r.innerHTML='<div class="media-result-item"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح TMDB مفقود</span></div></div>';return;}
    try{
        var rA=await fetch('https://api.themoviedb.org/3/search/multi?api_key='+encodeURIComponent(key)+'&query='+encodeURIComponent(q)+'&language=ar');
        var rE=await fetch('https://api.themoviedb.org/3/search/multi?api_key='+encodeURIComponent(key)+'&query='+encodeURIComponent(q)+'&language=en-US');
        if(rA.status===401){r.innerHTML='<div class="media-result-item"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح TMDB غير صحيح</span></div></div>';return;}
        var dA=await rA.json(),dE=await rE.json();
        var seen=new Set();
        var items=[].concat(dA.results||[],dE.results||[]).filter(function(i){if(seen.has(i.id))return false;seen.add(i.id);return(i.title||i.name);}).slice(0,8);
        if(!items.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج</div></div>';return;}
        r.innerHTML=items.map(function(i){
            var t=i.title||i.name||'',y=(i.release_date||i.first_air_date||'').substring(0,4),
                p=i.poster_path?'https://image.tmdb.org/t/p/w92'+i.poster_path:'',
                pf=i.poster_path?'https://image.tmdb.org/t/p/w500'+i.poster_path:'',
                mt=i.media_type||'movie',
                th=mt==='tv'?'<span class="bdg bp" style="font-size:.6rem">مسلسل</span>':'<span class="bdg bc" style="font-size:.6rem">فيلم</span>',
                rt=i.vote_average?'<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> '+i.vote_average.toFixed(1)+'</span>':'';
            return '<div class="media-result-item" onclick="mediaPick(\''+ctx+'\',\''+escA(t)+'\',\''+escA(pf)+'\',\''+escA(i.overview||'')+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-film" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+'</div><div class="media-result-meta">'+(y||'\u2014')+' '+th+' '+rt+' <span class="source-badge tmdb">TMDB</span></div></div>'+
                '<button type="button" class="tmdb-info-btn" onclick="event.preventDefault();event.stopPropagation();showTmdbInfo('+i.id+',\''+mt+'\')" title="\u062A\u0641\u0627\u0635\u064A\u0644"><i class="fas fa-info"></i></button></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال TMDB</div></div>';}
}
async function searchAniList_ms(ctx,q){
    var r=$('mediaRes_'+ctx);
    var gql='query($s:String){Page(page:1,perPage:10){media(search:$s,type:ANIME,sort:POPULARITY_DESC){id title{romaji english native}coverImage{medium large}startDate{year}episodes format averageScore description(asHtml:false)}}}';
    try{
        var res=await fetch('https://graphql.anilist.co',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({query:gql,variables:{s:q}})});
        var data=await res.json();
        var items=(data&&data.data&&data.data.Page&&data.data.Page.media)||[];
        if(!items.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج أنمي</div></div>';return;}
        r.innerHTML=items.map(function(i){
            var t=i.title.english||i.title.romaji||i.title.native||'',
                ta=i.title.native||i.title.romaji||'',
                y=i.startDate?i.startDate.year:'',
                p=i.coverImage?i.coverImage.medium:'',pf=i.coverImage?i.coverImage.large:'',
                ep=i.episodes?i.episodes+' حلقة':'',
                sc=i.averageScore?'<span style="color:var(--gold);font-size:.65rem"><i class="fas fa-star"></i> '+(i.averageScore/10).toFixed(1)+'</span>':'',
                fm={TV:'مسلسل',MOVIE:'فيلم',OVA:'OVA',ONA:'ONA',SPECIAL:'خاص'},
                fl=fm[i.format]||i.format||'',
                ds=(i.description||'').replace(/<[^>]+>/g,'').substring(0,200);
            return '<div class="media-result-item" onclick="mediaPick(\''+ctx+'\',\''+escA(t)+'\',\''+escA(pf)+'\',\''+escA(ds)+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-dragon" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+(ta&&ta!==t?' <span style="color:var(--t3);font-size:.72rem">('+esc(ta)+')</span>':'')+'</div>'+
                '<div class="media-result-meta">'+(y||'\u2014')+' <span class="bdg" style="background:rgba(76,201,240,.1);color:#4CC9F0;border:1px solid rgba(76,201,240,.2);font-size:.6rem">'+fl+'</span> '+(ep?'<span style="font-size:.65rem">'+ep+'</span> ':'')+sc+' <span class="source-badge anilist">AniList</span></div></div></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال AniList</div></div>';}
}
async function searchOMDb_ms(ctx,q){
    var r=$('mediaRes_'+ctx),key=getOmdbKey();
    if(!key){r.innerHTML='<div class="media-result-item" onclick="S(\'api-settings\')" style="cursor:pointer"><div class="media-result-info"><span style="color:#ff6b6b"><i class="fas fa-key"></i> مفتاح OMDb مفقود — أضفه في إعدادات API</span></div></div>';return;}
    try{
        var res=await fetch('https://www.omdbapi.com/?apikey='+encodeURIComponent(key)+'&s='+encodeURIComponent(q)+'&page=1');
        var data=await res.json();
        if(data.Response==='False'||!data.Search||!data.Search.length){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:var(--t3)"><i class="fas fa-search"></i> لا نتائج — جرب بالإنجليزية</div></div>';return;}
        r.innerHTML=data.Search.slice(0,8).map(function(i){
            var t=i.Title||'',y=i.Year||'',p=(i.Poster&&i.Poster!=='N/A')?i.Poster:'',id=i.imdbID||'',
                tm={movie:'فيلم',series:'مسلسل',episode:'حلقة',game:'لعبة'},tl=tm[i.Type]||i.Type||'';
            return '<div class="media-result-item" onclick="omdbDetail_ms(\''+ctx+'\',\''+escA(id)+'\',\''+escA(t)+'\',\''+escA(p)+'\')">'+
                (p?'<img src="'+esc(p)+'" onerror="this.style.opacity=\'.2\'">':'<div style="width:36px;height:50px;background:var(--s3);border-radius:4px;display:flex;align-items:center;justify-content:center"><i class="fas fa-database" style="color:var(--t3)"></i></div>')+
                '<div class="media-result-info"><div class="media-result-title">'+esc(t)+'</div><div class="media-result-meta">'+(y||'\u2014')+' <span class="bdg" style="background:rgba(245,166,35,.1);color:var(--gold);border:1px solid rgba(245,166,35,.2);font-size:.6rem">'+tl+'</span> '+(id?'<span style="font-size:.62rem;color:var(--t3)">'+id+'</span> ':'')+'<span class="source-badge omdb">OMDb</span></div></div></div>';
        }).join('');
    }catch(e){r.innerHTML='<div class="media-result-item"><div class="media-result-info" style="color:#ff6b6b"><i class="fas fa-exclamation-triangle"></i> خطأ اتصال OMDb</div></div>';}
}
async function omdbDetail_ms(ctx,imdbId,ft,fp){
    var key=getOmdbKey();
    if(!key||!imdbId){mediaPick(ctx,ft,fp,'');return;}
    try{
        var res=await fetch('https://www.omdbapi.com/?apikey='+encodeURIComponent(key)+'&i='+encodeURIComponent(imdbId)+'&plot=short');
        var d=await res.json();
        if(d.Response==='True')mediaPick(ctx,d.Title||ft,(d.Poster&&d.Poster!=='N/A')?d.Poster:fp,(d.Plot&&d.Plot!=='N/A')?d.Plot:'');
        else mediaPick(ctx,ft,fp,'');
    }catch(e){mediaPick(ctx,ft,fp,'');}
}
function mediaPick(ctx,title,poster,desc){
    var r=$('mediaRes_'+ctx);if(r)r.style.display='none';
    if(ctx==='add'){
        $('srName').value=title;
        if(poster){$('srPoster').value=poster;srPosterPreview('srPosterThumb',poster);}
        if(desc&&$('srDesc'))$('srDesc').value=desc;
    }else{
        $('eSrName').value=title;
        if(poster){$('eSrPoster').value=poster;srPosterPreview('eSrPosterThumb',poster);}
        if(desc&&$('eSrDesc'))$('eSrDesc').value=desc;
    }
}
document.addEventListener('click',function(e){
    if(!e.target.closest('.media-search-wrap'))
        document.querySelectorAll('.media-search-results').forEach(function(r){r.style.display='none';});
});
// ═══ نهاية نظام البحث المتعدد ═══


// ══════════════════════════════════════════════════════════════
// نظام إدارة المستخدمين والصلاحيات v1.0
// ══════════════════════════════════════════════════════════════
const ADMIN_ROLE = "<?php echo $_admin_role; ?>";
const ADMIN_SECTIONS = <?php echo json_encode($_admin_sections); ?>;
const ADMIN_USER_ID = <?php echo $_admin_user_id; ?>;

const ALL_SECTION_DEFS = [
    {key:'dashboard',    name:'لوحة التحكم',      icon:'fas fa-home'},
    {key:'categories',   name:'الأقسام',           icon:'fas fa-th-large'},
    {key:'channels',     name:'القنوات',           icon:'fas fa-tv'},
    {key:'series',       name:'شاشتي',             icon:'fas fa-film'},
    {key:'vupload',      name:'رفع الأفلام',       icon:'fas fa-cloud-upload-alt'},
    {key:'vmanage',      name:'إدارة الفيديوهات',  icon:'fas fa-photo-video'},
    {key:'api-settings', name:'إعدادات API',       icon:'fas fa-plug'},
    {key:'site-settings',name:'إعدادات الموقع',    icon:'fas fa-cog'},
    {key:'change-password',name:'كلمة المرور',     icon:'fas fa-key'},
    {key:'system-tools', name:'صيانة النظام',      icon:'fas fa-tools'},
    {key:'backup',       name:'النسخ الاحتياطي',   icon:'fas fa-database'}
];

const ROLE_LABELS = {administrator:'مدير عام',super:'مشرف',normal:'عادي',custom:'مخصص'};
const ROLE_CLASSES = {administrator:'admin',super:'super',normal:'normal',custom:'custom'};

let _usrAll = [];

// ── بناء شبكة الصلاحيات ──
function buildPermsGrid(containerId, selected) {
    var sel = selected || [];
    var html = '';
    ALL_SECTION_DEFS.forEach(function(s) {
        var on = sel.indexOf(s.key) !== -1;
        html += '<div class="perm-item'+(on?' on':'')+'" data-key="'+s.key+'" onclick="togglePerm(this)">';
        html += '<div class="pi-ic"><i class="'+s.icon+'"></i></div>';
        html += '<span class="pi-name">'+s.name+'</span>';
        html += '<div class="pi-chk"><i class="fas fa-check"></i></div>';
        html += '</div>';
    });
    $(containerId).innerHTML = html;
}

function togglePerm(el) {
    el.classList.toggle('on');
}

function getSelectedPerms(containerId) {
    var perms = [];
    $(containerId).querySelectorAll('.perm-item.on').forEach(function(el) {
        perms.push(el.getAttribute('data-key'));
    });
    return perms;
}

// ── إظهار/إخفاء شبكة الصلاحيات بناء على الدور ──
function auRoleChange() {
    var role = $('auRole').value;
    $('auPermsWrap').style.display = (role === 'custom') ? 'block' : 'none';
    if(role === 'custom') buildPermsGrid('auPermsGrid', ['vupload']);
}
function euRoleChange() {
    var role = $('euRole').value;
    $('euPermsWrap').style.display = (role === 'custom') ? 'block' : 'none';
}

// ── تحميل المستخدمين ──
function loadUsers() {
    $('usrGrid').innerHTML = '';
    $('usrEmpty').style.display = 'none';
    $('usrLoading').style.display = 'block';
    api({ajax_action:'get_admin_users'}).then(function(d) {
        $('usrLoading').style.display = 'none';
        if(!d.success) { al('usrGrid', d.error || 'خطأ', 'e'); return; }
        _usrAll = d.data || [];
        usrRender(_usrAll);
    });
}

function usrFilter() {
    var q = ($('usrSearch').value || '').toLowerCase();
    var role = $('usrRoleFilter').value;
    usrRender(_usrAll.filter(function(u) {
        var matchQ = !q || u.username.toLowerCase().indexOf(q) !== -1 || (u.display_name||'').toLowerCase().indexOf(q) !== -1;
        var matchR = role === 'all' || u.role === role;
        return matchQ && matchR;
    }));
}

function usrRender(users) {
    var g = $('usrGrid'), e = $('usrEmpty');
    $('usrCount').textContent = users.length + ' مستخدم';
    if(!users.length) { g.innerHTML = ''; e.style.display = 'block'; return; }
    e.style.display = 'none';
    g.innerHTML = users.map(function(u) {
        var rc = ROLE_CLASSES[u.role] || 'normal';
        var rl = ROLE_LABELS[u.role] || u.role;
        var initial = (u.display_name || u.username || '?').charAt(0).toUpperCase();
        var inactive = u.is_active == 0;
        var lastLogin = u.last_login ? u.last_login.substring(0,16) : 'لم يدخل بعد';
        var sections = [];
        try { sections = JSON.parse(u.allowed_sections || '[]'); } catch(e) {}
        var secText = '';
        if(u.role === 'custom' && sections.length > 0) {
            secText = '<span style="font-size:.68rem;color:var(--gold)"><i class="fas fa-lock-open"></i> ' + sections.length + ' قسم مسموح</span>';
        } else if(u.role === 'normal') {
            secText = '<span style="font-size:.68rem;color:#4CC9F0"><i class="fas fa-cloud-upload-alt"></i> رفع فقط</span>';
        } else if(u.role === 'administrator' || u.role === 'super') {
            secText = '<span style="font-size:.68rem;color:#00D084"><i class="fas fa-globe"></i> كل الأقسام</span>';
        }

        return '<div class="usr-card'+(inactive?' usr-inactive':'')+'">' +
            '<div class="usr-card-hd">' +
                '<div class="usr-avt '+rc+'">'+esc(initial)+'</div>' +
                '<div style="flex:1;min-width:0">' +
                    '<div class="usr-name">'+esc(u.display_name || u.username)+(inactive?' <span style="color:#ff6b6b;font-size:.72rem">⛔ معطّل</span>':'')+'</div>' +
                    '<div class="usr-uname">@'+esc(u.username)+'</div>' +
                '</div>' +
                '<span class="usr-role-bdg '+rc+'"><i class="fas fa-'+(rc==='admin'?'crown':rc==='super'?'shield-alt':rc==='custom'?'sliders-h':'user')+'"></i> '+rl+'</span>' +
            '</div>' +
            '<div class="usr-card-body"><div class="usr-meta">' +
                '<span><i class="fas fa-clock" style="color:var(--t3)"></i> آخر دخول: '+esc(lastLogin)+'</span>' +
                '<span><i class="fas fa-calendar" style="color:var(--t3)"></i> أُنشئ: '+esc((u.created_at||'').substring(0,10))+'</span>' +
                (secText ? '<span>'+secText+'</span>' : '') +
            '</div></div>' +
            '<div class="usr-card-ft">' +
                '<button class="ib ed" onclick=\'openEditUser('+JSON.stringify(u).replace(/'/g,"\\'")+')\'><i class="fas fa-pen"></i></button>' +
                (u.id != ADMIN_USER_ID ? '<button class="ib dl" onclick="deleteUser('+u.id+',\''+escA(u.display_name||u.username)+'\')"><i class="fas fa-trash"></i></button>' : '') +
            '</div>' +
        '</div>';
    }).join('');
}

// ── إضافة مستخدم ──
function addUser() {
    var username = $('auUsername').value.trim();
    var display = $('auDisplay').value.trim();
    var password = $('auPassword').value;
    var role = $('auRole').value;
    var sections = (role === 'custom') ? JSON.stringify(getSelectedPerms('auPermsGrid')) : '[]';

    if(!username) { al('auAlert','أدخل اسم المستخدم','e'); return; }
    if(!password || password.length < 4) { al('auAlert','كلمة المرور يجب أن تكون 4 أحرف على الأقل','e'); return; }

    al('auAlert','<span class="sp"></span> جارٍ الإنشاء...','i');
    api({ajax_action:'add_admin_user', username:username, password:password, display_name:display, role:role, allowed_sections:sections}).then(function(d) {
        if(d.success) {
            CM('addUserM');
            $('auUsername').value = '';
            $('auDisplay').value = '';
            $('auPassword').value = '';
            $('auRole').value = 'normal';
            $('auPermsWrap').style.display = 'none';
            al('auAlert','','');
            loadUsers();
        } else {
            al('auAlert', d.error || 'خطأ', 'e');
        }
    });
}

// ── فتح تعديل مستخدم ──
function openEditUser(u) {
    $('euId').value = u.id;
    $('euUsername').value = u.username;
    $('euDisplay').value = u.display_name || '';
    $('euPassword').value = '';
    $('euRole').value = u.role;
    $('euActive').value = u.is_active;

    var sections = [];
    try { sections = JSON.parse(u.allowed_sections || '[]'); } catch(e) {}

    if(u.role === 'custom') {
        $('euPermsWrap').style.display = 'block';
        buildPermsGrid('euPermsGrid', sections);
    } else {
        $('euPermsWrap').style.display = 'none';
    }

    // Super لا يستطيع اختيار administrator
    if(ADMIN_ROLE === 'super') {
        var opts = $('euRole').options;
        for(var i = 0; i < opts.length; i++) {
            if(opts[i].value === 'administrator') opts[i].disabled = true;
        }
    }

    al('euAlert','','');
    OM('editUserM');
}

// ── حفظ تعديل مستخدم ──
function editUser() {
    var id = $('euId').value;
    var display = $('euDisplay').value.trim();
    var role = $('euRole').value;
    var is_active = $('euActive').value;
    var new_pass = $('euPassword').value;
    var sections = (role === 'custom') ? JSON.stringify(getSelectedPerms('euPermsGrid')) : '[]';

    al('euAlert','<span class="sp"></span> جارٍ الحفظ...','i');
    api({ajax_action:'edit_admin_user', id:id, display_name:display, role:role, allowed_sections:sections, is_active:is_active, new_password:new_pass}).then(function(d) {
        if(d.success) {
            CM('editUserM');
            loadUsers();
        } else {
            al('euAlert', d.error || 'خطأ', 'e');
        }
    });
}

// ── حذف مستخدم ──
function deleteUser(id, name) {
    if(!confirm('حذف المستخدم "' + name + '" نهائياً؟')) return;
    api({ajax_action:'delete_admin_user', id:id}).then(function(d) {
        if(d.success) loadUsers();
        else alert(d.error || 'خطأ');
    });
}

// ══════════════════════════════════════════════════════════════
// فرض الصلاحيات على واجهة المستخدم
// ══════════════════════════════════════════════════════════════
(function enforcePermissions() {
    if(ADMIN_ROLE === 'administrator') return; // المدير العام يرى كل شيء

    var allowed = [];
    if(ADMIN_ROLE === 'super') {
        // المشرف يرى كل شيء + إدارة المستخدمين
        allowed = ALL_SECTION_DEFS.map(function(s){return s.key;});
        allowed.push('users');
    } else if(ADMIN_ROLE === 'normal') {
        allowed = ['vupload'];
    } else if(ADMIN_ROLE === 'custom') {
        allowed = ADMIN_SECTIONS || [];
    }

    // إخفاء أزرار القائمة الجانبية غير المسموحة
    document.querySelectorAll('.si[onclick]').forEach(function(btn) {
        var onclick = btn.getAttribute('onclick') || '';
        var match = onclick.match(/S\('([^']+)'\)/);
        if(match) {
            var sid = match[1];
            if(allowed.indexOf(sid) === -1) {
                btn.style.display = 'none';
            }
        }
    });

    // تعديل دالة S لمنع الوصول للأقسام غير المسموحة
    var _origS = window.S;
    window.S = function(id) {
        if(allowed.indexOf(id) === -1) {
            alert('ليس لديك صلاحية للوصول لهذا القسم');
            return;
        }
        _origS(id);
    };

    // عند تحميل الصفحة، اذهب لأول قسم مسموح
    if(allowed.length > 0 && allowed.indexOf('dashboard') === -1) {
        setTimeout(function() {
            // إزالة on من dashboard
            var ds = document.getElementById('dashboard');
            if(ds) ds.classList.remove('on');
            document.querySelectorAll('.si').forEach(function(b){b.classList.remove('on');});
            _origS(allowed[0]);
            if(allowed[0] === 'vupload') {} // لا حاجة لتحميل إضافي
            else if(allowed[0] === 'series') { if(typeof loadSeries === 'function') loadSeries(); }
            else if(allowed[0] === 'vmanage') { if(typeof vmLoad === 'function') vmLoad(); }
        }, 100);
    }
})();
// ═══ نهاية نظام المستخدمين ═══

</script>
</body>
</html>
