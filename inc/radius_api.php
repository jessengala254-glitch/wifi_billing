<?php
// radius_api.php
// Final production-ready RADIUS API for voucher-style WiFi (vouchers table, MAC-binding, expiry, logging, rate-limits, Mikrotik queues)

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

error_log("DEBUG: create_voucher request received at " . date('Y-m-d H:i:s'));

require_once __DIR__ . '/functions.php';     
require_once __DIR__ . '/routeros_api.class.php'; 
$config = require __DIR__ . '/config.php';
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

// ---------------------------------------
//  ADD BANDWIDTH ARRAY RIGHT HERE
// ---------------------------------------
$BANDWIDTH_BY_PLAN = [
    'hour'   => '5M/5M',
    'day'    => '5M/5M',
    'weekly' => '10M/10M',
    'monthly'=> '10M/10M',
    '3hours' => '5M/5M'
];

// ---------- Config (tune these) ----------
$API_KEY = $config['api_key'] ?? 'change_me';
$ALLOWED_IPS_NO_KEY = $config['allowed_ips_no_key'] ?? ['127.0.0.1', '192.168.10.68'];
$ROUTER_IP   = $config['router_ip'] ?? '192.168.10.67';
$ROUTER_USER = $config['router_user'] ?? 'admin';
$ROUTER_PASS = $config['router_pass'] ?? 'Dvdjesse1998???';

// rate-limiting window & thresholds
define('RL_WINDOW_SECONDS', $config['rate_limit_window'] ?? 60);
define('RL_MAX_ATTEMPTS', $config['rate_limit_max'] ?? 5);

// bandwidth mapping (plan_type => mikrotik rate string)
$BANDWIDTH_BY_PLAN = $config['bandwidth_by_plan'] ?? [
    'hour'   => '5M/5M',
    'day'    => '5M/5M',
    'weekly' => '10M/10M',
    'monthly'=> '10M/10M',
    '3hours' => '5M/5M'
];

// -------------------- helpers --------------------
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitize_mac($m) {
    if (!$m) return null;
    $m = preg_replace('/[^a-fA-F0-9]/', '', $m);
    if (strlen($m) !== 12) return null;
    $parts = str_split(strtolower($m), 2);
    return implode(':', $parts);
}

// login attempts & rate-limit helpers
function record_login_attempt($pdo, $username = null, $client_ip = null) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, client_ip) VALUES (?, ?)");
    $stmt->execute([$username, $client_ip]);
}

function too_many_attempts($pdo, $username = null, $client_ip = null) {
    $window_start = date('Y-m-d H:i:s', time() - RL_WINDOW_SECONDS);
    if ($username) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= ?");
        $s->execute([$username, $window_start]);
        if ((int)$s->fetchColumn() >= RL_MAX_ATTEMPTS) return true;
    }
    if ($client_ip) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE client_ip = ? AND attempted_at >= ?");
        $s->execute([$client_ip, $window_start]);
        if ((int)$s->fetchColumn() >= RL_MAX_ATTEMPTS) return true;
    }
    return false;
}

// RouterOS queue helpers
function setBandwidthQueue($router_ip, $router_user, $router_pass, $target_ip, $speed) {
    if (!$target_ip || !$speed) return false;
    $API = new RouterosAPI();
    if (!$API->connect($router_ip, $router_user, $router_pass)) {
        throw new Exception("Unable to connect to MikroTik for queue creation");
    }
    // remove existing queue with same name if exists
    $name = "limit-{$target_ip}";
    $existing = $API->comm("/queue/simple/print", ["?name" => $name]);
    if (is_array($existing) && count($existing) > 0) {
        foreach ($existing as $e) {
            if (isset($e['.id'])) $API->comm("/queue/simple/remove", ["numbers" => $e['.id']]);
        }
    }
    // add queue
    $API->comm("/queue/simple/add", [
        "name" => $name,
        "target" => $target_ip,
        "max-limit" => $speed,
        "comment" => "created-by-radius-api"
    ]);
    $API->disconnect();
    return true;
}

function removeBandwidthQueue($router_ip, $router_user, $router_pass, $target_ip) {
    $API = new RouterosAPI();
    if (!$API->connect($router_ip, $router_user, $router_pass)) {
        throw new Exception("Unable to connect to MikroTik for queue removal");
    }
    $name = "limit-{$target_ip}";
    $items = $API->comm("/queue/simple/print", ["?name" => $name]);
    if (is_array($items)) {
        foreach ($items as $it) {
            if (isset($it['.id'])) {
                $API->comm("/queue/simple/remove", ["numbers" => $it['.id']]);
            }
        }
    }
    $API->disconnect();
    return true;
}

