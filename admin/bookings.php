<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
    $stmt->execute([$status, $booking_id]);
}

// Fetch bookings
$stmt = $conn->query("
    SELECT b.*, s.name AS service_name, 
           c.username AS customer_name, 
           m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users c ON b.customer_id = c.id
    LEFT JOIN users m ON b.massager_id = m.id
    ORDER BY b.booking_date DESC
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Bookings - Admin</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<header class="header">
    <h1>Admin Panel 🌻</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">
    <h2>All Bookings 🌻</h2>

    <div class="table-wrapper">
        <table class="booking-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Massager</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($bookings as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['customer_name']); ?></td>
                    <td><?= htmlspecialchars($b['massager_name']); ?></td>
                    <td><?= htmlspecialchars($b['service_name']); ?></td>
                    <td><?= $b['booking_date']; ?></td>
                    <td><?= $b['booking_time']; ?></td>
                    <td><?= ucfirst($b['status']); ?></td>
                    <td><?= ucfirst($b['payment_method']); ?></td>
                    <td><?= ucfirst($b['payment_status']); ?></td>
                    <td>
                        <form method="post" class="status-form">
                            <input type="hidden" name="booking_id" value="<?= $b['id']; ?>">
                            <select name="status">
                                <option value="pending" <?= $b['status']=='pending'?'selected':''; ?>>Pending</option>
                                <option value="approved" <?= $b['status']=='approved'?'selected':''; ?>>Approved</option>
                                <option value="completed" <?= $b['status']=='completed'?'selected':''; ?>>Completed</option>
                                <option value="cancelled" <?= $b['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="btn">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
