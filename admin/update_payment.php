<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* =========================
   SECURITY CHECK
========================= */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'massager'])) {
    exit('Unauthorized access.');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = $error = "";

/* =========================
   CREATE UPLOAD FOLDER IF MISSING
========================= */
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/* =========================
   HANDLE QR UPLOAD
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['qr_image'])) {
    if ($_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
        $newName = uniqid() . "." . $ext;
        $target = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $target)) {
            $stmt = $conn->prepare("INSERT INTO payment_qr (uploaded_by, role, qr_image) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $role, $newName]);
            $success = "QR uploaded successfully!";
        } else {
            $error = "Failed to move uploaded file. Check folder permissions.";
        }
    } else {
        $error = "File upload error. Please try again.";
    }
}

/* =========================
   HANDLE PAYMENT STATUS UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['payment_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $payment_status = $_POST['payment_status'] === 'paid' ? 'paid' : 'pending';

    $stmt = $conn->prepare("UPDATE bookings SET payment_status=? WHERE id=?");
    if ($stmt->execute([$payment_status, $booking_id])) {
        $success = "Payment status updated!";
    } else {
        $error = "Failed to update payment status.";
    }
}

/* =========================
   FETCH BOOKINGS
========================= */
$stmt = $conn->query("
    SELECT b.*, u.username AS customer_name, s.name AS service_name
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    JOIN services s ON b.service_id = s.id
    ORDER BY b.booking_date DESC
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH LATEST QR IMAGE
========================= */
$qr = $conn->query("SELECT * FROM payment_qr ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="page-content">

    <h2>Payment Management</h2>

    <?php if($error): ?>
        <p class="msg error"><?= $error ?></p>
    <?php endif; ?>
    <?php if($success): ?>
        <p class="msg success"><?= $success ?></p>
    <?php endif; ?>

    <!-- Header / Navigation -->
    <div class="admin-nav">
        <a href="dashboard.php">Dashboard</a> |
        <a href="../auth/logout.php">Logout</a>
    </div>

    <!-- QR Upload Section -->
    <div class="section">
        <h3>Upload QR Code for Customers</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="qr_image" required>
            <button type="submit">Upload QR</button>
        </form>

        <?php if($qr): ?>
            <p>Latest QR uploaded by <?= htmlspecialchars($qr['role']) ?>:</p>
            <img src="../uploads/<?= htmlspecialchars($qr['qr_image']) ?>" alt="Payment QR" width="200">
        <?php endif; ?>
    </div>

    <!-- Bookings Table Section -->
    <div class="section">
        <h3>Bookings</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['customer_name']) ?></td>
                        <td><?= htmlspecialchars($b['service_name']) ?></td>
                        <td><?= $b['booking_date'] ?></td>
                        <td><?= $b['booking_time'] ?></td>
                        <td><?= ucfirst($b['status']) ?></td>
                        <td><?= ucfirst($b['payment_status']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <select name="payment_status">
                                    <option value="pending" <?= $b['payment_status']=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="paid" <?= $b['payment_status']=='paid'?'selected':'' ?>>Paid</option>
                                </select>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