// FreeRADIUS CoA disconnect helper (radclient must be installed)
function radius_coa_disconnect($nas_ip, $secret, $username) {
    // keep this safe - do not expose secrets
    $cmd = "echo 'User-Name=$username' | radclient -x {$nas_ip}:3799 disconnect {$secret} 2>&1";
    exec($cmd, $output, $rc);
    if ($rc !== 0) {
        throw new Exception("radclient CoA failed: " . implode("\n", $output));
    }
    return true;
}

// -------------------- Security check --------------------
$headers = function_exists('getallheaders') ? getallheaders() : [];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!in_array($client_ip, $ALLOWED_IPS_NO_KEY)) {
    if (empty($headers['X-API-KEY']) || $headers['X-API-KEY'] !== $API_KEY) {
        respond(['error' => 'Unauthorized'], 403);
    }
}

// -------------------- Get payload (robust) --------------------
$raw_input = file_get_contents('php://input');
$payload = json_decode($raw_input, true);

// Basic JSON check
if ($payload === null && strlen($raw_input) > 0) {
    respond(['error' => 'Bad request - invalid JSON'], 400);
}
if (!is_array($payload)) $payload = [];

// require "type" for every call
if (!isset($payload['type'])) {
    respond(['error' => 'Bad request - missing type'], 400);
}

$type = (string)$payload['type'];

// username is required for most calls, but not for create_voucher
$username = isset($payload['username']) ? (string)$payload['username'] : null;
if ($type !== 'create_voucher' && empty($username)) {
    respond(['error' => 'Bad request - missing username for type ' . $type], 400);
}

$password   = $payload['password'] ?? '';

// Collect possible MAC fields (order matters: preferred first)
$mac_candidates = [
    $payload['mac_address'] ?? null,
    $payload['mac'] ?? null,
    $payload['calling_station_id'] ?? null,
    $payload['Calling-Station-Id'] ?? null,
    $payload['calling-station-id'] ?? null,
    $payload['caller-id'] ?? null,
];

// Normalize and pick first valid MAC
$mac = null;
foreach ($mac_candidates as $mval) {
    if (empty($mval)) continue;
    $clean = sanitize_mac($mval);
    if ($clean) { $mac = $clean; break; }
}

// Framed IP (accept common variants)
$framed_ip = $payload['framed_ip'] ?? $payload['Framed-IP-Address'] ?? null;

// NAS IP from payload (or left null)
$nas_ip = $payload['nas_ip'] ?? $payload['NAS-IP-Address'] ?? null;

// plan/duration
$plan_type  = $payload['plan_type'] ?? null;
$duration_mins = isset($payload['duration_mins']) ? (int)$payload['duration_mins'] : null;


// -------------------- create_voucher endpoint --------------------
// if ($type === 'create_voucher') {
//     date_default_timezone_set('Africa/Nairobi');
//     // --- Get client IP ---
//     $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

//     // --- Get phone from payload ---
//     $customer_phone = $payload['phone'] ?? null; 
//     if (!$customer_phone) {
//         respond(['error' => 'phone is required'], 400);
//     }

//     // --- Determine plan ---
//     $plan_identifier = $payload['plan_id'] ?? $payload['plan_title'] ?? null;
//     if (!$plan_identifier) {
//         respond(['error' => 'plan_id or plan_title required'], 400);
//     }

//     // --- Fetch plan ---
//     if (isset($payload['plan_id'])) {
//         $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1");
//         $stmt->execute([$payload['plan_id']]);
//     } else {
//         $stmt = $pdo->prepare("SELECT * FROM plans WHERE title = ? AND active = 1 LIMIT 1");
//         $stmt->execute([$payload['plan_title']]);
//     }
//     $plan = $stmt->fetch(PDO::FETCH_ASSOC);
//     if (!$plan) {
//         respond(['error' => 'Plan not found or inactive'], 404);
//     }

//     // --- Duration ---
//     $duration_minutes = isset($payload['duration_mins']) 
//         ? (int)$payload['duration_mins'] 
//         : (int)$plan['duration_minutes'];

//     // --- Generate voucher credentials ---
//     $voucher_username = 'LEONET' . mt_rand(100, 9999);
//     $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
//     $voucher_password_plain = '';
//     for ($i = 0; $i < 6; $i++) {
//         $voucher_password_plain .= $chars[random_int(0, strlen($chars) - 1)];
//     }

//     // --- Calculate expiry ---
//     $start_time = date('Y-m-d H:i:s');
//     $expiry = date('Y-m-d H:i:s', strtotime("+$duration_minutes minutes"));

//     // --- Rate Limit ---
//     $plan_type_key = strtolower(str_replace(' ', '', $plan['title']));
//     $rate_limit = $BANDWIDTH_BY_PLAN[$plan_type_key] ?? '5M/5M';

