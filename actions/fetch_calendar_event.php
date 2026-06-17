<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kuala_Lumpur');

session_start();

try {
    // 1. Fetch only confirmed paid bookings to display on the customer's overview
    $stmt = $conn->query("
        SELECT id, booking_date, status, payment_status 
        FROM bookings 
        WHERE payment_status = 'paid' 
        AND status != 'cancelled'
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    
    // Group your database entries by date to check for shop capacity thresholds
    $date_counts = [];
    foreach ($bookings as $b) {
        $date_only = date('Y-m-d', strtotime($b['booking_date']));
        if (!isset($date_counts[$date_only])) {
            $date_counts[$date_only] = 0;
        }
        $date_counts[$date_only]++;
    }

    // 2. Mark fully booked days as background overlay
    foreach ($date_counts as $date => $count) {
        if ($count >= 4) {
            $events[] = [
                'start'     => $date,
                'display'   => 'background',
                'className' => 'is-fully-booked',
                'extendedProps' => ['type' => 'full']
            ];
        }
    }

    // 3. Show the logged-in customer's own booked slot on the calendar
    if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
        $customer_id = (int) $_SESSION['user_id'];

        $stmt_my = $conn->prepare("
            SELECT b.booking_date, b.booking_time, b.status, b.payment_status, s.name AS service_name
            FROM bookings b
            JOIN services s ON b.service_id = s.id
            WHERE b.customer_id = ?
              AND b.status NOT IN ('cancelled')
              AND b.payment_status NOT IN ('failed')
            ORDER BY b.booking_date ASC, b.booking_time ASC
        ");
        $stmt_my->execute([$customer_id]);
        $my_bookings = $stmt_my->fetchAll(PDO::FETCH_ASSOC);

        foreach ($my_bookings as $mb) {
            $date_str = date('Y-m-d', strtotime($mb['booking_date']));
            $time_12  = date('g:i A', strtotime($mb['booking_time']));

            $events[] = [
                'title'     => '📌 Booked · ' . $time_12,
                'start'     => $date_str,
                'className' => 'my-booking-event',
                'extendedProps' => [
                    'type'    => 'my_booking',
                    'service' => $mb['service_name'],
                    'time'    => $time_12,
                    'status'  => $mb['status'],
                    'payment_status' => $mb['payment_status'],
                ]
            ];
        }
    }

    echo json_encode($events);

} catch (PDOException $e) {
    echo json_encode([]);
}