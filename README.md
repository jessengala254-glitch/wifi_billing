# Leo Konnect - Internet Billing System (starter)

## Overview
This is a starter Internet Billing System for Leo Konnect (Kenya). Users can register, buy WiFi access plans (M-Pesa), and get automatic access for the purchased duration. Admin can manage plans and users, and export reports.

## Requirements
- PHP 8+, PDO extension
- MySQL 5.7+
- Composer (for PHPMailer, dompdf)
- cPanel or Linux hosting with cron
- M-Pesa Daraja credentials (consumer key & secret)
- TLS/HTTPS

## Installation
1. Import `db.sql` into MySQL.
2. Copy `inc/config.php.example` to `inc/config.php` and fill DB & API keys.
3. Run `composer install` in project root to fill `vendor/`.
4. Configure webroot to `public/`.
5. Set a cron job: `* * * * * php /path/to/leokonnect/cron/expire_sessions.php`
6. Configure your router/hotspot to accept a user’s `ip`/`mac` and call this system when granting internet access.

## Notes
- Replace example secrets.
- Sandbox test with M-Pesa before production.
