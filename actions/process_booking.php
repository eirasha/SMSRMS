<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Input
$customer_id  = $_SESSION['user_id'];
$service_id   = isset($_POST['service_id'])   ? (int)$_POST['service_id']        : 0;
$massager_id  = isset($_POST['massager_id'])  ? (int)$_POST['massager_id']       : 0;
$booking_date = isset($_POST['booking_date']) ? trim($_POST['booking_date'])      : '';
$booking_time = isset($_POST['booking_time']) ? trim($_POST['booking_time'])      : '';

if (!$service_id || !$massager_id || empty($booking_date) || empty($booking_time)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required booking fields.']);
    exit;
}

// Resolve massager user_id
try {
    $stmt = $conn->prepare("SELECT user_id FROM massagers WHERE id = ? AND status = 1");
    $stmt->execute([$massager_id]);
    $massager = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$massager) {
        echo json_encode(['status' => 'error', 'message' => 'Selected therapist is not available.']);
        exit;
    }

    $massager_user_id = $massager['user_id'];

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error validating therapist.']);
    exit;
}

// Reject past date/time bookings
date_default_timezone_set('Asia/Kuala_Lumpur');
$now     = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$slot_dt = new DateTime($booking_date . ' ' . $booking_time, new DateTimeZone('Asia/Kuala_Lumpur'));

if ($slot_dt < $now) {
    echo json_encode(['status' => 'error', 'message' => 'This time slot has already passed.']);
    exit;
}

// Handle optional receipt upload
$receipt_filename = null;

if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/receipts/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        $receipt_filename = 'REC_' . time() . '_' . $customer_id . '.' . $ext;

        if (!move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $upload_dir . $receipt_filename)) {
            $receipt_filename = null;
        }
    }
}

// Booking transaction
try {
    $conn->beginTransaction();

    // Expire abandoned unpaid bookings older than 15 minutes
    $conn->prepare("
        UPDATE bookings
        SET status = 'cancelled', payment_status = 'failed'
        WHERE payment_status = 'unpaid'
          AND status = 'pending'
          AND created_at < NOW() - INTERVAL 15 MINUTE
    ")->execute();

    // Check slot availability
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE booking_date    = ?
          AND booking_time    = ?
          AND massager_id     = ?
          AND status         != 'cancelled'
          AND payment_status != 'failed'
    ");
    $stmt->execute([$booking_date, $booking_time, $massager_user_id]);

    if ($stmt->fetchColumn() > 0) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This slot was just taken. Please select another.']);
        exit;
    }

    // Insert booking
    $payment_status = $receipt_filename ? 'pending_verification' : 'unpaid';

    $stmt = $conn->prepare("
        INSERT INTO bookings
            (customer_id, service_id, massager_id, booking_date, booking_time, status, payment_status, receipt_image, created_at)
        VALUES
            (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
    ");

    if ($stmt->execute([$customer_id, $service_id, $massager_user_id, $booking_date, $booking_time, $payment_status, $receipt_filename])) {
        $new_booking_id = $conn->lastInsertId();
        error_log("process_booking: new_booking_id=" . $new_booking_id);
        $conn->commit();
        echo json_encode([
            'status'     => 'success',
            'message'    => 'Appointment slot secured.',
            'booking_id' => $new_booking_id,
        ]);
    } else {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save booking.']);
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Please try again.']);
}