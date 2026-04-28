<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* SECURITY CHECK */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];

/* FETCH FEEDBACK RELATED TO MASSAGER BOOKINGS */
$stmt = $conn->prepare("
    SELECT f.*, s.name AS service_name, u.username AS customer_name
    FROM feedback f
    JOIN bookings b ON f.booking_id = b.id
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$massager_id]);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback Received</title>
<link rel="stylesheet" href="../css/massager.css">
</head>
<body>

<header class="header">
    <h1>Feedback Received 📝</h1>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_bookings.php">Manage Bookings</a>
        <a href="feedback.php">Feedback</a>
        <a href="availability.php">Update Availability</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</header>

<div class="page-content">

<?php if (empty($feedbacks)): ?>
    <p class="info-msg">No feedback yet.</p>
<?php else: ?>
    <?php foreach ($feedbacks as $f): ?>
        <div class="card feedback-card">
            <strong>Customer:</strong> <?= htmlspecialchars($f['customer_name']) ?><br>
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

            <!-- MASSAGER REPLY FORM -->
            <form method="post" action="reply_feedback.php" class="reply-form">
                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                <textarea name="reply" required placeholder="Reply to customer..."></textarea>
                <button type="submit">Reply</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

</body>
</html>
