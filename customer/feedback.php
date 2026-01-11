<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch completed bookings without feedback
$stmt = $conn->prepare("
    SELECT b.id AS booking_id, s.name AS service_name, m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users m ON b.massager_id = m.id
    WHERE b.customer_id = ? AND b.status='completed' 
    AND NOT EXISTS (SELECT 1 FROM feedback f WHERE f.booking_id = b.id)
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $stmt = $conn->prepare("INSERT INTO feedback (booking_id, rating, comment) VALUES (?, ?, ?)");
    if ($stmt->execute([$booking_id, $rating, $comment])) {
        $success = "Feedback submitted successfully!";
    } else {
        $error = "Failed to submit feedback.";
    }
}
?>

<h2>Leave Feedback</h2>

<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

<?php if(count($bookings) == 0): ?>
    <p>No completed bookings available for feedback.</p>
<?php else: ?>
    <?php foreach($bookings as $b): ?>
        <form method="post" style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">
            <strong><?php echo htmlspecialchars($b['service_name']); ?></strong> by <?php echo htmlspecialchars($b['massager_name']); ?><br>
            Rating: 
            <select name="rating" required>
                <option value="">--Select--</option>
                <?php for($i=1;$i<=5;$i++) echo "<option value='$i'>$i</option>"; ?>
            </select><br>
            Comment:<br>
            <textarea name="comment" required></textarea><br><br>
            <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
            <button type="submit">Submit Feedback</button>
        </form>
    <?php endforeach; ?>
<?php endif; ?>
