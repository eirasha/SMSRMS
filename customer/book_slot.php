<?php
session_start();
include '../config/db.php';

$customer_id     = $_SESSION['user_id'];
$availability_id = $_POST['availability_id'];
$massager_id     = $_POST['massager_id'];
$service_id      = $_POST['service_id'];

/* 1️⃣ Check availability */
$check = $conn->prepare(
  "SELECT * FROM availability WHERE id=? AND status='available'"
);
$check->bind_param("i", $availability_id);
$check->execute();
$slot = $check->get_result()->fetch_assoc();

if (!$slot) {
  die("Slot unavailable.");
}

/* 2️⃣ Calculate slot duration */
$start = strtotime($slot['start_time']);
$end   = strtotime($slot['end_time']);
$slot_minutes = ($end - $start) / 60;

/* 3️⃣ Get service duration */
$svc = $conn->prepare("SELECT duration FROM services WHERE id=?");
$svc->bind_param("i", $service_id);
$svc->execute();
$duration = $svc->get_result()->fetch_assoc()['duration'];

/* 4️⃣ VALIDATION (must be here) */
if ($duration > $slot_minutes) {
  die("Service duration exceeds selected time slot.");
}

/* 5️⃣ Transaction starts AFTER validation */
$conn->begin_transaction();

try {
  $insert = $conn->prepare(
    "INSERT INTO bookings
     (customer_id, massager_id, availability_id, service_id,
      booking_date, start_time, end_time)
     VALUES (?,?,?,?,?,?,?)"
  );

  $insert->bind_param(
    "iiiisss",
    $customer_id,
    $massager_id,
    $availability_id,
    $service_id,
    $slot['date'],
    $slot['start_time'],
    $slot['end_time']
  );

  $insert->execute();

  $update = $conn->prepare(
    "UPDATE availability SET status='booked' WHERE id=?"
  );
  $update->bind_param("i", $availability_id);
  $update->execute();

  $conn->commit();
  header("Location: my_bookings.php");
  exit;

} catch (Exception $e) {
  $conn->rollback();
  echo "Booking failed.";
}
