<?php
// inc/config.php
$config = [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'radius',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4'
    ],
    'mail' => [
        'from_email' => 'local@example.com',
        'from_name' => 'Local Dev'
    ],
    'mpesa' => [
        'consumer_key' => 'YOUR_CONSUMER_KEY',
        'consumer_secret' => 'YOUR_CONSUMER_SECRET',
        'shortcode' => '174379',
        'passkey' => 'YOUR_PASSKEY',
        'environment' => 'sandbox',
        'callback_url' => 'https://yourdomain.com/public/mpesa_callback.php'
    ],
    'app' => [
        'base_url' => 'https://yourdomain.com/public',
        'jwt_secret' => 'change_this_secret_for_sessions'
    ],
    'sms' => [
        'provider' => 'none',
        'api_key' => '',
        'username' => ''
    ],
    'mikrotik' => [
        'host' => '192.168.10.67',
        'user' => 'admin',
        'pass' => 'Dvdjesse1998???',
        'port' => 8728,
        'timeout' => 5
    ]
];

return $config;
