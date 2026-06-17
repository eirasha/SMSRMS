<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kuala_Lumpur');

$date        = trim($_GET['date'] ?? '');
$massager_id = isset($_GET['massager_id']) ? (int)$_GET['massager_id'] : 0;

if (empty($date) || $massager_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing date or therapist parameter.']);
    exit;
}

$master_slots = [
    '09:00:00' => '09:00 AM',
    '11:00:00' => '11:00 AM',
    '14:00:00' => '02:00 PM',
    '16:00:00' => '04:00 PM'
];

$now = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));

try {
    $stmt = $conn->prepare("SELECT user_id FROM massagers WHERE id = ? AND status = 1");
    $stmt->execute([$massager_id]);
    $massager = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$massager) {
        echo json_encode(['status' => 'error', 'message' => 'Selected therapist is not available.']);
        exit;
    }

    $massager_user_id = $massager['user_id'];

    $stmtBlocked = $conn->prepare("
        SELECT available_start FROM massager_availability
        WHERE massager_id = ? AND available_date = ? AND slot_type = 'blocked'
    ");
    $stmtBlocked->execute([$massager_user_id, $date]);
    $blocked_times = $stmtBlocked->fetchAll(PDO::FETCH_COLUMN);

    $stmtTaken = $conn->prepare("
        SELECT booking_time FROM bookings
        WHERE booking_date = ? AND massager_id = ?
          AND status != 'cancelled'
          AND payment_status NOT IN ('failed', 'refunded')
    ");
    $stmtTaken->execute([$date, $massager_user_id]);
    $taken_times = $stmtTaken->fetchAll(PDO::FETCH_COLUMN);

    $response_slots = [];
    foreach ($master_slots as $time_24 => $time_12) {
        $slot_dt = new DateTime($date . ' ' . $time_24, new DateTimeZone('Asia/Kuala_Lumpur'));
        $is_past    = $slot_dt < $now;
        $is_taken   = in_array($time_24, $taken_times);
        $is_blocked = in_array($time_24, $blocked_times);

        $response_slots[] = [
            'time_24'      => $time_24,
            'time_12'      => $time_12,
            'is_available' => !$is_past && !$is_taken && !$is_blocked,
            'reason'       => $is_past ? 'past' : ($is_taken ? 'booked' : ($is_blocked ? 'blocked' : null))
        ];
    }

    echo json_encode(['status' => 'success', 'slots' => $response_slots]);

} catch (PDOException $e) {
    error_log("get_synced_slots.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
}