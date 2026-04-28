<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* =========================
   SECURITY CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$success = $error = "";

/* =========================
   FETCH LATEST QR
========================= */
$qr = $conn->query("SELECT * FROM payment_qr ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

/* =========================
   FETCH CUSTOMER BOOKINGS
========================= */
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.customer_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE CUSTOMER PAYMENT ACTION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];

    $stmt = $conn->prepare("SELECT payment_status FROM bookings WHERE id=? AND customer_id=?");
    $stmt->execute([$booking_id, $customer_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $error = "Booking not found.";
    } elseif ($booking['payment_status'] === 'paid') {
        $error = "Payment already marked as paid.";
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET payment_status='pending' WHERE id=?");
        $stmt->execute([$booking_id]);
        $success = "Payment submitted. Massager/Admin will verify it.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Payments</title>
<link rel="stylesheet" href="../css/cust.css">
</head>
<body>

<header class="header">
    <h1>My Payments 💳</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="book_service.php">Book Service</a>
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

    <!-- QR Section -->
    <?php if($qr): ?>
        <div class="qr-section">
            <h3>Scan QR to Pay</h3>
            <img src="../uploads/<?= htmlspecialchars($qr['qr_image']) ?>" alt="Payment QR" class="qr-img">
            <p class="qr-info">Uploaded by <?= htmlspecialchars($qr['role']) ?> at <?= $qr['created_at'] ?></p>
        </div>
    <?php else: ?>
        <p>No payment QR available yet. Please wait for massager/admin to upload.</p>
    <?php endif; ?>

    <!-- Booking Table -->
    <h3>My Bookings</h3>
    <?php if(empty($bookings)): ?>
        <p>No bookings found.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['service_name']) ?></td>
                        <td><?= $b['booking_date'] ?></td>
                        <td><?= $b['booking_time'] ?></td>
                        <td><?= ucfirst($b['status']) ?></td>
                        <td><?= ucfirst($b['payment_status']) ?></td>
                        <td>
                            <?php if($b['payment_status'] !== 'paid'): ?>
                                <form method="post">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn-pay">Mark as Paid via QR</button>
                                </form>
                            <?php else: ?>
                                <em>Paid</em>
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
