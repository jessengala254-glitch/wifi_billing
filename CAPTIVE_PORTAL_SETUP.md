# Leo Konnect - Captive Portal Setup Guide

## MikroTik Configuration for Captive Portal

To redirect users to the Leo Konnect payment page when they connect to your WiFi, follow these steps:

### 1. Access MikroTik Web Interface
- Go to your MikroTik router IP (usually http://192.168.88.1)
- Login with your credentials

### 2. Configure Hotspot

#### Navigate to:
**IP → Hotspot → Hotspot Profiles**

#### Edit the default profile (or create a new one):
- **Name:** leo-konnect (or any name)
- **Login By:** Voucher
- **HTML Directory:** /flash/hotspot (default)
- **Redirect URL:** `http://192.168.10.68/leokonnect/index.php?return_url=$(link-redirect)&from_captive=1` 
  - Replace `192.168.10.68` with your server IP

#### Important Settings:
- **Use RADIUS:** Yes (to authenticate against FreeRADIUS)
- **RADIUS Server:** Your FreeRADIUS server IP
- **RADIUS Secret:** Your RADIUS secret
- **Split tunneling:** Off (to force redirect)

### 3. Configure Interface for Hotspot

**IP → Hotspot → Hotspot**

#### Create/Edit Hotspot on LAN interface:
- **Interface:** Choose your WiFi interface (ether1, wlan0, etc.)
- **Address Pool:** Your IP range (e.g., 192.168.100.0/24)
- **Profile:** leo-konnect (the one you just created)

### 4. Create Hotspot User (for testing)

**IP → Hotspot → Users**
- You can add test users, but best practice is to use vouchers

### 5. Configure DNS Redirect (Important!)

To ensure ALL HTTP requests get redirected to captive portal:

**IP → Firewall → NAT**

Create a rule:
- **Chain:** dstnat
- **Protocol:** TCP
- **Dst. Port:** 80
- **In. Interface:** Your LAN (where hotspot is)
- **Action:** redirect
- **To Ports:** 8080

Then create another rule for port 8080:
- **Chain:** dstnat
- **Protocol:** TCP
- **Dst. Port:** 8080
- **In. Interface:** Your LAN
- **Src. Address:** !192.168.88.0/24 (your management interface)
- **Action:** redirect
- **To Ports:** 80
- **To Address:** Your Leo Konnect server IP (192.168.10.68)

### 6. Create Firewall Rule to Allow HTTPS

**IP → Firewall → Filter Rules**

Add rule to allow HTTPS (optional, for payment page):
- **Chain:** forward
- **Protocol:** TCP
- **Dst. Port:** 443
- **Action:** accept

---

## Flow After Setup

1. **User connects to WiFi** → Gets assigned IP from hotspot pool
2. **User opens browser** → Any HTTP request is redirected to `captive_portal.php`
3. **User sees plan selection** → Displays available WiFi plans
4. **User chooses plan** → Redirected to `purchase.php?plan_id=X&from_captive=1`
5. **User pays via M-Pesa** → Voucher credentials generated & SMS sent
6. **User gets redirected** → Back to original return_url or Google
7. **User logs in with voucher** → Uses username/password from SMS

---

## Testing the Captive Portal

### Without MikroTik (for development):
```bash
# Test the captive portal directly (with parameters)
curl "http://192.168.10.68/leokonnect/index.php?from_captive=1&return_url=http://www.google.com"

# Test payment flow
curl -X POST http://192.168.10.68/leokonnect/api/payments.php \
  -d "phone=0791234567&plan_id=1"
```

### With MikroTik:
1. Connect a device to the WiFi network
2. Open a browser (no login needed)
3. Try to visit any website (e.g., google.com)
4. Should be automatically redirected to captive portal
5. Select a plan and complete payment

---

## Troubleshooting

### Captive portal not loading?
- Check MikroTik's Redirect URL is correct
- Verify firewall NAT rules are set up
- Check DNS redirect rules

### Payment page not loading?
- Verify Leo Konnect server is reachable from MikroTik
- Check firewall rules allow traffic to HTTPS
- Look at Apache logs: `tail -f /var/log/apache2/error.log`

### Vouchers not working after payment?
- Check RADIUS server is running: `sudo systemctl status freeradius`
- Verify voucher was created: `SELECT * FROM vouchers WHERE phone='07xxx'`
- Check radcheck records exist

---

## Next Steps

1. Configure real IntaSend credentials in `inc/config.php`
2. Set up SSL/TLS certificate for HTTPS
3. Configure backup RADIUS servers for redundancy
4. Set up monitoring for payment processing
5. Create admin dashboard to view statistics

