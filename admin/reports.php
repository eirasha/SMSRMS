<?php
session_start();
require_once __DIR__ . '/../config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-t');

try {
    /* ── 1. SUMMARY METRICS ── */
    $stmt_rev = $conn->prepare("
        SELECT SUM(s.price) as total_rev
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        WHERE b.payment_status = 'paid'
          AND b.booking_date BETWEEN ? AND ?
    ");
    $stmt_rev->execute([$start_date, $end_date]);
    $total_revenue = (float)($stmt_rev->fetch(PDO::FETCH_ASSOC)['total_rev'] ?? 0.00);

    $stmt_count = $conn->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE payment_status = 'paid'
          AND booking_date BETWEEN ? AND ?
    ");
    $stmt_count->execute([$start_date, $end_date]);
    $total_count = (int)$stmt_count->fetchColumn();

    $avg_per_txn = $total_count > 0 ? $total_revenue / $total_count : 0;

    /* ── 2. FULL PAYMENT TRANSACTION TABLE ── */
    $stmt_txn = $conn->prepare("
        SELECT
            b.id            AS booking_id,
            b.transaction_id,
            u.username      AS customer_name,
            u.email         AS customer_email,
            s.name          AS service_name,
            s.price,
            b.booking_date,
            b.booking_time,
            b.payment_status
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users    u ON b.customer_id = u.id
        WHERE b.payment_status = 'paid'
          AND b.booking_date BETWEEN ? AND ?
        ORDER BY b.booking_date DESC, b.id DESC
    ");
    $stmt_txn->execute([$start_date, $end_date]);
    $transactions = $stmt_txn->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='background:#fee2e2;color:#991b1b;padding:20px;border:1px solid #ef4444;font-family:sans-serif;margin:20px;border-radius:8px;'>
            <h3>❌ Report Generation Error</h3>
            <code>" . htmlspecialchars($e->getMessage()) . "</code>
         </div>");
}

$generated_date = date('d F Y');
$generated_time = date('h:i A');
$admin_name     = htmlspecialchars($_SESSION['username'] ?? 'Administrator');
$period_label   = date('d M Y', strtotime($start_date)) . ' – ' . date('d M Y', strtotime($end_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Report | Sunflower Command</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css?v=<?= time() ?>">
    <style>
        :root { --gold: #c9a84c; --gold-light: #f4d03f; --gold-pale: #fdf8ec; --dark: #1a1208; --text: #3d2e0e; --text-muted: #8a7355; --border: #e8d9b5; --white: #fffef9; --card-shadow: 0 4px 24px rgba(201,168,76,0.10); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gold-pale); color: var(--text); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--dark); position: fixed; height: 100vh; left: 0; top: 0; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.15); z-index: 100; }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        

        /* Sidebar */
.brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
    color: var(--gold-light);
    letter-spacing: 2px;
}

/* Report Header */
.report-brand-name {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--dark);
}



        .nav-links { display: flex; flex-direction: column; gap: 6px; padding: 0 16px; flex: 1; }
        .nav-links a { text-decoration: none; font-size: 0.95rem; font-weight: 500; color: #c4b08a; padding: 12px 18px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
        .nav-links a:hover, .nav-links a.active { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-links a.logout { color: #e57373; margin-top: auto; margin-bottom: 24px; font-weight: 600; transition: all 0.3s ease; }
        .nav-links a.logout:hover { background: rgba(229, 115, 115, 0.1); color: #ff8a8a; }
        .nav-links a.logout span { margin-right: 8px; }
        
        /* Main Content */
        .main-content { margin-left: 260px; padding: 40px 50px; flex-grow: 1; }
        

        /* ── FILTER PANEL ── */
        .filter-panel {
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            padding: 20px 24px; border-radius: 12px; margin-bottom: 28px;
            display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;
            box-shadow: var(--card-shadow);
        }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        .input-group label { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .date-input {
            padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 6px;
            font-family: inherit; font-size: 0.95rem; color: var(--text-dark); background: #fff;
        }
        .btn-action {
            padding: 11px 20px; border-radius: 6px; font-weight: 700; font-size: 0.9rem;
            cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none; white-space: nowrap;
        }
        .btn-search { background: var(--dark); color: #fff; }
        .btn-search:hover { background: #111827; }
        .btn-csv  { background: #16a34a; color: #fff; }
        .btn-csv:hover  { background: #15803d; }
        .btn-pdf  { background: #2563eb; color: #fff; margin-left: auto; }
        .btn-pdf:hover  { background: #1d4ed8; }

        /* ── REPORT CARD (wraps everything below filter) ── */
        .report-card {
            background: var(--glass-bg); backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border); border-radius: 16px;
            box-shadow: var(--card-shadow); overflow: hidden;
        }

        /* ── HEADER BAND (mirrors PDF) ── */
        .rpt-header {
            background: linear-gradient(135deg, #f4d03f 0%, #e5c100 100%);
            padding: 24px 32px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 4px solid #c9a800;
        }
        .rpt-header-left { display: flex; align-items: center; gap: 14px; }
        .logo-circle {
            width: 52px; height: 52px; border-radius: 50%;
            overflow: hidden; border: 3px solid rgba(255,255,255,0.8);
            background: #fff; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-circle img { width: 100%; height: 100%; object-fit: contain; }
        
        .brand-tagline { font-size: 0.72rem; color: #4a3f00; margin-top: 2px; font-weight: 600; }
        .rpt-header-right { text-align: right; }
        .rpt-title { font-size: 1.1rem; font-weight: 800; color: var(--dark); }
        .rpt-subtitle { font-size: 0.72rem; color: #4a3f00; margin-top: 3px; font-weight: 600; }

        /* ── META BAR (mirrors PDF) ── */
        .rpt-meta {
            background: var(--dark); color: var(--primary);
            padding: 10px 32px;
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;
            font-size: 0.78rem; font-weight: 700; letter-spacing: 0.2px;
        }
        .rpt-meta span { color: #fff; font-weight: 400; margin-left: 4px; }

        /* ── BODY INSIDE CARD ── */
        .rpt-body { padding: 28px 32px 32px; }

        /* ── SUMMARY CARDS (mirrors PDF) ── */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .sc {
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            padding: 18px 20px; background: #fafafa;
        }
        .sc.hl { background: #fef9c3; border-color: var(--primary); }
        .sc-label {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px;
        }
        .sc-value { font-size: 1.6rem; font-weight: 800; color: var(--dark); }
        .sc.hl .sc-value { color: #854d0e; }
        .sc-desc { font-size: 0.72rem; color: #9ca3af; margin-top: 3px; }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 0.85rem; font-weight: 800; color: var(--dark);
            margin-bottom: 14px; padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex; align-items: center; gap: 8px;
        }
        .section-title .dot {
            width: 8px; height: 8px; background: var(--primary);
            border-radius: 50%; display: inline-block; flex-shrink: 0;
        }

        /* ── PAYMENT TABLE (mirrors PDF) ── */
        .payment-table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
        .payment-table thead tr { background: var(--dark); }
        .payment-table thead th {
            padding: 11px 13px; text-align: left;
            color: var(--primary); font-weight: 700;
            font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .payment-table tbody tr:nth-child(even) { background: rgba(0,0,0,0.018); }
        .payment-table tbody tr:hover { background: #fef9c3; transition: background 0.15s; }
        .payment-table tbody td {
            padding: 11px 13px; border-bottom: 1px solid #f0f0f0; vertical-align: middle;
        }
        .payment-table tbody tr:last-child td { border-bottom: none; }

        .txn-code {
            font-family: 'Courier New', monospace; font-size: 0.78rem;
            background: #f3f4f6; padding: 2px 7px; border-radius: 4px;
            color: #1d4ed8; font-weight: 600;
        }
        .badge-paid {
            display: inline-block; background: #dcfce7; color: #166534;
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        }
        .amount-cell { font-weight: 700; color: #166534; white-space: nowrap; }
        .no-val { color: #d1d5db; }

        /* Total row */
        .total-row td {
            background: var(--dark) !important;
            color: var(--primary) !important;
            font-weight: 800; font-size: 0.9rem;
            padding: 13px !important; border-bottom: none !important;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 50px 20px; color: var(--text-muted);
        }
        .empty-state .es-icon { font-size: 2.5rem; margin-bottom: 10px; }

        /* ── REPORT FOOTER (mirrors PDF) ── */
        .rpt-footer {
            margin-top: 32px; padding-top: 16px;
            border-top: 1.5px solid #e5e7eb;
            display: flex; justify-content: space-between; align-items: flex-end;
            font-size: 0.75rem; color: #9ca3af;
        }
        .signature-line {
            border-top: 1px solid #9ca3af; width: 200px;
            margin-left: auto; margin-top: 36px;
            padding-top: 5px; font-size: 0.7rem;
            color: #6b7280; text-align: center;
        }

        .export-buttons{
    display:flex;
    gap:10px;
    margin-left:auto;
}

        @media (max-width: 1024px) {
            .sidebar { width: 80px; padding: 20px 10px; align-items: center; }
            .brand span, .nav-links a span { display: none; }
            .main-content { margin-left: 80px; padding: 20px; }
            .summary-row { grid-template-columns: 1fr; }
            .rpt-meta { flex-direction: column; gap: 4px; }
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
        <a href="dashboard.php">Dashboard</a>
        <a href="bookings.php">Manage Reservation</a>
        <a href="assign/massagers.php">Manage Massagers</a>
        <a href="service.php">Manage Services</a>
        <a href="transactions.php">Manage Payments</a>
        <a href="availability.php">Manage Availability</a>
        <a href="feedback.php" >Manage Feedback</a>
        <a href="reports.php" class="active">Generate Reports</a>
        <a href="../auth/logout.php" class="logout"><span>🚪</span> <span>Logout</span></a>
    </nav>
    </nav>

    <main class="main-content">
        <div style="margin-bottom:24px;">
            <h1 style="margin:0; font-weight:800; font-size:2rem;">Payment Report</h1>
            <p style="margin:5px 0 0; color:var(--text-muted); font-weight:500;">Filter by date range, then export as PDF or CSV.</p>
        </div>

        <!-- FILTER + EXPORT BAR -->
        <form method="get" class="filter-panel">
            <div class="input-group">
                <label>Start Date</label>
                <input type="date" name="start_date" class="date-input" value="<?= htmlspecialchars($start_date) ?>" required>
            </div>
            <div class="input-group">
                <label>End Date</label>
                <input type="date" name="end_date" class="date-input" value="<?= htmlspecialchars($end_date) ?>" required>
            </div>
            <button type="submit" class="btn-action btn-search">🔍</button>
            <div class="export-buttons">
    <a id="btn-csv"
       href="export_payment_csv.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
       class="btn-action btn-csv">
       ⬇️ CSV
    </a>

    <a id="btn-pdf"
       href="export_payment_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
       target="_blank"
       class="btn-action btn-pdf">
       🖨️ PDF
    </a>
</div>
        </form>

        <!-- ══ REPORT CARD ══ -->
        <div class="report-card">

            <!-- Header Band -->
            <div class="rpt-header">
                <div class="rpt-header-left">
                    <div class="logo-circle">
                        <img src="../uploads/logo.png" alt="Sunflower Logo"
                             onerror="this.style.display='none'; this.parentElement.innerHTML='🌻';">
                    </div>
                    <div>
                        <div class="report-brand-name">SUNFLOWER</div>
                        <div class="brand-tagline">Massage &amp; Relaxation Center</div>
                    </div>
                </div>
                <div class="rpt-header-right">
                    <div class="rpt-title">💳 Payment Report</div>
                    <div class="rpt-subtitle">Official Financial Statement</div>
                </div>
            </div>

            <!-- Meta Bar -->
            <div class="rpt-meta">
                <div>📅 Report Period: <span><?= htmlspecialchars($period_label) ?></span></div>
                <div>🕐 Generated: <span><?= $generated_date ?>, <?= $generated_time ?></span></div>
                <div>👤 Prepared by: <span><?= $admin_name ?></span></div>
            </div>

            <!-- Body -->
            <div class="rpt-body">

                <!-- Summary Cards -->
                <div class="summary-row">
                    <div class="sc hl">
                        <div class="sc-label">Total Revenue (Paid)</div>
                        <div class="sc-value">RM <?= number_format($total_revenue, 2) ?></div>
                        <div class="sc-desc">From successful gateway clearances</div>
                    </div>
                    <div class="sc">
                        <div class="sc-label">Total Transactions</div>
                        <div class="sc-value"><?= $total_count ?></div>
                        <div class="sc-desc">Paid bookings in selected period</div>
                    </div>
                    <div class="sc">
                        <div class="sc-label">Average Per Transaction</div>
                        <div class="sc-value">RM <?= number_format($avg_per_txn, 2) ?></div>
                        <div class="sc-desc">Revenue per paid booking</div>
                    </div>
                </div>

                <!-- Transaction Table -->
                <div class="section-title">
                    <span class="dot"></span> Payment Transaction Ledger
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="es-icon">📭</div>
                        <div>No paid transactions found for this period.</div>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Booking ID</th>
                                    <th>Transaction ID</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Amount (RM)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $i => $row): ?>
                                    <tr>
                                        <td style="color:#9ca3af; font-size:0.8rem;"><?= $i + 1 ?></td>
                                        <td style="font-weight:700;">#<?= $row['booking_id'] ?></td>
                                        <td>
                                            <?php if ($row['transaction_id']): ?>
                                                <span class="txn-code"><?= htmlspecialchars($row['transaction_id']) ?></span>
                                            <?php else: ?>
                                                <span class="no-val">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                                            <span style="color:#9ca3af; font-size:0.75rem;"><?= htmlspecialchars($row['customer_email']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['service_name']) ?></td>
                                        <td style="white-space:nowrap;"><?= date('d M Y', strtotime($row['booking_date'])) ?></td>
                                        <td style="white-space:nowrap; color:var(--text-muted);">
                                            <?= $row['booking_time'] ? date('h:i A', strtotime($row['booking_time'])) : '<span class="no-val">—</span>' ?>
                                        </td>
                                        <td class="amount-cell">RM <?= number_format($row['price'], 2) ?></td>
                                        <td><span class="badge-paid">✓ Paid</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="7" style="text-align:right;">TOTAL REVENUE</td>
                                    <td>RM <?= number_format($total_revenue, 2) ?></td>
                                    <td><?= $total_count ?> txns</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="rpt-footer">
                    <div>
                        <div style="font-weight:600; color:var(--text-dark);">Sunflower Massage &amp; Relaxation Center</div>
                        <div style="margin-top:3px;">This report is system-generated and valid without a physical signature.</div>
                        <div style="margin-top:2px;">Generated on <?= $generated_date ?> at <?= $generated_time ?></div>
                    </div>
                    <div>
                        <div class="signature-line">Authorized Signature / Admin Stamp</div>
                    </div>
                </div>

            </div><!-- end rpt-body -->
        </div><!-- end report-card -->
    </main>
</div>

<script>
(function () {
    const s = document.querySelector('input[name="start_date"]');
    const e = document.querySelector('input[name="end_date"]');
    const csv = document.getElementById('btn-csv');
    const pdf = document.getElementById('btn-pdf');
    function sync() {
        const sv = encodeURIComponent(s.value);
        const ev = encodeURIComponent(e.value);
        if (csv) csv.href = 'export_payment_csv.php?start_date=' + sv + '&end_date=' + ev;
        if (pdf) pdf.href = 'export_payment_pdf.php?start_date=' + sv + '&end_date=' + ev;
    }
    if (s) s.addEventListener('change', sync);
    if (e) e.addEventListener('change', sync);
})();
</script>
</body>
</html>