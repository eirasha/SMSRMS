<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'admin') {
  header("Location: ../index.php");
  exit;
}

$id = $_GET['id'];

$conn->query("
  UPDATE bookings
  SET payment_status = 'paid'
  WHERE id = $id
");

header("Location: bookings.php");
