<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$activeStmt = $db->prepare('SELECT * FROM journeys WHERE user_id = ? AND status = "active" LIMIT 1');
$activeStmt->execute([$currentUser['id']]);
$activeJourney = $activeStmt->fetch();

$recentStmt = $db->prepare('SELECT * FROM journeys WHERE user_id = ? AND status != "active" ORDER BY started_at DESC LIMIT 3');
$recentStmt->execute([$currentUser['id']]);
$recentJourneys = $recentStmt->fetchAll();

$contactsPreviewStmt = $db->prepare(
    'SELECT tc.id, COALESCE(u.full_name, tc.invite_name) AS display_name, tc.status
     FROM trusted_contacts tc
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status = "confirmed"
     ORDER BY tc.created_at DESC LIMIT 3'
);
$contactsPreviewStmt->execute([$currentUser['id']]);
$contactsPreview = $contactsPreviewStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Dashboard</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small></div></div>
  <nav>
    <a class="active" href="index.php"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
    <a href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
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
  <div><label>YOUR TRAVEL COMMAND CENTER</label><h1><?= st_greeting() ?>, <?= htmlspecialchars($userName) ?></h1></div>
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

    <!-- Top-Right User Profile Dropdown Component -->
    <div class="profile-dropdown-wrap" id="profileDropdownWrap">
      <button type="button" class="avatar-btn" id="profileAvatarBtn" aria-expanded="false" title="Account Menu">
        <?= st_avatar_inner($currentUser) ?>
      </button>

      <div class="profile-dropdown-menu" id="profileDropdownMenu">
        <div class="profile-dropdown-header">
          <b><?= htmlspecialchars($userName) ?></b>
          <small>Traveler</small>
        </div>
        <hr class="profile-dropdown-divider">
        <ul class="profile-dropdown-list">
          <li>
            <a href="settings.php" class="profile-dropdown-item">
              <i class="fa-solid fa-camera"></i>
              <span>Update Profile Picture</span>
            </a>
          </li>
          <li>
            <a href="logout.php" class="profile-dropdown-item logout-link">
              <i class="fa-solid fa-arrow-right-from-bracket"></i>
              <span>Logout</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</header>

<div class="content">
<section class="hero">
  <div>
    <label>READY FOR YOUR NEXT JOURNEY?</label>
    <h2>Where are you going today?</h2>
    <p>Plan your route, track your journey and keep the people you trust connected along the way.</p>
    <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input id="destination" placeholder="Search a destination..."><button type="button" id="locate">Use my location</button></div>
    <div class="shortcuts"><button type="button" data-shortcut="home">Home</button><button type="button" data-shortcut="work">Work</button><a class="shortcut-link" href="places.php">Nearby places</a></div>
  </div>
  <div class="hero-note"><i class="fa-solid fa-compass"></i><b>Travel with confidence.</b><span>Navigate. Track. Share. Connect. Stay safe.</span></div>
</section>

<section class="grid">
  <div class="card map-card">
    <div class="card-head"><div><label>LIVE MAP</label><h3>Explore your journey</h3></div><button type="button" id="myLocation">My location</button></div>
    <div id="map"></div>
    <div class="legend"><span><i class="current"></i>Your location</span><span><i class="destination"></i>Destination</span></div>
  </div>

  <div class="card actions">
    <div class="card-head"><div><label>QUICK ACTIONS</label><h3>What do you need?</h3></div></div>
    <a class="action primary" href="start-journey.php"><i class="fa-solid fa-route"></i><span><b>Start Journey</b><small>Plan and begin a trip</small></span><strong>›</strong></a>
    <a class="action" href="start-journey.php"><i class="fa-solid fa-location-arrow"></i><span><b>Share Location</b><small>Let someone know where you are</small></span><strong>›</strong></a>
    <a class="action" href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i><span><b>Trusted Contacts</b><small>Manage people you trust</small></span><strong>›</strong></a>
    <a class="action sos" href="safety.php"><i class="fa-solid fa-triangle-exclamation"></i><span><b>Emergency / SOS</b><small>Get help when you need it</small></span><strong>›</strong></a>
  </div>
</section>

<section class="card journey">
  <div class="card-head"><div><label>JOURNEY STATUS</label><h3>Active journey</h3></div><span class="status"><?= $activeJourney ? '● Travelling' : '● Not travelling' ?></span></div>
  <?php if ($activeJourney): ?>
  <div class="empty" style="border-style:solid;border-color:var(--line)">
    <i class="fa-solid fa-route"></i>
    <div><b><?= htmlspecialchars($activeJourney['start_label']) ?> &rarr; <?= htmlspecialchars($activeJourney['end_label']) ?></b><p>Started <?= (new DateTime($activeJourney['started_at']))->format('g:i A') ?><?= $activeJourney['distance_km'] !== null ? ' &middot; ' . number_format((float) $activeJourney['distance_km'], 1) . ' km' : '' ?></p></div>
    <a class="empty-link" href="live-tracking.php">View live map</a>
  </div>
  <?php else: ?>
  <div class="empty"><i class="fa-solid fa-road"></i><div><b>No active journey</b><p>Start a journey to see live tracking, ETA and safety information here.</p></div><a class="empty-link" href="start-journey.php">Start a journey</a></div>
  <?php endif; ?>
</section>

