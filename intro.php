<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome | EMT Cinema</title>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
body {
    min-height: 100vh;
    background: linear-gradient(135deg,#35104f,#7136a8,#e84d9b);
    color: white;
    display: grid;
    place-items: center;
    text-align: center;
    overflow: hidden;
}
.intro {
    padding: 25px;
    animation: appear 1.5s ease;
}
.intro h1 {
    font-size: clamp(38px,8vw,75px);
    letter-spacing: 3px;
    margin: 0;
}
.intro p {
    letter-spacing: 3px;
}
.line {
    width: 0;
    height: 4px;
    background: white;
    margin: 30px auto;
    animation: loading 10s linear forwards;
}
@keyframes loading {
    to { width: 100%; }
}
@keyframes appear {
    from { opacity: 0; transform: translateY(25px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>

<body>
<div class="intro">
    <h1>EMT CINEMA</h1>
    <p>COME FOR THE MOVIE, STAY FOR THE FEELING</p>
    <div class="line"></div>
    <p>YOUR MOVIE EXPERIENCE STARTS HERE</p>
</div>

<script>
setTimeout(function () {
    window.location.href = 'home.php';
}, 10000);
</script>
</body>
</html>

