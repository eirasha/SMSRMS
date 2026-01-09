<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'massager') {
  header("Location: ../index.php");
  exit;
}

$booking_id = $_POST['booking_id'];
$massager_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
  UPDATE bookings
  SET status='completed'
  WHERE id=? AND massager_id=?
");

$stmt->bind_param("ii", $booking_id, $massager_id);
$stmt->execute();

header("Location: dashboard.php");
