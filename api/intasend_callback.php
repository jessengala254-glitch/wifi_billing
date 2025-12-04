<?php
// api/intasend_callback.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/config.php';
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

$logFile = __DIR__ . '/../logs/intasend_callback.txt';

// Read raw JSON from IntaSend
$payload = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " | RAW: " . $payload . "\n", FILE_APPEND);

// Get payment_id from query string or JSON metadata
$json_data = json_decode($payload, true);
$payment_id = intval($_GET['payment_id'] ?? ($json_data['metadata']['payment_id'] ?? 0));

if (!$payment_id) {
    file_put_contents($logFile, "Missing payment_id\n\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error'=>'Missing payment_id']);
    exit;
}

// Fetch payment record
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    file_put_contents($logFile, "Payment not found: $payment_id\n\n", FILE_APPEND);
    http_response_code(404);
    echo json_encode(['error'=>'Payment not found']);
    exit;
}

// Update payment status
$status = $json_data['status'] ?? 'failed';
$stmt = $pdo->prepare("UPDATE payments SET status = ?, transaction_time = NOW() WHERE id = ?");
$stmt->execute([$status, $payment_id]);

file_put_contents($logFile, "Payment ID $payment_id updated to status: $status\n", FILE_APPEND);

if ($status === 'success') {
    // ========== CREATE VOUCHER via radius_api.php ==========
    try {
        $phone = $payment['phone'];
        $plan_id = intval($payment['plan_id']);

        // Call radius_api.php to create voucher (handles all creation, RADIUS records, top-ups, etc.)
        $ch = curl_init("http://192.168.10.68/leokonnect/inc/radius_api.php");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-API-KEY: change_me"  // Use the default API key from config
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'type' => 'create_voucher',
            'phone' => $phone,
            'plan_id' => $plan_id
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $voucher_response = json_decode($response, true);
        file_put_contents($logFile, "Voucher Creation Response (HTTP $http_code): " . substr($response, 0, 500) . "\n", FILE_APPEND);
        
        if ($http_code === 200 && isset($voucher_response['voucher'])) {
            $voucher_data = $voucher_response['voucher'];
            $voucher_username = $voucher_data['username'] ?? null;
            $voucher_password = $voucher_data['password'] ?? null;
            $expiry = $voucher_data['expiry'] ?? null;
            
            // Send SMS with voucher credentials
            $sms_message = "Leo Konnect: Username: $voucher_username | Password: $voucher_password | Valid: $expiry";
            $result = send_sms($phone, $sms_message);
            file_put_contents($logFile, "SMS sent to $phone: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            
            // Update payment with voucher info
            $stmt = $pdo->prepare("UPDATE payments SET username = ?, password = ? WHERE id = ?");
            $stmt->execute([$voucher_username, $voucher_password, $payment_id]);
        } else {
            file_put_contents($logFile, "ERROR: Failed to create voucher. Response: " . $response . "\n", FILE_APPEND);
        }

    } catch (Exception $e) {
        file_put_contents($logFile, "ERROR creating voucher: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

file_put_contents($logFile, "\n", FILE_APPEND);

// Respond to IntaSend
http_response_code(200);
echo json_encode(['status'=>'ok']);

