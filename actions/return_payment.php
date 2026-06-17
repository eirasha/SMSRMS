<?php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$user_id    = $_SESSION['user_id'];

if ($booking_id <= 0) {
    header("Location: ../customer/calendar.php?error=missing_booking");
    exit;
}

$payment_cleared = false;

try {
    // Only READ - never write here
    $stmt = $conn->prepare("
        SELECT payment_status 
        FROM bookings 
        WHERE id = ? AND customer_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // Only trust the database, NOT the GET parameter
    if ($booking && $booking['payment_status'] === 'paid') {
        $payment_cleared = true;
    }

} catch (PDOException $e) {
    error_log("Return page DB error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Result | Sunflower</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background: #fff9e6; }</style>
</head>
<body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isCleared = <?= $payment_cleared ? 'true' : 'false' ?>;

    if (isCleared) {
        Swal.fire({
            icon: 'success',
            title: 'Payment Successful!',
            text: 'Your appointment is confirmed and ready.',
            confirmButtonColor: '#f4d03f'
        }).then(() => {
            window.location.href = '../customer/calendar.php';
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Transaction Declined',
            text: 'Payment could not be processed. Please try again.',
            confirmButtonColor: '#ef4444'
        }).then(() => {
            window.location.href = '../customer/calendar.php';
        });
    }
});
</script>
</body>
</html>