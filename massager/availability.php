<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* =========================
   SECURITY
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    exit('Unauthorized');
}

$massager_id = $_SESSION['user_id'];
$success = $error = "";

/* =========================
   DELETE AVAILABILITY
========================= */
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];
    
    $stmt = $conn->prepare("DELETE FROM massager_availability WHERE id=? AND massager_id=?");
    if ($stmt->execute([$delete_id, $massager_id])) {
        $success = "Availability slot deleted successfully!";
    } else {
        $error = "Failed to delete slot.";
    }
}

/* =========================
   ADD NEW AVAILABILITY
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !empty($_POST['date']) 
    && !empty($_POST['start_time']) 
    && !empty($_POST['end_time'])) {

    $date = $_POST['date'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    if ($start >= $end) {
        $error = "Start time must be before end time.";
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM massager_availability
            WHERE massager_id = ? AND available_date = ?
            AND (
                (available_start < ? AND available_end > ?) 
             OR (available_start < ? AND available_end > ?)
             OR (available_start >= ? AND available_end <= ?)
            )
        ");
        $stmt->execute([$massager_id, $date, $end, $end, $start, $start, $start, $end]);
        if ($stmt->fetchColumn() > 0) {
            $error = "This slot overlaps with an existing availability.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO massager_availability (massager_id, available_date, available_start, available_end)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$massager_id, $date, $start, $end]);
            $success = "Availability added successfully!";
        }
    }
}

/* =========================
   FETCH CURRENT AVAILABILITY
========================= */
$stmt = $conn->prepare("SELECT * FROM massager_availability WHERE massager_id=? ORDER BY available_date, available_start");
$stmt->execute([$massager_id]);
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Availability</title>
<link rel="stylesheet" href="../css/massager.css">
</head>
<body>

<header class="header">
    <h1>My Availability ⏰</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">Manage Bookings</a>
        <a href="feedback.php">Feedback</a>
        <a href="availability.php">Update Availability</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="success-msg"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<!-- Add Slot Form -->
<div class="card">
    <h3>Add New Availability</h3>
    <form method="post" class="availability-form">
        <label for="date">Date:</label>
        <input type="date" name="date" id="date" required>

        <label for="start_time">Start Time:</label>
        <input type="time" name="start_time" id="start_time" required>

        <label for="end_time">End Time:</label>
        <input type="time" name="end_time" id="end_time" required>

        <button type="submit" class="btn">Add Availability</button>
    </form>
</div>

<!-- Current Slots -->
<h3>Current Availability</h3>
<?php if (empty($slots)): ?>
    <p class="info-msg">No availability added yet.</p>
<?php else: ?>
    <?php foreach ($slots as $s): ?>
        <div class="card availability-card">
            <strong>Date:</strong> <?= htmlspecialchars($s['available_date']) ?><br>
            <strong>Time:</strong> <?= htmlspecialchars($s['available_start']) ?> - <?= htmlspecialchars($s['available_end']) ?><br>
            <a href="?delete_id=<?= $s['id'] ?>" class="delete-link" onclick="return confirm('Are you sure to delete this slot?')">Delete</a>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

</body>
</html>
