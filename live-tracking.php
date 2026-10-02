<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

// 1. Check if user is trying to view a specific shared journey via GET parameter
$viewJourneyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$viewJourney = null;

if ($viewJourneyId > 0) {
    $viewStmt = $db->prepare(
        'SELECT j.*, u.full_name AS traveler_name
         FROM journeys j
         JOIN journey_shares js ON js.journey_id = j.id
         JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
         JOIN users u ON u.id = j.user_id
         WHERE j.id = ? AND tc.contact_user_id = ? AND tc.status = "confirmed" AND j.status = "active"'
    );
    $viewStmt->execute([$viewJourneyId, $currentUser['id']]);
    $viewJourney = $viewStmt->fetch();
}

// 2. Fetch the user's own active journey
$activeStmt = $db->prepare('SELECT * FROM journeys WHERE user_id = ? AND status = "active" LIMIT 1');
$activeStmt->execute([$currentUser['id']]);
$activeJourney = $activeStmt->fetch();

// Determine target journey (viewing shared takes precedence if ?id= is set, otherwise fall back to own)
$targetJourney = $viewJourney ?: $activeJourney;
$isWatcher = $viewJourney ? true : false;

$watchers = [];
if ($activeJourney) {
    $watchersStmt = $db->prepare(
        'SELECT COALESCE(u.full_name, tc.invite_name) AS display_name, tc.contact_user_id, tc.id AS trusted_contact_id
         FROM journey_shares js
         JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
         LEFT JOIN users u ON u.id = tc.contact_user_id
         WHERE js.journey_id = ?'
    );
    $watchersStmt->execute([$activeJourney['id']]);
    $watchers = $watchersStmt->fetchAll();
}

$watchedStmt = $db->prepare(
    'SELECT j.id, j.start_label, j.end_label, j.started_at, u.full_name AS traveler_name
     FROM journeys j
     JOIN journey_shares js ON js.journey_id = j.id
     JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
     JOIN users u ON u.id = j.user_id
     WHERE tc.contact_user_id = ? AND tc.status = "confirmed" AND j.status = "active"
     ORDER BY j.started_at DESC'
);
$watchedStmt->execute([$currentUser['id']]);
$watchedJourneys = $watchedStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Live Tracking</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.st-locator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #176b5b;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
    position: relative;
}
.st-locator::after {
    content: '';
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    background: rgba(23,107,91,.25);
    animation: st-locator-pulse 2s ease-out infinite;
}
@keyframes st-locator-pulse {
    0% { transform: scale(0.4); opacity: 1; }
    100% { transform: scale(1.6); opacity: 0; }
}

