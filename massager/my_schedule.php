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

// Quick stats
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'approved' AND booking_date = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN status = 'approved' AND booking_date > CURDATE() THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        COUNT(*) as total
    FROM bookings
    WHERE massager_id = ? AND payment_status = 'paid'
");
$stmt->execute([$massager_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule | Sunflower Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
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
            --green-light: #d8f3dc;
            --red: #c0392b;
            --red-light: #fdecea;
            --amber: #b7791f;
            --amber-light: #fef3c7;
            --blue: #1e40af;
            --blue-light: #dbeafe;
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
        .main-content { 
            margin-left: var(--sidebar-w); 
            flex: 1; 
            padding: 40px 50px; 
            max-width: calc(100% - var(--sidebar-w)); 
        }
       
        /* Page Header */
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
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            margin-bottom: 40px; 
        }
        .stat-card { 
            background: var(--white); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            padding: 24px; 
            box-shadow: var(--card-shadow); 
            position: relative; 
            overflow: hidden; 
        }
        .stat-card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 3px; 
            background: var(--gold); 
        }
        .stat-label { 
            font-size: 0.8rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: var(--text-muted); 
            margin-bottom: 8px; 
        }
        .stat-value { 
            font-family: 'Playfair Display', serif; 
            font-size: 2.4rem; 
            color: var(--dark); 
        }
        .stat-value.green { color: var(--green); }

        /* ── CARD STYLES ── */
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

        /* CONTENT GRID */
        .content-grid { 
            display: grid; 
            grid-template-columns: 1fr 360px; 
            gap: 24px; 
            align-items: start; 
        }

        /* CALENDAR */
        .calendar-card { 
            background: var(--white); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            padding: 24px; 
            box-shadow: var(--card-shadow); 
        }
        .calendar-card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding-bottom: 16px; 
            border-bottom: 1px solid var(--border); 
            flex-wrap: wrap; 
            gap: 12px; 
        }
        .calendar-card-header h2 { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.25rem; 
            color: var(--dark); 
        }
        .legend { 
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
        }
        .legend-item { 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 0.85rem; 
            color: var(--text-muted); 
        }
        .legend-dot { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
        }

        /* DETAIL PANEL */
        .detail-panel { 
            background: var(--white); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            box-shadow: var(--card-shadow); 
            overflow: hidden; 
            position: sticky; 
            top: 30px; 
        }
        .detail-panel-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
            background: var(--dark); 
        }
        .detail-panel-header h3 { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.1rem; 
            color: var(--gold-light); 
            margin: 0; 
        }
        .detail-date { 
            font-size: 0.9rem; 
            color: #c4b08a; 
            margin-top: 4px; 
        }
        .detail-panel-body { 
            padding: 24px; 
            max-height: 620px; 
            overflow-y: auto; 
        }
        .detail-panel-body::-webkit-scrollbar { width: 6px; }
        .detail-panel-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }

        /* BOOKING ITEM */
        .booking-item { 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            padding: 18px; 
            margin-bottom: 16px; 
            background: var(--gold-pale); 
            transition: all 0.2s; 
        }
        .booking-item:last-child { margin-bottom: 0; }
        .booking-item:hover { 
            border-color: var(--gold); 
            box-shadow: var(--card-shadow); 
        }
        .booking-time { 
            font-family: 'Playfair Display', serif; 
            font-size: 1.2rem; 
            color: var(--dark); 
            font-weight: 700; 
            margin-bottom: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }
        .booking-row { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.9rem; 
            margin-bottom: 8px; 
        }
        .booking-row:last-child { margin-bottom: 0; }
        .booking-row .label { 
            color: var(--text-muted); 
            font-weight: 500; 
        }
        .booking-row .value { 
            color: var(--text); 
            font-weight: 600; 
            text-align: right; 
        }

        .btn-complete { 
            width: 100%; 
            margin-top: 16px; 
            padding: 11px 20px; 
            background: var(--green); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-family: inherit; 
            font-size: 0.9rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .btn-complete:hover { 
            background: #1f5c3a; 
            transform: translateY(-1px); 
        }
        .btn-complete:disabled { 
            background: #a0b4a8; 
            cursor: not-allowed; 
            transform: none; 
        }

        /* BADGES */
        .badge { 
            display: inline-block; 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.78rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .badge-approved { background: var(--green-light); color: var(--green); border: 1px solid #b7e4c7; }
        .badge-completed { background: var(--blue-light); color: var(--blue); border: 1px solid #bfdbfe; }
        .badge-pending { background: var(--amber-light); color: var(--amber); border: 1px solid #fde68c; }
        .badge-cancelled { background: var(--red-light); color: var(--red); border: 1px solid #f8aba6; }

        /* EMPTY STATE */
        .panel-empty { 
            text-align: center; 
            padding: 60px 20px; 
            color: var(--text-muted); 
        }
        .panel-empty .icon { 
            font-size: 3rem; 
            opacity: 0.4; 
            margin-bottom: 16px; 
        }

        /* FULLCALENDAR OVERRIDES */
        .fc-button-primary { 
            background-color: var(--gold) !important; 
            border-color: var(--gold) !important; 
            color: var(--dark) !important; 
            font-weight: 600 !important; 
        }
        .fc-button-primary:not(:disabled):hover { 
            background-color: var(--gold-light) !important; 
            border-color: var(--gold-light) !important; 
        }
        .fc-button-primary:not(:disabled).fc-button-active { 
            background-color: var(--dark) !important; 
            border-color: var(--dark) !important; 
            color: var(--gold-light) !important; 
        }
        .fc-toolbar-title { 
            font-family: 'Playfair Display', serif !important; 
            font-size: 1.3rem !important; 
            color: var(--dark) !important; 
        }
        .fc-col-header-cell-cushion { 
            color: var(--text-muted) !important; 
            font-weight: 600 !important; 
            font-size: 0.85rem !important; 
            text-transform: uppercase; 
        }
        .fc-event { 
            border: none !important; 
            border-radius: 6px !important; 
            font-size: 0.8rem !important; 
            padding: 3px 6px !important; 
        }
        .fc-theme-standard td, .fc-theme-standard th { 
            border-color: var(--border) !important; 
        }
        .fc-day-today { background: rgba(201,168,76,0.08) !important; }
        .fc-daygrid-day:hover { background: rgba(201,168,76,0.04) !important; }

        @media (max-width: 1100px) {
            .content-grid { grid-template-columns: 1fr; }
            .detail-panel { position: static; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .main-content { padding: 24px 20px; }
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
        <a href="my_schedule.php" class="active"><span></span> <span>My Schedule</span></a>
        <a href="availability.php"><span></span> <span>Availability</span></a>
        <a href="feedback.php"><span></span> <span>Feedback</span></a>
        <a href="../auth/logout.php" style="color: #e57373; margin-top: auto; margin-bottom: 24px;"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>My Schedule</h1>
            <p>Manage your appointments and track upcoming sessions.</p>
        </div>
        <div class="date-badge">
            <?= date('l, d F Y') ?>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Today's Sessions</div>
            <div class="stat-value green"><?= $stats['today'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Upcoming</div>
            <div class="stat-value"><?= $stats['upcoming'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        </div>
    </div>

    <!-- CALENDAR + DETAIL -->
    <div class="content-grid">
        <!-- CALENDAR -->
        <div class="calendar-card card">
            <div class="calendar-card-header">
                <h2>Calendar</h2>
                <div class="legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#3b82f6;"></div> Booking</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ef4444;"></div> Blocked</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#10b981;"></div> Completed</div>
                </div>
            </div>
            <div id="calendar"></div>
        </div>

        <!-- DETAIL PANEL -->
        <div class="detail-panel card" id="detailPanel">
            <div class="detail-panel-header">
                <h3 id="panelTitle">Day Overview</h3>
                <div class="detail-date" id="panelDate">Select a date to view bookings</div>
            </div>
            <div class="detail-panel-body" id="panelBody">
                <div class="panel-empty">
                    <div class="icon">📅</div>
                    <p>Click any date on the calendar to see your bookings for that day.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const massagerId = <?= $massager_id ?>;
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

const panelTitle = document.getElementById('panelTitle');
const panelDate = document.getElementById('panelDate');
const panelBody = document.getElementById('panelBody');

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
});

document.addEventListener('DOMContentLoaded', function() {
    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        height: 'auto',
        events: 'api_calendar_events.php',
        dateClick: function(info) {
            document.querySelectorAll('.fc-day-selected').forEach(el => el.classList.remove('fc-day-selected'));
            info.dayEl.classList.add('fc-day-selected');
            loadDayBookings(info.dateStr);
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const dateStr = info.event.startStr.split('T')[0];
            loadDayBookings(dateStr);
        }
    });
    calendar.render();

    // Load today
    const today = new Date().toISOString().split('T')[0];
    loadDayBookings(today);
});

function loadDayBookings(dateStr) {
    const [y, m, d] = dateStr.split('-');
    const formatted = new Date(y, m - 1, d).toLocaleDateString('en-MY', { 
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' 
    });

    panelTitle.textContent = 'Day Overview';
    panelDate.textContent = formatted;
    panelBody.innerHTML = '<div class="panel-empty"><div class="icon">⏳</div><p>Loading bookings...</p></div>';

    fetch(`api_day_bookings.php?date=${dateStr}&massager_id=${massagerId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                renderBookings(data.bookings, dateStr, formatted);
            } else {
                panelBody.innerHTML = `<div class="panel-empty"><div class="icon">⚠️</div><p>${data.message}</p></div>`;
            }
        })
        .catch(() => {
            panelBody.innerHTML = '<div class="panel-empty"><div class="icon">❌</div><p>Failed to load bookings.</p></div>';
        });
}

function renderBookings(bookings, dateStr, formatted) {
    panelTitle.textContent = bookings.length > 0 
        ? `${bookings.length} Session${bookings.length > 1 ? 's' : ''}` 
        : 'No Sessions';

    if (bookings.length === 0) {
        panelBody.innerHTML = `
            <div class="panel-empty">
                <div class="icon">🌿</div>
                <p>No bookings on<br><strong>${formatted}</strong></p>
            </div>`;
        return;
    }

    let html = '';
    bookings.forEach(b => {
        const statusClass = {
            approved: 'badge-approved',
            completed: 'badge-completed',
            pending: 'badge-pending',
            cancelled: 'badge-cancelled'
        }[b.status] || 'badge-pending';

        const canComplete = b.status === 'approved' && b.payment_status === 'paid';

        html += `
        <div class="booking-item" id="booking-${b.id}">
            <div class="booking-time">
                ${b.time_12}
                <span class="badge ${statusClass}">${capitalize(b.status)}</span>
            </div>
            <div class="booking-row">
                <span class="label">Customer</span>
                <span class="value">${escHtml(b.customer_name)}</span>
            </div>
            <div class="booking-row">
                <span class="label">Service</span>
                <span class="value">${escHtml(b.service_name)}</span>
            </div>
            <div class="booking-row">
                <span class="label">Price</span>
                <span class="value">RM ${b.price}</span>
            </div>
            <div class="booking-row">
                <span class="label">Payment</span>
                <span class="value">${capitalize(b.payment_status)}</span>
            </div>
            ${canComplete ? `
            <button class="btn-complete" onclick="completeBooking(${b.id}, this)">
                Mark as Completed
            </button>` : ''}
        </div>`;
    });
    panelBody.innerHTML = html;
}

function completeBooking(bookingId, btn) {
    Swal.fire({
        title: 'Mark as Completed?',
        text: 'This action cannot be undone.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Complete',
        confirmButtonColor: '#2d6a4f',
        cancelButtonColor: '#c0392b'
    }).then(result => {
        if (!result.isConfirmed) return;

        btn.disabled = true;
        btn.textContent = 'Saving...';

        const fd = new FormData();
        fd.append('booking_id', bookingId);
        fd.append('csrf_token', csrfToken);

        fetch('../actions/complete_booking.php', { 
            method: 'POST', 
            body: fd 
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Toast.fire({ icon: 'success', title: data.message });
                const item = document.getElementById(`booking-${bookingId}`);
                if (item) {
                    const badge = item.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge badge-completed';
                        badge.textContent = 'Completed';
                    }
                    const completeBtn = item.querySelector('.btn-complete');
                    if (completeBtn) completeBtn.remove();
                }
            } else {
                btn.disabled = false;
                btn.textContent = 'Mark as Completed';
                Toast.fire({ icon: 'error', title: data.message });
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Mark as Completed';
            Toast.fire({ icon: 'error', title: 'Network error.' });
        });
    });
}

function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
</body>
</html>