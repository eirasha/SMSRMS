<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['booking_id'])) {
    header("Location: ../customer/calendar.php?error=missing_booking");
    exit;
}

$booking_id = (int)$_GET['booking_id'];
$user_id    = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT b.id, b.payment_status, s.price, s.name AS service_name, u.username, u.email 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        JOIN users u ON b.customer_id = u.id 
        WHERE b.id = ? AND b.customer_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: ../customer/calendar.php?error=booking_not_found");
        exit;
    }

    if ($booking['payment_status'] !== 'unpaid') {
        header("Location: ../customer/calendar.php?error=already_processed");
        exit;
    }

    $amount_in_cents = (int)round($booking['price'] * 100);

    $category_code = "b2vojdo8";
    $secret_key    = "7sf86sm0-nugp-hnvz-yssw-w3ay5sf8jsw3";
    $url           = "https://toyyibpay.com/index.php/api/createBill";

   $base_url = 'https://conduit-dreamland-underdone.ngrok-free.dev/SMSRMS/';

    $bill_name = substr('Sunflower - ' . $booking['service_name'], 0, 30);
    $bill_desc = substr('Reservation #' . $booking['id'], 0, 100);

    $payload = [
        'userSecretKey'           => $secret_key,
        'categoryCode'            => $category_code,
        'billName'                => $bill_name,
        'billDescription'         => $bill_desc,
        'billPriceSetting'        => 1,
        'billPayorInfo'           => 1,
        'billAmount'              => $amount_in_cents,
        'billReturnUrl'  => $base_url . "actions/return_payment.php?booking_id=" . $booking['id'],
        'billCallbackUrl'=> $base_url . "actions/callback_payment.php",
        'billExternalReferenceNo' => $booking['id'],
        'billTo'                  => $booking['username'],
        'billEmail'               => $booking['email'],
        'billPhone'               => '0123456789'
    ];

    $curl = curl_init($url);
    if (!$curl) {
        error_log("checkout_payment.php: curl_init failed");
        header("Location: ../customer/calendar.php?error=gateway_unreachable");
        exit;
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($curl);
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    unset($curl);

    if ($curl_errno) {
        error_log("checkout_payment.php curl error: " . $curl_error);
        header("Location: ../customer/calendar.php?error=gateway_unreachable");
        exit;
    }

    $res_data = json_decode($response, true);

    if (isset($res_data[0]['BillCode'])) {
        $bill_code = $res_data[0]['BillCode'];
        header("Location: https://toyyibpay.com/" . $bill_code);
        exit;
    } else {
        $tp_msg = $res_data[0]['msg'] ?? $res_data['msg'] ?? 'Unknown response';
        error_log("checkout_payment.php gateway rejected: " . $tp_msg);
        header("Location: ../customer/calendar.php?error=gateway_rejected");
        exit;
    }

} catch (PDOException $e) {
    error_log("checkout_payment.php PDO error: " . $e->getMessage());
    header("Location: ../customer/calendar.php?error=server_error");
    exit;
}