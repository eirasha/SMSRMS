<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$success = $error = '';

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // SUBMIT NEW FEEDBACK
    if ($_POST['action'] === 'submit') {
        $booking_id  = (int)$_POST['booking_id'];
        $massager_id = (int)$_POST['massager_id'];
        $rating      = (int)$_POST['rating'];
        $comment     = trim($_POST['comment']);

        if ($rating < 1 || $rating > 5 || empty($comment)) {
            $error = 'Please provide a rating and comment.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND customer_id = ? AND payment_status = 'paid'");
            $stmt->execute([$booking_id, $customer_id]);
            if (!$stmt->fetch()) {
                $error = 'Invalid booking.';
            } else {
                $stmt = $conn->prepare("SELECT id FROM feedback WHERE booking_id = ? AND customer_id = ?");
                $stmt->execute([$booking_id, $customer_id]);
                if ($stmt->fetch()) {
                    $error = 'You have already submitted feedback for this booking.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO feedback (booking_id, customer_id, massager_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$booking_id, $customer_id, $massager_id, $rating, $comment]);
                    $success = 'Feedback submitted successfully!';
                }
            }
        }
    }

    // EDIT FEEDBACK
    if ($_POST['action'] === 'edit') {
        $feedback_id = (int)$_POST['feedback_id'];
        $rating      = (int)$_POST['rating'];
        $comment     = trim($_POST['comment']);

        if ($rating < 1 || $rating > 5 || empty($comment)) {
            $error = 'Please provide a rating and comment.';
        } else {
            $stmt = $conn->prepare("UPDATE feedback SET rating = ?, comment = ? WHERE id = ? AND customer_id = ?");
            $stmt->execute([$rating, $comment, $feedback_id, $customer_id]);
            $success = 'Feedback updated successfully!';
        }
    }

    // DELETE FEEDBACK
    if ($_POST['action'] === 'delete') {
        $feedback_id = (int)$_POST['feedback_id'];
        $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ? AND customer_id = ?");
        $stmt->execute([$feedback_id, $customer_id]);
        $success = 'Feedback deleted.';
    }
}

