<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin/plans_manage.php
require_once __DIR__ . '/../inc/functions.php';
session_start();

// Very basic admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

// ✅ Logging helper function (add this if not already in functions.php)
if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    }
}

// add/edit/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_SESSION['user_id'];

    if (isset($_POST['create'])) {
        $stmt = $pdo->prepare("INSERT INTO plans (title,price,duration_minutes,description,active) VALUES (?,?,?,?,1)");
        $stmt->execute([$_POST['title'], $_POST['price'], $_POST['duration_minutes'], $_POST['description']]);

        // ✅ Log the creation action
        log_action($pdo, $admin_id, "Create Plan", "Added plan '{$_POST['title']}' costing Ksh {$_POST['price']}");

    } elseif (isset($_POST['delete'])) {
        // Get plan info before deletion
        $plan = $pdo->prepare("SELECT title FROM plans WHERE id=?");
        $plan->execute([$_POST['id']]);
        $plan_title = $plan->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM plans WHERE id=?");
        $stmt->execute([$_POST['id']]);

        // ✅ Log the deletion
        log_action($pdo, $admin_id, "Delete Plan", "Deleted plan '{$plan_title}' (ID #{$_POST['id']})");
    }

    header('Location: plans_manage.php');
    exit;
}

$plans = $pdo->query("SELECT * FROM plans")->fetchAll();

// Get plan statistics
$planFilter = $_GET['plan_filter'] ?? 'all';

// Total plans count
$totalPlansStmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE active = 1");
$totalPlans = $totalPlansStmt->fetchColumn();

// Hotspot plans
$hotspotPlansStmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE active = 1 AND (title LIKE '%hotspot%' OR description LIKE '%hotspot%')");
$hotspotPlans = $hotspotPlansStmt->fetchColumn();

// PPPoE plans
$pppoePlansStmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE active = 1 AND (title LIKE '%pppoe%' OR description LIKE '%pppoe%')");
$pppoePlans = $pppoePlansStmt->fetchColumn();

// Free trial plans
$freeTrialStmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE active = 1 AND price = 0");
$freeTrialPlans = $freeTrialStmt->fetchColumn();

// Pagination for vouchers
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$voucherFilter = $_GET['voucher_filter'] ?? 'all';
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get voucher statistics
// Total vouchers
$totalVouchersStmt = $pdo->query("SELECT COUNT(*) FROM vouchers");
$totalVouchers = $totalVouchersStmt->fetchColumn();

// Hotspot vouchers
$hotspotVouchersStmt = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE plan_type NOT LIKE '%pppoe%'");
$hotspotVouchers = $hotspotVouchersStmt->fetchColumn();

// PPPoE vouchers
$pppoeVouchersStmt = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE plan_type LIKE '%pppoe%'");
$pppoeVouchers = $pppoeVouchersStmt->fetchColumn();

// Expired vouchers
$expiredVouchersStmt = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE expiry < NOW() AND status != 'active'");
$expiredVouchers = $expiredVouchersStmt->fetchColumn();

// Online vouchers
$onlineVouchersStmt = $pdo->query("SELECT COUNT(DISTINCT v.id) FROM vouchers v INNER JOIN radacct r ON v.username = r.username WHERE r.acctstoptime IS NULL");
$onlineVouchers = $onlineVouchersStmt->fetchColumn();

// Active vouchers
$activeVouchersStmt = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE status = 'active' AND expiry > NOW()");
$activeVouchers = $activeVouchersStmt->fetchColumn();

// Build voucher query based on filter
$voucherQuery = "SELECT id, username, plan_type, rate_limit, expiry, status, created_at, phone FROM vouchers WHERE 1=1";

if ($voucherFilter === 'hotspot') {
    $voucherQuery .= " AND plan_type NOT LIKE '%pppoe%'";
} elseif ($voucherFilter === 'pppoe') {
    $voucherQuery .= " AND plan_type LIKE '%pppoe%'";
} elseif ($voucherFilter === 'expired') {
    $voucherQuery .= " AND expiry < NOW() AND status != 'active'";
} elseif ($voucherFilter === 'online') {
    $voucherQuery .= " AND id IN (SELECT DISTINCT v.id FROM vouchers v INNER JOIN radacct r ON v.username = r.username WHERE r.acctstoptime IS NULL)";
} elseif ($voucherFilter === 'active') {
    $voucherQuery .= " AND status = 'active' AND expiry > NOW()";
}

