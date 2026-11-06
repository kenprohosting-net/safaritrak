<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$showAppMapBackground = true;
$initialDestination = isset($_GET['destination']) && is_string($_GET['destination'])
    ? trim($_GET['destination'])
    : '';
$initialDestinationLat = isset($_GET['end_lat']) && is_numeric($_GET['end_lat']) ? (float) $_GET['end_lat'] : '';
$initialDestinationLng = isset($_GET['end_lng']) && is_numeric($_GET['end_lng']) ? (float) $_GET['end_lng'] : '';

$db = safaritrak_db();
$contactsStmt = $db->prepare(
    'SELECT tc.id, COALESCE(u.full_name, tc.invite_name) AS display_name
     FROM trusted_contacts tc
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status = "confirmed"
     ORDER BY display_name ASC'
);
$contactsStmt->execute([$currentUser['id']]);
$confirmedContacts = $contactsStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Start Journey</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>PLAN A TRIP</label><h1>Start a journey</h1></div>
  <div class="head-actions">
    <div class="notif-wrap">
      <button type="button" class="notif-bell" id="notifBell"><i class="fa-regular fa-bell"></i><span class="notif-dot" id="notifDot"></span></button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
        <div class="notif-list" id="notifDropdownList">
          <p class="notif-empty">Loading...</p>
        </div>
      </div>
    </div>
    <div class="avatar"><?= st_avatar_inner($currentUser) ?></div>
  </div>
</header>

<div class="content">

<div class="page-head">
  <div><h2>Where are you headed?</h2><p>Fill in your trip details and choose who should be able to follow along.</p></div>
  <a class="btn-ghost" href="my-journeys.php"><i class="fa-solid fa-arrow-left"></i>Back to my journeys</a>
</div>

<p style="font-size:11px;color:var(--muted);margin:-10px 0 16px">Travelling with other people? <a href="groups.php?tab=trips" style="color:var(--p);font-weight:700;text-decoration:none">Create a group journey instead</a></p>

<div class="card">
  <div class="card-head"><div><label>TRIP DETAILS</label><h3>Journey information</h3></div></div>
  <form class="form-grid" id="startJourneyForm" novalidate>

    <div class="form-field">
      <label for="startPoint">Starting point</label>
      <input type="text" id="startPoint" data-location-autocomplete placeholder="e.g. Nairobi CBD" required>
      <button type="button" class="btn-ghost" id="useMyLocationBtn" style="margin-top:6px;align-self:flex-start;font-size:9px;padding:7px 10px"><i class="fa-solid fa-location-crosshairs"></i> Use my current location</button>
      <input type="hidden" id="startLat">
      <input type="hidden" id="startLng">
    </div>

    <div class="form-field">
      <label for="endPoint">Destination</label>
      <input type="text" id="endPoint" data-location-autocomplete placeholder="e.g. Meru Town" value="<?= htmlspecialchars($initialDestination, ENT_QUOTES, 'UTF-8') ?>" required>
      <input type="hidden" id="endLat" value="<?= htmlspecialchars((string) $initialDestinationLat, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="endLng" value="<?= htmlspecialchars((string) $initialDestinationLng, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="form-field">
      <label for="transportMode">Mode of transport</label>
      <select id="transportMode">
        <option value="car">Car</option>
        <option value="bus">Bus / Matatu</option>
        <option value="motorbike">Motorbike</option>
        <option value="walking">Walking</option>
      </select>
    </div>

    <div class="form-field">
      <label for="departureTime">Planned departure</label>
      <input type="datetime-local" id="departureTime">
    </div>

    <div class="form-field full">
      <label for="journeyNote">Note for your trusted contacts</label>
      <textarea id="journeyNote" rows="3" placeholder="e.g. Travelling for a family visit, will call once I arrive"></textarea>
    </div>

    <div class="form-field full">
      <label>Share this journey with</label>
      <?php if (empty($confirmedContacts)): ?>
      <p class="hint">You have no confirmed trusted contacts yet. <a href="trusted-contacts.php" style="color:var(--p);font-weight:700;text-decoration:none">Add one first</a> so someone can follow this journey.</p>
      <?php else: ?>
      <div class="share-contacts">
        <?php foreach ($confirmedContacts as $c): ?>
        <div class="share-contact-row">
          <span class="person"><?= htmlspecialchars(st_initials($c['display_name'])) ?></span>
          <span><?= htmlspecialchars($c['display_name']) ?></span>
          <label class="toggle"><input type="checkbox" class="share-checkbox" value="<?= (int) $c['id'] ?>" checked><span></span></label>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="form-field full" style="padding-top:4px">
      <div class="toggle-row" style="border-top:0;padding-top:0">
        <span><b>Alert me if I go off route</b><small>Get notified if your journey deviates significantly from the planned route</small></span>
        <label class="toggle"><input type="checkbox" id="deviationAlert" checked><span></span></label>
      </div>
    </div>

  </form>
  <div class="form-actions">
    <button type="button" class="btn-primary" id="submitJourney"><i class="fa-solid fa-route"></i>Start journey</button>
    <a class="btn-ghost" href="my-journeys.php">Cancel</a>
  </div>
</div>

</div>
<p class="route-preview-status" id="routePreviewStatus" role="status" aria-live="polite">Choose a starting point and destination from the suggestions to preview a driving route.</p>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="location-autocomplete.js?v=<?= hash_file('sha256', __DIR__ . '/location-autocomplete.js') ?>"></script>
<script src="start-journey.js?v=<?= hash_file('sha256', __DIR__ . '/start-journey.js') ?>"></script>
</body>
</html>
