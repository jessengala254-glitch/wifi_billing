# SmartPayPesa Integration Setup Guide

## Overview
This system now uses SmartPayPesa for M-Pesa STK Push payments (instead of IntaSend).

## Setup Steps

### 1. Get SmartPayPesa Credentials
1. Visit https://smartpaypesa.com/
2. Create an account or login
3. Complete business verification (usually faster than IntaSend)
4. Navigate to API Settings/Credentials
5. Copy your:
   - API Key
   - API Secret
   - Service Code (Business Shortcode)

### 2. Configure Your System
Edit `/var/www/html/leokonnect/api/payments.php` and update these lines (around line 45):

```php
$smartpay_api_key = "YOUR_SMARTPAYPESA_API_KEY";
$smartpay_secret = "YOUR_SMARTPAYPESA_SECRET";
$smartpay_service_code = "YOUR_SERVICE_CODE";
```

### 3. Set Callback URL in SmartPayPesa Dashboard
Configure your callback URL in SmartPayPesa dashboard:
```
https://YOUR_DOMAIN/leokonnect/api/smartpay_callback.php
```

Or for local testing:
```
https://192.168.10.68/leokonnect/api/smartpay_callback.php
```

**Note:** For local testing, you may need to use ngrok or expose your local server.

### 4. Test the Integration

#### Using Mock Payment (for testing without API credentials):
The system automatically falls back to mock payment if SmartPayPesa is unavailable.

#### Using Real SmartPayPesa:
1. Add your credentials to `payments.php`
2. Make a test purchase
3. You'll receive an STK push on your phone
4. Enter your M-Pesa PIN
5. Check logs at `/var/www/html/leokonnect/logs/smartpay_callback.txt`

## How It Works

### Payment Flow:
1. User selects a plan and enters phone number
2. System calls SmartPayPesa API to initiate STK push
3. User receives M-Pesa prompt on their phone
4. User enters PIN to complete payment
5. SmartPayPesa sends callback to your server
6. System creates RADIUS voucher and shows credentials

### Key Differences from IntaSend:
- **No redirect**: STK push is sent directly to phone (no popup window)
- **Faster**: Users don't leave your site
- **Better UX**: Enter PIN on phone, credentials appear automatically
- **Simpler**: No complex invoice URLs or redirects

## Troubleshooting

### No STK Push Received:
- Verify phone number format (should be 07xxxxxxxx or 2547xxxxxxxx)
- Check SmartPayPesa dashboard for errors
- Ensure API credentials are correct
- Check if shortcode is active

### Payment Shows Pending:
- Check callback logs: `/var/www/html/leokonnect/logs/smartpay_callback.txt`
- Verify callback URL is set correctly in SmartPayPesa dashboard
- For local testing, ensure your server is accessible from internet (use ngrok)

### Mock Payment Testing:
If SmartPayPesa is not configured or fails, the system automatically uses mock payment for testing.

## Security Notes
- Never commit API keys to Git
- Store credentials in environment variables for production
- Use HTTPS for callback URLs
- Validate callback signatures (implement if SmartPayPesa provides signature verification)

## Support
- SmartPayPesa Docs: https://docs.smartpaypesa.com/
- SmartPayPesa Support: support@smartpaypesa.com
