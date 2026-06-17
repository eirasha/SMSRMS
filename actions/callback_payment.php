<?php
require_once __DIR__ . '/../config/db.php';

ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;
    if ($status === 1) {
        header("Location: /SMSRMS/customer/payment.php?status=success");
    } else {
        header("Location: /SMSRMS/customer/payment.php?status=failed");
    }
    exit;
}

$refno      = isset($_POST['refno'])      ? trim($_POST['refno'])       : '';
$status     = isset($_POST['status_id'])     ? (int)$_POST['status_id']       : 0;
$booking_id = isset($_POST['order_id'])   ? (int)$_POST['order_id']     : 0;
$amount     = isset($_POST['amount'])     ? trim($_POST['amount'])      : '0.00';
$billcode   = isset($_POST['billcode'])   ? trim($_POST['billcode'])    : '';

// ✅ CORRECTED: ToyyibPay MD5 Checksum Validation
$secret_key        = '7sf86sm0-nugp-hnvz-yssw-w3ay5sf8jsw3'; // MUST match the one in checkout_payment.php
$expected_checksum = md5($billcode . $booking_id . $status . $secret_key);
$received_checksum = isset($_POST['checksum']) ? trim($_POST['checksum']) : '';

if ($received_checksum !== $expected_checksum) {
    error_log("❌ Security Alert: Invalid checksum for Booking #$booking_id");
    echo "FAIL";
    exit;
}

if ($status === 1) {
    try {
        $conn->beginTransaction();

        $check_stmt = $conn->prepare("SELECT customer_id, payment_status FROM bookings WHERE id = ?");
        $check_stmt->execute([$booking_id]);
        $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $conn->rollBack();
            error_log("❌ Callback Error: Booking #$booking_id not found.");
            echo "FAIL";
            exit;
        }

        if ($booking['payment_status'] === 'paid') {
            $conn->rollBack();
            echo "OK";
            exit;
        }

        $update_stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?");
        $update_stmt->execute([$booking_id]);

        $ledger_stmt = $conn->prepare("
            INSERT INTO payments (user_id, booking_id, transaction_ref, amount, status, created_at)
            VALUES (?, ?, ?, ?, 'verified', NOW())
        ");
        $ledger_stmt->execute([
            $booking['customer_id'],
            $booking_id,
            $refno,
            $amount
        ]);

        $conn->commit();
        error_log("✅ Payment Verified: Booking #$booking_id | Ref: $refno");
        echo "OK";
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("❌ Callback Exception: " . $e->getMessage());
        echo "FAIL";
        exit;
    }
} else {
    error_log("⚠️ Payment status $status received for Booking #$booking_id");
    echo "OK";
    exit;
}