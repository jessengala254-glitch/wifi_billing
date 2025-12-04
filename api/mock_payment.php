<?php
/**
 * Mock Payment Simulator
 * Allows testing the complete payment flow without real IntaSend credentials
 */

session_start();

// Include config and functions
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isset($_GET['payment_id'])) die('Payment ID required.');
$payment_id = $_GET['payment_id'];

// Check if this is a confirmation request
$action = $_GET['action'] ?? null;

if ($action === 'confirm') {
    $pdo = db();
    
    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        die("Payment not found.");
    }
    
    // Mark as paid (status values are 'pending', 'success', 'failed')
    $stmt = $pdo->prepare("UPDATE payments SET status = 'success' WHERE id = ?");
    $stmt->execute([$payment_id]);
    
    // Get phone number from payment
    $phone = $payment['phone'];
    $plan_id = $payment['plan_id'];
    
    // Mark as paid (status values are 'pending', 'success', 'failed')
    $stmt = $pdo->prepare("UPDATE payments SET status = 'success' WHERE id = ?");
    $stmt->execute([$payment_id]);
    
    // ===== Call radius_api.php to create voucher =====
    // This handles all voucher creation, RADIUS records, MAC binding, expiry, etc.
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
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $voucher_response = json_decode($response, true);
    
    // Extract voucher details from response (radius_api returns nested object)
    $voucher_data = $voucher_response['voucher'] ?? $voucher_response;
    $username = $voucher_data['username'] ?? null;
    $password = $voucher_data['password'] ?? null;
    $expiry = $voucher_data['expiry'] ?? null;
    
    if (!$username || !$password) {
        error_log("ERROR: Failed to create voucher for payment_id=$payment_id. Response: " . $response);
        // Still mark payment as success but log the error
        echo "Payment confirmed! Voucher creation is processing.";
        header("refresh:3; url=../../purchase.php", true, 303);
        exit;
    }
    
    // Send SMS with credentials from voucher
    $sms_message = "Leo Konnect: Username: $username | Password: $password | Valid: $expiry";
    send_sms($phone, $sms_message);
    
    // Log success
    error_log("Mock Payment Success: Payment $payment_id -> Voucher $username for $phone");
    
    // Update payment with created voucher info
    $stmt = $pdo->prepare("UPDATE payments SET username = ?, password = ? WHERE id = ?");
    $stmt->execute([$username, $password, $payment_id]);
    
    echo "Payment confirmed! Voucher created and SMS sent.";
    header("refresh:2; url=../../purchase.php", true, 303);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Mock M-Pesa Payment Confirmation</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f0f0f0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        h2 {
            color: #333;
            margin-top: 0;
        }
        .info {
            background: #f9f9f9;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
            text-align: left;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        button {
            flex: 1;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .confirm {
            background: #4CAF50;
            color: white;
        }
        .confirm:hover {
            background: #45a049;
        }
        .cancel {
            background: #f44336;
            color: white;
        }
        .cancel:hover {
            background: #da190b;
        }
        .demo-note {
            background: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 12px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>M-Pesa Payment Confirmation</h2>
        
        <div class="info">
            <p><strong>Payment ID:</strong> <?= htmlspecialchars($payment_id) ?></p>
            <p>This is a <strong>test/mock payment</strong> page. In production, you would complete M-Pesa payment here.</p>
        </div>

        <div class="demo-note">
            <p>🔧 <strong>Demo Mode:</strong> This system is running in mock mode for testing. Once you have valid IntaSend credentials, set <code>$MOCK_MODE = false</code> in <code>api/payments.php</code></p>
        </div>

        <div class="button-group">
            <button class="confirm" onclick="confirmPayment()">✓ Confirm Payment</button>
            <button class="cancel" onclick="window.close()">✗ Cancel</button>
        </div>
    </div>

    <script>
        function confirmPayment() {
            window.location.href = `?payment_id=<?= urlencode($payment_id) ?>&action=confirm`;
        }
    </script>
</body>
</html>
