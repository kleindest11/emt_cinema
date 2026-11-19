

<?php
require_once __DIR__ . '/../config/db.php';

function customerEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$screeningId = filter_input(INPUT_GET, 'screening', FILTER_VALIDATE_INT);

if (!$screeningId) {
    exit('Invalid screening.');
}

$stmt = $conn->prepare("
    SELECT s.screening_id, s.screening_date, s.start_time,
           s.end_time, m.title, m.genre, m.poster,
           h.hall_id, h.hall_name
    FROM screenings s
    INNER JOIN movies m ON m.movie_id = s.movie_id
    INNER JOIN halls h ON h.hall_id = s.hall_id
    WHERE s.screening_id = ?
      AND s.screening_status = 'Scheduled'
      AND CONCAT(s.screening_date, ' ', s.start_time) > NOW()
    LIMIT 1
");
$stmt->bind_param("i", $screeningId);
$stmt->execute();
$screening = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$screening) {
    exit('This screening is no longer available.');
}

// Release expired booking holds for this screening.
$stmt = $conn->prepare("
    UPDATE bookings
    SET booking_status = 'Expired'
    WHERE screening_id = ?
      AND booking_status = 'Pending'
      AND hold_expires_at IS NOT NULL
      AND hold_expires_at <= NOW()
");
$stmt->bind_param("i", $screeningId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        se.seat_id,
        se.seat_number,
        CASE
            WHEN se.seat_status <> 'Available' THEN 'Maintenance'
            WHEN EXISTS (
                SELECT 1
                FROM tickets t
                WHERE t.screening_id = ?
                  AND t.seat_id = se.seat_id
                  AND t.ticket_status IN ('Valid', 'Collected', 'Used')
            ) THEN 'Booked'
            WHEN EXISTS (
                SELECT 1
                FROM booking_seats bs
                INNER JOIN bookings b ON b.booking_id = bs.booking_id
                WHERE bs.screening_id = ?
                  AND bs.seat_id = se.seat_id
                  AND (
                      b.booking_status IN ('Confirmed', 'Collected')
                      OR (
                          b.booking_status = 'Pending'
                          AND b.hold_expires_at > NOW()
                      )
                  )
            ) THEN 'Booked'
            ELSE 'Available'
        END AS availability
    FROM seats se
    WHERE se.hall_id = ?
    ORDER BY se.seat_number
");
$stmt->bind_param(
    "iii",
    $screeningId,
    $screeningId,
    $screening['hall_id']
);
$stmt->execute();
$seats = $stmt->get_result();

$pageTitle = 'Choose Your Seats';
require_once __DIR__ . '/header.php';
?>
<div class="container">
    <section class="card">
        <h1>Choose Your Seats</h1>
        <h2><?= customerEscape($screening['title']) ?></h2>
        <p><?= customerEscape($screening['hall_name']) ?></p>
        <p><?= customerEscape($screening['screening_date']) ?>
            • <?= customerEscape(date('h:i A', strtotime($screening['start_time']))) ?></p>
    </section>

    <section class="card">
        <h2>Screen</h2>
        <div style="background:linear-gradient(90deg,#7c3aed,#db2777);color:white;text-align:center;padding:12px;border-radius:12px;margin:20px auto;max-width:600px">
            SCREEN
        </div>

        <div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px">
            <span>🟢 Available</span>
            <span>🔴 Booked</span>
            <span>⚫ Maintenance</span>
        </div>

        <form method="POST" action="create_booking.php" id="seatForm">
            <input type="hidden" name="screening_id" value="<?= (int)$screeningId ?>">

            <div class="seat-grid">
                <?php while ($seat = $seats->fetch_assoc()): ?>
                    <label style="display:inline-flex;flex-direction:column;align-items:center;margin:6px">
                        <input
                            type="checkbox"
                            name="seat_ids[]"
                            value="<?= (int)$seat['seat_id'] ?>"
                            <?= $seat['availability'] !== 'Available' ? 'disabled' : '' ?>
                        >
                        <span style="
                            display:inline-block;
                            min-width:42px;
                            padding:10px 6px;
                            margin-top:5px;
                            border-radius:9px;
                            text-align:center;
                            background:<?= $seat['availability'] === 'Available' ? '#16a34a' : ($seat['availability'] === 'Booked' ? '#dc2626' : '#555') ?>;
                            color:white;">
                            <?= customerEscape($seat['seat_number']) ?>
                        </span>
                    </label>
                <?php endwhile; ?>
            </div>

            <hr>

            <label>Your Name</label>
            <input type="text" name="customer_name" maxlength="120" required>

            <label>Your Phone Number</label>
            <input type="tel" name="customer_phone" maxlength="30" required>

            <label>Email (Optional)</label>
            <input type="email" name="customer_email" maxlength="150">

            <label>Ticket Category</label>
            <select name="ticket_category" required>
                <option value="">Choose a ticket category</option>
                <option value="Weekday">Weekday</option>
                <option value="Weekend">Weekend</option>
                <option value="Premiere">Premiere</option>
                <option value="Couples">Couples</option>
                <option value="Family">Family</option>
                <option value="Special">Special</option>
            </select>

            <button class="btn btn-primary" type="submit">
                Continue to Booking
            </button>
        </form>
    </section>
</div>

<style>
.seat-grid {
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    max-width:650px;
    margin:auto;
}
.seat-grid input {
    accent-color:#16a34a;
}
.seat-grid label:has(input:disabled) {
    opacity:.8;
}
</style>
<?php require_once __DIR__ . '/footer.php'; ?>