// Paid bookings not yet reviewed
$stmt = $conn->prepare("
    SELECT b.id, b.booking_date, b.booking_time, s.name AS service_name, m.name AS massager_name, b.massager_id
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN massagers m ON b.massager_id = m.user_id
    WHERE b.customer_id = ? AND b.payment_status = 'paid'
    AND b.id NOT IN (SELECT booking_id FROM feedback WHERE customer_id = ?)
    ORDER BY b.booking_date DESC
");
$stmt->execute([$customer_id, $customer_id]);
$eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Submitted feedback with replies
$stmt = $conn->prepare("
    SELECT f.*, s.name AS service_name, m.name AS massager_name,
           r.reply, r.created_at as replied_at, r.replied_by
    FROM feedback f
    JOIN bookings b ON f.booking_id = b.id
    JOIN services s ON b.service_id = s.id
    LEFT JOIN massagers m ON f.massager_id = m.user_id
    LEFT JOIN feedback_replies r ON f.id = r.feedback_id
    WHERE f.customer_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$customer_id]);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | Sunflower</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #c9a84c; --gold-light: #f4d03f; --gold-pale: #fdf8ec;
            --dark: #1a1208; --dark-soft: #2d2010; --text: #3d2e0e;
            --text-muted: #8a7355; --border: #e8d9b5; --white: #fffef9;
            --green: #2d6a4f; --green-light: #d8f3dc;
            --red: #c0392b; --red-light: #fdecea;
            --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); min-height: 100vh; padding-top: 75px; }

        .header { position: fixed; top: 0; left: 0; width: 100%; height: 70px; background: var(--dark); display: flex; align-items: center; justify-content: space-between; padding: 0 5%; z-index: 1000; box-shadow: 0 2px 20px rgba(0,0,0,0.3); }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { height: 40px; width: 40px; object-fit: contain; border-radius: 50%; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        .nav-bar { display: flex; align-items: center; gap: 6px; }
        .nav-bar a { text-decoration: none; font-size: 0.875rem; font-weight: 500; color: #c4b08a; padding: 7px 14px; border-radius: 6px; transition: all 0.2s; }
        .nav-bar a:hover { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-bar a.active { color: var(--gold-light); background: rgba(244,208,63,0.12); }
        .nav-bar a.logout { color: #e57373; }

        .container { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-family: 'Playfair Display', serif; font-size: 1.9rem; color: var(--dark); }
        .page-title p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: var(--green-light); color: var(--green); border-left: 4px solid var(--green); }
        .alert-error   { background: var(--red-light); color: var(--red); border-left: 4px solid var(--red); }

        .card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--card-shadow); overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); }
        .card-header h2 { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--dark); }
        .card-body { padding: 24px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 7px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--white); font-family: inherit; font-size: 0.92rem; color: var(--text); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238a7355' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }

        .star-rating { display: flex; gap: 6px; flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 1.8rem; color: #d1c4a8; cursor: pointer; transition: color 0.15s; line-height: 1; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: var(--gold-light); }

        .btn { padding: 11px 24px; border-radius: 8px; border: none; font-family: inherit; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-gold { background: var(--gold); color: var(--dark); }
        .btn-gold:hover { background: #b8942e; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(201,168,76,0.3); }
        .btn-sm { padding: 6px 14px; font-size: 0.78rem; border-radius: 6px; }
        .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--gold); color: var(--gold); }
        .btn-danger { background: var(--red-light); color: var(--red); border: 1.5px solid #f5c6c6; }
        .btn-danger:hover { background: var(--red); color: white; }

        .feedback-item { border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; background: var(--white); transition: box-shadow 0.2s; }
        .feedback-item:hover { box-shadow: var(--card-shadow); }
        .feedback-item:last-child { margin-bottom: 0; }
        .feedback-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .feedback-service { font-weight: 700; color: var(--dark); font-size: 1rem; }
        .feedback-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 3px; }
        .stars-display { color: var(--gold-light); font-size: 1.2rem; letter-spacing: 2px; }
        .feedback-comment { font-size: 0.92rem; color: var(--text); line-height: 1.6; font-style: italic; margin-bottom: 14px; padding: 12px 16px; background: var(--gold-pale); border-radius: 8px; border-left: 3px solid var(--gold); }
        .reply-box { margin-top: 14px; padding: 14px 16px; background: #f0f9f4; border-radius: 8px; border-left: 3px solid var(--green); }
        .reply-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--green); margin-bottom: 6px; }
        .reply-box p { font-size: 0.9rem; color: var(--text); line-height: 1.5; }
        .reply-by { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; }
        .feedback-actions { display: flex; gap: 8px; margin-top: 14px; }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; padding: 28px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal h3 { font-family: 'Playfair Display', serif; margin-bottom: 20px; color: var(--dark); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    </style>
</head>
<body>

<header class="header">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="calendar.php">Reservation</a>
        <a href="payment.php">Payments</a>
        <a href="feedback.php" class="active">Feedback</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="container">

    <div class="page-title">
        <h1>My Feedback</h1>
        <p>Rate your sessions and share your experience with us.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">&#10005; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- SUBMIT FORM -->
    <?php if (!empty($eligible)): ?>
    <div class="card">
        <div class="card-header"><h2>Leave a Review</h2></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="massager_id" id="massager-id-input">

                <div class="form-group">
                    <label>Select Session</label>
                    <select name="booking_id" id="booking-select" class="form-control" required>
                        <option value="" disabled selected>Choose a paid session...</option>
                        <?php foreach ($eligible as $b): ?>
                            <option value="<?= $b['id'] ?>" data-massager="<?= $b['massager_id'] ?>">
                                <?= htmlspecialchars($b['service_name']) ?> - <?= date('d M Y', strtotime($b['booking_date'])) ?>
                                <?= $b['massager_name'] ? ' (' . htmlspecialchars($b['massager_name']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" class="form-control" placeholder="Share your experience..." required></textarea>
                </div>

                <button type="submit" class="btn btn-gold">Submit Review</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- SUBMITTED REVIEWS -->
    <div class="card">
        <div class="card-header"><h2>My Reviews</h2></div>
        <div class="card-body">
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state">
                    <div class="icon">&#128172;</div>
                    <p>No reviews submitted yet.</p>
                </div>
            <?php else: ?>
               <?php foreach ($feedbacks as $f): ?>
<div class="feedback-item">
    <div class="feedback-top">
        <div>
            <div class="feedback-service"><?= htmlspecialchars($f['service_name']) ?></div>
            <div class="feedback-meta">
                <?= $f['massager_name'] ? 'with ' . htmlspecialchars($f['massager_name']) . ' &middot; ' : '' ?>
                <?= date('d M Y', strtotime($f['created_at'])) ?>
            </div>
        </div>
        <div class="stars-display">
            <?= str_repeat('&#9733;', $f['rating']) ?><?= str_repeat('&#9734;', 5 - $f['rating']) ?>
        </div>
    </div>

    <div class="feedback-comment">"<?= nl2br(htmlspecialchars($f['comment'])) ?>"</div>

    <?php if (!empty($f['reply'])): ?>
    <div class="reply-box">
        <div class="reply-label">Response from Admin</div>
        <p><?= nl2br(htmlspecialchars($f['reply'])) ?></p>
        <div class="reply-by">Replied on: <?= date('d M Y, g:i A', strtotime($f['replied_at'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="feedback-actions">
        <button class="btn btn-sm btn-outline" onclick="openEdit(<?= $f['id'] ?>, <?= $f['rating'] ?>, <?= json_encode($f['comment']) ?>)">Edit</button>
        <form method="POST" onsubmit="return confirm('Delete this review?');" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h3>Edit Review</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="feedback_id" id="edit-feedback-id">
            <div class="form-group">
                <label>Rating</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="edit-star<?= $i ?>" value="<?= $i ?>">
                        <label for="edit-star<?= $i ?>">&#9733;</label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Comment</label>
                <textarea name="comment" id="edit-comment" class="form-control" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeEdit()">Cancel</button>
                <button type="submit" class="btn btn-gold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('booking-select')?.addEventListener('change', function() {
    document.getElementById('massager-id-input').value = this.options[this.selectedIndex].dataset.massager;
});

function openEdit(id, rating, comment) {
    document.getElementById('edit-feedback-id').value = id;
    document.getElementById('edit-comment').value = comment;
    const radio = document.getElementById('edit-star' + rating);
    if (radio) radio.checked = true;
    document.getElementById('editModal').classList.add('active');
}

function closeEdit() {
    document.getElementById('editModal').classList.remove('active');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>
</body>
</html>