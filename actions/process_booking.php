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
    
    // Defaulting massager ID to 2 for now. 
    // If you have a dropdown in the modal for massagers, change this to: $_POST['massager_id']
    $massager_id = 2; 

    try {
        // ==========================================
        // 1. THE ANTI-OVERLAP CHECK
        // ==========================================
        $stmt = $conn->prepare("
            SELECT id FROM bookings 
            WHERE massager_id = ? 
            AND booking_date = ? 
            AND booking_time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$massager_id, $booking_date, $booking_time]);
        
        if ($stmt->rowCount() > 0) {
            // CONFLICT FOUND!
            $_SESSION['error_msg'] = "Sorry, that time slot is already taken. Please choose another time.";
            header("Location: ../customer/dashboard.php");
            exit;
        }

        // ==========================================
        // 2. INSERT THE BOOKING
        // ==========================================
        $insert_stmt = $conn->prepare("
            INSERT INTO bookings (customer_id, service_id, massager_id, booking_date, booking_time, status, payment_status) 
            VALUES (?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $insert_stmt->execute([$customer_id, $service_id, $massager_id, $booking_date, $booking_time]);
        
        $new_booking_id = $conn->lastInsertId();

        // ==========================================
        // 3. SEND NOTIFICATION TO THE MASSAGER
        // ==========================================
        $customer_name = $_SESSION['username'];
        $alert_message = "New Booking! $customer_name booked a slot on $booking_date at " . date("h:i A", strtotime($booking_time));
        
        // Make sure you ran the CREATE TABLE notifications query in phpMyAdmin!
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, booking_id, message) 
            VALUES (?, ?, ?)
        ");
        $notif_stmt->execute([$massager_id, $new_booking_id, $alert_message]);

        // ==========================================
        // 4. SUCCESS REDIRECT
        // ==========================================
        $_SESSION['success_msg'] = "Booking successful! The massager has been notified.";
        
        // Redirect back to dashboard (or you can change this to payment.php)
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