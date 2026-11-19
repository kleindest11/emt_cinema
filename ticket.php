<?php
require_once __DIR__ . '/../config/db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$selectedScreening = filter_input(INPUT_GET, 'screening', FILTER_VALIDATE_INT);

$stmt = $conn->prepare("
    SELECT s.screening_id, s.screening_date, s.start_time,
           s.end_time, s.screening_type,
           h.hall_name, m.title, m.genre, m.poster,
           m.duration_minutes
    FROM screenings s
    INNER JOIN halls h ON s.hall_id = h.hall_id
    INNER JOIN movies m ON s.movie_id = m.movie_id
    WHERE s.screening_status = 'Scheduled'
      AND s.screening_date >= CURDATE()
    ORDER BY s.screening_date, s.start_time
");
$stmt->execute();
$screenings = $stmt->get_result();

$prices = $conn->query("
    SELECT category, price, number_of_seats
    FROM ticket_prices
    WHERE price_status = 'Active'
    ORDER BY price_id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Tickets | EMT Cinema</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
<div class="container">
    <div class="brand">EMT CINEMA</div>
    <div class="tagline">COME FOR THE MOVIE, STAY FOR THE FEELING</div>
    <nav class="navbar">
        <a href="home.php">Home</a>
        <a href="library.php">Library</a>
        <a class="active" href="ticket.php">Get Tickets</a>
        <a href="offers.php">Offers</a>
        <a href="../staff_login.php">Staff Login</a>
    </nav>
</div>
</header>

<main class="container">

    <div class="page-heading">
        <h1>Book Your Movie Ticket</h1>
        <p class="muted">Choose a screening to begin your booking.</p>
    </div>

    <div class="card">
        <h2>Available Screenings</h2>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Hall</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($screenings->num_rows > 0): ?>
                <?php while ($screening = $screenings->fetch_assoc()): ?>
                    <tr>
                        <td><?= h($screening['title']) ?></td>
                        <td><?= h($screening['hall_name']) ?></td>
                        <td><?= h(date('d M Y', strtotime($screening['screening_date']))) ?></td>
                        <td><?= h(date('g:i A', strtotime($screening['start_time']))) ?></td>
                        <td><?= h($screening['screening_type']) ?></td>
                        <td>
                            <a class="btn btn-pink"
                               href="seats.php?screening=<?= (int)$screening['screening_id'] ?>">
                                Select Seats
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No upcoming screenings are available.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="page-heading">
        <h2>Ticket Prices</h2>
        <div class="card-grid">
            <?php while ($price = $prices->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= h($price['category']) ?></h3>
                    <h2>
                        <?= $price['price'] === null
                            ? 'To be announced'
                            : 'UGX ' . number_format((float)$price['price']) ?>
                    </h2>
                    <p class="muted">
                        <?= (int)$price['number_of_seats'] ?> seat(s)
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="card">
        <h2>Weekend Packages</h2>
        <p>
            Weekend and group packages will appear here once their prices
            have been confirmed by EMT Cinema.
        </p>
    </div>

    <div class="card">
        <h2>Special Offers</h2>
        <p>Visit our offers page to see current promotions.</p>
        <a class="btn" href="offers.php">View Offers</a>
    </div>

</main>

<footer class="site-footer">
    EMT CINEMA • COME FOR THE MOVIE, STAY FOR THE FEELING
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
