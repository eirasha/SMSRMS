<?php
session_start();
include '../config/db.php';

$customer_id = $_SESSION['user_id'];

$sql = "SELECT b.*, u.name AS massager_name
        FROM bookings b
        JOIN users u ON b.massager_id = u.id
        WHERE b.customer_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>My Bookings</h2>

<?php while($b = $result->fetch_assoc()): ?>
<p>
🧑‍💆 <?= $b['massager_name'] ?> |
📅 <?= $b['booking_date'] ?> |
⏰ <?= $b['start_time'] ?> - <?= $b['end_time'] ?> |
📌 <?= $b['status'] ?> |
💳 <?= $b['payment_status'] ?>
</p>

<?php if ($b['status'] == 'approved' && $b['payment_status'] == 'unpaid'): ?>
<form action="pay.php" method="POST">
  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
  <select name="payment_method">
    <option value="qr">QR</option>
    <option value="cash">Cash</option>
  </select>
  <button>Pay</button>
</form>
<?php endif; ?>

<?php
$fb = $conn->prepare("SELECT id FROM feedback WHERE booking_id=?");
$fb->bind_param("i", $b['id']);
$fb->execute();
$hasFeedback = $fb->get_result()->num_rows > 0;
?>

<?php if ($b['status'] == 'completed' && !$hasFeedback): ?>
  <a href="feedback.php?booking_id=<?= $b['id'] ?>">Give Feedback</a>
<?php endif; ?>

<hr>
<?php endwhile; ?>
