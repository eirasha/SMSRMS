<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Ensure only logged-in customers can book
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_SESSION['user_id'];
    $service_id = $_POST['service_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $requested_massager = $_POST['massager_id']; // Can be an ID or the word 'any'
    
    $final_massager_id = null;

    try {
        // ==========================================
        // SCENARIO A: CUSTOMER CHOSE "ANY AVAILABLE"
        // ==========================================
        if ($requested_massager === 'any') {
            // Find a random massager who is completely free at this exact date and time
            $stmt = $conn->prepare("
                SELECT id FROM users 
                WHERE role = 'massager' AND status = 'active'
                AND id NOT IN (
                    SELECT massager_id FROM bookings 
                    WHERE booking_date = ? 
                    AND booking_time = ? 
                    AND status != 'cancelled'
                )
                ORDER BY RAND() LIMIT 1
            ");
            $stmt->execute([$booking_date, $booking_time]);
            
            if ($stmt->rowCount() == 0) {
                $_SESSION['error_msg'] = "Sorry, all our specialists are fully booked at that time. Please try another slot.";
                header("Location: ../customer/dashboard.php");
                exit;
            }
            $final_massager_id = $stmt->fetchColumn();

        } 
        // ==========================================
        // SCENARIO B: CUSTOMER CHOSE A SPECIFIC PERSON
        // ==========================================
        else {
            $final_massager_id = $requested_massager;
            // Check if THIS specific person is free
            $stmt = $conn->prepare("
                SELECT id FROM bookings 
                WHERE massager_id = ? AND booking_date = ? AND booking_time = ? AND status != 'cancelled'
            ");
            $stmt->execute([$final_massager_id, $booking_date, $booking_time]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error_msg'] = "Sorry, that specific specialist is already booked at that time.";
                header("Location: ../customer/dashboard.php");
                exit;
            }
        }

        // ==========================================
        // INSERT THE BOOKING
        // ==========================================
        $insert_stmt = $conn->prepare("
            INSERT INTO bookings (customer_id, service_id, massager_id, booking_date, booking_time, status, payment_status) 
            VALUES (?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $insert_stmt->execute([$customer_id, $service_id, $final_massager_id, $booking_date, $booking_time]);
        $new_booking_id = $conn->lastInsertId();

        // ==========================================
        // SEND NOTIFICATION TO THE ASSIGNED MASSAGER
        // ==========================================
        $customer_name = $_SESSION['username'];
        $alert_message = "New Booking! $customer_name has booked a slot on $booking_date at " . date("h:i A", strtotime($booking_time));
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, booking_id, message) 
            VALUES (?, ?, ?)
        ");
        $notif_stmt->execute([$final_massager_id, $new_booking_id, $alert_message]);

        // ==========================================
        // SUCCESS REDIRECT
        // ==========================================
        $_SESSION['success_msg'] = "Booking successful! Your specialist has been notified.";
        header("Location: ../customer/dashboard.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "System Error: " . $e->getMessage();
        header("Location: ../customer/dashboard.php");
        exit;
    }
} else {
    header("Location: ../customer/dashboard.php");
    exit;
}
?>