.st-pin {
    width: 26px;
    height: 34px;
    position: relative;
    transform: translateY(-4px);
}
.st-pin::before {
    content: '';
    position: absolute;
    top: 0;
    left: 3px;
    width: 20px;
    height: 20px;
    border-radius: 50% 50% 50% 0;
    background: #d69b2d;
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,.35);
    transform: rotate(-45deg);
}
.st-pin::after {
    content: '';
    position: absolute;
    top: 6px;
    left: 9px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small></div></div>
  <nav>
    <a href="index.php"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
    <a href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
    <a class="active" href="live-tracking.php"><i class="fa-solid fa-location-crosshairs"></i>Live Tracking</a>
    <a href="places.php"><i class="fa-solid fa-map-pin"></i>Places</a>
    <a href="messages.php"><i class="fa-regular fa-message"></i>Messages<?= $unreadConversationCount > 0 ? " <em>" . $unreadConversationCount . "</em>" : "" ?></a>
    <a href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i>Trusted Contacts</a>
    <a href="safety.php"><i class="fa-solid fa-shield-halved"></i>Safety</a>
  </nav>
  <div class="bottom">
    <a href="settings.php"><i class="fa-solid fa-gear"></i>Settings</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account"><span><?= st_avatar_inner($currentUser) ?></span><div><b><?= htmlspecialchars($userName) ?></b><small>Traveler</small></div></div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>ON THE ROAD</label><h1>Live Tracking</h1></div>
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

<?php if (!$targetJourney): ?>

  <?php if (!empty($watchedJourneys)): ?>
  <div class="page-head">
    <div><h2>Journeys shared with you</h2><p>You are not travelling right now, but you can watch these live.</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn-ghost" href="group-travel.php"><i class="fa-solid fa-user-group"></i>Group travel</a>
      <a class="btn-primary" href="start-journey.php"><i class="fa-solid fa-plus"></i>Start a journey</a>
    </div>
  </div>

  <div class="card">
    <div class="journey-list">
      <?php foreach ($watchedJourneys as $wj): ?>
      <a href="live-tracking.php?id=<?= (int) $wj['id'] ?>" class="journey-row" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--border,#eee);">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="jicon"><i class="fa-solid fa-location-crosshairs"></i></div>
          <div class="jinfo">
            <b><?= htmlspecialchars($wj['traveler_name']) ?></b>
            <small style="display:block;color:var(--muted,#666);margin-top:2px"><?= htmlspecialchars($wj['start_label']) ?> &rarr; <?= htmlspecialchars($wj['end_label']) ?> &middot; Started <?= (new DateTime($wj['started_at']))->format('g:i A') ?></small>
          </div>
        </div>
        <div class="jmeta"><span class="badge active" style="background:#10b981;color:#fff;padding:4px 8px;border-radius:12px;font-size:11px;font-weight:bold;">Live</span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="empty" style="margin:21px;flex-wrap:wrap">
      <i class="fa-solid fa-location-crosshairs"></i>
      <div><b>No journey in progress</b><p>Start a journey and your live position, distance covered and safety tools will show up here.</p></div>
      <div style="display:flex;gap:10px;margin-left:auto">
        <a class="btn-ghost" href="group-travel.php">Group travel</a>
        <a class="empty-link" href="start-journey.php">Start a journey</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>

  <div class="page-head">
    <div>
      <h2><?= htmlspecialchars($targetJourney['start_label']) ?> &rarr; <?= htmlspecialchars($targetJourney['end_label']) ?></h2>
      <p><?= $isWatcher ? 'Tracking ' . htmlspecialchars($targetJourney['traveler_name']) : 'Journey in progress' ?>, started at <?= (new DateTime($targetJourney['started_at']))->format('g:i A') ?>.</p>
    </div>
    <?php if (!$isWatcher): ?>
      <button type="button" class="btn-ghost" data-open-modal="endJourneyModal"><i class="fa-solid fa-circle-stop"></i>End journey</button>
    <?php endif; ?>
  </div>

  <div class="card map-full">
    <div class="card-head">
      <div><label>LIVE MAP</label><h3><?= $isWatcher ? htmlspecialchars($targetJourney['traveler_name']) . "'s current position" : 'Your current position' ?></h3></div>
      <div style="display:flex;align-items:center;gap:14px">
        <div class="connection-status"><span class="connection-dot" id="connectionDot"></span><span id="trackingStatus">Connecting...</span></div>
        <button id="myLocationBtn">Recenter map</button>
      </div>
    </div>
    <div id="map" style="height: 450px; width: 100%;"></div>
    <div class="legend">
      <span><i class="current"></i><?= $isWatcher ? htmlspecialchars($targetJourney['traveler_name']) : 'Your location' ?></span>
      <?php if ($targetJourney['end_lat']): ?><span><i class="destination"></i>Destination</span><?php endif; ?>
    </div>
    <div class="eta-strip">
      <div class="eta-chip"><label>DISTANCE COVERED</label><strong id="coveredKm">0 km</strong></div>
      <div class="eta-chip"><label>DISTANCE REMAINING</label><strong id="remainingKm">-</strong></div>
      <div class="eta-chip"><label>TOTAL DISTANCE</label><strong id="totalKm"><?= $targetJourney['distance_km'] !== null ? number_format((float) $targetJourney['distance_km'], 1) . ' km' : 'Unknown' ?></strong></div>
      <div class="eta-chip"><label>STARTED</label><strong><?= (new DateTime($targetJourney['started_at']))->format('g:i A') ?></strong></div>
      <div class="eta-chip"><label>CURRENT SPEED</label><strong id="currentSpeed">-</strong></div>
      <div class="eta-chip"><label>LOCATION ACCURACY</label><strong id="locationAccuracy">-</strong></div>
    </div>
  </div>

  <?php if (!$isWatcher): ?>
  <section class="lower">
    <div class="card">
      <div class="card-head"><div><label>WATCHING THIS JOURNEY</label><h3>People tracking you</h3></div><a href="trusted-contacts.php">Manage</a></div>
      <div class="rows contacts">
        <?php if (empty($watchers)): ?>
        <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">You did not share this journey with anyone.</p>
        <?php endif; ?>
        <?php foreach ($watchers as $w): ?>
        <div><span class="person"><?= htmlspecialchars(st_initials($w['display_name'])) ?></span><div><b><?= htmlspecialchars($w['display_name']) ?></b><small>&#9679; <?= $w['contact_user_id'] ? 'Watching now' : 'Invited, not on SafariTrak yet' ?></small></div>
          <?php if ($w['contact_user_id']): ?><a class="msg-link" href="messages.php?to=<?= (int) $w['contact_user_id'] ?>"><i class="fa-regular fa-message"></i></a><?php endif; ?>
          <button type="button" class="btn-ghost stop-sharing-btn" data-contact-id="<?= (int) $w['trusted_contact_id'] ?>" style="color:#c94b4b;padding:6px 9px;font-size:9px">Stop</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><div><label>SAFETY</label><h3>While you travel</h3></div></div>
      <div class="tip-list">
        <div class="tip-row"><i class="fa-solid fa-route"></i><div><b>Route deviation alerts are <?= $targetJourney['route_deviation_alert'] ? 'on' : 'off' ?></b><p>You will be notified if you move significantly off the planned route.</p></div></div>
        <div class="tip-row"><i class="fa-solid fa-triangle-exclamation"></i><div><b>SOS is one tap away</b><p>Use the emergency button on the Safety page if you need urgent help.</p></div></div>
        <div class="tip-row"><i class="fa-solid fa-gas-pump"></i><div><b>Need a stop along the way?</b><p><a href="places.php" style="color:var(--p);font-weight:700;text-decoration:none">Find nearby hospitals, fuel stations, hotels and more</a></p></div></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($watchedJourneys)): ?>
  <div class="card" style="margin-top:18px">
    <div class="card-head"><div><label>ALSO WATCHING</label><h3>Journeys shared with you</h3></div></div>
    <div class="journey-list">
      <?php foreach ($watchedJourneys as $wj): ?>
      <a href="live-tracking.php?id=<?= (int) $wj['id'] ?>" class="journey-row" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--border,#eee);">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="jicon"><i class="fa-solid fa-location-crosshairs"></i></div>
          <div class="jinfo"><b><?= htmlspecialchars($wj['traveler_name']) ?></b><small style="display:block;color:var(--muted,#666);margin-top:2px"><?= htmlspecialchars($wj['start_label']) ?> &rarr; <?= htmlspecialchars($wj['end_label']) ?></small></div>
        </div>
        <div class="jmeta"><span class="badge active" style="background:#10b981;color:#fff;padding:4px 8px;border-radius:12px;font-size:11px;font-weight:bold;">Live</span></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<?php if ($activeJourney && !$isWatcher): ?>
