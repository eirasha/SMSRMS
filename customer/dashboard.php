<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Please log in first.");
}

$customer_id = $_SESSION['user_id'];
?>

<h1>Welcome, Customer</h1>

<div>
    <a href="book_slot.php">Book Slot</a> |
    <a href="my_bookings.php">My Bookings</a> |
    <a href="payment.php">Payment</a> |
    <a href="feedback.php">Feedback</a>
</div>

<h2>Available Slots</h2>
<?php
$sql = "SELECT a.id AS availability_id, m.id AS massager_id, s.id AS service_id,
               s.name AS service_name, m.name AS massager_name, a.date, a.start_time, a.end_time
        FROM availability a
        JOIN massagers m ON a.massager_id = m.id
        JOIN services s ON a.service_id = s.id
        WHERE a.status='available'";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "<div>";
    echo "{$row['service_name']} with {$row['massager_name']} on {$row['date']} {$row['start_time']}-{$row['end_time']}";
    echo " <a href='book_slot.php?availability_id={$row['availability_id']}&massager_id={$row['massager_id']}&service_id={$row['service_id']}'>Book Slot</a>";
    echo "</div>";
}
?>
