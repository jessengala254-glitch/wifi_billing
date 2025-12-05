<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Active Hotspot Sessions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">

</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <a href="#" class="logo"><i class="fas fa-wifi"></i> Leo <span>Konnect</span></a>
    </div>
    <a href="../api/auth.php?action=logout" class="logout-btn">Logout</a>
</div>



<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <a href="#" class="logo">
      <i class="fas fa-wifi"></i> Leo <span>Konnect</span>
    </a>
     
    <ul>
        <li><a href="index.php" onclick="closeSidebar()">Home</a></li>
        <li><a href="users_manage.php" onclick="closeSidebar()">Users</a></li>
        <li><a href="plans_manage.php" onclick="closeSidebar()">Plans</a></li>
        <li><a href="payments_manage.php" onclick="closeSidebar()"><i class="fa fa-credit-card"></i>Payments</a></li>
        <li><a href="admin_reports.php" onclick="closeSidebar()">Reports</a></li>
        <li><a href="logs_view.php" onclick="closeSidebar()">Logs</a></li>
        <li><a href="mikrotik_sessions.php" onclick="closeSidebar()"><i class="fa fa-wifi"></i>Hotspot Sessions</a></li>
        <li><a href="mikrotik_devices.php" onclick="closeSidebar()">MikroTik</a></li>
    </ul>
</div>

<!-- Dark Overlay -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    sidebar.classList.remove('active');
    overlay.classList.remove('active');
}
</script>

</body>
</html>
