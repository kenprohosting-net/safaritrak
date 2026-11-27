<?php

$pageTitle = $pageTitle ?? 'Dashboard';
$pageHeading = $pageHeading ?? $pageTitle;
$pageEyebrow = $pageEyebrow ?? 'YOUR JOURNEY. SAFER TOGETHER.';
$activeNav = $activeNav ?? 'dashboard';
$loadLeaflet = $loadLeaflet ?? false;
$loadMapLibre = $loadMapLibre ?? false;
$isExploreDashboard = $isExploreDashboard ?? false;
$isRentalWorkspace = $isRentalWorkspace ?? false;
$showAppMapBackground = $showAppMapBackground ?? !$isExploreDashboard;
require_once __DIR__ . '/../config/env.php';
$googleMapsApiKey = trim(getenv('SAFARITRAK_GOOGLE_MAPS_API_KEY') ?: '');
$googleMapId = trim(getenv('SAFARITRAK_GOOGLE_MAP_ID') ?: '');
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#060b16">
  <?php if ($isExploreDashboard && $googleMapsApiKey !== ''): ?>
    <meta name="google-maps-api-key" content="<?= htmlspecialchars($googleMapsApiKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php if ($googleMapId !== ''): ?>
      <meta name="google-map-id" content="<?= htmlspecialchars($googleMapId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php endif; ?>
  <?php endif; ?>
  <title><?= htmlspecialchars($pageTitle) ?> | SafariTrak</title>
  <link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard.css') ?>">
  <?php if ($isRentalWorkspace): ?>
    <link rel="stylesheet" href="rental-workspace.css?v=<?= hash_file('sha256', __DIR__ . '/../../rental-workspace.css') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <?php if ($loadMapLibre): ?>
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.css">
    <script src="map-style.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-style.js') ?>"></script>
    <script src="route-directions.js?v=<?= hash_file('sha256', __DIR__ . '/../../route-directions.js') ?>"></script>
    <?php if ($isExploreDashboard): ?>
      <script src="google-maps-loader.js?v=<?= hash_file('sha256', __DIR__ . '/../../google-maps-loader.js') ?>"></script>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($loadLeaflet): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <?php endif; ?>
  <script>
    (function () {
      var theme = 'dark';
      try {
        theme = localStorage.getItem('safaritrak-theme') || theme;
      } catch (error) {
        theme = 'dark';
      }
      document.documentElement.dataset.theme = theme === 'light' ? 'light' : 'dark';
    }());
  </script>
</head>
<body>
<?php if (!$isExploreDashboard): ?>
  <?php require __DIR__ . '/app-map-background.php'; ?>
