<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
// $pdo->exec("SET time_zone = '+03:00'");
$admin_id = $_SESSION['user_id'];

// Logs function
if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        log_action($pdo, $admin_id, "Delete User", "Deleted user {$user['name']}");
    }
    header("Location: users_manage.php");
    exit;
}

// Handle activate/deactivate
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT name, status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        $action = ($newStatus === 'active') ? 'Activate User' : 'Deactivate User';
        log_action($pdo, $admin_id, $action, "{$action} - {$user['name']}");
    }
    header("Location: users_manage.php");
    exit;
}

// -----------------------------------------
// Read GET parameters
// -----------------------------------------
$search = $_GET['search'] ?? '';
$sort   = $_GET['sort'] ?? 'desc'; 
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

$perPage = 20;
$offset  = ($page - 1) * $perPage;

// -----------------------------------------
// Build Base Queries (NAMED PARAMETERS ONLY)
// -----------------------------------------
$query       = "SELECT id, name, phone, role, status, voucher_id, created_at FROM users WHERE 1=1";
$countQuery  = "SELECT COUNT(*) FROM users WHERE 1=1";
$params      = [];
$countParams = [];

// -----------------------------------------
// Search
// -----------------------------------------
if (!empty($search)) {
    $query      .= " AND (name LIKE :search OR phone LIKE :search)";
    $countQuery .= " AND (name LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
    $countParams[':search'] = "%$search%";
}

// -----------------------------------------
// Sorting
// -----------------------------------------
$sortOrder = ($sort === 'asc') ? 'ASC' : 'DESC';
$query .= " ORDER BY created_at $sortOrder";

// -----------------------------------------
// Pagination
// -----------------------------------------
$query .= " LIMIT :limit OFFSET :offset";
$params[':limit']  = $perPage;
$params[':offset'] = $offset;

