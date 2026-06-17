<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// 1. Capture the filter date from the GET request
$filter_date = $_GET['filter_date'] ?? '';

// 2. Base query
$query = "
    SELECT b.*, s.name AS service_name, s.price,
           p.proof_path, p.created_at AS paid_at
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'verified'
    WHERE b.customer_id = ?
";

// 3. Array to hold our prepared statement parameters
$params = [$customer_id];

// 4. If a date is selected, append the condition to the query
if (!empty($filter_date)) {
    $query .= " AND DATE(b.booking_date) = ?";
    $params[] = $filter_date;
}

// 5. Finish the query with the ORDER BY clause
$query .= " ORDER BY b.created_at DESC";

// 6. Execute the dynamic query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$total_paid    = array_sum(array_map(fn($b) => $b['payment_status'] === 'paid' ? $b['price'] : 0, $bookings));
$count_paid    = count(array_filter($bookings, fn($b) => $b['payment_status'] === 'paid'));
$count_unpaid  = count(array_filter($bookings, fn($b) => in_array($b['payment_status'], ['unpaid', 'pending'])));
$count_verify  = count(array_filter($bookings, fn($b) => $b['payment_status'] === 'pending_verification'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Sunflower</title>
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
            --blue: #1e40af;
            --blue-light: #dbeafe;
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
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { height: 40px; width: 40px; object-fit: contain; border-radius: 50%; }
        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            color: var(--gold-light);
            letter-spacing: 2px;
        }
        .nav-bar { display: flex; align-items: center; gap: 6px; }
        .nav-bar a {
            text-decoration: none; font-size: 0.875rem; font-weight: 500;
            color: #c4b08a; padding: 7px 14px; border-radius: 6px; transition: all 0.2s;
        }
        .nav-bar a:hover { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-bar a.active { color: var(--gold-light); background: rgba(244,208,63,0.12); }
        .nav-bar a.logout { color: #e57373; }
        .nav-bar a.logout:hover { background: rgba(229,115,115,0.1); }

        /* ── LAYOUT ── */
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }

        /* ── PAGE TITLE ── */
        .page-title {
            margin-bottom: 28px;
        }
        .page-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem;
            color: var(--dark);
        }
        .page-title p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }

        /* ── SUMMARY STATS ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 28px;
        }
        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .summary-card.total::before   { background: var(--gold); }
        .summary-card.paid::before    { background: var(--green); }
        .summary-card.unpaid::before  { background: var(--red); }
        .summary-card.verify::before  { background: var(--amber); }
        .summary-label {
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-muted); margin-bottom: 8px;
        }
        .summary-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.7rem; color: var(--dark); line-height: 1;
        }
        .summary-value.green { color: var(--green); }

        /* ── TABLE CARD ── */
        .table-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .table-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .table-card-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem; color: var(--dark);
        }

        /* ── TABLE ── */
        .payments-table { width: 100%; border-collapse: collapse; }
        .payments-table th {
            padding: 11px 16px; text-align: left;
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-muted); background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
        }
        .payments-table td {
            padding: 14px 16px; font-size: 0.88rem;
            border-bottom: 1px solid #f0e8d0; vertical-align: middle;
        }
        .payments-table tr:last-child td { border-bottom: none; }
        .payments-table tr:hover td { background: #fdf5e0; }

        .service-name { font-weight: 600; color: var(--dark); }
        .booking-id   { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
        .price        { font-family: 'Playfair Display', serif; font-size: 1.05rem; color: var(--green); font-weight: 700; }
        .txn-ref      { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }

        /* ── BADGES ── */
        .badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-paid      { background: var(--green-light); color: var(--green); }
        .badge-unpaid    { background: var(--red-light); color: var(--red); }
        .badge-verifying { background: var(--amber-light); color: var(--amber); }
        .badge-failed    { background: #f3f4f6; color: #6b7280; }
        .badge-pending   { background: var(--blue-light); color: var(--blue); }
        .badge-approved  { background: var(--green-light); color: var(--green); }
        .badge-cancelled { background: var(--red-light); color: var(--red); }

        /* ── PAY NOW BUTTON ── */
        .btn-pay {
            display: inline-block;
            background: var(--gold);
            color: var(--dark);
            padding: 7px 16px;
            border-radius: 7px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            border: none; cursor: pointer;
        }
        .btn-pay:hover {
            background: #b8942e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.35);
        }

        /* ── RECEIPT LINK ── */
        .receipt-link {
            font-size: 0.78rem; color: var(--gold); text-decoration: none; font-weight: 600;
        }
        .receipt-link:hover { text-decoration: underline; }

        /* ── RECEIPT BUTTON ── */
        .btn-receipt {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--gold-pale);
            color: var(--dark);
            border: 1px solid var(--gold);
            padding: 6px 13px;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-receipt:hover {
            background: var(--gold);
            color: var(--dark);
            box-shadow: 0 3px 10px rgba(201,168,76,0.3);
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 60px 20px; color: var(--text-muted);
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 14px; opacity: 0.4; }
        .empty-state p { margin-bottom: 20px; }
        .empty-state a {
            display: inline-block; background: var(--gold); color: var(--dark);
            padding: 10px 24px; border-radius: 8px; text-decoration: none;
            font-weight: 700; font-size: 0.9rem;
        }

        @media (max-width: 900px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .payments-table th:nth-child(3),
            .payments-table td:nth-child(3) { display: none; }
        }

        .filter-panel {
        background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px;
        border: 1px solid var(--border); display: flex; gap: 15px; align-items: flex-end;
    }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .filter-group label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    .filter-group input { padding: 10px; border: 1px solid var(--border); border-radius: 8px; }
    .btn-filter { background: var(--gold); border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; color: var(--dark); }


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
        <a href="payment.php" class="active">Payments</a>
        <a href="feedback.php">Feedback</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="container">

    <div class="page-title">
        <h1>Payment History</h1>
        <p>List of Payment .</p>
    </div>

   <form method="GET" class="filter-panel">
        <div class="filter-group">
            <label>Filter by Date</label>
            <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
        </div>
        <button type="submit" class="btn-filter">Apply Filter</button>
        <a href="payment.php" style="color:var(--red); font-size:0.8rem; font-weight:600; text-decoration:none;">Clear</a>
    </form>

    <!-- Summary Stats -->
    <div class="summary-grid">
        <div class="summary-card total">
            <div class="summary-label">Total Spent</div>
            <div class="summary-value green">RM <?= number_format($total_paid, 2) ?></div>
        </div>
        <div class="summary-card paid">
            <div class="summary-label">Paid</div>
            <div class="summary-value"><?= $count_paid ?></div>
        </div>
        <div class="summary-card unpaid">
            <div class="summary-label">Unpaid</div>
            <div class="summary-value"><?= $count_unpaid ?></div>
        </div>
        <div class="summary-card verify">
            <div class="summary-label">Verifying</div>
            <div class="summary-value"><?= $count_verify ?></div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h2>All Bookings</h2>
            <a href="calendar.php" style="font-size:0.82rem; color:var(--gold); text-decoration:none; font-weight:600;">+ New Booking</a>
        </div>


        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="icon">💳</div>
                <p>You have no bookings yet.</p>
                <a href="calendar.php">Make a Reservation</a>
            </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Amount</th>
                        <th>Booking Status</th>
                        <th>Payment</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>
                            <div class="service-name"><?= htmlspecialchars($b['service_name']) ?></div>
                            <div class="booking-id">#<?= $b['id'] ?></div>
                        </td>
                        <td>
                            <?= date('d M Y', strtotime($b['booking_date'])) ?><br>
                            <small style="color:var(--text-muted);"><?= date('g:i A', strtotime($b['booking_time'])) ?></small>
                        </td>
                        <td>
                            <span class="price">RM <?= number_format($b['price'], 2) ?></span>
                        </td>
                        <td>
                            <?php
                            $s = $b['status'];
                            $cls = match($s) {
                                'approved'  => 'badge-approved',
                                'completed' => 'badge-paid',
                                'cancelled' => 'badge-cancelled',
                                default     => 'badge-pending'
                            };
                            echo '<span class="badge ' . $cls . '">' . ucfirst($s) . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $ps = $b['payment_status'];
                            if ($ps === 'paid') {
                                echo '<span class="badge badge-paid">Paid</span>';
                            } elseif ($ps === 'pending_verification') {
                                echo '<span class="badge badge-verifying">Verifying</span>';
                            } elseif ($ps === 'failed') {
                                echo '<span class="badge badge-failed">Failed</span>';
                            } else {
                                echo '<span class="badge badge-unpaid">Unpaid</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($b['payment_status'] === 'paid'): ?>
                                <a href="receipt.php?id=<?= $b['id'] ?>" class="btn-receipt" title="View Receipt">
                                    🧾 Receipt
                                </a>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.78rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>