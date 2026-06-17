<?php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

// Strict Security Authorization Verification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Handle status update safely inside an isolated transactional block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = trim($_POST['status']);
    // Capture the current filter date so we can redirect back to it
    $current_filter = $_POST['current_filter_date'] ?? '';

    try {
        $conn->beginTransaction();

        // 1. Update primary reservation track lifecycle indicator status state flags
        $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
        $stmt->execute([$status, $booking_id]);

        // 2. Cascade status rules automatically down into matching payment table logs
        if ($status === 'cancelled') {
            // FIXED: Shifted from 'unpaid' to 'refunded' to preserve financial auditing consistency
            $stmtPay = $conn->prepare("UPDATE bookings SET payment_status='refunded' WHERE id=?");
            $stmtPay->execute([$booking_id]);
            
            $stmtPayLog = $conn->prepare("UPDATE payments SET status='rejected' WHERE booking_id=?");
            $stmtPayLog->execute([$booking_id]);
        }

        $conn->commit();
        
        // Refresh to avoid form payload re-submission alerts on reload, keeping the date filter if active
        $redirect_url = "bookings.php";
        if (!empty($current_filter)) {
            $redirect_url .= "?filter_date=" . urlencode($current_filter);
        }
        header("Location: " . $redirect_url);
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "❌ Failed updating reservation lifecycle data: " . $e->getMessage();
    }
}

// Check if admin is filtering by a specific date
$filter_date = $_GET['filter_date'] ?? '';

// Build the query dynamically based on whether a date filter is applied
$query = "
    SELECT b.*, s.name AS service_name, 
           c.username AS customer_name, 
           m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN users c ON b.customer_id = c.id
    LEFT JOIN users m ON b.massager_id = m.id
";

$params = [];
if (!empty($filter_date)) {
    // Exact date match for DATETIME columns by wrapping in DATE()
    $query .= " WHERE DATE(b.booking_date) = :filter_date ";
    $params[':filter_date'] = $filter_date;
}

