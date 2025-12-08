<?php
// inc/config.php
$config = [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'radius',
        'user' => 'leokonnect_app',
        'pass' => 'LeoKonnect1998???',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'base_url' => 'https://yourdomain.com/public',
        'jwt_secret' => 'Dvdjesse1998...'
    ],
    'mpesa' => [
        'consumer_key' => 'YOUR_CONSUMER_KEY',
        'consumer_secret' => 'YOUR_CONSUMER_SECRET',
        'shortcode' => '174379',
        'passkey' => 'YOUR_PASSKEY',
        'environment' => 'sandbox',
        'callback_url' => 'https://yourdomain.com/public/mpesa_callback.php'
    ],
    'intasend' => [
        'secret_key'   => 'ISSecretKey_live_8a79adee-83bc-419f-a139-587c80baf930',
        'callback_url' => 'http://192.168.10.68/leokonnect/api/intasend_callback.php'
    ],
    'sms' => [
        'provider' => 'simflix',
        'api_key' => 'GrEFbI6JZgsNckzCj9O3DmXqaRTH405WfU1doPtShxAQLvKpy2u8elVM7BinYw',
        'api_url' => 'https://smsapp.simflix.co.ke/sms/v3/sendmultiple',
        'sender_id' => 'LEOKONNECT Ltd',
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
