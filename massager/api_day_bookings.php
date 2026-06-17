<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$massager_id = $_SESSION['user_id'];
$date        = trim($_GET['date'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT b.id, b.booking_time, b.status, b.payment_status,
               s.name AS service_name, s.price,
               u.username AS customer_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.massager_id = ? AND b.booking_date = ?
          AND b.status != 'cancelled'
        ORDER BY b.booking_time ASC
    ");
    $stmt->execute([$massager_id, $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings = array_map(function($b) {
        // Format time to 12hr
        $b['time_12'] = date('g:i A', strtotime($b['booking_time']));
        $b['price']   = number_format($b['price'], 2);
        return $b;
    }, $rows);

    echo json_encode(['status' => 'success', 'bookings' => $bookings]);

} catch (PDOException $e) {
    error_log("api_day_bookings.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error.']);
}