<?php
// api.php - ملف داخلي كامل للتحكم في MikroTik (45.89.111.166)
// يعمل 100% مع الواجهة العربية التي عندك

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// بيانات الاتصال بخادمك (ثابتة)
$ROUTER_IP = "45.89.111.166";
$USERNAME  = "vps";
$PASSWORD  = "noor1992320";
$PORT      = 8728;

// مكتبة MikroTik API (مدمجة داخل الملف - لا تحتاج تحميل شيء)
class RouterOS_API {
    private $socket = false;
    private $debug = false;

    public function connect($ip, $login, $password, $port = 8728) {
        $this->socket = @fsockopen("tcp://$ip", $port, $errno, $errstr, 30);
        if (!$this->socket) return false;

        $this->write('/login', false);
        $this->write('=name=' . $login);
        $this->write('=password=' . $password);
        $read = $this->read(false);
        if (isset($read[0]) && $read[0][0] == '!trap') return false;
        return true;
    }

    public function comm($command, $param = array()) {
        $this->write($command, true);
        foreach ($param as $k => $v) {
            if ($v !== '') $this->write("=$k=$v");
        }
        $this->write('', true);
        return $this->read(true);
    }

    private function write($command, $end = false) {
        if (!$this->socket) return;
        $len = strlen($command);
        if ($len < 0x80) {
            fwrite($this->socket, chr($len) . $command);
        } else {
            // دعم الأوامر الطويلة
            fwrite($this->socket, pack('C', ($len & 0xFF) | 0x80) . pack('n', $len >> 8) . $command);
        }
        if ($end) fwrite($this->socket, "\0");
    }

    private function read($parse = true) {
        $result = array();
        while (true) {
            $len = fgetc($this->socket);
            if ($len === false) break;
            $len = ord($len) & 0xFF;
            if ($len & 0x80) {
                $len = ($len & 0x7F) << 8;
                $len += ord(fgetc($this->socket));
            }
            if ($len == 0) break;
            $data = fread($this->socket, $len);
            if ($parse) {
                $item = array();
                foreach (explode("\n", trim($data)) as $part) {
                    if (strpos($part, '=') !== false) {
                        list($key, $val) = explode('=', $part, 2);
                        if (substr($key, 0, 1) == '.') $key = substr($key, 1);
                        $item[$key] = $val;
                    }
                }
                if (!empty($item)) $result[] = $item;
            }
        }
        return $result;
    }

    public function disconnect() {
        if ($this->socket) fclose($this->socket);
    }
}

// بدء الاتصال
$API = new RouterOS_API();
if (!$API->connect($ROUTER_IP, $USERNAME, $PASSWORD, $PORT)) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بالخادم'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'get_info':
        $resource = $API->comm('/system/resource/print')[0] ?? [];
        $board    = $API->comm('/system/routerboard/print')[0] ?? [];
        $uptime   = $resource['uptime'] ?? 'غير معروف';
        $version  = $resource['version'] ?? 'غير معروف';
        $model    = $board['model'] ?? ($resource['board-name'] ?? 'غير معروف');

        $secrets = $API->comm('/ppp/secret/print');
        $active  = $API->comm('/ppp/active/print');

        echo json_encode([
            'success' => true,
            'model' => $model,
            'version' => $version,
            'uptime' => $uptime,
            'total_users' => count($secrets),
            'active_sessions' => count($active)
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'get_users':
        $secrets = $API->comm('/ppp/secret/print');
        echo json_encode(['success' => true, 'users' => $secrets], JSON_UNESCAPED_UNICODE);
        break;

    case 'add_user':
        $name = $_POST['name'] ?? '';
        $pass = $_POST['pass'] ?? '';
        $service = $_POST['service'] ?? 'any';
        $rate = $_POST['rate'] ?? '';

        if (empty($name) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'اسم المستخدم وكلمة المرور مطلوبان']);
            break;
        }

        $API->comm('/ppp/secret/add', [
            'name' => $name,
            'password' => $pass,
            'service' => $service,
            'profile' => 'default',
            'rate-limit' => $rate
        ]);

        echo json_encode(['success' => true, 'message' => "تم إضافة المستخدم $name بنجاح"], JSON_UNESCAPED_UNICODE);
        break;

    case 'delete_user':
        $id = $_POST['id'] ?? '';
        if ($id) {
            $API->comm('/ppp/secret/remove', ['.id' => $id]);
            echo json_encode(['success' => true, 'message' => 'تم الحذف']);
        } else {
            echo json_encode(['success' => false, 'message' => 'معرف المستخدم مفقود']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'أمر غير معروف']);
}

$API->disconnect();
?>