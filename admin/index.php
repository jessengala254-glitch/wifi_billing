<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

// get admin display name (prefer session username, fallback to DB lookup)
$adminName = 'Admin';
if (!empty($_SESSION['username'])) {
    $adminName = $_SESSION['username'];
} elseif (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $adminName = $row['name'] ?: $row['username'] ?: $adminName;
    }
}

// compute greeting based on server local hour
$hour = (int) date('G');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good afternoon';
} elseif ($hour >= 17 && $hour < 22) {
    $greeting = 'Good evening';
} else {
    $greeting = 'Hello';
}

// Define items per page
$perPage = 20;

// Pagination helper function
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

try {
    // Total users
    $totalUsers = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE voucher_id IS NOT NULL")->fetch()['total'] ?? 0;

    // Total plans
    $totalPlans = $pdo->query("SELECT COUNT(*) AS total FROM plans")->fetch()['total'] ?? 0;

    // Total revenue
    // $totalRevenue = $pdo->query("
    //     SELECT IFNULL(SUM(p.amount),0) AS total
    //     FROM payments p
    //     INNER JOIN vouchers v ON p.plan_id = v.plan_id
    //     WHERE p.status = 'success'
    // ")->fetch()['total'] ?? 0;
    $totalRevenue = $pdo->query("
        SELECT IFNULL(SUM(amount),0) AS total
        FROM payments
        WHERE status = 'success'
    ")->fetch()['total'] ?? 0;

    // Active subscriptions (count active vouchers)
    $activeSubs = $pdo->query("
        SELECT COUNT(*) AS total
        FROM vouchers
        WHERE status = 'active'
    ")->fetch()['total'] ?? 0;

    // Data usage with dynamic period
    $dataUsagePeriod = $_GET['data_period'] ?? '7day';
    $dataPeriodMap = [
        '7day' => ['interval' => '7 DAY', 'label' => 'Last 7 Days'],
        '14day' => ['interval' => '14 DAY', 'label' => 'Last 14 Days'],
        '1month' => ['interval' => '1 MONTH', 'label' => 'Last Month'],
        '3month' => ['interval' => '3 MONTH', 'label' => 'Last 3 Months'],
        '6month' => ['interval' => '6 MONTH', 'label' => 'Last 6 Months']
    ];
    if (!isset($dataPeriodMap[$dataUsagePeriod])) {
        $dataUsagePeriod = '7day';
    }
    $dataPeriodConfig = $dataPeriodMap[$dataUsagePeriod];
    
    $dataUsage = $pdo->query("
        SELECT 
            DATE(acctstarttime) AS date,
            ROUND(SUM(acctinputoctets + acctoutputoctets)/(1024*1024),2) AS data_usage
        FROM radacct
        WHERE acctstarttime >= DATE_SUB(CURDATE(), INTERVAL {$dataPeriodConfig['interval']})
        GROUP BY DATE(acctstarttime)
        ORDER BY DATE(acctstarttime)
    ")->fetchAll(PDO::FETCH_ASSOC);
 
    // Active vouchers by plan with filter
    $voucherStatusFilter = $_GET['voucher_status'] ?? 'all';
    $voucherWhereClause = "1=1";
    if ($voucherStatusFilter === 'active') {
        $voucherWhereClause = "status = 'active'";
    } elseif ($voucherStatusFilter === 'expired') {
        $voucherWhereClause = "status = 'expired'";
    }
    
    $activeByPlan = $pdo->query("
        SELECT plan_type, COUNT(*) AS total
        FROM vouchers
        WHERE {$voucherWhereClause}
        GROUP BY plan_type
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $planLabels = array_column($activeByPlan, 'plan_type');
    $planValues = array_column($activeByPlan, 'total');

    // Customer retention - get period from GET parameter (default 6 months)
    $retentionPeriod = $_GET['retention_period'] ?? '6month';
    
    // Map periods to SQL intervals
    $periodMap = [
        '1week' => ['interval' => '1 WEEK', 'label' => '1 Week', 'format' => '%Y-%m-%d'],
        '14day' => ['interval' => '14 DAY', 'label' => '14 Days', 'format' => '%Y-%m-%d'],
        '1month' => ['interval' => '1 MONTH', 'label' => '1 Month', 'format' => '%b %Y'],
        '3month' => ['interval' => '3 MONTH', 'label' => '3 Months', 'format' => '%b %Y'],
        '6month' => ['interval' => '6 MONTH', 'label' => '6 Months', 'format' => '%b %Y'],
        '1year' => ['interval' => '1 YEAR', 'label' => '1 Year', 'format' => '%b %Y']
    ];
    
    // Validate and get period config
    if (!isset($periodMap[$retentionPeriod])) {
        $retentionPeriod = '6month';
    }
    $periodConfig = $periodMap[$retentionPeriod];
    
    // Customer retention query with dynamic period
    $retention = $pdo->prepare("
        SELECT DATE_FORMAT(MIN(created_at), ?) AS month, COUNT(*) AS new_users
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$periodConfig['interval']})
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(MIN(created_at)), MONTH(MIN(created_at))
    ");
    $retention->execute([$periodConfig['format']]);
    $retention = $retention->fetchAll(PDO::FETCH_ASSOC);

    // User registrations with dynamic period
    $userRegPeriod = $_GET['user_reg_period'] ?? '7day';
    $userRegPeriodMap = [
        '7day' => ['interval' => '7 DAY', 'label' => 'Last 7 Days'],
        '14day' => ['interval' => '14 DAY', 'label' => 'Last 14 Days'],
        '1month' => ['interval' => '1 MONTH', 'label' => 'Last Month'],
        '3month' => ['interval' => '3 MONTH', 'label' => 'Last 3 Months'],
        '6month' => ['interval' => '6 MONTH', 'label' => 'Last 6 Months']
    ];
    if (!isset($userRegPeriodMap[$userRegPeriod])) {
        $userRegPeriod = '7day';
    }
    $userRegPeriodConfig = $userRegPeriodMap[$userRegPeriod];
    
    $userRegs = $pdo->query("
        SELECT DATE(created_at) AS date, COUNT(*) AS count
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$userRegPeriodConfig['interval']})
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Plan Performance Table
    try {
        $sql = "
          SELECT 
              p.title AS name,
              p.price AS price,
              COUNT(v.id) AS total_users,
              SUM(p.price) AS monthly_revenue,
              CASE WHEN COUNT(v.id) > 0 THEN SUM(p.price)/COUNT(v.id) ELSE 0 END AS arpu,
              ROUND(IFNULL(AVG(ru.data_usage_gb), 0), 2) AS avg_usage_gb
          FROM vouchers v
          JOIN plans p ON v.plan_id = p.id
          LEFT JOIN (
              SELECT 
                  r.username,
                  SUM(r.acctinputoctets + r.acctoutputoctets)/(1024*1024*1024) AS data_usage_gb
              FROM radacct r
              WHERE MONTH(r.acctstarttime) = MONTH(CURRENT_DATE())
                AND YEAR(r.acctstarttime) = YEAR(CURRENT_DATE())
              GROUP BY r.username
          ) ru ON ru.username = v.username
          WHERE MONTH(v.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(v.created_at) = YEAR(CURRENT_DATE())
          GROUP BY p.id, p.title, p.price
          ORDER BY p.id;
          ";
        $stmt = $pdo->query($sql);
        $packagePerformance = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        $packagePerformance = [];
        echo "Database error: " . $e->getMessage();
    }

    // Data Usage Per User with pagination and filters
    $page_data_usage = max(1, (int)($_GET['page_data'] ?? 1));
    $offset_data_usage = ($page_data_usage - 1) * $perPage;
    $dataUsageStatusFilter = $_GET['data_status'] ?? 'all';
    $dataUsagePlanFilter = $_GET['data_plan'] ?? 'all';
    
    try {
        // Build WHERE clause
        $whereClause = "1=1";
        $params = [];
        
        if ($dataUsageStatusFilter === 'active') {
            $whereClause .= " AND v.status = 'active'";
        } elseif ($dataUsageStatusFilter === 'expired') {
            $whereClause .= " AND v.status = 'expired'";
        }
        
        if ($dataUsagePlanFilter !== 'all') {
            $whereClause .= " AND v.plan_type = ?";
            $params[] = $dataUsagePlanFilter;
        }
        
        // Count total records
        $countQuery = "
            SELECT COUNT(DISTINCT v.username)
            FROM vouchers v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE {$whereClause}
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $total_data_users = $countStmt->fetchColumn();
        $totalPages_data_usage = ceil($total_data_users / $perPage);
        
        // Fetch paginated data
        $dataQuery = "
            SELECT 
                u.id,
                v.username,
                u.phone,
                v.plan_type,
                ROUND(SUM(r.acctinputoctets)/(1024*1024*1024), 3) AS input_gb,
                ROUND(SUM(r.acctoutputoctets)/(1024*1024*1024), 3) AS output_gb,
                ROUND(SUM(r.acctinputoctets + r.acctoutputoctets)/(1024*1024*1024), 3) AS total_gb,
                v.status,
                v.created_at
            FROM vouchers v
            LEFT JOIN users u ON v.user_id = u.id
            LEFT JOIN radacct r ON r.username = v.username
            WHERE {$whereClause}
            GROUP BY u.id, v.username, u.phone, v.plan_type, v.status, v.created_at
            ORDER BY total_gb DESC
            LIMIT {$perPage} OFFSET {$offset_data_usage}
        ";
        $dataStmt = $pdo->prepare($dataQuery);
        $dataStmt->execute($params);
        $dataPerUser = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dataPerUser = [];
        echo "Database error: " . $e->getMessage();
    }

    

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leo Konnect | Admin Dashboard</title>
<link rel="stylesheet" href="admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-header">
    <div>
      <h1><?= htmlspecialchars($greeting . ', ' . $adminName) ?> 👋</h1>
      <p class="date-time"><i class="far fa-calendar"></i> <?= date('l, F j, Y') ?> | <i class="far fa-clock"></i> <?= date('g:i A') ?></p>
    </div>
  </div>

  <div class="cards">
    <div class="card">
      <i class="fas fa-users icon"></i>
      <h2><?= number_format($totalUsers) ?></h2>
      <p><i class="fas fa-user-check"></i> Total Users</p>
    </div>
    <div class="card">
      <i class="fas fa-certificate icon"></i>
      <h2><?= number_format($activeSubs) ?></h2>
      <p><i class="fas fa-ticket-alt"></i> Active Subscriptions</p>
    </div>
    <div class="card">
      <i class="fas fa-money-bill-wave icon"></i>
      <h2>Ksh <?= number_format($totalRevenue) ?></h2>
      <p><i class="fas fa-chart-line"></i> Total Revenue</p>
    </div>
    <div class="card">
      <i class="fas fa-box icon"></i>
      <h2><?= number_format($totalPlans) ?></h2>
      <p><i class="fas fa-layer-group"></i> Available Plans</p>
    </div>
  </div>

  <div class="charts-container">
    <div class="chart-card">
      <div class="retention-header">
        <h3><i class="fas fa-chart-pie"></i> Vouchers by Plan</h3>
        <select id="voucherStatusSelect" onchange="window.location.href='?voucher_status=' + this.value + '<?= !empty($_GET['retention_period']) ? '&retention_period=' . htmlspecialchars($_GET['retention_period']) : '' ?><?= !empty($_GET['data_period']) ? '&data_period=' . htmlspecialchars($_GET['data_period']) : '' ?><?= !empty($_GET['user_reg_period']) ? '&user_reg_period=' . htmlspecialchars($_GET['user_reg_period']) : '' ?>';" class="retention-select">
          <option value="all" <?= $voucherStatusFilter === 'all' ? 'selected' : '' ?>>All Vouchers</option>
          <option value="active" <?= $voucherStatusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
          <option value="expired" <?= $voucherStatusFilter === 'expired' ? 'selected' : '' ?>>Expired Only</option>
        </select>
      </div>
      <p class="chart-description">Distribution of vouchers across different plans</p>
      <canvas id="planChart"></canvas>
    </div>

    <div class="chart-card">
      <div class="retention-header">
        <h3><i class="fas fa-user-plus"></i> Customer Retention</h3>
        <select id="retentionPeriodSelect" onchange="changeRetentionPeriod(this.value)" class="retention-select">
          <option value="1week" <?= $retentionPeriod === '1week' ? 'selected' : '' ?>>1 Week</option>
          <option value="14day" <?= $retentionPeriod === '14day' ? 'selected' : '' ?>>14 Days</option>
          <option value="1month" <?= $retentionPeriod === '1month' ? 'selected' : '' ?>>1 Month</option>
          <option value="3month" <?= $retentionPeriod === '3month' ? 'selected' : '' ?>>3 Months</option>
          <option value="6month" <?= $retentionPeriod === '6month' ? 'selected' : '' ?>>6 Months</option>
          <option value="1year" <?= $retentionPeriod === '1year' ? 'selected' : '' ?>>1 Year</option>
        </select>
      </div>
      <p class="chart-description">Track new user registrations over the selected period</p>
      <canvas id="retentionChart"></canvas>
    </div>

    <div class="chart-card">
      <div class="retention-header">
        <h3><i class="fas fa-database"></i> Data Usage</h3>
        <select id="dataPeriodSelect" onchange="window.location.href='?data_period=' + this.value + '<?= !empty($_GET['retention_period']) ? '&retention_period=' . htmlspecialchars($_GET['retention_period']) : '' ?><?= !empty($_GET['user_reg_period']) ? '&user_reg_period=' . htmlspecialchars($_GET['user_reg_period']) : '' ?>'; " class="retention-select">
          <option value="7day" <?= $dataUsagePeriod === '7day' ? 'selected' : '' ?>>Last 7 Days</option>
          <option value="14day" <?= $dataUsagePeriod === '14day' ? 'selected' : '' ?>>Last 14 Days</option>
          <option value="1month" <?= $dataUsagePeriod === '1month' ? 'selected' : '' ?>>Last Month</option>
          <option value="3month" <?= $dataUsagePeriod === '3month' ? 'selected' : '' ?>>Last 3 Months</option>
          <option value="6month" <?= $dataUsagePeriod === '6month' ? 'selected' : '' ?>>Last 6 Months</option>
        </select>
      </div>
      <p class="chart-description">Total data consumption by all users over the selected period</p>
      <canvas id="dataUsageChart"></canvas>
    </div>

    <div class="chart-card">
      <div class="retention-header">
        <h3><i class="fas fa-user-clock"></i> User Registrations</h3>
        <select id="userRegPeriodSelect" onchange="window.location.href='?user_reg_period=' + this.value + '<?= !empty($_GET['retention_period']) ? '&retention_period=' . htmlspecialchars($_GET['retention_period']) : '' ?><?= !empty($_GET['data_period']) ? '&data_period=' . htmlspecialchars($_GET['data_period']) : '' ?>';" class="retention-select">
          <option value="7day" <?= $userRegPeriod === '7day' ? 'selected' : '' ?>>Last 7 Days</option>
          <option value="14day" <?= $userRegPeriod === '14day' ? 'selected' : '' ?>>Last 14 Days</option>
          <option value="1month" <?= $userRegPeriod === '1month' ? 'selected' : '' ?>>Last Month</option>
          <option value="3month" <?= $userRegPeriod === '3month' ? 'selected' : '' ?>>Last 3 Months</option>
          <option value="6month" <?= $userRegPeriod === '6month' ? 'selected' : '' ?>>Last 6 Months</option>
        </select>
      </div>
      <p class="chart-description">Daily count of new user sign-ups over the selected period</p>
      <canvas id="userRegChart"></canvas>
    </div>

  </div>

  <div class="router-dashboard">
    <h2><i class="fas fa-trophy"></i> Package Performance Comparison</h2>
        <p class="chart-description">Detailed analysis of each plan's performance including user count, revenue, and data usage metrics</p>
        <table>
            <thead>
                <tr>
                    <th><i class="fas fa-box"></i> Package</th>
                    <th><i class="fas fa-tag"></i> Price (Ksh)</th>
                    <th><i class="fas fa-users"></i> Users This Month</th>
                    <th><i class="fas fa-coins"></i> Monthly Revenue (Ksh)</th>
                    <th><i class="fas fa-chart-bar"></i> Avg Data Usage (GB)</th>
                    <th><i class="fas fa-calculator"></i> ARPU (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packagePerformance as $pkg): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($pkg['name']) ?></strong></td>
                    <td><?= number_format((float)$pkg['price'],2) ?></td>
                    <td><?= number_format($pkg['total_users']) ?></td>
                    <td><strong><?= number_format($pkg['monthly_revenue'],2) ?></strong></td>
                    <td><?= number_format($pkg['avg_usage_gb'],2) ?></td>
                    <td><?= number_format($pkg['arpu'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="router-dashboard">
        <div class="retention-header">
            <h2><i class="fas fa-signal"></i> Data Usage Per User</h2>
            <div style="display: flex; gap: 10px;">
                <select id="dataStatusSelect" onchange="window.location.href='?data_status=' + this.value + '&data_plan=<?= htmlspecialchars($dataUsagePlanFilter) ?><?= !empty($_GET['voucher_status']) ? '&voucher_status=' . htmlspecialchars($_GET['voucher_status']) : '' ?><?= !empty($_GET['retention_period']) ? '&retention_period=' . htmlspecialchars($_GET['retention_period']) : '' ?><?= !empty($_GET['data_period']) ? '&data_period=' . htmlspecialchars($_GET['data_period']) : '' ?><?= !empty($_GET['user_reg_period']) ? '&user_reg_period=' . htmlspecialchars($_GET['user_reg_period']) : '' ?>';" class="retention-select">
                    <option value="all" <?= $dataUsageStatusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="active" <?= $dataUsageStatusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="expired" <?= $dataUsageStatusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
                <select id="dataPlanSelect" onchange="window.location.href='?data_plan=' + this.value + '&data_status=<?= htmlspecialchars($dataUsageStatusFilter) ?><?= !empty($_GET['voucher_status']) ? '&voucher_status=' . htmlspecialchars($_GET['voucher_status']) : '' ?><?= !empty($_GET['retention_period']) ? '&retention_period=' . htmlspecialchars($_GET['retention_period']) : '' ?><?= !empty($_GET['data_period']) ? '&data_period=' . htmlspecialchars($_GET['data_period']) : '' ?><?= !empty($_GET['user_reg_period']) ? '&user_reg_period=' . htmlspecialchars($_GET['user_reg_period']) : '' ?>';" class="retention-select">
                    <option value="all" <?= $dataUsagePlanFilter === 'all' ? 'selected' : '' ?>>All Plans</option>
                    <?php
                    $plansQuery = $pdo->query("SELECT DISTINCT plan_type FROM vouchers ORDER BY plan_type");
                    while ($planRow = $plansQuery->fetch(PDO::FETCH_ASSOC)) {
                        $selected = $dataUsagePlanFilter === $planRow['plan_type'] ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($planRow['plan_type']) . '" ' . $selected . '>' . htmlspecialchars($planRow['plan_type']) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
        <p class="chart-description">Individual data consumption breakdown with filters</p>
        
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Phone</th>
                    <th>Plan Type</th>
                    <th>Upload (GB)</th>
                    <th>Download (GB)</th>
                    <th>Total Usage (GB)</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($dataPerUser)): ?>
                    <?php foreach ($dataPerUser as $user): ?>
                    <tr onclick="window.location.href='user_detail.php?id=<?= $user['id'] ?>'" style="cursor: pointer;">
                        <td><span class="username-badge"><?= htmlspecialchars($user['username']) ?></span></td>
                        <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($user['plan_type']) ?></td>
                        <td><?= number_format((float)($user['output_gb'] ?? 0), 3) ?> GB</td>
                        <td><?= number_format((float)($user['input_gb'] ?? 0), 3) ?> GB</td>
                        <td><strong><?= number_format((float)($user['total_gb'] ?? 0), 3) ?> GB</strong></td>
                        <td><span class="status-badge <?= $user['status'] === 'active' ? 'status-active' : 'status-expired' ?>"><?= ucfirst($user['status']) ?></span></td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" align="center">No data usage records found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination" data-table="data-usage">
            <?= renderPagination($page_data_usage, $totalPages_data_usage, 'page_data') ?>
        </div>
    </div>
</div>

<script>
    // Function to change retention period
    function changeRetentionPeriod(period) {
        const url = new URL(window.location.href);
        url.searchParams.set('retention_period', period);
        window.location.href = url.toString();
    }

    const activeSubsValue = <?= (int)$activeSubs ?>;
    const dataUsageLabels = <?= json_encode(array_column($dataUsage, 'date')) ?>;
    const dataUsageValues = <?= json_encode(array_column($dataUsage, 'data_usage')) ?>;
    const userRegLabels = <?= json_encode(array_column($userRegs, 'date')) ?>;
    const userRegValues = <?= json_encode(array_column($userRegs, 'count')) ?>;
    const retentionLabels = <?= json_encode(array_column($retention, 'month')) ?>;
    const retentionValues = <?= json_encode(array_column($retention, 'new_users')) ?>;
    const planLabels = <?= json_encode($planLabels) ?>;
    const planValues = <?= json_encode($planValues) ?>;

    const chartColors = { background: 'rgba(40, 167, 69, 0.2)', border: '#006837', text: '#333' };
 
    // Generate unique colors for each plan
    function generatePlanColors(count) {
        const colors = [
            '#006837',  // Green
            '#28a745',  // Light Green
            '#007BFF',  // Blue
            '#6F42C1',  // Purple
            '#FFC107',  // Amber
            '#FF5722',  // Deep Orange
            '#E91E63',  // Pink
            '#00BCD4',  // Cyan
            '#FF9800',  // Orange
            '#795548',  // Brown
            '#9C27B0',  // Deep Purple
            '#4CAF50'   // Medium Green
        ];
        
        // If we have more plans than predefined colors, generate random colors
        while (colors.length < count) {
            const r = Math.floor(Math.random() * 200 + 55);
            const g = Math.floor(Math.random() * 200 + 55);
            const b = Math.floor(Math.random() * 200 + 55);
            colors.push(`rgb(${r}, ${g}, ${b})`);
        }
        
        return colors.slice(0, count);
    }

    const planColors = generatePlanColors(planLabels.length);
    
    // Active Vouchers by Plan Pie Chart
    new Chart(document.getElementById('planChart'), {
        type: 'pie',
        data: {
            labels: planLabels,
            datasets: [{
                data: planValues,
                backgroundColor: planColors,
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: { 
            plugins: { 
                legend: { 
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });


    // Customer Retention Chart
    new Chart(document.getElementById('retentionChart'), {
        type: 'line',
        data: {
            labels: retentionLabels,
            datasets: [{
                label: 'New Users',
                data: retentionValues,
                borderColor: chartColors.border,
                backgroundColor: chartColors.background,
                fill: true,
                tension: 0.3,
                borderWidth: 2
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { color: chartColors.text } }, y: { ticks: { color: chartColors.text, beginAtZero: true } } } }
    });

    // Data Usage Chart
    new Chart(document.getElementById('dataUsageChart'), {
        type: 'line',
        data: {
            labels: dataUsageLabels,
            datasets: [{
                label: 'Data Used (MB)',
                data: dataUsageValues,
                backgroundColor: chartColors.background,
                borderColor: chartColors.border,
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { color: chartColors.text } }, y: { ticks: { color: chartColors.text, beginAtZero: true } } } }
    });

    // User Registrations Chart
    new Chart(document.getElementById('userRegChart'), {
        type: 'bar',
        data: {
            labels: userRegLabels,
            datasets: [{
                label: 'New Users',
                data: userRegValues,
                backgroundColor: chartColors.background,
                borderColor: chartColors.border,
                borderWidth: 1
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { color: chartColors.text } }, y: { ticks: { color: chartColors.text, beginAtZero: true } } } }
    });

    // Auto-refresh at midnight
    let currentDate = new Date().toDateString();
    
    setInterval(function() {
        const now = new Date();
        const newDate = now.toDateString();
        
        // Check if date has changed (crossed midnight)
        if (newDate !== currentDate) {
            currentDate = newDate;
            console.log('Midnight crossed - refreshing dashboard data...');
            location.reload();
        }
    }, 60000); 

    // Also check every second around midnight (23:59:00 - 00:01:00)
    setInterval(function() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        
        // More frequent checking around midnight
        if ((hours === 23 && minutes >= 59) || (hours === 0 && minutes <= 1)) {
            const newDate = now.toDateString();
            if (newDate !== currentDate) {
                currentDate = newDate;
                console.log('Midnight crossed - refreshing dashboard data...');
                location.reload();
            }
        }
    }, 1000); // Check every second around midnight
</script>

</body>
</html>

