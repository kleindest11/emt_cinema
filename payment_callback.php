<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/issue_tickets.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_EMT_SIGNATURE'] ?? '';

$expectedSignature = hash_hmac(
    'sha256',
    $rawBody,
    PAYMENT_CALLBACK_SECRET
);

if (
    !hash_equals(PAYMENT_CALLBACK_SECRET, 'REPLACE_WITH_A_LONG_RANDOM_SECRET') &&
    hash_equals($expectedSignature, $signature)
) {
    // The placeholder has been replaced and the signature is valid.
} else {
    http_response_code(401);
    exit('Invalid payment notification signature.');
}

$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid notification data.');
}

$providerReference = trim($data['provider_reference'] ?? '');
$transactionReference = trim($data['transaction_reference'] ?? '');
$status = trim($data['status'] ?? '');
$amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);

if (
    $providerReference === '' ||
    $transactionReference === '' ||
    $amount === false ||
    !is_numeric($amount)
) {
    http_response_code(400);
    exit('Missing payment details.');
}

if (!in_array($status, ['SUCCESSFUL', 'FAILED'], true)) {
    http_response_code(400);
    exit('Unsupported payment status.');
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        SELECT payment_id, booking_id, amount, payment_status
        FROM payments
        WHERE provider_reference = ?
        FOR UPDATE
    ");
    $stmt->bind_param("s", $providerReference);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception('Payment request not found.');
    }

    if (abs((float)$payment['amount'] - (float)$amount) > 0.01) {
        throw new Exception('Payment amount does not match.');
    }

    if ($payment['payment_status'] === 'Successful') {
        $conn->commit();
        http_response_code(200);
        exit('Already processed.');
    }

    if ($status === 'SUCCESSFUL') {
        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_status = 'Successful',
                transaction_reference = ?,
                payment_time = NOW()
            WHERE payment_id = ?
        ");
        $stmt->bind_param(
            "si",
            $transactionReference,
            $payment['payment_id']
        );
        $stmt->execute();
        $stmt->close();

        issueTickets($conn, (int)$payment['booking_id']);
    } else {
        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_status = 'Failed',
                transaction_reference = ?,
                payment_time = NOW()
            WHERE payment_id = ?
        ");
        $stmt->bind_param(
            "si",
            $transactionReference,
            $payment['payment_id']
        );
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    http_response_code(200);
    echo 'Processed';

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Payment callback error: ' . $e->getMessage());
    http_response_code(400);
    echo 'Unable to process notification.';
}
