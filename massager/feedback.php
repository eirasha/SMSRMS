<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];

// Fetch feedback for bookings assigned to this massager
$stmt = $conn->prepare("
    SELECT f.rating, f.comment, s.name AS service_name, c.username AS customer_name
    FROM feedback f
    JOIN bookings b ON f.booking_id = b.id
    JOIN users c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$massager_id]);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Feedback Received</h2>

<?php if(count($feedbacks)==0): ?>
    <p>No feedback yet.</p>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>Customer</th>
            <th>Service</th>
            <th>Rating</th>
            <th>Comment</th>
        </tr>
        <?php foreach($feedbacks as $f): ?>
        <tr>
            <td><?php echo htmlspecialchars($f['customer_name']); ?></td>
            <td><?php echo htmlspecialchars($f['service_name']); ?></td>
            <td><?php echo $f['rating']; ?></td>
            <td><?php echo htmlspecialchars($f['comment']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
