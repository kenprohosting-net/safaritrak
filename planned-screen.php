<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$screens = require __DIR__ . '/backend/config/planned-screens.php';
$screenId = isset($_GET['screen']) && is_string($_GET['screen']) ? $_GET['screen'] : '';
$screen = $screens[$screenId] ?? null;

if ($screen === null) {
    http_response_code(404);
    $pageTitle = 'Screen not found';
    $pageHeading = 'This screen is not in the catalogue';
    $pageEyebrow = 'NOT FOUND';
    $activeNav = '';
    require __DIR__ . '/backend/views/app-shell-start.php';
    ?>
    <section class="card planned-card">
      <span class="status-pill status-pill-error">404 · Not found</span>
      <h2>We could not find that screen.</h2>
      <p>Use the navigation to return to a working SafariTrak feature.</p>
      <a class="btn-primary" href="index.php">Back to overview</a>
    </section>
    <?php
    require __DIR__ . '/backend/views/app-shell-end.php';
    exit;
}

$pageTitle = $screen['title'];
$pageHeading = $screen['title'];
$pageEyebrow = 'ROADMAP · ' . strtoupper($screen['phase']);
$activeNav = $screenId === 'groups' ? 'groups' : '';
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="planned-card">
  <div class="planned-card-icon"><i class="fa-solid fa-sparkles"></i></div>
  <span class="status-pill">Planned · <?= htmlspecialchars($screen['phase']) ?></span>
  <h2><?= htmlspecialchars($screen['title']) ?></h2>
  <p><?= htmlspecialchars($screen['description']) ?></p>
  <p class="planned-note">This screen is a roadmap placeholder, not a working feature. No tracking, alerts, or data collection are being performed here.</p>
  <div class="planned-actions">
    <a class="btn-primary" href="<?= htmlspecialchars($screen['available_route']) ?>"><?= htmlspecialchars($screen['available_label']) ?></a>
    <a class="btn-ghost" href="index.php">Back to overview</a>
  </div>
</section>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