//     // --- Human readable label ---
//     if ($duration_minutes >= 10080) {
//         $plan_type_label = '1 Week';
//     } elseif ($duration_minutes >= 1440) {
//         $plan_type_label = '1 Day';
//     } elseif ($duration_minutes >= 60) {
//         $plan_type_label = '1 Hour';
//     } else {
//         $plan_type_label = $plan['title'];
//     }

//     // if a phone was provided, try to top-up (extend) an existing voucher instead of creating a new one
//     if (!empty($phone)) {
//         // fetch plan details
//         $planStmt = $pdo->prepare("SELECT id, name AS plan_name, rate_limit FROM plans WHERE id = ? LIMIT 1");
//         $planStmt->execute([$plan_id]);
//         $planRow = $planStmt->fetch(PDO::FETCH_ASSOC);
//         $plan_name = $planRow['plan_name'] ?? ($plan_name ?? 'Custom');
//         $rate_limit = $planRow['rate_limit'] ?? ($rate_limit ?? '5M/5M');

//         $find = $pdo->prepare("SELECT * FROM vouchers WHERE phone = ? ORDER BY expiry DESC, created_at DESC LIMIT 1");
//         $find->execute([$phone]);
//         $existing = $find->fetch(PDO::FETCH_ASSOC);

//         if ($existing) {
//             // compute extension duration (use provided duration_mins if available)
//             $durationSecs = (!empty($duration_mins) ? intval($duration_mins) * 60 : 0);
//             if ($durationSecs <= 0 && !empty($planRow)) {
//                 // fallback: try to derive duration from plan (if you store it) else use 24h
//                 $durationSecs = 24 * 3600;
//             }

//             $now = time();
//             $currentExpiry = !empty($existing['expiry']) ? strtotime($existing['expiry']) : 0;
//             $base = ($currentExpiry > $now) ? $currentExpiry : $now;
//             $newExpiry = date('Y-m-d H:i:s', $base + $durationSecs);

//             $upd = $pdo->prepare("UPDATE vouchers SET expiry = ?, plan_id = ?, plan_type = ?, rate_limit = ?, status = 'active' WHERE id = ?");
//             $upd->execute([$newExpiry, $plan_id, $plan_name, $rate_limit, $existing['id']]);

//             // log top-up (no phone column in radius_logs assumed)
//             $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'create_voucher', 'ok', ?, ?)")
//                 ->execute([$existing['username'], 'topped_up', $_SERVER['REMOTE_ADDR'] ?? null]);

//             respond([
//                 'result' => 'ok',
//                 'action' => 'topped_up',
//                 'username' => $existing['username'],
//                 'expiry' => $newExpiry
//             ]);
//         }
//     }//end of phone top-up

//     // --- Insert voucher ---
//     $stmt = $pdo->prepare("
//         INSERT INTO vouchers (
//             username, password, plan_type, expiry, rate_limit, status, created_at, plan_id, phone
//         ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?, ?)
//     ");
//     $stmt->execute([
//         $voucher_username,
//         password_hash($voucher_password_plain, PASSWORD_DEFAULT),
//         $plan_type_label,
//         $expiry,
//         $rate_limit,
//         $plan['id'],
//         $customer_phone
//     ]);
//     $voucher_id = $pdo->lastInsertId();

//     // --- Auto-create user ---
//     $userInsert = $pdo->prepare("
//         INSERT INTO users (name, email, phone, username, password, role, voucher_id)
//         VALUES (?, ?, ?, ?, ?, 'user', ?)
//     ");
//     $userInsert->execute([
//         $voucher_username,
//         null,
//         $customer_phone,
//         $voucher_username,
//         password_hash($voucher_password_plain, PASSWORD_DEFAULT),
//         $voucher_id
//     ]);
//     $user_id = $pdo->lastInsertId();

//     // 1️⃣ Insert Cleartext password
//     $insertRad = $pdo->prepare("
//         INSERT INTO radcheck (username, attribute, op, value)
//         VALUES (?, 'Cleartext-Password', ':=', ?)
//     ");
//     $insertRad->execute([$voucher_username, $voucher_password_plain]);

//     // 2️⃣ Insert Session-Timeout
//     $session_seconds = $duration_minutes * 60;

//     $pdo->prepare("DELETE FROM radcheck WHERE username=? AND attribute='Session-Timeout'")
//         ->execute([$voucher_username]);

//     $insertTimeout = $pdo->prepare("
//         INSERT INTO radcheck (username, attribute, op, value)
//         VALUES (?, 'Session-Timeout', ':=', ?)
//     ");
//     $insertTimeout->execute([$voucher_username, $session_seconds]);

