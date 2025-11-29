<?php
// expire_radius_users.php
// Cron-able script to expire vouchers + disconnect users cleanly
// Secure access token required when executing via HTTP (keeps parity with your existing script)

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// require helpers
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/mikrotik.php'; 
//require_once __DIR__ . '/../inc/routeros_api.class.php'; 
$config = require __DIR__ . '/../inc/config.php';

function sanitize_mac($mac) {
    if (empty($mac)) {
        return ''; // return empty string instead of null
    }
    return strtoupper(str_replace([':', '-'], '', trim($mac)));
}

if (!isset($_GET['token']) || $_GET['token'] !== ($config['expire_token'] ?? 'e0d1f3a8c9b7f5d4e3a2b1c0d9e8f7a6')) {
    http_response_code(403);
    exit('Unauthorized');
}

$pdo = db();

// ---------- 0) helper: CoA ----------
function radius_disconnect_user($nas_ip, $secret, $username) {
    // radclient needs to be present on the host
    $cmd = "echo 'User-Name=$username' | radclient -x {$nas_ip}:3799 disconnect {$secret} 2>&1";
    exec($cmd, $output, $rc);
    if ($rc !== 0) {
        // return output for logging, but don't abort whole run
        return ['ok' => false, 'output' => implode("\n", $output), 'rc' => $rc];
    }
    return ['ok' => true];
}

// ---------- 1) Mark vouchers expired ----------
try {
    $upd = $pdo->prepare("UPDATE vouchers SET status='expired' WHERE status='active' AND expiry <= NOW()");
    $upd->execute();
} catch (Exception $e) {
    error_log("Failed to mark vouchers expired: " . $e->getMessage());
}

// ---------- 2) Select affected vouchers + session data ----------
$stmt = $pdo->prepare("
    SELECT 
      v.username AS voucher_username,
      v.framed_ip AS voucher_framed_ip,
      v.mac_address AS voucher_mac,
      v.id AS voucher_id,
      s.id AS session_id,
      s.ip AS session_ip,
      s.mac AS session_mac,
      s.hotspot_username AS session_hotspot_username,
      s.user_id AS session_user_id,
      s.expires_at AS session_expires_at
    FROM vouchers v
    LEFT JOIN sessions s ON s.hotspot_username = v.username
    WHERE v.status = 'expired' AND v.expiry <= NOW()
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No expired vouchers found.\n";
    exit;
}

// ---------- 3) Connect to MikroTik (prefer mikrotik_connect if present) ----------
$mt = null;
try {
    if (function_exists('mikrotik_connect')) {
        $mt = mikrotik_connect();
    } else {
        // fallback to RouterosAPI using config values
        $router_ip   = $config['router_ip'] ?? '192.168.10.67';
        $router_user = $config['router_user'] ?? 'admin';
        $router_pass = $config['router_pass'] ?? 'Dvdjesse1998???';
        $API = new RouterosAPI();
        if ($API->connect($router_ip, $router_user, $router_pass)) {
            $mt = $API;
        } else {
            $mt = null; // we'll still try CoA + DB cleanup even without MT connection
        }
    }
} catch (Exception $e) {
    error_log("MikroTik connect error: " . $e->getMessage());
    $mt = null;
}




