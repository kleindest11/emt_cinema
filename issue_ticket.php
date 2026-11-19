<?php

function issueTickets(mysqli $conn, int $bookingId, ?int $staffUserId = null): void
{
    $stmt = $conn->prepare("
        SELECT booking_id, screening_id, booking_status,
               hold_expires_at, number_of_seats
        FROM bookings
        WHERE booking_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        throw new Exception('Booking was not found.');
    }

    if (!in_array($booking['booking_status'], ['Pending', 'Confirmed'], true)) {
        throw new Exception('This booking cannot receive tickets.');
    }

    if (
        $booking['booking_status'] === 'Pending' &&
        $booking['hold_expires_at'] &&
        strtotime($booking['hold_expires_at']) <= time()
    ) {
        throw new Exception('The booking hold has expired.');
    }

    $screeningId = (int)$booking['screening_id'];

    $stmt = $conn->prepare("
        SELECT seat_id
        FROM booking_seats
        WHERE booking_id = ?
        ORDER BY seat_id
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $seatIds = [];
    while ($row = $result->fetch_assoc()) {
        $seatIds[] = (int)$row['seat_id'];
    }
    $stmt->close();

    if (count($seatIds) !== (int)$booking['number_of_seats']) {
        throw new Exception('The booking seat records are incomplete.');
    }

    /*
     * A ticket must not already exist for the same screening and seat.
     */
    $insert = $conn->prepare("
        INSERT INTO tickets (
            booking_id,
            screening_id,
            seat_id,
            ticket_code,
            ticket_status,
            created_at
        ) VALUES (?, ?, ?, ?, 'Valid', NOW())
    ");

    foreach ($seatIds as $seatId) {
        $ticketCode = 'EMT-' . strtoupper(bin2hex(random_bytes(6)));

        $insert->bind_param(
            "iiis",
            $bookingId,
            $screeningId,
            $seatId,
            $ticketCode
        );

        $insert->execute();
    }

    $insert->close();

    $stmt = $conn->prepare("
        UPDATE bookings
        SET booking_status = 'Confirmed',
            hold_expires_at = NULL
        WHERE booking_id = ?
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $stmt->close();

    if ($staffUserId !== null) {
        $action = 'Tickets issued';
        $tableName = 'bookings';
        $details = 'Tickets issued for booking ID ' . $bookingId;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = $conn->prepare("
            INSERT INTO audit_logs
                (user_id, action, table_name, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ississ",
            $staffUserId,
            $action,
            $tableName,
            $bookingId,
            $details,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}
