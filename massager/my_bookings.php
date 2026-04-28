<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* =========================
   SECURITY CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];
$success = $error = "";

/* =========================
   HANDLE STATUS UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['booking_id']) && isset($_POST['status'])) {
    $booking_id = (int) $_POST['booking_id'];
    $status = $_POST['status'];

    $valid_status = ['pending', 'approved', 'completed', 'cancelled'];
    if (!in_array($status, $valid_status)) {
        $error = "Invalid status selected.";
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=? AND massager_id=?");
        $stmt->execute([$status, $booking_id, $massager_id]);
        $success = "Booking status updated!";
    }
}

/* =========================
   FETCH BOOKINGS ASSIGNED TO THIS MASSAGER
========================= */
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, c.username AS customer_name
    FROM bookings b
    JOIN users c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ?
    ORDER BY b.booking_date ASC, b.booking_time ASC
");
$stmt->execute([$massager_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Bookings</title>
<link rel="stylesheet" href="../css/massager.css">
</head>
<body>

<header class="header">
    <h1>My Booking Sessions 💆‍♂️</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">Manage Bookings</a>
        <a href="feedback.php">Feedback</a>
        <a href="availability.php">Update Availability</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

<?php if($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if($success): ?>
    <p class="success-msg"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php if(empty($bookings)): ?>
    <p class="info-msg">No bookings assigned yet.</p>
<?php else: ?>
    <div class="booking-cards">
        <?php 
        $today = date('Y-m-d');
        foreach($bookings as $b):
            $highlight = ($b['booking_date'] == $today) ? "highlight" : "";
        ?>
        <div class="card booking-card <?= $highlight ?>">
            <strong>Customer:</strong> <?= htmlspecialchars($b['customer_name']) ?><br>
            <strong>Service:</strong> <?= htmlspecialchars($b['service_name']) ?><br>
            <strong>Date:</strong> <?= $b['booking_date'] ?><br>
            <strong>Time:</strong> <?= $b['booking_time'] ?><br>
            <strong>Status:</strong> 
            <span class="status <?= strtolower($b['status']) ?>"><?= ucfirst($b['status']) ?></span>

            <form method="post" class="status-form">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <select name="status">
                    <?php 
                    $statuses = ['pending','approved','completed','cancelled'];
                    foreach($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $b['status']==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Update</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

</body>
</html>
