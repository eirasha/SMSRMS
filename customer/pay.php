<?php
include '../config/db.php';

$id = $_POST['booking_id'];
$method = $_POST['payment_method'];

$conn->query("
  UPDATE bookings 
  SET payment_method='$method'
  WHERE id=$id
");

header("Location: my_bookings.php");
