<?php
//login.php
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 1);
    session_start();
    require_once('inc/functions.php');

    $error = isset($_GET['error']) && $_GET['error'] == 1;
    $invalid = isset($_GET['invalid']) && $_GET['invalid'] == 1;
    $expired = isset($_GET['expired']) && $_GET['expired'] == 1;
    $logged_out = isset($_GET['message']) && $_GET['message'] == 'logged_out';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login | Leo Konnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
</head>
    
<body>
    
<header class="navbar">
  <div class="container nav-flex">
    <a href="index.php" class="logo">
      <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
    </a>
    <nav>
      <a href="plans.php">Plans</a>
      <a href="#">About</a>
      <a href="#">Contact</a>
    </nav>
    <div class="nav-btns">
      <a href="login.php" class="signin">Sign In</a>
    </div>
  </div>
</header>
    
<section class="auth-page">

  <?php if ($error): ?>
    <div id="notification" class="notification error">⚠️ Incorrect username or password.</div>

  <?php elseif ($invalid): ?>
    <div id="notification" class="notification error">❌ Voucher not found.</div>

  <?php elseif ($expired): ?>
    <div id="notification" class="notification error">⌛ This voucher has expired.</div>

  <?php elseif ($logged_out): ?>
    <div id="notification" class="notification success">✅ Logged out successfully.</div>
  <?php endif; ?>

  <div class="auth-container">
    <a href="index.php" class="logo">
      <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
    </a>

    <h2>Voucher Login</h2>
    <p class="sub-text">Enter your voucher username and password to connect.</p>

    <form method="POST" action="/leokonnect/api/auth.php">
      <input type="hidden" name="action" value="login">

      <label>Username</label>
      <input type="text" name="username" placeholder="Enter voucher username" required>

      <label>Password</label>
      <input type="password" name="password" placeholder="Enter voucher password" required>

      <button type="submit">Login</button>
    </form>

    <p class="info-note">
      Buy a voucher from the <a href="plans.php">Plans Page</a>.
    </p>
  </div>
</section>
    
<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-column brand"> 
      <h2 class="footer-logo">
        <i class="fas fa-wifi"></i> Leo <span>Konnect</span></h2>
      <p>Making internet accessible and affordable for everyone in Kenya.</p>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-x-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
      </div>
    </div>
	
    <div class="footer-column">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="plans.php">View Plans</a></li>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="login.php">Sign In</a></li>
      </ul>
    </div>

    <div class="footer-column">
      <h3>Support</h3>
      <ul>
        <li><a href="#">FAQ</a></li>
        <li><a href="#">Help Center</a></li>
        <li><a href="#">Terms of Service</a></li>
        <li><a href="#">Privacy Policy</a></li>
      </ul>
    </div>

    <div class="footer-column contact">
      <h3>Contact Us</h3>
      <ul>
        <li><i class="fas fa-map-marker-alt"></i> Malindi, Kenya</li>
        <li><i class="fas fa-phone"></i> +254 700 000 000</li>
        <li><i class="fas fa-envelope"></i> info@leokonnect.co.ke</li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Leo Konnect. All rights reserved.</p>
  </div>
</footer>
    
<script>
  const notif = document.getElementById('notification');
  if (notif) {
    notif.style.opacity = '1';
    setTimeout(() => notif.style.opacity = '0', 5000);
    setTimeout(() => notif.remove(), 5500);
  }
</script>s

</body>
</html>

