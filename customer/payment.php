<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch pending payments
$stmt = $conn->prepare("
    SELECT b.id, s.name AS service_name, b.booking_date, b.booking_time, b.payment_status, b.payment_method
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.payment_status='pending'
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $method = $_POST['payment_method'] ?? 'cash';

    // Update payment
    $stmt = $conn->prepare("UPDATE bookings SET payment_status='paid', payment_method=? WHERE id=?");
    $stmt->execute([$method, $booking_id]);

    $success = "Payment successful!";
    
    // Refresh bookings list
    $stmt = $conn->prepare("
        SELECT b.id, s.name AS service_name, b.booking_date, b.booking_time, b.payment_status, b.payment_method
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.customer_id = ? AND b.payment_status='pending'
    ");
    $stmt->execute([$customer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2>Pending Payments</h2>

<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

<?php if(count($bookings) == 0): ?>
    <p>No pending payments.</p>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>Service</th>
            <th>Date</th>
            <th>Time</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
            <th>Action</th>
        </tr>
        <?php foreach($bookings as $b): ?>
        <tr>
            <td><?php echo htmlspecialchars($b['service_name']); ?></td>
            <td><?php echo $b['booking_date']; ?></td>
            <td><?php echo $b['booking_time']; ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                    <select name="payment_method">
                        <option value="cash" <?php if($b['payment_method']=='cash') echo 'selected'; ?>>Cash</option>
                        <option value="qr" <?php if($b['payment_method']=='qr') echo 'selected'; ?>>QR</option>
                    </select>
            </td>
            <td><?php echo ucfirst($b['payment_status']); ?></td>
            <td>
                    <button type="submit">Pay Now</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
