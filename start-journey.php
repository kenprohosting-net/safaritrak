<?php
require __DIR__ . '/backend/includes/auth-guard.php';

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

// Fetch unread messages count using receiver_id and read_at NULL check
$unreadStmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL');
$unreadStmt->execute([$currentUser['id']]);
$unreadConversationCount = (int) $unreadStmt->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Start Journey</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small></div></div>
  <nav>
    <a href="index.php"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
    <a class="active" href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
    <a href="live-tracking.php"><i class="fa-solid fa-location-crosshairs"></i>Live Tracking</a>
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
  <div><label>PLAN A TRIP</label><h1>Start a journey</h1></div>
  <div class="head-actions">
    <div class="notif-wrap">
      <button type="button" class="notif-bell" id="notifBell"><i class="fa-regular fa-bell"></i><span class="notif-dot" id="notifDot"></span></button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
        <div class="notif-list" id="notifDropdownList"><p class="notif-empty">Loading...</p></div>
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

<p style="font-size:11px;color:var(--muted);margin:-10px 0 16px">Travelling with other people? <a href="group-travel.php" style="color:var(--p);font-weight:700;text-decoration:none">Create a group journey instead</a></p>

<div id="journeyFormError" class="hint" style="display:none;margin:-4px 0 16px;padding:12px 14px;border-radius:9px;background:#fff0f0;border:1px solid #f0c0c0;color:#a92323;font-size:12px"></div>

<div class="card map-card" style="margin-bottom:18px">
  <div class="card-head"><div><label>ROUTE PREVIEW</label><h3>Your journey on the map</h3></div></div>
  <div id="map"></div>
  <div class="legend"><span><i class="current"></i>Starting point</span><span><i class="destination"></i>Destination</span></div>
</div>

<div class="card">
  <div class="card-head"><div><label>TRIP DETAILS</label><h3>Journey information</h3></div></div>
  <form class="form-grid" id="startJourneyForm" novalidate>

    <div class="form-field full">
      <label for="startPoint">Starting point</label>
      <div style="display:flex;gap:8px">
        <input type="text" id="startPoint" placeholder="e.g. Nairobi CBD" required style="flex:1">
        <button type="button" class="btn-ghost" id="findStartBtn" style="white-space:nowrap"><i class="fa-solid fa-magnifying-glass"></i> Find</button>
      </div>
      <div id="startResults" style="display:none;margin-top:6px;border:1px solid var(--line);border-radius:9px;overflow:hidden"></div>
      <p class="hint" id="startStatus" style="margin-top:6px">Search for your starting point and select a result, or use your current location.</p>
      <button type="button" class="btn-ghost" id="useMyLocationBtn" style="margin-top:8px;align-self:flex-start;font-size:9px;padding:7px 10px"><i class="fa-solid fa-location-crosshairs"></i> Use my current location</button>
      <input type="hidden" id="startLat">
      <input type="hidden" id="startLng">
    </div>

    <div class="form-field full">
      <label for="endPoint">Destination</label>
      <div style="display:flex;gap:8px">
        <input type="text" id="endPoint" placeholder="e.g. Meru Town" required style="flex:1">
        <button type="button" class="btn-ghost" id="findEndBtn" style="white-space:nowrap"><i class="fa-solid fa-magnifying-glass"></i> Find</button>
      </div>
      <div id="endResults" style="display:none;margin-top:6px;border:1px solid var(--line);border-radius:9px;overflow:hidden"></div>
      <p class="hint" id="endStatus" style="margin-top:6px">Search for your destination and select a result.</p>
      <input type="hidden" id="endLat">
      <input type="hidden" id="endLng">
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
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="start-journey.js"></script>
</body>
</html>