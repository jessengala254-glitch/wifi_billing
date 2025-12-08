<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
$admin_id = $_SESSION['user_id'];
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: users_manage.php');
    exit;
}

// Logs function
if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    }
}

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'toggle_status') {
        $stmt = $pdo->prepare("SELECT name, status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $user_id]);
            $actionText = ($newStatus === 'active') ? 'Activate User' : 'Deactivate User';
            log_action($pdo, $admin_id, $actionText, "{$actionText} - {$user['name']}");
        }
        header("Location: user_detail.php?id=$user_id");
        exit;
    }
    
    if ($action === 'delete_user') {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            log_action($pdo, $admin_id, "Delete User", "Deleted user {$user['name']}");
        }
        header("Location: users_manage.php");
        exit;
    }
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users_manage.php');
    exit;
}

// Get voucher information
$voucher = null;
$isOnline = false;
if (!empty($user['voucher_id'])) {
    $stmtVoucher = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? LIMIT 1");
    $stmtVoucher->execute([$user['voucher_id']]);
    $voucher = $stmtVoucher->fetch(PDO::FETCH_ASSOC);
} else {
    // Get most recent voucher for this phone
    $stmtVoucher = $pdo->prepare("SELECT * FROM vouchers WHERE phone = ? ORDER BY id DESC LIMIT 1");
    $stmtVoucher->execute([$user['phone']]);
    $voucher = $stmtVoucher->fetch(PDO::FETCH_ASSOC);
}

