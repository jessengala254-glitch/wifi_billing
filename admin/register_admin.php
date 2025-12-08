<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/functions.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);

    if (!$name || !$email || !$phone || !$password || !$confirm) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $pdo = db();
        $hash = hash_password($password);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, $phone, $hash]);
            header('Location: admin_login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Admin Account | Leo Konnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin_style.css">
	<script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
</head>
<body>
<section class="auth-page">
  <div class="auth-container">
     <a href="../index.php" class="logo">
         <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
     </a>
    <h2>Create Admin Account</h2>

    <?php if (!empty($error)): ?>
      <div class="notification error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label>Name</label>
      <input type="text" name="name" placeholder="Enter full name" required>

      <label>Email</label>
      <input type="email" name="email" placeholder="Enter email address" required>

      <label>Phone Number</label>
      <input type="text" name="phone" placeholder="Enter phone number" required>

      <label>Password</label>
      <div class="password-wrapper">
        <input type="password" name="password" id="password" placeholder="Create password" required>
        <i id="eye1" onclick="togglePassword('password','eye1')">👁️</i>
      </div>

      <label>Confirm Password</label>
      <div class="password-wrapper">
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
        <i id="eye2" onclick="togglePassword('confirm_password','eye2')">👁️</i>
      </div>

      <button class="submit">Create Admin</button>
    </form>

    <p>Already an admin? <a href="admin_login.php">Login</a></p>
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
    function togglePassword(id, iconId) {
      const input = document.getElementById(id);
      const icon = document.getElementById(iconId);
      if (input.type === "password") {
        input.type = "text";
        icon.textContent = "🙈";
      } else {
        input.type = "password";
        icon.textContent = "👁️";
      }
    }
</script>
</body>
</html>