<div class="modal-overlay" id="endJourneyModal" style="z-index:5000">
  <div class="modal">
    <div class="modal-head"><div><h3>End this journey?</h3><p>Your trusted contacts will be notified that you have arrived.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <p>Make sure you have safely reached your destination before ending tracking.</p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Keep travelling</button>
      <button type="button" class="danger" id="confirmEndJourneyBtn">End journey</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($targetJourney): ?>
<script>
window.journeyId = <?= (int) $targetJourney['id'] ?>;
window.isWatcher = <?= $isWatcher ? 'true' : 'false' ?>;

<?php if ($targetJourney['start_lat'] !== null && $targetJourney['start_lng'] !== null): ?>
window.startCoordinates = {
    lat: <?= (float) $targetJourney['start_lat'] ?>,
    lng: <?= (float) $targetJourney['start_lng'] ?>
};
<?php endif; ?>

<?php if ($targetJourney['end_lat'] !== null && $targetJourney['end_lng'] !== null): ?>
window.destinationCoordinates = {
    lat: <?= (float) $targetJourney['end_lat'] ?>,
    lng: <?= (float) $targetJourney['end_lng'] ?>
};
<?php endif; ?>

document.getElementById('confirmEndJourneyBtn')?.addEventListener('click', function () {
    document.getElementById('endJourneyModal').classList.remove('open');
    if (window.safariTrakTracking && typeof window.safariTrakTracking.endJourneyDirect === 'function') {
        window.safariTrakTracking.endJourneyDirect();
    }
});
</script>
<?php endif; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<?php if ($targetJourney): ?>
<script src="tracking.js"></script>
<?php endif; ?>
</body>
</html>