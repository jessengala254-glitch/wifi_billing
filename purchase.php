<?php
require_once __DIR__ . '/inc/functions.php';
session_start();

if (!isset($_GET['plan_id'])) die('Plan required.');
$plan_id = intval($_GET['plan_id']);

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

$stmt = $pdo->prepare("SELECT * FROM plans WHERE id=? LIMIT 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) die('Plan not found.');

// Get return URL (for captive portal redirect after payment)
$return_url = $_GET['return_url'] ?? 'http://www.google.com';
$from_captive = $_GET['from_captive'] ?? false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Purchase Plan | Leo Konnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purchase-page">

<!-- Header -->
<header class="navbar">
  <div class="container nav-flex">
    <a href="index.php" class="logo">
      <span class="icon-wifi"></span> Leo <span>Konnect</span>
    </a>
  </div>
</header>

<div class="purchase-wrapper">
  <div class="purchase-container">
    <div class="purchase-header">
      <div class="plan-badge">Selected Plan</div>
      <h2><?= htmlspecialchars($plan['title']) ?></h2>
      <div class="plan-price">Ksh <?= number_format($plan['price'], 0) ?></div>
      <p class="plan-description">Unlimited high-speed internet access</p>
    </div>

    <form id="payForm" class="payment-form">
      <input type="hidden" name="action" value="initiate">
      <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
      <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
      <input type="hidden" name="ip" id="userIp" value="<?= $_SERVER['REMOTE_ADDR'] ?>">
      <input type="hidden" name="mac" id="userMac" value="">
      <input type="hidden" id="from_captive" value="<?= $from_captive ? '1' : '0' ?>">

      <div class="form-group">
        <label>
          <span class="icon-phone"></span>
          M-Pesa Phone Number
        </label>
        <input 
          type="tel" 
          name="phone" 
          required 
          placeholder="07xxxxxxxx" 
          pattern="[0-9]{10}"
          maxlength="10"
          class="phone-input"
        >
        <small>Enter your M-Pesa number to receive STK push</small>
      </div>

      <div class="payment-summary">
        <div class="summary-row">
          <span>Plan Price</span>
          <span class="amount">Ksh <?= number_format($plan['price'], 0) ?></span>
        </div>
        <div class="summary-row total">
          <span>Total Amount</span>
          <span class="amount">Ksh <?= number_format($plan['price'], 0) ?></span>
        </div>
      </div>

      <button type="submit" class="btn-payment">
        <span class="icon-wallet"></span>
        Pay Ksh <?= number_format($plan['price'], 0) ?> via M-Pesa
      </button>
    </form>

    <div id="status" class="status-message"></div>
    <div id="voucher" class="voucher-info"></div>

    <div class="purchase-footer">
      <p><span class="icon-shield"></span> Secure payment powered by M-Pesa</p>
      <p><span class="icon-support"></span> Need help? Contact support</p>
    </div>
  </div>
</div>

<script>
const payForm = document.getElementById('payForm');
const statusDiv = document.getElementById('status');
const voucherDiv = document.getElementById('voucher');
const fromCaptive = document.getElementById('from_captive').value === '1';

// Auto-detect user's MAC address (if available from network info)
async function detectUserInfo() {
    // IP is already set from PHP
    // MAC address detection would require backend integration with MikroTik
    // For now, we'll let the backend handle MAC detection when processing payment
}

detectUserInfo();

payForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    statusDiv.className = "status-message loading";
    statusDiv.innerHTML = '<div class="spinner"></div> <span>Initiating payment...</span>';
    voucherDiv.innerHTML = "";

    try {
        const formData = new FormData(payForm);
        const res = await fetch('api/payments.php', { method: 'POST', body: formData });
        const data = await res.json();

        // Check for errors
        if (data.error) {
            statusDiv.className = "status-message error";
            statusDiv.innerHTML = `<span class="icon-shield"></span> <strong>Error:</strong> ${data.error}`;
            return;
        }

        if (!data || !data.payment_id) {
            statusDiv.className = "status-message error";
            statusDiv.innerHTML = '<span class="icon-shield"></span> Could not start payment. Please try again.';
            return;
        }

        const paymentId = data.payment_id;
        const returnUrl = new URLSearchParams(formData).get('return_url');

        // Check if using SmartPay (STK push) or mock
        if (data.gateway === 'SmartPay') {
            statusDiv.className = "status-message info";
            statusDiv.innerHTML = '<div class="spinner"></div> <span><strong>STK push sent!</strong><br>Please check your phone and enter your M-Pesa PIN</span>';
            
            // Poll for payment confirmation
            const poll = setInterval(async () => {
                const checkRes = await fetch(`api/check_payment.php?payment_id=${paymentId}`);
                const checkData = await checkRes.json();

                if (checkData.status === 'success') {
                    clearInterval(poll);
                    statusDiv.className = "status-message success";
                    statusDiv.innerHTML = '<span class="icon-rocket"></span> <strong>Payment Successful!</strong>';
                    
                    voucherDiv.className = "voucher-info show";
                    voucherDiv.innerHTML = `
                        <div class="voucher-card">
                            <h3><span class="icon-lock"></span> Your WiFi Credentials</h3>
                            <div class="credential-row">
                                <span class="label">Username:</span>
                                <span class="value">${checkData.username}</span>
                            </div>
                            <div class="credential-row">
                                <span class="label">Password:</span>
                                <span class="value">${checkData.password}</span>
                            </div>
                            <div class="credential-row">
                                <span class="label">Valid Until:</span>
                                <span class="value">${checkData.expires}</span>
                            </div>
                            <p class="voucher-note">
                                <span class="icon-support"></span> 
                                Use these credentials to login to the WiFi network
                            </p>
                        </div>
                    `;
                    
                    // If coming from captive portal, redirect after 5 seconds
                    if (fromCaptive) {
                        setTimeout(() => {
                            window.location.href = returnUrl || 'http://www.google.com';
                        }, 5000);
                    }
                } else if (checkData.status === 'failed') {
                    clearInterval(poll);
                    statusDiv.className = "status-message error";
                    statusDiv.innerHTML = '<span class="icon-shield"></span> <strong>Payment failed.</strong> Please try again.';
                } else {
                    statusDiv.className = "status-message info";
                    statusDiv.innerHTML = '<div class="spinner"></div> <span>Waiting for payment confirmation...<br><small>Please enter your M-Pesa PIN on your phone</small></span>';
                }
            }, 3000); // Check every 3 seconds

            // Stop polling after 2 minutes
            setTimeout(() => {
                clearInterval(poll);
                if (!voucherDiv.innerHTML) {
                    statusDiv.className = "status-message warning";
                    statusDiv.innerHTML = '<span class="icon-clock"></span> <strong>Payment timeout.</strong> Please check your M-Pesa messages or try again.';
                }
            }, 120000);

        } else if (data.gateway === 'MOCK') {
            // Mock payment - instant success
            statusDiv.className = "status-message success";
            statusDiv.innerHTML = '<span class="icon-rocket"></span> <strong>Payment Successful!</strong> <small>(Test Mode)</small>';
            
            if (data.voucher) {
                // Handle both new voucher and topped-up voucher response formats
                const username = data.voucher.username || (data.voucher.voucher && data.voucher.voucher.username);
                const password = data.voucher.password || (data.voucher.voucher && data.voucher.voucher.password);
                const expiry = data.voucher.new_expiry || data.voucher.expiry || (data.voucher.voucher && data.voucher.voucher.expiry);
                
                voucherDiv.className = "voucher-info show";
                voucherDiv.innerHTML = `
                    <div class="voucher-card">
                        <h3><span class="icon-lock"></span> Your WiFi Credentials</h3>
                        <div class="credential-row">
                            <span class="label">Username:</span>
                            <span class="value">${username}</span>
                        </div>
                        <div class="credential-row">
                            <span class="label">Password:</span>
                            <span class="value">${password}</span>
                        </div>
                        <div class="credential-row">
                            <span class="label">Valid Until:</span>
                            <span class="value">${expiry}</span>
                        </div>
                        <p class="voucher-note">
                            <span class="icon-support"></span> 
                            Use these credentials to login to the WiFi network
                        </p>
                    </div>
                `;
                
                if (fromCaptive) {
                    setTimeout(() => {
                        window.location.href = returnUrl || 'http://www.google.com';
                    }, 3000);
                }
            }
        }

    } catch (err) {
        statusDiv.className = "status-message error";
        statusDiv.innerHTML = `<span class="icon-shield"></span> <strong>Payment error:</strong> ${err.message}`;
    }
});
</script>
</body>
</html>