$voucherQuery .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// Fetch recent vouchers with pagination
$stmt = $pdo->prepare($voucherQuery);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plans Manage</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="container">
        <h1>Manage Plans</h1>
        <div class="outer-board">
            <h2>Current Plans</h2>
            <p class="chart-description">Manage your WiFi subscription plans. Configure pricing, duration, and plan details.</p>
            
            <!-- Plan Tabs -->
            <div class="plan-tabs">
                <a href="?plan_filter=all&page=<?= $page ?>&voucher_filter=<?= $voucherFilter ?>" 
                   class="plan-tab <?= $planFilter === 'all' ? 'active' : '' ?>">
                    <span class="tab-icon">📋</span>
                    <span>All Plans</span>
                    <span class="tab-count"><?= $totalPlans ?></span>
                </a>
                <a href="?plan_filter=hotspot&page=<?= $page ?>&voucher_filter=<?= $voucherFilter ?>" 
                   class="plan-tab <?= $planFilter === 'hotspot' ? 'active' : '' ?>">
                    <span class="tab-icon">📡</span>
                    <span>Hotspot</span>
                    <span class="tab-count"><?= $hotspotPlans ?></span>
                </a>
                <a href="?plan_filter=pppoe&page=<?= $page ?>&voucher_filter=<?= $voucherFilter ?>" 
                   class="plan-tab <?= $planFilter === 'pppoe' ? 'active' : '' ?>">
                    <span class="tab-icon">🔗</span>
                    <span>PPPoE</span>
                    <span class="tab-count"><?= $pppoePlans ?></span>
                </a>
                <a href="?plan_filter=trial&page=<?= $page ?>&voucher_filter=<?= $voucherFilter ?>" 
                   class="plan-tab <?= $planFilter === 'trial' ? 'active' : '' ?>">
                    <span class="tab-icon">🎁</span>
                    <span>Free Trial</span>
                    <span class="tab-count"><?= $freeTrialPlans ?></span>
                </a>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Price (Ksh)</th>
                        <th>Duration (mins)</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($plans as $p): ?>
                        <tr>
                            <td data-label="Title"><?= htmlspecialchars($p['title']) ?></td>
                            <td data-label="Price (Ksh)"><?= number_format($p['price'], 2) ?></td>
                            <td data-label="Duration (mins)"><?= $p['duration_minutes'] ?></td>
                            <td data-label="Description"><?= htmlspecialchars($p['description']) ?></td>
                            <td data-label="Action">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button name="delete" onclick="return confirm('Delete this plan?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="outer-board">
            <h2>Create New Plan</h2>
            <form method="POST">
                <label>Title</label>
                <input name="title" required>

                <label>Price (Ksh)</label>
                <input name="price" required type="number" step="0.01">

                <label>Duration (minutes)</label>
                <input name="duration_minutes" required type="number">

                <label>Description</label>
                <textarea name="description"></textarea>

                <button name="create">Create</button>
            </form>
        </div>

        <div class="outer-board">
            <h2>Recent Vouchers</h2>
            <p class="chart-description">Track generated vouchers and their current status. Monitor active, expired, and online users.</p>
            
            <!-- Voucher Tabs -->
            <div class="voucher-tabs">
                <a href="?voucher_filter=all&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'all' ? 'active' : '' ?>">
                    <span class="tab-icon">🎫</span>
                    <span>All Vouchers</span>
                    <span class="tab-count"><?= $totalVouchers ?></span>
                </a>
                <a href="?voucher_filter=hotspot&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'hotspot' ? 'active' : '' ?>">
                    <span class="tab-icon">📡</span>
                    <span>Hotspot</span>
                    <span class="tab-count"><?= $hotspotVouchers ?></span>
                </a>
                <a href="?voucher_filter=pppoe&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'pppoe' ? 'active' : '' ?>">
                    <span class="tab-icon">🔗</span>
                    <span>PPPoE</span>
                    <span class="tab-count"><?= $pppoeVouchers ?></span>
                </a>
                <a href="?voucher_filter=expired&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'expired' ? 'active' : '' ?>">
                    <span class="tab-icon">⏰</span>
                    <span>Expired</span>
                    <span class="tab-count"><?= $expiredVouchers ?></span>
                </a>
                <a href="?voucher_filter=online&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'online' ? 'active' : '' ?>">
                    <span class="tab-icon">🟢</span>
                    <span>Online</span>
                    <span class="tab-count"><?= $onlineVouchers ?></span>
                </a>
                <a href="?voucher_filter=active&plan_filter=<?= $planFilter ?>" 
                   class="voucher-tab <?= $voucherFilter === 'active' ? 'active' : '' ?>">
                    <span class="tab-icon">✅</span>
                    <span>Active</span>
                    <span class="tab-count"><?= $activeVouchers ?></span>
                </a>
            </div>
            
            <div id="voucher-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Plan Type</th>
                            <th>Rate Limit</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Phones</th>
                        </tr>
                    </thead>
                    <tbody id="voucher-tbody">
                        <?php foreach ($vouchers as $v): 
                            // Determine rate limit class
                            $rateLimit = $v['rate_limit'] ?? '-';
                            $rateClass = 'rate-other';
                            if (strpos($rateLimit, '5M/5M') !== false) {
                                $rateClass = 'rate-5m';
                            } elseif (strpos($rateLimit, '10M/10M') !== false) {
                                $rateClass = 'rate-10m';
                            } elseif (strpos($rateLimit, '20M/20M') !== false) {
                                $rateClass = 'rate-20m';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($v['id'] ?? '') ?></td>
                            <td><span class="username-badge"><?= htmlspecialchars($v['username'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($v['plan_type'] ?? 'Unknown') ?></td>
                            <td><span class="rate-badge <?= $rateClass ?>"><?= htmlspecialchars($rateLimit) ?></span></td>
                            <td><?= htmlspecialchars($v['expiry'] ?? '-') ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($v['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($v['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($v['created_at'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($v['phone'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&voucher_filter=<?= $voucherFilter ?>&plan_filter=<?= $planFilter ?>">« Previous</a>
                    <?php endif; ?>

                    <?php 
                    // Recalculate total pages based on filtered vouchers
                    $countQuery = "SELECT COUNT(*) FROM vouchers WHERE 1=1";
                    if ($voucherFilter === 'hotspot') {
                        $countQuery .= " AND plan_type NOT LIKE '%pppoe%'";
                    } elseif ($voucherFilter === 'pppoe') {
                        $countQuery .= " AND plan_type LIKE '%pppoe%'";
                    } elseif ($voucherFilter === 'expired') {
                        $countQuery .= " AND expiry < NOW() AND status != 'active'";
                    } elseif ($voucherFilter === 'online') {
                        $countQuery .= " AND id IN (SELECT DISTINCT v.id FROM vouchers v INNER JOIN radacct r ON v.username = r.username WHERE r.acctstoptime IS NULL)";
                    }
                    $totalFiltered = $pdo->query($countQuery)->fetchColumn();
                    $totalPages = ceil($totalFiltered / $perPage);
                    
                    for($p=1; $p<=$totalPages; $p++): ?>
                        <a href="?page=<?= $p ?>&voucher_filter=<?= $voucherFilter ?>&plan_filter=<?= $planFilter ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&voucher_filter=<?= $voucherFilter ?>&plan_filter=<?= $planFilter ?>">Next »</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<script>
    function fetchVouchers() {
        fetch('fetch_vouchers.php')
            .then(res => res.text())
            .then(html => {
                document.getElementById('voucher-table').innerHTML = html;
            })
            .catch(err => console.error(err));
    }

    // Refresh every 5 seconds
    setInterval(fetchVouchers, 30000);
</script>

</body>
</html>


