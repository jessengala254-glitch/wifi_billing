<?php
require_once 'routeros_api.class.php'; 

$router = [
    'name' => 'MikroTik8',
    'host' => '192.168.10.67',
    'user' => 'admin',  
    'pass' => 'Dvdjesse1998???',  
    'winbox' => 'winbox://192.168.88.1:8291'
];

$api = new RouterosAPI();
$api->debug = false;

$info = [
    'name' => $router['name'],
    'winbox' => $router['winbox'],
    'cpu' => 0,
    'memory' => 0,
    'status' => 'Offline',
    'provisioning' => 'Unknown'
];

if ($api->connect($router['host'], $router['user'], $router['pass'])) {
    $resource = $api->comm("/system/resource/print")[0] ?? [];
    $cpu = $resource['cpu-load'] ?? 0;
    $memUsed = $resource['used-memory'] ?? 0;

    $info['cpu'] = (int)$cpu;
    $info['memory'] = round($memUsed / 1024 / 1024, 2); // MB
    $info['status'] = 'Online';
    $info['provisioning'] = 'Completed';

    $api->disconnect();
}

header('Content-Type: application/json');
echo json_encode([$info]);
