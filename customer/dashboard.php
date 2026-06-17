<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// 1. Stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('pending','approved') AND payment_status != 'failed' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) as paid
    FROM bookings WHERE customer_id=?
");
$stmt->execute([$customer_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Next upcoming booking
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, s.price, m.name AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN massagers m ON b.massager_id = m.user_id
    WHERE b.customer_id = ? AND b.status IN ('pending','approved') AND b.payment_status != 'failed'
    ORDER BY b.booking_date ASC, b.booking_time ASC
    LIMIT 1
");
$stmt->execute([$customer_id]);
$next_booking = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Recent bookings
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, m.name AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN massagers m ON b.massager_id = m.user_id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC
    LIMIT 6
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$username = htmlspecialchars($_SESSION['username'] ?? 'Customer');
$today = date('l, d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sunflower</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #c9a84c;
            --gold-light: #f4d03f;
            --gold-pale: #fdf8ec;
            --dark: #1a1208;
            --dark-soft: #2d2010;
            --text: #3d2e0e;
            --text-muted: #8a7355;
            --border: #e8d9b5;
            --white: #fffef9;
            --green: #2d6a4f;
            --green-light: #d8f3dc;
            --red: #c0392b;
            --red-light: #fdecea;
            --amber: #b7791f;
            --amber-light: #fef3c7;
            --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gold-pale);
            color: var(--text);
            min-height: 100vh;
            padding-top: 75px;
        }

        /* ── HEADER ── */
        .header {
            position: fixed; top: 0; left: 0; width: 100%; height: 70px;
            background: var(--dark);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 5%; z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
        }
        .brand-logo {
            width: 36px; height: 36px;
            background: var(--gold);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            color: var(--gold-light);
            letter-spacing: 2px;
        }
        .nav-bar {
            display: flex; align-items: center; gap: 6px;
        }
        .nav-bar a {
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: #c4b08a;
            padding: 7px 14px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .nav-bar a:hover { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-bar a.active { color: var(--gold-light); background: rgba(244,208,63,0.12); }
        .nav-bar a.logout { color: #e57373; }
        .nav-bar a.logout:hover { background: rgba(229,115,115,0.1); }

        /* ── LAYOUT ── */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* ── WELCOME ── */
        .welcome {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--dark);
            line-height: 1.2;
        }
        .welcome h1 span { color: var(--gold); }
        .welcome-date {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        /* ── STATS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(201,168,76,0.15); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--gold);
        }
        .stat-label {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--dark);
            line-height: 1;
        }
        .stat-icon {
            position: absolute;
            right: 16px; top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            opacity: 0.12;
        }

        /* ── MAIN GRID ── */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            align-items: start;
        }

        /* ── CARD BASE ── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .card-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            color: var(--dark);
        }
        .card-header a {
            font-size: 0.82rem;
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }
        .card-header a:hover { text-decoration: underline; }
        .card-body { padding: 20px 24px; }

        /* ── NEXT BOOKING ── */
        .next-booking-card {
            background: linear-gradient(135deg, var(--dark) 50%, var(--dark-soft) 100%);
            border: none;
            color: var(--white);
        }
        .next-booking-card .card-header {
            border-bottom: 1px solid rgba(176, 163, 112, 0.81);
        }
        .next-booking-card .card-header h2 { color: var(--gold-light); }
        .next-booking-card .card-header a { color: #c4b08a; }

        .booking-detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(160, 157, 97, 0.76);
        }
        .booking-detail-row:last-child { border-bottom: none; }
        .detail-icon {
            width: 36px; height: 36px;
            background: rgba(242, 225, 159, 0.5);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .detail-label {
            font-size: 0.75rem;
            color: #c4b08a;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 0.95rem;
            color: var(--white);
            font-weight: 500;
        }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pending   { background: var(--amber-light); color: var(--amber); }
        .badge-approved  { background: var(--green-light); color: var(--green); }
        .badge-completed { background: #e0e7ff; color: #3730a3; }
        .badge-cancelled { background: var(--red-light); color: var(--red); }
        .badge-paid      { background: var(--green-light); color: var(--green); }
        .badge-unpaid    { background: var(--red-light); color: var(--red); }
        .badge-verifying { background: var(--amber-light); color: var(--amber); }

        /* ── BOOKINGS TABLE ── */
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bookings-table th {
            padding: 10px 14px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
        }
        .bookings-table td {
            padding: 13px 14px;
            font-size: 0.88rem;
            border-bottom: 1px solid #f0e8d0;
            vertical-align: middle;
        }
        .bookings-table tr:last-child td { border-bottom: none; }
        .bookings-table tr:hover td { background: var(--gold-pale); }
        .service-name { font-weight: 600; color: var(--dark); }
        .therapist-name { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }

        /* ── QUICK ACTIONS ── */
        .quick-actions { display: flex; flex-direction: column; gap: 12px; }
        .action-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: 1.5px solid transparent;
        }
        .action-btn-primary {
            background: var(--gold);
            color: var(--dark);
            border-color: var(--gold);
        }
        .action-btn-primary:hover {
            background: #b8942e;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(201,168,76,0.35);
        }
        .action-btn-secondary {
            background: var(--white);
            color: var(--text);
            border-color: var(--border);
        }
        .action-btn-secondary:hover {
            border-color: var(--gold);
            color: var(--gold);
            transform: translateY(-1px);
        }
        .action-icon {
            width: 38px; height: 38px;
            background: rgba(0,0,0,0.08);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .action-btn-primary .action-icon { background: rgba(0,0,0,0.12); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5; }
        .empty-state p { font-size: 0.9rem; }

        /* ── NO BOOKING STATE ── */
        .no-booking {
            padding: 28px 24px;
            text-align: center;
        }
        .no-booking .icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }
        .no-booking p { color: #c4b08a; font-size: 0.9rem; margin-bottom: 16px; }
        .no-booking a {
            display: inline-block;
            background: var(--gold);
            color: var(--dark);
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: background 0.2s;
        }
        .no-booking a:hover { background: #b8942e; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .main-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .nav-bar a span { display: none; }
            .welcome h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-bar">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="calendar.php">Reservation</a>
        <a href="payment.php">Payments</a>
        <a href="feedback.php">Feedback</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="container">

    <!-- Welcome -->
    <div class="welcome">
        <div>
            <h1>Welcome back, <span><?= $username ?></span>!</h1>
            <p class="welcome-date"><?= $today ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-icon">📋</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Upcoming</div>
            <div class="stat-value"><?= $stats['upcoming'] ?? 0 ?></div>
            <div class="stat-icon">📅</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
            <div class="stat-icon">✅</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Paid Sessions</div>
            <div class="stat-value"><?= $stats['paid'] ?? 0 ?></div>
            <div class="stat-icon">💳</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="main-grid">

        <!-- Left: Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Bookings</h2>
                <a href="payment.php">View all →</a>
            </div>
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No bookings yet. Make your first reservation!</p>
                </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td>
                                <div class="service-name"><?= htmlspecialchars($b['service_name']) ?></div>
                                <div class="therapist-name"><?= htmlspecialchars($b['massager_name'] ?? 'Assigning therapist...') ?></div>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($b['booking_date'])) ?><br>
                                <small style="color: var(--text-muted);"><?= date('g:i A', strtotime($b['booking_time'])) ?></small>
                            </td>
                            <td>
                                <?php
                                $s = $b['status'];
                                $cls = match($s) {
                                    'approved'  => 'badge-approved',
                                    'completed' => 'badge-completed',
                                    'cancelled' => 'badge-cancelled',
                                    default     => 'badge-pending'
                                };
                                ?>
                                <span class="badge <?= $cls ?>"><?= ucfirst($s) ?></span>
                            </td>
                            <td>
                                <?php
                                $ps = $b['payment_status'];
                                if ($ps === 'paid') {
                                    echo '<span class="badge badge-paid">Paid</span>';
                                } elseif ($ps === 'pending_verification') {
                                    echo '<span class="badge badge-verifying">Verifying</span>';
                                } elseif ($ps === 'failed') {
                                    echo '<span class="badge badge-cancelled">Failed</span>';
                                } else {
                                    echo '<span class="badge badge-unpaid">Unpaid</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div style="display: flex; flex-direction: column; gap: 20px;">

            <!-- Next Appointment -->
            <div class="card next-booking-card">
                <div class="card-header">
                    <h2>Next Appointment</h2>
                    <a href="calendar.php">Manage →</a>
                </div>
                <?php if ($next_booking): ?>
                <div class="card-body">
                    <div class="booking-detail-row">
                        <div class="detail-icon">💆</div>
                        <div>
                            <div class="detail-label">Service</div>
                            <div class="detail-value"><?= htmlspecialchars($next_booking['service_name']) ?></div>
                        </div>
                    </div>
                    <div class="booking-detail-row">
                        <div class="detail-icon">📅</div>
                        <div>
                            <div class="detail-label">Date</div>
                            <div class="detail-value"><?= date('d F Y', strtotime($next_booking['booking_date'])) ?></div>
                        </div>
                    </div>
                    <div class="booking-detail-row">
                        <div class="detail-icon">🕐</div>
                        <div>
                            <div class="detail-label">Time</div>
                            <div class="detail-value"><?= date('g:i A', strtotime($next_booking['booking_time'])) ?></div>
                        </div>
                    </div>
                    <div class="booking-detail-row">
                        <div class="detail-icon">👤</div>
                        <div>
                            <div class="detail-label">Massager</div>
                            <div class="detail-value"><?= htmlspecialchars($next_booking['massager_name'] ?? 'Being assigned') ?></div>
                        </div>
                    </div>
                    <div class="booking-detail-row">
                        <div class="detail-icon">💳</div>
                        <div>
                            <div class="detail-label">Payment</div>
                            <div class="detail-value">
                                <?php
                                $ps = $next_booking['payment_status'];
                                if ($ps === 'paid') echo '<span class="badge badge-paid">Paid</span>';
                                elseif ($ps === 'pending_verification') echo '<span class="badge badge-verifying">Verifying</span>';
                                else echo '<span class="badge badge-unpaid">Unpaid</span>';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-booking">
                    <div class="icon">🌿</div>
                    <p>No upcoming appointments scheduled.</p>
                    <a href="calendar.php">Book a Session</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2>Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="calendar.php" class="action-btn action-btn-primary">
                            <div class="action-icon">📅</div>
                            <div>
                                <div>Book New Session</div>
                                <div style="font-size:0.75rem; font-weight:400; opacity:0.8;">Choose date, time & therapist</div>
                            </div>
                        </a>
                        <a href="payment.php" class="action-btn action-btn-secondary">
                            <div class="action-icon">💳</div>
                            <div>
                                <div>Payment History</div>
                                <div style="font-size:0.75rem; font-weight:400; color: var(--text-muted);">View all transactions</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
