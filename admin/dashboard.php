<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch booking stats
$stmt = $conn->query("SELECT COUNT(*) as total FROM bookings");
$totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(*) as completed FROM bookings WHERE status='completed'");
$completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

$stmt = $conn->query("SELECT COUNT(*) as pendingPayment FROM bookings WHERE payment_status='pending'");
$pendingPayment = $stmt->fetch(PDO::FETCH_ASSOC)['pendingPayment'];

$stmt = $conn->query("SELECT COUNT(*) as paidPayment FROM bookings WHERE payment_status='paid'");
$paidPayment = $stmt->fetch(PDO::FETCH_ASSOC)['paidPayment'];
?>
 
<nav>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header class="header">
    <div class="header-top">
        <h1>Admin Dashboard 🌻</h1>
        <button class="nav-toggle" aria-label="Toggle navigation">☰</button>
    </div>
    <nav class="nav-bar">
        <a href="bookings.php">Manage Bookings</a>
        <a href="service.php">Manage Services</a>
        <a href="update_payment.php">Manage Payments</a>
        <a href="feedback.php">Manage Feedback</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<main class="main-container">
    <div class="dashboard-cards">
        <div class="card">
            <h3>Total Bookings</h3>
            <p><?= $totalBookings ?></p>
        </div>
        <div class="card">
            <h3>Completed Bookings</h3>
            <p><?= $completed ?></p>
        </div>
        <div class="card">
            <h3>Pending Payments</h3>
            <p><?= $pendingPayment ?></p>
        </div>
        <div class="card">
            <h3>Paid Payments</h3>
            <p><?= $paidPayment ?></p>
        </div>
    </div>
</main>

<script>
const toggleBtn = document.querySelector('.nav-toggle');
const navBar = document.querySelector('.nav-bar');

toggleBtn.addEventListener('click', () => {
    navBar.classList.toggle('show');
});
</script>
</body>
</html>
 
