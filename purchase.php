<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('inc/functions.php');
session_start();

if (!isset($_GET['plan_id'])) die('Plan required.');
$plan_id = intval($_GET['plan_id']);

$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

$stmt = $pdo->prepare("SELECT * FROM plans WHERE id=? LIMIT 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) die('Plan not found.');

$user_id = $_SESSION['user_id'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Purchase Plan | Leo Konnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>
    
<div class="purchase-container">
  <a href="../index.php" class="logo">Leo Konnect</a>

  <h2><?= htmlspecialchars($plan['title']) ?></h2>
  <p class="plan-price">Ksh <?= number_format($plan['price'], 2) ?></p>

  <form id="payForm" method="POST" action="../leokonnect/api/payments.php">
    <input type="hidden" name="action" value="initiate">
    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">

    <label>Phone Number (used for M-Pesa)</label>
    <input name="phone" required placeholder="07xxxxxxxx">

    <label>IP Address (optional)</label>
    <input name="ip" value="<?= $_SERVER['REMOTE_ADDR'] ?>">

    <label>MAC Address (optional)</label>
    <input name="mac" placeholder="AA:BB:CC:DD:EE:FF">

    <button type="submit">Pay Ksh <?= number_format($plan['price'], 2) ?></button>
  </form>

</div>
    
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
</body>
</html>
