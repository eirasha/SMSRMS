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

<h2>Admin Dashboard</h2>

<div style="display:flex; gap:20px;">
    <div style="border:1px solid #ccc; padding:10px; width:150px;">
        <strong>Total Bookings</strong>
        <p><?php echo $totalBookings; ?></p>
    </div>
    <div style="border:1px solid #ccc; padding:10px; width:150px;">
        <strong>Completed Bookings</strong>
        <p><?php echo $completed; ?></p>
    </div>
    <div style="border:1px solid #ccc; padding:10px; width:150px;">
        <strong>Pending Payments</strong>
        <p><?php echo $pendingPayment; ?></p>
    </div>
    <div style="border:1px solid #ccc; padding:10px; width:150px;">
        <strong>Paid Payments</strong>
        <p><?php echo $paidPayment; ?></p>
    </div>
</div>

<nav>
    <a href="bookings.php">Manage Bookings</a> 
    |<a href="service.php">Manage Services</a> | 
    <a href="../auth/logout.php">Logout</a>
</nav>
