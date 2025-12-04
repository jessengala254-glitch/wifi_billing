# MikroTik Quick Setup - Redirect to Leo Konnect

## Step 1: Access MikroTik

Via **SSH**:
```bash
ssh admin@192.168.88.1
# Default password: (blank or admin)
```

Or use **Web Interface**:
- Go to: http://192.168.88.1
- Login with admin credentials

---

## Step 2: Update redirect.html

The easiest way is to modify the `redirect.html` file on MikroTik to point to your Leo Konnect server.

### Option A: Via SSH (Recommended)

```bash
# SSH into MikroTik
ssh admin@192.168.88.1

# Edit the redirect.html file
/system file edit redirect.html

# Replace the content with:
```

**New redirect.html content:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0;url=http://192.168.10.68/leokonnect/index.php?return_url=$(link-redirect)&from_captive=1">
</head>
<body>
    <p>Redirecting to Leo Konnect...</p>
</body>
</html>
```

**Important**: Replace `192.168.10.68` with your actual server IP address.

### Option B: Via Web Interface

1. Go to **Files** in the left menu
2. Find `redirect.html`
3. Click on it and select **Edit**
4. Replace the content with the HTML above
5. Save

---

## Step 3: Configure Hotspot Profile

In MikroTik Web Interface:

1. Go to **IP → Hotspot → Hotspot Profiles**
2. Edit the **default** profile (or your custom profile)
3. Set these values:
   - **Name**: default
   - **Login By**: Voucher
   - **HTML Directory**: /flash/hotspot
   - **Redirect URL**: Leave blank (we'll use redirect.html instead)

4. Scroll down to **RADIUS** section:
   - **Use RADIUS**: Yes
   - **RADIUS Server**: 192.168.10.68 (your FreeRADIUS server IP)
   - **RADIUS Secret**: your_radius_secret
   - **Accounting**: Yes

---

## Step 4: Test the Captive Portal

1. Connect your phone/device to the WiFi hotspot
2. Open a browser
3. Try to visit any website (e.g., google.com)
4. You should be redirected to: `http://192.168.10.68/leokonnect/index.php?from_captive=1`
5. Select a plan and complete payment
6. After payment, you'll get voucher credentials
7. Login with those credentials to access the internet

---

## Troubleshooting

### Users not being redirected

**Check if hotspot is enabled:**
```bash
/ip hotspot print
```

**Check hotspot status:**
```bash
/ip hotspot active print
```

### redirect.html not working

Try using `alogin.html` instead:
```html
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0;url=http://192.168.10.68/leokonnect/index.php?return_url=$(link-redirect)&from_captive=1">
</head>
<body>
    <p>Redirecting to Leo Konnect...</p>
</body>
</html>
```

Edit `/flash/hotspot/alogin.html` and replace content with the above.

### Can't access Leo Konnect server from MikroTik network

Make sure:
- MikroTik can ping your server: `ping 192.168.10.68`
- Firewall rules allow traffic to port 80
- DNS is working (try `nslookup` commands)

---

## Complete Working Setup Checklist

- [ ] SSH'd into MikroTik
- [ ] Updated `redirect.html` (or `alogin.html`) with new URL
- [ ] Configured hotspot profile with RADIUS settings
- [ ] Tested connection and redirect
- [ ] Successfully purchased plan and received voucher
- [ ] Logged in with voucher credentials

---

## Server IP Reference

Your Leo Konnect server is running on: **192.168.10.68**

If this is wrong, update the URLs in `redirect.html` and MikroTik hotspot settings accordingly.
