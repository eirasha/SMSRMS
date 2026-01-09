<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'massager') {
  header("Location: ../index.php");
  exit;
}

$massager_id = $_SESSION['user_id'];

$sql = "SELECT b.*, u.name AS customer_name
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        WHERE b.massager_id = ?
        ORDER BY b.booking_date, b.start_time";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $massager_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>My Assigned Bookings</h2>

<?php while($b = $result->fetch_assoc()): ?>
<p>
👤 <?= $b['customer_name'] ?> |
📅 <?= $b['booking_date'] ?> |
⏰ <?= $b['start_time'] ?> - <?= $b['end_time'] ?> |
📌 <?= $b['status'] ?> |
💳 <?= $b['payment_status'] ?>
</p>

<?php if ($b['status'] == 'approved' && $b['payment_status'] == 'paid'): ?>
<form action="update_status.php" method="POST">
  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
  <button>Mark as Completed</button>
</form>
<?php endif; ?>

<hr>
<?php endwhile; ?>
