<?php
// api/payments.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/mpesa.php';
session_start();
$pdo = db();
$action = $_POST['action'] ?? null;

if ($action === 'initiate') {
    $plan_id = intval($_POST['plan_id']);
    $phone = $_POST['phone'];
    $ip = $_POST['ip'] ?? null;
    $mac = $_POST['mac'] ?? null;

    // We no longer require $_SESSION['user_id']
    $user_id = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare("SELECT price FROM plans WHERE id=? LIMIT 1");
    $stmt->execute([$plan_id]); 
    $plan = $stmt->fetch();
    if (!$plan) { die('Plan not found'); }

    // create a pending payment
    // $stmt = $pdo->prepare("INSERT INTO payments (user_id, plan_id, amount, phone, status, ip, mac) VALUES (?,?,?,?, 'pending', ?, ?)");
    // $stmt->execute([$user_id, $plan_id, $plan['price'], $phone, $ip, $mac]);
    // $payment_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, plan_id, amount, phone, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$user_id, $plan_id, $plan['price'], $phone]);
    $payment_id = $pdo->lastInsertId();


    // Initiate STK push
    $config = require __DIR__ . '/../inc/config.php';
    $callback = $config['mpesa']['callback_url'] . '?payment_id=' . $payment_id;

    try {
        $res = mpesa_stk_push($phone, $plan['price'], "LEOKONNECT{$payment_id}", "Payment for plan {$plan_id}", $callback);
        $checkout = $res['CheckoutRequestID'] ?? null;
        $stmt = $pdo->prepare("UPDATE payments SET mpesa_receipt=? WHERE id=?");
        $stmt->execute([$checkout, $payment_id]);

        header('Location: ../dashboard.php?payment_started=1'); 
        exit;
    } catch (Exception $e) {
        $stmt = $pdo->prepare("UPDATE payments SET status='failed' WHERE id=?");
        $stmt->execute([$payment_id]);
        die("Payment initiation error: " . $e->getMessage());
    }
}


http_response_code(400);
echo "Bad request";
