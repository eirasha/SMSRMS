<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'massager') {
  header("Location: ../index.php");
  exit;
}

$massager_id = $_SESSION['user_id'];

$sql = "SELECT f.*, u.name AS customer_name
        FROM feedback f
        JOIN users u ON f.customer_id = u.id
        WHERE f.massager_id = ?
        ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $massager_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Customer Feedback</h2>

<?php while($f = $result->fetch_assoc()): ?>
<p>
👤 <?= $f['customer_name'] ?> |
⭐ <?= $f['rating'] ?>/5  
<br>
<?= $f['comment'] ?>
</p>
<hr>
<?php endwhile; ?>