//     // 3️⃣ Expiration (FreeRADIUS date)
//     $radius_expire_format = date("d M Y H:i:s", strtotime($expiry));
//     $insertExpiry = $pdo->prepare("
//         INSERT INTO radcheck (username, attribute, op, value)
//         VALUES (?, 'Expiration', ':=', ?)
//     ");
//     $insertExpiry->execute([$voucher_username, $radius_expire_format]);

//     // 4️⃣ Rate Limit
//     $insertRate = $pdo->prepare("
//         INSERT INTO radreply (username, attribute, op, value)
//         VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)
//     ");
//     $insertRate->execute([$voucher_username, $rate_limit]);

//     // --- Create session entry ---
//     $sessionInsert = $pdo->prepare("
//         INSERT INTO sessions (user_id, hotspot_username, hotspot_password, plan_id, started_at, expires_at, active, created_at)
//         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
//     ");
//     $sessionInsert->execute([
//         $user_id,
//         $voucher_username,
//         $voucher_password_plain,
//         $plan['id'],
//         $start_time,
//         $expiry
//     ]);

//     // --- Create payment record ---
//     $paymentInsert = $pdo->prepare("
//         INSERT INTO payments (user_id, plan_id, phone, amount, status, created_at)
//         VALUES (?, ?, ?, ?, 'success', NOW())
//     ");
//     $paymentInsert->execute([
//         $user_id,
//         $plan['id'],
//         $customer_phone,
//         $plan['price'] ?? 0
//     ]);

//     // --- Log ---
//     $pdo->prepare("
//         INSERT INTO radius_logs (username, event_type, result, message, client_ip)
//         VALUES (?, 'system', 'ok', 'voucher created', ?)
//     ")->execute([
//         $voucher_username,
//         $client_ip
//     ]);

//     // --- Response ---
//     respond([
//         'result' => 'ok',
//         'voucher' => [
//             'username'   => $voucher_username,
//             'password'   => $voucher_password_plain,
//             'expiry'     => $expiry,
//             'plan_type'  => $plan_type_label,
//             'rate_limit' => $rate_limit
//         ],
//         'session' => [
//             'hotspot_username' => $voucher_username,
//             'hotspot_password' => $voucher_password_plain,
//             'started_at'       => $start_time,
//             'expires_at'       => $expiry
//         ]
//     ]);
// }


