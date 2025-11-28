<?php
require_once('inc/functions.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Contact Us — Leo Konnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
      <a href="contact.php" class="active">Contact</a>
    </nav>
    <div class="nav-btns">
      <a href="login.php" class="signin">Sign In</a>
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>

<section class="contact-section container">
  <h2>Get in Touch</h2>
  <p class="subtext">We’d love to hear from you! Reach out to us for support, feedback, or partnership inquiries.</p>

  <div class="contact-grid">
    <div class="contact-info">
      <h3><i class="fas fa-headset"></i> Contact Information</h3>
      <ul>
        <li><i class="fas fa-map-marker-alt"></i> Malindi, Kenya</li>
        <li><i class="fas fa-phone"></i> +254 700 000 000</li>
        <li><i class="fas fa-envelope"></i> info@leokonnect.co.ke</li>
      </ul>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-x-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
      </div>
    </div>

    <div class="contact-form">
      <h3><i class="fas fa-paper-plane"></i> Send Us a Message</h3>
      <form method="post" action="send_message.php">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" placeholder="Enter your name" required>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="5" placeholder="Write your message..." required></textarea>

        <button type="submit" class="btn-primary">Send Message</button>
      </form>
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
        <li><a href="plans.php">View Plans</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="login.php">Sign In</a></li>
      </ul>
    </div>

    <div class="footer-column contact">
      <h3>Support</h3>
      <ul>
        <li><a href="#">FAQ</a></li>
        <li><a href="#">Help Center</a></li>
        <li><a href="#">Terms of Service</a></li>
        <li><a href="#">Privacy Policy</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Leo Konnect. All rights reserved.</p>
  </div>
</footer>
</body>
</html>
