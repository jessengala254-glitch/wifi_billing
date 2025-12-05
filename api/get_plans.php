<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once('../inc/functions.php');

try {
    $pdo = db();
    $plans = $pdo->query("SELECT * FROM plans WHERE active=1 ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'plans' => $plans
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch plans'
    ]);
}
