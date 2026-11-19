<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function paymentError($message) {
    exit(
        '<div style="font-family:Arial;padding:30px">' .
        '<h2>Payment not started</h2><p>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
        '</p><a href="javascript:history.back()">Go back</a></div>'
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.php');
    exit;
}

$reference = trim($_POST['booking_reference'] ?? '');
$method = trim($_POST['payment_method'] ?? '');
$phone = trim($_POST['payment_phone'] ?? '');

$sessionReference = $_SESSION['customer_booking_reference'] ?? '';

if (
    $reference === '' ||
    $sessionReference === '' ||
    !hash_equals($sessionReference, $reference)
) {
    paymentError('Invalid booking reference.');
}

if (!in_array($method, ['MTN_MOMO', 'AIRTEL_MONEY'], true)) {
    paymentError('Select a valid Mobile Money payment method.');
}

if ($phone === '' || strlen($phone) > 20) {
    paymentError('Enter a valid Mobile Money phone number.');
}

$stmt = $conn->prepare("
    SELECT booking_id, booking_reference, total_amount,
           booking_status, hold_expires_at
    FROM bookings
    WHERE booking_reference = ?
    LIMIT 1
");
$stmt->bind_param("s", $reference);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking || $booking['booking_status'] !== 'Pending') {
    paymentError('This booking is no longer awaiting payment.');
}

if (
    empty($booking['hold_expires_at']) ||
    strtotime($booking['hold_expires_at']) <= time()
) {
    paymentError('Your seat reservation has expired. Please book again.');
}

/*
 * Avoid creating duplicate pending payment records for the same booking
 * and payment method.
 */
$stmt = $conn->prepare("
    SELECT payment_id
    FROM payments
    WHERE booking_id = ?
      AND payment_method = ?
      AND payment_status = 'Pending'
    LIMIT 1
");
$stmt->bind_param("is", $booking['booking_id'], $method);
$stmt->execute();
$existingPayment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existingPayment) {
    $amount = (float)$booking['total_amount'];

    $stmt = $conn->prepare("
        INSERT INTO payments (
            booking_id,
            payment_method,
            amount,
            payment_status,
            created_at
        ) VALUES (?, ?, ?, 'Pending', NOW())
    ");

    $stmt->bind_param(
        "isd",
        $booking['booking_id'],
        $method,
        $amount
    );

    $stmt->execute();
    $paymentId = $conn->insert_id;
    $stmt->close();
} else {
    $paymentId = (int)$existingPayment['payment_id'];
}

/*
 * The actual MTN/Airtel payment request must be sent through the
 * respective merchant API. Do not mark this payment Successful here.
 */
$_SESSION['customer_payment_reference'] = $reference;
$_SESSION['customer_payment_id'] = $paymentId;

header(
    'Location: payment_status.php?reference=' . urlencode($reference)
);
exit;
