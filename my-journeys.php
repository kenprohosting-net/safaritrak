<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$showAppMapBackground = true;
$showAppMapPanelsToggle = false;
$showAppMapFocusControl = false;
$appMapPage = 'journeys';

$db = safaritrak_db();

$journeysStmt = $db->prepare(
    'SELECT j.*, 
            COUNT(DISTINCT js.id) AS share_count,
            GROUP_CONCAT(DISTINCT COALESCE(u.full_name, tc.invite_name) SEPARATOR ", ") AS shared_names
     FROM journeys j
     LEFT JOIN journey_shares js ON js.journey_id = j.id
     LEFT JOIN trusted_contacts tc ON tc.id = js.trusted_contact_id
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE j.user_id = ?
     GROUP BY j.id
     ORDER BY j.started_at DESC'
);
$journeysStmt->execute([$currentUser['id']]);
$journeys = $journeysStmt->fetchAll();

$groupStmt = $db->prepare(
    'SELECT gj.*, 
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_journey_id = gj.id) AS member_count
     FROM group_journeys gj
     LEFT JOIN group_members gm_user ON gm_user.group_journey_id = gj.id
     WHERE gj.organizer_id = ? OR gm_user.user_id = ?
     GROUP BY gj.id
     ORDER BY gj.departure_at DESC'
);
$groupStmt->execute([$currentUser['id'], $currentUser['id']]);
$groupJourneys = $groupStmt->fetchAll();

