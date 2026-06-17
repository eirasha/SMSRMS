<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    echo json_encode([]);
    exit;
}

$massager_id = $_SESSION['user_id'];
$start       = trim($_GET['start'] ?? '');
$end         = trim($_GET['end'] ?? '');

// Fallback to current month if not provided
if (empty($start)) $start = date('Y-m-01');
if (empty($end))   $end   = date('Y-m-t');

// Strip timezone offset if present (FullCalendar sends ISO 8601)
$start = substr($start, 0, 10);
$end   = substr($end, 0, 10);

$events = [];

try {
    // 1. Fetch bookings
    $stmt = $conn->prepare("
        SELECT b.id, b.booking_date, b.booking_time, b.status, b.payment_status,
               s.name AS service_name,
               u.username AS customer_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.massager_id = ?
          AND b.booking_date BETWEEN ? AND ?
          AND b.status != 'cancelled'
        ORDER BY b.booking_date ASC, b.booking_time ASC
    ");
    $stmt->execute([$massager_id, $start, $end]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $b) {
        // Build start datetime
        $start_dt = $b['booking_date'] . 'T' . $b['booking_time'];
        // Assume 1 hour session
        $end_dt   = $b['booking_date'] . 'T' . date('H:i:s', strtotime($b['booking_time']) + 3600);

        // Color by status
        $color = match($b['status']) {
            'completed' => '#10b981',
            'approved'  => '#3b82f6',
            default     => '#f59e0b'
        };

        $events[] = [
            'id'    => 'booking_' . $b['id'],
            'title' => date('g:i A', strtotime($b['booking_time'])) . ' · ' . $b['customer_name'],
            'start' => $start_dt,
            'end'   => $end_dt,
            'color' => $color,
            'extendedProps' => [
                'customer'       => $b['customer_name'],
                'service'        => $b['service_name'],
                'status'         => ucfirst($b['status']),
                'payment'        => ucfirst($b['payment_status']),
            ]
        ];
    }

    // 2. Fetch blocked slots
    $stmtBlocked = $conn->prepare("
        SELECT available_date, available_start, available_end
        FROM massager_availability
        WHERE massager_id = ?
          AND available_date BETWEEN ? AND ?
          AND slot_type = 'blocked'
    ");
    $stmtBlocked->execute([$massager_id, $start, $end]);
    $blocked = $stmtBlocked->fetchAll(PDO::FETCH_ASSOC);

    foreach ($blocked as $b) {
        $events[] = [
            'id'    => 'blocked_' . $b['available_date'] . '_' . $b['available_start'],
            'title' => 'Blocked · ' . date('g:i A', strtotime($b['available_start'])),
            'start' => $b['available_date'] . 'T' . $b['available_start'],
            'end'   => $b['available_date'] . 'T' . $b['available_end'],
            'color' => '#ef4444',
            'extendedProps' => ['type' => 'blocked']
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    error_log("api_calendar_events.php error: " . $e->getMessage());
    echo json_encode([]);
}