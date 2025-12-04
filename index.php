<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('inc/functions.php');

$pdo = db();
$plans = $pdo->query("SELECT * FROM plans WHERE active=1 ORDER BY price ASC")->fetchAll();

// Capture captive portal parameters if present
$return_url = $_GET['return_url'] ?? 'http://www.google.com';
$from_captive = isset($_GET['from_captive']) ? 1 : 0;
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
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
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
    </nav>
    <div class="nav-btns">
      <a href="login.php" class="signin">Sign In</a>
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>

<section class="hero">
  <div class="container hero-content">
    <h1>Affordable Internet<br>For Everyone</h1>
    <p>Pay-as-you-go WiFi starting from just <span>Ksh 10</span> per hour.<br>
       Stay connected without breaking the bank.</p>
    <div class="hero-buttons">
      <a href="plans.php" class="btn-primary">View Plans</a>
      <a href="register.php" class="btn-outline">Get Connected</a>
    </div>
    <div class="hero-features">
      <span>⚡ High-Speed Mbps</span>
      <span>🚀 Instant Activation</span>
      <span>🔒 No Contracts</span>
    </div>
  </div>
  <div class="wave"></div>
</section>    

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
      if ($minutes >= 43200) { // 30 days
          $durationText = '1 month';
          $icon = 'fa-calendar-alt';
      } elseif ($minutes >= 10080) { // 7 days
          $durationText = '1 week';
          $icon = 'fa-calendar-week';
      } elseif ($minutes >= 1440) { // 1 day
          $durationText = '1 day';
          $icon = 'fa-sun';
      } elseif ($minutes >= 60) { // 1 hour
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
      
      <a href="purchase.php?plan_id=<?= $p['id'] ?><?= $from_captive ? '&from_captive=1&return_url=' . urlencode($return_url) : '' ?>" class="btn-primary">Choose Plan</a>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="why-choose-us">
  <h2>Why Choose Leo Konnect?</h2>
  <p class="intro">We're committed to making internet access simple, affordable, and reliable for everyone in Kenya.</p>

  <div class="why-grid">
    <div class="why-card">
      <i class="fas fa-bolt"></i>
      <h3>Instant Activation</h3>
      <p>Get connected immediately after payment. No waiting, no setup fees.</p>
    </div>

    <div class="why-card">
      <i class="fas fa-wallet"></i>
      <h3>Affordable Pricing</h3>
      <p>Pay only for what you use plans start from just Ksh 10 per hour.</p>
    </div>

    <div class="why-card">
      <i class="fas fa-solid fa-people-group"></i>
      <h3>Community First</h3>
      <p>Bringing reliable and affordable internet connectivity to everyone.</p>
    </div>

    <div class="why-card">
      <i class="fas fa-wifi"></i>
      <h3>Reliable Coverage</h3>
      <p>Stay connected wherever you are with our strong and stable network.</p>
    </div>

    <div class="why-card">
      <i class="fas fa-headset"></i>
      <h3>24/7 Support</h3>
      <p>Our customer support team is always available to help you anytime.</p>
    </div>

    <div class="why-card">
      <i class="fas fa-shield-alt"></i>
      <h3>Secure Connection</h3>
      <p>Enjoy safe, encrypted, and uninterrupted internet access always.</p>
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
