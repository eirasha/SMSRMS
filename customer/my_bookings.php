<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$success = $error = "";

/* =========================
   HANDLE BOOKING CANCELLATION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cancel_booking_id'])) {
    $booking_id = (int)$_POST['cancel_booking_id'];

    $stmt = $conn->prepare("SELECT status FROM bookings WHERE id=? AND customer_id=?");
    $stmt->execute([$booking_id, $customer_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking && $booking['status'] === 'pending') {
        $stmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
        $stmt->execute([$booking_id]);
        $success = "Booking cancelled successfully.";
    } else {
        $error = "You can only cancel pending bookings.";
    }
}

/* =========================
   FETCH CUSTOMER BOOKINGS
========================= */
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users m ON b.massager_id = m.id
    WHERE b.customer_id = ?
    ORDER BY b.booking_date DESC, b.booking_time DESC
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings</title>
<link rel="stylesheet" href="../css/cust.css">
</head>
<body>

<header class="header">
    <h1>My Bookings </h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="book_service.php">Book a Service</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">
    <?php if($error): ?>
        <p class="msg error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if($success): ?>
        <p class="msg success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if(empty($bookings)): ?>
        <p>You have no bookings yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Massager</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bookings as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['service_name']) ?></td>
                            <td><?= htmlspecialchars($b['massager_name'] ?? 'Not assigned') ?></td>
                            <td><?= $b['booking_date'] ?></td>
                            <td><?= $b['booking_time'] ?></td>
                            <td><?= ucfirst($b['status']) ?></td>
                            <td><?= ucfirst($b['payment_status']) ?></td>
                            <td>
                                <?php if($b['status'] === 'pending'): ?>
                                    <form method="post">
                                        <input type="hidden" name="cancel_booking_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
