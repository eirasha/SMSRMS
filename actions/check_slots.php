<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

if (empty($date)) {
    echo json_encode([]);
    exit;
}

try {
    // Only grab slots that have been officially paid for or approved
    $stmt = $conn->prepare("
        SELECT TIME(booking_date) as booked_time 
        FROM bookings 
        WHERE DATE(booking_date) = ? 
        AND payment_status = 'paid' 
        AND status != 'cancelled'
    ");
    $stmt->execute([$date]);
    $taken_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($taken_slots);
} catch (PDOException $e) {
    echo json_encode([]);
}