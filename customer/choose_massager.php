<?php
session_start();
include '../config/db.php';

$service_id = $_GET['service_id'];

$sql = "SELECT id, name FROM users WHERE role='massager'";
$result = $conn->query($sql);
?>

<h2>Choose Massager</h2>

<?php while($m = $result->fetch_assoc()): ?>
  <p>
    🧑‍💆 <?= $m['name'] ?>
    <a href="view_availability.php?massager_id=<?= $m['id'] ?>&service_id=<?= $service_id ?>">
      View Availability
    </a>
  </p>
<?php endwhile; ?>
