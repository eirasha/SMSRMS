<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-t');

$stmt = $conn->prepare("
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
$stmt->execute([$start_date, $end_date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = array_sum(array_column($rows, 'price'));
$total_count   = count($rows);

// Logo as base64 (inline so PDF renderer gets it)
$logo_path = __DIR__ . '/../uploads/logo.png';
$logo_b64  = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}

$generated_date = date('d F Y');
$generated_time = date('h:i A');
$admin_name     = htmlspecialchars($_SESSION['username'] ?? 'Administrator');
$period_label   = date('d M Y', strtotime($start_date)) . ' &ndash; ' . date('d M Y', strtotime($end_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Report &mdash; Sunflower</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #fff;
            padding: 0;
        }

        /* ── TOP HEADER BAND ── */
        .header-band {
            background: linear-gradient(135deg, #f4d03f 0%, #e5c100 100%);
            padding: 28px 40px 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid #c9a800;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-circle {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.8);
            background: #fff;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .logo-placeholder {
            font-size: 26px;
            line-height: 1;
        }
        .brand-name {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a2e;
            letter-spacing: 0.5px;
        }
        .brand-tagline {
            font-size: 11px;
            color: #4a3f00;
            margin-top: 2px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .header-right {
            text-align: right;
        }
        .report-title {
            font-size: 18px;
            font-weight: 800;
            color: #1a1a2e;
        }
        .report-sub {
            font-size: 11px;
            color: #4a3f00;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ── META INFO BAR ── */
        .meta-bar {
            background: #1a1a2e;
            color: #f4d03f;
            padding: 10px 40px;
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .meta-bar span { color: #fff; font-weight: 400; margin-left: 4px; }

        /* ── BODY CONTENT ── */
        .body-content {
            padding: 30px 40px;
        }

        /* ── SUMMARY CARDS ── */
        .summary-row {
            display: flex;
            gap: 18px;
            margin-bottom: 28px;
        }
        .summary-card {
            flex: 1;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px 20px;
            background: #fafafa;
        }
        .summary-card.highlight {
            background: #fef9c3;
            border-color: #f4d03f;
        }
        .summary-card .sc-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .summary-card .sc-value {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a2e;
        }
        .summary-card.highlight .sc-value { color: #854d0e; }
        .summary-card .sc-desc {
            font-size: 10.5px;
            color: #9ca3af;
            margin-top: 3px;
        }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f4d03f;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title .dot {
            width: 8px; height: 8px;
            background: #f4d03f;
            border-radius: 50%;
            display: inline-block;
        }

        /* ── PAYMENT TABLE ── */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .payment-table thead tr {
            background: #1a1a2e;
            color: #f4d03f;
        }
        .payment-table thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .payment-table tbody tr:nth-child(even) { background: #fafafa; }
        .payment-table tbody tr:hover { background: #fef9c3; }
        .payment-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .payment-table tbody tr:last-child td { border-bottom: none; }

        .txn-code {
            font-family: 'Courier New', monospace;
            font-size: 10.5px;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            color: #1d4ed8;
            font-weight: 600;
        }
        .badge-paid {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .amount-cell {
            font-weight: 700;
            color: #166534;
            white-space: nowrap;
        }

        /* ── TOTAL ROW ── */
        .total-row td {
            background: #1a1a2e !important;
            color: #f4d03f !important;
            font-weight: 800;
            font-size: 13px;
            padding: 12px !important;
            border-bottom: none !important;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #9ca3af;
        }
        .empty-state .es-icon { font-size: 40px; margin-bottom: 12px; }

        /* ── FOOTER ── */
        .report-footer {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1.5px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 10.5px;
            color: #9ca3af;
        }
        .report-footer .signature-block {
            text-align: right;
        }
        .report-footer .signature-line {
            border-top: 1px solid #6b7280;
            width: 180px;
            margin-left: auto;
            margin-top: 30px;
            padding-top: 5px;
            font-size: 10px;
            color: #6b7280;
        }

        /* ── PRINT STYLES ── */
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .header-band { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .meta-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .payment-table thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-card.highlight { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* ── ACTION BUTTONS (screen only) ── */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 24px;
            display: flex;
            gap: 10px;
            z-index: 99;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print  { background: #1a1a2e; color: #f4d03f; }
        .btn-back   { background: #f3f4f6; color: #374151; }
        .btn:hover  { opacity: 0.88; }
    </style>
</head>
<body>

<!-- Action buttons (hidden on print) -->
<div class="action-bar no-print">
    <a href="reports.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-back">← Back</a>
    <button onclick="window.print()" class="btn btn-print">🖨️ Print / Save as PDF</button>
</div>

<!-- ══ HEADER BAND ══ -->
<div class="header-band">
    <div class="header-left">
        <div class="logo-circle">
            <?php if ($logo_b64): ?>
                <img src="<?= $logo_b64 ?>" alt="Sunflower Logo">
            <?php else: ?>
                <span class="logo-placeholder">🌻</span>
            <?php endif; ?>
        </div>
        <div>
            <div class="brand-name">SUNFLOWER</div>
            <div class="brand-tagline">Massage &amp; Relaxation Center</div>
        </div>
    </div>
    <div class="header-right">
        <div class="report-title">💳 Payment Report</div>
        <div class="report-sub">Official Financial Statement</div>
    </div>
</div>

<!-- ══ META INFO BAR ══ -->
<div class="meta-bar">
    <div>📅 Report Period: <span><?= $period_label ?></span></div>
    <div>🕐 Generated: <span><?= $generated_date ?>, <?= $generated_time ?></span></div>
    <div>👤 Prepared by: <span><?= $admin_name ?></span></div>
</div>

<!-- ══ BODY ══ -->
<div class="body-content">

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="summary-card highlight">
            <div class="sc-label">Total Revenue (Paid)</div>
            <div class="sc-value">RM <?= number_format($total_revenue, 2) ?></div>
            <div class="sc-desc">From successful gateway clearances</div>
        </div>
        <div class="summary-card">
            <div class="sc-label">Total Transactions</div>
            <div class="sc-value"><?= $total_count ?></div>
            <div class="sc-desc">Paid bookings in selected period</div>
        </div>
        <div class="summary-card">
            <div class="sc-label">Average Per Transaction</div>
            <div class="sc-value">RM <?= $total_count > 0 ? number_format($total_revenue / $total_count, 2) : '0.00' ?></div>
            <div class="sc-desc">Revenue per paid booking</div>
        </div>
    </div>

    <!-- Table -->
    <div class="section-title">
        <span class="dot"></span> Payment Transaction Ledger
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <div class="es-icon">📭</div>
            <div>No paid transactions found for this period.</div>
        </div>
    <?php else: ?>
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
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td style="color:#9ca3af; font-size:11px;"><?= $i + 1 ?></td>
                        <td style="font-weight:700;">#<?= $row['booking_id'] ?></td>
                        <td>
                            <?php if ($row['transaction_id']): ?>
                                <span class="txn-code"><?= htmlspecialchars($row['transaction_id']) ?></span>
                            <?php else: ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                            <span style="color:#9ca3af; font-size:10.5px;"><?= htmlspecialchars($row['customer_email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($row['service_name']) ?></td>
                        <td style="white-space:nowrap;"><?= date('d M Y', strtotime($row['booking_date'])) ?></td>
                        <td style="white-space:nowrap; color:#6b7280;">
                            <?= $row['booking_time'] ? date('h:i A', strtotime($row['booking_time'])) : '—' ?>
                        </td>
                        <td class="amount-cell">RM <?= number_format($row['price'], 2) ?></td>
                        <td><span class="badge-paid">✓ Paid</span></td>
                    </tr>
                <?php endforeach; ?>
                <!-- Total row -->
                <tr class="total-row">
                    <td colspan="7" style="text-align:right;">TOTAL REVENUE</td>
                    <td>RM <?= number_format($total_revenue, 2) ?></td>
                    <td><?= $total_count ?> txns</td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="report-footer">
        <div>
            <div>Sunflower Massage &amp; Relaxation Center</div>
            <div style="margin-top:3px;">This report is system-generated and valid without a physical signature.</div>
            <div style="margin-top:2px; color:#d1d5db;">Generated on <?= $generated_date ?> at <?= $generated_time ?></div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Authorized Signature / Admin Stamp</div>
        </div>
    </div>

</div><!-- end body-content -->

<script>
// Auto-trigger print dialog when page loads (optional — remove if unwanted)
// window.onload = function() { window.print(); };
</script>
</body>
</html>