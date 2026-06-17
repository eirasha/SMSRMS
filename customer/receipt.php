<?php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$booking_id  = intval($_GET['id'] ?? 0);

if (!$booking_id) {
    header("Location: payment.php");
    exit;
}

// Fetch booking details — only if it belongs to this customer and is paid
$stmt = $conn->prepare("
    SELECT
        b.id            AS booking_id,
        b.transaction_id,
        b.booking_date,
        b.booking_time,
        b.payment_status,
        b.status        AS booking_status,
        b.created_at    AS booked_at,
        s.name          AS service_name,
        s.price,
        s.duration,
        u.username      AS customer_name,
        u.email         AS customer_email,
        p.created_at    AS paid_at,
        p.proof_path,
        m.name          AS massager_name
    FROM bookings b
    JOIN services  s ON b.service_id    = s.id
    JOIN users     u ON b.customer_id   = u.id
    LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'verified'
    LEFT JOIN massagers m ON b.massager_id = m.user_id
    WHERE b.id = ?
      AND b.customer_id = ?
      AND b.payment_status = 'paid'
");
$stmt->execute([$booking_id, $customer_id]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    // Not found or not paid — redirect back
    header("Location: payment.php");
    exit;
}

// Logo as base64 so it embeds correctly when printed/saved as PDF
$logo_path = __DIR__ . '/../uploads/logo.png';
$logo_b64  = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}

