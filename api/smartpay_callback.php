<?php
// api/smartpay_callback.php - SmartPay Webhook Handler
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/radius_api.php';

// Log all incoming requests
file_put_contents(__DIR__ . '/../logs/smartpay_callback.txt', date('Y-m-d H:i:s') . " - " . file_get_contents('php://input') . "\n", FILE_APPEND);

header('Content-Type: application/json');

try {
    $pdo = db();
    $pdo->exec("SET time_zone = '+03:00'");

    // Get JSON payload from SmartPay webhook
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
        exit;
    }

    // Extract SmartPay webhook data structure
    $stkCallback = $data['Body']['stkCallback'] ?? null;
    
    if (!$stkCallback) {
        error_log("SmartPay webhook: Invalid structure");
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid webhook structure"]);
        exit;
    }

    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
    $resultCode = $stkCallback['ResultCode'] ?? null;
    $resultDesc = $stkCallback['ResultDesc'] ?? '';
    
    // Extract metadata
    $mpesaReceipt = null;
    $amount = null;
    $phone = null;
    
    if (isset($stkCallback['CallbackMetadata']['Item'])) {
        foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $mpesaReceipt = $item['Value'];
            } elseif ($item['Name'] === 'Amount') {
                $amount = $item['Value'];
            } elseif ($item['Name'] === 'PhoneNumber') {
                $phone = $item['Value'];
            }
        }
    }

    // Find payment by checkout request ID
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE mpesa_receipt = ? LIMIT 1");
    $stmt->execute([$checkoutRequestID]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        error_log("SmartPay webhook: Payment not found for CheckoutRequestID: $checkoutRequestID");
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Payment not found"]);
        exit;
    }

    $payment_id = $payment['id'];

    // Check if payment is successful (ResultCode 0 = success)
    if ($resultCode === 0 || $resultCode === '0') {
        // Update payment status with M-Pesa receipt
        $stmt = $pdo->prepare("UPDATE payments SET status = 'success', mpesa_receipt = ?, transaction_time = NOW() WHERE id = ?");
        $stmt->execute([$mpesaReceipt, $payment_id]);

        // Create RADIUS voucher
        $voucher_data = create_radius_voucher($pdo, $payment['plan_id'], $payment['phone'], null, null);

        if ($voucher_data && isset($voucher_data['user_id'])) {
            // Link payment to user
            $stmt = $pdo->prepare("UPDATE payments SET user_id = ? WHERE id = ?");
            $stmt->execute([$voucher_data['user_id'], $payment_id]);

            error_log("SmartPay: Voucher created for payment_id=$payment_id, receipt=$mpesaReceipt, user_id={$voucher_data['user_id']}");
        }

        http_response_code(200);
        echo json_encode([
            "ResultCode" => 0,
            "ResultDesc" => "Payment processed successfully"
        ]);
    } else {
        // Payment failed
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed', mpesa_receipt = ? WHERE id = ?");
        $stmt->execute([$checkoutRequestID, $payment_id]);

        error_log("SmartPay: Payment failed for payment_id=$payment_id, ResultCode=$resultCode, Desc=$resultDesc");

        http_response_code(200);
        echo json_encode([
            "ResultCode" => $resultCode,
            "ResultDesc" => $resultDesc
        ]);
    }

} catch (Exception $e) {
    error_log("SmartPay callback error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
