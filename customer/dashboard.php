<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// --- Dashboard Stats ---
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE customer_id=?");
$stmt->execute([$customer_id]);
$totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as completed FROM bookings WHERE customer_id=? AND status='completed'");
$stmt->execute([$customer_id]);
$completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

$stmt = $conn->prepare("SELECT COUNT(*) as pendingPayment FROM bookings WHERE customer_id=? AND payment_status='pending'");
$stmt->execute([$customer_id]);
$pendingPayment = $stmt->fetch(PDO::FETCH_ASSOC)['pendingPayment'];

$stmt = $conn->prepare("SELECT COUNT(*) as paidPayment FROM bookings WHERE customer_id=? AND payment_status='paid'");
$stmt->execute([$customer_id]);
$paidPayment = $stmt->fetch(PDO::FETCH_ASSOC)['paidPayment'];

// --- Fetch all bookings ---
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users m ON b.massager_id = m.id
    WHERE b.customer_id=?
    ORDER BY b.booking_date DESC, b.booking_time ASC
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Dashboard</title>
<link rel="stylesheet" href="../css/cust.css">
</head>
<body>

<header class="header">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['username']); ?> </h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="book_service.php">Book Slot</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="payment.php">Payments</a>
        <a href="feedback.php">Feedback</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

    <!-- Dashboard Cards -->
    <div class="dashboard-cards">
        <div class="card">
            <h3>Total Bookings</h3>
            <p><?= $totalBookings ?></p>
        </div>
        <div class="card">
            <h3>Completed</h3>
            <p><?= $completed ?></p>
        </div>
        <div class="card">
            <h3>Pending Payment</h3>
            <p><?= $pendingPayment ?></p>
        </div>
        <div class="card">
            <h3>Paid</h3>
            <p><?= $paidPayment ?></p>
        </div>
    </div>

    <!-- Bookings Table -->
    <h2>My Bookings </h2>

    <div class="table-wrapper">
        <?php if(count($bookings) == 0): ?>
            <p>No bookings found.</p>
        <?php else: ?>
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Massager</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['service_name']); ?></td>
                        <td><?= htmlspecialchars($b['massager_name'] ?? '-'); ?></td>
                        <td><?= $b['booking_date']; ?></td>
                        <td><?= $b['booking_time']; ?></td>
                        <td><?= ucfirst($b['status']); ?></td>
                        <td><?= ucfirst($b['payment_status']); ?></td>
                        <td>
                            <?php if($b['payment_status']=='pending'): ?>
                                <a href="payment.php?booking_id=<?= $b['id']; ?>">Pay Now</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
