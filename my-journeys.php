<?php
require __DIR__ . '/backend/includes/auth-guard.php';

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

// Normalizes any status value (including "in_progress") to one of the
// three states the UI knows how to badge, so an unexpected status string
// never throws an undefined array key warning.
function journey_status_key(string $status): string {
    if ($status === 'in_progress') {
        return 'active';
    }
    return in_array($status, ['active', 'completed', 'cancelled'], true) ? $status : 'active';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | My Journeys</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
    <a class="btn-ghost" href="group-travel.php"><i class="fa-solid fa-user-group"></i>Group travel</a>
    <a class="btn-primary" href="start-journey.php"><i class="fa-solid fa-plus"></i>Start a journey</a>
  </div>
</div>

<div class="tabs" data-tab-group="journeys">
  <button type="button" class="tab active" data-tab="all">All</button>
  <button type="button" class="tab" data-tab="active">Active</button>
  <button type="button" class="tab" data-tab="completed">Completed</button>
  <button type="button" class="tab" data-tab="cancelled">Cancelled</button>
  <button type="button" class="tab" data-tab="group">Group</button>
</div>

<div class="card">
  <div class="journey-list" id="journeyList">

    <?php if (empty($journeys) && empty($groupJourneys)): ?>
    <p class="hint" style="padding:20px 21px;color:var(--muted);font-size:11px">You have not started any journeys yet. When you do, they will show up here.</p>
    <?php endif; ?>

    <?php foreach ($journeys as $j): ?>
    <?php
      $statusKey = journey_status_key($j['status']);
      $statusBadge = $statusKey;
      $statusLabel = ['active' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$statusKey];
      $icon = $statusKey === 'cancelled' ? 'fa-xmark' : ($statusKey === 'completed' ? 'fa-check' : 'fa-route');
      $subLine = $statusKey === 'active'
        ? 'Started ' . (new DateTime($j['started_at']))->format('g:i A') . ($j['share_count'] > 0 ? ' &middot; Shared with ' . $j['share_count'] . ' ' . ($j['share_count'] == 1 ? 'contact' : 'contacts') : '')
        : ucfirst($statusLabel) . ' &middot; ' . (new DateTime($j['started_at']))->format('j M, g:i A');
    ?>
    <div class="journey-row" data-status="<?= $statusKey ?>" data-open-modal="journeyModal<?= (int) $j['id'] ?>">
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
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<?php foreach ($journeys as $j): ?>
<?php $statusKey = journey_status_key($j['status']); ?>
<div class="modal-overlay" id="journeyModal<?= (int) $j['id'] ?>">
  <div class="modal">
    <div class="modal-head">
      <div><h3><?= htmlspecialchars($j['start_label']) ?> &rarr; <?= htmlspecialchars($j['end_label']) ?></h3><p><?= ucfirst($statusKey === 'active' ? 'in progress' : $statusKey) ?><?= $statusKey === 'active' ? ' &middot; started ' . (new DateTime($j['started_at']))->format('g:i A') : '' ?></p></div>
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
      <?php if ($statusKey === 'active'): ?>
      <a class="primary" href="live-tracking.php">View on map</a>
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
      <a class="primary" href="group-travel.php">Manage group</a>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="journeys.js"></script>
</body>
</html>