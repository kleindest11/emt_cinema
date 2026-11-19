
<?php
require_once __DIR__ . '/../config/db.php';

$today = date('Y-m-d');

// Escape output safely
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Movie poster path
function movieImage($poster) {
    if (!empty($poster)) {
        $filename = basename($poster);

        if (preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return '../uploads/posters/' . $filename;
        }
    }

    return '';
}

// Get movies showing today
$showing = $conn->prepare("
    SELECT
        s.screening_id,
        s.screening_date,
        s.start_time,
        s.end_time,
        s.screening_type,
        h.hall_name,
        m.movie_id,
        m.title,
        m.genre,
        m.duration_minutes,
        m.description,
        m.poster,
        m.parental_guidance
    FROM screenings s
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_date = ?
      AND s.screening_status = 'Scheduled'
      AND m.movie_status = 'Showing'
    ORDER BY h.hall_name, s.start_time
");

$showing->bind_param("s", $today);
$showing->execute();
$showingResult = $showing->get_result();

// Get upcoming movies
$upNext = $conn->query("
    SELECT
        s.screening_id,
        s.screening_date,
        s.start_time,
        h.hall_name,
        m.title,
        m.poster,
        m.genre
    FROM screenings s
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_date > CURDATE()
      AND s.screening_status = 'Scheduled'
    ORDER BY s.screening_date, s.start_time
    LIMIT 8
");

// Get premieres
$premieres = $conn->query("
    SELECT
        s.screening_id,
        s.screening_date,
        s.start_time,
        h.hall_name,
        m.title,
        m.poster,
        m.genre
    FROM screenings s
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_type = 'Premiere'
      AND s.screening_status = 'Scheduled'
      AND s.screening_date >= CURDATE()
    ORDER BY s.screening_date, s.start_time
    LIMIT 6
");

// Get screenings for the next seven days
$weekStart = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+6 days'));

$weekly = $conn->prepare("
    SELECT
        s.screening_id,
        s.screening_date,
        s.start_time,
        h.hall_name,
        m.title,
        m.poster
    FROM screenings s
    INNER JOIN movies m ON s.movie_id = m.movie_id
    INNER JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_date BETWEEN ? AND ?
      AND s.screening_status = 'Scheduled'
    ORDER BY s.screening_date, s.start_time
");

$weekly->bind_param("ss", $weekStart, $weekEnd);
$weekly->execute();
$weeklyResult = $weekly->get_result();

// Get active ticket categories
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

<title>EMT Cinema | Home</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

/* Movie poster images */
.movie-poster {
    width: 100%;
    height: 280px;
    object-fit: cover;
    display: block;
    border-radius: 10px;
    background: #29202f;
}

.poster-placeholder {
    width: 100%;
    height: 280px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #7136a8, #e84d9b);
    color: white;
    font-size: 20px;
    font-weight: bold;
    border-radius: 10px;
}

.screening-poster {
    width: 100%;
    height: 230px;
    object-fit: cover;
    display: block;
    border-radius: 10px;
    margin-bottom: 15px;
    background: #29202f;
}

.movie-card,
.card {
    overflow: hidden;
}

.movie-card img,
.card img {
    max-width: 100%;
}

.movie-card-content {
    padding: 15px;
}

.movie-card h3,
.card h3 {
    margin-top: 12px;
}

.movie-section {
    margin-top: 40px;
    margin-bottom: 40px;
}

.movie-grid,
.card-grid {
    gap: 20px;
}

.movie-description {
    color: #777;
    font-size: 14px;
}

.booking-link {
    display: inline-block;
    margin-top: 10px;
}

</style>
</head>

<body>

<!-- HEADER -->
<header class="site-header">

    <div class="container">

        <div class="brand">EMT CINEMA</div>

        <div class="tagline">
            COME FOR THE MOVIE, STAY FOR THE FEELING
        </div>

        <nav class="navbar">

            <a class="active" href="home.php">Home</a>

            <a href="library.php">Movie Library</a>

            <a href="ticket.php">Get Tickets</a>

            <a href="offers.php">Offers</a>

            <a href="contact.php">Contact</a>

            <a href="../staff_login.php">Staff Login</a>

            <button class="theme-toggle" onclick="toggleTheme()">
                Theme
            </button>

        </nav>

    </div>

</header>


<main class="container">

    <!-- WELCOME SECTION -->
    <section class="page-heading">

        <h1>Your next favourite movie awaits.</h1>

        <p class="muted">
            Enjoy the big screen at EMT Cinema,
            Ham Towers, Makerere Hill Road.
        </p>

        <a class="btn btn-pink" href="ticket.php">
            Book Your Seat
        </a>

        <a class="btn" href="library.php">
            Explore Movie Library
        </a>

    </section>


    <!-- NOW SHOWING -->
    <section class="movie-section">

        <h2>Now Showing Today</h2>

        <div class="movie-grid">

            <?php if ($showingResult->num_rows > 0): ?>

                <?php while ($movie = $showingResult->fetch_assoc()): ?>

                    <article class="movie-card">

                        <?php $posterPath = movieImage($movie['poster']); ?>

                        <?php if ($posterPath): ?>

                            <img
                                class="movie-poster"
                                src="<?= h($posterPath) ?>"
                                alt="<?= h($movie['title']) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="poster-placeholder">
                                EMT CINEMA
                            </div>

                        <?php endif; ?>

                        <div class="movie-card-content">

                            <h3><?= h($movie['title']) ?></h3>

                            <p class="muted">
                                <?= h($movie['genre']) ?>
                                •
                                <?= (int)$movie['duration_minutes'] ?> minutes
                            </p>

                            <span class="badge">
                                <?= h($movie['hall_name']) ?>
                            </span>

                            <?php if (!empty($movie['parental_guidance'])): ?>
                                <p class="movie-description">
                                    Guidance:
                                    <?= h($movie['parental_guidance']) ?>
                                </p>
                            <?php endif; ?>

                            <p>
                                <?= h(date('g:i A', strtotime($movie['start_time']))) ?>
                            </p>

                            <a
                                class="btn btn-pink"
                                href="ticket.php?screening=<?= (int)$movie['screening_id'] ?>"
                            >
                                Book Seat
                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="card">
                    No movies have been scheduled for today yet.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- UPCOMING SCREENINGS -->
    <section class="movie-section">

        <h2>Up Next</h2>

        <div class="card-grid">

            <?php if ($upNext && $upNext->num_rows > 0): ?>

                <?php while ($movie = $upNext->fetch_assoc()): ?>

                    <div class="card">

                        <?php $posterPath = movieImage($movie['poster']); ?>

                        <?php if ($posterPath): ?>

                            <img
                                class="screening-poster"
                                src="<?= h($posterPath) ?>"
                                alt="<?= h($movie['title']) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="poster-placeholder">
                                EMT CINEMA
                            </div>

                        <?php endif; ?>

                        <h3><?= h($movie['title']) ?></h3>

                        <p class="muted">
                            <?= h($movie['genre']) ?>
                        </p>

                        <p>
                            <?= h($movie['hall_name']) ?>
                        </p>

                        <p>
                            <?= h(date('D, d M Y', strtotime($movie['screening_date']))) ?>
                        </p>

                        <p>
                            <?= h(date('g:i A', strtotime($movie['start_time']))) ?>
                        </p>

                        <a
                            class="btn"
                            href="ticket.php?screening=<?= (int)$movie['screening_id'] ?>"
                        >
                            View Screening
                        </a>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="card">
                    No upcoming screenings are currently published.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- WEEKLY SCREENINGS -->
    <section class="movie-section">

        <h2>This Week at EMT Cinema</h2>

        <div class="table-wrap card">

            <table>

                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Hall</th>
                        <th>Poster</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($weeklyResult->num_rows > 0): ?>

                        <?php while ($movie = $weeklyResult->fetch_assoc()): ?>

                            <tr>

                                <td><?= h($movie['title']) ?></td>

                                <td>
                                    <?= h(date('d M Y', strtotime($movie['screening_date']))) ?>
                                </td>

                                <td>
                                    <?= h(date('g:i A', strtotime($movie['start_time']))) ?>
                                </td>

                                <td><?= h($movie['hall_name']) ?></td>

                                <td>
                                    <?php $posterPath = movieImage($movie['poster']); ?>

                                    <?php if ($posterPath): ?>

                                        <img
                                            src="<?= h($posterPath) ?>"
                                            alt="<?= h($movie['title']) ?>"
                                            style="width:65px;height:85px;object-fit:cover;border-radius:6px;"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        No poster

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="ticket.php?screening=<?= (int)$movie['screening_id'] ?>">
                                        Book
                                    </a>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">
                                No weekly screenings published.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- PREMIERES -->
    <section class="movie-section">

        <h2>Premieres</h2>

        <div class="card-grid">

            <?php if ($premieres && $premieres->num_rows > 0): ?>

                <?php while ($movie = $premieres->fetch_assoc()): ?>

                    <div class="card">

                        <?php $posterPath = movieImage($movie['poster']); ?>

                        <?php if ($posterPath): ?>

                            <img
                                class="screening-poster"
                                src="<?= h($posterPath) ?>"
                                alt="<?= h($movie['title']) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="poster-placeholder">
                                EMT CINEMA
                            </div>

                        <?php endif; ?>

                        <span class="badge">
                            Premiere
                        </span>

                        <h3><?= h($movie['title']) ?></h3>

                        <p class="muted">
                            <?= h($movie['genre']) ?>
                        </p>

                        <p><?= h($movie['hall_name']) ?></p>

                        <p>
                            <?= h(date('d M Y', strtotime($movie['screening_date']))) ?>
                        </p>

                        <p>
                            <?= h(date('g:i A', strtotime($movie['start_time']))) ?>
                        </p>

                        <a
                            class="btn btn-pink"
                            href="ticket.php?screening=<?= (int)$movie['screening_id'] ?>"
                        >
                            Book Premiere
                        </a>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="card">
                    No premieres have been announced yet.
                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- TICKET CATEGORIES -->
    <section class="movie-section">

        <h2>Ticket Categories</h2>

        <div class="card-grid">

            <?php if ($prices && $prices->num_rows > 0): ?>

                <?php while ($price = $prices->fetch_assoc()): ?>

                    <div class="card">

                        <h3><?= h($price['category']) ?></h3>

                        <p>
                            <?= $price['price'] === null
                                ? 'Price to be announced'
                                : 'UGX ' . number_format((float)$price['price']) ?>
                        </p>

                        <p class="muted">
                            <?= (int)$price['number_of_seats'] ?> seat(s)
                        </p>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="card">
                    Ticket categories will be announced soon.
                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- FOOTER -->
<footer class="site-footer">

    <div class="container">

        <strong>EMT CINEMA</strong>

        <p>
            COME FOR THE MOVIE, STAY FOR THE FEELING
        </p>

        <p>
            Ham Towers, Makerere Hill Road
        </p>

    </div>

</footer>


<script src="../assets/js/main.js"></script>

</body>
</html>
