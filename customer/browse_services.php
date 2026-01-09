<?php
session_start();
include '../config/db.php';

$result = $conn->query("SELECT * FROM services");
?>

<h2>Browse Services</h2>

<?php while($s = $result->fetch_assoc()): ?>
  <div style="border:1px solid #ccc; padding:10px; margin:10px;">
    <h3><?= $s['service_name'] ?></h3>
    <p><?= $s['description'] ?></p>
    <p>RM <?= $s['price'] ?></p>
    <p> Duration: <?= $s['duration'] ?> minutes</p>


    <a href="choose_massager.php?service_id=<?= $s['id'] ?>">
      Choose Massager
    </a>
  </div>
<?php endwhile; ?>
