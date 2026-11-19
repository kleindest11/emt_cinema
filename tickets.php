<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$reference = trim($_GET['reference'] ?? '');
$booking = null;
$tickets = [];

if ($reference !== '') {
    $stmt = $conn->prepare("
        SELECT b.booking_id, b.booking_reference, b.customer_name,
               b.booking_status, m.title, s.screening_date,
               s.start_time, h.hall_name
        FROM bookings b
        INNER JOIN screenings s ON s.screening_id = b.screening_id
        INNER JOIN movies m ON m.movie_id = s.movie_id
        INNER JOIN halls h ON h.hall_id = s.hall_id
        WHERE b.booking_reference = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($booking && $booking['booking_status'] === 'Confirmed') {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find My Tickets | EMT Cinema</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="card" style="max-width:650px;margin:35px auto">
        <h1>Find Your Tickets</h1>
        <p>Enter the booking reference you received when booking.</p>

        <form method="GET">
            <input type="text" name="reference"
                   placeholder="EMT-XXXXXXXXXX"
                   value="<?= h($reference) ?>"
                   required>
            <button class="btn btn-primary" type="submit">Find Tickets</button>
        </form>

        <?php if ($reference !== ''): ?>
            <?php if (!$booking): ?>
                <p>No booking was found with that reference.</p>
            <?php elseif ($booking['booking_status'] !== 'Confirmed'): ?>
                <p>Your booking is not confirmed yet. Please check payment status.</p>
            <?php else: ?>
                <hr>
                <h2><?= h($booking['title']) ?></h2>
                <p><?= h($booking['hall_name']) ?></p>
                <p><?= h(date('D, d M Y', strtotime($booking['screening_date']))) ?></p>

                <?php foreach ($tickets as $ticket): ?>
                    <div class="card" style="margin:12px 0">
                        <h3><?= h($ticket['ticket_code']) ?></h3>
                        <p>Seat: <?= h($ticket['seat_number']) ?></p>
                        <p>Status: <?= h($ticket['ticket_status']) ?></p>
                    </div>
                <?php endforeach; ?>

                <button class="btn btn-primary" onclick="window.print()">Print Tickets</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
