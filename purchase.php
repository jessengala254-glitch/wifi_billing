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
<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="purchase-container">
  <h2><?= htmlspecialchars($plan['title']) ?></h2>
  <p class="plan-price">Ksh <?= number_format($plan['price'], 2) ?></p>

  <form id="payForm">
    <input type="hidden" name="action" value="initiate">
    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
    <input type="hidden" id="from_captive" value="<?= $from_captive ? '1' : '0' ?>">

    <label>Phone Number (M-Pesa)</label>
    <input name="phone" required placeholder="07xxxxxxxx">

    <label>IP Address (optional)</label>
    <input name="ip" value="<?= $_SERVER['REMOTE_ADDR'] ?>">

    <label>MAC Address (optional)</label>
    <input name="mac" placeholder="AA:BB:CC:DD:EE:FF">

    <button type="submit">Pay Ksh <?= number_format($plan['price'], 2) ?></button>
  </form>

  <div id="status" style="margin-top:20px;"></div>
  <div id="voucher" style="margin-top:20px; font-weight:bold; white-space: pre-line;"></div>
</div>

<script>
const payForm = document.getElementById('payForm');
const statusDiv = document.getElementById('status');
const voucherDiv = document.getElementById('voucher');
const fromCaptive = document.getElementById('from_captive').value === '1';

payForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    statusDiv.innerText = "Initializing payment...";
    voucherDiv.innerText = "";

    try {
        const formData = new FormData(payForm);
        const res = await fetch('../leokonnect/api/payments.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (!data || !data.payment_id || !data.authorization_url) {
            statusDiv.innerText = "Could not start payment. Try again.";
            return;
        }

        // Open IntaSend payment page in new tab
        window.open(data.authorization_url, '_blank');
        statusDiv.innerText = "Payment page opened. Waiting for confirmation...";

        const paymentId = data.payment_id;
        const returnUrl = new URLSearchParams(formData).get('return_url');

        // Poll for payment confirmation
        const poll = setInterval(async () => {
            const checkRes = await fetch(`../leokonnect/api/check_payment.php?payment_id=${paymentId}`);
            const checkData = await checkRes.json();

            if (checkData.status === 'success') {
                clearInterval(poll);
                statusDiv.innerText = "✅ Payment successful!";
                voucherDiv.innerText = `Username: ${checkData.username}\nPassword: ${checkData.password}\nValid until: ${checkData.expires}\n\n📱 Use these credentials to login to the WiFi network.`;
                
                // If coming from captive portal, redirect after 3 seconds
                if (fromCaptive) {
                    setTimeout(() => {
                        // Redirect to return URL or try to open WiFi login
                        window.location.href = returnUrl || 'http://www.google.com';
                    }, 3000);
                }
            } else if (checkData.status === 'failed') {
                clearInterval(poll);
                statusDiv.innerText = "❌ Payment failed: " + (checkData.desc || 'Declined');
            } else {
                statusDiv.innerText = "⏳ Waiting for payment confirmation...";
            }
        }, 5000);
    } catch (err) {
        statusDiv.innerText = "❌ Payment error: " + err.message;
    }
});
</script>
</body>
</html>
