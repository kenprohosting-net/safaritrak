<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();
$userId = (int) $currentUser['id'];
$pendingStmt = $db->prepare(
    'SELECT ca.id, ca.child_user_id, child.full_name AS child_name, o.name AS organization_name,
            admin.full_name AS invited_by_name
     FROM organization_child_access ca
     JOIN organizations o ON o.id = ca.organization_id AND o.is_suspended = 0
     JOIN organization_profiles p ON p.organization_id = o.id
       AND p.account_type = "school" AND p.review_status = "approved"
     JOIN organization_travelers student
       ON student.organization_id = ca.organization_id AND student.user_id = ca.child_user_id
      AND student.status = "active" AND student.member_type = "student"
     JOIN organization_travelers guardian
       ON guardian.organization_id = ca.organization_id AND guardian.user_id = ca.guardian_user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN users child ON child.id = ca.child_user_id
     JOIN users admin ON admin.id = ca.invited_by
     WHERE ca.guardian_user_id = ? AND ca.status = "pending"
     ORDER BY ca.created_at DESC'
);
$pendingStmt->execute([$userId]);
$pendingInvitations = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

$childrenStmt = $db->prepare(
    'SELECT ca.id AS access_id, ca.child_user_id, ca.organization_id, child.full_name AS child_name, o.name AS organization_name
     FROM organization_child_access ca
     JOIN organizations o ON o.id = ca.organization_id AND o.is_suspended = 0
     JOIN organization_profiles p ON p.organization_id = o.id
       AND p.account_type = "school" AND p.review_status = "approved"
     JOIN organization_travelers student
       ON student.organization_id = ca.organization_id AND student.user_id = ca.child_user_id
      AND student.status = "active" AND student.member_type = "student"
     JOIN organization_travelers guardian
       ON guardian.organization_id = ca.organization_id AND guardian.user_id = ca.guardian_user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN users child ON child.id = ca.child_user_id
     WHERE ca.guardian_user_id = ? AND ca.status = "active"
     ORDER BY child.full_name'
);
$childrenStmt->execute([$userId]);
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

$requestedChildId = filter_input(INPUT_GET, 'child', FILTER_VALIDATE_INT);
$selectedChild = null;
foreach ($children as $child) {
    if ($requestedChildId && (int) $child['child_user_id'] === $requestedChildId) {
        $selectedChild = $child;
        break;
    }
}
if (!$selectedChild && $children) {
    $selectedChild = $children[0];
}

$childJourneys = [];
$mapFeatures = [];
if ($selectedChild) {
    $journeysStmt = $db->prepare(
        'SELECT id, start_label, start_lat, start_lng, end_label, end_lat, end_lng,
                status, started_at, planned_departure_at, ended_at, distance_km
         FROM journeys
         WHERE user_id = ?
         ORDER BY FIELD(status, "active", "planned", "completed", "cancelled"),
                  COALESCE(planned_departure_at, started_at) DESC
         LIMIT 50'
    );
    $journeysStmt->execute([(int) $selectedChild['child_user_id']]);
    $childJourneys = $journeysStmt->fetchAll(PDO::FETCH_ASSOC);

    $requestedTripId = filter_input(INPUT_GET, 'trip', FILTER_VALIDATE_INT);
    $selectedJourney = null;
    foreach ($childJourneys as $journey) {
        if ($requestedTripId && (int) $journey['id'] === $requestedTripId) {
            $selectedJourney = $journey;
            break;
        }
    }
    if (!$selectedJourney && $childJourneys) {
        $selectedJourney = $childJourneys[0];
    }

    if ($selectedJourney) {
        $journeyId = (int) $selectedJourney['id'];
        $positionsStmt = $db->prepare(
            'SELECT lat, lng FROM journey_positions WHERE journey_id = ?
             ORDER BY id DESC LIMIT 1200'
        );
        $positionsStmt->execute([$journeyId]);
        $route = array_map(
            static fn(array $position): array => [(float) $position['lng'], (float) $position['lat']],
            array_reverse($positionsStmt->fetchAll(PDO::FETCH_ASSOC))
        );
        $startLat = $selectedJourney['start_lat'] === null ? null : (float) $selectedJourney['start_lat'];
        $startLng = $selectedJourney['start_lng'] === null ? null : (float) $selectedJourney['start_lng'];
        $endLat = $selectedJourney['end_lat'] === null ? null : (float) $selectedJourney['end_lat'];
        $endLng = $selectedJourney['end_lng'] === null ? null : (float) $selectedJourney['end_lng'];
        if ($startLat !== null && $startLng !== null) {
            $mapFeatures[] = [
                'journey_id' => $journeyId,
                'lat' => $startLat,
                'lng' => $startLng,
                'kind' => 'start',
                'label' => 'Start · ' . (string) $selectedJourney['start_label'],
                'route' => count($route) > 1 ? $route : [],
                'routeColor' => 'planned',
            ];
        }
        if ($endLat !== null && $endLng !== null) {
            $mapFeatures[] = [
                'journey_id' => $journeyId,
                'lat' => $endLat,
                'lng' => $endLng,
                'kind' => 'finish',
                'label' => 'Destination · ' . (string) $selectedJourney['end_label'],
            ];
        }
    }
}

