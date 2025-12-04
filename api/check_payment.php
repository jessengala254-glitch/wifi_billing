<?php
require_once __DIR__ . '/../inc/functions.php';
header('Content-Type: application/json');

$payment_id = $_GET['payment_id'] ?? null;

if (!$payment_id) {
    echo json_encode(["status" => "missing"]);
    exit;
}

$pdo = db();

// Query payment with joined voucher details
$stmt = $pdo->prepare("
    SELECT p.*, v.username, v.password, v.expiry
    FROM payments p
    LEFT JOIN vouchers v ON v.phone = p.phone AND v.status = 'active'
    WHERE p.id = ? 
    LIMIT 1
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo json_encode(["status" => "notfound"]);
    exit;
}

if ($payment['status'] === 'success') {
    if ($payment['username']) {
        echo json_encode([
            "status" => "success",
            "username" => $payment['username'],
            "password" => $payment['password'],
            "expires"  => $payment['expiry']
        ]);
    } else {
        // Payment completed but voucher not yet created
        echo json_encode(["status" => "processing"]);
    }
    exit;
}

if ($payment['status'] === 'failed') {
    echo json_encode([
        "status" => "failed",
        "desc"   => "Payment was declined or cancelled"
    ]);
    exit;
}

// Otherwise still pending
echo json_encode(["status" => "pending"]);
