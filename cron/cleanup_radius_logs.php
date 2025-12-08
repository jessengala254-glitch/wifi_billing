<?php
// cleanup_radius_logs.php
// Cleans up old RADIUS logs to prevent database bloat
// Run daily via cron

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
$config = require __DIR__ . '/../inc/config.php';

// Security token check (same pattern as expire_radius_users.php)
if (!isset($_GET['token']) || $_GET['token'] !== ($config['expire_token'] ?? 'e0d1f3a8c9b7f5d4e3a2b1c0d9e8f7a6')) {
    http_response_code(403);
    exit('Unauthorized');
}

try {
    $pdo = db();
    
    // Delete logs older than 30 days
    $stmt = $pdo->prepare("DELETE FROM radius_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // Also cleanup old radpostauth entries (failed login attempts)
    $stmt2 = $pdo->prepare("DELETE FROM radpostauth WHERE authdate < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt2->execute();
    $deleted_auth = $stmt2->rowCount();
    
    // Log the cleanup
    error_log("RADIUS Logs Cleanup: Deleted $deleted radius_logs and $deleted_auth radpostauth entries older than 30 days");
    
    echo json_encode([
        'status' => 'success',
        'deleted_radius_logs' => $deleted,
        'deleted_radpostauth' => $deleted_auth,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("RADIUS Logs Cleanup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
