<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('routeros_api.class.php');

$API = new RouterosAPI();
$API->debug = false;

$host = '192.168.10.67';
$user = 'admin';
$pass = 'Dvdjesse1998???';

if ($API->connect($host, $user, $pass)) {
    echo "Connected successfully!<br><br>";

    $API->write('/ppp/active/print');
    $data = $API->read();
    print_r($data);

    $API->disconnect();
} else {
    echo "Failed to connect to RouterOS API";
}
?>
