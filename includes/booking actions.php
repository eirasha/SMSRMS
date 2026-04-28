<?php
/* =========================
   booking_actions.php
   Reusable booking management
========================= */

if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id || !in_array($role, ['admin','massager'])) {
    exit('Unauthorized access');
}

/* =========================
   HANDLE STATUS UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    $booking_id = (int) $_POST['booking_id'];
    $status = $_POST['status'];

    if ($role === 'admin') {
        // Admin can update any booking
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
        $stmt->execute([$status, $booking_id]);
    } elseif ($role === 'massager') {
        // Massager can only update their assigned bookings
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=? AND massager_id=?");
        $stmt->execute([$status, $booking_id, $user_id]);
    }
}

/* =========================
   FETCH BOOKINGS
========================= */
if ($role === 'admin') {
    $stmt = $conn->query("
        SELECT b.*, s.name AS service_name, 
               c.username AS customer_name, 
               m.username AS massager_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users c ON b.customer_id = c.id
        LEFT JOIN users m ON b.massager_id = m.id
        ORDER BY b.booking_date DESC, b.booking_time ASC
    ");
} elseif ($role === 'massager') {
    $stmt = $conn->prepare("
        SELECT b.*, s.name AS service_name, c.username AS customer_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users c ON b.customer_id = c.id
        WHERE b.massager_id = ?
        ORDER BY b.booking_date DESC, b.booking_time ASC
    ");
    $stmt->execute([$user_id]);
}

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