<?php endif; ?>
<div class="app app--map-layout<?= $isExploreDashboard ? ' app--explore' : '' ?><?= !empty($isGroupsDashboard) ? ' app--groups app--workspace' : '' ?><?= $isRentalWorkspace ? ' app--workspace app--rental-workspace' : '' ?><?= !empty($isCarHireMarketplace) ? ' app--car-hire' : '' ?><?= !empty($isFleetDashboard) ? ' app--fleet-dashboard' : '' ?>">
  <?php require __DIR__ . '/app-navigation.php'; ?>

  <main>
    <header class="app-header">
      <button class="menu" id="menu" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <?php if ($isExploreDashboard): ?>
        <a class="mobile-brand" href="index.php" aria-label="SafariTrak explore"><span class="logo"><i class="fa-solid fa-location-dot"></i></span><b>SafariTrak</b></a>
        <h1 class="visually-hidden"><?= htmlspecialchars($pageHeading) ?></h1>
      <?php else: ?>
        <div class="header-title"><label><?= htmlspecialchars($pageEyebrow) ?></label><h1><?= htmlspecialchars($pageHeading) ?></h1></div>
      <?php endif; ?>
      <div class="head-actions">
        <div class="notif-wrap">
          <button type="button" class="notif-bell" id="notifBell" aria-label="Notifications"><i class="fa-regular fa-bell"></i><span class="notif-dot" id="notifDot"></span></button>
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-dropdown-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
            <div class="notif-list" id="notifDropdownList"><p class="notif-empty">Loading...</p></div>
          </div>
        </div>
      </div>
    </header>
    <?php if ($isExploreDashboard): ?>
      <div class="app-search-row">
        <form class="dashboard-planner" id="dashboardPlanner" novalidate>
          <div class="dashboard-planner-header">
            <div class="dashboard-planner-heading">
              <span class="dashboard-planner-symbol" aria-hidden="true"><i class="fa-solid fa-route"></i></span>
              <div>
                <span class="dashboard-planner-kicker">ROUTE PLANNER</span>
                <h2>Plan your route</h2>
                <p>Preview a drive before you go.</p>
              </div>
            </div>
            <div class="dashboard-planner-header-actions">
              <span class="dashboard-planner-mode"><i class="fa-solid fa-car" aria-hidden="true"></i>Driving</span>
              <button type="button" class="dashboard-panel-toggle" id="toggleDashboardPlanner" aria-expanded="true" aria-label="Collapse route planner" title="Collapse route planner">
                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i><span class="visually-hidden">Hide route planner</span>
              </button>
            </div>
          </div>
          <div class="dashboard-planner-content" id="dashboardPlannerContent">
            <div class="dashboard-planner-fields">
              <div class="dashboard-planner-field dashboard-planner-field-start">
                <span class="dashboard-planner-waypoint dashboard-planner-waypoint-start" aria-hidden="true"><i class="fa-solid fa-location-crosshairs"></i></span>
                <div class="dashboard-planner-field-copy">
                  <label for="startPoint">From</label>
                  <input id="startPoint" type="search" data-location-autocomplete autocomplete="off" placeholder="Choose starting point" aria-label="Starting point">
                  <button type="button" class="dashboard-current-location" id="useCurrentStartLocation"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Use current location</button>
                </div>
                <input type="hidden" id="startLat">
                <input type="hidden" id="startLng">
              </div>
              <button type="button" id="swapPlannerPoints" class="dashboard-planner-swap" aria-label="Swap starting point and destination"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i></button>
              <div class="dashboard-planner-field dashboard-planner-field-destination">
                <span class="dashboard-planner-waypoint dashboard-planner-waypoint-destination" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                <div class="dashboard-planner-field-copy">
                  <label for="endPoint">To</label>
                  <input id="endPoint" type="search" data-location-autocomplete autocomplete="off" placeholder="Choose destination" aria-label="Destination">
                  <label class="visually-hidden" for="savedPlannerLocation">Choose a saved destination</label>
                  <select id="savedPlannerLocation" class="dashboard-saved-location"><option value="">Use a saved location…</option></select>
                  <small id="savedPlannerLocationStatus" role="status" aria-live="polite"></small>
                </div>
                <input type="hidden" id="endLat">
                <input type="hidden" id="endLng">
              </div>
            </div>
            <p class="dashboard-planner-hint">Type and select both locations from the suggestions to preview a route.</p>
            <p class="dashboard-planner-status" id="dashboardPlannerStatus" role="status" aria-live="polite">Your route preview will appear here.</p>
            <div class="dashboard-planner-actions">
              <button type="button" id="startTripNow" class="btn-primary" disabled><i class="fa-solid fa-play"></i>Start now</button>
              <button type="button" id="scheduleTrip" class="btn-ghost" disabled><i class="fa-regular fa-calendar"></i>Schedule trip</button>
              <button type="button" id="confirmScheduledTrip" class="btn-primary" disabled hidden><i class="fa-solid fa-calendar-check"></i>Confirm schedule</button>
              <button type="button" id="retryDashboardRoute" class="btn-ghost" hidden><i class="fa-solid fa-rotate-right"></i>Retry route</button>
            </div>
            <label class="dashboard-planner-departure" id="scheduledDepartureField" hidden>
              <span>Departure time</span>
              <input id="scheduledDeparture" type="datetime-local">
            </label>
            <div class="dashboard-planner-share" id="dashboardTripResult" hidden>
              <p id="dashboardTripResultStatus" role="status" aria-live="polite"></p>
              <button type="button" id="shareDashboardTrip" class="btn-ghost"><i class="fa-solid fa-share-nodes"></i>Share trip</button>
              <input type="url" id="dashboardTripShareUrl" aria-label="Temporary trip link" readonly hidden>
              <button type="button" id="copyDashboardTripLink" class="btn-ghost" hidden>Copy link</button>
            </div>
            <details class="dashboard-shared-journey">
              <summary><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>View shared journey</summary>
              <label for="sharedJourneyUrl">Paste a temporary journey link</label>
              <input type="url" id="sharedJourneyUrl" autocomplete="url" placeholder="https://…/shared-location.php?token=…">
              <button type="button" id="viewSharedJourney" class="btn-ghost"><i class="fa-solid fa-eye" aria-hidden="true"></i>View journey</button>
              <p id="sharedJourneyStatus" role="status" aria-live="polite"></p>
            </details>
          </div>
        </form>
      </div>
    <?php endif; ?>
    <div class="content<?= $isExploreDashboard ? ' content--explore' : '' ?>">
