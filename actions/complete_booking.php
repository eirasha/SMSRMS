<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Security token expired. Please refresh the page.']);
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$massager_id = $_SESSION['user_id'];

if (!$booking_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID.']);
    exit;
}

try {
    // Verify booking
    $stmt = $conn->prepare("
        SELECT id, status, payment_status 
        FROM bookings 
        WHERE id = ? AND massager_id = ?
    ");
    $stmt->execute([$booking_id, $massager_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['status' => 'error', 'message' => 'Booking not found or does not belong to you.']);
        exit;
    }

    if ($booking['status'] === 'completed') {
        echo json_encode(['status' => 'error', 'message' => 'This booking is already marked as completed.']);
        exit;
    }

    if ($booking['status'] === 'cancelled') {
        echo json_encode(['status' => 'error', 'message' => 'Cannot complete a cancelled booking.']);
        exit;
    }

    if ($booking['payment_status'] !== 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'Cannot complete a booking that has not been paid.']);
        exit;
    }

    // Update booking - WITHOUT completed_at
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'completed' 
        WHERE id = ? AND massager_id = ?
    ");
    $stmt->execute([$booking_id, $massager_id]);

    echo json_encode(['status' => 'success', 'message' => 'Session marked as completed successfully!']);

} catch (PDOException $e) {
    error_log("complete_booking.php error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}