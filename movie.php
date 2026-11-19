
6.
<?php
require_once __DIR__ . '/../config/db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit('Invalid movie selected.');
}

$stmt = $conn->prepare("
    SELECT *
    FROM movies
    WHERE movie_id = ?
      AND movie_status = 'Archived'
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();

$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    exit('Movie not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($movie['title']) ?> | EMT Cinema</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
<div class="container">
    <div class="brand">EMT CINEMA</div>
    <nav class="navbar">
        <a href="home.php">Home</a>
        <a href="library.php">Library</a>
        <a href="ticket.php">Get Tickets</a>
        <a href="../staff_login.php">Staff Login</a>
    </nav>
</div>
</header>

<main class="container">
    <div class="page-heading">
        <h1><?= h($movie['title']) ?></h1>
    </div>

    <div class="card">
        <?php if ($movie['poster']): ?>
            <img src="../uploads/posters/<?= h(basename($movie['poster'])) ?>"
                 alt="<?= h($movie['title']) ?>"
                 style="width:100%;max-width:350px;border-radius:14px;">
        <?php endif; ?>

        <p><strong>Genre:</strong> <?= h($movie['genre']) ?></p>
        <p><strong>Duration:</strong> <?= (int)$movie['duration_minutes'] ?> minutes</p>
        <p><?= nl2br(h($movie['description'])) ?></p>

        <?php if ((int)$movie['streaming_rights_confirmed'] === 1): ?>
            <h2>Watch Movie</h2>
            <?php if (!empty($movie['trailer_url'])): ?>
                <p class="muted">
                    A trailer link is available, but full-length streaming
                    must be configured through an authorised video host.
                </p>
            <?php else: ?>
                <p class="muted">Full-length streaming has not been configured.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ((int)$movie['download_rights_confirmed'] === 1): ?>
            <p class="muted">
                Download access will appear once an authorised movie file
                and secure download handler have been configured.
            </p>
        <?php endif; ?>

        <a class="btn" href="library.php">Back to Library</a>
    </div>
</main>

<footer class="site-footer">EMT CINEMA</footer>
<script src="../assets/js/main.js"></script>
</body>
</html>
