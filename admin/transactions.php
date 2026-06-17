<?php
session_start();
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Handle approve / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['booking_id'])) {
    $bid    = (int)$_POST['booking_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve' && $bid > 0) {
            $conn->prepare("
                UPDATE bookings
                SET payment_status = 'paid', status = 'approved'
                WHERE id = ? AND payment_status IN ('pending_verification','unpaid','pending')
            ")->execute([$bid]);
        }

        if ($action === 'reject' && $bid > 0) {
            $conn->prepare("
                UPDATE bookings
                SET payment_status = 'failed', status = 'cancelled'
                WHERE id = ? AND payment_status NOT IN ('paid','refunded')
            ")->execute([$bid]);
        }

        if ($action === 'refund' && $bid > 0) {
            $conn->prepare("
                UPDATE bookings
                SET payment_status = 'refunded', status = 'cancelled'
                WHERE id = ? AND payment_status = 'paid'
            ")->execute([$bid]);
        }

    } catch (PDOException $e) {
        error_log("manage_payments action error: " . $e->getMessage());
    }

    header("Location: transactions.php");
    exit;
}

// Filters
$filter    = $_GET['status']    ?? 'all';
$search    = trim($_GET['search']    ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to    = $_GET['date_to']   ?? '';

$allowed = ['all','paid','unpaid','failed','refunded'];
if (!in_array($filter, $allowed)) $filter = 'all';

// Build query
$where  = ['1=1'];
$params = [];

if ($filter !== 'all') {
    $where[]  = "b.payment_status = :filter";
    $params[':filter'] = $filter;
}
if ($search !== '') {
    $where[]          = "u.username LIKE :search";
    $params[':search'] = '%' . $search . '%';
}
if ($date_from !== '') {
    $where[]              = "DATE(b.booking_date) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[]            = "DATE(b.booking_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

try {
    $stmt = $conn->prepare("
        SELECT
            b.id, b.booking_date, b.booking_time,
            b.status AS booking_status,
            b.payment_status,
            b.created_at,
            s.name AS service_name, s.price,
            u.username AS customer_name, u.email,
            m.name AS massager_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        LEFT JOIN massagers m ON b.massager_id = m.user_id
        $where_sql
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary metrics
    $metrics = $conn->query("
        SELECT
            COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END)                        AS paid,
            COUNT(CASE WHEN b.payment_status IN ('unpaid','pending') THEN 1 END)         AS unpaid,
            COUNT(CASE WHEN b.payment_status = 'failed' THEN 1 END)                      AS failed,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN s.price ELSE 0 END),0) AS revenue
        FROM bookings b
        JOIN services s ON b.service_id = s.id
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("manage_payments.php: " . $e->getMessage());
    die("Server error.");
}


// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','Customer','Email','Service','Therapist','Booking Date','Time','Amount (RM)','Booking Status','Payment Status','Created At']);
    foreach ($bookings as $b) {
        fputcsv($out, [
            $b['id'],
            $b['customer_name'],
            $b['email'],
            $b['service_name'],
            $b['massager_name'] ?? 'Unassigned',
            date('d M Y', strtotime($b['booking_date'])),
            date('g:i A', strtotime($b['booking_time'])),
            number_format($b['price'], 2),
            $b['booking_status'],
            $b['payment_status'],
            date('d M Y H:i', strtotime($b['created_at'])),
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments | Sunflower Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #c9a84c; --gold-light: #f4d03f; --gold-pale: #fdf8ec;
            --dark: #1a1208; --text: #3d2e0e; --text-muted: #8a7355;
            --border: #e8d9b5; --white: #fffef9;
            --green: #2d6a4f; --green-light: #d8f3dc;
            --red: #c0392b; --red-light: #fdecea;
            --amber: #b7791f; --amber-light: #fef3c7;
            --blue: #1e40af; --blue-light: #dbeafe;
            --purple: #5b21b6; --purple-light: #ede9fe;
            --sidebar-w: 240px; --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); min-height: 100vh; display: flex; }

        /* SIDEBAR */
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
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    margin-bottom: 20px;
}

.brand img {
    height: 40px;
    width: 40px;
    object-fit: contain;
    border-radius: 50%;
}

.brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
    color: var(--gold-light);
    letter-spacing: 2px;
}

.nav-links {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 0 16px;
    flex: 1;
}

.nav-links a {
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    color: #c4b08a;
    padding: 12px 18px;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-links a:hover,
.nav-links a.active {
    color: var(--gold-light);
    background: rgba(244,208,63,0.08);
}

.nav-links a.logout {
    color: #e57373;
    margin-top: auto;
    margin-bottom: 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.nav-links a.logout:hover {
    background: rgba(229,115,115,0.1);
    color: #ff8a8a;
}

.nav-links a.logout span {
    margin-right: 8px;
}

/* MAIN CONTENT */
.main-content {
    margin-left: 260px;
    flex: 1;
    padding: 40px 50px;
}
        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--dark); }
        .page-title p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 18px 16px; box-shadow: var(--card-shadow); position: relative; overflow: hidden; cursor: pointer; transition: transform 0.2s; text-decoration: none; display: block; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gold); }
        .stat-card.dark { background: var(--dark); border-color: var(--dark); }
        .stat-card.dark::before { background: var(--gold-light); }
        .stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 6px; }
        .stat-card.dark .stat-label { color: #c4b08a; }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 1.7rem; color: var(--dark); line-height: 1; }
        .stat-card.dark .stat-value { color: var(--gold-light); }

        /* CARD */
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--card-shadow); overflow: hidden; }
        .card-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card-header h2 { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--dark); }

        /* FILTER BAR */
        .filter-bar { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; background: var(--gold-pale); }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid var(--border); border-radius: 7px; font-family: inherit; font-size: 0.875rem; color: var(--text); background: var(--white); outline: none; transition: border-color 0.2s; }
        .filter-group input:focus, .filter-group select:focus { border-color: var(--gold); }
        .filter-group input[type="text"] { min-width: 180px; }
        .btn-filter { padding: 8px 18px; background: var(--gold); color: var(--dark); border: none; border-radius: 7px; font-weight: 700; font-size: 0.875rem; font-family: inherit; cursor: pointer; }
        .btn-filter:hover { background: #b8942e; }
        .btn-clear { font-size: 0.875rem; font-weight: 600; color: var(--red); text-decoration: none; padding: 8px 4px; }

        /* STATUS TABS */
        .status-tabs { display: flex; gap: 6px; padding: 14px 22px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .tab { text-decoration: none; padding: 5px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); background: var(--gold-pale); border: 1px solid var(--border); transition: all 0.15s; }
        .tab:hover { color: var(--text); }
        .tab.active { background: var(--dark); color: var(--gold-light); border-color: var(--dark); }

        /* TABLE META */
        .table-meta { display: flex; justify-content: space-between; align-items: center; padding: 10px 22px; border-bottom: 1px solid var(--border); }
        .row-count { font-size: 0.82rem; color: var(--text-muted); }
        .export-btn { text-decoration: none; padding: 6px 14px; background: var(--green-light); border: 1px solid #b7e4c7; color: var(--green); border-radius: 7px; font-size: 0.78rem; font-weight: 700; }
        .export-btn:hover { background: #c5f0d4; }

        /* TABLE */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 10px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); background: var(--gold-pale); border-bottom: 1px solid var(--border); white-space: nowrap; }
        .data-table td { padding: 13px 16px; font-size: 0.86rem; border-bottom: 1px solid #f0e8d0; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: #fffbf0; }
        .customer-name { font-weight: 600; color: var(--dark); }
        .customer-email { font-size: 0.76rem; color: var(--text-muted); }

        /* BADGES */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-paid      { background: var(--green-light); color: var(--green); }
        .badge-unpaid    { background: var(--red-light); color: var(--red); }
        .badge-failed    { background: #f3f4f6; color: #6b7280; }
        .badge-refunded  { background: var(--purple-light); color: var(--purple); }
        .badge-approved  { background: var(--green-light); color: var(--green); }
        .badge-completed { background: var(--blue-light); color: var(--blue); }
        .badge-pending   { background: var(--amber-light); color: var(--amber); }
        .badge-cancelled { background: var(--red-light); color: var(--red); }

        /* RECEIPT */
        /* ACTION BUTTONS */
        .btn-approve { padding: 5px 12px; background: var(--green-light); color: var(--green); border: 1.5px solid #a7f3c0; border-radius: 6px; font-size: 0.75rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all 0.2s; }
        .btn-approve:hover { background: var(--green); color: white; }
        .btn-reject { padding: 5px 12px; background: var(--red-light); color: var(--red); border: 1.5px solid #f5c6c6; border-radius: 6px; font-size: 0.75rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all 0.2s; }
        .btn-reject:hover { background: var(--red); color: white; }
        .btn-refund { padding: 5px 12px; background: var(--purple-light); color: var(--purple); border: 1.5px solid #c4b5fd; border-radius: 6px; font-size: 0.75rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all 0.2s; }
        .btn-refund:hover { background: var(--purple); color: white; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.35; }

        /* RECEIPT MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; padding: 24px; max-width: 500px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal h3 { font-family: 'Playfair Display', serif; margin-bottom: 16px; color: var(--dark); }
        .modal img { width: 100%; border-radius: 8px; border: 1px solid var(--border); }
        .modal-close { float: right; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
        .modal-close:hover { color: var(--red); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo">
        <span class="brand-name">SUNFLOWER</span>
    </div>

    <nav class="nav-links">
        <a href="dashboard.php">Dashboard</a>

        <a href="bookings.php">Manage Reservation</a>

        <a href="assign/massagers.php">Manage Massagers</a>

        <a href="service.php">Manage Services</a>

        <a href="transactions.php" class="active">Manage Payments</a>

        <a href="availability.php">Manage Availability</a>

        <a href="feedback.php">Manage Feedback</a>

        <a href="reports.php">Generate Reports</a>

        <a href="../auth/logout.php" class="logout">
            <span>🚪</span>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main-content">

    <div class="page-title">
        <h1>Manage Payments</h1>
        <p>Review, approve or reject customer payment submissions.</p>
    </div>

    <!-- STATS — clickable to filter -->
    <div class="stats-grid">
        <div class="stat-card dark">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">RM <?= number_format($metrics['revenue'], 2) ?></div>
        </div>
        <a href="transactions.php?status=paid" class="stat-card">
            <div class="stat-label">Paid</div>
            <div class="stat-value" style="color:var(--green);"><?= $metrics['paid'] ?></div>
        </a>
        <a href="transactions.php?status=unpaid" class="stat-card">
            <div class="stat-label">Unpaid</div>
            <div class="stat-value" style="color:var(--red);"><?= $metrics['unpaid'] ?></div>
        </a>
        <a href="transactions.php?status=failed" class="stat-card">
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= $metrics['failed'] ?></div>
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>All Bookings</h2>
        </div>

        <!-- FILTER -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <button type="submit" class="btn-filter">Search</button>
            <a href="transactions.php" class="btn-clear">Clear</a>
        </form>

        <!-- STATUS TABS -->
        <div class="status-tabs">
            <?php
            $tabs = [
                'all'                  => 'All',
                'paid'                 => '✅ Paid',
            
                'failed'               => '❌ Failed',
                'refunded'             => '↩ Refunded',
            ];
            foreach ($tabs as $key => $label):
                $url = 'transactions.php?' . http_build_query(array_filter([
                    'status'    => $key,
                    'search'    => $search,
                    'date_from' => $date_from,
                    'date_to'   => $date_to,
                ]));
            ?>
                <a href="<?= $url ?>" class="tab <?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <!-- META ROW -->
        <div class="table-meta">
            <span class="row-count"><?= count($bookings) ?> record<?= count($bookings) !== 1 ? 's' : '' ?></span>
            <?php
            $csv_url = 'transactions.php?' . http_build_query(array_filter([
                'export'    => 'csv',
                'status'    => $filter !== 'all' ? $filter : '',
                'search'    => $search,
                'date_from' => $date_from,
                'date_to'   => $date_to,
            ]));
            ?>
            <a href="<?= $csv_url ?>" class="export-btn">&#11015; Export CSV</a>
        </div>

        <!-- TABLE -->
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="icon">💳</div>
                <p>No records match your filters.</p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Therapist</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Booking</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><strong>#<?= $b['id'] ?></strong></td>
                    <td>
                        <div class="customer-name"><?= htmlspecialchars($b['customer_name']) ?></div>
                        <div class="customer-email"><?= htmlspecialchars($b['email']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['service_name']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($b['massager_name'] ?? '—') ?></td>
                    <td>
                        <?= date('d M Y', strtotime($b['booking_date'])) ?><br>
                        <small style="color:var(--text-muted);"><?= date('g:i A', strtotime($b['booking_time'])) ?></small>
                    </td>
                    <td style="font-weight:700; color:var(--green);">RM <?= number_format($b['price'], 2) ?></td>

                    <td>
                        <?php
                        $bs = $b['booking_status'];
                        $bc = match($bs) {
                            'approved'  => 'badge-approved',
                            'completed' => 'badge-completed',
                            'cancelled' => 'badge-cancelled',
                            default     => 'badge-pending'
                        };
                        echo '<span class="badge ' . $bc . '">' . ucfirst($bs) . '</span>';
                        ?>
                    </td>
                    <td>
                        <?php
                        $ps = $b['payment_status'];
                        $pc = match($ps) {
                            'paid'                 => 'badge-paid',
                            'pending_verification' => 'badge-pending',
                            'failed'               => 'badge-failed',
                            'refunded'             => 'badge-refunded',
                            default                => 'badge-unpaid'
                        };
                        $pl = match($ps) {
                            'pending_verification' => 'Verifying',
                            default => ucfirst($ps)
                        };
                        echo '<span class="badge ' . $pc . '">' . $pl . '</span>';
                        ?>
                    </td>
                    <td>
                        <div class="action-group">
                        <?php if ($b['payment_status'] === 'pending_verification'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve payment for booking #<?= $b['id'] ?>?')">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-approve">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject and cancel booking #<?= $b['id'] ?>?')">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-reject">Reject</button>
                            </form>
                        <?php elseif ($b['payment_status'] === 'paid'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Issue refund for booking #<?= $b['id'] ?>? This only updates the system — process the actual refund via ToyyibPay dashboard.')">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="action" value="refund">
                                <button type="submit" class="btn-refund">Refund</button>
                            </form>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:0.78rem;">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <p style="font-size:0.8rem; color:var(--text-muted); margin-top:12px;">
        &#8505; Refunds must also be processed manually via the
        <a href="https://toyyibpay.com" target="_blank" style="color:var(--amber);">ToyyibPay merchant dashboard</a>.
        Clicking Refund here only updates the booking status in your system.
    </p>

</main>


</body>
</html>