<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* SECURITY CHECK */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

/* FETCH ALL FEEDBACK */
$stmt = $conn->query("
    SELECT f.*, u.username AS customer_name
    FROM feedback f
    JOIN users u ON f.customer_id = u.id
    ORDER BY f.created_at DESC
");
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Feedback</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="page-content">

    <h2>Customer Feedback</h2>

    <!-- Header / Navigation -->
    <div class="admin-nav">
        <a href="dashboard.php">Dashboard</a> |
        <a href="../auth/logout.php">Logout</a>
    </div>

    <?php if (empty($feedbacks)): ?>
        <p>No customer feedback yet.</p>
    <?php endif; ?>

    <?php foreach ($feedbacks as $f): ?>
    <div class="feedback-card">
        <div class="feedback-header">
            <strong>Customer: <?= htmlspecialchars($f['customer_name']) ?></strong>
            <span class="rating">⭐ <?= $f['rating'] ?>/5</span>
            <?php if ($f['is_flagged']): ?>
                <span class="flagged">🚩 Flagged</span>
            <?php endif; ?>
        </div>

        <div class="feedback-comment">
            <strong>Comment:</strong><br>
            <?= nl2br(htmlspecialchars($f['comment'])) ?>
        </div>

        <!-- FETCH REPLIES -->
        <?php
        $replyStmt = $conn->prepare("
            SELECT r.*, u.username, u.role
            FROM feedback_replies r
            JOIN users u ON r.replied_by = u.id
            WHERE r.feedback_id = ?
            ORDER BY r.created_at ASC
        ");
        $replyStmt->execute([$f['id']]);
        $replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="feedback-replies">
            <strong>Replies:</strong><br>
            <?php if (empty($replies)): ?>
                <em>No replies yet.</em>
            <?php else: ?>
                <?php foreach ($replies as $r): ?>
                    <div class="reply">
                        <strong><?= ucfirst($r['role']) ?> (<?= htmlspecialchars($r['username']) ?>):</strong><br>
                        <?= nl2br(htmlspecialchars($r['reply'])) ?><br>
                        <small><?= $r['created_at'] ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ADMIN REPLY FORM -->
        <form method="post" action="reply_feedback.php" class="feedback-form">
            <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
            <textarea name="reply" required placeholder="Reply to customer..."></textarea>
            <button type="submit">Reply</button>
        </form>
    </div>
    <?php endforeach; ?>

</div>

</body>
</html>