$generated_on = date('d F Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $rec['booking_id'] ?> | Sunflower</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold:       #c9a84c;
            --gold-light: #f4d03f;
            --gold-pale:  #fdf8ec;
            --dark:       #1a1208;
            --text:       #3d2e0e;
            --text-muted: #8a7355;
            --border:     #e8d9b5;
            --white:      #fffef9;
            --green:      #2d6a4f;
            --green-light:#d8f3dc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gold-pale);
            color: var(--text);
            min-height: 100vh;
            padding: 40px 20px;
        }

        /* ── SCREEN-ONLY TOOLBAR ── */
        .toolbar {
            max-width: 720px;
            margin: 0 auto 20px auto;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .toolbar a, .toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-back {
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border) !important;
        }
        .btn-back:hover { background: var(--border); }
        .btn-print {
            background: var(--gold);
            color: var(--dark);
        }
        .btn-print:hover {
            background: #b8942e;
            box-shadow: 0 4px 12px rgba(201,168,76,0.35);
        }

        /* ── RECEIPT CARD ── */
        .receipt {
            max-width: 720px;
            margin: 0 auto;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(201,168,76,0.15);
            overflow: hidden;
        }

        /* Header band */
        .receipt-header {
            background: linear-gradient(135deg, #1a1208 0%, #2d2010 100%);
            padding: 32px 40px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .receipt-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .receipt-brand img {
            height: 54px;
            width: 54px;
            object-fit: contain;
            border-radius: 50%;
            border: 2px solid var(--gold);
        }
        .receipt-brand-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--gold-light);
            letter-spacing: 3px;
        }
        .receipt-brand-text p {
            font-size: 0.75rem;
            color: #a89070;
            margin-top: 3px;
            letter-spacing: 1px;
        }
        .receipt-title-block {
            text-align: right;
        }
        .receipt-title-block h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--gold-light);
            letter-spacing: 2px;
        }
        .receipt-title-block .receipt-no {
            font-size: 0.78rem;
            color: #a89070;
            margin-top: 4px;
            font-family: monospace;
        }

        /* Gold divider */
        .gold-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--gold-light), var(--gold), var(--gold-light));
        }

        /* Paid stamp area */
        .paid-banner {
            background: var(--green-light);
            border-bottom: 1px solid #b7e4c7;
            padding: 12px 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .paid-banner .stamp {
            background: var(--green);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 2px;
            padding: 4px 12px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .paid-banner span {
            font-size: 0.82rem;
            color: var(--green);
            font-weight: 500;
        }

        /* Body */
        .receipt-body {
            padding: 36px 40px;
        }

        /* Two-column info */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px 40px;
            margin-bottom: 32px;
        }
        .info-block label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 4px;
        }
        .info-block .val {
            font-size: 0.92rem;
            color: var(--dark);
            font-weight: 500;
        }
        .info-block .val.mono {
            font-family: monospace;
            font-size: 0.85rem;
        }

        /* Service line items */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .items-table thead tr {
            background: var(--gold-pale);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .items-table th {
            padding: 10px 14px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            text-align: left;
        }
        .items-table th:last-child { text-align: right; }
        .items-table td {
            padding: 16px 14px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f0e8d0;
            vertical-align: top;
        }
        .items-table td:last-child { text-align: right; }
        .item-name { font-weight: 600; color: var(--dark); }
        .item-sub  { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }
        .price-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            color: var(--green);
            font-weight: 700;
        }

        /* Total row */
        .total-row {
            display: flex;
            justify-content: flex-end;
            padding: 18px 14px 0;
            gap: 40px;
            align-items: center;
        }
        .total-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }
        .total-amount {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--green);
            font-weight: 700;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px dashed var(--border);
            margin: 28px 0;
        }

        /* Footer note */
        .receipt-footer {
            text-align: center;
            padding: 24px 40px 32px;
            border-top: 1px solid var(--border);
        }
        .receipt-footer p {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.7;
        }
        .receipt-footer .thank-you {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--gold);
            margin-bottom: 8px;
        }
        .receipt-footer .generated {
            margin-top: 16px;
            font-size: 0.7rem;
            color: #c5b590;
        }

        /* ── PRINT STYLES ── */
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .receipt {
                box-shadow: none;
                border: none;
                border-radius: 0;
                max-width: 100%;
            }
        }

        @media (max-width: 600px) {
            .receipt-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .receipt-title-block { text-align: left; }
            .receipt-body { padding: 24px 20px; }
            .info-grid { grid-template-columns: 1fr; gap: 16px; }
            .receipt-footer { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="toolbar">
    <a href="payment.php" class="btn-back">
        ← Back to Payments
    </a>
    <button class="btn-print" onclick="window.print()">
        🖨️ Print / Save PDF
    </button>
</div>

<!-- Receipt Card -->
<div class="receipt">

    <!-- Header -->
    <div class="receipt-header">
        <div class="receipt-brand">
            <?php if ($logo_b64): ?>
                <img src="<?= $logo_b64 ?>" alt="Sunflower Logo">
            <?php endif; ?>
            <div class="receipt-brand-text">
                <h1>SUNFLOWER</h1>
                <p>MASSAGE & SPA</p>
            </div>
        </div>
        <div class="receipt-title-block">
            <h2>PAYMENT RECEIPT</h2>
            <div class="receipt-no">Receipt #RCP-<?= str_pad($rec['booking_id'], 5, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="gold-bar"></div>

    <!-- Paid stamp -->
    <div class="paid-banner">
        <span class="stamp">✔ Paid</span>
        <span>Payment verified &amp; confirmed</span>
    </div>

    <div class="receipt-body">

        <!-- Customer & Booking Info -->
        <div class="info-grid">
            <div class="info-block">
                <label>Customer Name</label>
                <div class="val"><?= htmlspecialchars($rec['customer_name']) ?></div>
            </div>
            <div class="info-block">
                <label>Email</label>
                <div class="val"><?= htmlspecialchars($rec['customer_email']) ?></div>
            </div>
            <div class="info-block">
                <label>Booking Date</label>
                <div class="val"><?= date('d F Y', strtotime($rec['booking_date'])) ?></div>
            </div>
            <div class="info-block">
                <label>Booking Time</label>
                <div class="val"><?= date('g:i A', strtotime($rec['booking_time'])) ?></div>
            </div>
            <?php if ($rec['massager_name']): ?>
            <div class="info-block">
                <label>Therapist</label>
                <div class="val"><?= htmlspecialchars($rec['massager_name']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-block">
                <label>Payment Date</label>
                <div class="val">
                    <?= $rec['paid_at'] ? date('d F Y, h:i A', strtotime($rec['paid_at'])) : date('d F Y', strtotime($rec['booking_date'])) ?>
                </div>
            </div>
            <?php if ($rec['transaction_id']): ?>
            <div class="info-block" style="grid-column: 1 / -1;">
                <label>Transaction Reference</label>
                <div class="val mono"><?= htmlspecialchars($rec['transaction_id']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <hr class="divider">

        <!-- Service Line Item -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-name"><?= htmlspecialchars($rec['service_name']) ?></div>
                        <?php if ($rec['duration']): ?>
                        <div class="item-sub"><?= htmlspecialchars($rec['duration']) ?> mins session</div>
                        <?php endif; ?>
                    </td>
                    <td>1</td>
                    <td>
                        <span class="price-val">RM <?= number_format($rec['price'], 2) ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="total-row">
            <div class="total-label">Total Paid</div>
            <div class="total-amount">RM <?= number_format($rec['price'], 2) ?></div>
        </div>

    </div>

    <!-- Footer -->
    <div class="receipt-footer">
        <div class="thank-you">Thank you for choosing Sunflower</div>
        <p>
            We appreciate your trust in our services.<br>
            Please retain this receipt for your records.
        </p>
        <div class="generated">Generated on <?= $generated_on ?></div>
    </div>

</div>

</body>
</html>
