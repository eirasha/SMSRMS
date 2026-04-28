<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$success = $error = "";

/* FETCH SERVICES AND MASSAGERS */
$services = $conn->query("SELECT * FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$massagers = $conn->query("SELECT id, username FROM users WHERE role='massager' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

/* FETCH AVAILABLE SLOTS */
$available_slots = [];
if (!empty($_POST['massager_id']) && !empty($_POST['date'])) {
    $massager_id = (int)$_POST['massager_id'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("SELECT available_start, available_end FROM massager_availability WHERE massager_id=? AND available_date=? ORDER BY available_start");
    $stmt->execute([$massager_id, $date]);
    $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($availabilities as $a) {
        $start = strtotime($a['available_start']);
        $end = strtotime($a['available_end']);
        for ($t=$start; $t+3600 <= $end; $t+=3600) {
            $slot_time = date('H:i', $t);
            $stmt2 = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE massager_id=? AND booking_date=? AND booking_time=? AND status IN ('pending','approved')");
            $stmt2->execute([$massager_id, $date, $slot_time]);
            if ($stmt2->fetchColumn() == 0) $available_slots[] = $slot_time;
        }
    }
}

/* HANDLE BOOKING */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['service_id']) && !empty($_POST['massager_id']) && !empty($_POST['date']) && !empty($_POST['time'])) {
    $service_id = (int)$_POST['service_id'];
    $massager_id = (int)$_POST['massager_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE massager_id=? AND booking_date=? AND booking_time=? AND status IN ('pending','approved')");
    $stmt->execute([$massager_id, $date, $time]);

    if ($stmt->fetchColumn() > 0) {
        $error = "Sorry, this slot has just been booked by another customer.";
    } else {
        $stmt = $conn->prepare("INSERT INTO bookings (customer_id, service_id, massager_id, booking_date, booking_time, status, payment_status) VALUES (?, ?, ?, ?, ?, 'pending', 'pending')");
        $stmt->execute([$customer_id, $service_id, $massager_id, $date, $time]);
        $success = "Booking successful!";
        $available_slots = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book a Service</title>
<link rel="stylesheet" href="../css/cust.css">
</head>
<body>

<header class="header">
    <h1>Book a Service </h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">
    <?php if ($error) echo "<p class='msg error'>$error</p>"; ?>
    <?php if ($success) echo "<p class='msg success'>$success</p>"; ?>

    <form method="post">
        <label>Service:</label>
        <select name="service_id" required>
            <option value="">--Select Service--</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> - RM<?= $s['price'] ?></option>
            <?php endforeach; ?>
        </select>

        <label>Massager:</label>
        <select name="massager_id" required onchange="this.form.submit()">
            <option value="">--Select Massager--</option>
            <?php foreach ($massagers as $m): ?>
                <option value="<?= $m['id'] ?>" <?= (!empty($_POST['massager_id']) && $_POST['massager_id']==$m['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($m['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Date:</label>
        <input type="date" name="date" value="<?= $_POST['date'] ?? '' ?>" required onchange="this.form.submit()">

        <?php if (!empty($available_slots)): ?>
            <label>Available Slots:</label>
            <select name="time" required>
                <option value="">--Select Time--</option>
                <?php foreach ($available_slots as $slot): ?>
                    <option value="<?= $slot ?>"><?= $slot ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Book Now</button>
        <?php elseif (!empty($_POST['massager_id']) && !empty($_POST['date'])): ?>
            <p class="msg error">No available slots for this massager on this date.</p>
        <?php endif; ?>
    </form>
</div>

</body>
</html>
