<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

/* =========================
   HANDLE NEW FEEDBACK SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['rating'], $_POST['comment'])) {
    $booking_id = (int) $_POST['booking_id'];
    $rating = (int) $_POST['rating'];
    $comment = trim($_POST['comment']);
    $is_flagged = preg_match('/(bad|terrible|awful)/i', $comment) ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO feedback (booking_id, customer_id, rating, comment, is_flagged)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$booking_id, $customer_id, $rating, $comment, $is_flagged]);

    header("Location: feedback.php");
    exit;
}

/* =========================
   FETCH COMPLETED BOOKINGS WITHOUT FEEDBACK
========================= */
$stmt = $conn->prepare("
    SELECT b.id, s.name AS service_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN feedback f ON f.booking_id = b.id
    WHERE b.customer_id = ? AND b.status = 'completed' AND f.id IS NULL
");
$stmt->execute([$customer_id]);
$pendingFeedbackBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH CUSTOMER FEEDBACK WITH REPLIES
========================= */
$stmt2 = $conn->prepare("
    SELECT f.*, s.name AS service_name
    FROM feedback f
    JOIN bookings b ON f.booking_id = b.id
    JOIN services s ON b.service_id = s.id
    WHERE f.customer_id = ?
    ORDER BY f.created_at DESC
");
$stmt2->execute([$customer_id]);
$feedbacks = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Feedback</title>
<link rel="stylesheet" href="../css/cust.css">
</head>
<body>

<header class="header">
    <h1>My Feedback 📝</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="book_service.php">Book Service</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="payment.php">Payments</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

    <!-- Submit Feedback Form -->
    <?php if (!empty($pendingFeedbackBookings)): ?>
    <div class="card">
        <h3>Submit Feedback</h3>
        <form method="post">
            <label for="booking_id">Select Booking:</label>
            <select name="booking_id" id="booking_id" required>
                <option value="">--Select Booking--</option>
                <?php foreach ($pendingFeedbackBookings as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['service_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="rating">Rating:</label>
            <select name="rating" id="rating" required>
                <option value="">--Rate--</option>
                <?php for ($i=1;$i<=5;$i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                <?php endfor; ?>
            </select>

            <label for="comment">Comment:</label>
            <textarea name="comment" id="comment" required placeholder="Write your feedback..."></textarea>

            <button type="submit" class="btn">Submit Feedback</button>
        </form>
    </div>
    <?php else: ?>
        <p class="info-msg">No completed bookings pending feedback.</p>
    <?php endif; ?>

    <hr>

    <!-- My Feedback -->
    <h3>My Feedback History</h3>
    <?php if (empty($feedbacks)): ?>
        <p class="info-msg">You have not submitted any feedback yet.</p>
    <?php else: ?>
        <?php foreach ($feedbacks as $f): ?>
            <div class="card feedback-card">
                <strong>Service:</strong> <?= htmlspecialchars($f['service_name']) ?><br>
                <strong>Rating:</strong> ⭐ <?= $f['rating'] ?>/5
                <?php if ($f['is_flagged']): ?>
                    <span class="flagged"> 🚩 Flagged</span>
                <?php endif; ?>
                <p><strong>Comment:</strong><br><?= nl2br(htmlspecialchars($f['comment'])) ?></p>

                <!-- FETCH REPLIES -->
                <?php
                $replyStmt = $conn->prepare("
                    SELECT r.*, u.username
                    FROM feedback_replies r
                    JOIN users u ON r.replied_by = u.id
                    WHERE r.feedback_id = ?
                    ORDER BY r.created_at ASC
                ");
                $replyStmt->execute([$f['id']]);
                $replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="replies">
                    <strong>Replies:</strong>
                    <?php if (empty($replies)): ?>
                        <em>No replies yet.</em>
                    <?php else: ?>
                        <?php foreach ($replies as $r): ?>
                            <div class="reply">
                                <strong><?= ucfirst($r['role']) ?> (<?= htmlspecialchars($r['username']) ?>):</strong>
                                <p><?= nl2br(htmlspecialchars($r['reply'])) ?></p>
                                <small><?= $r['created_at'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
