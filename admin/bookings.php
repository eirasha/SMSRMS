<?php
session_start();
include '../config/db.php';

$result = $conn->query("
  SELECT b.*, u.name AS customer, m.name AS massager
  FROM bookings b
  JOIN users u ON b.customer_id = u.id
  JOIN users m ON b.massager_id = m.id
  ORDER BY b.created_at DESC
");
?>

<h2>Manage Bookings</h2>

<?php while($b = $result->fetch_assoc()): ?>
  
  

<p>
👤 <?= $b['customer'] ?>
🧑‍💆 <?= $b['massager'] ?>
📅 <?= $b['booking_date'] ?>
⏰ <?= $b['start_time'] ?> - <?= $b['end_time'] ?>
📌 <?= $b['status'] ?>

<?php if ($b['payment_status'] == 'unpaid' && $b['status'] == 'approved'): ?>
  <a href="update_payment.php?id=<?= $b['id'] ?>">
    Confirm Payment
  </a>
<?php endif; ?>

<a href="update_booking.php?id=<?= $b['id'] ?>&action=approve">Approve</a>
<a href="update_booking.php?id=<?= $b['id'] ?>&action=cancel">Cancel</a>
</p>
<?php endwhile; ?>
