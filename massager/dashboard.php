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
    ORDER BY b.booking_date DESC
");
$stmt->execute([$massager_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Massager)</h2>

<nav>
    <a href="dashboard.php">Dashboard</a> | 
    <a href="../auth/logout.php">Logout</a>
</nav>
<hr>

<h3>My Bookings</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>Customer</th>
        <th>Service</th>
        <th>Date</th>
        <th>Time</th>
        <th>Status</th>
    </tr>
    <?php foreach($bookings as $b) { ?>
    <tr>
        <td><?php echo htmlspecialchars($b['customer_name']); ?></td>
        <td><?php echo htmlspecialchars($b['service_name']); ?></td>
        <td><?php echo $b['booking_date']; ?></td>
        <td><?php echo $b['booking_time']; ?></td>
        <td><?php echo ucfirst($b['status']); ?></td>
    </tr>
    <?php } ?>
</table>
