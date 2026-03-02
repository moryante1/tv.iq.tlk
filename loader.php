<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $host = $_POST['host'] ?? '';
    $user = $_POST['user'] ?? '';
    $password = $_POST['password'] ?? '';
    $dbname = $_POST['dbname'] ?? '';

    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === 0) {

        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);

        try {
            $conn = new mysqli($host, $user, $password);

            if ($conn->connect_error) {
                throw new Exception("فشل الاتصال: " . $conn->connect_error);
            }

            $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
            $conn->select_db($dbname);

            if ($conn->multi_query($sql)) {
                do {
                    $conn->store_result();
                } while ($conn->more_results() && $conn->next_result());
            }

            $success = true;
            $message = "✅ تم التنصيب بنجاح";

            $conn->close();

        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
        }

    } else {
        $message = "❌ اختر ملف SQL صحيح";
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>Installer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex justify-content-center align-items-center vh-100">

<div class="card p-4" style="width:450px;">
<h4 class="text-center mb-3">تنصيب النظام</h4>

<?php if($message): ?>
<div class="alert <?= $success ? 'alert-success':'alert-danger' ?>">
<?= $message ?>
</div>
<?php endif; ?>

<?php if(!$success): ?>
<form method="post" enctype="multipart/form-data">
<input class="form-control mb-2" name="host" value="localhost" required>
<input class="form-control mb-2" name="user" value="root" required>
<input type="password" class="form-control mb-2" name="password">
<input class="form-control mb-2" name="dbname" required placeholder="Database Name">
<input type="file" class="form-control mb-2" name="sql_file" accept=".sql" required>
<button class="btn btn-primary w-100">تنصيب</button>
</form>
<?php endif; ?>

</div>

</body>
</html>
