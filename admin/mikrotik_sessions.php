<?php
// mikrotik_sessions.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/mikrotik.php';
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = "";
$perPage = 20; 

// Pagination helper
function renderPagination($currentPage, $totalPages, $paramName='page') {
    $html = '';
    $maxLinks = 5; 
    $start = max(1, $currentPage - floor($maxLinks / 2));
    $end = min($totalPages, $start + $maxLinks - 1);
    $start = max(1, $end - $maxLinks + 1); // adjust start if near end

    // preserve existing GET params
    $queryParams = $_GET;
    unset($queryParams[$paramName]); 

    $baseUrl = '?' . http_build_query($queryParams);

    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        $html .= '<a href="' . $baseUrl . '&' . $paramName . '=' . $prevPage . '">&laquo; Prev</a>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $html .= '<a href="' . $baseUrl . '&' . $paramName . '=' . $i . '" class="' . $active . '">' . $i . '</a>';
    }

    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        $html .= '<a href="' . $baseUrl . '&' . $paramName . '=' . $nextPage . '">Next &raquo;</a>';
    }

    return $html;
}

// 1️⃣ Handle disconnect action
if (isset($_GET['disconnect'])) {
    $user_to_disconnect = $_GET['disconnect'];
    try {
        mikrotik_disconnect_active($user_to_disconnect);
        log_action($pdo, $_SESSION['user_id'], "Disconnect User", "Disconnected $user_to_disconnect", "info");
        $msg = "User $user_to_disconnect disconnected successfully.";
    } catch (Exception $e) {
        $msg = "Error disconnecting user: " . $e->getMessage();
    }
}

// 2️⃣ Pagination page numbers
$page_active = max(1, (int)($_GET['page_active'] ?? 1));
$page_rad = max(1, (int)($_GET['page_rad'] ?? 1));
$page_auth = max(1, (int)($_GET['page_auth'] ?? 1));
$page_users = max(1, (int)($_GET['page_users'] ?? 1));
$page_vouchers = max(1, (int)($_GET['page_vouchers'] ?? 1));

$offset_active = ($page_active - 1) * $perPage;
$offset_rad = ($page_rad - 1) * $perPage;
$offset_auth = ($page_auth - 1) * $perPage;
$offset_users = ($page_users - 1) * $perPage;
$offset_vouchers = ($page_vouchers - 1) * $perPage; 

// 3️⃣ Fetch active users
try {
    $all_active_users = mikrotik_list_active();
    $total_active = count($all_active_users);
    $active_users = array_slice($all_active_users, $offset_active, $perPage);
} catch (Exception $e) {
    $active_users = [];
    $total_active = 0;
    $msg = "Failed to fetch active users: " . $e->getMessage();
}
$totalPages_active = ceil($total_active / $perPage);

// 4️⃣ Fetch last radacct sessions
$stmt = $pdo->prepare("
    SELECT SQL_CALC_FOUND_ROWS 
           username, 
           acctstarttime, 
           acctstoptime, 
           framedipaddress, 
           callingstationid,
           ROUND(acctinputoctets / (1024*1024*1024), 4) AS input_gb,
           ROUND(acctoutputoctets / (1024*1024*1024), 4) AS output_gb,
           ROUND((acctinputoctets + acctoutputoctets) / (1024*1024*1024), 4) AS total_gb,
           acctterminatecause
    FROM radacct
    ORDER BY acctstarttime DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset_rad, PDO::PARAM_INT);
$stmt->execute();

$rad_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_rad = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages_rad = ceil($total_rad / $perPage);

