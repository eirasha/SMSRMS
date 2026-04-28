<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/phpqrcode/qrlib.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','massager'])) {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = $error = "";

// Upload folder
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Select booking
$bookings = $conn->query("SELECT id, customer_id, status, payment_status FROM bookings ORDER BY booking_date DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];

    // QR content: payment link for this booking
    $paymentLink = "http://localhost/SMSRMS/customer/pay.php?booking_id=$booking_id";

    // Generate QR
    $qrFileName = "qr_$booking_id.png";
    $qrFilePath = $uploadDir . $qrFileName;
    QRcode::png($paymentLink, $qrFilePath, 'L', 4, 2);

    // Save QR in DB (overwrite if exists)
    $stmt = $conn->prepare("INSERT INTO payment_qr (booking_id, uploaded_by, role, qr_image)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE qr_image=?");
    $stmt->execute([$booking_id, $user_id, $role, $qrFileName, $qrFileName]);

    $success = "QR generated for booking #$booking_id successfully!";
}
?>

<h2>Generate Payment QR</h2>

<?php if($success) echo "<p style='color:green;'>$success</p>"; ?>

<form method="post">
    Select Booking:
    <select name="booking_id" required>
        <option value="">--Select Booking--</option>
        <?php foreach($bookings as $b): ?>
            <option value="<?= $b['id'] ?>">Booking #<?= $b['id'] ?> - Status: <?= $b['status'] ?> - Payment: <?= $b['payment_status'] ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Generate QR</button>
</form>
