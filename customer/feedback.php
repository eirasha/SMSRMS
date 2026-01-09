<?php
session_start();
include '../config/db.php';

$booking_id = $_GET['booking_id'];
$customer_id = $_SESSION['user_id'];

/* Verify booking belongs to customer & completed */
$check = $conn->prepare("
  SELECT massager_id FROM bookings
  WHERE id=? AND customer_id=? AND status='completed'
");
$check->bind_param("ii", $booking_id, $customer_id);
$check->execute();
$booking = $check->get_result()->fetch_assoc();

if (!$booking) {
  die("Invalid feedback request.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $rating  = $_POST['rating'];
  $comment = $_POST['comment'];

  $insert = $conn->prepare("
    INSERT INTO feedback
    (booking_id, customer_id, massager_id, rating, comment)
    VALUES (?,?,?,?,?)
  ");

  $insert->bind_param(
    "iiiis",
    $booking_id,
    $customer_id,
    $booking['massager_id'],
    $rating,
    $comment
  );

  $insert->execute();
  header("Location: my_bookings.php");
  exit;
}
?>

<h2>Give Feedback</h2>

<form method="POST">
  <label>Rating (1–5)</label>
  <select name="rating" required>
    <option value="5">★★★★★</option>
    <option value="4">★★★★</option>
    <option value="3">★★★</option>
    <option value="2">★★</option>
    <option value="1">★</option>
  </select>

  <br><br>

  <textarea name="comment" placeholder="Write your feedback..."></textarea>
  <br><br>

  <button>Submit</button>
</form>