// 5️⃣ Fetch radpostauth logs
$stmt2 = $pdo->prepare("
    SELECT SQL_CALC_FOUND_ROWS username, pass, reply, authdate
    FROM radpostauth
    ORDER BY authdate DESC
    LIMIT :limit OFFSET :offset
");
$stmt2->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt2->bindValue(':offset', $offset_auth, PDO::PARAM_INT);
$stmt2->execute();
$auths = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$total_auth = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages_auth = ceil($total_auth / $perPage);

// 6️⃣ Fetch radcheck users
$stmt3 = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS username, attribute, value FROM radcheck ORDER BY username ASC LIMIT :limit OFFSET :offset");
$stmt3->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt3->bindValue(':offset', $offset_users, PDO::PARAM_INT);
$stmt3->execute();
$users = $stmt3->fetchAll(PDO::FETCH_ASSOC);
$total_users = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages_users = ceil($total_users / $perPage);

// 7️⃣ Fetch vouchers with payments
$stmt4 = $pdo->prepare("
    SELECT SQL_CALC_FOUND_ROWS
        v.id,
        v.username AS voucher_code,
        v.plan_type,
        v.expiry,
        v.status AS voucher_status,
        v.created_at,
        p.amount,
        p.phone,
        p.status AS payment_status,
        p.transaction_time
    FROM vouchers v
    LEFT JOIN (
        SELECT p1.*
        FROM payments p1
        INNER JOIN (
            SELECT plan_id, MAX(transaction_time) AS latest_payment
            FROM payments
            GROUP BY plan_id
        ) p2 ON p1.plan_id = p2.plan_id AND p1.transaction_time = p2.latest_payment
    ) p ON v.plan_id = p.plan_id
    ORDER BY v.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt4->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt4->bindValue(':offset', $offset_vouchers, PDO::PARAM_INT);
$stmt4->execute();
$vouchers = $stmt4->fetchAll(PDO::FETCH_ASSOC);
$total_vouchers = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages_vouchers = ceil($total_vouchers / $perPage);

// 8️⃣ Fetch expired users
try {
    $now = date('Y-m-d H:i:s');
    $stmt6 = $pdo->prepare("
        SELECT username 
        FROM subscriptions
        WHERE status='active' AND (plan_end <= ? OR data_used >= data_limit)
    ");
    $stmt6->execute([$now]);
    $expired_users = $stmt6->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $expired_users = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RADIUS Dashboard</title>
<link rel="stylesheet" href="admin_style.css">
<style>
    .status-active { color: green; font-weight: bold; }
    .status-expired { color: red; font-weight: bold; }
    
    .username-badge {
        display: inline-block;
        padding: 4px 8px;
        background: #e8f5e9;
        color: #2e7d32;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    
    .reply-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    
    .reply-accept {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .reply-reject {
        background: #ffebee;
        color: #c62828;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    
    .status-badge.status-active {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .status-badge.status-expired {
        background: #ffebee;
        color: #c62828;
    }
    
    tr.expired { background:#ffdddd; }
    .badge { padding:4px 8px; border-radius:6px; color:#fff; font-size:12px; }
    .badge-online { background:#28a745; }
    .badge-expired { background:#dc3545; }
    .disconnect-btn { padding:4px 6px; background:#d9534f; color:white; border-radius:5px; text-decoration:none; }
    .disconnect-btn:hover { background:#c9302c; }

    .outer-board { 
        max-width:1200px; 
        margin:auto; 
        background:#fff; 
        border-radius:10px; 
        padding:20px; 
        margin-bottom: 30px; 
        box-shadow:0 2px 10px rgba(0,0,0,0.1);
    }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Auto-refresh every 10 seconds
    setTimeout(function(){ location.reload(); }, 30000);
</script>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">

    <?php if (!empty($msg)) echo "<div>{$msg}</div>"; ?>
    <div class="outer-board">
    <!-- Active Sessions -->
    <div id="active-sessions-table">
        <h2>Active Sessions</h2>
        <table>
            <thead>
                <tr><th>Username</th><th>IP</th><th>MAC</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if(!empty($active_users)): ?>
                <?php foreach($active_users as $u): 
                    $username = $u['name'] ?? '';
                    $ip = $u['address'] ?? '-';
                    $mac_from_router = $u['caller-id'] ?? '-';

                    // Fetch MAC from radcheck table if bound
                    $stmt = $pdo->prepare("SELECT value FROM radcheck WHERE username=? AND attribute='Calling-Station-Id'");
                    $stmt->execute([$username]);
                    $mac_bound = $stmt->fetchColumn() ?: '-';
                    $isExpired = in_array($username, $expired_users);
                ?>
                <tr class="<?= $isExpired ? 'expired' : '' ?>">
                    <td><span class="username-badge"><?= htmlspecialchars($username) ?></span></td>
                    <td><?= htmlspecialchars($ip) ?></td>
                    <td><?= htmlspecialchars($mac_from_router) ?> (DB: <?= htmlspecialchars($mac_bound) ?>)</td>
                    <td><?= $isExpired ? '<span class="badge badge-expired">Expired</span>' : '<span class="badge badge-online">Online</span>' ?></td>
                    <td><a class="disconnect-btn" href="?disconnect=<?= urlencode($username) ?>">Disconnect</a></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" align="center">No active users</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination" data-table="active-sessions">
            <?= renderPagination($page_active, $totalPages_active, 'page_active') ?>
        </div>
    </div>
    </div>


    <!-- Recent Sessions -->
    <div id="recent-sessions-table">
        <div class="outer-board">
        <h2>Recent Sessions</h2>
        <table>
            <thead>
                <tr>
                    <th>User</th><th>IP Address</th><th>MAC Address</th><th>Start Time</th><th>Stop Time</th>
                    <th>Input (GB)</th><th>Output (GB)</th><th>Total (GB)</th><th>Terminate Cause</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($rad_sessions)): ?>
                <?php foreach($rad_sessions as $s): ?>
                <tr class="<?= in_array($s['username'], $expired_users) ? 'expired' : '' ?>">
                    <td><span class="username-badge"><?= htmlspecialchars($s['username']) ?></span></td>
                    <td><?= htmlspecialchars($s['framedipaddress'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($s['callingstationid'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($s['acctstarttime']) ?></td>
                    <td><?= htmlspecialchars($s['acctstoptime'] ?: '-') ?></td>
                    <td><?= number_format((float)($s['input_gb'] ?? 0),3) ?> GB</td>
                    <td><?= number_format((float)($s['output_gb'] ?? 0),3) ?> GB</td>
                    <td><?= number_format((float)($s['total_gb'] ?? 0),3) ?> GB</td>
                    <td><?= htmlspecialchars($s['acctterminatecause'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" align="center">No session records found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination" data-table="recent-sessions">
            <?= renderPagination($page_rad, $totalPages_rad, 'page_rad') ?>
        </div>
        </div>
    </div>

    <!-- PostAuth Logs -->
    <div id="postauth-logs-table">
        <div class="outer-board">
            <h2>PostAuth Logs</h2>
            <table>
                <thead><tr><th>Username</th><th>Password</th><th>Reply</th><th>Auth Time</th></tr></thead>
                <tbody>
                    <?php foreach($auths as $a): 
                        $replyClass = ($a['reply'] === 'Access-Accept') ? 'reply-accept' : 'reply-reject';
                    ?>
                    <tr class="<?= $a['reply'] !== 'Access-Accept' ? 'expired' : '' ?>">
                        <td><span class="username-badge"><?= htmlspecialchars($a['username']) ?></span></td>
                        <td><?= htmlspecialchars($a['pass']) ?></td>
                        <td><span class="reply-badge <?= $replyClass ?>"><?= htmlspecialchars($a['reply']) ?></span></td>
                        <td><?= htmlspecialchars($a['authdate']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination" data-table="postauth-logs">
                <?= renderPagination($page_auth, $totalPages_auth, 'page_auth') ?>
            </div>
        </div>
    </div>


    <!-- Users (radcheck) -->
    <div class="outer-board">
        <h2>Users (radcheck)</h2>
        <table>
            <thead>
                <tr><th>Username</th><th>Attribute</th><th>Value</th></tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><span class="username-badge"><?= htmlspecialchars($u['username']) ?></span></td>
                    <td><?= htmlspecialchars($u['attribute']) ?></td>
                    <td><?= htmlspecialchars($u['value']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php echo renderPagination($page_users, $totalPages_users, 'page_users'); ?>
        </div>
    </div>

    <!-- Voucher Payments -->
    <div class="outer-board">
        <h2>Voucher Payments</h2>
        <table>
            <thead>
                <tr><th>Username</th><th>Plan</th><th>Expiry</th><th>Status</th><th>Amount</th><th>Phone</th><th>Payment status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($vouchers)): ?>
                    <?php foreach ($vouchers as $v): ?>
                    <tr>
                        <td><span class="username-badge"><?= htmlspecialchars($v['voucher_code']) ?></span></td>
                        <td><?= htmlspecialchars($v['plan_type']) ?></td>
                        <td><?= htmlspecialchars($v['expiry']) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($v['voucher_status']) ?>">
                                <?= ucfirst(htmlspecialchars($v['voucher_status'])) ?>
                            </span>
                        </td>
                        <!-- <td><?= htmlspecialchars($v['voucher_status']) ?></td> -->
                        <td><?= htmlspecialchars($v['amount'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['payment_status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['transaction_time'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" align="center">No voucher records found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php echo renderPagination($page_vouchers, $totalPages_vouchers, 'page_vouchers'); ?>
        </div>
    </div>

</div>

</body>
</html>