// -------------------- create_voucher endpoint --------------------
if ($type === 'create_voucher') {
    date_default_timezone_set('Africa/Nairobi');
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // --- Validate phone ---
    $customer_phone = $payload['phone'] ?? null;
    if (!$customer_phone) {
        respond(['error' => 'phone is required'], 400);
    }

    // --- Validate plan ---
    $plan_identifier = $payload['plan_id'] ?? $payload['plan_title'] ?? null;
    if (!$plan_identifier) {
        respond(['error' => 'plan_id or plan_title required'], 400);
    }

    // --- Fetch plan ---
    if (isset($payload['plan_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$payload['plan_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE title = ? AND active = 1 LIMIT 1");
        $stmt->execute([$payload['plan_title']]);
    }
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        respond(['error' => 'Plan not found or inactive'], 404);
    }

    // --- Get duration ---
    $duration_minutes = isset($payload['duration_mins']) 
        ? (int)$payload['duration_mins'] 
        : (int)($plan['duration_minutes'] ?? 1440);

    // --- Calculate plan label ---
    if ($duration_minutes >= 10080) {
        $plan_type_label = '1 Week';
    } elseif ($duration_minutes >= 1440) {
        $plan_type_label = '1 Day';
    } elseif ($duration_minutes >= 60) {
        $plan_type_label = '1 Hour';
    } else {
        $plan_type_label = $plan['title'] ?? '3 Hours';
    }

    // --- Get rate limit ---
    $plan_type_key = strtolower(str_replace(' ', '', $plan['title']));
    $rate_limit = $BANDWIDTH_BY_PLAN[$plan_type_key] ?? '5M/5M';

    // ========== TOP-UP LOGIC: Check if phone already has active voucher ==========
    $topup_check = $pdo->prepare("SELECT * FROM vouchers WHERE phone = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
    $topup_check->execute([$customer_phone]);
    $existing_voucher = $topup_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_voucher) {
        // TOP-UP: Extend the existing voucher's expiry
        $now = time();
        $current_expiry = strtotime($existing_voucher['expiry']);
        
        // If already expired, start from now; otherwise extend from current expiry
        $base_time = ($current_expiry > $now) ? $current_expiry : $now;
        $new_expiry = date('Y-m-d H:i:s', $base_time + ($duration_minutes * 60));

        // Update existing voucher
        $upd = $pdo->prepare("
            UPDATE vouchers 
            SET expiry = ?, plan_id = ?, plan_type = ?, rate_limit = ?, status = 'active'
            WHERE id = ?
        ");
        $upd->execute([$new_expiry, $plan['id'], $plan_type_label, $rate_limit, $existing_voucher['id']]);

        // Update FreeRADIUS records for extended session
        $session_seconds = $duration_minutes * 60;
        $radius_expire_format = date("d M Y H:i:s", strtotime($new_expiry));

        $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Session-Timeout'")
            ->execute([$existing_voucher['username']]);
        $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)")
            ->execute([$existing_voucher['username'], $session_seconds]);

        $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'")
            ->execute([$existing_voucher['username']]);
        $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)")
            ->execute([$existing_voucher['username'], $radius_expire_format]);

        // Log the top-up
        $pdo->prepare("
            INSERT INTO radius_logs (username, event_type, result, message, client_ip)
            VALUES (?, 'create_voucher', 'ok', ?, ?)
        ")->execute([$existing_voucher['username'], 'topped_up', $client_ip]);

        // Return top-up response
        respond([
            'result' => 'ok',
            'action' => 'topped_up',
            'username' => $existing_voucher['username'],
            'old_expiry' => $existing_voucher['expiry'],
            'new_expiry' => $new_expiry,
            'rate_limit' => $rate_limit
        ]);
    }

    // ========== NEW VOUCHER: No active voucher for this phone, create new one ==========

    // --- Generate credentials ---
    $voucher_username = 'LEONET' . mt_rand(1000, 9999);
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $voucher_password_plain = '';
    for ($i = 0; $i < 6; $i++) {
        $voucher_password_plain .= $chars[random_int(0, strlen($chars) - 1)];
    }

    // --- Calculate times ---
    $start_time = date('Y-m-d H:i:s');
    $expiry = date('Y-m-d H:i:s', strtotime("+$duration_minutes minutes"));
    $session_seconds = $duration_minutes * 60;
    $radius_expire_format = date("d M Y H:i:s", strtotime($expiry));

    // --- Insert voucher ---
    $stmt = $pdo->prepare("
        INSERT INTO vouchers (username, password, plan_type, expiry, rate_limit, status, created_at, plan_id, phone)
        VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?, ?)
    ");
    $stmt->execute([
        $voucher_username,
        password_hash($voucher_password_plain, PASSWORD_DEFAULT),
        $plan_type_label,
        $expiry,
        $rate_limit,
        $plan['id'],
        $customer_phone
    ]);
    $voucher_id = $pdo->lastInsertId();

    // --- Insert into users table (auto-create user) ---
    // ⚠️ Handle duplicate phone constraint: only insert if phone not already in users
    try {
        $userInsert = $pdo->prepare("
            INSERT INTO users (name, email, phone, username, password, role, voucher_id)
            VALUES (?, ?, ?, ?, ?, 'user', ?)
        ");
        $userInsert->execute([
            $voucher_username,
            null,
            $customer_phone,
            $voucher_username,
            password_hash($voucher_password_plain, PASSWORD_DEFAULT),
            $voucher_id
        ]);
        $user_id = $pdo->lastInsertId();
    } catch (Exception $e) {
        // If phone already exists in users, just use the existing user
        $existingUser = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $existingUser->execute([$customer_phone]);
        $user_id = $existingUser->fetchColumn();
        if (!$user_id) {
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'create_voucher', 'error', ?)")
                ->execute([$voucher_username, 'user_creation_failed: ' . $e->getMessage()]);
            respond(['error' => 'Failed to create user record'], 500);
        }
    }

    // --- Insert FreeRADIUS records ---
    // 1. Cleartext Password
    $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)")
        ->execute([$voucher_username, $voucher_password_plain]);

    // 2. Session Timeout
    $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)")
        ->execute([$voucher_username, $session_seconds]);

    // 3. Expiration
    $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)")
        ->execute([$voucher_username, $radius_expire_format]);

    // 4. Rate Limit (radreply)
    $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)")
        ->execute([$voucher_username, $rate_limit]);

    // --- Create session entry ---
    $sessionInsert = $pdo->prepare("
        INSERT INTO sessions (user_id, hotspot_username, hotspot_password, plan_id, started_at, expires_at, active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    $sessionInsert->execute([
        $user_id,
        $voucher_username,
        $voucher_password_plain,
        $plan['id'],
        $start_time,
        $expiry
    ]);

    // --- Create payment record ---
    $paymentInsert = $pdo->prepare("
        INSERT INTO payments (user_id, plan_id, phone, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'success', NOW())
    ");
    $paymentInsert->execute([
        $user_id,
        $plan['id'],
        $customer_phone,
        $plan['price'] ?? 0
    ]);

    // --- Log voucher creation ---
    $pdo->prepare("
        INSERT INTO radius_logs (username, event_type, result, message, client_ip)
        VALUES (?, 'create_voucher', 'ok', 'voucher_created', ?)
    ")->execute([$voucher_username, $client_ip]);

    // --- Return response ---
    respond([
        'result' => 'ok',
        'voucher' => [
            'username'   => $voucher_username,
            'password'   => $voucher_password_plain,
            'expiry'     => $expiry,
            'plan_type'  => $plan_type_label,
            'rate_limit' => $rate_limit
        ],
        'session' => [
            'hotspot_username' => $voucher_username,
            'hotspot_password' => $voucher_password_plain,
            'started_at'       => $start_time,
            'expires_at'       => $expiry
        ]
    ]);
}

// -------------------- Rate-limiting check for authenticate/authorize --------------------
if (in_array($type, ['authenticate','authorize'])) {
    if (too_many_attempts($pdo, $username, $client_ip)) {
        record_login_attempt($pdo, $username, $client_ip);
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$username, $type, 'reject', 'rate_limit_exceeded', $client_ip, $mac, $framed_ip]);
        respond(['result' => 'reject', 'message' => 'Too many attempts, try again later'], 429);
    }
}

// -------------------- 1) AUTHENTICATE --------------------
if ($type === 'authenticate') {
    record_login_attempt($pdo, $username, $client_ip);

    $stmt = $pdo->prepare("SELECT id, password, status FROM vouchers WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$v) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) VALUES (?, 'authenticate', 'reject', ?, ?, ?, ?)")->execute([$username, 'voucher_not_found', $client_ip, $mac, $framed_ip]);
        respond(['result' => 'reject']);
    }
    if ($v['status'] !== 'active') {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authenticate', 'reject', ?, ?)")->execute([$username, 'voucher_not_active', $client_ip]);
        respond(['result' => 'reject', 'message' => 'Voucher not active']);
    }
    if (!password_verify($password, $v['password'])) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authenticate', 'reject', ?, ?)")->execute([$username, 'bad_password', $client_ip]);
        respond(['result' => 'reject']);
    }

    // password ok
    $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authenticate', 'accept', ?, ?)")->execute([$username, 'password_ok', $client_ip]);
    respond(['result' => 'accept', 'reply_attributes' => ['Session-Timeout' => 3600]]);
}

