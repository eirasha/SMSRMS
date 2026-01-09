<?php
session_start();
include "../config/db.php";

if ($_SESSION['role'] != 'massager') {
  header("Location: ../index.php");
  exit;
}
?>

<h2>Set Availability</h2>

<form method="POST">
  Date:
  <input type="date" name="date" required><br><br>

  Start Time:
  <input type="time" name="start_time" required><br><br>

  End Time:
  <input type="time" name="end_time" required><br><br>

  <button type="submit">Save</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  $massager_id = $_SESSION['user_id'];
  $date = $_POST['date'];
  $start = $_POST['start_time'];
  $end = $_POST['end_time'];

  // Check overlap
  $check = mysqli_query($conn,
    "SELECT * FROM availability
     WHERE massager_id = '$massager_id'
     AND date = '$date'
     AND (
          (start_time <= '$start' AND end_time > '$start')
          OR
          (start_time < '$end' AND end_time >= '$end')
          OR
          (start_time >= '$start' AND end_time <= '$end')
         )"
  );

  if (mysqli_num_rows($check) > 0) {
      echo "❌ Time slot overlaps with existing availability";
  } else {
      mysqli_query($conn,
        "INSERT INTO availability (massager_id, date, start_time, end_time)
         VALUES ('$massager_id','$date','$start','$end')"
      );
      echo "✅ Availability saved";
  }
}

?>
