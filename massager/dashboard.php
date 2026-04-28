<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Only massager can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];

// Fetch bookings assigned to this massager
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, u.username AS customer_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u ON b.customer_id = u.id
    WHERE b.massager_id = ?
    ORDER BY b.booking_date DESC, b.booking_time ASC
");
$stmt->execute([$massager_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Massager Dashboard</title>
<link rel="stylesheet" href="../css/massager.css">
</head>
<body>

<header class="header">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">Manage Bookings</a>
        <a href="massager_payment.php">Manage Payments</a>
        <a href="feedback.php">Feedback</a>
        <a href="availability.php">Update Availability</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">
    <h2>My Bookings</h2>

    <?php if(empty($bookings)): ?>
        <p class="info-msg">No bookings assigned yet.</p>
    <?php else: ?>
        <div class="booking-cards">
            <?php foreach($bookings as $b): ?>
                <div class="card booking-card">
                    <strong>Customer:</strong> <?= htmlspecialchars($b['customer_name']) ?><br>
                    <strong>Service:</strong> <?= htmlspecialchars($b['service_name']) ?><br>
                    <strong>Date:</strong> <?= $b['booking_date'] ?><br>
                    <strong>Time:</strong> <?= $b['booking_time'] ?><br>
                    <strong>Status:</strong> 
                    <span class="status <?= strtolower($b['status']) ?>">
                        <?= ucfirst($b['status']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