$journeyCarouselItems = [];
$journeyStatusLabels = ['planned' => 'Scheduled', 'active' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
foreach ($journeys as $journey) {
    $journeyCarouselItems[] = [
        'type' => 'journey',
        'id' => (int) $journey['id'],
        'status' => (string) $journey['status'],
        'start' => (string) $journey['start_label'],
        'end' => (string) $journey['end_label'],
        'startLat' => $journey['start_lat'] === null ? null : (float) $journey['start_lat'],
        'startLng' => $journey['start_lng'] === null ? null : (float) $journey['start_lng'],
        'endLat' => $journey['end_lat'] === null ? null : (float) $journey['end_lat'],
        'endLng' => $journey['end_lng'] === null ? null : (float) $journey['end_lng'],
        'date' => (string) ($journey['planned_departure_at'] ?: $journey['started_at']),
        'statusLabel' => $journeyStatusLabels[$journey['status']] ?? ucfirst((string) $journey['status']),
        'distance' => $journey['distance_km'] !== null ? number_format((float) $journey['distance_km'], 1) . ' km' : 'Not recorded',
        'shared' => (string) ($journey['shared_names'] ?: 'Not shared'),
    ];
}
foreach ($groupJourneys as $journey) {
    $journeyCarouselItems[] = [
        'type' => 'group',
        'status' => 'group',
        'groupTitle' => (string) $journey['title'],
        'groupDestination' => (string) $journey['destination_label'],
        'date' => (string) ($journey['departure_at'] ?? ''),
        'statusLabel' => ucfirst((string) $journey['status']),
        'distance' => $journey['distance_km'] !== null ? number_format((float) $journey['distance_km'], 1) . ' km' : 'Not recorded',
        'shared' => (int) $journey['member_count'] . ' members',
    ];
}

$mapFeatures = [];
$visibleMapJourneys = array_slice($journeys, 0, 20);
$positionsByJourney = [];
if ($visibleMapJourneys) {
    $journeyIds = array_map(static fn(array $journey): int => (int) $journey['id'], $visibleMapJourneys);
    $placeholders = implode(',', array_fill(0, count($journeyIds), '?'));
    $positionsStmt = $db->prepare(
        "SELECT journey_id, lat, lng FROM journey_positions
         WHERE journey_id IN ({$placeholders}) ORDER BY id DESC LIMIT 1200"
    );
    $positionsStmt->execute($journeyIds);
    foreach ($positionsStmt->fetchAll() as $position) {
        $positionsByJourney[(int) $position['journey_id']][] = [
            (float) $position['lng'],
            (float) $position['lat'],
        ];
    }
    foreach ($positionsByJourney as &$journeyPositions) {
        $journeyPositions = array_reverse($journeyPositions);
    }
    unset($journeyPositions);

    foreach ($visibleMapJourneys as $journey) {
        $id = (int) $journey['id'];
        $startLat = $journey['start_lat'] === null ? null : (float) $journey['start_lat'];
        $startLng = $journey['start_lng'] === null ? null : (float) $journey['start_lng'];
        $finishLat = $journey['end_lat'] === null ? null : (float) $journey['end_lat'];
        $finishLng = $journey['end_lng'] === null ? null : (float) $journey['end_lng'];
        $route = $positionsByJourney[$id] ?? [];
        if ($startLat !== null && $startLng !== null) {
            $mapFeatures[] = [
                'journey_id' => $id,
                'lat' => $startLat,
                'lng' => $startLng,
                'kind' => 'start',
                'label' => 'Start · ' . (string) $journey['start_label'],
                'description' => (string) $journey['start_label'] . ' — ' . (string) $journey['end_label'],
                'route' => count($route) > 1 ? $route : [],
            ];
        }
        if ($finishLat !== null && $finishLng !== null) {
            $mapFeatures[] = [
                'journey_id' => $id,
                'lat' => $finishLat,
                'lng' => $finishLng,
                'kind' => 'finish',
                'label' => 'Finish · ' . (string) $journey['end_label'],
                'description' => ucfirst((string) $journey['status']) . ' journey from ' . (string) $journey['start_label'],
            ];
        }
    }
}

?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | My Journeys</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script>
  try {
    const savedTheme = localStorage.getItem('safaritrak-theme');
    if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;
  } catch (error) {
    document.documentElement.dataset.theme = 'dark';
  }
</script>
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--journeys">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>YOUR TRIPS</label><h1>My Journeys</h1></div>
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

<section class="card journey-overview-panel" id="journeyOverviewPanel" aria-label="Trip filters and actions">
<div class="tabs journey-filter-bar" data-tab-group="journeys" role="group" aria-label="Filter journeys">
  <span class="journey-filter-label">FILTER TRIPS</span>
  <div class="journey-filter-options">
    <button type="button" class="tab active" data-tab="all" aria-pressed="true">All</button>
    <button type="button" class="tab" data-tab="planned" aria-pressed="false">Planned</button>
    <button type="button" class="tab" data-tab="active" aria-pressed="false">Active</button>
    <button type="button" class="tab" data-tab="completed" aria-pressed="false">Completed</button>
    <button type="button" class="tab" data-tab="cancelled" aria-pressed="false">Cancelled</button>
    <button type="button" class="tab" data-tab="group" aria-pressed="false">Group</button>
  </div>
</div>

<div class="journey-action-row">
  <a class="btn-ghost" href="trusted-contacts.php"><i class="fa-solid fa-user-plus"></i>Add trusted people</a>
  <a class="btn-ghost" href="groups.php?tab=trips"><i class="fa-solid fa-user-group"></i>Group trips</a>
  <a class="btn-primary" href="start-journey.php"><i class="fa-solid fa-plus"></i>Start a journey</a>
</div>
</section>

<section class="card trip-carousel-panel" id="tripCarouselPanel" role="region" aria-label="Trips carousel">
  <div class="trip-carousel-heading">
    <h2>TRIPS</h2>
    <span id="journeyCarouselPosition" aria-live="polite">0 trips</span>
  </div>
  <div class="trip-carousel">
    <button type="button" class="trip-carousel-arrow" id="previousJourney" aria-label="Previous trip" title="Previous trip"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
    <article class="trip-carousel-slide" aria-live="polite">
      <h3 id="tripCarouselTitle">Choose a trip</h3>
      <div class="trip-carousel-summary">
        <strong id="tripCarouselDistance">Not recorded</strong>
        <span class="badge" id="tripCarouselStatus">No trip selected</span>
      </div>
      <p>Departure: <time id="tripCarouselDate">—</time></p>
      <p>Shared with: <span id="tripCarouselShared">—</span></p>
      <p id="tripCarouselRouteStatus" class="journey-map-route-status" role="status" aria-live="polite">Select a trip to load its road route.</p>
      <div class="trip-carousel-actions">
        <button type="button" class="btn-primary" id="shareSelectedJourney" disabled><i class="fa-solid fa-share-nodes"></i>Share trip</button>
        <button type="button" class="btn-primary journey-start-button" id="startSelectedJourney" hidden><i class="fa-solid fa-play" aria-hidden="true"></i>Start now</button>
        <a class="btn-ghost" id="endSelectedJourney" href="end-journey.php" hidden><i class="fa-solid fa-stop" aria-hidden="true"></i>End trip</a>
        <a class="btn-ghost" id="openCarouselGroup" href="groups.php?tab=trips" hidden>Open group trips</a>
        <input type="url" id="selectedJourneyShareUrl" aria-label="Temporary trip sharing link" readonly hidden>
        <button type="button" class="btn-ghost" id="copySelectedJourneyLink" hidden>Copy link</button>
        <p id="selectedJourneyShareStatus" role="status" aria-live="polite"></p>
      </div>
    </article>
    <button type="button" class="trip-carousel-arrow" id="nextJourney" aria-label="Next trip" title="Next trip"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
  </div>
  <p class="trip-carousel-empty" id="emptyState" hidden>You have no trips in this category yet.</p>
</section>

<button type="button" class="journey-panels-toggle" id="journeyPanelsToggle" aria-controls="journeyOverviewPanel tripCarouselPanel" aria-pressed="false" aria-label="Focus on selected trip route" hidden>
  <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
  <span id="journeyPanelsToggleLabel">Focus route</span>
</button>

</div>

<script type="application/json" id="journeyCarouselData"><?= json_encode($journeyCarouselItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="journeys.js?v=<?= hash_file('sha256', __DIR__ . '/journeys.js') ?>"></script>
</body>
</html>