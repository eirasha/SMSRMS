<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];
$success = $error = '';

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = 'Reply posted successfully!';
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_id'], $_POST['reply'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $reply       = trim($_POST['reply']);

    if (empty($reply)) {
        $error = 'Reply cannot be empty.';
    } else {
        try {
            // Security: Check that this feedback belongs to the logged-in massager
            $check = $conn->prepare("SELECT id FROM feedback WHERE id = ? AND massager_id = ?");
            $check->execute([$feedback_id, $massager_id]);
            
            if ($check->rowCount() === 0) {
                $error = 'Invalid feedback or unauthorized action.';
            } else {
                // Update the main feedback table
                $stmt = $conn->prepare("
                    UPDATE feedback 
                    SET reply = ?, 
                        replied_by = 'massager', 
                        replied_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$reply, $feedback_id]);

                header("Location: feedback.php?success=1");
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Failed to post reply. Error: ' . $e->getMessage();
        }
    }
}   // ← Only ONE closing brace here

// Fetch feedback for this massager
$stmt = $conn->prepare("
    SELECT f.*, s.name AS service_name, u.username AS customer_name, b.booking_date
    FROM feedback f
    JOIN bookings b ON f.booking_id = b.id
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE f.massager_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$massager_id]);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total    = count($feedbacks);
$avg      = $total ? round(array_sum(array_column($feedbacks, 'rating')) / $total, 1) : 0;
$replied  = count(array_filter($feedbacks, fn($f) => !empty($f['reply'])));
$pending  = $total - $replied;
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
            --dark: #1a1208; --text: #3d2e0e; --text-muted: #8a7355;
            --border: #e8d9b5; --white: #fffef9;
            --green: #2d6a4f; --green-light: #d8f3dc;
            --red: #c0392b; --red-light: #fdecea;
            --amber: #b7791f; --amber-light: #fef3c7;
            --sidebar-w: 260px; /* Updated to 260px to match availability.php */
            --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); min-height: 100vh; display: flex; }

        /* ── CONSISTENT SIDEBAR STYLES ── */
        .sidebar {
            width: var(--sidebar-w); background: var(--dark); position: fixed; height: 100vh;
            left: 0; top: 0; display: flex; flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.15); z-index: 100;
        }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .brand-logo { width: 36px; height: 36px; background: var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        .nav-links { display: flex; flex-direction: column; gap: 6px; padding: 20px 16px; flex-grow: 1; }
        .nav-links a { text-decoration: none; font-size: 0.95rem; font-weight: 500; color: #c4b08a; padding: 12px 18px; border-radius: 8px; display: flex; align-items: center; gap: 12px; transition: all 0.2s;}
        .nav-links a:hover { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-links a.active { color: var(--gold-light); background: rgba(244,208,63,0.08); }

        /* MAIN */
        .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 40px 50px; max-width: calc(100% - var(--sidebar-w)); }

        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: var(--dark); }
        .page-title p { color: var(--text-muted); font-size: 0.95rem; margin-top: 6px; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: var(--green-light); color: var(--green); border-left: 4px solid var(--green); }
        .alert-error   { background: var(--red-light); color: var(--red); border-left: 4px solid var(--red); }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 18px 20px; box-shadow: var(--card-shadow); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gold); }
        .stat-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--dark); }
        .stat-value.gold { color: var(--gold); }

        /* FEEDBACK CARDS */
        .feedback-item { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 22px; margin-bottom: 16px; box-shadow: var(--card-shadow); }
        .feedback-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
        .customer-name { font-weight: 700; font-size: 1rem; color: var(--dark); }
        .feedback-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 3px; }
        .stars-display { color: var(--gold-light); font-size: 1.2rem; letter-spacing: 2px; }
        .badge-flagged { display: inline-block; background: var(--red-light); color: var(--red); padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; margin-left: 8px; }
        .feedback-comment { font-size: 0.92rem; color: var(--text); line-height: 1.6; font-style: italic; margin-bottom: 16px; padding: 12px 16px; background: var(--gold-pale); border-radius: 8px; border-left: 3px solid var(--gold); }

        /* REPLY BOX STYLES */
        .reply-box { padding: 14px 16px; background: #f0f9f4; border-radius: 8px; border-left: 3px solid var(--green); margin-bottom: 14px; }
        .reply-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--green); margin-bottom: 6px; }
        
        /* Admin Reply Modifiers */
        .reply-box.admin { background: var(--amber-light); border-left-color: var(--amber); }
        .reply-box.admin .reply-label { color: var(--amber); }

        .reply-box p { font-size: 0.9rem; color: var(--text); line-height: 1.5; }
        .reply-by { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; }

        .reply-form { display: flex; flex-direction: column; gap: 10px; }
        .reply-form textarea { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; resize: vertical; min-height: 80px; font-family: inherit; font-size: 0.9rem; background: var(--white); transition: border-color 0.2s; }
        .reply-form textarea:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .btn-reply { align-self: flex-end; background: var(--gold); color: var(--dark); border: none; padding: 9px 22px; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s; }
        .btn-reply:hover { background: #b8942e; transform: translateY(-1px); }

        .no-reply-tag { display: inline-block; background: #f1f1f1; color: var(--text-muted); padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; margin-bottom: 10px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; margin-bottom: 14px; opacity: 0.4; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-links">
        <a href="dashboard.php"><span></span> <span>Dashboard</span></a>
        <a href="my_schedule.php"><span></span> <span>My Schedule</span></a>
        <a href="availability.php"><span></span> <span>Availability</span></a>
        <a href="feedback.php" class="active"><span></span> <span>Feedback</span></a>
        <a href="../auth/logout.php" style="color: #e57373; margin-top: auto; margin-bottom: 24px;"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">

    <div class="page-title">
        <h1>Client Feedback</h1>
        <p>View reviews from your sessions and respond to clients.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">&#10005; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Reviews</div>
            <div class="stat-value"><?= $total ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Rating</div>
            <div class="stat-value gold"><?= $avg > 0 ? $avg . ' &#9733;' : '—' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Awaiting Reply</div>
            <div class="stat-value"><?= $pending ?></div>
        </div>
    </div>

    <?php if (empty($feedbacks)): ?>
        <div class="empty-state">
            <div class="icon">&#128172;</div>
            <p>No feedback received yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($feedbacks as $f): ?>
        <div class="feedback-item">
            <div class="feedback-top">
                <div>
                    <div class="customer-name">
                        <?= htmlspecialchars($f['customer_name']) ?>
                        <?php if ($f['is_flagged']): ?>
                            <span class="badge-flagged">&#128681; Flagged</span>
                        <?php endif; ?>
                    </div>
                    <div class="feedback-meta">
                        <?= htmlspecialchars($f['service_name']) ?> &middot;
                        <?= date('d M Y', strtotime($f['booking_date'])) ?>
                    </div>
                </div>
                <div class="stars-display">
                    <?= str_repeat('&#9733;', $f['rating']) ?><?= str_repeat('&#9734;', 5 - $f['rating']) ?>
                </div>
            </div>

            <div class="feedback-comment">"<?= nl2br(htmlspecialchars($f['comment'])) ?>"</div>

            <?php if ($f['reply']): ?>
                <?php $isAdmin = ($f['replied_by'] === 'admin'); ?>
                <div class="reply-box <?= $isAdmin ? 'admin' : '' ?>">
                    <div class="reply-label">
                        <?= $isAdmin ? '&#128100; Admin\'s Reply' : 'Your Reply' ?>
                    </div>
                    <p><?= nl2br(htmlspecialchars($f['reply'])) ?></p>
                    <?php if ($f['replied_at']): ?>
                        <div class="reply-by">
                            <?= date('d M Y, g:i A', strtotime($f['replied_at'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-reply-tag">No reply yet</div>
                <form method="POST" class="reply-form">
                    <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                    <textarea name="reply" placeholder="Write a reply to <?= htmlspecialchars($f['customer_name']) ?>..." required></textarea>
                    <button type="submit" class="btn-reply">Post Reply</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>
</body>
</html>