// -------------------- 2) AUTHORIZE --------------------
if ($type === 'authorize') {
    record_login_attempt($pdo, $username, $client_ip);

    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$v) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authorize', 'reject', ?, ?)")->execute([$username, 'voucher_not_found', $client_ip]);
        respond(['result' => 'reject', 'message' => 'Voucher not found']);
    }

    // ✅ CHECK EXPIRY FIRST (before status check)
    if (!empty($v['expiry']) && strtotime($v['expiry']) < time()) {
        // Update status atomically
        $pdo->prepare("UPDATE vouchers SET status='expired' WHERE id = ?")->execute([$v['id']]);
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authorize', 'reject', ?, ?)")->execute([$username, 'expired', $client_ip]);
        respond(['result' => 'reject', 'message' => 'Voucher expired']);
    }

    // NOW check status
    if ($v['status'] !== 'active') {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip) VALUES (?, 'authorize', 'reject', ?, ?)")->execute([$username, 'voucher_not_active', $client_ip]);
        respond(['result' => 'reject', 'message' => 'Voucher not active']);
    }

    // MAC binding (priority: vouchers.mac_address -> radcheck Calling-Station-Id)
    $allowedMac = $v['mac_address'] ? sanitize_mac($v['mac_address']) : null;
    if (!$allowedMac) {
        $macStmt = $pdo->prepare("SELECT value FROM radcheck WHERE username=? AND attribute='Calling-Station-Id' LIMIT 1");
        $macStmt->execute([$username]);
        $rc_mac = $macStmt->fetchColumn();
        if ($rc_mac) $allowedMac = sanitize_mac($rc_mac);
    }

    if (!$allowedMac) {
        if ($mac) {
            // bind first-login MAC
            $pdo->prepare("UPDATE vouchers SET mac_address = ? WHERE id = ?")->execute([$mac, $v['id']]);
            $ins = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Calling-Station-Id', ':=', ?)");
            $ins->execute([$username, $mac]);
            $allowedMac = $mac;
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'info', ?)")->execute([$username, 'mac_bound_first_login']);
        } else {
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'reject', ?)")->execute([$username, 'no_mac_provided']);
            respond(['result' => 'reject', 'message' => 'No MAC provided to bind']);
        }
    }

    // compare macs
    if ($allowedMac && $mac) {
        if (strtolower($allowedMac) !== strtolower($mac)) {
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac) VALUES (?, 'authorize', 'reject', ?, ?, ?)")->execute([$username, 'mac_mismatch', $client_ip, $mac]);
            respond(['result' => 'reject', 'message' => 'MAC binding mismatch']);
        }
    } elseif ($allowedMac && !$mac) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'reject', ?)")->execute([$username, 'nas_missing_mac']);
        respond(['result' => 'reject', 'message' => 'NAS did not supply MAC']);
    }

    // -------------------- DEVICE LIMIT CHECK --------------------
    // Define max devices per voucher
    $max_devices = $v['device_limit'] ?? 1; // default to 1 if not set

    // Count currently active sessions for this username
    $activeSessionsStmt = $pdo->prepare("
        SELECT COUNT(*) FROM radacct 
        WHERE username = ? AND acctstoptime IS NULL
    ");
    $activeSessionsStmt->execute([$username]);
    $currentDevices = (int) $activeSessionsStmt->fetchColumn();

    if ($currentDevices >= $max_devices) {
        $pdo->prepare("
            INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac)
            VALUES (?, 'authorize', 'reject', ?, ?, ?)
        ")->execute([$username, "device_limit_exceeded ({$currentDevices}/{$max_devices})", $client_ip, $mac]);
        respond([
            'result' => 'reject',
            'message' => "Device limit exceeded ({$currentDevices}/{$max_devices})"
        ]);
    }

    // Framed IP / pool binding
    $allowedIP = $v['framed_ip'] ?: null;
    if (!$allowedIP) {
        $ipStmt = $pdo->prepare("SELECT value FROM radcheck WHERE username=? AND attribute='Framed-IP-Address' LIMIT 1");
        $ipStmt->execute([$username]);
        $rc_ip = $ipStmt->fetchColumn();
        if ($rc_ip) $allowedIP = $rc_ip;
    }
    if (!$allowedIP && $framed_ip) {
        // assign first login
        $pdo->prepare("UPDATE vouchers SET framed_ip = ? WHERE id = ?")->execute([$framed_ip, $v['id']]);
        $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Framed-IP-Address', ':=', ?)")->execute([$username, $framed_ip]);
        $allowedIP = $framed_ip;
    } elseif ($allowedIP && $framed_ip && $allowedIP !== $framed_ip) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'reject', ?)")->execute([$username, 'ip_mismatch']);
        respond(['result' => 'reject', 'message' => 'IP binding mismatch']);
    }


    // Build reply attributes
    $reply_attributes = [];
    $seconds_left = max(60, strtotime($v['expiry']) - time());
    $reply_attributes['Session-Timeout'] = $seconds_left;

    // VLAN
    if (!empty($v['vlan_id'])) {
        $reply_attributes['Tunnel-Type'] = 'VLAN';
        $reply_attributes['Tunnel-Medium-Type'] = 'IEEE-802';
        $reply_attributes['Tunnel-Private-Group-Id'] = (string)$v['vlan_id'];
    }

    // Static IP / pool
    if (!empty($allowedIP)) {
        $reply_attributes['Framed-IP-Address'] = $allowedIP;
    } elseif (!empty($v['framed_pool'])) {
        $reply_attributes['Framed-Pool'] = $v['framed_pool'];
    }

    // Mikrotik Rate-Limit (string)
    $rate_limit = $v['rate_limit'] ?: ($BANDWIDTH_BY_PLAN[$v['plan_type']] ?? $BANDWIDTH_BY_PLAN['hour']);
    if ($rate_limit) {
        $reply_attributes['Mikrotik-Rate-Limit'] = $rate_limit;
    }

    // Try to create defensive router queue if we have an IP
    if (!empty($allowedIP) && !empty($rate_limit)) {
        try {
            setBandwidthQueue($ROUTER_IP, $ROUTER_USER, $ROUTER_PASS, $allowedIP, $rate_limit);
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'info', ?)")->execute([$username, "queue_added {$allowedIP} {$rate_limit}"]);
        } catch (Exception $e) {
            $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'authorize', 'error', ?)")->execute([$username, "queue_error: " . $e->getMessage()]);
            // still accept - Router can honor Mikrotik-Rate-Limit attribute
        }
    }

    $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) VALUES (?, 'authorize', 'accept', ?, ?, ?, ?)")->execute([$username, 'authorized_ok', $client_ip, $mac, $framed_ip]);
    respond(['result' => 'accept', 'reply_attributes' => $reply_attributes]);

    // Save session details for disconnection later
    if (!empty($mac) && !empty($framed_ip)) {
        $pdo->prepare("
            UPDATE vouchers 
            SET mac_address = ?, framed_ip = ?, used_at = NOW()
            WHERE id = ?
        ")->execute([$mac, $framed_ip, $v['id']]);
    }

    // Convert reply_attributes into proper RADIUS reply object
    $radius_reply = [];
    foreach ($reply_attributes as $attr => $value) {
        $radius_reply[] = [
            "attribute" => $attr,
            "value"     => $value
        ];
    }

    // Log success
    $pdo->prepare("
        INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) 
        VALUES (?, 'authorize', 'accept', ?, ?, ?, ?)
    ")->execute([$username, 'authorized_ok', $client_ip, $mac, $framed_ip]);

    // Final REST response (correct format)
    respond([
        "result" => "ok",
        "reply"  => $radius_reply
    ]);

}

