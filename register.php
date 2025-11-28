<?php
require_once('inc/functions.php');
$success = isset($_GET['success']) && $_GET['success'] == 1;
$error = isset($_GET['error']) && $_GET['error'] == 1;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register | Leo Konnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
    <style>


    </style>
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
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>
    
<section class="auth-page">
  <?php if ($success): ?>
    <div id="notification" class="notification success">✅ Account created successfully!</div>
  <?php elseif ($error): ?>
    <div id="notification" class="notification error">⚠️ Something went wrong. Try again.</div>
  <?php endif; ?>

  <div class="auth-container">
     
     <a href="index.php" class="logo">
         <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
     </a>
    <!--<img src="assets/images/logo.png" alt="Leo Konnect Logo" class="logo">-->
    <h2>Create Your Account</h2>
    <form id="registerForm" method="POST" action="../api/auth.php">
      <input type="hidden" name="action" value="register">

      <label for="name">Full Name</label>
      <input id="name" name="name" placeholder="Enter your full name" required>

      <label for="email">Email Address</label>
      <input id="email" name="email" type="email" placeholder="example@email.com" required>

      <label for="phone">Phone Number</label>
      <input id="phone" name="phone" type="tel" placeholder="+254 700 000 000" required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" placeholder="••••••••" required>

      <button type="submit">Register Account</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
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
        <li><a href="#">View Plans</a></li>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="#">Sign In</a></li>
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
      setTimeout(() => notif.classList.add('show'), 100); // fade-in
      setTimeout(() => notif.classList.remove('show'), 5000); // fade-out
    }
  </script>

</body>
</html>
