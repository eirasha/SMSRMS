<?php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$master_slots = [
    '09:00:00' => '09:00 AM',
    '11:00:00' => '11:00 AM',
    '14:00:00' => '02:00 PM',
    '16:00:00' => '04:00 PM'
];

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Security token expired.']);
        exit;
    }

    if ($_POST['action'] === 'fetch_day_status') {
        $date = trim($_POST['date'] ?? '');

        $stmtBookings = $conn->prepare("
            SELECT b.booking_time, u.username AS customer_name
            FROM bookings b
            LEFT JOIN users u ON b.customer_id = u.id
            WHERE b.massager_id = ? AND b.booking_date = ?
              AND b.status != 'cancelled' AND b.payment_status NOT IN ('failed', 'refunded')
        ");
        $stmtBookings->execute([$massager_id, $date]);
        $bookings = [];
        foreach ($stmtBookings->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookings[$row['booking_time']] = $row['customer_name'];
        }

        $stmtBlocks = $conn->prepare("
            SELECT available_start, block_reason FROM massager_availability
            WHERE massager_id = ? AND available_date = ? AND slot_type = 'blocked'
        ");
        $stmtBlocks->execute([$massager_id, $date]);
        $blocks = [];
        foreach ($stmtBlocks->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $blocks[$row['available_start']] = $row['block_reason'] ?? '';
        }

        $slot_data = [];
        foreach ($master_slots as $time_24 => $time_12) {
            if (isset($bookings[$time_24])) {
                $state = 'booked';
                $meta  = 'Booked by ' . $bookings[$time_24];
                $block_reason = '';
            } elseif (array_key_exists($time_24, $blocks)) {
                $state = 'blocked';
                $meta  = 'Blocked by you';
                $block_reason = $blocks[$time_24];
            } else {
                $state = 'available';
                $meta  = 'Open';
                $block_reason = '';
            }
            $slot_data[] = ['time_24' => $time_24, 'time_12' => $time_12, 'state' => $state, 'meta' => $meta, 'block_reason' => $block_reason];
        }

        echo json_encode(['status' => 'success', 'slots' => $slot_data]);
        exit;
    }

    if ($_POST['action'] === 'save_blocks') {
        $date = trim($_POST['date'] ?? '');
        $blocked_times = $_POST['blocks'] ?? [];
        $block_reasons = $_POST['block_reasons'] ?? [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
            exit;
        }

        $valid_times = array_keys($master_slots);
        $blocked_times = array_filter($blocked_times, fn($t) => in_array($t, $valid_times));

        try {
            $conn->beginTransaction();

            $conn->prepare("DELETE FROM massager_availability WHERE massager_id = ? AND available_date = ? AND slot_type = 'blocked'")
                 ->execute([$massager_id, $date]);

            if (!empty($blocked_times)) {
                $insStmt = $conn->prepare("INSERT INTO massager_availability (massager_id, available_date, available_start, available_end, slot_type, block_reason) VALUES (?, ?, ?, ?, 'blocked', ?)");
                foreach ($blocked_times as $time) {
                    $end_time = date('H:i:s', strtotime($time) + 3600);
                    $reason = trim($block_reasons[$time] ?? '');
                    $insStmt->execute([$massager_id, $date, $time, $end_time, $reason]);
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Availability updated successfully!']);

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("availability.php save_blocks error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability | Sunflower Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --gold: #c9a84c;
            --gold-light: #f4d03f;
            --gold-pale: #fdf8ec;
            --dark: #1a1208;
            --text: #3d2e0e;
            --text-muted: #8a7355;
            --border: #e8d9b5;
            --white: #fffef9;
            --green: #2d6a4f;
            --red: #c0392b;
            --red-light: #fdecea;
            --amber: #b7791f;
            --sidebar-w: 260px;
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

        /* ── CONSISTENT SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w); background: var(--dark); position: fixed; height: 100vh;
            left: 0; top: 0; display: flex; flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.15); z-index: 100;
        }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .brand img { height: 40px; width: 40px; object-fit: contain; border-radius: 50%; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--gold-light); letter-spacing: 2px; }
        .nav-links { display: flex; flex-direction: column; gap: 6px; padding: 20px 16px; flex-grow: 1; }
        .nav-links a { text-decoration: none; font-size: 0.95rem; font-weight: 500; color: #c4b08a; padding: 12px 18px; border-radius: 8px; display: flex; align-items: center; gap: 12px; transition: all 0.2s;}
        .nav-links a:hover { color: var(--gold-light); background: rgba(244,208,63,0.08); }
        .nav-links a.active { color: var(--gold-light); background: rgba(244,208,63,0.08); }

        /* ── MAIN CONTENT ── */
        .main-content { 
            margin-left: var(--sidebar-w); 
            flex: 1; 
            padding: 40px 50px; 
            max-width: calc(100% - var(--sidebar-w)); 
        }

        .page-header { 
            margin-bottom: 35px; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-end; 
        }
        .page-header h1 { 
            font-family: 'Playfair Display', serif; 
            font-size: 2.2rem; 
            color: var(--dark); 
        }
        .page-header p { 
            font-size: 0.95rem; 
            color: var(--text-muted); 
            margin-top: 6px; 
        }

        .card { 
            background: var(--white); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            box-shadow: var(--card-shadow); 
        }
        .card-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
        }
        .card-header h2 { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.25rem; 
            color: var(--dark); 
        }
        .card-body { padding: 32px; }

        .info-box { 
            background: var(--gold-pale); 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            padding: 16px 20px; 
            margin-bottom: 28px; 
            font-size: 0.9rem; 
            color: var(--text-muted); 
            line-height: 1.5; 
        }

        .form-group { margin-bottom: 28px; }
        .form-group label { 
            display: block; 
            font-size: 0.8rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: var(--text-muted); 
            margin-bottom: 10px; 
        }
        .form-control { 
            width: 100%; 
            padding: 14px 16px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-family: inherit; 
            font-size: 1rem; 
            color: var(--text); 
        }
        .form-control:focus { 
            border-color: var(--gold); 
            box-shadow: 0 0 0 3px rgba(201,168,76,0.12); 
            outline: none; 
        }

        .slot-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
            gap: 16px; 
        }
        .slot-cb { 
            display: block; 
            position: relative; 
            cursor: pointer; 
        }
        .slot-cb input { position: absolute; opacity: 0; width: 0; height: 0; }
        
        .slot-box { 
            border: 2px solid var(--border); 
            border-radius: 12px; 
            padding: 20px 12px; 
            background: var(--white); 
            text-align: center; 
            transition: all 0.25s; 
        }
        .slot-box:hover { border-color: var(--gold); }
        .slot-time { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.25rem; 
            font-weight: 700; 
            color: var(--dark); 
            margin-bottom: 6px; 
        }
        .slot-status { 
            font-size: 0.78rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .slot-cb input:checked ~ .slot-box { 
            border-color: var(--red); 
            background: var(--red-light); 
        }
        .slot-cb input:checked ~ .slot-box .slot-status { color: var(--red); }

        .slot-cb.is-booked { cursor: not-allowed; }
        .slot-cb.is-booked .slot-box { 
            background: var(--gold-pale); 
            opacity: 0.85; 
        }
        .slot-cb.is-booked .slot-time { 
            color: var(--text-muted); 
            text-decoration: line-through; 
        }
        .slot-cb.is-booked .slot-status { color: var(--amber); }

        .reason-row {
            display: none;
            margin-top: 8px;
        }
        .slot-cb input:checked ~ .slot-box .reason-row {
            display: block;
        }
        .reason-select {
            width: 100%;
            font-size: 0.72rem;
            padding: 5px 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            color: var(--text);
            background: #fff;
            cursor: pointer;
        }
        .reason-select:focus {
            border-color: var(--red);
            outline: none;
        }

        .btn-primary { 
            width: 100%; 
            padding: 14px; 
            background: var(--gold); 
            color: var(--dark); 
            border: none; 
            border-radius: 8px; 
            font-size: 1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .btn-primary:hover { background: var(--gold-light); }
        .btn-primary:disabled { background: #d1c4a8; cursor: not-allowed; }

        @media (max-width: 768px) {
            .main-content { padding: 24px 20px; }
            .slot-grid { grid-template-columns: 1fr 1fr; }
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
        <a href="dashboard.php"><span></span> <span>Dashboard</span></a>
        <a href="my_schedule.php"><span></span> <span>My Schedule</span></a>
        <a href="availability.php" class="active"><span></span> <span>Availability</span></a>
        <a href="feedback.php"><span></span> <span>Feedback</span></a>
        <a href="../auth/logout.php" style="color: #e57373; margin-top: auto; margin-bottom: 24px;"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Availability</h1>
            <p>Block unavailable time slots. Booked appointments cannot be modified.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Manage Availability</h2>
        </div>
        <div class="card-body">

            <form id="blockForm">
                <input type="hidden" name="action" value="save_blocks">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label>Select Date</label>
                    <input type="date" name="date" id="dateInput" class="form-control" 
                           min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Time Slots</label>
                    <div id="loader" style="text-align:center; padding:20px; color:var(--text-muted); display:none;">
                        Loading slots...
                    </div>
                    <div class="slot-grid" id="slotGrid"></div>
                </div>

                <button type="submit" class="btn-primary" id="saveBtn">Save Changes</button>
            </form>
        </div>
    </div>
</main>

<script>
const CSRF = '<?= $_SESSION['csrf_token'] ?>';
const dateInput = document.getElementById('dateInput');
const slotGrid = document.getElementById('slotGrid');
const loader = document.getElementById('loader');
const saveBtn = document.getElementById('saveBtn');

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
});

dateInput.addEventListener('change', fetchSlots);
fetchSlots();

function fetchSlots() {
    const date = dateInput.value;
    if (!date) return;

    slotGrid.style.display = 'none';
    loader.style.display = 'block';
    saveBtn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'fetch_day_status');
    fd.append('date', date);
    fd.append('csrf_token', CSRF);

    fetch('availability.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            loader.style.display = 'none';
            slotGrid.style.display = 'grid';
            saveBtn.disabled = false;

            if (data.status === 'success') {
                renderSlots(data.slots);
            } else {
                Toast.fire({ icon: 'error', title: data.message });
            }
        })
        .catch(() => {
            loader.style.display = 'none';
            Toast.fire({ icon: 'error', title: 'Network error.' });
        });
}

function renderSlots(slots) {
    slotGrid.innerHTML = '';
    slots.forEach(slot => {
        const isBooked = slot.state === 'booked';
        const isBlocked = slot.state === 'blocked';
        const savedReason = slot.block_reason || '';

        const reasonOptions = [
            { value: '', label: '— Select reason —' },
            { value: 'sick', label: 'Medical Leave' },
            { value: 'family', label: 'Family Matter' },
            { value: 'other', label: 'Other' },
        ];

        const optionsHTML = reasonOptions.map(o =>
            `<option value="${o.value}" ${savedReason === o.value ? 'selected' : ''}>${o.label}</option>`
        ).join('');

        slotGrid.insertAdjacentHTML('beforeend', `
            <label class="slot-cb ${isBooked ? 'is-booked' : ''}">
                <input type="checkbox" name="blocks[]" value="${slot.time_24}"
                       ${isBooked ? 'disabled' : ''} ${isBlocked ? 'checked' : ''}
                       onchange="toggleReasonRow(this)">
                <div class="slot-box">
                    <div class="slot-time">${slot.time_12}</div>
                    <div class="slot-status">${slot.meta}</div>
                    <div class="reason-row" style="${isBlocked ? 'display:block;' : ''}">
                        <select class="reason-select" name="block_reasons[${slot.time_24}]"
                                onclick="event.preventDefault()"
                                onchange="event.stopPropagation()">
                            ${optionsHTML}
                        </select>
                    </div>
                </div>
            </label>
        `);
    });
}

function toggleReasonRow(checkbox) {
    const reasonRow = checkbox.closest('.slot-cb').querySelector('.reason-row');
    if (reasonRow) {
        reasonRow.style.display = checkbox.checked ? 'block' : 'none';
    }
}

document.getElementById('blockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    fetch('availability.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
            if (data.status === 'success') {
                Toast.fire({ icon: 'success', title: data.message });
                fetchSlots(); // Refresh slots
            } else {
                Toast.fire({ icon: 'error', title: data.message });
            }
        })
        .catch(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
            Toast.fire({ icon: 'error', title: 'Network error.' });
        });
});
</script>
</body>
</html>