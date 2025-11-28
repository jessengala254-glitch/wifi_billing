<?php
// auth.php — Clean, separated admin and voucher login
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// -------------------- ADMIN REGISTRATION --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register_admin') {
    $name     = trim($_POST['name'] ?? "");
    $email    = trim($_POST['email'] ?? "");
    $phone    = trim($_POST['phone'] ?? "");
    $password = $_POST['password'] ?? null;

    if (!$name || !$email || !$phone || !$password) {
        header("Location: ../admin/register_admin.php?error=empty");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header("Location: ../admin/register_admin.php?error=exists");
        exit;
    }

    $hashed = hash_password($password);
    $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,?)");
    $stmt->execute([$name,$email,$phone,$hashed,'admin']);

    header("Location: ../admin/admin_login.php?registered=1");
    exit;
}

// -------------------- LOGIN --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");

    // --- Admin Login ---
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='admin' LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && verify_password($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['role']    = 'admin';

        log_action($pdo, $admin['id'], "Admin Login", "Admin logged in successfully", "success");
        header("Location: ../admin/index.php");
        exit;
    }

    //--- Voucher User Login ---
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE username=? LIMIT 1");
    $stmt->execute([$username]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        header("Location: ../login.php?invalid=1");
        exit;
    }

    // Check password
    if (!verify_password($password, $voucher['password'])) {
        header("Location: ../login.php?error=1");
        exit;
    }

    // 🚫 BLOCK LOGIN IF VOUCHER IS EXPIRED
    if (strtotime($voucher['expiry']) < time()) {
        header("Location: ../login.php?expired=1");
        exit;
    }

    // Fetch the user from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE voucher_id = ? LIMIT 1");
    $stmt->execute([$voucher['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🚫 If somehow not found, create user once
    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, voucher_id, status) VALUES (?,?,?,?,?)");
        $stmt->execute([$voucher['username'], $voucher['password'], 'user', $voucher['id'], 'active']);
        $user_id = $pdo->lastInsertId();
    } else {
        $user_id = $user['id'];
    }

    // ---- RADCHECK ensure entry exists ----
    $check = $pdo->prepare("SELECT id FROM radcheck WHERE username=? LIMIT 1");
    $check->execute([$username]);

    if (!$check->fetch()) {
        $insert = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value)
                                 VALUES (?, 'Cleartext-Password', ':=', ?)");
        $insert->execute([$username, $password]);
    }

    // ---- Store session ----
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role']    = 'user';
    $_SESSION['voucher'] = $username;

    log_action($pdo, $user_id, "Voucher Login", "User logged in using voucher", "success");
    header("Location: /leokonnect/dashboard.php");
    exit;
}

// -------------------- LOGOUT --------------------
if ($action === 'logout') {
    $user_id = $_SESSION['user_id'] ?? 0;
    log_action($pdo, $user_id, "Logout", "User logged out", "info");

    session_unset();
    session_destroy();

    header("Location: ../login.php?message=logged_out");
    exit;
}
