<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once __DIR__ . '/../inc/functions.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $pdo = db();
    $pdo->exec("SET time_zone = '+03:00'");
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && verify_password($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['role'] = $admin['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login | Leo Konnect</title>
  <link rel="stylesheet" href="admin_style.css">
	<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
</head>
    
<body>
    
<section class="auth-page">
  <div class="auth-container">
    <a href="index.php" class="logo">
         <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
     </a>
    <h2>Admin Login</h2>
    <p class="subtitle">Sign in to manage <strong>Leo Konnect</strong></p>

    <?php if (!empty($error)): ?>
      <div id="notification" class="notification error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($_GET['registered'])): ?>
      <div id="notification" class="notification success">✅ Admin created successfully! You can now log in.</div>
    <?php endif; ?>

    <form method="POST">
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter your email" required>

      <label>Password</label>
      <div class="password-wrapper">
        <input type="password" name="password" id="password" placeholder="Enter your password" required>
        <span class="toggle-password" onclick="togglePassword()">👁️</span>
      </div>

      <button type="submit">Login</button>
    </form>

    <p>Don’t have an admin account? <a href="register_admin.php">Create one</a></p>
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
    function togglePassword() {
      const password = document.getElementById('password');
      const toggle = document.querySelector('.toggle-password');
      if (password.type === 'password') {
        password.type = 'text';
        toggle.textContent = '🙈';
      } else {
        password.type = 'password';
        toggle.textContent = '👁️';
      }
    }

    const notif = document.getElementById('notification');
    if (notif) {
      notif.style.opacity = '1';
      setTimeout(() => notif.style.opacity = '0', 5000);
      setTimeout(() => notif.remove(), 5500);
    }
  </script>
</body>
</html>