// ---------- 4) Process each expired voucher ----------
$processed = 0;
foreach ($rows as $r) {
    $processed++;
    $voucher_user = $r['voucher_username'];
    $v_ip = $r['voucher_framed_ip'] ?: $r['session_ip'];
    $v_mac = sanitize_mac($r['voucher_mac'] ?? $r['session_mac'] ?? null);
    $session_id = $r['session_id'];
    $session_user_id = $r['session_user_id'] ?? null;

    // LOG helper
    $log = function($msg, $type = 'system') use ($pdo, $voucher_user, $session_user_id) {
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI'; // safe for cron
        $result = ($type === 'error') ? 'error' : 'ok';

        $stmt = $pdo->prepare("
            INSERT INTO radius_logs 
            (username, event_type, result, message, client_ip, mac, framed_ip) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$voucher_user, $type, $result, $msg, $client_ip, null, null]);
    };


    // 4.1 Remove Mikrotik queue (if we have IP and mt connection)
    if ($mt && !empty($v_ip)) {
        try {
            // queue name possibility: "<ip>-limit" or "limit-<ip>" — try both safely
            $namesToTry = [$v_ip . "-limit", "limit-" . $v_ip, "limit_".$v_ip];
            foreach ($namesToTry as $qname) {
                // find entries
                $found = $mt->comm("/queue/simple/print", ["?name" => $qname]);
                if (is_array($found) && count($found) > 0) {
                    foreach ($found as $f) {
                        if (isset($f['.id'])) {
                            $mt->comm("/queue/simple/remove", ["numbers" => $f['.id']]);
                        }
                    }
                    $log("removed_queue {$qname}", 'system');
                }
            }
        } catch (Exception $e) {
            $log("queue_removal_error: " . $e->getMessage(), 'error');
        }
    }

    // 4.2 Remove firewall address-list entries for IP
    if ($mt && !empty($v_ip)) {
        try {
            $found = $mt->comm("/ip/firewall/address-list/print", ["?address" => $v_ip]);
            if (is_array($found) && count($found) > 0) {
                foreach ($found as $f) {
                    if (isset($f['.id'])) $mt->comm("/ip/firewall/address-list/remove", ["numbers" => $f['.id']]);
                }
                $log("removed_address_list {$v_ip}", 'system');
            }
        } catch (Exception $e) {
            $log("addresslist_removal_error: " . $e->getMessage(), 'error');
        }
    }

    // 4.3 Disconnect active Hotspot session by username
    if ($mt && !empty($voucher_user)) {
        try {
            // hotspot active by user
            $mt->write('/ip/hotspot/active/print', false);
            $mt->write('?.user=' . $voucher_user, false);
            $mt->write('=.proplist=.id');
            $read = $mt->read(false);
            // read result may be an array; try to extract any ids
            if (is_array($read)) {
                foreach ($read as $entry) {
                    if (isset($entry['.id'])) {
                        $mt->comm('/ip/hotspot/active/remove', ["numbers" => $entry['.id']]);
                    }
                }
                $log("hotspot_sessions_removed_for {$voucher_user}", 'system');
            }
        } catch (Exception $e) {
            $log("hotspot_disconnect_error: " . $e->getMessage(), 'error');
        }
    }

    // 4.4 Disconnect PPPoE active by caller-id (MAC)
    if ($mt && $v_mac) {
        try {
            $act = $mt->comm("/ppp/active/print", ["?caller-id" => $v_mac]);
            if (is_array($act) && count($act) > 0) {
                foreach ($act as $a) {
                    if (isset($a['.id'])) $mt->comm("/ppp/active/remove", ["numbers" => $a['.id']]);
                }
                $log("pppoe_sessions_removed_for_mac {$v_mac}", 'system');
            }
        } catch (Exception $e) {
            $log("pppoe_disconnect_error: " . $e->getMessage(), 'error');
        }
    }

    // 4.5 FreeRADIUS CoA disconnect (if configured)
    try {
        $nas_ip_coa = $config['coa']['nas_ip'] ?? ($config['nas_ip'] ?? '127.0.0.1');
        $coa_secret = $config['coa']['secret'] ?? ($config['nas_secret'] ?? 'testing123');
        $coaRes = radius_disconnect_user($nas_ip_coa, $coa_secret, $voucher_user);
        if ($coaRes['ok']) {
            $log("coa_disconnect_ok", 'system');
        } else {
            $log("coa_disconnect_failed: " . ($coaRes['output'] ?? 'no-output'), 'error');
        }
    } catch (Exception $e) {
        $log("coa_exception: " . $e->getMessage(), 'error');
    }

    // 4.6 Remove radcheck entries for this username (so they can't re-auth)
    try {
        $del = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
        $del->execute([$voucher_user]);
        $log("radcheck_removed", 'system');
    } catch (Exception $e) {
        $log("radcheck_delete_error: " . $e->getMessage(), 'error');
    }

    // 4.7 Update sessions table to mark inactive (if there is a session row)
    if ($session_id) {
        try {
            $pdo->prepare("UPDATE sessions SET active = 0 WHERE id = ?")->execute([$session_id]);
            $log("session_marked_inactive id={$session_id}", 'system');
        } catch (Exception $e) {
            $log("session_update_error: " . $e->getMessage(), 'error');
        }
    }

    // 4.8 Insert into radius_logs (more structured)
    try {
        $pdo->prepare("INSERT INTO radius_logs (username, event_type, result, message, client_ip, mac, framed_ip) VALUES (?, 'expire', 'ok', ?, ?, ?, ?)")
            ->execute([$voucher_user, "expired_and_disconnected", $_SERVER['REMOTE_ADDR'] ?? null, $v_mac, $v_ip]);
    } catch (Exception $e) {
        $log("radius_logs_insert_error: " . $e->getMessage(), 'error');
    }

    //4.9 Optional notify the user if you have a user_id linked to the session
    if ($session_user_id) {
        try {
            $usr = $pdo->prepare("SELECT phone FROM users WHERE id=? LIMIT 1");
            $usr->execute([$session_user_id]);
            $u = $usr->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['phone'])) {
                $msg = "Your internet plan expired at " . ($r['session_expires_at'] ?? 'now') . ". Renew to regain access.";
                // Email notifications removed; only send SMS
                send_sms($u['phone'], $msg);
                $log("notify_sms_sent", 'system');
            } else {
                $log("notify_skipped_no_phone", 'system');
            }
        } catch (Exception $e) {
            $log("notify_error: " . $e->getMessage(), 'error');
        }
    }
}

// ---------- 5) Close MikroTik connection if we opened RouterosAPI manually ----------
if (isset($API) && is_object($API)) {
    try { $API->disconnect(); } catch (Exception $e) {}
}
if (is_object($mt) && method_exists($mt, 'disconnect')) {
    try { $mt->disconnect(); } catch (Exception $e) {}
}

echo "Expired vouchers processed: " . count($rows) . "\n";
exit;
