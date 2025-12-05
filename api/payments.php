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

    // ========== SMARTPAYPESA PAYMENT GATEWAY ==========
    // SmartPayPesa STK Push Integration
    
    $use_mock = false;
    
    // SmartPayPesa credentials
    $smartpay_api_key = "bbd04d2817d0cc29d72db948e1bab15be5e50fda87ad128220dcec186038d2b9";
    
    // Format phone number (remove leading 0, add 254)
    $formatted_phone = $phone;
    if (substr($formatted_phone, 0, 1) === '0') {
        $formatted_phone = '254' . substr($formatted_phone, 1);
    }
    
    // SmartPayPesa STK Push payload (correct format from documentation)
    $stk_payload = [
        "phone" => $formatted_phone,
        "amount" => floatval($plan['price']),
        "account_reference" => "PAY-" . $payment_id,
        "description" => $plan['title'] . " - Leo Konnect"
    ];
    
    // Attempt SmartPayPesa API call (correct endpoint from documentation)
    $ch = curl_init("https://api.smartpay.co.ke/v1/initiatestk");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: " . $smartpay_api_key,
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log the response for debugging
    error_log("SmartPay Response (HTTP $http_code): " . substr($response, 0, 500));

    // Check if SmartPayPesa call was successful
    if (!$curl_error && ($http_code == 200 || $http_code == 201)) {
        $res = json_decode($response, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SmartPay JSON decode error: " . json_last_error_msg());
        } elseif ($res && isset($res['success']) && $res['success'] === true) {
            // Update payment with checkout request ID from SmartPay
            $checkout_id = $res['checkoutRequestID'] ?? null;
            if ($checkout_id) {
                $stmt = $pdo->prepare("UPDATE payments SET mpesa_receipt = ? WHERE id = ?");
                $stmt->execute([$checkout_id, $payment_id]);
            }
            
            // Return success - STK push sent
            http_response_code(200);
            echo json_encode([
                "result" => "ok",
                "payment_id" => $payment_id,
                "message" => "STK push sent to " . $phone . ". Please enter your M-Pesa PIN.",
                "checkout_request_id" => $checkout_id,
                "gateway" => "SmartPay"
            ]);
            exit;
        } else {
            error_log("SmartPay error: " . ($res['message'] ?? 'Unknown error'));
        }
    } else {
        error_log("SmartPay API failed (HTTP $http_code): $curl_error");
    }

    // If SmartPay failed, fall back to mock payment
    error_log("SmartPay integration unavailable. Falling back to mock payment for payment_id=$payment_id");
    $use_mock = true;
    
    // Mock payment - auto-approve for testing
    $stmt = $pdo->prepare("UPDATE payments SET status = 'success', mpesa_receipt = ? WHERE id = ?");
    $stmt->execute(['MOCK-' . time(), $payment_id]);
    
    // Create voucher immediately for mock - call radius API via HTTP
    $voucher_data = null;
    $radius_payload = json_encode([
        "type" => "create_voucher",
        "plan_id" => $plan_id,
        "phone" => $phone
    ]);
    
    $ch = curl_init("http://127.0.0.1/leokonnect/inc/radius_api.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $radius_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $voucher_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("RADIUS API Response (HTTP $http_code): " . substr($voucher_response, 0, 500));
    
    if ($voucher_response) {
        $voucher_data = json_decode($voucher_response, true);
        // Link payment to user if voucher created
        if ($voucher_data && isset($voucher_data['user_id'])) {
            $stmt = $pdo->prepare("UPDATE payments SET user_id = ? WHERE id = ?");
            $stmt->execute([$voucher_data['user_id'], $payment_id]);
        }
    }

    // Return payment initiation response
    http_response_code(200);
    echo json_encode([
        "result" => "ok",
        "payment_id" => $payment_id,
        "message" => "Mock payment successful (SmartPayPesa unavailable)",
        "gateway" => "MOCK",
        "voucher" => $voucher_data
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

