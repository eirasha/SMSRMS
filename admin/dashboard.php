<?php
session_start();
require_once __DIR__ . '/../config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

try {
    /* ========================================================
       1. REAL-TIME SYNCHRONIZED METRICS METERS
    ======================================================== */
    $totalBookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $completed     = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
    $paidPayment   = $conn->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $unpaidPayment = $conn->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'unpaid' OR payment_status = 'pending'")->fetchColumn();

    $revenueStmt = $conn->query("
        SELECT SUM(s.price)
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.payment_status = 'paid'
    ");
    $totalRevenue = (float)$revenueStmt->fetchColumn();

    /* ========================================================
       2. RECENT INSTANT PAYMENTS REVENUE LOG (Last 5 Entries)
    ======================================================== */
    $recentPayments = $conn->query("
        SELECT b.id as booking_id, b.transaction_id, b.booking_date, s.name as service_name, s.price, u.username as customer_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.payment_status = 'paid'
        ORDER BY b.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* ========================================================
       3. CHART: RATING DISTRIBUTION (Pie Chart)
    ======================================================== */
    $ratingRows = $conn->query("
        SELECT rating, COUNT(*) as total
        FROM feedback
        GROUP BY rating
        ORDER BY rating ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $ratingLabels = [];
    $ratingData   = [];
    foreach ($ratingRows as $row) {
        $ratingLabels[] = $row['rating'] . ' Star' . ($row['rating'] != 1 ? 's' : '');
        $ratingData[]   = (int)$row['total'];
    }

    $avgRating = $conn->query("SELECT ROUND(AVG(rating), 1) FROM feedback")->fetchColumn();
    $totalFeedbacks = $conn->query("SELECT COUNT(*) FROM feedback")->fetchColumn();

    /* ========================================================
       4. CHART: MOST BOOKED SERVICES (Bar Chart)
    ======================================================== */
    $serviceRows = $conn->query("
        SELECT s.name as service_name, COUNT(b.id) as total_bookings
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        GROUP BY s.id, s.name
        ORDER BY total_bookings DESC
        LIMIT 7
    ")->fetchAll(PDO::FETCH_ASSOC);

    $serviceLabels = [];
    $serviceData   = [];
    foreach ($serviceRows as $row) {
        $serviceLabels[] = $row['service_name'];
        $serviceData[]   = (int)$row['total_bookings'];
    }

} catch (PDOException $e) {
    die("<div style='background:#fee2e2; color:#991b1b; padding:20px; border:1px solid #ef4444; font-family:sans-serif; margin:20px; border-radius:8px;'>
            <h3>❌ Metrics Aggregation System Error</h3>
            <code>" . htmlspecialchars($e->getMessage()) . "</code>
         </div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Sunflower Command</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css?v=<?= time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --gold: #c9a84c; --gold-light: #f4d03f; --gold-pale: #fdf8ec;
            --dark: #1a1208; --text: #3d2e0e; --text-muted: #8a7355;
            --border: #e8d9b5; --white: #fffef9; --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); min-height: 100vh; display: flex; }

        .admin-layout { display: flex; width: 100%; min-height: 100vh; }

        .sidebar { width: 260px; background: var(--dark); position: fixed; height: 100vh; left: 0; top: 0; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.15); z-index: 100; }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
        .brand-logo { width: 36px; height: 36px; background: var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 6px; padding: 0 16px; flex-grow: 1; }
        .nav-links a { text-decoration: none; font-size: 0.95rem; font-weight: 500; color: #c4b08a; padding: 12px 18px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
        .nav-links a:hover, .nav-links a.active { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-links a.logout { color: #e57373; margin-top: auto; margin-bottom: 24px; }
        
        .main-content { margin-left: 260px; padding: 40px 50px; flex-grow: 1; }

        /* METRICS GRID */
        .metrics-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px; margin-bottom: 35px;
        }
        .stat-card {
            background: var(--glass-bg); backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border); padding: 25px; border-radius: 12px;
            box-shadow: var(--card-shadow); display: flex; flex-direction: column; gap: 8px;
        }
        .stat-title { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); }
        .stat-card.revenue-card { background: #dcfce7; border-color: #bbf7d0; }
        .stat-card.revenue-card .stat-number { color: #166534; }

        /* CHARTS ROW */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        .content-card {
            background: var(--glass-bg); backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border); padding: 30px; border-radius: 16px;
            box-shadow: var(--card-shadow); margin-bottom: 30px;
        }
        .content-card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 700; font-size: 1.1rem; }

        /* Chart container heights */
        .chart-wrap {
            position: relative;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Rating summary badges below pie */
        .rating-summary {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
        }
        .rating-summary-item { text-align: center; }
        .rating-summary-item .rs-val { font-size: 1.4rem; font-weight: 800; color: var(--text-dark); }
        .rating-summary-item .rs-lbl { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        /* Data table */
        .data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .data-table th { padding: 14px; border-bottom: 2px solid #e5e7eb; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; }
        .data-table td { padding: 14px; border-bottom: 1px solid #edf2f7; font-size: 0.95rem; }
        .data-table tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }

        /* No-data placeholder */
        .no-data-msg {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 200px; color: var(--text-muted); font-size: 0.95rem; gap: 8px;
        }
        .no-data-msg span { font-size: 2rem; }

        @media (max-width: 1200px) {
            .charts-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 1024px) {
            .sidebar { width: 80px; padding: 20px 10px; align-items: center; }
            .brand span, .nav-links a span { display: none; }
            .main-content { margin-left: 80px; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <nav class="sidebar">
        <div class="brand">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
         <nav class="nav-links">
        <a href="dashboard.php"class="active">Dashboard</a>
        <a href="bookings.php">Manage Reservation</a>
        <a href="assign/massagers.php">Manage Massagers</a>
        <a href="service.php">Manage Services</a>
        <a href="transactions.php">Manage Payments</a>
        <a href="availability.php">Manage Availability</a>
        <a href="feedback.php" >Manage Feedback</a>
        <a href="reports.php">Generate Reports</a>
        <a href="../auth/logout.php" class="logout"><span>🚪</span> <span>Logout</span></a>
    </nav>
    </nav>

    <main class="main-content">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0; font-weight: 800; font-size: 2rem;">Admin's Dashboard</h1>
            <p style="margin: 5px 0 0 0; color: var(--text-muted); font-weight: 500;"></p>
        </div>

        <!-- METRICS -->
        <div class="metrics-grid">
            <div class="stat-card revenue-card">
                <span class="stat-title">Gross Revenue</span>
                <span class="stat-number">RM <?= number_format($totalRevenue, 2) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title">Gateway Clearances</span>
                <span class="stat-number"><?= $paidPayment ?> Paid</span>
            </div>
            <div class="stat-card">
                <span class="stat-title">Unpaid Invoices</span>
                <span class="stat-number"><?= $unpaidPayment ?> Awaiting</span>
            </div>
            <div class="stat-card">
                <span class="stat-title">Total Bookings</span>
                <span class="stat-number"><?= $totalBookings ?> Sessions</span>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="charts-row">

            <!-- PIE CHART: Rating Distribution -->
            <div class="content-card" style="margin-bottom:0;">
                <h3>⭐ Customer Rating Distribution</h3>
                <?php if (empty($ratingData)): ?>
                    <div class="no-data-msg"><span>📭</span>No ratings submitted yet.</div>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="ratingPieChart"></canvas>
                    </div>
                    <div class="rating-summary">
                        <div class="rating-summary-item">
                            <div class="rs-val">⭐ <?= $avgRating ?? '—' ?></div>
                            <div class="rs-lbl">Avg Rating</div>
                        </div>
                        <div class="rating-summary-item">
                            <div class="rs-val"><?= $totalFeedbacks ?></div>
                            <div class="rs-lbl">Total Reviews</div>
                        </div>
                        <div class="rating-summary-item">
                            <div class="rs-val"><?= !empty($ratingRows) ? end($ratingRows)['total'] : 0 ?></div>
                            <div class="rs-lbl">5-Star Reviews</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- BAR CHART: Most Booked Services -->
            <div class="content-card" style="margin-bottom:0;">
                <h3>🏆 Most Booked Services</h3>
                <?php if (empty($serviceData)): ?>
                    <div class="no-data-msg"><span>📭</span>No booking data yet.</div>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="serviceBarChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- end charts-row -->

        <!-- RECENT TRANSACTIONS TABLE -->
        <div class="content-card">
            <h3>💳 Live Payment Gateway Transaction Streams</h3>
            <?php if (empty($recentPayments)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No automated gateway authorizations recorded yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Selected Treatment</th>
                                <th>Date Issued</th>
                                <th>Amount</th>
                                <th>Gateway Reference ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($pay['customer_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($pay['service_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($pay['booking_date'])) ?></td>
                                    <td><strong>RM <?= number_format($pay['price'], 2) ?></strong></td>
                                    <td><code style="background:#f3f4f6; padding:4px 8px; border-radius:4px; font-weight:600; color:#1e40af;"><?= htmlspecialchars($pay['transaction_id']) ?></code></td>
                                    <td><span class="badge paid">Instant Success</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php if (!empty($ratingData)): ?>
<script>
// ── PIE CHART: Rating Distribution ──────────────────────────────────────────
const ratingLabels = <?= json_encode($ratingLabels) ?>;
const ratingData   = <?= json_encode($ratingData) ?>;

new Chart(document.getElementById('ratingPieChart'), {
    type: 'doughnut',
    data: {
        labels: ratingLabels,
        datasets: [{
            data: ratingData,
            backgroundColor: [
                '#ef4444', // 1 star – red
                '#f97316', // 2 star – orange
                '#eab308', // 3 star – yellow
                '#84cc16', // 4 star – lime
                '#22c55e', // 5 star – green
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: { family: 'Inter', size: 12, weight: '600' },
                    padding: 14,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.parsed} review${ctx.parsed !== 1 ? 's' : ''}`
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if (!empty($serviceData)): ?>
<script>
// ── BAR CHART: Most Booked Services ─────────────────────────────────────────
const serviceLabels = <?= json_encode($serviceLabels) ?>;
const serviceData   = <?= json_encode($serviceData) ?>;

// Truncate long service names for display
const displayLabels = serviceLabels.map(l => l.length > 18 ? l.substring(0, 16) + '…' : l);

new Chart(document.getElementById('serviceBarChart'), {
    type: 'bar',
    data: {
        labels: displayLabels,
        datasets: [{
            label: 'Total Bookings',
            data: serviceData,
            backgroundColor: [
                'rgba(244, 208, 63, 0.85)',
                'rgba(251, 191, 36, 0.85)',
                'rgba(245, 158, 11, 0.85)',
                'rgba(234, 179, 8,  0.85)',
                'rgba(163, 230, 53, 0.85)',
                'rgba(74,  222, 128,0.85)',
                'rgba(34,  197, 94, 0.85)',
            ],
            borderColor: [
                '#d4af37','#d4a017','#b45309','#a16207',
                '#65a30d','#16a34a','#15803d'
            ],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: (items) => serviceLabels[items[0].dataIndex],
                    label: ctx => ` ${ctx.parsed.y} booking${ctx.parsed.y !== 1 ? 's' : ''}`
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#6b7280' }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: { family: 'Inter', size: 11 },
                    color: '#6b7280'
                },
                grid: { color: 'rgba(0,0,0,0.05)' }
            }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>