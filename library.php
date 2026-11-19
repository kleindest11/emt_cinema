<?php
require_once __DIR__ . '/../config/db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$movies = $conn->query("
    SELECT movie_id, title, genre, duration_minutes,
           description, poster, streaming_rights_confirmed,
           download_rights_confirmed
    FROM movies
    WHERE movie_status = 'Archived'
      AND (streaming_rights_confirmed = 1
           OR download_rights_confirmed = 1)
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Movie Library | EMT Cinema</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
<div class="container">
    <div class="brand">EMT CINEMA</div>
    <div class="tagline">COME FOR THE MOVIE, STAY FOR THE FEELING</div>
    <nav class="navbar">
        <a href="home.php">Home</a>
        <a class="active" href="library.php">Library</a>
        <a href="ticket.php">Get Tickets</a>
        <a href="offers.php">Offers</a>
        <a href="../staff_login.php">Staff Login</a>
        <button class="theme-toggle" onclick="toggleTheme()">Theme</button>
    </nav>
</div>
</header>

<main class="container">
    <div class="page-heading">
        <h1>Movie Library</h1>
        <p class="muted">Explore movies previously featured at EMT Cinema.</p>
    </div>

    <div class="movie-grid">
    <?php if ($movies->num_rows > 0): ?>
        <?php while ($movie = $movies->fetch_assoc()): ?>
            <article class="movie-card">
                <?php if (!empty($movie['poster'])): ?>
                    <img src="../uploads/posters/<?= h(basename($movie['poster'])) ?>"
                         alt="<?= h($movie['title']) ?>">
                <?php else: ?>
                    <div style="height:270px;background:linear-gradient(135deg,#7136a8,#e84d9b);display:grid;place-items:center;color:white;">
                        EMT CINEMA
                    </div>
                <?php endif; ?>

                <div class="movie-card-content">
                    <h3><?= h($movie['title']) ?></h3>
                    <p class="muted">
                        <?= h($movie['genre']) ?> •
                        <?= (int)$movie['duration_minutes'] ?> minutes
                    </p>

                    <p><?= h($movie['description']) ?></p>

                    <?php if ((int)$movie['streaming_rights_confirmed'] === 1): ?>
                        <span class="badge badge-success">Streaming available</span>
                    <?php endif; ?>

                    <?php if ((int)$movie['download_rights_confirmed'] === 1): ?>
                        <span class="badge">Download available</span>
                    <?php endif; ?>

                    <p>
                        <a class="btn" href="movie.php?id=<?= (int)$movie['movie_id'] ?>">
                            View Movie
                        </a>
                    </p>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            No past movies are currently available in the library.
        </div>
    <?php endif; ?>
    </div>
</main>

<footer class="site-footer">
    EMT CINEMA • COME FOR THE MOVIE, STAY FOR THE FEELING
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
