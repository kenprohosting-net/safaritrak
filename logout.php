<?php

require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/helpers.php';

st_start_session();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!st_validate_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        $error = 'Your security token expired. Refresh this page and try again.';
    } else {
        st_logout();
        header('Location: login.php');
        exit;
    }
}

$csrfToken = st_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SafariTrak | Sign out</title>
  <link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <main class="planned-card">
    <div class="planned-card-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
    <h1>Sign out of SafariTrak?</h1>
    <p>Confirm to end this browser session.</p>
    <?php if ($error !== ''): ?>
      <p class="planned-note" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form class="planned-actions" method="post" action="logout.php">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn-primary" type="submit">Sign out</button>
      <a class="btn-ghost" href="index.php">Cancel</a>
    </form>
  </main>
</body>
</html>
