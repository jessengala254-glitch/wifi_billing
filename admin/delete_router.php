<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../inc/functions.php';

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid router ID");

$stmt = $pdo->prepare("DELETE FROM nas WHERE id = ?");
$stmt->execute([$id]);

header("Location: mikrotik_devices.php?deleted=1");
exit;

