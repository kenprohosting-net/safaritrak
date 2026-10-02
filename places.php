<?php
require __DIR__ . '/backend/includes/auth-guard.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Places</title>
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
    <a href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
    <a href="live-tracking.php"><i class="fa-solid fa-location-crosshairs"></i>Live Tracking</a>
    <a class="active" href="places.php"><i class="fa-solid fa-map-pin"></i>Places</a>
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
  <div><label>USEFUL STOPS</label><h1>Places nearby</h1></div>
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
  <div><h2>Find what you need along the way</h2><p>Search for hospitals, police stations, fuel, hotels and restaurants near your route.</p></div>
</div>

<div class="search" style="margin-bottom:16px">
  <i class="fa-solid fa-magnifying-glass"></i>
  <input id="placeSearch" placeholder="Search places, e.g. Total fuel station Thika Road">
  <button id="placeSearchBtn">Search</button>
</div>

<div class="tabs" id="placeTabs">
  <button type="button" class="tab active" data-category="all"><i class="fa-solid fa-layer-group"></i> All</button>
  <button type="button" class="tab" data-category="hospital"><i class="fa-solid fa-kit-medical"></i> Hospitals</button>
  <button type="button" class="tab" data-category="police"><i class="fa-solid fa-building-shield"></i> Police</button>
  <button type="button" class="tab" data-category="fuel"><i class="fa-solid fa-gas-pump"></i> Fuel</button>
  <button type="button" class="tab" data-category="hotel"><i class="fa-solid fa-bed"></i> Hotels</button>
  <button type="button" class="tab" data-category="restaurant"><i class="fa-solid fa-utensils"></i> Restaurants</button>
</div>

<div class="grid">
  <div class="card map-card">
    <div class="card-head"><div><label>MAP</label><h3>Places near your route</h3></div></div>
    <div id="placesMap"></div>
  </div>

  <div class="card">
    <div class="card-head"><div><label>RESULTS</label><h3 id="resultsCount">Finding places near you...</h3></div></div>
    <div class="journey-list" id="placesList"></div>
    <p class="hint" id="placesStatus" style="padding:0 21px 21px;color:var(--muted);font-size:11px">Getting your location...</p>
  </div>
</div>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="placeDetailModal">
  <div class="modal">
    <div class="modal-head">
      <div>
        <h3 id="placeDetailTitle">Place name</h3>
        <p id="placeDetailSubtitle">Category &middot; distance</p>
      </div>
      <button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <p id="placeDetailAddress"></p>
      <p style="margin-top:8px" id="placeDetailHours"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <a id="placeDetailDirections" class="primary" target="_blank" rel="noopener" style="text-decoration:none;text-align:center">Get directions</a>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="places.js"></script>
</body>
</html>