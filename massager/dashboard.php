<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Security Check: Only Massagers allowed here
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'massager') {
    header("Location: ../auth/login.php");
    exit;
}

$massager_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// --- 1. Fetch Performance Stats ---
// Calculate Today's Estimated Earnings and Total Appointments
$stmt_stats = $conn->prepare("
    SELECT 
        COUNT(*) as today_appointments,
        SUM(CASE WHEN b.status = 'completed' THEN s.price ELSE 0 END) as today_earnings
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ? AND b.booking_date = ?
");
$stmt_stats->execute([$massager_id, $today]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// --- 2. Fetch The Daily Timeline (Today's Schedule) ---
$stmt_schedule = $conn->prepare("
    SELECT b.*, u.username AS client_name, s.name AS service_name
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    WHERE b.massager_id = ? AND b.booking_date = ?
    ORDER BY b.booking_time ASC
");
$stmt_schedule->execute([$massager_id, $today]);
$schedule = $stmt_schedule->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Fetch Unread Notifications ---
$stmt_notif = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt_notif->execute([$massager_id]);
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Specialist Command Center | Sunflower</title>
    <!-- We will use a specific CSS file for the massager layout -->
    <link rel="stylesheet" href="http://localhost/SMSRMS/css/massager.css?v=<?= time(); ?>">
</head>
<body>

<header class="header">
    <div class="nav-left">
        <img src="../uploads/logo.png" alt="Logo" class="nav-logo">
        <span class="brand-name">SUNFLOWER <span class="badge badge-pro">PRO</span></span>
    </div>

    <div class="hamburger" id="hamburger">
        <span></span><span></span><span></span>
    </div>

    <nav class="nav-bar" id="nav-menu">
        <a href="dashboard.php" class="active">Today's Schedule</a>
        <a href="clients.php">My Clients</a>
        <a href="availability.php">Availability</a>
        <a href="../auth/logout.php" class="logout">Logout</a>
    </nav>
</header>

<main class="main-wrapper">
    <div class="page-container">
        
        <section class="welcome-section">
            <h1>Welcome back, <?= htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Here is your schedule for today, <?= date('l, F jS'); ?>.</p>
        </section>

        <!-- Performance Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Today's Appointments</div>
                <div class="value"><?= $stats['today_appointments'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Today's Earnings</div>
                <div class="value" style="color: var(--success-text)">RM <?= number_format($stats['today_earnings'] ?? 0, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">New Notifications</div>
                <div class="value" style="color: var(--pending-text)"><?= count($notifications) ?></div>
            </div>
        </div>

        <div class="dashboard-split">
            
            <!-- LEFT: The Daily Timeline -->
            <div class="content-card timeline-card">
                <div class="card-header">
                    <h2>📅 Today's Itinerary</h2>
                </div>
                <div class="timeline-container">
                    <?php if(empty($schedule)): ?>
                        <p class="empty-state">You have no appointments scheduled for today. Enjoy your rest!</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach($schedule as $appt): 
                                // Determine the status dot color
                                $dot_class = 'dot-pending';
                                if($appt['status'] == 'completed') $dot_class = 'dot-completed';
                                if($appt['status'] == 'cancelled') $dot_class = 'dot-cancelled';
                            ?>
                            <div class="timeline-item">
                                <div class="time-column">
                                    <span class="time"><?= date("h:i A", strtotime($appt['booking_time'])); ?></span>
                                    <span class="duration"><?= $appt['duration'] ?> min</span>
                                </div>
                                <div class="timeline-divider">
                                    <div class="timeline-dot <?= $dot_class ?>"></div>
                                    <div class="timeline-line"></div>
                                </div>
                                <div class="timeline-content glass-panel">
                                    <h3><?= htmlspecialchars($appt['service_name']); ?></h3>
                                    <p class="client-name">👤 Client: <strong><?= htmlspecialchars($appt['client_name']); ?></strong></p>
                                    
                                    <div class="action-buttons">
                                        <?php if($appt['status'] == 'pending'): ?>
                                            <!-- In a real app, this would submit via AJAX to update status -->
                                            <a href="../actions/update_status.php?id=<?= $appt['id'] ?>&status=completed" class="btn-success btn-sm">Mark Complete</a>
                                            <a href="../actions/update_status.php?id=<?= $appt['id'] ?>&status=cancelled" class="btn-danger btn-sm">No-Show</a>
                                        <?php else: ?>
                                            <span class="badge badge-<?= strtolower($appt['status']) ?>"><?= ucfirst($appt['status']) ?></span>
                                        <?php endif; ?>
                                        <button class="btn-secondary btn-sm" onclick="alert('Client CRM Notes Feature Coming Soon!')">📝 Add Note</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Notifications & Alerts -->
            <div class="content-card alerts-card">
                <div class="card-header">
                    <h2>🔔 Recent Activity</h2>
                </div>
                <div class="alerts-container">
                    <?php if(empty($notifications)): ?>
                        <p class="empty-state">No new notifications.</p>
                    <?php else: ?>
                        <?php foreach($notifications as $notif): ?>
                            <div class="alert-item">
                                <p><?= htmlspecialchars($notif['message']); ?></p>
                                <small class="time-ago"><?= date("h:i A", strtotime($notif['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div> <!-- End Split -->
    </div>
</main>

<script>
    // Mobile Nav Logic
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
    });
</script>

</body>
</html>