// -----------------------------------------
// Execute MAIN query
// -----------------------------------------
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------
// Execute COUNT query
// -----------------------------------------
$countStmt = $pdo->prepare($countQuery);
foreach ($countParams as $key => $value) {
    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();

$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users | Leo Konnect</title>
<link rel="stylesheet" href="admin_style.css">
<style>
    .router-dashboard { max-width:1200px; margin:auto; background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
    .active {color: green; font-weight:bold;}
    .inactive {color: red; font-weight:bold;}
    .exp-active {color: green; font-weight:bold;}
    .exp-expired {color: red; font-weight:bold;}
    
    .name-badge {
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
    
    .status-active {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .status-inactive {
        background: #ffebee;
        color: #c62828;
    }
    
    .expiry-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    
    .expiry-active {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .expiry-expired {
        background: #ffebee;
        color: #c62828;
    }
    
    .expiry-none {
        background: #f5f5f5;
        color: #757575;
    }
    
    /* .search-bar { margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;}
    .search-bar input { width:300px; padding:5px 10px; border-radius:5px; border:1px solid #ccc;} */
    .search-filter-grid {
        display: grid;
        grid-template-columns: 15fr 10fr 3fr;
        gap: 10px;
        width: 100%;
        margin-bottom: 10px;
        margin-left:auto;
    }
    .search-filter-grid input,
    .search-filter-grid select,
    .search-filter-grid button {
        padding: 8px 20px;
        font-size: 16px;
        box-sizing: border-box;
    }
    .search-filter-grid button {
        cursor: pointer;
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

<div class="main-content">
  <h1>Manage Users</h1>

  <div class="router-dashboard">

  <form method="get" class="search-filter-grid">
      <input type="text" name="search" placeholder="Search name or phone" value="<?= htmlspecialchars($search) ?>">
      <select name="sort">
          <option value="desc" <?= $sort==='desc'?'selected':'' ?>>Newest First</option>
          <option value="asc" <?= $sort==='asc'?'selected':'' ?>>Oldest First</option>
      </select>
      <button type="submit">Apply</button>
  </form>

  <table>
  <thead>
  <tr>
      <th>#</th>
      <th>Name</th>
      <th>Phone</th>
      <th>Role</th>
      <th>Status</th>
      <th>Expiry</th>
      <th>Last Online</th>
      <th>Actions</th>
  </tr>
  </thead>
  <tbody>
  <?php
  $i = $offset + 1;
  foreach ($users as $row):
      $userId = $row['id'];
      if (!empty($row['voucher_id'])) {
          $stmtVoucher = $pdo->prepare("
              SELECT username, plan_type, rate_limit, expiry, status
              FROM vouchers
              WHERE id = ?
              LIMIT 1
          ");
          $stmtVoucher->execute([$row['voucher_id']]);
      } else {
          $stmtVoucher = $pdo->query("
              SELECT username, plan_type, rate_limit, expiry, status
              FROM vouchers
              ORDER BY id DESC
              LIMIT 1
          ");
      }
      $currentPlan = $stmtVoucher->fetch(PDO::FETCH_ASSOC);

  $voucherUsername = $currentPlan['username'] ?? "N/A";
  $planName        = $currentPlan['plan_type'] ?? "NO PLAN";
  $planRate        = $currentPlan['rate_limit'] ?? "-";
  $planExpiry      = $currentPlan['expiry'] ?? "-";
  $planStatus      = $currentPlan['status'] ?? "-";

  $stmtRad = $pdo->prepare("
      SELECT acctstarttime, acctstoptime
      FROM radacct
      WHERE username = ?
      ORDER BY acctstarttime DESC
      LIMIT 1
  ");
  $stmtRad->execute([$voucherUsername]);
  $rad = $stmtRad->fetch(PDO::FETCH_ASSOC);

  if ($rad) {
      $last = $rad['acctstoptime'] ?? $rad['acctstarttime'];
      $lastSeconds = time() - strtotime($last);
      if ($lastSeconds < 60) $lastOnlineText = $lastSeconds . " sec ago";
      elseif ($lastSeconds < 3600) $lastOnlineText = floor($lastSeconds / 60) . " min ago";
      elseif ($lastSeconds < 86400) $lastOnlineText = floor($lastSeconds / 3600) . " hrs ago";
      else $lastOnlineText = floor($lastSeconds / 86400) . " days ago";
  } else {
      $lastOnlineText = "No data";
  }

  if ($planName === "NO PLAN" || !$planExpiry) {
      $expiryText = "<span class='expiry-badge expiry-none'>NO PLAN</span>";
  } else {
      $expiryTime = strtotime($planExpiry);
      $diff = $expiryTime - time();
      if ($diff <= 0) {
          $ago = abs($diff);
          if ($ago < 3600) $text = floor($ago/60). " min ago";
          elseif ($ago < 86400) $text = floor($ago/3600). " hrs ago";
          else $text = floor($ago/86400). " days ago";
          $expiryText = "<span class='expiry-badge expiry-expired'>Expired ($text)</span>";
      } else {
          if ($diff < 3600) $text = floor($diff/60). " min left";
          elseif ($diff < 86400) $text = floor($diff/3600). " hrs left";
          else $text = floor($diff/86400). " days left";
          $expiryText = "<span class='expiry-badge expiry-active'>Active ($text)</span>";
      }
  }

  ?>

  <tr>
      <td><?= $i++; ?></td>
      <td><span class="name-badge"><?= htmlspecialchars($row['name']); ?></span></td>
      <td><?= htmlspecialchars($row['phone']); ?></td>
      <td><?= htmlspecialchars($row['role']); ?></td>
      <td><span class="status-badge <?= ($row['status']==='active')?'status-active':'status-inactive' ?>"><?= ucfirst($row['status']) ?></span></td>
      <td><?= $expiryText ?></td>
      <td><?= $lastOnlineText ?></td>
      <td>
          <a href="?toggle=<?= (int)$row['id'] ?>" class="btn-sm toggle"><?= ($row['status']==='active')?'Deactivate':'Activate' ?></a>
          <a href="?delete=<?= (int)$row['id'] ?>" class="btn-sm delete" onclick="return confirm('Delete this user?');">Delete</a>
      </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>

  <!-- Pagination links -->
  <div class="pagination">
    <?php if($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= $sort ?>">« Previous</a>
    <?php endif; ?>

    <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?page=<?= $p ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= $sort ?>" 
        class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>

    <?php if($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&search=<?= htmlspecialchars($search) ?>&sort=<?= $sort ?>">Next »</a>
    <?php endif; ?>
    </div>


</div>
  </div>
</body>
</html>
