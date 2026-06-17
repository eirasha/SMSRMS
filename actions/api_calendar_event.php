<?php
session_start();
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$massager_id = $_SESSION['user_id'];

// FullCalendar automatically sends 'start' and 'end' GET parameters
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-1 month'));
$end_date = $_GET['end'] ?? date('Y-m-d', strtotime('+2 months'));

$events = [];

try {
    // ---------------------------------------------------------
    // 1. FETCH CUSTOMER BOOKINGS
    // ---------------------------------------------------------
    $stmt_bookings = $conn->prepare("
        SELECT b.id, b.booking_date, b.booking_time, u.username as customer_name, s.name as service_name
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        JOIN services s ON b.service_id = s.id
        WHERE b.massager_id = ? 
          AND b.booking_date BETWEEN ? AND ?
          AND b.status NOT IN ('cancelled', 'rejected')
          AND b.payment_status NOT IN ('failed', 'refunded')
    ");
    
    // We extract just the YYYY-MM-DD part from FullCalendar's ISO strings
    $clean_start = substr($start_date, 0, 10);
    $clean_end = substr($end_date, 0, 10);
    
    $stmt_bookings->execute([$massager_id, $clean_start, $clean_end]);
    $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $row) {
        // Calculate an end time (assuming standard 1 hour session for the calendar view)
        $start_datetime = $row['booking_date'] . ' ' . $row['booking_time'];
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + 3600); // +1 hour

        $events[] = [
            'id'    => 'booking_' . $row['id'],
            'title' => $row['customer_name'] . ' (' . $row['service_name'] . ')',
            'start' => $start_datetime,
            'end'   => $end_datetime,
            'color' => '#3b82f6', // Blue for active bookings
            'textColor' => '#ffffff'
        ];
    }

    // ---------------------------------------------------------
    // 2. FETCH MANUAL BLOCKS (Availability)
    // ---------------------------------------------------------
    $stmt_blocks = $conn->prepare("
        SELECT id, available_date, available_start, available_end 
        FROM massager_availability 
        WHERE massager_id = ? 
          AND slot_type = 'blocked'
          AND available_date BETWEEN ? AND ?
    ");
    $stmt_blocks->execute([$massager_id, $clean_start, $clean_end]);
    $blocks = $stmt_blocks->fetchAll(PDO::FETCH_ASSOC);

    foreach ($blocks as $row) {
        // Fallback for end time if your DB doesn't store it accurately
        $end_time = $row['available_end'] ? $row['available_end'] : date('H:i:s', strtotime($row['available_start']) + 3600);
        
        $events[] = [
            'id'    => 'block_' . $row['id'],
            'title' => 'Blocked',
            'start' => $row['available_date'] . ' ' . $row['available_start'],
            'end'   => $row['available_date'] . ' ' . $end_time,
            'color' => '#ef4444', // Red for blocked slots
            'textColor' => '#ffffff'
        ];
    }

    // ---------------------------------------------------------
    // OUTPUT CLEAN JSON
    // ---------------------------------------------------------
    header('Content-Type: application/json');
    echo json_encode($events);
    exit;

} catch (PDOException $e) {
    // If the database crashes, return an empty array so the calendar doesn't break
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
?>