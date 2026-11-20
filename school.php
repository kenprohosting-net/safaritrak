<?php

require __DIR__ . '/backend/includes/auth-guard.php';
require_once __DIR__ . '/backend/includes/school-transport.php';

$schoolAccess = st_school_transport_verification($db, (int) $currentUser['id']);
if (!$schoolAccess) {
    header('Location: index.php');
    exit();
}

$activeNav = 'school';
$appMapPage = 'school';
$showAppMapBackground = true;
$showAppMapControls = false;
$showAppMapPanelsToggle = false;
$students = [];
if ($schoolAccess['role'] === 'school_admin') {
    $studentsStmt = $db->prepare(
        'SELECT DISTINCT student.id, student.full_name
         FROM organization_travelers pupil
         JOIN users student ON student.id = pupil.user_id
         JOIN organization_child_access ca
           ON ca.organization_id = pupil.organization_id
          AND ca.child_user_id = pupil.user_id AND ca.status = "active"
         JOIN organization_travelers guardian
           ON guardian.organization_id = ca.organization_id
          AND guardian.user_id = ca.guardian_user_id
          AND guardian.status = "active" AND guardian.member_type = "guardian"
         WHERE pupil.organization_id = ? AND pupil.status = "active"
           AND pupil.member_type = "student"
         ORDER BY student.full_name'
    );
    $studentsStmt->execute([(int) $schoolAccess['school_id']]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#060b16">
<title>School Transport | SafariTrak</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="school-workspace.css?v=<?= hash_file('sha256', __DIR__ . '/school-workspace.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--workspace app--school app--school-<?= htmlspecialchars($schoolAccess['role'], ENT_QUOTES, 'UTF-8') ?>"
     data-school-role="<?= htmlspecialchars($schoolAccess['role'], ENT_QUOTES, 'UTF-8') ?>"
     data-school-id="<?= (int) $schoolAccess['school_id'] ?>"
     data-current-user-id="<?= (int) $currentUser['id'] ?>">
  <?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

  <main>
    <div class="school-mobile-tabs" role="tablist" aria-label="School transport panels">
      <button type="button" class="is-active" data-school-panel="left" aria-selected="true">Route & roster</button>
      <button type="button" data-school-panel="right" aria-selected="false">Live trip</button>
    </div>

    <?php if ($schoolAccess['role'] === 'school_admin'): ?>
    <div class="school-workspace" id="schoolWorkspace" aria-label="School transport dashboard">
      <aside class="school-panel school-left-panel" id="schoolLeftPanel">
        <header class="school-heading">
          <div>
            <p class="school-eyebrow">SCHOOL TRANSPORT</p>
            <h1><?= htmlspecialchars($schoolAccess['school_name'], ENT_QUOTES, 'UTF-8') ?></h1>
          </div>
          <span class="school-verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified School</span>
        </header>
        <div class="school-panel-title-row">
          <h2>Fleet & active routes</h2>
          <button type="button" class="school-icon-button" id="openBusModal" aria-label="Add a bus" title="Add a bus">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
          </button>
        </div>
        <div class="school-filter-row" role="group" aria-label="Filter buses">
          <button type="button" class="is-active" data-bus-filter="all">All Buses</button>
          <button type="button" data-bus-filter="en_route">En Route</button>
          <button type="button" data-bus-filter="arrived">Arrived</button>
        </div>
        <div class="school-bus-list" id="schoolBusList" aria-live="polite">
          <div class="school-empty">Loading your school fleet…</div>
        </div>
        <p class="school-feedback" id="schoolLeftFeedback" role="status" aria-live="polite"></p>
      </aside>

      <aside class="school-panel school-right-panel" id="schoolRightPanel">
        <header class="school-panel-heading">
          <div>
            <p class="school-eyebrow">FAMILY SAFETY</p>
            <h2>Parent location broadcast & roster</h2>
          </div>
        </header>
        <button type="button" class="school-primary-button school-broadcast-button" id="broadcastHomebound">
          <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>
          <span>Broadcast Homebound Trip to Parents</span>
        </button>
        <div class="school-eta-card" id="schoolEtaCard">
          <span class="school-status-dot"></span>
          <div><strong id="schoolEtaTitle">Select a bus</strong><small id="schoolEtaDetail">Live ETA appears when a bus location is available.</small></div>
        </div>
        <div class="school-panel-title-row school-roster-title">
          <h3>Verified parent roster</h3>
          <button class="school-text-button" type="button" id="openVerifyParentModal">+ Verify</button>
        </div>
        <div class="school-parent-roster" id="schoolParentRoster" aria-live="polite">
          <div class="school-empty">Select a bus to view its verified roster.</div>
        </div>
        <form class="school-note-form" id="driverNoteForm">
          <label for="driverNote">Share a driver update</label>
          <div><input id="driverNote" maxlength="500" placeholder="Traffic clear after Runda…"><button type="submit" aria-label="Share trip update"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button></div>
        </form>
        <p class="school-small-note">Live updates are shared in-app with verified parents assigned to this bus. Keep this page open while sharing GPS.</p>
        <p class="school-feedback" id="schoolRightFeedback" role="status" aria-live="polite"></p>
      </aside>
    </div>
    <?php else: ?>
    <div class="school-workspace school-workspace--parent" id="schoolWorkspace" aria-label="Verified parent bus tracking">
      <aside class="school-panel school-left-panel" id="schoolLeftPanel">
        <header class="school-panel-heading">
          <div>
            <p class="school-eyebrow">SCHOOL TRANSPORT</p>
            <h1>My child’s bus</h1>
          </div>
          <span class="school-verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified</span>
        </header>
        <div class="school-parent-profile">
          <span class="school-avatar" id="studentAvatar">ST</span>
          <div><strong id="studentName">Loading student…</strong><small id="studentGrade">Verified parent account</small></div>
        </div>
        <section class="school-assigned-bus" id="assignedBusCard">
          <div class="school-empty">Loading assigned bus…</div>
        </section>
        <section class="school-timeline-section">
          <div class="school-panel-title-row"><h2>Drop-off timeline</h2><span class="school-live-pill" id="studentStatusPill">Waiting</span></div>
          <ol class="school-timeline" id="schoolTimeline"></ol>
        </section>
        <div class="school-driver-note" id="parentDriverNote" hidden></div>
        <div class="school-dropoff-controls">
          <button class="school-secondary-button" type="button" id="chooseDropoff">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i> Choose home drop-off on map
          </button>
          <div class="school-dropoff-label-row">
            <label for="dropoffLabel">Drop-off label</label>
            <input id="dropoffLabel" maxlength="120" value="Home drop-off">
          </div>
          <label class="school-route-consent"><input id="routeServiceConsent" type="checkbox"><span>Allow route services to calculate directions to this point. They receive its coordinates.</span></label>
          <p class="school-small-note">Your selected point is visible to your school transport administrators and in your own tracking view. Leave the box unchecked to keep it out of route calculations.</p>
        </div>
        <p class="school-feedback" id="parentLeftFeedback" role="status" aria-live="polite"></p>
      </aside>

      <aside class="school-panel school-right-panel" id="schoolRightPanel">
        <header class="school-panel-heading">
          <div><p class="school-eyebrow">LIVE SAFETY</p><h2>Live trip & communication</h2></div>
        </header>
        <div class="school-broadcast-status" id="parentBroadcastStatus">
          <span class="school-status-dot"></span><strong>Waiting for school broadcast</strong>
        </div>
        <div class="school-live-details">
          <div><span>Last update</span><strong id="parentLastUpdate">—</strong></div>
          <div><span>Near</span><strong id="parentCurrentRoad">Waiting for bus location</strong></div>
          <div><span>Estimated distance</span><strong id="parentRemainingDistance">—</strong></div>
        </div>
        <div class="school-contact-actions" id="parentContactActions">
          <p class="school-empty">Your school will provide contact options when a bus is assigned.</p>
        </div>
        <div class="school-roster-status" id="parentRosterStatus">
          <span class="school-avatar school-avatar--small" id="rosterStudentAvatar">ST</span>
          <div><strong id="rosterStudentName">Student</strong><small>Waiting for boarding status</small></div>
          <span class="school-live-pill">Not boarded</span>
        </div>
        <div class="school-notice"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Location is shared only while the school’s live broadcast is active. SMS delivery is not configured in this build.</span></div>
        <p class="school-feedback" id="parentRightFeedback" role="status" aria-live="polite"></p>
      </aside>
    </div>
    <?php endif; ?>
  </main>

<?php if ($schoolAccess['role'] === 'school_admin'): ?>
<dialog class="school-modal" id="addBusDialog" aria-labelledby="addBusTitle">
  <form method="dialog" class="school-modal-head"><div><p class="school-eyebrow">FLEET SETUP</p><h2 id="addBusTitle">Register a school bus</h2><p>Set the school origin and the route stops for this bus.</p></div><button class="school-icon-button" aria-label="Close dialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></form>
  <form id="addBusForm" class="school-form">
    <div class="school-form-grid">
      <label>Bus number<input name="bus_number" maxlength="32" required placeholder="Bus 04"></label>
      <label>Route name<input name="route_name" maxlength="120" required placeholder="Kikuyu / Rungiri Route"></label>
      <label>Driver name<input name="driver_name" maxlength="120" required placeholder="Driver full name"></label>
      <label>Driver phone<input name="driver_phone" maxlength="20" type="tel" placeholder="+254…"></label>
      <label class="school-form-full">Driver account email or phone (optional)<input name="driver_identifier" maxlength="160" placeholder="Must belong to active school staff"></label>
      <label>School origin name<input name="origin_name" maxlength="120" required placeholder="School main gate"></label>
      <label>Origin latitude<input name="origin_lat" type="number" step="any" min="-90" max="90" required placeholder="-1.2864"></label>
      <label>Origin longitude<input name="origin_lng" type="number" step="any" min="-180" max="180" required placeholder="36.8172"></label>
    </div>
    <div class="school-stop-editor">
      <div class="school-panel-title-row"><h3>Ordered route stops</h3><button class="school-text-button" type="button" id="addStopRow">+ Add stop</button></div>
      <p class="school-small-note">Coordinates are used to create the road-snapped map route. Use decimal latitude and longitude.</p>
      <div id="schoolStopRows">
        <div class="school-stop-row">
          <input aria-label="Stop name" placeholder="Westlands Hub" maxlength="120" required>
          <select aria-label="Stop type"><option value="dropoff">Drop-off</option><option value="pickup">Pickup</option><option value="waypoint">Waypoint</option></select>
          <input aria-label="Stop latitude" type="number" step="any" min="-90" max="90" placeholder="Latitude" required>
          <input aria-label="Stop longitude" type="number" step="any" min="-180" max="180" placeholder="Longitude" required>
          <button type="button" class="school-remove-stop" aria-label="Remove stop" disabled><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
      </div>
    </div>
    <p class="school-feedback" id="addBusFeedback" role="status" aria-live="polite"></p>
    <div class="school-modal-actions"><button type="button" class="school-secondary-button" data-close-school-dialog>Cancel</button><button type="submit" class="school-primary-button">Save bus & route</button></div>
  </form>
</dialog>

<dialog class="school-modal" id="verifyParentDialog" aria-labelledby="verifyParentTitle">
  <form method="dialog" class="school-modal-head"><div><p class="school-eyebrow">PARENT ACCESS</p><h2 id="verifyParentTitle">Verify a parent account</h2><p>Only parents with accepted access to an active student can be linked.</p></div><button class="school-icon-button" aria-label="Close dialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></form>
  <form id="verifyParentForm" class="school-form">
    <label>Parent email or phone<input name="parent_identifier" required maxlength="160" placeholder="Use the account’s exact email or phone"></label>
    <label>Student<select name="student_user_id" required><option value="">Choose an active student</option>
      <?php foreach ($students as $student): ?>
      <option value="<?= (int) $student['id'] ?>"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select></label>
    <div class="school-form-grid">
      <label>Student grade (optional)<input name="student_grade" maxlength="40" placeholder="Grade 3"></label>
      <label>Assigned bus<select name="bus_id" id="verifyBusSelect" required><option value="">Choose a bus</option></select></label>
    </div>
    <p class="school-feedback" id="verifyParentFeedback" role="status" aria-live="polite"></p>
    <div class="school-modal-actions"><button type="button" class="school-secondary-button" data-close-school-dialog>Cancel</button><button type="submit" class="school-primary-button">Verify & assign</button></div>
  </form>
</dialog>
<?php endif; ?>
</div>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="route-directions.js?v=<?= hash_file('sha256', __DIR__ . '/route-directions.js') ?>"></script>
<script src="school-transport.js?v=<?= hash_file('sha256', __DIR__ . '/school-transport.js') ?>"></script>
</body>
</html>
