<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$myAlertStmt = $db->prepare('SELECT id, created_at FROM sos_alerts WHERE user_id = ? AND status = "active" LIMIT 1');
$myAlertStmt->execute([$currentUser['id']]);
$myActiveAlert = $myAlertStmt->fetch();

$watchingAlertsStmt = $db->prepare(
    'SELECT sa.id, sa.created_at, u.full_name AS owner_name
     FROM sos_alerts sa
     JOIN trusted_contacts tc ON tc.owner_id = sa.user_id AND tc.contact_user_id = ? AND tc.status = "confirmed" AND tc.sos_alerts = 1
     JOIN users u ON u.id = sa.user_id
     WHERE sa.status = "active"
     ORDER BY sa.created_at DESC'
);
$watchingAlertsStmt->execute([$currentUser['id']]);
$watchingAlerts = $watchingAlertsStmt->fetchAll();

$emergencyContactsStmt = $db->prepare(
    'SELECT tc.id, COALESCE(u.full_name, tc.invite_name) AS display_name, tc.relationship
     FROM trusted_contacts tc
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status = "confirmed" AND tc.sos_alerts = 1
     ORDER BY tc.created_at ASC'
);
$emergencyContactsStmt->execute([$currentUser['id']]);
$emergencyContacts = $emergencyContactsStmt->fetchAll();

$settingsStmt = $db->prepare('SELECT route_deviation_alerts, arrival_notifications, auto_sos_on_silence FROM users WHERE id = ?');
$settingsStmt->execute([$currentUser['id']]);
$safetySettings = $settingsStmt->fetch();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Safety</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small></div></div>
  <nav>
    <a href="index.php"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
    <a href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
    <a href="live-tracking.php"><i class="fa-solid fa-location-crosshairs"></i>Live Tracking</a>
    <a href="places.php"><i class="fa-solid fa-map-pin"></i>Places</a>
    <a href="messages.php"><i class="fa-regular fa-message"></i>Messages<?= $unreadConversationCount > 0 ? " <em>" . $unreadConversationCount . "</em>" : "" ?></a>
    <a href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i>Trusted Contacts</a>
    <a class="active" href="safety.php"><i class="fa-solid fa-shield-halved"></i>Safety</a>
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
  <div><label>YOUR SAFETY</label><h1>Safety</h1></div>
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

<?php if (!empty($watchingAlerts)): ?>
<div class="card" style="margin-bottom:18px;border:1px solid #f3c8c8">
  <div class="card-head"><div><label style="color:#c94b4b">URGENT</label><h3>Someone needs help</h3></div></div>
  <div class="journey-list">
    <?php foreach ($watchingAlerts as $wa): ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon" style="background:#fdecec;color:#c94b4b"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($wa['owner_name']) ?> sent an SOS alert</b><small><?= (new DateTime($wa['created_at']))->format('j M, g:i A') ?></small></div>
      <div class="jmeta"><a href="messages.php" class="btn-primary">Message them</a></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($myActiveAlert): ?>
<div class="sos-panel">
  <button type="button" class="sos-btn" id="resolveSosBtn"><i class="fa-solid fa-check"></i></button>
  <div><h3>Your SOS alert is active</h3><p>Sent <?= (new DateTime($myActiveAlert['created_at']))->format('g:i A') ?>. Your trusted contacts have been notified. Tap the button once you are safe to let them know.</p></div>
</div>
<script>window.MY_ACTIVE_ALERT_ID = <?= (int) $myActiveAlert['id'] ?>;</script>
<?php else: ?>
<div class="sos-panel">
  <button type="button" class="sos-btn" data-open-modal="sosModal"><i class="fa-solid fa-triangle-exclamation"></i></button>
  <div><h3>Need help right now?</h3><p>Press the SOS button to alert your trusted contacts with your current location. Only use this in a real emergency.</p></div>
</div>
<?php endif; ?>

<section class="lower">

  <div class="card">
    <div class="card-head"><div><label>EMERGENCY CONTACTS</label><h3>Who gets notified</h3></div><a href="trusted-contacts.php">Manage</a></div>
    <div class="rows contacts">
      <?php if (empty($emergencyContacts)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No one is set up to receive SOS alerts yet. Turn on the SOS toggle for a trusted contact to add them here.</p>
      <?php endif; ?>
      <?php foreach ($emergencyContacts as $ec): ?>
      <div><span class="person"><?= htmlspecialchars(st_initials($ec['display_name'])) ?></span><div><b><?= htmlspecialchars($ec['display_name']) ?></b><small><?= htmlspecialchars($ec['relationship'] ?: 'Emergency contact') ?></small></div><a class="msg-link" href="messages.php"><i class="fa-regular fa-message"></i></a></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><label>SETTINGS</label><h3>Safety preferences</h3></div></div>
    <div style="padding:0 21px 8px">
      <div class="toggle-row"><span><b>Route deviation alerts</b><small>Notify me if I move significantly off my planned route</small></span><label class="toggle"><input type="checkbox" class="safety-toggle" data-field="route_deviation_alerts" <?= $safetySettings['route_deviation_alerts'] ? 'checked' : '' ?>><span></span></label></div>
      <div class="toggle-row"><span><b>Arrival notifications</b><small>Let my trusted contacts know when I reach my destination</small></span><label class="toggle"><input type="checkbox" class="safety-toggle" data-field="arrival_notifications" <?= $safetySettings['arrival_notifications'] ? 'checked' : '' ?>><span></span></label></div>
      <div class="toggle-row"><span><b>Automatic SOS on long silence</b><small>Send an alert if I have not moved or responded for a long time</small></span><label class="toggle"><input type="checkbox" class="safety-toggle" data-field="auto_sos_on_silence" <?= $safetySettings['auto_sos_on_silence'] ? 'checked' : '' ?>><span></span></label></div>
    </div>
  </div>

</section>

<div class="card" style="margin-top:18px">
  <div class="card-head"><div><label>STAYING SAFE</label><h3>Travel safety tips</h3></div></div>
  <div class="tip-list">
    <div class="tip-row"><i class="fa-solid fa-route"></i><div><b>Always share your journey</b><p>Let a trusted contact know your route before you start travelling, especially at night.</p></div></div>
    <div class="tip-row"><i class="fa-solid fa-gas-pump"></i><div><b>Plan your stops</b><p>Use the places search to find fuel stations, hospitals and hotels along your route.</p></div></div>
    <div class="tip-row"><i class="fa-solid fa-battery-three-quarters"></i><div><b>Keep your phone charged</b><p>Live tracking and the SOS button need battery. Carry a power bank on long trips.</p></div></div>
  </div>
</div>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="sosModal">
  <div class="modal sos">
    <div class="modal-head">
      <div class="sos-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <b>Send an SOS alert?</b>
      <p>This will notify all your trusted contacts with your current location and let them know you need help. Only use this if you are in real danger or need urgent assistance.</p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="danger" id="confirmSosBtn">Send SOS</button>
    </div>
  </div>
</div>

<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="safety.js"></script>
</body>
</html>
