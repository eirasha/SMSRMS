<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// --- 1. Dashboard Stats (Optimized single query) ---
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END) as pendingPayment,
    SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) as paidPayment
    FROM bookings WHERE customer_id=?");
$stmt->execute([$customer_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 2. Fetch Available Services for Quick Booking ---
$stmt_services = $conn->query("SELECT * FROM services LIMIT 5");
$quick_services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Fetch Recent Bookings ---
$stmt = $conn->prepare("
    SELECT b.*, s.name AS service_name, m.username AS massager_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users m ON b.massager_id = m.id
    WHERE b.customer_id=?
    ORDER BY b.booking_date DESC, b.booking_time ASC
");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | Sunflower</title>
    <!-- Versioning added to force browser to load new CSS -->
    <link rel="stylesheet" href="http://localhost/SMSRMS/css/cust.css?v=<?= time(); ?>">
</head>
<body>

<header class="header">
    <div class="nav-left">
        <img src="../uploads/logo.png" alt="Logo" class="nav-logo">
        <span class="brand-name">SUNFLOWER</span>
    </div>

    <!-- The Hamburger Icon for Mobile -->
    <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <!-- Navigation Links -->
    <nav class="nav-bar" id="nav-menu">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="book_service.php">Book Slot</a>
        <a href="my_bookings.php">My Bookings</a>
        <a href="payment.php">Payments</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<main class="main-wrapper">
    <div class="page-container">
        
        <section class="welcome-section">
            <h1>Hello, <?= htmlspecialchars($_SESSION['username']); ?> <span style="color: #10b981;">●</span></h1>
            <p>Welcome back to your Sunflower dashboard.</p>
        </section>

        <!-- Expert Feature: Quick Book Service Cards -->
        <?php if(!empty($quick_services)): ?>
        <div class="quick-book-section">
            <div class="section-header">
                <h2>Our Signature Services</h2>
                <a href="book_service.php" class="view-all">View All</a>
            </div>
            
            <div class="services-scroll-container">
                <?php foreach($quick_services as $service): ?>
                <div class="service-card">
                    <div class="service-img-wrapper">
                        <!-- Use default_service.jpg if image_path is empty -->
                        <img src="../uploads/<?= htmlspecialchars($service['image_path'] ?? 'default_service.jpg'); ?>" alt="<?= htmlspecialchars($service['name']); ?>" class="service-img">
                        <span class="service-price">RM <?= number_format($service['price'] ?? 0, 2); ?></span>
                    </div>
                    <div class="service-info">
                        <h3><?= htmlspecialchars($service['name']); ?></h3>
                        <p class="service-duration">⏱ <?= htmlspecialchars($service['duration'] ?? '60'); ?> mins</p>
                        <!-- Button triggers the Modal -->
                        <button type="button" class="btn-book-quick open-modal-btn" 
                                data-id="<?= $service['id']; ?>" 
                                data-name="<?= htmlspecialchars($service['name']); ?>">
                            Book Now
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Bookings</div>
                <div class="value"><?= $stats['total'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Completed</div>
                <div class="value" style="color: var(--success-text)"><?= $stats['completed'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="label">To Pay</div>
                <div class="value" style="color: var(--pending-text)"><?= $stats['pendingPayment'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Successful Payments</div>
                <div class="value"><?= $stats['paidPayment'] ?? 0 ?></div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="content-card">
            <div class="card-header">
                <h2>Recent Bookings</h2>
            </div>

            <div class="table-wrapper">
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th>Service Details</th>
                            <th>Provider</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($bookings)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 2rem;">No bookings found.</td></tr>
                        <?php else: ?>
                            <?php foreach($bookings as $b): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['service_name']); ?></strong></td>
                                <td><?= htmlspecialchars($b['massager_name'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <?= date("M d, Y", strtotime($b['booking_date'])); ?><br>
                                    <small style="color:var(--text-muted)"><?= date("h:i A", strtotime($b['booking_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($b['status']); ?>">
                                        <?= ucfirst($b['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($b['payment_status']); ?>">
                                        <?= ucfirst($b['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($b['payment_status']=='pending'): ?>
                                        <a class="btn-pay" href="payment.php?booking_id=<?= $b['id']; ?>">Pay Now</a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.75rem; font-weight: bold;">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ==============================================
     THE BOOKING MODAL (POPUP)
=============================================== -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal-content glass-panel">
        <div class="modal-header">
            <h2>Complete Your Booking</h2>
            <button class="close-btn" id="closeModal">&times;</button>
        </div>
        
        <form action="../actions/process_booking.php" method="POST" id="quickBookForm">
            <input type="hidden" name="service_id" id="modalServiceId" value="">
            
            <div class="form-group">
                <label>Selected Service</label>
                <input type="text" id="modalServiceName" readonly class="form-control readonly-input">
            </div>

            <div class="form-group">
                <label>Select Date</label>
                <input type="date" name="booking_date" required class="form-control" min="<?= date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label>Select Time</label>
                <select name="booking_time" required class="form-control">
                    <option value="">-- Pick a Time Slot --</option>
                    <option value="09:00:00">09:00 AM</option>
                    <option value="11:00:00">11:00 AM</option>
                    <option value="14:00:00">02:00 PM</option>
                    <option value="16:00:00">04:00 PM</option>
                </select>
            </div>

            <button type="submit" class="btn-confirm-book">Confirm Appointment</button>
        </form>
    </div>
</div>

<!-- JavaScript for Mobile Menu AND Modal -->
<script>
    // --- Mobile Hamburger Menu Logic ---
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');

    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
    });

    document.querySelectorAll('.nav-bar a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        });
    });

    // --- Modal Logic ---
    const modal = document.getElementById('bookingModal');
    const closeModal = document.getElementById('closeModal');
    const modalServiceId = document.getElementById('modalServiceId');
    const modalServiceName = document.getElementById('modalServiceName');
    const openButtons = document.querySelectorAll('.open-modal-btn');

    openButtons.forEach(button => {
        button.addEventListener('click', () => {
            modalServiceId.value = button.getAttribute('data-id');
            modalServiceName.value = button.getAttribute('data-name');
            modal.classList.add('active');
        });
    });

    closeModal.addEventListener('click', () => {
        modal.classList.remove('active');
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
</script>

</body>
</html>