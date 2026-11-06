<?php
require __DIR__ . '/backend/includes/org-guard.php';

if (!$myOrg || $myOrgSuspended) {
    header('Location: org-dashboard.php');
    exit;
}

$db = safaritrak_db();
$orgId = $myOrg['id'];

$isSchool = ($myOrg['account_type'] ?? '') === 'school';
$travelerQuery = $isSchool
    ? 'SELECT ot.id AS row_id, ot.status AS membership_status, ot.member_type, u.id AS user_id, u.full_name
       FROM organization_travelers ot
       JOIN users u ON u.id = ot.user_id
       WHERE ot.organization_id = ?
       ORDER BY ot.status ASC, ot.member_type ASC, u.full_name ASC'
    : 'SELECT ot.id AS row_id, ot.status AS membership_status, ot.member_type, u.id AS user_id, u.full_name, u.phone,
              (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id) AS journey_count,
              (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id AND j.status = "active") AS has_active_journey,
              (SELECT MAX(started_at) FROM journeys j WHERE j.user_id = u.id) AS last_journey_at
       FROM organization_travelers ot
       JOIN users u ON u.id = ot.user_id
       WHERE ot.organization_id = ?
       ORDER BY ot.status ASC, u.full_name ASC';
$travelersStmt = $db->prepare($travelerQuery);
$travelersStmt->execute([$orgId]);
$travelers = $travelersStmt->fetchAll();
$membershipRequests = [];
if (($myOrg['account_type'] ?? '') === 'school') {
    $requestsStmt = $db->prepare(
        'SELECT r.id, r.member_type, r.created_at, u.full_name, u.email
         FROM organization_membership_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.organization_id = ? AND r.status = "pending"
         ORDER BY r.created_at ASC'
    );
    $requestsStmt->execute([$orgId]);
    $membershipRequests = $requestsStmt->fetchAll();
}

function admin_last_active(?string $dt): string {
    if (!$dt) return 'No activity yet';
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return (new DateTime($dt))->format('j M Y');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Travelers</title>
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
  <div><label>PEOPLE</label><h1>Travelers</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar">O</div></div>
</header>

<div class="content">

<div class="page-head">
  <div><h2><?= ($myOrg['account_type'] ?? '') === 'school' ? 'School community' : 'Everyone in your organization' ?></h2><p><?= ($myOrg['account_type'] ?? '') === 'school' ? 'Connect personal accounts as students, guardians, or staff. School membership does not grant access to personal routes or live locations.' : 'See who is travelling, manage access, and keep an eye on activity.' ?></p></div>
  <?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
    <button type="button" class="btn-primary" data-open-modal="schoolInviteModal"><i class="fa-solid fa-link"></i>Invite school member</button>
  <?php else: ?>
    <button type="button" class="btn-primary" data-open-modal="addTravelerModal"><i class="fa-solid fa-user-plus"></i>Add traveler</button>
  <?php endif; ?>
</div>

<?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
<div class="card org-enrollment-requests">
  <div class="card-head"><div><label>ACCESS CONTROL</label><h3>Pending enrollment requests</h3></div></div>
  <?php if (!$membershipRequests): ?>
    <p class="hint" style="padding:0 21px 18px;color:var(--muted);font-size:11px">No requests are waiting for approval.</p>
  <?php else: ?>
    <div class="journey-list">
    <?php foreach ($membershipRequests as $request): ?>
      <div class="journey-row">
        <div class="jicon"><i class="fa-solid fa-user-check"></i></div>
        <div class="jinfo"><b><?= htmlspecialchars($request['full_name']) ?></b><small><?= htmlspecialchars(ucfirst($request['member_type'])) ?> · <?= htmlspecialchars($request['email']) ?></small></div>
        <div class="jmeta"><button type="button" class="table-action enrollment-review-btn" data-request-id="<?= (int) $request['id'] ?>" data-action="approve">Approve</button><button type="button" class="table-action danger enrollment-review-btn" data-request-id="<?= (int) $request['id'] ?>" data-action="reject">Reject</button></div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="travelerSearch" placeholder="<?= $isSchool ? 'Search school members by name...' : 'Search travelers by name or phone...' ?>">
    <select id="travelerStatusFilter" aria-label="<?= $isSchool ? 'Filter school members by role' : 'Filter travelers by activity' ?>">
      <?php if ($isSchool): ?>
      <option value="all">All members</option>
      <option value="student">Students</option>
      <option value="guardian">Guardians</option>
      <option value="staff">Staff</option>
      <option value="deactivated">Deactivated</option>
      <?php else: ?>
      <option value="all">All statuses</option>
      <option value="active">Active now</option>
      <option value="offline">Offline</option>
      <option value="deactivated">Deactivated</option>
      <?php endif; ?>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="travelersTable">
      <thead>
        <?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
        <tr><th>Member</th><th>Role</th><th>Enrollment</th><th>Action</th></tr>
        <?php else: ?>
        <tr><th>Traveler</th><th>Phone</th><th>Status</th><th>Journeys</th><th>Last active</th><th>Action</th></tr>
        <?php endif; ?>
      </thead>
      <tbody>
        <?php if (empty($travelers)): ?>
        <tr><td colspan="<?= ($myOrg['account_type'] ?? '') === 'school' ? 4 : 6 ?>" style="text-align:center;color:var(--muted);padding:24px 0"><?= ($myOrg['account_type'] ?? '') === 'school' ? 'No school members yet. Invite a personal account to request enrollment.' : 'No travelers yet. Add someone by their phone number to get started.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($travelers as $t): ?>
        <?php
          $rowStatus = $t['membership_status'] === 'deactivated' ? 'deactivated' : ($isSchool ? 'enrolled' : ($t['has_active_journey'] > 0 ? 'active' : 'offline'));
          $badge = $rowStatus === 'enrolled'
            ? ['completed', 'Enrolled']
            : ['active' => ['active', 'Active now'], 'offline' => ['completed', 'Offline'], 'deactivated' => ['cancelled', 'Deactivated']][$rowStatus];
        ?>
        <tr data-status="<?= $rowStatus ?>" data-member-type="<?= htmlspecialchars($t['member_type'], ENT_QUOTES, 'UTF-8') ?>">
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($t['full_name'])) ?></span><?= htmlspecialchars($t['full_name']) ?></div></td>
          <?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
          <td><?= htmlspecialchars(ucfirst($t['member_type'])) ?></td>
          <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
          <?php else: ?>
          <td><?= htmlspecialchars($t['phone']) ?></td>
          <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
          <td><?= (int) $t['journey_count'] ?></td>
          <td><?= admin_last_active($t['last_journey_at']) ?></td>
          <?php endif; ?>
          <td>
            <a class="table-action" href="#" data-open-modal="travelerModal<?= (int) $t['row_id'] ?>">View</a>
            <?php if ($t['membership_status'] === 'active'): ?>
            <button type="button" class="table-action danger status-btn" data-row-id="<?= (int) $t['row_id'] ?>" data-action="deactivate" style="background:none;border:0;cursor:pointer;font-family:inherit">Deactivate</button>
            <?php else: ?>
            <button type="button" class="table-action status-btn" data-row-id="<?= (int) $t['row_id'] ?>" data-action="reactivate" style="background:none;border:0;cursor:pointer;font-family:inherit">Reactivate</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint" id="travelersEmptyState" style="display:none;padding:0 21px 21px;color:var(--muted);font-size:11px">No travelers match your search.</p>
