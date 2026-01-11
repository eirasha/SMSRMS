<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users m ON b.massager_id = m.id
    WHERE b.customer_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>My Bookings</h2>

<table border="1" cellpadding="5">
    <tr>
        <th>Service</th>
        <th>Massager</th>
        <th>Date</th>
        <th>Time</th>
        <th>Status</th>
        <th>Payment</th>
        <th>Action</th>
    </tr>
    <?php foreach($bookings as $b): ?>
    <?php
        // Color-code status
        $statusColor = match($b['status']) {
            'pending' => 'orange',
            'approved' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'black',
        };
        $paymentColor = $b['payment_status']=='paid' ? 'green' : 'orange';
    ?>
    <tr>
        <td><?php echo htmlspecialchars($b['service_name']); ?></td>
        <td><?php echo htmlspecialchars($b['massager_name'] ?? '-'); ?></td>
        <td><?php echo $b['booking_date']; ?></td>
        <td><?php echo $b['booking_time']; ?></td>
        <td style="color:<?php echo $statusColor; ?>;"><?php echo ucfirst($b['status']); ?></td>
        <td style="color:<?php echo $paymentColor; ?>;"><?php echo ucfirst($b['payment_status']); ?></td>
        <td>
            <?php if($b['payment_status']=='pending'): ?>
                <a href="payment.php?booking_id=<?php echo $b['id']; ?>">Pay Now</a>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
