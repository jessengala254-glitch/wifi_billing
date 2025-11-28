<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

$config = require __DIR__ . '/../inc/config.php';
$pdo = db($config['db']);

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ip         = $_POST['ip'];
    $shortname  = $_POST['shortname'];
    $type       = $_POST['type'];
    $secret     = $_POST['secret'];
    $desc       = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$ip, $shortname, $type, $secret, $desc]);

    // Redirect back to router list
    header("Location: mikrotik_devices.php?added=1");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Router</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* .form-box {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-box h2 {
            margin-bottom: 15px;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            padding: 10px 20px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor:pointer;
        } */
    </style>
</head>

<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <div class="outer-board">
        <h2>Add New MikroTik Router</h2>

        <form method="POST">
            <label>Router IP Address</label>
            <input type="text" name="ip" placeholder="e.g. 192.168.10.1" required>

            <label>Router Name</label>
            <input type="text" name="shortname" placeholder="Router Name" required>

            <label>Provisioning Type</label>
            <select name="type" required>
                <option value="pppoe">PPPoE</option>
                <option value="hotspot">Hotspot</option>
                <option value="static">Static</option>
            </select>

            <label>API Secret / Password</label>
            <input type="text" name="secret" placeholder="Router Secret" required>

            <label>Description</label>
            <textarea name="description" placeholder="Write something..."></textarea>

            <button type="submit">Save Router</button>
        </form>
    </div>
    
</div>

</body>
</html>
