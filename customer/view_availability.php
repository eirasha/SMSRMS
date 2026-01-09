<?php
session_start();
include '../config/db.php';

$service = $conn->prepare("SELECT duration FROM services WHERE id=?");
$service->bind_param("i", $service_id);
$service->execute();
$service_data = $service->get_result()->fetch_assoc();
$duration = $service_data['duration'];


$massager_id = $_GET['massager_id'];
$service_id  = $_GET['service_id'];

$sql = "SELECT * FROM availability 
        WHERE massager_id = ? 
        AND status = 'available'
        ORDER BY date, start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $massager_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Available Slots</h2>

<?php while($row = $result->fetch_assoc()): ?>
<form action="book_slot.php" method="POST">
  <input type="hidden" name="availability_id" value="<?= $row['id'] ?>">
  <input type="hidden" name="massager_id" value="<?= $row['massager_id'] ?>">
   <input type="hidden" name="service_id" value="<?= $service_id ?>">
  <p>
    📅 <?= $row['date'] ?>
    ⏰ <?= $row['start_time'] ?> - <?= $row['end_time'] ?>
    ⏱ <?= $duration ?> minutes
    <button type="submit">Book</button>
  </p>
</form>
<?php endwhile; ?>
