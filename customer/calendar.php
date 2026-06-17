<?php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

// Strict Security Check for Customer Role Context
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// 1. Fetch Available Services for the Selector
$stmt_services = $conn->query("SELECT id, name, price FROM services ORDER BY id ASC");
$services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Active Massager Personnel
$stmt_massagers = $conn->query("SELECT m.id, m.name FROM massagers m WHERE m.status = 1 ORDER BY m.name ASC");
$massagers = $stmt_massagers->fetchAll(PDO::FETCH_ASSOC);

$today_date = date('Y-m-d');

// 3. Fetch User's Current Upcoming Active Reservation
$stmt_my_booking = $conn->prepare("
    SELECT b.booking_date, b.booking_time, b.status, b.payment_status, s.name AS service_name, s.price
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.status IN ('pending', 'approved') AND b.payment_status != 'failed'
    ORDER BY b.booking_date ASC
    LIMIT 1
");
$stmt_my_booking->execute([$customer_id]);
$my_booking = $stmt_my_booking->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation | Sunflower</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
   
        <link rel=\"stylesheet\" href=\"../css/cust.css?v=<?= time();  ?>">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        :root{
    --gold:#c9a84c;
    --gold-light:#f4d03f;
    --gold-pale:#fdf8ec;

    --dark:#1a1208;
    --dark-soft:#2d2010;

    --text:#3d2e0e;
    --text-muted:#8a7355;

    --border:#e8d9b5;
    --white:#fffef9;

    --green:#2d6a4f;
    --green-light:#d8f3dc;

    --red:#c0392b;
    --red-light:#fdecea;

    --amber:#b7791f;
    --amber-light:#fef3c7;

    --card-shadow:0 4px 24px rgba(201,168,76,0.10);
}
        body{
    padding-top:75px;
    font-family:'DM Sans',sans-serif;
    color:var(--text);
    margin:0;
    background:var(--gold-pale);
}
        
        /* HEADER */
        .header{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:70px;
    background:var(--dark);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 5%;
    box-sizing:border-box;
    box-shadow:0 2px 20px rgba(0,0,0,0.3);
    z-index:2000;
}

.nav-left span{
    font-family:'Playfair Display',serif;
    color:var(--gold-light);
    letter-spacing:2px;
}

.nav-bar{
    display:flex;
    gap:6px;
    align-items:center;
}

.nav-bar a{
    text-decoration:none;
    font-size:.875rem;
    font-weight:500;
    color:#c4b08a;
    padding:7px 14px;
    border-radius:6px;
    transition:.2s;
}

.nav-bar a:hover{
    color:var(--gold-light);
    background:rgba(244,208,63,.08);
}

.nav-bar a.active{
    color:var(--gold-light);
    background:rgba(244,208,63,.12);
}
.nav-bar .logout{
    color:#e57373;
}

.nav-bar .logout:hover{
    background:rgba(229,115,115,.1);
}
       .brand{
    display:flex;
    align-items:center;
    gap:10px;
}

.brand-name{
    font-family:'Playfair Display', serif;
    font-size:1.25rem;
    color:var(--gold-light);
    letter-spacing:2px;
} 

        /* LAYOUT */
        .calendar-layout { max-width: 1250px; margin: 0 auto; display: grid; grid-template-columns: 1fr 400px; gap: 30px; padding: 20px; box-sizing: border-box; }
        .card-panel{
    background:var(--white);
    padding:28px;
    border-radius:16px;
    border:1px solid var(--border);
    box-shadow:var(--card-shadow);
    height:fit-content;
}
        
        /* FullCalendar Overrides */
        #calendar{
    background:var(--white);
    font-family:'DM Sans',sans-serif;
}
        .fc-day-past { background: #f3f4f6 !important; cursor: not-allowed !important; }
        .fc-button-primary { background-color: var(--primary) !important; border-color: var(--primary) !important; color: #1f2937 !important; font-weight: 600 !important; text-transform: capitalize !important; }
        .fc-button-primary:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; }
        .fc .fc-toolbar-title { font-size: 1.35rem !important; font-weight: 700; color: var(--text-main); }

        /* "Booked" date marker — shown on dates that already have one or more reservations */
        .fc-daygrid-event.is-booked-mark { background: var(--amber-light); color: var(--amber) !important; border-radius: 6px; padding: 1px 6px; font-size: 0.72rem; font-weight: 700; }
        .fc-daygrid-event.is-booked-mark .fc-daygrid-event-dot { border-color: var(--amber) !important; }
        .fc-daygrid-event.is-booked-mark .fc-event-title { color: var(--amber); }

        /* Fully booked background overlay */
        .fc-bg-event.is-fully-booked { opacity: 0.45; }

        /* Calendar legend */
        .calendar-legend { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-muted); }
        .calendar-legend .legend-item { display: flex; align-items: center; gap: 7px; }
        .calendar-legend .legend-dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
        .legend-dot.dot-booked { background: var(--amber); }
        .legend-dot.dot-full { background: var(--red); }
        .legend-dot.dot-open { background: var(--green); }
        .legend-dot.dot-mine-paid    { background: #16a34a; }
        .legend-dot.dot-mine-pending { background: #d97706; }

        /* ── My Booking Events ── */
        .fc-event.my-booking-event {
            border-radius: 5px !important;
            border: none !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            padding: 2px 6px !important;
            cursor: pointer !important;
        }
        .fc-event.my-booking-paid {
            background: #16a34a !important;
            color: #fff !important;
        }
        .fc-event.my-booking-pending {
            background: #d97706 !important;
            color: #fff !important;
        }
        .fc-event.my-booking-other {
            background: #6b7280 !important;
            color: #fff !important;
        }

        /* ── Booking tooltip popup ── */
        #booking-tooltip {
            display: none;
            position: fixed;
            z-index: 9999;
            background: var(--dark);
            color: #f9f5eb;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.82rem;
            line-height: 1.6;
            box-shadow: 0 8px 28px rgba(0,0,0,0.35);
            max-width: 240px;
            pointer-events: none;
        }
        #booking-tooltip strong { color: var(--gold-light); display: block; margin-bottom: 4px; font-size: 0.85rem; }
        #booking-tooltip .tt-row { display: flex; justify-content: space-between; gap: 12px; }
        #booking-tooltip .tt-label { color: #c4b08a; }
        #booking-tooltip .tt-val   { color: #fff; font-weight: 600; }
        
        /* Current Booking Card */
        .current-booking-card { background: #fffdf5; border: 1px dashed #f4d03f; border-radius: 12px; padding: 18px; margin-bottom: 25px; }
        .current-booking-card h4 { margin: 0 0 10px 0; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; color: #b45309; }
        .booking-details-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.88rem; }
        .booking-details-row span:first-child { color: var(--text-muted); font-weight: 500; }
        .booking-details-row span:last-child { color: var(--text-main); font-weight: 600; }

        /* Status Pills */
        .status-pill { display: inline-flex; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-bottom: 15px; text-transform: uppercase; }
        .pill-available { background: #dcfce7; color: #166534; } 
        .pill-past { background: #f3f4f6; color: #4b5563; }        
        .pill-full { background: #fee2e2; color: #991b1b; }
        
        .badge-status { padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .payment-unpaid { background: #fee2e2; color: #dc2626; }
        .payment-paid { background: #dcfce7; color: #16a34a; }

        /* Form Controls */
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4b5563; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; box-sizing: border-box; font-family: inherit; font-size: 0.95rem; color: var(--text-main); transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary-hover); box-shadow: 0 0 0 4px rgba(244, 208, 63, 0.25); }
        .form-control:disabled { background: #f9fafb; cursor: not-allowed; color: #9ca3af; }
        
        /* Make disabled options look clearly inactive */
        #time-select option:disabled { color: #9ca3af; background-color: #f3f4f6; font-style: italic; }

        .btn-submit { width: 100%; padding: 15px; background: var(--primary); color: #1f2937; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background 0.2s, transform 0.1s; display: block; box-shadow: 0 4px 6px -1px rgba(244, 208, 63, 0.3); }
        .btn-submit:hover:not(:disabled) { background: var(--primary-hover); }
        .btn-submit:active:not(:disabled) { transform: scale(0.98); }
        .btn-submit:disabled { background: #cbd5e1; color: #94a3b8; cursor: not-allowed; box-shadow: none; }



        @media (max-width: 992px) {
            .calendar-layout { grid-template-columns: 1fr; }
            body { padding-top: 85px; }
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
        <a href="dashboard.php">Dashboard</a>
        <a href="calendar.php" class="active">Reservation</a>
        <a href="payment.php">Payments</a>
        <a href="feedback.php">Feedback</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="calendar-layout">
    <div class="card-panel">
        <div id='calendar'></div>
        <div class="calendar-legend">
            <span class="legend-item"><span class="legend-dot dot-mine-paid"></span> My booking (paid)</span>
            <span class="legend-item"><span class="legend-dot dot-mine-pending"></span> My booking (pending)</span>
            <span class="legend-item"><span class="legend-dot dot-full"></span> Fully booked</span>
            <span class="legend-item"><span class="legend-dot dot-open"></span> Open date</span>
        </div>
    </div>

    <div class="card-panel" id="booking-sidebar">
        
        <?php if ($my_booking): ?>
            <?php
                // FIX #5: Dynamic badge class based on actual booking status
                $status_class = $my_booking['status'] === 'approved' ? 'status-approved' : 'status-pending';
            ?>
            <div class="current-booking-card">
                <h4>Your Active Reservation</h4>
                <div class="booking-details-row">
                    <span>Service:</span>
                    <span><?= htmlspecialchars($my_booking['service_name']) ?></span>
                </div>
                <div class="booking-details-row">
                    <span>Date & Time:</span>
                    <span><?= date('d M Y', strtotime($my_booking['booking_date'])) ?> at <?= date('g:i A', strtotime($my_booking['booking_time'])) ?></span>
                </div>
                <div class="booking-details-row">
                    <span>Session Status:</span>
                    <!-- FIX #5: Was hardcoded as 'status-pending' for all statuses -->
                    <span><span class="badge-status <?= $status_class ?>"><?= ucfirst($my_booking['status']) ?></span></span>
                </div>
                <div class="booking-details-row">
                    <span>Payment:</span>
                    <span>
                        <span class="badge-status <?= $my_booking['payment_status'] === 'paid' ? 'payment-paid' : 'payment-unpaid' ?>">
                            <?= $my_booking['payment_status'] === 'pending_verification' ? 'Verifying' : ucfirst($my_booking['payment_status']) ?>
                        </span>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <div class="booking-header">
            <div id="status-pill" class="status-pill pill-past">Select a date</div>
            <h3 id="display-date" style="margin: 0 0 5px 0; font-size: 1.4rem; font-weight: 700;">Schedule</h3>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 20px 0; line-height: 1.4;">Complete online payment gateway clearance to confirm your targeted appointment block.</p>
        </div>
        
        <form id="ajaxBookingForm" action="../actions/process_booking.php" method="POST">
            <input type="hidden" name="booking_date" id="selected-date-input">
            
            <div class="form-group">
                <label>Select Therapist</label>
                <select name="massager_id" id="massager-select" class="form-control" required>
                    <?php foreach($massagers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Service</label>
                <select name="service_id" id="service-select" class="form-control" required disabled>
                    <option value="" disabled selected>Choose a wellness service...</option>
                    <?php foreach($services as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> - RM <?= number_format($s['price'], 2) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Available Time Slots</label>
                <select name="booking_time" id="time-select" class="form-control" required disabled>
                    <option value="" disabled selected>-- Pick a Time --</option>
                </select>
            </div>

            <button type="submit" id="bookBtn" class="btn-submit" disabled>Proceed to Pay Now</button>
            <div id="feedback" style="margin-top: 15px; font-weight: 500; font-size: 0.9rem;"></div>
        </form>
    </div>
</div>

<div id="booking-tooltip"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const todayStr = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' });

    const tooltip = document.getElementById('booking-tooltip');

    // Hide tooltip on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.my-booking-event')) tooltip.style.display = 'none';
    });

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        validRange: { start: todayStr }, 
        events: '../actions/fetch_calendar_event.php',

        // Show a tooltip popup when clicking on one of the customer's own booking events
        eventClick: function(info) {
            const props = info.event.extendedProps;
            if (props.type !== 'my_booking') return;

            const payLabel = props.payment_status === 'pending_verification'
                ? 'Verifying'
                : (props.payment_status.charAt(0).toUpperCase() + props.payment_status.slice(1));

            tooltip.innerHTML = `
                <strong>📅 Your Reservation</strong>
                <div class="tt-row"><span class="tt-label">Service</span><span class="tt-val">${props.service}</span></div>
                <div class="tt-row"><span class="tt-label">Time</span><span class="tt-val">${props.time}</span></div>
                <div class="tt-row"><span class="tt-label">Status</span><span class="tt-val">${props.status.charAt(0).toUpperCase() + props.status.slice(1)}</span></div>
                <div class="tt-row"><span class="tt-label">Payment</span><span class="tt-val">${payLabel}</span></div>
            `;

            // Position near the clicked element
            const rect = info.el.getBoundingClientRect();
            const left = Math.min(rect.left, window.innerWidth - 260);
            tooltip.style.top  = (rect.bottom + 8) + 'px';
            tooltip.style.left = left + 'px';
            tooltip.style.display = 'block';

            info.jsEvent.stopPropagation();
        },

        dateClick: function(info) {
            if (info.dateStr < todayStr) return;

            document.getElementById('selected-date-input').value = info.dateStr;
            
            // FIX #2: Parse date parts manually to avoid UTC off-by-one in UTC+8 timezone.
            // new Date("YYYY-MM-DD") is parsed as UTC midnight, rendering one day behind in MY locale.
            const [y, m, d] = info.dateStr.split('-');
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = new Date(y, m - 1, d).toLocaleDateString('en-US', options);
            document.getElementById('display-date').innerText = formattedDate;
            
            loadSyncedTimeSlots(info.dateStr);
        }
    });
    calendar.render();

    // Re-fire slot load when therapist changes
    document.getElementById('massager-select').addEventListener('change', function() {
        const selectedDate = document.getElementById('selected-date-input').value;
        if (selectedDate) {
            loadSyncedTimeSlots(selectedDate);
        }
    });

    // FIX #3: Only enable the book button when the user selects an actual time slot value
    document.getElementById('time-select').addEventListener('change', function() {
        document.getElementById('bookBtn').disabled = !this.value;
    });

    function loadSyncedTimeSlots(dateStr) {
        const timeSelect = document.getElementById('time-select');
        const statusPill = document.getElementById('status-pill');
        const feedback = document.getElementById('feedback');
        const massagerId = document.getElementById('massager-select').value;

        // FIX #4: Reset dependent controls at the start of every load call
        document.getElementById('service-select').disabled = true;
        document.getElementById('bookBtn').disabled = true;
        
        timeSelect.innerHTML = '<option value="" disabled selected>⚡ Checking Availability...</option>';
        timeSelect.disabled = true;
        feedback.innerHTML = '';

        fetch(`/SMSRMS/actions/get_synced_slots.php?date=${dateStr}&massager_id=${massagerId}`)
            .then(res => {
                if (!res.ok) throw new Error("HTTP error, status: " + res.status);
                return res.json();
            })
            .then(data => {
                console.log("Database Sync Return Payload:", data);
                timeSelect.innerHTML = '<option value="" disabled selected>-- Pick a Time --</option>';
                
                if (data.status === 'success') {
                    let openSlotsCount = 0;
                    
                    data.slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time_24;
                        option.text = slot.time_12;
                        
                        if (!slot.is_available) {
                            option.disabled = true;
                            // Distinguish WHY the slot is unavailable instead of a generic label:
                            //  - 'booked'  -> a customer has already reserved this slot
                            //  - 'blocked' -> the therapist marked themselves unavailable
                            //  - 'past'    -> the time has already passed for today
                            let label = 'Unavailable';
                            if (slot.reason === 'booked') {
                                label = 'Booked';
                            } else if (slot.reason === 'blocked') {
                                label = 'Unavailable';
                            } else if (slot.reason === 'past') {
                                label = 'Past';
                            }
                            option.text += ` (${label})`;
                            option.dataset.reason = slot.reason || '';
                        } else {
                            openSlotsCount++;
                        }
                        timeSelect.appendChild(option);
                    });

                    if (openSlotsCount === 0) {
                        statusPill.innerText = "FULLY BOOKED";
                        statusPill.className = "status-pill pill-full";
                        // bookBtn stays disabled (already reset above)
                    } else {
                        statusPill.innerText = "AVAILABLE";
                        statusPill.className = "status-pill pill-available";
                        // FIX #3: Do NOT enable bookBtn here — wait for time-select change event
                    }

                    document.getElementById('service-select').disabled = false;
                    timeSelect.disabled = false;
                } else {
                    timeSelect.innerHTML = '<option value="" disabled>Error calculating hours.</option>';
                    feedback.innerHTML = `<p style="color:#dc2626; margin:0;">❌ API Error: ${data.message}</p>`;
                    // service-select and bookBtn remain disabled (already reset above — FIX #4)
                }
            })
            .catch(err => {
                console.error("AJAX Error Hook:", err);
                timeSelect.innerHTML = '<option value="" disabled>Network Error.</option>';
                feedback.innerHTML = `<p style="color:#b91c1c; background:#fee2e2; padding:12px; border-radius:8px; margin:0; border:1px solid #fca5a5;">❌ Connection issue. Verify path actions/get_synced_slots.php exists.</p>`;
                // service-select and bookBtn remain disabled (already reset above — FIX #4)
            });
    }

    document.getElementById('ajaxBookingForm').onsubmit = function(e) {
        e.preventDefault();

        // Safety check: verify the selected option is not disabled
        const timeSelect = document.getElementById('time-select');
        const selectedOption = timeSelect.options[timeSelect.selectedIndex];

        if (!selectedOption || !timeSelect.value || selectedOption.disabled) {
            alert("Please select a valid time slot before proceeding.");
            return false;
        }

        const btn = document.getElementById('bookBtn');
        btn.disabled = true;
        btn.innerText = "Securing temporary slot...";

        fetch(this.action, { 
            method: 'POST', 
            body: new FormData(this) 
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.booking_id) {
                window.location.href = `../actions/checkout_payment.php?booking_id=${data.booking_id}`;  
            } else {
                document.getElementById('feedback').innerHTML = `<p style="color:#b91c1c; background:#fee2e2; padding:12px; border-radius:8px; margin:0; border:1px solid #fca5a5;">❌ ${data.message}</p>`;
                btn.disabled = false;
                btn.innerText = "Proceed to Pay Now";
            }
        })
        .catch(err => {
            console.error("Submission Error Context:", err);
            document.getElementById('feedback').innerHTML = `<p style="color:#b91c1c; background:#fee2e2; padding:12px; border-radius:8px; margin:0; border:1px solid #fca5a5;">❌ Failed to route process tracking transaction request.</p>`;
            btn.disabled = false;
            btn.innerText = "Proceed to Pay Now";
        });
    };

}); // FIX #1: Closing brace for DOMContentLoaded — was missing, breaking all JS on the page
</script>
</body>
</html>