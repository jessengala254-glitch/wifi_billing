<?php
/**
 * ====================================
 * Reset System - Delete All Users Data
 * ====================================
 * Deletes all customer data (users, vouchers, sessions, payments)
 * Keeps admin accounts intact
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin only.');
}

$pdo = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'DELETE ALL DATA') {
    
    echo "<h2>Starting System Reset...</h2>";
    echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p>";
    echo "<hr>";
    
    try {
        $pdo->beginTransaction();
        
        // Step 1: Get count of data to be deleted
        $userCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
        $voucherCount = $pdo->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
        $sessionCount = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
        $paymentCount = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
        $radacctCount = $pdo->query("SELECT COUNT(*) FROM radacct")->fetchColumn();
        $radcheckCount = $pdo->query("SELECT COUNT(*) FROM radcheck")->fetchColumn();
        
        echo "<h3>Data to be deleted:</h3>";
        echo "<ul>";
        echo "<li>Users (non-admin): {$userCount}</li>";
        echo "<li>Vouchers: {$voucherCount}</li>";
        echo "<li>Sessions: {$sessionCount}</li>";
        echo "<li>Payments: {$paymentCount}</li>";
        echo "<li>RADIUS Accounting: {$radacctCount}</li>";
        echo "<li>RADIUS Check: {$radcheckCount}</li>";
        echo "</ul>";
        echo "<hr>";
        
        // Step 2: Delete from payments
        echo "<p>🗑️ Deleting payments...</p>";
        $pdo->exec("DELETE FROM payments");
        echo "<p style='color: green;'>✓ Deleted {$paymentCount} payments</p>";
        
        // Step 3: Delete from sessions
        echo "<p>🗑️ Deleting sessions...</p>";
        $pdo->exec("DELETE FROM sessions");
        echo "<p style='color: green;'>✓ Deleted {$sessionCount} sessions</p>";
        
        // Step 4: Delete from radacct (RADIUS accounting)
        echo "<p>🗑️ Deleting RADIUS accounting records...</p>";
        $pdo->exec("DELETE FROM radacct");
        echo "<p style='color: green;'>✓ Deleted {$radacctCount} RADIUS accounting records</p>";
        
        // Step 5: Delete from radcheck (RADIUS auth)
        echo "<p>🗑️ Deleting RADIUS check records...</p>";
        $pdo->exec("DELETE FROM radcheck");
        echo "<p style='color: green;'>✓ Deleted {$radcheckCount} RADIUS check records</p>";
        
        // Step 6: Delete from vouchers
        echo "<p>🗑️ Deleting vouchers...</p>";
        $pdo->exec("DELETE FROM vouchers");
        echo "<p style='color: green;'>✓ Deleted {$voucherCount} vouchers</p>";
        
        // Step 7: Delete non-admin users
        echo "<p>🗑️ Deleting non-admin users...</p>";
        $pdo->exec("DELETE FROM users WHERE role != 'admin'");
        echo "<p style='color: green;'>✓ Deleted {$userCount} users</p>";
        
        // Step 8: Reset auto-increment IDs (optional)
        echo "<p>🔄 Resetting auto-increment counters...</p>";
        $pdo->exec("ALTER TABLE payments AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE sessions AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE radacct AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE radcheck AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE vouchers AUTO_INCREMENT = 1");
        echo "<p style='color: green;'>✓ Auto-increment counters reset</p>";
        
        $pdo->commit();
        
        echo "<hr>";
        echo "<h2 style='color: green;'>✓ System Reset Complete!</h2>";
        echo "<p>All customer data has been deleted. Admin accounts preserved.</p>";
        
        // Verify
        $remainingUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
        $remainingAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        
        echo "<h3>Current Status:</h3>";
        echo "<ul>";
        echo "<li>Admin accounts: <strong>{$remainingAdmins}</strong></li>";
        echo "<li>Customer accounts: <strong>{$remainingUsers}</strong></li>";
        echo "<li>Vouchers: <strong>0</strong></li>";
        echo "<li>Sessions: <strong>0</strong></li>";
        echo "<li>Payments: <strong>0</strong></li>";
        echo "</ul>";
        
        echo "<p>Completed at: " . date('Y-m-d H:i:s') . "</p>";
        echo "<br><a href='users_manage.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>← Back to Users</a>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<h2 style='color: red;'>✗ Error occurred!</h2>";
        echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<br><a href='reset_system.php'>← Try Again</a>";
    }
    
} else {
    // Show confirmation form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Reset System - Delete All Data</title>
        <link rel="stylesheet" href="admin_style.css">
        <style>
            .warning-box {
                background: #fff3cd;
                border: 2px solid #ffc107;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .danger-box {
                background: #f8d7da;
                border: 2px solid #dc3545;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .info-box {
                background: #d1ecf1;
                border: 2px solid #17a2b8;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                margin: 5px;
            }
            .btn-danger {
                background: #dc3545;
                color: white;
            }
            .btn-danger:hover {
                background: #c82333;
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
            .confirm-input {
                width: 100%;
                padding: 10px;
                font-size: 16px;
                border: 2px solid #dc3545;
                border-radius: 6px;
                margin: 10px 0;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
        </style>
    </head>
    <body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <h1>⚠️ Reset System - Delete All Data</h1>
            
            <div class="danger-box">
                <h2>🚨 DANGER ZONE 🚨</h2>
                <p><strong>This action is IRREVERSIBLE!</strong></p>
                <p>You are about to permanently delete:</p>
                <ul>
                    <li>All customer/user accounts (role = 'user')</li>
                    <li>All vouchers and credentials</li>
                    <li>All payment records</li>
                    <li>All session history</li>
                    <li>All RADIUS authentication and accounting records</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>✓ What will be PRESERVED:</h3>
                <ul>
                    <li>Admin accounts</li>
                    <li>Plans configuration</li>
                    <li>MikroTik router settings</li>
                    <li>System settings</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h3>Current Database Statistics:</h3>
                <?php
                $stats = [
                    'Total Users (non-admin)' => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn(),
                    'Admin Users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
                    'Vouchers' => $pdo->query("SELECT COUNT(*) FROM vouchers")->fetchColumn(),
                    'Sessions' => $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
                    'Payments' => $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
                    'RADIUS Accounting' => $pdo->query("SELECT COUNT(*) FROM radacct")->fetchColumn(),
                ];
                
                echo "<ul>";
                foreach ($stats as $label => $count) {
                    echo "<li><strong>{$label}:</strong> {$count}</li>";
                }
                echo "</ul>";
                ?>
            </div>
            
            <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY SURE? This cannot be undone!');">
                <h3>Type "DELETE ALL DATA" to confirm:</h3>
                <input type="text" name="confirm" class="confirm-input" placeholder="Type: DELETE ALL DATA" required>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger">🗑️ DELETE ALL DATA</button>
                    <a href="users_manage.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
}
?>
