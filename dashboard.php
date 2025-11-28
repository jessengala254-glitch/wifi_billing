<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('inc/functions.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?session=expired");
    exit();
}


// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ----------------------
//  ADD EXPIRY CHECK HERE
// ----------------------
$pdo = db();
$pdo->exec("SET time_zone = '+03:00'");

if (isset($_SESSION['voucher'])) {
    $voucher_username = $_SESSION['voucher'];

    $stmt = $pdo->prepare("SELECT expiry FROM vouchers WHERE username=? LIMIT 1");
    $stmt->execute([$voucher_username]);
    $row = $stmt->fetch();

    if (!$row || strtotime($row['expiry']) < time()) {
        session_unset();
        session_destroy();
        header("Location: login.php?expired=1");
        exit;
    }
}

$uid = $_SESSION['user_id'];
$pdo = db();

/* ============================================================
   FETCH ACTIVE SESSION (IF ANY)
   ============================================================ */
$sessionQuery = $pdo->prepare("
    SELECT s.*, p.title 
    FROM sessions s 
    JOIN plans p ON s.plan_id = p.id 
    WHERE s.user_id = ? AND s.active = 1 
    ORDER BY s.expires_at DESC 
    LIMIT 1
");
$sessionQuery->execute([$uid]);
$session = $sessionQuery->fetch();

/* ============================================================
   FETCH PURCHASE HISTORY
   ============================================================ */
$historyQuery = $pdo->prepare("
    SELECT payments.*, plans.title 
    FROM payments 
    JOIN plans ON payments.plan_id = plans.id 
    WHERE payments.user_id = ? 
    ORDER BY payments.created_at DESC 
    LIMIT 20
");
$historyQuery->execute([$uid]);
$history = $historyQuery->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Dashboard | Leo Konnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/e097ce4e3c.js" crossorigin="anonymous"></script>
</head>

<body>

<!-- Navbar -->
<header class="navbar">
  <div class="container nav-flex">
    <a href="index.php" class="logo">
      <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
    </a>

    <div class="hamburger" id="hamburger">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <nav>
      <a href="plans.php">Plans</a>
      <a href="#">About</a>
      <a href="#">Contact</a>
    </nav>

    <div class="nav-btns">
      <a href="../api/auth.php?action=logout" class="btn-primary">Logout</a>
    </div>
  </div>
</header>

<!-- Mobile Navigation -->
<div class="mobile-menu" id="mobileMenu">
  <a href="plans.php">Plans</a>
  <a href="#">About</a>
  <a href="#">Contact</a>
  <a href="../api/auth.php?action=logout" class="btn-primary">Logout</a>
</div>

<!-- Main Dashboard -->


<main class="dashboard-container" id="mainContent">

  <div class="dashboard-header">
    <h2>Your Dashboard</h2>
  </div>

  <!-- ============================
       Active Plan Section
       ============================ -->

  <section class="active-plan-section">
    <?php if ($session): ?>
      <div class="active-plan-card">
        <h3>Active Plan: <?= htmlspecialchars($session['title']) ?></h3>
        <p>Expires At: <?= htmlspecialchars($session['expires_at']) ?></p>
        <p id="countdown" class="countdown">Loading...</p>
      </div>

  <script>
    const exp = new Date("<?= addslashes($session['expires_at']) ?>");
    function runCountdown() {
      const now = new Date();
      let diff = exp - now;
      if (diff <= 0) {
        document.getElementById('countdown').innerText = "Expired";
        return;
      }
      let mins = Math.floor(diff / 60000);
      let secs = Math.floor((diff % 60000) / 1000);
      document.getElementById('countdown').innerText = `${mins}m ${secs}s remaining`;
    }
    setInterval(runCountdown, 1000);
    runCountdown();
  </script>
<?php else: ?>
  <div class="no-plan">
    <p>You have no active plan at the moment.</p>
    <a href="plans.php" class="btn-primary">Buy a plan</a>
  </div>
<?php endif; ?>


  </section>

  <!-- ============================
       Purchase History Section
       ============================ -->

  <section class="purchase-history-section">
    <h3>Purchase History</h3>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Plan</th>
            <th>Amount (Ksh)</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($history): ?>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><?= htmlspecialchars($h['title']) ?></td>
                <td><?= number_format($h['amount'], 2) ?></td>
                <td class="status-<?= strtolower($h['status']) ?>">
                  <?= htmlspecialchars(ucfirst($h['status'])) ?>
                </td>
                <td><?= htmlspecialchars($h['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center">No purchase history found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</main>


<!-- Footer -->
<footer class="site-footer">
  <div class="footer-container">

    <div class="footer-column brand">
      <h2 class="footer-logo">
         <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
      </h2>
      <p>Affordable internet for everyone in Kenya.</p>
    </div>

    <div class="footer-column">
      <h3>Quick Links</h3>
      <ul>
        <li><a href="plans.php">View Plans</a></li>
        <li><a href="#">About</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="login.php">Sign In</a></li>
      </ul>
    </div>

    <div class="footer-column contact">
      <h3>Contact</h3>
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

<!-- Mobile Navbar Script -->
<script>
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  const mainContent = document.querySelector('main');

  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    mobileMenu.classList.toggle('show');
    mainContent.style.marginTop = mobileMenu.classList.contains('show')
      ? (mobileMenu.offsetHeight - 50) + 'px'
      : '0';
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      mobileMenu.classList.remove('show');
      hamburger.classList.remove('open');
      mainContent.style.marginTop = '0';
    }
  });
</script>

</body>
</html>







