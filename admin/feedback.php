<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$success = $error = '';

// Handle Actions (Reply/Flag/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply'])) {
        $stmt = $conn->prepare("UPDATE feedback SET reply = ?, replied_by = 'admin', replied_at = NOW() WHERE id = ?");
        $stmt->execute([trim($_POST['reply']), (int)$_POST['feedback_id']]);
        $success = 'Reply posted successfully!';
    } elseif (isset($_POST['toggle_flag'])) {
        $stmt = $conn->prepare("UPDATE feedback SET is_flagged = ? WHERE id = ?");
        $stmt->execute([$_POST['current_flag'] ? 0 : 1, (int)$_POST['feedback_id']]);
        $success = 'Feedback status updated.';
    } elseif (isset($_POST['delete_feedback'])) {
        $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->execute([(int)$_POST['feedback_id']]);
        $success = 'Feedback deleted.';
    }
}

// Data Fetching
$filter_rating = $_GET['rating'] ?? '';
$filter_flagged = $_GET['flagged'] ?? '';
$filter_date = $_GET['date'] ?? '';

$sql = "SELECT f.*, u.username AS customer_name, s.name AS service_name, m.name AS massager_name 
        FROM feedback f
        JOIN users u ON f.customer_id = u.id
        JOIN bookings b ON f.booking_id = b.id
        JOIN services s ON b.service_id = s.id
        LEFT JOIN massagers m ON f.massager_id = m.user_id WHERE 1=1";
$params = [];

if ($filter_rating !== '') { $sql .= " AND f.rating = ?"; $params[] = (int)$filter_rating; }
if ($filter_flagged !== '') { $sql .= " AND f.is_flagged = ?"; $params[] = (int)$filter_flagged; }
if ($filter_date !== '') { $sql .= " AND DATE(f.created_at) = ?"; $params[] = $filter_date; }

$stmt = $conn->prepare($sql . " ORDER BY f.created_at DESC");
$stmt->execute($params);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($feedbacks);
$avg = $total ? round(array_sum(array_column($feedbacks, 'rating')) / $total, 1) : 0;
$flagged = count(array_filter($feedbacks, fn($f) => $f['is_flagged']));
$no_reply = count(array_filter($feedbacks, fn($f) => empty($f['reply'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Feedback - Sunflower Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Shared Styles */
        :root { --gold: #c9a84c; --gold-light: #f4d03f; --gold-pale: #fdf8ec; --dark: #1a1208; --text: #3d2e0e; --text-muted: #8a7355; --border: #e8d9b5; --white: #fffef9; --card-shadow: 0 4px 24px rgba(201,168,76,0.10); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--dark); position: fixed; height: 100vh; left: 0; top: 0; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.15); z-index: 100; }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 6px; padding: 0 16px; flex: 1; }
        .nav-links a { text-decoration: none; font-size: 0.95rem; font-weight: 500; color: #c4b08a; padding: 12px 18px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
        .nav-links a:hover, .nav-links a.active { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-links a.logout { color: #e57373; margin-top: auto; margin-bottom: 24px; font-weight: 600; transition: all 0.3s ease; }
        .nav-links a.logout:hover { background: rgba(229, 115, 115, 0.1); color: #ff8a8a; }
        .nav-links a.logout span { margin-right: 8px; }
        
        /* Main Content */
        .main-content { margin-left: 260px; padding: 40px 50px; flex-grow: 1; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--dark); }
        
        /* Stats & Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 30px; }
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 22px; box-shadow: var(--card-shadow); }
        .stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 1.8rem; margin-top: 5px; }
        
        /* Filter Bar */
        .filter-bar { display: flex; gap: 16px; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; background: var(--white); padding: 20px; border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--card-shadow); }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .form-group label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 6px; }
        .form-group input, .form-group select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; background: var(--white); outline: none; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--gold); }
        .filter-actions { display: flex; gap: 10px; }
        .btn-filter { background: var(--gold-light); color: var(--dark); border: none; padding: 10px 20px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-filter:hover { background: var(--gold); }
        .btn-clear { background: #f5f5f5; color: var(--text-muted); border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; transition: 0.2s; }
        .btn-clear:hover { background: #e0e0e0; color: var(--dark); }

        /* Feedback List */
        .feedback-item { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: var(--card-shadow); }
        .feedback-meta { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; }
        .btn-reply { background: var(--gold); color: var(--dark); border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="bookings.php">Manage Reservation</a>
        <a href="assign/massagers.php">Manage Massagers</a>
        <a href="service.php">Manage Services</a>
        <a href="transactions.php">Manage Payments</a>
        <a href="availability.php">Manage Availability</a>
        <a href="feedback.php" class="active">Manage Feedback</a>
        <a href="reports.php">Generate Reports</a>
        <a href="../auth/logout.php" class="logout"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1>Manage Feedback</h1>
    </div>

    <div class="stats-grid">
        <div class="card"><div class="stat-label">Total Reviews</div><div class="stat-value"><?= $total ?></div></div>
        <div class="card"><div class="stat-label">Avg Rating</div><div class="stat-value"><?= $avg ?> ★</div></div>
        <div class="card"><div class="stat-label">Flagged</div><div class="stat-value"><?= $flagged ?></div></div>
        <div class="card"><div class="stat-label">Pending Reply</div><div class="stat-value"><?= $no_reply ?></div></div>
    </div>

    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Filter by Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        </div>
        <div class="form-group">
            <label>Filter by Rating</label>
            <select name="rating">
                <option value="">All Ratings</option>
                <option value="5" <?= $filter_rating == '5' ? 'selected' : '' ?>>5 Stars</option>
                <option value="4" <?= $filter_rating == '4' ? 'selected' : '' ?>>4 Stars</option>
                <option value="3" <?= $filter_rating == '3' ? 'selected' : '' ?>>3 Stars</option>
                <option value="2" <?= $filter_rating == '2' ? 'selected' : '' ?>>2 Stars</option>
                <option value="1" <?= $filter_rating == '1' ? 'selected' : '' ?>>1 Star</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter">Apply Filters</button>
            <a href="feedback.php" class="btn-clear">Clear</a>
        </div>
    </form>

    <?php if (empty($feedbacks)): ?>
        <div class="card" style="text-align: center; color: var(--text-muted);">
            <p>No feedback found matching your criteria.</p>
        </div>
    <?php else: ?>
        <?php foreach ($feedbacks as $f): ?>
        <div class="feedback-item">
            <h3><?= htmlspecialchars($f['customer_name']) ?></h3>
            <div class="feedback-meta">
                Date: <?= date('d M Y', strtotime($f['created_at'])) ?> | 
                Rating: <?= str_repeat('★', $f['rating']) ?><?= str_repeat('☆', 5 - $f['rating']) ?>
            </div>
            <p style="margin: 10px 0;"><?= htmlspecialchars($f['comment']) ?></p>
            <?php if ($f['reply']): ?>
                <div style="background:var(--gold-pale); padding:10px; border-left:4px solid var(--gold);">
                    <strong>Admin Reply:</strong> <?= htmlspecialchars($f['reply']) ?>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                    <textarea name="reply" style="width:100%; padding:10px; border:1px solid var(--border); border-radius: 8px;" placeholder="Write a reply..."></textarea>
                    <button type="submit" class="btn-reply" style="margin-top:10px;">Post Reply</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>