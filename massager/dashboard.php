<?php
session_start();
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$display_date = date('l, d F Y'); // Formatted for the UI (e.g., Monday, 15 June 2026)

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------
// AJAX HANDLER FOR APPOINTMENT STATUS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Security token expired.']);
        exit;
    }

    if ($_POST['action'] === 'update_status') {
        $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        if ($booking_id && in_array($new_status, ['completed', 'no-show', 'cancelled'])) {
            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND massager_id = ?");
            $stmt->execute([$new_status, $booking_id, $massager_id]);
            echo json_encode(['status' => 'success', 'message' => 'Appointment updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
        }
        exit;
    }
}

// ---------------------------------------------------------
// FETCH DASHBOARD DATA
// ---------------------------------------------------------
// 1. Stats: Appointments and Earnings
$stmt_stats = $conn->prepare("
    SELECT 
        COUNT(*) as today_appointments,
        SUM(CASE WHEN b.status = 'completed' THEN s.price ELSE 0 END) as today_earnings,
        SUM(CASE WHEN b.status IN ('pending', 'approved') THEN s.price ELSE 0 END) as expected_earnings
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ? AND b.booking_date = ? AND b.status != 'cancelled'
");
$stmt_stats->execute([$massager_id, $today]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// 2. Today's Itinerary
$stmt_itinerary = $conn->prepare("
    SELECT b.id, b.booking_time, b.status, s.name as service_name, u.username as customer_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users u ON b.customer_id = u.id
    WHERE b.massager_id = ? AND b.booking_date = ?
    ORDER BY b.booking_time ASC
");
$stmt_itinerary->execute([$massager_id, $today]);
$itinerary = $stmt_itinerary->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sunflower Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --gold:        #c9a84c;
            --gold-light:  #f4d03f;
            --gold-pale:   #fdf8ec;
            --dark:        #1a1208;
            --text:        #3d2e0e;
            --text-muted:  #8a7355;
            --border:      #e8d9b5;
            --white:       #fffef9;
            --green:       #2d6a4f;
            --green-light: #d8f3dc;
            --red:         #c0392b;
            --red-light:   #fdecea;
            --amber:       #b7791f;
            --sidebar-w:   260px;
            --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gold-pale);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

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

        /* ── MAIN CONTENT ── */
        .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 40px 50px; max-width: calc(100% - var(--sidebar-w)); }
        
        /* Updated Page Header to support Flexbox */
        .page-header { margin-bottom: 35px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: var(--dark); }
        .page-header p { font-size: 0.95rem; color: var(--text-muted); margin-top: 6px; }

        /* Current Date Badge */
        .date-badge {
            background: var(--white);
            padding: 10px 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        .date-badge span { color: var(--gold); font-size: 1.2rem; }

        /* ── STATS GRID ── */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: var(--card-shadow); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gold); }
        .stat-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 2.4rem; color: var(--dark); }
        .stat-value.gold { color: var(--gold); }

        /* ── ITINERARY TIMELINE ── */
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--card-shadow); }
        .card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .card-header h2 { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--dark); }
        
        .timeline { padding: 24px; display: flex; flex-direction: column; gap: 20px; }
        .timeline-item { display: flex; align-items: stretch; gap: 20px; padding: 16px; background: var(--gold-pale); border-radius: 12px; border: 1px solid var(--border); transition: border-color 0.2s; }
        .timeline-item:hover { border-color: var(--gold); }
        
        .timeline-time { width: 100px; font-weight: 700; color: var(--gold); font-size: 1.1rem; flex-shrink: 0; display: flex; align-items: center; }
        .timeline-content { flex-grow: 1; border-left: 2px solid var(--border); padding-left: 20px; }
        .timeline-title { font-size: 1.1rem; font-weight: 700; color: var(--dark); margin-bottom: 4px; }
        .timeline-sub { font-size: 0.9rem; color: var(--text-muted); }
        
        .timeline-actions { display: flex; align-items: center; gap: 10px; }
        
        /* ── BUTTONS & BADGES ── */
        .btn { padding: 9px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; font-family: inherit; }
        .btn-success { background: var(--green-light); color: var(--green); }
        .btn-success:hover { background: var(--green); color: white; transform: translateY(-1px); }
        .btn-danger { background: var(--red-light); color: var(--red); }
        .btn-danger:hover { background: var(--red); color: white; transform: translateY(-1px); }

        .badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge.completed { background: var(--green-light); color: var(--green); border: 1px solid #b7e4c7; }
        .badge.cancelled { background: var(--red-light); color: var(--red); border: 1px solid #f8aba6; }
        .badge.no-show { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; opacity: 0.4; margin-bottom: 12px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-links">
        <a href="dashboard.php" class="active"><span></span> <span>Dashboard</span></a>
        <a href="my_schedule.php"><span></span> <span>My Schedule</span></a>
        <a href="availability.php"><span></span> <span>Availability</span></a>
        <a href="feedback.php"><span></span> <span>Feedback</span></a>
        <a href="../auth/logout.php" style="color: #e57373; margin-top: auto; margin-bottom: 24px;"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Welcome Back, <?= htmlspecialchars($_SESSION['username'] ?? 'Massager') ?></h1>
            <p>Here is your schedule and performance overview for today.</p>
        </div>
        
        <div class="date-badge">
            <span></span> <?= $display_date ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-value"><?= $stats['today_appointments'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Expected Earnings</div>
            <div class="stat-value">RM <?= number_format($stats['expected_earnings'], 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Earned (Completed)</div>
            <div class="stat-value gold">RM <?= number_format($stats['today_earnings'], 2) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Today's Itinerary</h2>
        </div>
        <div class="timeline">
            <?php if (empty($itinerary)): ?>
                <div class="empty-state">
                    <div class="icon"></div>
                    <p>You have no appointments scheduled for today.</p>
                </div>
            <?php else: ?>
                <?php foreach ($itinerary as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-time">
                        <?= date('h:i A', strtotime($item['booking_time'])) ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title"><?= htmlspecialchars($item['customer_name']) ?></div>
                        <div class="timeline-sub"><?= htmlspecialchars($item['service_name']) ?></div>
                    </div>
                    <div class="timeline-actions">
                        <?php if (in_array($item['status'], ['completed', 'cancelled', 'no-show'])): ?>
                            <span class="badge <?= $item['status'] ?>"><?= str_replace('-', ' ', ucfirst($item['status'])) ?></span>
                        <?php else: ?>
                            <button class="btn btn-success" onclick="updateStatus(<?= $item['id'] ?>, 'completed')">Complete</button>
                            <button class="btn btn-danger" onclick="updateStatus(<?= $item['id'] ?>, 'no-show')">No-Show</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function updateStatus(bookingId, status) {
    Swal.fire({
        title: 'Confirm Action',
        text: `Are you sure you want to mark this session as ${status.toUpperCase()}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2d6a4f',
        cancelButtonColor: '#c0392b',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('booking_id', bookingId);
            fd.append('status', status);
            fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

            fetch('dashboard.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Updated!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#c9a84c'
                    }).then(() => location.reload()); // Reload to update stats instantly
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'A network error occurred.', 'error');
            });
        }
    });
}
</script>
</body>
</html>