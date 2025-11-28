<?php
require_once __DIR__ . '/config.php'; // PDO connection
require_once __DIR__ . '/radius_api.php';


$pdo = db();

// 1. Create a test voucher (expires in 2 minutes)
$voucher = create_voucher($pdo, 2); // 2-minute expiry
echo "Created Voucher:\n";
print_r($voucher);

// 2. Simulate Authentication function
function test_auth($pdo, $username, $password, $mac = null, $framed_ip = null) {
    // Check expiry, MAC, IP
    $stmt = $pdo->prepare("SELECT expiry_date, mac_address, framed_ip FROM vouchers WHERE username=? AND status='active'");
    $stmt->execute([$username]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) return 'Voucher not found';
    if (strtotime($record['expiry_date']) < time()) return 'Voucher expired';
    if ($record['mac_address'] && strtolower($record['mac_address']) !== strtolower($mac)) return 'MAC mismatch';
    if ($record['framed_ip'] && $record['framed_ip'] !== $framed_ip) return 'IP mismatch';

    return 'Authentication successful';
}

// 3. Test login immediately
echo "\nTesting login immediately:\n";
echo test_auth($pdo, $voucher['username'], $voucher['password'], $voucher['mac'], $voucher['framed_ip']);

// 4. Test login after expiry
sleep(130); // wait 2 minutes + 10 seconds
echo "\nTesting login after expiry:\n";
echo test_auth($pdo, $voucher['username'], $voucher['password'], $voucher['mac'], $voucher['framed_ip']);

// 5. Test MAC/IP mismatch
$wrong_mac = 'AA:BB:CC:DD:EE:00';
$wrong_ip = '192.168.10.99';
echo "\nTesting MAC/IP mismatch:\n";
echo test_auth($pdo, $voucher['username'], $voucher['password'], $wrong_mac, $voucher['framed_ip']) . "\n";
echo test_auth($pdo, $voucher['username'], $voucher['password'], $voucher['mac'], $wrong_ip) . "\n";
?>
