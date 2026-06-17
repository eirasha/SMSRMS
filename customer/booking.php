<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

try {
    $stmt_services = $conn->query("SELECT * FROM services ORDER BY name ASC");
    $db_services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

    $stmt_massagers = $conn->query("SELECT id, name FROM massagers WHERE status = 1 ORDER BY name ASC");
    $db_massagers = $stmt_massagers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Server database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Sunflower Pro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #f4d03f;
            --primary-hover: #e5c100;
            --bg: #f9fafb;
            --surface: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding-top: 70px;
        }

        /* ── HEADER ── */
        .header {
            position: fixed; top: 0; left: 0; width: 100%; height: 70px;
            background: #fff; display: flex; align-items: center;
            justify-content: space-between; padding: 0 5%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 2000;
        }
        .brand-name { font-weight: bold; font-size: 1.2rem; }
        .nav-bar { display: flex; gap: 20px; align-items: center; }
        .nav-bar a { text-decoration: none; font-weight: 600; color: #333; transition: color 0.2s; }
        .nav-bar a:hover, .nav-bar a.active { color: #10b981; }
        .nav-bar a.logout { color: #ef4444 !important; }

        /* ── LAYOUT ── */
        .booking-wrapper {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .booking-container {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 40px;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        h2 {
            margin-top: 0;
            font-size: 1.25rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        select, input[type="text"], input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        select:focus, input:focus {
            outline: none;
            border-color: var(--primary-hover);
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.2);
        }

        /* ── TIME SLOTS ── */
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
        }
        .time-chip {
            background: var(--surface);
            border: 1px solid var(--primary-hover);
            color: #1f2937;
            padding: 12px 8px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .time-chip:hover { background: rgba(244, 208, 63, 0.1); transform: translateY(-1px); }
        .time-chip.selected {
            background: var(--primary);
            color: #1f2937;
            border-color: var(--primary-hover);
            box-shadow: 0 4px 6px rgba(244, 208, 63, 0.3);
        }
        .time-chip.taken {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #e5e7eb;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            background: var(--bg);
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .loading-text {
            color: #b45309;
            font-weight: 600;
            text-align: center;
            padding: 40px;
            animation: pulse 1.5s infinite;
        }

        /* ── DETAILS FORM ── */
        #details-form {
            display: none;
            margin-top: 30px;
            animation: slideDown 0.4s ease-out;
            background: var(--bg);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .btn-submit {
            background: var(--primary);
            color: #1f2937;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover:not(:disabled) { background: var(--primary-hover); }
        .btn-submit:disabled { background: #d1d5db; cursor: not-allowed; }

        .flatpickr-calendar {
            box-shadow: none !important;
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100% !important;
        }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .booking-container { grid-template-columns: 1fr; }
            .nav-bar { gap: 12px; }
        }
    </style>
</head>
<body>

<!-- ── HEADER (fixed: moved inside body) ── -->
<header class="header">
    <div class="nav-left">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-bar">
        <a href="dashboard.php">Dashboard</a>
        <a href="calendar.php">Reservation</a>
        <a href="booking.php" class="active">Book Service</a>
        <a href="payment.php">Payments</a>
        <a href="feedback.php">Feedback</a> 
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="booking-wrapper">
<div class="booking-container">

    <!-- LEFT: Service, Therapist, Date -->
    <div class="step-left">
        <h2>1. Service & Date</h2>

        <label for="service">Select Service</label>
        <select id="service" required>
            <option value="" disabled selected>Choose a service...</option>
            <?php if (empty($db_services)): ?>
                <option value="" disabled>No services found. Contact Admin.</option>
            <?php else: ?>
                <?php foreach ($db_services as $s): ?>
                    <option value="<?= $s['id'] ?>">
                        <?= htmlspecialchars($s['name']) ?> — RM <?= number_format($s['price'], 2) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label for="massager">Select Therapist</label>
        <select id="massager" required>
            <option value="" disabled selected>Choose a therapist...</option>
            <?php if (empty($db_massagers)): ?>
                <option value="" disabled>No therapists available.</option>
            <?php else: ?>
                <?php foreach ($db_massagers as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label>Pick a Date</label>
        <input type="text" id="calendar-input" placeholder="Click to choose a date" readonly>
    </div>

    <!-- RIGHT: Time Slots + Details Form -->
    <div class="step-right">
        <h2>2. Select Time</h2>
        <p id="selected-date-text" style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
            Please select a service, therapist, and date first.
        </p>

        <div id="time-slots-container">
            <div class="empty-state">🗓️ Select a service, therapist, and date to see available times.</div>
        </div>

        <div id="details-form">
            <h2>3. Confirm Booking</h2>
            <form id="finalBookingForm">
                <!-- Hidden fields posted to process_booking.php -->
                <input type="hidden" id="booking_date"  name="booking_date">
                <input type="hidden" id="booking_time"  name="booking_time">
                <input type="hidden" id="service_id"    name="service_id">
                <input type="hidden" id="massager_id"   name="massager_id">

                <label for="client_name">Full Name</label>
                <input type="text" id="client_name" name="client_name" required placeholder="John Doe">

                <label for="client_phone">Phone Number</label>
                <input type="tel" id="client_phone" name="client_phone" required placeholder="012-3456789">

                <button type="submit" class="btn-submit">Proceed to Online Payment</button>
            </form>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    let selectedDate = null;
    let selectedTime24 = null;

    // ── Flatpickr ──
    flatpickr("#calendar-input", {
        inline: false,
        minDate: "today",
        disable: [
            function(date) { return date.getDay() === 0; } // No Sundays
        ],
        onChange: function(selectedDates, dateStr) {
            selectedDate = dateStr;
            document.getElementById('booking_date').value = dateStr;
            maybeLoadSlots();
        }
    });

    // Re-fetch when service or therapist changes
    document.getElementById('service').addEventListener('change', function() {
        document.getElementById('service_id').value = this.value;
        maybeLoadSlots();
    });

    document.getElementById('massager').addEventListener('change', function() {
        document.getElementById('massager_id').value = this.value;
        maybeLoadSlots();
    });

    // Only fetch if all three are selected
    function maybeLoadSlots() {
        const serviceVal  = document.getElementById('service').value;
        const massagerVal = document.getElementById('massager').value;

        if (!serviceVal || !massagerVal || !selectedDate) return;

        fetchAvailableTimes(massagerVal, selectedDate);
    }

    function fetchAvailableTimes(massagerId, dateStr) {
        const timeContainer = document.getElementById('time-slots-container');
        const dateText      = document.getElementById('selected-date-text');
        const detailsForm   = document.getElementById('details-form');

        detailsForm.style.display = 'none';
        selectedTime24 = null;

        dateText.innerHTML = `Availability for <strong>${dateStr}</strong>:`;
        timeContainer.innerHTML = '<div class="loading-text">Finding open slots...</div>';

        // Fixed: correct path + correct API endpoint
        fetch(`../actions/get_synced_slots.php?massager_id=${massagerId}&date=${dateStr}`)
            .then(r => r.json())
            .then(data => {
                timeContainer.innerHTML = '';

                if (data.status === 'error') {
                    timeContainer.innerHTML = `<div class="empty-state" style="color:#ef4444;">${data.message}</div>`;
                    return;
                }

                // Fixed: API returns data.slots not data.data
                const slots = data.slots || [];

                if (slots.length === 0) {
                    timeContainer.innerHTML = `<div class="empty-state">No available slots on this day. Try another date.</div>`;
                    return;
                }

                const grid = document.createElement('div');
                grid.className = 'time-slots-grid';

                slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';

                    if (!slot.is_available) {
                        btn.className = 'time-chip taken';
                        btn.textContent = slot.time_12;
                        btn.disabled = true;
                    } else {
                        btn.className = 'time-chip';
                        btn.textContent = slot.time_12;
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.time-chip').forEach(c => c.classList.remove('selected'));
                            this.classList.add('selected');

                            selectedTime24 = slot.time_24;
                            document.getElementById('booking_time').value = selectedTime24;
                            detailsForm.style.display = 'block';
                            detailsForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        });
                    }

                    grid.appendChild(btn);
                });

                timeContainer.appendChild(grid);
            })
            .catch(() => {
                timeContainer.innerHTML = `<div class="empty-state" style="color:#ef4444;">Connection error. Please refresh the page.</div>`;
            });
    }

    // ── Form Submit → process_booking → checkout_payment ──
    document.getElementById('finalBookingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = this.querySelector('.btn-submit');
        btn.textContent = 'Securing Booking...';
        btn.disabled = true;

        fetch('../actions/process_booking.php', {   // Fixed: correct path
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.booking_id) {
                Swal.fire({
                    title: 'Appointment Saved!',
                    text: 'Redirecting to payment gateway...',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Fixed: correct path to checkout
                    window.location.href = `../actions/checkout_payment.php?booking_id=${res.booking_id}`;
                });
            } else {
                Swal.fire('Booking Failed', res.message || 'An unexpected error occurred.', 'error');
                btn.textContent = 'Proceed to Online Payment';
                btn.disabled = false;
            }
        })
        .catch(() => {
            Swal.fire('Error', 'Connection timeout. Please try again.', 'error');
            btn.textContent = 'Proceed to Online Payment';
            btn.disabled = false;
        });
    });

    // Auto-select service from URL param e.g. booking.php?service_id=2
    window.addEventListener('DOMContentLoaded', () => {
        const serviceId = new URLSearchParams(window.location.search).get('service_id');
        if (serviceId) {
            const dropdown = document.getElementById('service');
            dropdown.value = serviceId;
            document.getElementById('service_id').value = serviceId;
        }
    });
</script>
</body>
</html>