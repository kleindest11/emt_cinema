
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
    SELECT
        b.booking_id,
        b.booking_reference,
        b.customer_name,
        b.total_amount,
        b.booking_status,
        m.title,
        s.screening_date,
        s.start_time,
        h.hall_name,
        p.payment_status,
        p.payment_method,
        p.transaction_reference
    FROM bookings b
    INNER JOIN screenings s ON s.screening_id = b.screening_id
    INNER JOIN movies m ON m.movie_id = s.movie_id
    INNER JOIN halls h ON h.hall_id = s.hall_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    WHERE b.booking_reference = ?
    ORDER BY p.payment_id DESC
    LIMIT 1
");
$stmt->bind_param("s", $reference);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    exit('Booking not found.');
}

$tickets = [];

if ($booking['booking_status'] === 'Confirmed') {
    $stmt = $conn->prepare("
        SELECT t.ticket_code, t.ticket_status, se.seat_number
        FROM tickets t
        INNER JOIN seats se ON se.seat_id = t.seat_id
        WHERE t.booking_id = ?
        ORDER BY se.seat_number
    ");
    $stmt->bind_param("i", $booking['booking_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | EMT Cinema</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="card" style="max-width:650px;margin:35px auto">
        <h1>Booking Payment Status</h1>
        <p>Reference: <strong><?= h($booking['booking_reference']) ?></strong></p>
        <h2><?= h($booking['title']) ?></h2>
        <p><?= h($booking['hall_name']) ?></p>
        <p>
            <?= h(date('D, d M Y', strtotime($booking['screening_date']))) ?>
            • <?= h(date('h:i A', strtotime($booking['start_time']))) ?>
        </p>
        <p>Total: <strong>UGX <?= number_format((float)$booking['total_amount']) ?></strong></p>

        <?php if ($booking['booking_status'] === 'Confirmed'): ?>
            <h2>Payment confirmed</h2>
            <p>Your tickets have been generated.</p>

            <?php foreach ($tickets as $ticket): ?>
                <div style="border:1px dashed #a78bfa;border-radius:12px;padding:15px;margin:12px 0">
                    <p><strong>Ticket number:</strong></p>
                    <h3><?= h($ticket['ticket_code']) ?></h3>
                    <p>Seat: <?= h($ticket['seat_number']) ?></p>
                    <p>Status: <?= h($ticket['ticket_status']) ?></p>
                </div>
            <?php endforeach; ?>

            <button class="btn btn-primary" onclick="window.print()">
                Print Tickets
            </button>

        <?php elseif ($booking['booking_status'] === 'Pending'): ?>
            <h2>Payment pending</h2>
            <p>
                We have not yet received verified payment confirmation.
                Please do not make another payment until you have checked
                your Mobile Money message or contacted the cashier.
            </p>
            <button class="btn btn-primary" onclick="location.reload()">
                Refresh Status
            </button>

        <?php else: ?>
            <h2>Booking <?= h($booking['booking_status']) ?></h2>
            <p>Please contact the cinema if you need assistance.</p>
        <?php endif; ?>

        <p><a href="home.php">Return to Home</a></p>
    </div>
</div>
</body>
</html>