$query .= " ORDER BY b.booking_date DESC ";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Exact match to the premium palette */
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
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            background: var(--dark);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.15);
            z-index: 100;
        }
        .brand {
            padding: 30px 24px;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 20px;
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
        .nav-links {
            display: flex; flex-direction: column; gap: 6px;
            padding: 0 16px;
            flex-grow: 1;
        }
        .nav-links a {
            text-decoration: none; font-size: 0.95rem; font-weight: 500;
            color: #c4b08a; padding: 12px 18px; border-radius: 8px;
            transition: all 0.2s; display: flex; align-items: center; gap: 12px;
        }
        .nav-links a:hover, .nav-links a.active {
            color: var(--gold-light); background: rgba(244,208,63,0.08);
        }
        .nav-links a.logout { color: #e57373; margin-top: auto; margin-bottom: 24px; }
        .nav-links a.logout:hover { background: rgba(229,115,115,0.1); }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: 260px;
            padding: 40px 50px;
            flex-grow: 1;
            width: calc(100% - 260px);
        }
        .welcome { margin-bottom: 35px; }
        .welcome h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--dark);
            line-height: 1.2;
        }
        .welcome-desc {
            font-size: 0.95rem; color: var(--text-muted);
            font-weight: 400; margin-top: 6px;
        }

        /* ── ERROR MESSAGE ── */
        .error-alert {
            background: var(--red-light); color: var(--red);
            padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;
            font-weight: 600; border: 1px solid #f8aba1;
        }

        /* ── DATA CARD & TABLE ── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .card-header {
            padding: 22px 26px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .card-header h2 {
            font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--dark); margin: 0;
        }
        
        /* Filter Form Styles */
        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-form label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-responsive { overflow-x: auto; }
        .bookings-table { width: 100%; border-collapse: collapse; }
        .bookings-table th {
            padding: 14px 20px; text-align: left; font-size: 0.78rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-muted); background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
        }
        .bookings-table td {
            padding: 16px 20px; font-size: 0.92rem;
            border-bottom: 1px solid #f0e8d0; vertical-align: middle;
        }
        .bookings-table tr:last-child td { border-bottom: none; }
        .bookings-table tr:hover td { background: var(--gold-pale); }
        
        /* ── TABLE TYPOGRAPHY & ELEMENTS ── */
        .customer-name { font-weight: 600; color: var(--dark); }
        .service-name { font-weight: 500; color: var(--text); }
        .therapist-name { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; }
        .date-time strong { color: var(--dark); display: block; }
        .date-time span { font-size: 0.8rem; color: var(--text-muted); }

        /* ── BADGES ── */
        .badge {
            display: inline-block; padding: 5px 12px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        /* Status Badges */
        .badge-pending { background: var(--amber-light); color: var(--amber); }
        .badge-approved { background: var(--green-light); color: var(--green); }
        .badge-completed { background: #e0e7ff; color: #3730a3; } /* Indigo */
        .badge-cancelled { background: var(--red-light); color: var(--red); }
        /* Payment Badges */
        .badge-paid { background: var(--green-light); color: var(--green); }
        .badge-unpaid { background: var(--red-light); color: var(--red); }
        .badge-refunded { background: #f1f5f9; color: #475569; }

        /* ── FORMS & BUTTONS ── */
        .action-form { display: flex; gap: 6px; align-items: center; margin: 0; }
        .status-select {
            padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--white); color: var(--text); font-weight: 500; font-family: inherit;
            font-size: 0.85rem; outline: none; cursor: pointer; transition: all 0.2s ease;
        }
        .status-select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        
        .btn-update {
            padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-family: inherit;
            font-weight: 600; background: var(--gold); color: var(--dark); border: none;
            cursor: pointer; transition: background 0.2s, transform 0.1s;
        }
        .btn-update:hover { background: #b8942e; }
        .btn-update:active { transform: scale(0.96); }
        
        .btn-clear {
            font-size: 0.85rem; font-weight: 600; color: var(--red);
            text-decoration: none; padding: 8px 12px; transition: color 0.2s;
        }
        .btn-clear:hover { text-decoration: underline; color: #991b1b; }

        /* Direct Action Buttons */
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        .btn-confirm { background: var(--green-light); color: var(--green); }
        .btn-confirm:hover { background: var(--green); color: var(--white); }
        
        .btn-complete { background: #e0e7ff; color: #3730a3; }
        .btn-complete:hover { background: #3730a3; color: var(--white); }
        
        .btn-cancel { background: var(--red-light); color: var(--red); }
        .btn-cancel:hover { background: var(--red); color: var(--white); }

        .no-action-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-style: italic;
        }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .brand-name, .nav-links a span { display: none; }
            .brand { justify-content: center; padding: 25px 10px; }
            .nav-links a { justify-content: center; padding: 14px; font-size: 1.2rem; }
            .main-content { margin-left: 80px; width: calc(100% - 80px); padding: 30px 25px; }
        }
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
        <a href="bookings.php" class="active" >Manage Reservation</a>
        <a href="assign/massagers.php">Manage Massagers</a>
        <a href="service.php">Manage Services</a>
        <a href="transactions.php">Manage Payments</a>
        <a href="availability.php">Manage Availability</a>
        <a href="feedback.php">Manage Feedback</a>
        <a href="reports.php">Generate Reports</a>
        <a href="../auth/logout.php" class="logout"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    
    <div class="welcome">
        <h1>All Reservations</h1>
        <p class="welcome-desc">Manage customer booking lifecycles, assign therapists, and update statuses.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-alert">
            <?= $error; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>System Booking Ledger</h2>
            
            <form method="GET" action="bookings.php" class="filter-form">
                <label for="filter_date">View Date:</label>
                <input type="date" id="filter_date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" class="status-select">
                <button type="submit" class="btn-update">Filter</button>
                <?php if (!empty($filter_date)): ?>
                    <a href="bookings.php" class="btn-clear">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Treatment Info</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="icon">📭</div>
                                    <p><?= empty($filter_date) ? 'No transaction histories found in system.' : 'No bookings found for the selected date.' ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($bookings as $b): ?>
                        <tr>
                            <td>
                                <div class="customer-name"><?= htmlspecialchars($b['customer_name']); ?></div>
                            </td>
                            <td>
                                <div class="service-name"><?= htmlspecialchars($b['service_name']); ?></div>
                                <div class="therapist-name">
                                    <?= empty($b['massager_name']) ? '<i>No therapist assigned</i>' : 'By: ' . htmlspecialchars($b['massager_name']); ?>
                                </div>
                            </td>
                            <td class="date-time">
                                <strong><?= date('d M Y', strtotime($b['booking_date'])); ?></strong>
                                <span><?= date('h:i A', strtotime($b['booking_date'])); ?></span>
                            </td>
                            <td>
                                <?php
                                    $s = $b['status'];
                                    $s_class = match($s) {
                                        'approved' => 'badge-approved',
                                        'completed' => 'badge-completed',
                                        'cancelled' => 'badge-cancelled',
                                        default => 'badge-pending'
                                    };
                                    $s_label = ($s === 'approved') ? 'Confirmed' : ucfirst($s);
                                ?>
                                <span class="badge <?= $s_class ?>"><?= $s_label ?></span>
                            </td>
                            <td>
                                <?php
                                    $p = $b['payment_status'];
                                    $p_class = match($p) {
                                        'paid' => 'badge-paid',
                                        'unpaid' => 'badge-unpaid',
                                        'refunded' => 'badge-refunded',
                                        default => 'badge-pending'
                                    };
                                ?>
                                <span class="badge <?= $p_class ?>"><?= ucfirst($p) ?></span>
                            </td>
                            <td>
                                <form method="post" class="action-form">
                                    <input type="hidden" name="booking_id" value="<?= $b['id']; ?>">
                                    <input type="hidden" name="current_filter_date" value="<?= htmlspecialchars($filter_date); ?>">
                                    
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <button type="submit" name="status" value="approved" class="btn-action btn-confirm">Confirm</button>
                                        <button type="submit" name="status" value="cancelled" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel</button>
                                    
                                    <?php elseif ($b['status'] === 'approved'): ?>
                                        <button type="submit" name="status" value="completed" class="btn-action btn-complete">Complete</button>
                                        <button type="submit" name="status" value="cancelled" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel</button>
                                    
                                    <?php else: ?>
                                        <span class="no-action-text">No actions</span>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>