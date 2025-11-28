<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * inc/mikrotik.php
 * Compatible with both EvilFreelancer RouterOS library and RouterosAPI.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Prefer composer autoload if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/RouterosAPI.php')) {
    require_once __DIR__ . '/RouterosAPI.php';
}

use RouterOS\Client;
use RouterOS\Query;

/**
 * Connect to MikroTik router
 */
function mikrotik_client() {
    global $config;
    $mk = $config['mikrotik'] ?? null;
    if (!$mk) {
        throw new Exception("MikroTik configuration missing in inc/config.php");
    }

    // ✅ Prefer EvilFreelancer library
    if (class_exists('RouterOS\Client')) {
        return new RouterOS\Client([
            'host' => $mk['host'],
            'user' => $mk['user'],
            'pass' => $mk['pass'],
            'port' => $mk['port'] ?? 8728,
            'timeout' => $mk['timeout'] ?? 5,
        ]);
    }

    // ✅ Fallback to RouterosAPI
    if (class_exists('RouterosAPI')) {
        $api = new RouterosAPI();
        $api->debug = false;
        if ($api->connect($mk['host'], $mk['user'], $mk['pass'], $mk['port'] ?? 8728)) {
            return $api;
        } else {
            throw new Exception("Could not connect to MikroTik using RouterosAPI.");
        }
    }

    throw new Exception("No MikroTik API class available.");
}

/**
 * ✅ Add hotspot user (with optional MAC binding, limit-uptime, rate-limit)
 */
function mikrotik_add_hotspot_user($username, $password, $opts = []) {
    $client = mikrotik_client();

    $query = (new Query('/ip/hotspot/user/add'))
        ->equal('name', $username)
        ->equal('password', $password);

    if (!empty($opts['limit-uptime'])) $query->equal('limit-uptime', $opts['limit-uptime']);
    if (!empty($opts['rate-limit'])) $query->equal('rate-limit', $opts['rate-limit']);
    if (!empty($opts['profile'])) $query->equal('profile', $opts['profile']);
    if (!empty($opts['mac'])) $query->equal('mac-address', $opts['mac']); // MAC binding

    try {
        $client->query($query)->read();
        return true;
    } catch (Exception $e) {
        error_log("MikroTik Add Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ Remove hotspot user + disconnect active sessions
 */
function mikrotik_remove_hotspot_user($username) {
    $client = mikrotik_client();

    try {
        // Disconnect active user
        $active = (new Query('/ip/hotspot/active/print'))->where('user', $username);
        $act = $client->query($active)->read();
        foreach ($act as $a) {
            if (!empty($a['.id'])) {
                $client->query((new Query('/ip/hotspot/active/remove'))->equal('.id', $a['.id']))->read();
            }
        }

        // Remove user record
        $users = $client->query((new Query('/ip/hotspot/user/print'))->where('name', $username))->read();
        foreach ($users as $u) {
            if (!empty($u['.id'])) {
                $client->query((new Query('/ip/hotspot/user/remove'))->equal('.id', $u['.id']))->read();
            }
        }
        return true;
    } catch (Exception $e) {
        error_log("mikrotik_remove_hotspot_user error: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ Disconnect active user by username or IP
 */
function mikrotik_disconnect_active($username = null, $ip = null) {
    $client = mikrotik_client();
    $filter = null;

    if ($username) {
        $filter = (new Query('/ip/hotspot/active/print'))->where('user', $username);
    } elseif ($ip) {
        $filter = (new Query('/ip/hotspot/active/print'))->where('address', $ip);
    } else {
        return false;
    }

    $resp = $client->query($filter)->read();
    foreach ($resp as $r) {
        $client->query((new Query('/ip/hotspot/active/remove'))->equal('.id', $r['.id']))->read();
    }
    return true;
}

/**
 * ✅ List active hotspot users
 */
function mikrotik_list_active() {
    $client = mikrotik_client();
    return $client->query(new Query('/ip/hotspot/active/print'))->read();
}

/**
 * ✅ Add simple queue for bandwidth limiting (optional)
 */
function mikrotik_add_simple_queue($target_ip, $limit, $comment = '') {
    $client = mikrotik_client();
    $q = (new Query('/queue/simple/add'))
        ->equal('name', 'user-' . $target_ip)
        ->equal('target', $target_ip . '/32')
        ->equal('max-limit', $limit);
    if ($comment) $q->equal('comment', $comment);
    $client->query($q)->read();
    return true;
}

/**
 * ✅ Remove simple queue for given IP
 */
function mikrotik_remove_simple_queue($target_ip) {
    $client = mikrotik_client();
    $resp = $client->query((new Query('/queue/simple/print'))->where('target', $target_ip . '/32'))->read();
    foreach ($resp as $r) {
        $client->query((new Query('/queue/simple/remove'))->equal('.id', $r['.id']))->read();
    }
    return true;
}
?>
