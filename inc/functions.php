<?php
// inc/functions.php
$config = require __DIR__ . '/config.php';

date_default_timezone_set("Africa/Nairobi");


/**
 * ----------------------------------------
 * 1️⃣ Database Connection
 * ----------------------------------------
 */
function db() {
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $db = $config['db'];
        $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

/**
 * ----------------------------------------
 * 2️⃣ Verify User Session
 * ----------------------------------------
 */
function user_verify_session() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_id'] ?? false;
}

/**
 * ----------------------------------------
 * 3️⃣ Password Helpers
 * ----------------------------------------
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * ----------------------------------------
 * 4️⃣ Email Sender
 * ----------------------------------------
 */
// function send_email($to, $subject, $body) {
//     global $config;
//     $mail = new PHPMailer\PHPMailer\PHPMailer(true);

//     try {
//         $mail->isSMTP();
//         $mail->Host = $config['mail']['host'];
//         $mail->SMTPAuth = true;
//         $mail->Username = $config['mail']['username'];
//         $mail->Password = $config['mail']['password'];
//         $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
//         $mail->Port = $config['mail']['port'];

//         $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
//         $mail->addAddress($to);
//         $mail->isHTML(true);
//         $mail->Subject = $subject;
//         $mail->Body = $body;
//         $mail->send();

//     } catch (Exception $e) {
//         error_log('Mail error: ' . $mail->ErrorInfo);
//     }
// }

/**
 * ----------------------------------------
 * 5️⃣ SMS Placeholder
 * ----------------------------------------
 */
function send_sms($phone, $message) {
    global $config;
    
    $sms_config = $config['sms'] ?? [];
    $api_key = $sms_config['api_key'] ?? null;
    $api_url = $sms_config['api_url'] ?? 'https://api.simflix.co.ke/send'; // Adjust if needed
    $sender_id = $sms_config['sender_id'] ?? 'LEOKONNECT';
    
    if (!$api_key) {
        error_log("SMS ERROR: Missing SimFlix API key");
        return false;
    }

    // Format phone number (remove leading 0, add 254)
    if (strpos($phone, '0') === 0) {
        $phone = '254' . substr($phone, 1);
    }

    $data = [
        'phone' => $phone,
        'message' => $message,
        'sender_id' => $sender_id
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        error_log("SMS sent to $phone via SimFlix: SUCCESS");
        return true;
    } else {
        error_log("SMS ERROR to $phone: HTTP $http_code - $response");
        return false;
    }
}

/**
 * ----------------------------------------
 * 6️⃣ Logging Helper
 * ----------------------------------------
 */
function log_action($pdo, $user_id, $action, $description, $type = 'info', $message = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();

    $stmt = $pdo->prepare("
        INSERT INTO logs (user_id, session_id, action, description, ip_address, type, message, created_at)
        VALUES (:user_id, :session_id, :action, :description, :ip_address, :type, :message, NOW())
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':session_id' => $session_id,
        ':action' => $action,
        ':description' => $description,
        ':ip_address' => $ip_address,
        ':type' => $type,
        ':message' => $message
    ]);
}

?>
