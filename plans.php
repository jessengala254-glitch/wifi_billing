<?php
require_once('inc/functions.php');
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");
$plans = $pdo->query("SELECT * FROM plans WHERE active=1 ORDER BY price ASC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Leo Konnect — Affordable Internet for Everyone</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

<section id="plans" class="plans-section container">
  <h2>Choose Your Plan</h2>
  <p class="subtext">
    Flexible, affordable plans that fit your internet needs.<br>
    No hidden fees, no contracts.
  </p>

  <div class="plans-grid">
    <?php foreach($plans as $i=>$p): ?>
    <?php
      $minutes = $p['duration_minutes'];
      if ($minutes >= 43200) {
          $durationText = '1 month';
          $icon = 'fa-calendar-alt';
      } elseif ($minutes >= 10080) {
          $durationText = '1 week';
          $icon = 'fa-calendar-week';
      } elseif ($minutes >= 1440) {
          $durationText = '1 day';
          $icon = 'fa-sun';
      } elseif ($minutes >= 60) {
          $durationText = '1 hour';
          $icon = 'fa-clock';
      } else {
          $durationText = $minutes . ' minutes';
          $icon = 'fa-stopwatch';
      }
    ?>
    <div class="plan-card <?= $i==1 ? 'popular' : '' ?>">
      <?php if($i==1): ?><div class="badge">Popular</div><?php endif; ?>
      <div class="plan-icon">
        <i class="fas <?= $icon ?>"></i>
      </div>
      <h3><?= htmlspecialchars($p['title']) ?></h3>
      <p class="price">Ksh <?= number_format($p['price'],0) ?></p>
      <ul>
        <li>Unlimited data for <?= htmlspecialchars($durationText) ?></li>
        <li>Ultra high-speed internet</li>
        <li>Instant activation</li>
        <li>Multiple device support</li>
      </ul>
      <a href="purchase.php?plan_id=<?= $p['id'] ?>" class="btn-primary">Choose Plan</a>
    </div>
    <?php endforeach; ?>
  </div>
</section>
    
<!-- FAQ SECTION -->
<section id="faq" class="faq-section container">
  <h2>Frequently Asked Questions</h2>
  <div class="faq-grid">

    <div class="faq-item">
      <h3><i class="fas fa-bolt"></i> How do I activate my plan?</h3>
      <p>Simply sign up, choose your plan, and pay via M-Pesa. Your internet will be activated instantly.</p>
    </div>

    <div class="faq-item">
      <h3><i class="fas fa-sync-alt"></i> Can I change my plan anytime?</h3>
      <p>Yes! You can upgrade or purchase a new plan at any time from your dashboard.</p>
    </div>

    <div class="faq-item">
      <h3><i class="fas fa-bell"></i> What happens when my plan expires?</h3>
      <p>You'll receive a notification before expiry. After expiration, you'll need to purchase a new plan to reconnect.</p>
    </div>

    <div class="faq-item">
      <h3><i class="fas fa-infinity"></i> Is there a data limit?</h3>
      <p>All our plans offer unlimited data within the specified time period. Enjoy worry-free browsing!</p>
    </div>

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

</body>
</html>
