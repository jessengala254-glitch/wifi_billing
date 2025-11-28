<?php

require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

$daysToKeep = 7;
$stmt = $pdo->prepare("DELETE FROM sessions WHERE active = 0 AND expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
$stmt->execute([$daysToKeep]);

$count = $stmt->rowCount();
echo "Cleaned up $count old sessions at " . date('Y-m-d H:i:s');