$activeNav = $selectedChild ? 'school-child-' . (int) $selectedChild['child_user_id'] : 'school-child-invitations';
$pageTitle = 'School trips';
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | School trips</title>
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
  <div><label>SCHOOL TRIP ACCESS</label><h1><?= $selectedChild ? 'Trips for ' . htmlspecialchars($selectedChild['child_name'], ENT_QUOTES, 'UTF-8') : 'School invitations' ?></h1></div>
  <div class="head-actions"><div class="avatar"><?= st_avatar_inner($currentUser) ?></div></div>
</header>
<div class="content">
  <p id="schoolAccessStatus" role="status" aria-live="polite"></p>
  <?php if ($pendingInvitations): ?>
  <section class="card" id="invitations" aria-label="Pending school invitations">
    <div class="card-head"><div><label>ACCESS REQUEST</label><h2>School trip invitations</h2></div></div>
    <div class="journey-list">
      <?php foreach ($pendingInvitations as $invitation): ?>
      <article class="journey-row">
        <div class="jicon"><i class="fa-solid fa-school"></i></div>
        <div class="jinfo"><b><?= htmlspecialchars($invitation['child_name'], ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars($invitation['organization_name'], ENT_QUOTES, 'UTF-8') ?> invited you to view trips for this student.</small></div>
        <div class="jmeta"><button type="button" class="btn-ghost school-access-action" data-access-id="<?= (int) $invitation['id'] ?>" data-action="decline">Decline</button><button type="button" class="btn-primary school-access-action" data-access-id="<?= (int) $invitation['id'] ?>" data-action="accept">Accept</button></div>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="hint">Accepting gives this account access to the selected student's recorded and live journeys. You can decline instead; school membership alone never grants trip access.</p>
  </section>
  <?php endif; ?>

  <?php if ($selectedChild): ?>
  <section class="card" aria-label="Student journey history">
    <div class="card-head"><div><label><?= htmlspecialchars($selectedChild['organization_name'], ENT_QUOTES, 'UTF-8') ?></label><h2><?= htmlspecialchars($selectedChild['child_name'], ENT_QUOTES, 'UTF-8') ?>'s trips</h2></div></div>
    <button type="button" class="btn-ghost school-access-action" data-access-id="<?= (int) $selectedChild['access_id'] ?>" data-action="revoke">Stop viewing these trips</button>
    <?php if (!$childJourneys): ?>
      <p class="hint">There are no trips to show yet.</p>
    <?php else: ?>
      <div class="journey-list">
        <?php foreach ($childJourneys as $journey): ?>
          <a class="journey-row<?= isset($selectedJourney) && (int) $selectedJourney['id'] === (int) $journey['id'] ? ' is-map-selected' : '' ?>"
             href="school-trips.php?child=<?= (int) $selectedChild['child_user_id'] ?>&amp;trip=<?= (int) $journey['id'] ?>">
            <span class="jicon"><i class="fa-solid fa-route"></i></span>
            <span class="jinfo"><b><?= htmlspecialchars($journey['start_label'], ENT_QUOTES, 'UTF-8') ?> &rarr; <?= htmlspecialchars($journey['end_label'], ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars(ucfirst($journey['status']), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($journey['planned_departure_at'] ?: $journey['started_at']), ENT_QUOTES, 'UTF-8') ?></small></span>
            <span class="jmeta"><span class="badge <?= htmlspecialchars($journey['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($journey['status']), ENT_QUOTES, 'UTF-8') ?></span></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="hint">Select a trip to show its route. Live location is available only while the trip is active.</p>
    <?php endif; ?>
  </section>
  <?php elseif (!$pendingInvitations): ?>
  <section class="card"><h2>No school trip access</h2><p class="hint">When a school administrator invites you to view a student's trips, the invitation will appear here.</p></section>
  <?php endif; ?>
</div>
<footer>&copy; <?= date('Y') ?> SafariTrak</footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="school-trips.js?v=<?= hash_file('sha256', __DIR__ . '/school-trips.js') ?>"></script>
</body>
</html>
