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

// Fetch all bookings including payment info
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

<h2>All Bookings (Admin)</h2>

<table border="1" cellpadding="5">
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
    <?php foreach($bookings as $b): ?>
    <tr>
        <td><?php echo htmlspecialchars($b['customer_name']); ?></td>
        <td><?php echo htmlspecialchars($b['massager_name']); ?></td>
        <td><?php echo htmlspecialchars($b['service_name']); ?></td>
        <td><?php echo $b['booking_date']; ?></td>
        <td><?php echo $b['booking_time']; ?></td>
        <td><?php echo ucfirst($b['status']); ?></td>
        <td><?php echo ucfirst($b['payment_method']); ?></td>
        <td><?php echo ucfirst($b['payment_status']); ?></td>
        <td>
            <form method="post" style="display:inline;">
                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                <select name="status">
                    <option value="pending" <?php if($b['status']=='pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if($b['status']=='approved') echo 'selected'; ?>>Approved</option>
                    <option value="completed" <?php if($b['status']=='completed') echo 'selected'; ?>>Completed</option>
                    <option value="cancelled" <?php if($b['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
                <button type="submit">Update</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<nav>
    <a href="dashboard.php">Dashboard</a> | 
    <a href="../auth/logout.php">Logout</a>
</nav>
