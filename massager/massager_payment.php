<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* SECURITY CHECK */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    exit("Unauthorized");
}

$massager_id = $_SESSION['user_id'];
$success = $error = "";

/* HANDLE PAYMENT STATUS UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['payment_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $payment_status = $_POST['payment_status'] === 'paid' ? 'paid' : 'pending';

    $stmt = $conn->prepare("UPDATE bookings SET payment_status=? WHERE id=? AND massager_id=?");
    if ($stmt->execute([$payment_status, $booking_id, $massager_id])) {
        $success = "Payment status updated successfully.";
    } else {
        $error = "Failed to update payment status.";
    }
}

/* FETCH BOOKINGS WITH PAYMENT */
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, c.username AS customer_name
    FROM bookings b
    JOIN users c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ?
    ORDER BY b.booking_date DESC, b.booking_time DESC
");
$stmt->execute([$massager_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* FETCH LATEST QR IMAGE */
$qr = $conn->query("SELECT * FROM payment_qr ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Payments</title>
<link rel="stylesheet" href="../css/massager.css">
</head>
<body>

<header class="header">
    <h1>Manage Payments 💰</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">Bookings</a>
        <a href="feedback.php">Feedback</a>
        <a href="availability.php">Availability</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="success-msg"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php if ($qr): ?>
<div class="card">
    <h3>Customer Payment QR</h3>
    <img src="../uploads/<?= htmlspecialchars($qr['qr_image']) ?>" alt="Payment QR" width="200">
    <p><small>Uploaded at <?= $qr['created_at'] ?> by <?= htmlspecialchars($qr['role']) ?></small></p>
</div>
<?php else: ?>
<p class="info-msg">No QR uploaded yet.</p>
<?php endif; ?>

<h3>Bookings & Payments</h3>
<?php if (empty($bookings)): ?>
    <p class="info-msg">No bookings found.</p>
<?php else: ?>
    <?php foreach ($bookings as $b): ?>
        <div class="card">
            <strong>Customer:</strong> <?= htmlspecialchars($b['customer_name']) ?><br>
            <strong>Service:</strong> <?= htmlspecialchars($b['service_name']) ?><br>
            <strong>Date & Time:</strong> <?= $b['booking_date'] ?> | <?= $b['booking_time'] ?><br>
            <strong>Status:</strong> <?= ucfirst($b['status']) ?><br>
            <strong>Payment:</strong> <?= ucfirst($b['payment_status']) ?><br>

            <?php if ($b['payment_status'] === 'pending'): ?>
            <form method="post" style="margin-top:8px;">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <select name="payment_status">
                    <option value="pending" selected>Pending</option>
                    <option value="paid">Paid</option>
                </select>
                <button type="submit" class="btn">Update</button>
            </form>
            <?php else: ?>
                <em>Paid ✅</em>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

</body>
</html>