// -------------------- 3) ACCOUNTING --------------------
if ($type === 'accounting') {
    $stmt = $pdo->prepare("INSERT INTO radacct (username, acctstarttime, acctstoptime, acctsessionid, acctuniqueid, acctinputoctets, acctoutputoctets, nasipaddress, acctterminatecause) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    try {
        $stmt->execute([
            $username,
            $payload['acctstarttime'] ?? date('Y-m-d H:i:s'),
            $payload['acctstoptime'] ?? null,
            $payload['acctsessionid'] ?? uniqid('s', true),
            $payload['acctuniqueid'] ?? uniqid('u', true),
            $payload['acctinputoctets'] ?? 0,
            $payload['acctoutputoctets'] ?? 0,
            $nas_ip ?? '0.0.0.0',
            $payload['acctterminatecause'] ?? 'Unknown'
        ]);
        // if acctstoptime present, optionally expire voucher if needed
        if (!empty($payload['acctstoptime'])) {
            $check = $pdo->prepare("SELECT id, expiry FROM vouchers WHERE username = ? LIMIT 1");
            $check->execute([$username]);
            $v = $check->fetch(PDO::FETCH_ASSOC);
            if ($v && !empty($v['expiry']) && strtotime($v['expiry']) < time()) {
                $pdo->prepare("UPDATE vouchers SET status='expired' WHERE id = ?")->execute([$v['id']]);
                $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'accounting', 'info', ?)")->execute([$username, 'voucher_expired_on_stop']);
            }
        }
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'accounting', 'ok', ?)")->execute([$username, 'accounting_inserted']);
        respond(['result' => 'ok']);
    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'accounting', 'error', ?)")->execute([$username, $e->getMessage()]);
        respond(['result' => 'error', 'message' => $e->getMessage()], 500);
    }
}

