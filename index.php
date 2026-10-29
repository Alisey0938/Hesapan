<?php
/**
 * Single-file Admin Panel and API Gateway
 * Domain Path: socialmedia-ad.ir/panel.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ============================================================================
// DATABASE CONFIGURATION (SQLite for single-file deployment or MySQL)
// ============================================================================
$dbFile = __DIR__ . '/app_admin_database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ساخت جدول کاربران و پشتیبان‌ها
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        full_name TEXT,
        subscription_status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS backups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        backup_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ایجاد کاربر ادمین اولیه
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, subscription_status) VALUES ('admin', 'admin123', 'مدیر کل سیستم', 'active')");
        $stmt->execute();
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// ============================================================================
// API ENDPOINT FOR APP (POST / GET)
// ============================================================================
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'backup') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $backupData = $_POST['backup_data'] ?? '';

        $stmt = $db->prepare("SELECT id FROM users WHERE username = :u AND password = :p");
        $stmt->execute([':u' => $username, ':p' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $stmtBackup = $db->prepare("INSERT INTO backups (user_id, backup_data) VALUES (:uid, :data)");
            $stmtBackup->execute([':uid' => $user['id'], ':data' => $backupData]);
            echo json_encode(['status' => 'success', 'message' => 'بکاپ با موفقیت ذخیره شد']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'نام کاربری یا کلمه عبور اشتباه است']);
        }
        exit;
    }

    if ($action === 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $fullname = $_POST['full_name'] ?? 'کاربر جدید';

        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name) VALUES (:u, :p, :f)");
            $stmt->execute([':u' => $username, ':p' => $password, ':f' => $fullname]);
            echo json_encode(['status' => 'success', 'message' => 'ثبت نام انجام شد']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'نام کاربری تکراری است']);
        }
        exit;
    }
}

// ============================================================================
// ADMIN PANEL HTML INTERFACE
// ============================================================================
$users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$backupsCount = $db->query("SELECT COUNT(*) FROM backups")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت کل حسابداری - socialmedia-ad.ir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: Tahoma, sans-serif; }
        .sidebar { background: #1e1e2d; color: #fff; min-height: 100vh; padding: 20px; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <h4 class="text-center my-3 text-warning">مدیریت کل هلو</h4>
            <hr>
            <ul class="nav nav-pills flex-column">
                <li class="nav-item"><a href="#" class="nav-link active">داشبورد اصلی</a></li>
                <li class="nav-item"><a href="#" class="nav-link text-white">کاربران اپلیکیشن</a></li>
                <li class="nav-item"><a href="#" class="nav-link text-white">نسخه‌های پشتیبان</a></li>
                <li class="nav-item"><a href="#" class="nav-link text-white">تنظیمات API</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <h3 class="mb-4">داشبورد نظارت و مدیریت اپلیکیشن حسابداری</h3>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card p-3 bg-primary text-white">
                        <h5>تعداد کاربران ثبت شده</h5>
                        <h2><?= count($users) ?> کاربر</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 bg-success text-white">
                        <h5>تعداد بکاپ‌های ذخیره‌شده</h5>
                        <h2><?= $backupsCount ?> فایل</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 bg-dark text-white">
                        <h5>آدرس API فعال</h5>
                        <small>socialmedia-ad.ir/panel.php</small>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card p-4">
                <h5 class="card-title mb-3">لیست کاربران و اشتراک‌ها</h5>
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>نام کاربری</th>
                            <th>نام کامل</th>
                            <th>وضعیت اشتراک</th>
                            <th>تاریخ ثبت‌نام</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><span class="badge bg-success"><?= $user['subscription_status'] ?></span></td>
                            <td><?= $user['created_at'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger">مسدودسازی</button>
                                <button class="btn btn-sm btn-outline-info">مشاهده بکاپ‌ها</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
