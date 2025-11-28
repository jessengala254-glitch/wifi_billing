<?php
require_once __DIR__ . '/../inc/functions.php';
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = db();
    $pdo->exec("SET time_zone = '+03:00'");

// Revenue per Plan
$stmt = $pdo->query("
    SELECT p.title AS plan_name,
           COUNT(pay.id) AS total_purchases,
           SUM(pay.amount) AS total_revenue
    FROM plans p
    LEFT JOIN payments pay ON pay.plan_id = p.id AND pay.status='success'
    GROUP BY p.id
    ORDER BY total_revenue DESC
");
$revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subscription Status
$stmt = $pdo->query("
    SELECT status, COUNT(*) AS count
    FROM users
    GROUP BY status
");
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Subscribers & Revenue
$stmt = $pdo->query("
    SELECT DATE_FORMAT(transaction_time, '%Y-%m') AS month,
           COUNT(DISTINCT user_id) AS total_subscribers,
           SUM(amount) AS revenue
    FROM payments
    WHERE status='success'
    GROUP BY month
    ORDER BY month ASC
");
$monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'revenueData' => $revenueData,
    'statusData' => $statusData,
    'monthlyData' => $monthlyData
]);


} catch (Exception $e) {
    echo json_encode([
        'revenueData' => [],
        'statusData' => [],
        'monthlyData' => [],
        'error' => $e->getMessage()
    ]);
}


