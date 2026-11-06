<?php
require_once __DIR__ . '/backend/includes/session.php';
st_start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0a0810">
  <title>SafariTrak | Your journey. Safer together.</title>
  <link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
  <link rel="stylesheet" href="auth-theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="welcome-page">
  <main class="welcome-layout">
    <a class="brand welcome-brand" href="welcome.php"><span class="logo"><i class="fa-solid fa-route"></i></span><span><b>SafariTrak</b><small>Track · Connect · Stay Safe</small></span></a>
    <section class="welcome-copy">
      <span class="welcome-kicker"><i class="fa-solid fa-satellite-dish"></i> A clearer way to travel together</span>
      <h1>Your journey.<br><span>Safer together.</span></h1>
      <p>Plan trips, share your location when you choose, and stay connected with the people who matter.</p>
      <div class="welcome-actions"><a class="btn-primary" href="signup.php">Create your account <i class="fa-solid fa-arrow-right"></i></a><a class="btn-ghost" href="login.php">Log in</a></div>
      <ul class="welcome-promises"><li><i class="fa-solid fa-location-dot"></i> Sharing is visible and revocable</li><li><i class="fa-solid fa-user-group"></i> Your trusted circle stays connected</li></ul>
    </section>
    <div class="welcome-visual" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="welcome-route"><span class="route-node route-start"></span><span class="route-node route-stop"></span><span class="route-node route-end"><i class="fa-solid fa-flag-checkered"></i></span></div><div class="welcome-map-label"><i class="fa-solid fa-location-dot"></i><span>Share your trip on your terms</span></div></div>
    <footer><span>© <?= date('Y') ?> SafariTrak</span><span>Private by choice · Connected by trust</span></footer>
  </main>
  <script src="auth-theme.js"></script>
</body>
</html>