<section class="lower">
  <div class="card">
    <div class="card-head"><div><label>HISTORY</label><h3>Recent journeys</h3></div><a href="my-journeys.php">View all</a></div>
    <div class="rows">
      <?php if (empty($recentJourneys)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No journeys yet. Once you travel, they will show up here.</p>
      <?php endif; ?>
      <?php foreach ($recentJourneys as $rj): ?>
      <a href="my-journeys.php"><i class="fa-solid <?= $rj['status'] === 'cancelled' ? 'fa-xmark' : 'fa-check' ?>"></i><div><b><?= htmlspecialchars($rj['start_label']) ?> &rarr; <?= htmlspecialchars($rj['end_label']) ?></b><small><?= ucfirst($rj['status']) ?> &middot; <?= (new DateTime($rj['started_at']))->format('j M') ?></small></div><strong><?= $rj['distance_km'] !== null ? number_format((float) $rj['distance_km'], 1) . ' km' : '-' ?></strong></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><div><label>PEOPLE</label><h3>Trusted contacts</h3></div><a href="trusted-contacts.php">Manage</a></div>
    <div class="rows contacts">
      <?php if (empty($contactsPreview)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No trusted contacts yet. <a href="trusted-contacts.php" style="color:var(--p);font-weight:700;text-decoration:none">Add one</a> so someone can watch your journeys.</p>
      <?php endif; ?>
      <?php foreach ($contactsPreview as $cp): ?>
      <div><span class="person"><?= htmlspecialchars(st_initials($cp['display_name'])) ?></span><div><b><?= htmlspecialchars($cp['display_name']) ?></b><small>&#9679; Confirmed</small></div><a class="msg-link" href="messages.php"><i class="fa-regular fa-message"></i></a></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="safety"><i class="fa-solid fa-shield-heart"></i><div><label>SAFETRAVEL</label><h3>Your safety stays with you.</h3><p>Add trusted contacts and enable location sharing when you want someone to monitor your journey.</p></div><a class="safety-link" href="safety.php">Safety settings</a></section>
</div>
<footer>© <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="dashboard-map.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // --------------------------------------------------------------
    // "Use my location" (#locate) and "My location" (#myLocation)
    // Previously these buttons had no click handler anywhere in the
    // codebase, so clicking them did nothing. dashboard-map.js
    // creates window.safariMap and exposes window.setCurrentMarker /
    // window.fitJourney, so this waits for that to exist and wires
    // both buttons to it.
    // --------------------------------------------------------------

    function whenMapReady(callback) {
        if (window.safariMap && typeof window.setCurrentMarker === 'function') {
            callback();
            return;
        }
        const check = setInterval(function () {
            if (window.safariMap && typeof window.setCurrentMarker === 'function') {
                clearInterval(check);
                callback();
            }
        }, 100);
        setTimeout(function () { clearInterval(check); }, 8000);
    }

    function locateAndCenter(button, opts) {
        opts = opts || {};

        if (!button) return;

        if (!navigator.geolocation) {
            const original = button.textContent;
            button.textContent = 'Not supported';
            setTimeout(function () { button.textContent = original; }, 2500);
            return;
        }

        whenMapReady(function () {
            const original = button.textContent;
            button.disabled = true;
            button.textContent = 'Locating…';

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    window.setCurrentMarker(lat, lng, pos.coords.accuracy);
                    window.safariMap.setView([lat, lng], 15);

                    if (opts.fillDestinationInput) {
                        const destInput = document.getElementById('destination');
                        if (destInput) {
                            destInput.value = 'My location (' + lat.toFixed(4) + ', ' + lng.toFixed(4) + ')';
                            destInput.dataset.lat = lat;
                            destInput.dataset.lng = lng;
                        }
                    }

                    button.disabled = false;
                    button.textContent = 'Located';
                    setTimeout(function () { button.textContent = original; }, 1800);
                },
                function (err) {
                    button.disabled = false;
                    button.textContent = err.code === err.PERMISSION_DENIED
                        ? 'Permission denied'
                        : 'Could not locate';
                    setTimeout(function () { button.textContent = original; }, 2500);
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
            );
        });
    }

    document.getElementById('locate')?.addEventListener('click', function () {
        locateAndCenter(this, { fillDestinationInput: true });
    });

    document.getElementById('myLocation')?.addEventListener('click', function () {
        locateAndCenter(this);
    });

    // --------------------------------------------------------------
    // Home / Work shortcuts. There is no saved-places table/endpoint
    // in this codebase, so this is a client-side stub using
    // localStorage: first click saves your current position under
    // that label, later clicks jump straight there. Replace with a
    // real API call once a saved_places backend exists.
    // --------------------------------------------------------------

    document.querySelectorAll('[data-shortcut]').forEach(function (btn) {
        const key = 'safaritrak_shortcut_' + btn.dataset.shortcut;

        btn.addEventListener('click', function () {
            const saved = localStorage.getItem(key);

            whenMapReady(function () {
                if (saved) {
                    const coords = JSON.parse(saved);
                    window.setCurrentMarker(coords.lat, coords.lng, 0);
                    window.safariMap.setView([coords.lat, coords.lng], 15);
                    return;
                }

                if (!navigator.geolocation) return;

                const original = btn.textContent;
                btn.textContent = 'Saving…';

                navigator.geolocation.getCurrentPosition(function (pos) {
                    localStorage.setItem(key, JSON.stringify({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude
                    }));
                    window.setCurrentMarker(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                    window.safariMap.setView([pos.coords.latitude, pos.coords.longitude], 15);
                    btn.textContent = original;
                }, function () {
                    btn.textContent = original;
                });
            });
        });
    });

});
</script>
</body>
</html>