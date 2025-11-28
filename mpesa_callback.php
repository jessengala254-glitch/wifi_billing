<?php
// public/mpesa_callback.php
// Handles M-Pesa payment confirmation and creates FreeRADIUS account + session record.
// Notes: relies on functions in inc/functions.php (create_radius_account, set_radius_expiration, generate_username, generate_password, send_email, send_sms, hash_password)
// and optionally inc/mikrotik.php (mikrotik_add_hotspot_user).

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/mikrotik.php'; // optional; if missing, comment out mikrotik calls

$logFile = __DIR__ . '/../logs/mpesa_callback.log';
$payload = file_get_contents('php://input');
file_put_contents($logFile, date('c') . " - payload: " . $payload . PHP_EOL, FILE_APPEND);

$data = json_decode($payload, true);
$payment_id = $_GET['payment_id'] ?? null;
$pdo = db();

if (!$data || !$payment_id) {
    http_response_code(400);
    file_put_contents($logFile, date('c') . " - missing data or payment_id\n", FILE_APPEND);
    echo json_encode(['result' => 'no data']);
    exit;
}

$stkCallback = $data['Body']['stkCallback'] ?? null;
if (!$stkCallback) {
    file_put_contents($logFile, date('c') . " - no stkCallback\n", FILE_APPEND);
    echo json_encode(['result' => 'invalid']);
    exit;
}

$checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
$resultCode = intval($stkCallback['ResultCode'] ?? 1);
$resultDesc = $stkCallback['ResultDesc'] ?? '';