// -------------------- 4) DISCONNECT --------------------
if ($type === 'disconnect') {
    if (!$mac && !$framed_ip && empty($payload['nas_username'])) {
        respond(['result' => 'error', 'message' => 'MAC or framed_ip or nas_username required to disconnect'], 400);
    }
    try {
        // use RouterOS to kill sessions (PPP or Hotspot)
        $API = new RouterosAPI();
        if (!$API->connect($ROUTER_IP, $ROUTER_USER, $ROUTER_PASS)) {
            throw new Exception("Unable to connect to router to disconnect");
        }
        if ($mac) {
            $sessions = $API->comm("/ppp/active/print", ["?caller-id" => $mac]);
            if (is_array($sessions)) {
                foreach ($sessions as $s) {
                    if (isset($s['.id'])) $API->comm("/ppp/active/remove", ["numbers" => $s['.id']]);
                }
            }
        }
        if ($framed_ip) {
            $sessions = $API->comm("/ip/hotspot/active/print", ["?address" => $framed_ip]);
            if (is_array($sessions)) {
                foreach ($sessions as $s) {
                    if (isset($s['.id'])) $API->comm("/ip/hotspot/active/remove", ["numbers" => $s['.id']]);
                }
            }
        }
        // optionally CoA by username if provided
        if (!empty($payload['nas_username'])) {
            $nas_ip_coa = $config['coa']['nas_ip'] ?? '127.0.0.1';
            $secret = $config['coa']['secret'] ?? 'testing123';
            try {
                radius_coa_disconnect($nas_ip_coa, $secret, $payload['nas_username']);
            } catch (Exception $e) {
                // log but proceed
                $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'disconnect', 'error', ?)")->execute([$payload['nas_username'], 'coa_error: ' . $e->getMessage()]);
            }
        }

        $API->disconnect();
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) VALUES (?, 'disconnect', 'ok', ?, ?, ?, ?)")->execute([$username, 'disconnected', $client_ip, $mac, $framed_ip]);
        respond(['result' => 'ok', 'action' => 'disconnected']);
    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message) VALUES (?, 'disconnect', 'error', ?)")->execute([$username, $e->getMessage()]);
        respond(['result' => 'error', 'message' => $e->getMessage()], 500);
    }
}

// -------------------- Unknown type --------------------
respond(['error' => 'Unknown type'], 400);
