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

// --- Fetch all bookings for slots display ---
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

<h2>Customer Dashboard</h2>



<!-- Booking Slots -->
<h2>My Bookings</h2>

<?php if(count($bookings) == 0): ?>
    <p>No bookings found.</p>
<?php else: ?>
<table border="1" cellpadding="5">
    <tr>
        <th>Service</th>
        <th>Massager</th>
        <th>Date</th>
        <th>Time</th>
        <th>Status</th>
        <th>Payment Status</th>
        <th>Action</th>
    </tr>
    <?php foreach($bookings as $b): ?>
    <tr>
        <td><?php echo htmlspecialchars($b['service_name']); ?></td>
        <td><?php echo htmlspecialchars($b['massager_name'] ?? '-'); ?></td>
        <td><?php echo $b['booking_date']; ?></td>
        <td><?php echo $b['booking_time']; ?></td>
        <td><?php echo ucfirst($b['status']); ?></td>
        <td><?php echo ucfirst($b['payment_status']); ?></td>
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
<?php endif; ?>

<!-- Navigation -->
<nav>
    <a href="dashboard.php">Dashboard</a> |
    <a href="book.php">Book Slot</a> |  
    <a href="my_booking.php">My Bookings</a> | 
    <a href="payment.php">Payments</a> | 
    <a href="../auth/logout.php">Logout</a>
</nav>