try {
    if ($resultCode === 0) {
        // Payment success - parse metadata
        $callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $mpesaReceipt = '';
        $amount = 0;
        foreach ($callbackMetadata as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') $mpesaReceipt = $item['Value'] ?? '';
            if (($item['Name'] ?? '') === 'Amount') $amount = $item['Value'] ?? 0;
        }

        // Update payments table
        $stmt = $pdo->prepare("UPDATE payments SET status='success', mpesa_receipt=?, amount=?, transaction_time=NOW(), updated_at=NOW() WHERE id=?");
        $stmt->execute([$mpesaReceipt, $amount, $payment_id]);

        // Fetch payment row (user, plan)
        $stmt = $pdo->prepare("SELECT user_id, plan_id FROM payments WHERE id=? LIMIT 1");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) {
            file_put_contents($logFile, date('c') . " - payment record not found: {$payment_id}\n", FILE_APPEND);
            echo json_encode(['result' => 'error', 'msg' => 'payment record not found']);
            exit;
        }

        $user_id = $payment['user_id'];
        $plan_id = $payment['plan_id'];
        $ip = $_GET['ip'] ?? null;
        $mac = $_GET['mac'] ?? null;

        // Load plan
        $planStmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
        $planStmt->execute([$plan_id]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            file_put_contents($logFile, date('c') . " - plan not found: {$plan_id}\n", FILE_APPEND);
            echo json_encode(['result' => 'error', 'msg' => 'plan not found']);
            exit;
        }

        // ✅ Create FreeRADIUS user account and start session automatically
        try {
            $pdo = db();

            // Generate login credentials
            $username = 'lk_' . bin2hex(random_bytes(3));
            $password_plain = bin2hex(random_bytes(4));

            // Get plan details
            $planStmt = $pdo->prepare("SELECT duration_minutes FROM plans WHERE id = ?");
            $planStmt->execute([$payment['plan_id']]);
            $plan = $planStmt->fetch();

            // Create FreeRADIUS user
            create_radius_account($pdo, $username, $password_plain, $mac, $plan['duration_minutes']);

            // Create user session (tracked in sessions table)
            create_session_after_payment($payment['user_id'], $payment['plan_id'], $ip, $mac);

            // Save credentials in payments for reference
            $stmt = $pdo->prepare("UPDATE payments SET hotspot_user=?, hotspot_pass=? WHERE id=?");
            $stmt->execute([$username, $password_plain, $payment_id]);

        } catch (Exception $e) {
            error_log("Auto session creation error: " . $e->getMessage());
        }

        $minutes = intval($plan['duration_minutes'] ?? 60);

        // ---- Generate unique username (avoid collision) ----
        $tries = 0;
        do {
            $username = generate_username($pdo); // simple generator based on users count
            // If generate_username may collide (rare), add randomness
            if ($tries > 0) $username .= '_' . random_int(100, 999);
            $check = $pdo->prepare("SELECT id FROM radcheck WHERE username = ? LIMIT 1");
            $check->execute([$username]);
            $exists = (bool)$check->fetch();
            $tries++;
            if ($tries > 10) break;
        } while ($exists);

        // Generate password
        $password_plain = generate_password(8);

        // ---- Update users table with username+hashed password (optional) ----
        try {
            $stmt = $pdo->prepare("SELECT id, email, phone FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                // update username and password (hashed) - useful for user dashboard
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, hash_password($password_plain), $user_id]);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - users update error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // ---- Create account in FreeRADIUS tables ----
        try {
            // create Cleartext-Password, bind MAC if provided, set Session-Timeout
            create_radius_account($pdo, $username, $password_plain, $mac, $minutes);

            // set Expiration (radcheck:Expiration)
            $start = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
            $expires = (clone $start)->add(new DateInterval('PT' . $minutes . 'M'));
            set_radius_expiration($pdo, $username, $expires);

            // optional: add Mikrotik rate limit (radreply)
            if (!empty($plan['rate_limit'])) {
                $stmt = $pdo->prepare("SELECT id FROM radreply WHERE username = ? AND attribute = 'Mikrotik-Rate-Limit' LIMIT 1");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $ustmt = $pdo->prepare("UPDATE radreply SET value = ? WHERE username = ? AND attribute = 'Mikrotik-Rate-Limit'");
                    $ustmt->execute([$plan['rate_limit'], $username]);
                } else {
                    $istmt = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
                    $istmt->execute([$username, $plan['rate_limit']]);
                }
            }

            // optional: assign to radusergroup for plan policies
            try {
                $groupname = 'Plan_' . $plan['id'];
                $gstmt = $pdo->prepare("INSERT INTO radusergroup (username, groupname) VALUES (?, ?)");
                $gstmt->execute([$username, $groupname]);
            } catch (Exception $ign) {
                // duplicate group entries might throw; ignore
            }

        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - create_radius_account error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // ---- Optionally create MikroTik hotspot user (if you still use MikroTik local users) ----
        try {
            if (function_exists('mikrotik_add_hotspot_user')) {
                $opts = [];
                if ($minutes >= 1440) {
                    $opts['limit-uptime'] = floor($minutes / 1440) . 'd';
                } elseif ($minutes >= 60) {
                    $opts['limit-uptime'] = floor($minutes / 60) . 'h';
                } else {
                    $opts['limit-uptime'] = $minutes . 'm';
                }
                if (!empty($mac)) $opts['mac-address'] = $mac;
                if (!empty($plan['rate_limit'])) $opts['rate-limit'] = $plan['rate_limit'];

                mikrotik_add_hotspot_user($username, $password_plain, $opts);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - mikrotik_add_hotspot_user error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // ---- Record session in your sessions table (store hotspot creds for display) ----
        try {
            $sessionStmt = $pdo->prepare("
                INSERT INTO sessions
                (user_id, payment_id, plan_id, hotspot_username, hotspot_password, ip, mac, started_at, expires_at, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $sessionStmt->execute([
                $user_id,
                $payment_id,
                $plan_id,
                $username,
                password_hash($password_plain, PASSWORD_DEFAULT),
                $ip,
                $mac,
                $start->format('Y-m-d H:i:s'),
                $expires->format('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - sessions insert error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // ---- Store plain credentials in payments row so you can show them to user (for now) ----
        try {
            $stmt = $pdo->prepare("UPDATE payments SET hotspot_user = ?, hotspot_pass = ? WHERE id = ?");
            $stmt->execute([$username, $password_plain, $payment_id]);
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - update payments error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // ---- Notify user by email / SMS ----
        try {
            $userEmail = $u['email'] ?? null;
            $userPhone = $u['phone'] ?? null;
            $msg = "Your LeoKonnect WiFi credentials:\nUsername: {$username}\nPassword: {$password_plain}\nValid until: " . $expires->format('Y-m-d H:i:s') . " (Africa/Nairobi)";
            if ($userEmail) send_email($userEmail, 'LeoKonnect - Your WiFi credentials', nl2br($msg));
            if ($userPhone) send_sms($userPhone, $msg);
        } catch (Exception $e) {
            file_put_contents($logFile, date('c') . " - notify error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        file_put_contents($logFile, date('c') . " - success: created user {$username} for payment {$payment_id}\n", FILE_APPEND);
        echo json_encode(['result' => 'success', 'username' => $username, 'expires' => $expires->format('Y-m-d H:i:s')]);
        exit;
    } else {
        // Payment failed
        $stmt = $pdo->prepare("UPDATE payments SET status='failed', result_desc = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$resultDesc, $payment_id]);
        file_put_contents($logFile, date('c') . " - payment failed: {$resultDesc}\n", FILE_APPEND);
        echo json_encode(['result' => 'failed', 'desc' => $resultDesc]);
        exit;
    }
} catch (Exception $e) {
    file_put_contents($logFile, date('c') . " - general error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['result' => 'error', 'message' => $e->getMessage()]);
    exit;
}