// Check if user is online and get plain text password from sessions
$plainPassword = '';
if ($voucher) {
    $stmtOnline = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM radacct 
        WHERE username = ? AND acctstoptime IS NULL
    ");
    $stmtOnline->execute([$voucher['username']]);
    $isOnline = $stmtOnline->fetchColumn() > 0;
    
    // Get plain text password from sessions table
    $stmtPassword = $pdo->prepare("
        SELECT hotspot_password 
        FROM sessions 
        WHERE hotspot_username = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmtPassword->execute([$voucher['username']]);
    $plainPassword = $stmtPassword->fetchColumn() ?: $voucher['password'];
}

// Get data usage stats
$dataStats = ['total_upload' => 0, 'total_download' => 0, 'total_sessions' => 0];
if ($voucher) {
    $stmtData = $pdo->prepare("
        SELECT 
            SUM(acctinputoctets) as total_upload,
            SUM(acctoutputoctets) as total_download,
            COUNT(*) as total_sessions
        FROM radacct
        WHERE username = ?
    ");
    $stmtData->execute([$voucher['username']]);
    $dataStats = $stmtData->fetch(PDO::FETCH_ASSOC);
}

// Get data usage this month (daily)
$dataUsageMonth = [];
if ($voucher) {
    $stmtMonth = $pdo->prepare("
        SELECT 
            DATE(acctstarttime) as day,
            SUM(acctinputoctets + acctoutputoctets) as total_bytes
        FROM radacct
        WHERE username = ? 
        AND acctstarttime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(acctstarttime)
        ORDER BY day ASC
    ");
    $stmtMonth->execute([$voucher['username']]);
    $dataUsageMonth = $stmtMonth->fetchAll(PDO::FETCH_ASSOC);
}

// Get peak usage hours
$peakHours = [];
if ($voucher) {
    $stmtHours = $pdo->prepare("
        SELECT 
            HOUR(acctstarttime) as hour,
            SUM(acctinputoctets + acctoutputoctets) as total_bytes
        FROM radacct
        WHERE username = ?
        GROUP BY HOUR(acctstarttime)
        ORDER BY hour ASC
    ");
    $stmtHours->execute([$voucher['username']]);
    $peakHours = $stmtHours->fetchAll(PDO::FETCH_ASSOC);
}

// Get payment history
$stmtPayments = $pdo->prepare("
    SELECT p.*, pl.title as plan_title, pl.price as plan_price
    FROM payments p
    LEFT JOIN plans pl ON p.plan_id = pl.id
    WHERE p.phone = ?
    ORDER BY p.created_at DESC
");
$stmtPayments->execute([$user['phone']]);
$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

// Get payments this year (monthly)
$paymentsYear = [];
$stmtYear = $pdo->prepare("
    SELECT 
        MONTH(p.created_at) as month,
        SUM(pl.price) as total_amount,
        COUNT(*) as count
    FROM payments p
    LEFT JOIN plans pl ON p.plan_id = pl.id
    WHERE p.phone = ? 
    AND YEAR(p.created_at) = YEAR(CURDATE())
    AND p.status = 'success'
    GROUP BY MONTH(p.created_at)
    ORDER BY month ASC
");
$stmtYear->execute([$user['phone']]);
$paymentsYear = $stmtYear->fetchAll(PDO::FETCH_ASSOC);

// Get session history
$stmtSessions = $pdo->prepare("
    SELECT *
    FROM radacct
    WHERE username = ?
    ORDER BY acctstarttime DESC
    LIMIT 50
");
$stmtSessions->execute([$voucher['username'] ?? '']);
$sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format bytes
function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Helper function to format duration
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details - <?= htmlspecialchars($user['name']) ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <a href="users_manage.php" class="back-link">← Back to Users</a>
    
    <!-- User Header -->
    <div class="user-header">
        <div class="user-header-top">
            <div class="user-info-left">
                <h2>
                    <?= htmlspecialchars($voucher['username'] ?? 'No Voucher') ?>
                    <span class="status-indicator <?= $isOnline ? 'status-online' : 'status-offline' ?>">
                        <?= $isOnline ? '● Online' : '○ Offline' ?>
                    </span>
                </h2>
                <div style="color: #666; font-size: 14px;">
                    Customer: <?= htmlspecialchars($user['name']) ?> | Phone: <?= htmlspecialchars($user['phone']) ?>
                </div>
            </div>
            
            <div class="action-buttons">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_status">
                    <button type="submit" class="btn <?= $user['status'] === 'active' ? 'btn-secondary' : 'btn-primary' ?>">
                        <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                    <input type="hidden" name="action" value="delete_user">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
        
        <div class="user-meta">
            <div class="meta-item">
                <div class="meta-label">Current Package</div>
                <div class="meta-value"><?= htmlspecialchars($voucher['plan_type'] ?? 'No Plan') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                        <?= ucfirst($user['status']) ?>
                    </span>
                </div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Expiry Date</div>
                <div class="meta-value">
                    <?php
                    if ($voucher && $voucher['expiry']) {
                        $expiryTime = strtotime($voucher['expiry']);
                        $now = time();
                        if ($expiryTime > $now) {
                            echo '<span class="badge badge-success">' . date('M d, Y H:i', $expiryTime) . '</span>';
                        } else {
                            echo '<span class="badge badge-danger">Expired</span>';
                        }
                    } else {
                        echo '<span class="badge badge-warning">No expiry</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tabs">
        <div class="tab-headers">
            <button class="tab-header active" onclick="switchTab(0)">General Information</button>
            <button class="tab-header" onclick="switchTab(1)">Reports</button>
            <button class="tab-header" onclick="switchTab(2)">Payments</button>
            <button class="tab-header" onclick="switchTab(3)">Sessions</button>
        </div>
        
        <!-- General Information Tab -->
        <div class="tab-content active">
            <h3 style="margin-top: 0;">User Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value"><?= ucfirst($user['role']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value"><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></div>
                </div>
            </div>
            
            <?php if ($voucher): ?>
            <h3 style="margin-top: 30px;">Voucher Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?= htmlspecialchars($voucher['username']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Password</div>
                    <div class="info-value">
                        <div class="password-field">
                            <span id="password-text">••••••</span>
                            <span class="password-toggle" onclick="togglePassword()" title="Show/Hide Password">👁️</span>
                        </div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Plan Type</div>
                    <div class="info-value"><?= htmlspecialchars($voucher['plan_type']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Rate Limit</div>
                    <div class="info-value"><?= htmlspecialchars($voucher['rate_limit']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Voucher Status</div>
                    <div class="info-value">
                        <span class="badge <?= $voucher['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($voucher['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?= date('M d, Y H:i', strtotime($voucher['created_at'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Expires</div>
                    <div class="info-value"><?= $voucher['expiry'] ? date('M d, Y H:i', strtotime($voucher['expiry'])) : 'Never' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">MAC Address</div>
                    <div class="info-value"><?= htmlspecialchars($voucher['mac_address'] ?? 'Not bound') ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Reports Tab -->
        <div class="tab-content">
            <h3 style="margin-top: 0;">Usage Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Upload</div>
                    <div class="stat-value"><?= formatBytes($dataStats['total_upload'] ?? 0) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Download</div>
                    <div class="stat-value"><?= formatBytes($dataStats['total_download'] ?? 0) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Sessions</div>
                    <div class="stat-value"><?= number_format($dataStats['total_sessions'] ?? 0) ?></div>
                </div>
            </div>
            
            <?php if ($voucher): ?>
            <h3>Expiry Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Expiry Date</div>
                    <div class="info-value">
                        <?php
                        if ($voucher['expiry']) {
                            $expiryTime = strtotime($voucher['expiry']);
                            $now = time();
                            echo date('M d, Y H:i:s', $expiryTime);
                            if ($expiryTime > $now) {
                                $diff = $expiryTime - $now;
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);
                                echo " <span class='badge badge-success'>({$days}d {$hours}h remaining)</span>";
                            } else {
                                $diff = $now - $expiryTime;
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);
                                echo " <span class='badge badge-danger'>(Expired {$days}d {$hours}h ago)</span>";
                            }
                        } else {
                            echo 'Never expires';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Connection Status</div>
                    <div class="info-value">
                        <span class="badge <?= $isOnline ? 'badge-success' : 'badge-danger' ?>">
                            <?= $isOnline ? 'Currently Online' : 'Offline' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-grid">
                <div class="chart-container">
                    <h4>📊 Data Usage This Month</h4>
                    <div class="chart-wrapper">
                        <canvas id="dataUsageChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h4>⏰ Peak Usage Hours</h4>
                    <div class="chart-wrapper">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container full-width">
                    <h4>💰 Payments This Year</h4>
                    <div class="chart-wrapper">
                        <canvas id="paymentsYearChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Payments Tab -->
        <div class="tab-content">
            <h3 style="margin-top: 0;">Payment History</h3>
            <?php if (empty($payments)): ?>
                <p style="color: #666; padding: 20px; text-align: center;">No payment records found.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Status</th>
                        <th>Gateway</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></td>
                        <td><?= htmlspecialchars($payment['plan_title'] ?? 'N/A') ?></td>
                        <td>KES <?= number_format($payment['plan_price'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($payment['mpesa_receipt'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge <?= $payment['status'] === 'success' ? 'badge-success' : ($payment['status'] === 'pending' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= ucfirst($payment['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($payment['gateway'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- Sessions Tab -->
        <div class="tab-content">
            <h3 style="margin-top: 0;">Session History</h3>
            <?php if (empty($sessions)): ?>
                <p style="color: #666; padding: 20px; text-align: center;">No session records found.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Upload</th>
                        <th>Download</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($session['acctstarttime'])) ?></td>
                        <td><?= $session['acctstoptime'] ? date('M d, Y H:i', strtotime($session['acctstoptime'])) : '<span class="badge badge-success">Active</span>' ?></td>
                        <td><?= formatDuration($session['acctsessiontime'] ?? 0) ?></td>
                        <td><?= formatBytes($session['acctinputoctets'] ?? 0) ?></td>
                        <td><?= formatBytes($session['acctoutputoctets'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($session['framedipaddress'] ?? 'N/A') ?></td>
                        <td>
                            <?php if ($session['acctstoptime']): ?>
                                <span class="badge badge-danger">Ended</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const actualPassword = <?= json_encode($plainPassword) ?>;
let passwordVisible = false;

function togglePassword() {
    passwordVisible = !passwordVisible;
    const passwordText = document.getElementById('password-text');
    if (passwordVisible) {
        passwordText.textContent = actualPassword;
    } else {
        passwordText.textContent = '••••••';
    }
}

function switchTab(index) {
    const headers = document.querySelectorAll('.tab-header');
    const contents = document.querySelectorAll('.tab-content');
    
    headers.forEach((header, i) => {
        if (i === index) {
            header.classList.add('active');
        } else {
            header.classList.remove('active');
        }
    });
    
    contents.forEach((content, i) => {
        if (i === index) {
            content.classList.add('active');
        } else {
            content.classList.remove('active');
        }
    });
}

// Prepare chart data
<?php
// Prepare data for Data Usage This Month chart
$dataUsageDays = [];
$dataUsageValues = [];
foreach ($dataUsageMonth as $row) {
    $dataUsageDays[] = date('M d', strtotime($row['day']));
    $dataUsageValues[] = round($row['total_bytes'] / 1048576, 2); // Convert to MB
}

// Prepare data for Peak Hours chart
$peakHoursLabels = [];
$peakHoursValues = [];
for ($i = 0; $i < 24; $i++) {
    $peakHoursLabels[] = sprintf("%02d:00", $i);
    $found = false;
    foreach ($peakHours as $row) {
        if ($row['hour'] == $i) {
            $peakHoursValues[] = round($row['total_bytes'] / 1048576, 2); // Convert to MB
            $found = true;
            break;
        }
    }
    if (!$found) {
        $peakHoursValues[] = 0;
    }
}

// Prepare data for Payments This Year chart
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$paymentsMonths = [];
$paymentsAmounts = [];
for ($i = 1; $i <= 12; $i++) {
    $paymentsMonths[] = $monthNames[$i];
    $found = false;
    foreach ($paymentsYear as $row) {
        if ($row['month'] == $i) {
            $paymentsAmounts[] = floatval($row['total_amount']);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $paymentsAmounts[] = 0;
    }
}
?>

const dataUsageData = {
    labels: <?= json_encode($dataUsageDays) ?>,
    datasets: [{
        label: 'Data Usage (MB)',
        data: <?= json_encode($dataUsageValues) ?>,
        borderColor: '#2196f3',
        backgroundColor: 'rgba(33, 150, 243, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4
    }]
};

const peakHoursData = {
    labels: <?= json_encode($peakHoursLabels) ?>,
    datasets: [{
        label: 'Data Usage (MB)',
        data: <?= json_encode($peakHoursValues) ?>,
        backgroundColor: 'rgba(76, 175, 80, 0.6)',
        borderColor: '#4caf50',
        borderWidth: 1
    }]
};

const paymentsYearData = {
    labels: <?= json_encode($paymentsMonths) ?>,
    datasets: [{
        label: 'Payments (KES)',
        data: <?= json_encode($paymentsAmounts) ?>,
        backgroundColor: 'rgba(255, 152, 0, 0.6)',
        borderColor: '#ff9800',
        borderWidth: 2,
        fill: true
    }]
};

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Data Usage Chart
    const dataUsageCtx = document.getElementById('dataUsageChart');
    if (dataUsageCtx) {
        new Chart(dataUsageCtx, {
            type: 'line',
            data: dataUsageData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' MB';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Peak Hours Chart
    const peakHoursCtx = document.getElementById('peakHoursChart');
    if (peakHoursCtx) {
        new Chart(peakHoursCtx, {
            type: 'line',
            data: peakHoursData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' MB';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Payments Year Chart
    const paymentsYearCtx = document.getElementById('paymentsYearChart');
    if (paymentsYearCtx) {
        new Chart(paymentsYearCtx, {
            type: 'bar',
            data: paymentsYearData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
