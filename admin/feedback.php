<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'admin') {
  header("Location: ../index.php");
  exit;
}

$result = $conn->query("
  SELECT f.*, c.name AS customer, m.name AS massager
  FROM feedback f
  JOIN users c ON f.customer_id = c.id
  JOIN users m ON f.massager_id = m.id
  ORDER BY f.created_at DESC
");
?>

<h2>All Feedback</h2>

<?php while($f = $result->fetch_assoc()): ?>
<p>
👤 <?= $f['customer'] ?> → 🧑‍💆 <?= $f['massager'] ?>  
⭐ <?= $f['rating'] ?>/5  
<br>
<?= $f['comment'] ?>
</p>
<hr>
<?php endwhile; ?>
