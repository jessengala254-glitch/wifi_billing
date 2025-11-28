<?php
// inc/mpesa.php
$config = require __DIR__ . '/config.php';

function mpesa_get_base() {
    global $config;
    return $config['mpesa']['environment'] === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_get_oauth_token() {
    global $config;
    $base = mpesa_get_base();
    $url = $base . '/oauth/v1/generate?grant_type=client_credentials';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, $config['mpesa']['consumer_key'] . ':' . $config['mpesa']['consumer_secret']);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new Exception('MPESA OAuth error: ' . curl_error($ch));
    }
    $data = json_decode($res, true);
    return $data['access_token'] ?? null;
}

function mpesa_stk_push($phone, $amount, $account_reference, $transaction_desc, $callback_url) {
    global $config;
    $token = mpesa_get_oauth_token();
    $base = mpesa_get_base();
    $url = $base . '/mpesa/stkpush/v1/processrequest';
    $timestamp = date('YmdHis');
    $passkey = $config['mpesa']['passkey'];
    $shortcode = $config['mpesa']['shortcode'];
    $password = base64_encode($shortcode.$passkey.$timestamp);

    $payload = [
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => intval($amount),
        "PartyA" => format_phone($phone),
        "PartyB" => $shortcode,
        "PhoneNumber" => format_phone($phone),
        "CallBackURL" => $callback_url,
        "AccountReference" => $account_reference,
        "TransactionDesc" => $transaction_desc
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:application/json","Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res = curl_exec($ch);
    if ($res === false) {
        throw new Exception("STK Push error: " . curl_error($ch));
    }
    return json_decode($res, true);
}

function format_phone($ph) {
    // normalize to 2547xxxxxxxx format
    $ph = preg_replace('/\D+/', '', $ph);
    if (strlen($ph) === 10 && substr($ph,0,1)=='0') {
        return '254' . substr($ph,1);
    } elseif (strlen($ph) === 9) {
        return '254' . $ph;
    } elseif (substr($ph,0,3) == '254') {
        return $ph;
    }
    return $ph;
}
