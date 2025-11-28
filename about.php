<?php
require_once('inc/functions.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>About Us — Leo Konnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* =============================
ABOUT US PAGE STYLES
============================= */
.about-section {
padding: 80px 20px;
text-align: center;
}

.about-section h2 {
font-size: 2.4rem;
color: #003366;
margin-bottom: 10px;
}

.about-section .subtext {
font-size: 1rem;
color: #666;
margin-bottom: 50px;
}

.about-content {
display: flex;
align-items: center;
justify-content: space-between;
flex-wrap: wrap;
gap: 40px;
}

.about-text {
flex: 1 1 500px;
text-align: left;
color: #333;
line-height: 1.7;
}

.about-text p {
margin-bottom: 20px;
font-size: 1rem;
}

.about-text h3 {
font-size: 1.5rem;
margin-top: 30px;
color: #003366;
}

.values-grid {
display: flex;
justify-content: center;
align-items: stretch;
flex-wrap: wrap;
gap: 25px;
margin: 50px auto;
max-width: 1200px;
padding: 0 20px;
}

.value-card {
flex: 1 1 calc(33.333% - 25px);
background: #fff;
border: 1px solid #e6e6e6;
border-radius: 12px;
padding: 35px 25px;
text-align: center;
box-shadow: 0 4px 10px rgba(0,0,0,.05);
transition: all 0.3s ease;
box-sizing: border-box;
}

.value-card:hover {
transform: translateY(-7px);
border-color: #28a745;
box-shadow: 0 8px 20px rgba(0,0,0,.08);
}


.value-card i {
font-size: 36px;
color: #28a745;
margin-bottom: 18px;
background: rgba(40, 167, 69, 0.1);
width: 80px;
height: 80px;
display: flex;
justify-content: center;
align-items: center;
border-radius: 50%;
margin-left: auto;
margin-right: auto;
}

.value-card h3 {
font-size: 20px;
font-weight: 600;
color: #28a745;
margin-bottom: 12px;
}

.value-card p {
font-size: 16px;
color: #555;
line-height: 1.6;
max-width: 90%;
margin: 0 auto;
}

/* Responsive */
@media (max-width: 992px) {
.value-card {
flex: 1 1 calc(50% - 25px);
}
}

@media (max-width: 600px) {
.value-card {
flex: 1 1 100%;
}
}



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
      <a href="about.php" class="active">About</a>
      <a href="contact.php">Contact</a>
    </nav>
    <div class="nav-btns">
      <a href="login.php" class="signin">Sign In</a>
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>

<section class="about-section container">
  <h2>About Leo Konnect</h2>
  <p class="subtext">Affordable, reliable, and fast internet for everyone in Kenya.</p>

  <div class="about-grid">
    <div class="about-content">
      <h3>Who We Are</h3>
      <p>Leo Konnect is a forward-thinking Internet Service Provider (ISP) dedicated to bridging Kenya’s digital divide. We believe everyone — from students and small businesses to families — deserves fast, affordable, and reliable internet access. Our mission is to make connectivity simple, transparent, and accessible to all.</p>

      <h3>What We Do</h3>
      <p>We offer a range of flexible WiFi plans that suit different needs and budgets — from hourly access to monthly unlimited connections. With an easy-to-use online portal, instant M-Pesa payments, and automated access control, Leo Konnect delivers internet the smart way.</p>

      <h3>Our Vision</h3>
      <p>To empower communities and businesses across Kenya through affordable connectivity and innovative digital solutions.</p>

      <h3>Our Mission</h3>
      <p>To provide fast, secure, and flexible internet services that adapt to your lifestyle and needs — whether at home, school, or work.</p>
    </div>

    <!--<div class="about-image">
      <img src="assets/images/about.jpg" alt="About Leo Konnect">
    </div>-->
  </div>
</section>

<section class="values-section container">
  <h2>Our Core Values</h2>
  <div class="values-grid">
    <div class="value-card">
      <i class="fas fa-users"></i>
      <h3>Customer Focus</h3>
      <p>We listen, understand, and serve our users with integrity and transparency.</p>
    </div>
    <div class="value-card">
      <i class="fas fa-bolt"></i>
      <h3>Innovation</h3>
      <p>We embrace technology and creativity to deliver fast, reliable, and modern internet experiences.</p>
    </div>
    <div class="value-card">
      <i class="fas fa-globe-africa"></i>
      <h3>Accessibility</h3>
      <p>We’re committed to making internet access available and affordable for every Kenyan, no matter where they are.</p>
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
