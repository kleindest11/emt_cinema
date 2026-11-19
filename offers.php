
<?php
require_once __DIR__ . '/../config/db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$offers = $conn->query("
    SELECT offer_title, description, discount_type,
           discount_value, start_date, end_date
    FROM offers
    WHERE offer_status = 'Active'
      AND start_date <= CURDATE()
      AND end_date >= CURDATE()
    ORDER BY end_date ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Special Offers | EMT Cinema</title>
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
        <a href="ticket.php">Get Tickets</a>
        <a class="active" href="offers.php">Offers</a>
        <a href="../staff_login.php">Staff Login</a>
    </nav>
</div>
</header>

<main class="container">
    <div class="page-heading">
        <h1>Special Offers & Packages</h1>
        <p class="muted">Enjoy available cinema offers and packages.</p>
    </div>

    <div class="card-grid">
    <?php if ($offers->num_rows > 0): ?>
        <?php while ($offer = $offers->fetch_assoc()): ?>
            <div class="card">
                <span class="badge">Special Offer</span>
                <h2><?= h($offer['offer_title']) ?></h2>
                <p><?= nl2br(h($offer['description'])) ?></p>
                <h3>
                    <?= $offer['discount_type'] === 'Percentage'
                        ? h($offer['discount_value']) . '% off'
                        : 'UGX ' . number_format((float)$offer['discount_value']) . ' off' ?>
                </h3>
                <p class="muted">
                    Valid until <?= h(date('d M Y', strtotime($offer['end_date']))) ?>
                </p>
                <a class="btn btn-pink" href="ticket.php">Book Tickets</a>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            No active special offers at the moment. Check again soon!
        </div>
    <?php endif; ?>
    </div>
</main>

<footer class="site-footer">EMT CINEMA</footer>
<script src="../assets/js/main.js"></script>
</body>
</html>
