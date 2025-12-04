<?php
// api/payments.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $pdo->exec("SET time_zone = '+03:00'");

    // Validate input
    $phone = $_POST['phone'] ?? null;
    $plan_id = $_POST['plan_id'] ?? null;

    if (!$phone || !$plan_id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing phone or plan_id"]);
        exit;
    }

    // Fetch plan
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id=? AND active=1 LIMIT 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        http_response_code(404);
        echo json_encode(["error" => "Invalid plan"]);
        exit;
    }

    // Create payment record (user_id is optional for guest payments)
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, phone, plan_id, amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $phone, $plan_id, $plan['price']]);
    $payment_id = $pdo->lastInsertId(); // Get the auto-generated ID

    // ========== PAYMENT GATEWAY ROUTING ==========
    // Try IntaSend first, fall back to mock if it fails
    
    // Try real IntaSend payment
    $checkout_url = null;
    $use_mock = false;
    
    $payload = [
        "amount" => floatval($plan['price']),
        "currency" => "KES",
        "customer" => [
            "phone_number" => $phone
        ],
        "payment_methods" => ["mpesa"],
        "metadata" => [
            "payment_id" => $payment_id
        ],
        "callback_url" => "https://192.168.10.68/leokonnect/api/intasend_callback.php?payment_id=" . $payment_id
    ];

    // Attempt IntaSend API call
    $ch = curl_init("https://api.intasend.com/v1/invoices/");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer ISSecretKey_live_8a79adee-83bc-419f-a139-587c80baf930"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Check if IntaSend call was successful
    if (!$curl_error && $http_code == 200) {
        $res = json_decode($response, true);
        if ($res) {
            $checkout_url = $res['url'] ?? $res['checkout_link'] ?? $res['invoice_url'] ?? null;
        }
    }

    // If IntaSend failed or invalid, fall back to mock payment
    if (!$checkout_url) {
        error_log("IntaSend integration unavailable (HTTP $http_code). Falling back to mock payment for payment_id=$payment_id");
        $use_mock = true;
        $checkout_url = "http://192.168.10.68/leokonnect/api/mock_payment.php?payment_id=" . $payment_id;
    }

    // Return payment initiation response
    http_response_code(200);
    echo json_encode([
        "result" => "ok",
        "payment_id" => $payment_id,
        "authorization_url" => $checkout_url,
        "gateway" => $use_mock ? "MOCK" : "IntaSend"
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

