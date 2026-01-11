<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Only customer can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch available services
$services = $conn->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);

// Fetch available massagers
$massagers = $conn->query("SELECT * FROM users WHERE role='massager'")->fetchAll(PDO::FETCH_ASSOC);

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'];
    $massager_id = $_POST['massager_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $customer_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO bookings (customer_id, service_id, massager_id, booking_date, booking_time) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$customer_id, $service_id, $massager_id, $date, $time])) {
        $success = "Booking successful!";
    } else {
        $error = "Failed to book. Please try again.";
    }
}
?>

<h2>Book a Service</h2>

<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

<form method="post">
    Service: 
    <select name="service_id" required>
        <option value="">--Select Service--</option>
        <?php foreach($services as $s) {
            echo "<option value='{$s['id']}'>{$s['name']} - RM{$s['price']}</option>";
        } ?>
    </select><br>

    Massager:
    <select name="massager_id" required>
        <option value="">--Select Massager--</option>
        <?php foreach($massagers as $m) {
            echo "<option value='{$m['id']}'>{$m['username']}</option>";
        } ?>
    </select><br>

    Date: <input type="date" name="date" required><br>
    Time: <input type="time" name="time" required><br><br>

    <button type="submit">Book</button>
</form>
<nav>
    <a href="dashboard.php">Dashboard</a> | 
    <a href="my_bookings.php">My Bookings</a> | 
    <a href="../auth/logout.php">Logout</a>