<?php
require_once "inc/functions.php";
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

// Select expired vouchers
$stmt = $pdo->query("SELECT username FROM vouchers WHERE expiry < NOW()");
$expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($expired as $e) {
    $u = $e['username'];

    // Remove from radcheck so they cannot login to hotspot
    $pdo->prepare("DELETE FROM radcheck WHERE username=?")->execute([$u]);

    // Mark voucher as expired
    $pdo->prepare("UPDATE vouchers SET status='expired' WHERE username=?")
        ->execute([$u]);
}
