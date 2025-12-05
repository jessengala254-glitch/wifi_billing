<?php
ini_set('display_errors', 1);
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

// Pagination for vouchers
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$totalVouchers = $pdo->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
$totalPages = ceil($totalVouchers / $perPage);

// Fetch recent vouchers with pagination
$stmt = $pdo->prepare("SELECT id, username, plan_type, rate_limit, expiry, status, created_at, phone FROM vouchers ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
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
        
        .rate-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
        }
        
        .rate-5m {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .rate-10m {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .rate-20m {
            background: #fff3e0;
            color: #e65100;
        }
        
        .rate-other {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        
        .pagination {
            margin-top: 15px;
            text-align: center;
        }
        
        .pagination a {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 2px;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        
        .pagination a.active {
            background-color: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        .pagination a:hover {
            background-color: #ddd;
        }
    </style>

</head>
<body>
<?php include 'sidebar.php'; ?>
    <div class="container">
        <h1>Manage Plans</h1>
        <div class="outer-board">
            <h2>Current Plans</h2>
            
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
                        <a href="?page=<?= $page-1 ?>">« Previous</a>
                    <?php endif; ?>

                    <?php for($p=1; $p<=$totalPages; $p++): ?>
                        <a href="?page=<?= $p ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>">Next »</a>
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


