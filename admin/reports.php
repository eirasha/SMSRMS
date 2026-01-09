<?php
session_start();
include '../config/db.php';

if ($_SESSION['role'] != 'admin') {
  header("Location: ../index.php");
  exit;
}

/* Total bookings */
$totalBookings = $conn->query(
  "SELECT COUNT(*) AS total FROM bookings"
)->fetch_assoc()['total'];

/* Total revenue (paid only) */
$totalRevenue = $conn->query(
  "SELECT SUM(s.price) AS revenue
   FROM bookings b
   JOIN services s ON b.service_id = s.id
   WHERE b.payment_status='paid'"
)->fetch_assoc()['revenue'];
?>