</div>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="addTravelerModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Add a traveler</h3><p>Add someone who already has a SafariTrak account by their phone number.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div class="form-field"><label>Phone number</label><input type="tel" id="addTravelerPhone" placeholder="e.g. 0711 223 344"></div>
      <p class="hint" id="addTravelerError" style="color:#c94b4b;margin-top:10px;display:none"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="primary" id="addTravelerBtn">Add traveler</button>
    </div>
  </div>
</div>

<?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
<div class="modal-overlay" id="schoolInviteModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Invite a school member</h3><p>They must request membership and you must approve before any journey data is visible.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div class="form-field"><label>Email address (optional)</label><input type="email" id="schoolInviteEmail" placeholder="student@example.com"></div>
      <div class="form-field"><label>Invite as</label><select id="schoolInviteType"><option value="student">Student / pupil</option><option value="guardian">Parent / guardian</option><option value="staff">School staff</option></select></div>
      <p class="hint" id="schoolInviteError" role="status" style="color:#c94b4b;margin-top:10px"></p>
      <div id="schoolInviteLinkWrap" hidden><div class="form-field"><label>Invitation link · expires in 7 days</label><input type="url" id="schoolInviteUrl" readonly></div><button type="button" class="btn-primary" id="copySchoolInvite">Copy link</button><a id="emailSchoolInvite" class="table-action" href="#">Open email</a></div>
    </div>
    <div class="modal-actions"><button type="button" class="ghost" data-close-modal>Close</button><button type="button" class="primary" id="createSchoolInvite">Create link</button></div>
  </div>
</div>
<?php endif; ?>

<?php foreach ($travelers as $t): ?>
<div class="modal-overlay" id="travelerModal<?= (int) $t['row_id'] ?>">
  <div class="modal">
    <div class="modal-head"><div><h3><?= htmlspecialchars($t['full_name']) ?></h3><p><?= $isSchool ? htmlspecialchars(ucfirst($t['member_type'])) . ' account' : htmlspecialchars($t['phone']) ?></p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <?php if (($myOrg['account_type'] ?? '') === 'school'): ?>
      <p><?= htmlspecialchars(ucfirst($t['member_type'])) ?> account. Personal routes and live location are not visible through school enrollment.</p>
      <?php else: ?>
      <p><?= (int) $t['journey_count'] ?> journey<?= $t['journey_count'] == 1 ? '' : 's' ?> logged.
      <?= $t['has_active_journey'] > 0 ? 'Currently on an active journey.' : ($t['last_journey_at'] ? 'Last active ' . strtolower(admin_last_active($t['last_journey_at'])) . '.' : 'No journeys yet.') ?></p>
      <?php endif; ?>
    </div>
    <div class="modal-actions"><button type="button" class="ghost" data-close-modal>Close</button></div>
  </div>
</div>
<?php endforeach; ?>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="org-travelers.js"></script>
</body>
</html>
