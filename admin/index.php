<?php
ini_set('display_errors', 1);
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

    // Data usage (last 7 days)
    $dataUsage = $pdo->query("
        SELECT 
            DATE(acctstarttime) AS date,
            ROUND(SUM(acctinputoctets + acctoutputoctets)/(1024*1024),2) AS data_usage
        FROM radacct
        WHERE acctstarttime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(acctstarttime)
        ORDER BY DATE(acctstarttime)
    ")->fetchAll(PDO::FETCH_ASSOC);
 
    // Active vouchers by plan
    $activeByPlan = $pdo->query("
        SELECT plan_type, COUNT(*) AS total
        FROM vouchers
        WHERE status = 'active'
        GROUP BY plan_type
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $planLabels = array_column($activeByPlan, 'plan_type');
    $planValues = array_column($activeByPlan, 'total');

    // Customer retention (6 months)
    $retention = $pdo->query("
        SELECT DATE_FORMAT(MIN(created_at), '%b %Y') AS month, COUNT(*) AS new_users
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(MIN(created_at)), MONTH(MIN(created_at))
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Daily registrations (last 7 days)
    $userRegs = $pdo->query("
        SELECT DATE(created_at) AS date, COUNT(*) AS count
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
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

    

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leo Konnect | Admin Dashboard</title>
<link rel="stylesheet" href="admin_style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  .perf-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.perf-table th, .perf-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #ccc;
    text-align: left;
}

.perf-table th {
    background: #006837;
    color: #fff;
    font-weight: bold;
}

.perf-table tr:hover {
    background: #f2f2f2;
}

</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <!-- <h1>Welcome to Leo Konnect Admin Dashboard</h1> -->
    <h1 style="text-align: left;"><?= htmlspecialchars($greeting . ' ' . $adminName) ?></h1>

  <div class="cards">
    <div class="card">
      <h2><?= $totalUsers ?></h2>
      <p>Total Users</p>
    </div>
    <div class="card">
      <h2><?= $activeSubs ?></h2>
      <p>Active Subscriptions</p>
    </div>
    <div class="card">
      <h2>Ksh <?= number_format($totalRevenue) ?></h2>
      <p>Total Revenue</p>
    </div>
    <div class="card">
      <h2><?= $totalPlans ?></h2>
      <p>Available Plans</p>
    </div>
  </div>

  <div class="charts-container">
    <div class="chart-card">
      <h3>Active Vouchers by Plan</h3>
      <canvas id="planChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>Customer Retention (6 Months)</h3>
      <canvas id="retentionChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>Data Usage (Last 7 Days)</h3>
      <canvas id="dataUsageChart"></canvas>
    </div>

    <div class="chart-card">
      <h3>User Registrations (Last 7 Days)</h3>
      <canvas id="userRegChart"></canvas>
    </div>

  </div>

  <h2 style="margin-top:40px;">Package Performance Comparison</h2>
    <table>
        <thead>
            <tr>
                <th>Package</th>
                <th>Price (Ksh)</th>
                <th>Users This Month</th>
                <th>Monthly Revenue (Ksh)</th>
                <th>Avg Data Usage (GB)</th>
                <th>ARPU (Ksh)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packagePerformance as $pkg): ?>
            <tr>
                <td><?= htmlspecialchars($pkg['name']) ?></td>
                <td><?= number_format((float)$pkg['price'],2) ?></td>
                <td><?= $pkg['total_users'] ?></td>
                <td><?= number_format($pkg['monthly_revenue'],2) ?></td>
                <td><?= number_format($pkg['avg_usage_gb'],2) ?></td>
                <td><?= number_format($pkg['arpu'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
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
 
    // Active Vouchers by Plan Pie Chart
    const planColors = ['#006837', '#B0B0B0', '#007BFF', '#6F42C1'];
    new Chart(document.getElementById('planChart'), {
        type: 'pie',
        data: {
            labels: planLabels,
            datasets: [{
                data: planValues,
                backgroundColor: planColors,
                borderWidth: 1
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
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
</script>

</body>
</html>

