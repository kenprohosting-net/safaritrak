<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$showAppMapBackground = true;

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

function journey_duration(string $start, ?string $end): string {
    if (!$end) {
        return '';
    }
    $diff = (new DateTime($start))->diff(new DateTime($end));
    $parts = [];
    if ($diff->h > 0) $parts[] = $diff->h . 'h';
    $parts[] = $diff->i . 'm';
    return implode(' ', $parts);
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

<div class="page-head">
  <div><h2>Everywhere you have travelled</h2><p>See your active trip, look back at past journeys, or plan a new one.</p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn-ghost" href="groups.php?tab=trips"><i class="fa-solid fa-user-group"></i>Group trips</a>
    <a class="btn-primary" href="start-journey.php"><i class="fa-solid fa-plus"></i>Start a journey</a>
  </div>
</div>

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

<div class="card journey-picker-panel" id="journeyPickerPanel" role="region" aria-label="Choose a trip" hidden>
  <div class="journey-list" id="journeyList">

    <?php if (empty($journeys) && empty($groupJourneys)): ?>
    <p class="hint" style="padding:20px 21px;color:var(--muted);font-size:11px">You have not started any journeys yet. When you do, they will show up here.</p>
    <?php endif; ?>

    <?php foreach ($journeys as $j): ?>
    <?php
      $statusBadge = ['planned' => 'planned', 'active' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled'][$j['status']];
      $statusLabel = ['planned' => 'Scheduled', 'active' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$j['status']];
      $icon = $j['status'] === 'cancelled' ? 'fa-xmark' : ($j['status'] === 'completed' ? 'fa-check' : 'fa-route');
      $subLine = $j['status'] === 'planned'
        ? 'Leaves ' . (new DateTime($j['planned_departure_at']))->format('j M, g:i A') . ($j['share_count'] > 0 ? ' &middot; Shared with ' . $j['share_count'] . ' ' . ($j['share_count'] == 1 ? 'contact' : 'contacts') : '')
        : ($j['status'] === 'active'
          ? 'Started ' . (new DateTime($j['started_at']))->format('g:i A') . ($j['share_count'] > 0 ? ' &middot; Shared with ' . $j['share_count'] . ' ' . ($j['share_count'] == 1 ? 'contact' : 'contacts') : '')
          : ucfirst($j['status']) . ' &middot; ' . (new DateTime($j['started_at']))->format('j M, g:i A'));
    ?>
    <div class="journey-row" role="button" tabindex="0" aria-label="Show trip from <?= htmlspecialchars($j['start_label'], ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($j['end_label'], ENT_QUOTES, 'UTF-8') ?>" data-status="<?= $j['status'] ?>" data-journey-id="<?= (int) $j['id'] ?>"
      data-journey-start="<?= htmlspecialchars($j['start_label'], ENT_QUOTES, 'UTF-8') ?>"
      data-journey-end="<?= htmlspecialchars($j['end_label'], ENT_QUOTES, 'UTF-8') ?>"
      data-start-lat="<?= $j['start_lat'] !== null ? (float) $j['start_lat'] : '' ?>"
      data-start-lng="<?= $j['start_lng'] !== null ? (float) $j['start_lng'] : '' ?>"
      data-end-lat="<?= $j['end_lat'] !== null ? (float) $j['end_lat'] : '' ?>"
      data-end-lng="<?= $j['end_lng'] !== null ? (float) $j['end_lng'] : '' ?>"
      data-journey-date="<?= htmlspecialchars($j['planned_departure_at'] ?: $j['started_at'], ENT_QUOTES, 'UTF-8') ?>"
      data-journey-status="<?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>"
      data-journey-distance="<?= $j['distance_km'] !== null ? number_format((float) $j['distance_km'], 1) . ' km' : 'Not recorded' ?>"
      data-journey-shared="<?= htmlspecialchars($j['shared_names'] ?: 'Not shared', ENT_QUOTES, 'UTF-8') ?>"
      data-journey-note="<?= htmlspecialchars($j['note'] ?: 'No trip note', ENT_QUOTES, 'UTF-8') ?>">
      <div class="jicon"><i class="fa-solid <?= $icon ?>"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($j['start_label']) ?> &rarr; <?= htmlspecialchars($j['end_label']) ?></b><small><?= $subLine ?></small></div>
      <div class="jmeta"><strong><?= $j['distance_km'] !== null ? number_format((float) $j['distance_km'], 1) . ' km' : '-' ?></strong><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($groupJourneys as $g): ?>
    <div class="journey-row" data-status="group" data-open-modal="groupModal<?= (int) $g['id'] ?>">
      <div class="jicon"><i class="fa-solid fa-user-group"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($g['title']) ?></b><small>Group journey &middot; <?= (int) $g['member_count'] ?> members &middot; <?= htmlspecialchars(ucfirst($g['status'])) ?></small></div>
      <div class="jmeta"><strong><?= $g['distance_km'] !== null ? number_format((float) $g['distance_km'], 1) . ' km' : '-' ?></strong><span class="badge active">Group</span></div>
    </div>
    <?php endforeach; ?>

  </div>
  <p class="hint" id="emptyState" style="display:none;padding:0 21px 21px;color:var(--muted);font-size:11px">No journeys in this category yet.</p>
</div>
</div>

<aside class="journey-map-detail" id="journeyMapDetail" aria-live="polite" aria-label="Selected trip details">
  <div class="journey-map-detail-actions">
    <button type="button" class="btn-ghost" id="journeyPickerToggle" aria-expanded="false" aria-controls="journeyPickerPanel"><i class="fa-solid fa-list"></i>Choose trip</button>
    <button type="button" class="btn-ghost" data-theme-toggle aria-label="Switch colour theme" title="Switch colour theme"><i class="fa-solid fa-circle-half-stroke"></i>Theme</button>
    <?php if (empty($journeys)): ?><a class="btn-primary" href="start-journey.php"><i class="fa-solid fa-plus"></i>Plan a trip</a><?php endif; ?>
  </div>
  <span class="explore-eyebrow">SELECTED TRIP</span>
  <h3 id="journeyMapDetailTitle"><?= !empty($journeys[0]) ? htmlspecialchars($journeys[0]['start_label'] . ' → ' . $journeys[0]['end_label'], ENT_QUOTES, 'UTF-8') : 'Choose a trip' ?></h3>
  <p id="journeyMapDetailStatus"><?= !empty($journeys[0]) ? htmlspecialchars(ucfirst($journeys[0]['status']), ENT_QUOTES, 'UTF-8') : 'Your selected route will appear here.' ?></p>
  <p id="journeyMapDetailRouteStatus" class="journey-map-route-status" role="status" aria-live="polite">Choose a trip to load its road route.</p>
  <dl>
    <div><dt>Distance</dt><dd id="journeyMapDetailDistance"><?= !empty($journeys[0]) && $journeys[0]['distance_km'] !== null ? number_format((float) $journeys[0]['distance_km'], 1) . ' km' : 'Not recorded' ?></dd></div>
    <div><dt>Departure</dt><dd id="journeyMapDetailDate"><?= !empty($journeys[0]) ? htmlspecialchars($journeys[0]['planned_departure_at'] ?: $journeys[0]['started_at'], ENT_QUOTES, 'UTF-8') : '—' ?></dd></div>
    <div><dt>Shared with</dt><dd id="journeyMapDetailShared"><?= !empty($journeys[0]) ? htmlspecialchars($journeys[0]['shared_names'] ?: 'Not shared', ENT_QUOTES, 'UTF-8') : '—' ?></dd></div>
  </dl>
  <p class="journey-map-detail-note" id="journeyMapDetailNote"><?= !empty($journeys[0]) ? htmlspecialchars($journeys[0]['note'] ?: 'No trip note', ENT_QUOTES, 'UTF-8') : 'Select a trip from your history to focus its route.' ?></p>
  <div class="journey-map-detail-share">
    <button type="button" class="btn-primary" id="shareSelectedJourney" disabled><i class="fa-solid fa-share-nodes"></i>Share trip</button>
    <input type="url" id="selectedJourneyShareUrl" aria-label="Temporary trip sharing link" readonly hidden>
    <button type="button" class="btn-ghost" id="copySelectedJourneyLink" hidden>Copy link</button>
    <p id="selectedJourneyShareStatus" role="status" aria-live="polite"></p>
  </div>
</aside>

<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<?php foreach ($journeys as $j): ?>
<div class="modal-overlay" id="journeyModal<?= (int) $j['id'] ?>">
  <div class="modal">
    <div class="modal-head">
      <div><h3><?= htmlspecialchars($j['start_label']) ?> &rarr; <?= htmlspecialchars($j['end_label']) ?></h3><p><?= ucfirst($j['status']) ?><?= $j['status'] === 'active' ? ' &middot; started ' . (new DateTime($j['started_at']))->format('g:i A') : ($j['status'] === 'planned' ? ' &middot; leaves ' . (new DateTime($j['planned_departure_at']))->format('j M, g:i A') : '') ?></p></div>
      <button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <p><b>Distance:</b> <?= $j['distance_km'] !== null ? number_format((float) $j['distance_km'], 1) . ' km' : 'Not available' ?><?= $j['ended_at'] ? ' &middot; <b>Duration:</b> ' . journey_duration($j['started_at'], $j['ended_at']) : '' ?></p>
      <?php if (!empty($j['shared_names'])): ?>
      <p style="margin-top:8px"><b>Shared with:</b> <?= htmlspecialchars($j['shared_names']) ?></p>
      <?php endif; ?>
      <?php if ($j['note']): ?>
      <p style="margin-top:8px"><b>Note:</b> <?= htmlspecialchars($j['note']) ?></p>
      <?php endif; ?>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <?php if ($j['status'] === 'active'): ?>
      <a class="primary" href="my-journeys.php?selected=<?= (int) $j['id'] ?>">View on map</a>
      <?php elseif ($j['status'] === 'planned'): ?>
      <button type="button" class="primary" data-start-planned="<?= (int) $j['id'] ?>">Start now</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php foreach ($groupJourneys as $g): ?>
<div class="modal-overlay" id="groupModal<?= (int) $g['id'] ?>">
  <div class="modal">
    <div class="modal-head"><div><h3><?= htmlspecialchars($g['title']) ?></h3><p><?= (int) $g['member_count'] ?> members &middot; <?= htmlspecialchars(ucfirst($g['status'])) ?></p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><p><b>Destination:</b> <?= htmlspecialchars($g['destination_label']) ?></p></div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <a class="primary" href="groups.php?tab=trips">Manage group</a>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="journeys.js"></script>
</body>
</html>