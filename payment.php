<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$reference = trim($_GET['reference'] ?? '');
$sessionReference = $_SESSION['customer_booking_reference'] ?? '';

if (
    $reference === '' ||
    $sessionReference === '' ||
    !hash_equals($sessionReference, $reference)
) {
    exit('Invalid booking reference.');
}

$stmt = $conn->prepare("
    SELECT booking_id, booking_reference, customer_name,
           customer_phone, total_amount, booking_status,
           hold_expires_at
    FROM bookings
    WHERE booking_reference = ?
    LIMIT 1
");
$stmt->bind_param("s", $reference);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    exit('Booking not found.');
}

if ($booking['booking_status'] !== 'Pending') {
    exit('This booking is no longer awaiting payment.');
}

if (
    empty($booking['hold_expires_at']) ||
    strtotime($booking['hold_expires_at']) <= time()
) {
    exit('Your seat reservation has expired. Please make a new booking.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | EMT Cinema</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-option {
            display:block;
            padding:18px;
            border:1px solid #ddd;
            border-radius:13px;
            margin:12px 0;
            cursor:pointer;
        }
        .payment-option:has(input:checked) {
            border:2px solid #a855f7;
            background:rgba(168,85,247,.08);
        }
        .payment-option input { margin-right:10px; }
        .field {
            width:100%;
            padding:12px;
            border:1px solid #ccc;
            border-radius:10px;
            margin:8px 0 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card" style="max-width:600px;margin:35px auto">
        <h1>Complete Your Payment</h1>
        <p>Booking reference: <strong><?= h($reference) ?></strong></p>
        <h2>UGX <?= number_format((float)$booking['total_amount']) ?></h2>

        <p>
            Your seats are temporarily held until
            <?= h(date('h:i A', strtotime($booking['hold_expires_at']))) ?>.
        </p>

        <form action="process_payment.php" method="POST">
            <input type="hidden" name="booking_reference"
                   value="<?= h($reference) ?>">

            <h3>Select payment method</h3>

            <label class="payment-option">
                <input type="radio" name="payment_method"
                       value="MTN_MOMO" required>
                <strong>MTN Mobile Money</strong>
                <p>Pay using your MTN Mobile Money account.</p>
            </label>

            <label class="payment-option">
                <input type="radio" name="payment_method"
                       value="AIRTEL_MONEY" required>
                <strong>Airtel Money</strong>
                <p>Pay using your Airtel Money account.</p>
            </label>

            <label for="payment_phone">Mobile Money phone number</label>
            <input class="field" type="tel" name="payment_phone"
                   id="payment_phone" maxlength="20"
                   value="<?= h($booking['customer_phone']) ?>"
                   required>

            <button class="btn btn-primary" type="submit">
                Continue
            </button>
        </form>

        <p style="margin-top:20px">
            <small>
                Never share your Mobile Money PIN with cinema staff or enter it
                into an unverified website. A genuine payment request should be
                handled through the official Mobile Money service.
            </small>
        </p>
    </div>
</div>
</body>
</html>
