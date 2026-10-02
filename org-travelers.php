<?php
require __DIR__ . '/backend/includes/org-guard.php';

if (!$myOrg || $myOrgSuspended) {
    header('Location: org-dashboard.php');
    exit;
}

$db = safaritrak_db();
$orgId = $myOrg['id'];

$travelersStmt = $db->prepare(
    'SELECT ot.id AS row_id, ot.status AS membership_status, u.id AS user_id, u.full_name, u.phone,
            (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id) AS journey_count,
            (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id AND j.status = "active") AS has_active_journey,
            (SELECT MAX(started_at) FROM journeys j WHERE j.user_id = u.id) AS last_journey_at
     FROM organization_travelers ot
     JOIN users u ON u.id = ot.user_id
     WHERE ot.organization_id = ?
     ORDER BY ot.status ASC, u.full_name ASC'
);
$travelersStmt->execute([$orgId]);
$travelers = $travelersStmt->fetchAll();

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
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="org-badge">ORGANIZATION</span></div></div>
  <nav>
    <a href="org-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <a class="active" href="org-travelers.php"><i class="fa-solid fa-users"></i>Travelers</a>
    <a href="org-groups.php"><i class="fa-solid fa-user-group"></i>Group Journeys</a>
    <a href="org-reports.php"><i class="fa-solid fa-chart-simple"></i>Reports</a>
  </nav>
  <div class="bottom">
    <a href="index.php"><i class="fa-solid fa-arrow-right-arrow-left"></i>Switch to traveler view</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account">
      <span><?= st_avatar_inner($currentUser) ?></span>
      <div>
        <b><?= htmlspecialchars($userName) ?></b>
        <small><?= htmlspecialchars($myOrg['name']) ?></small>
      </div>
    </div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>PEOPLE</label><h1>Travelers</h1></div>
  <div class="head-actions">
    <div class="notif-wrap">
      <button type="button" class="notif-bell" id="notifBell">
        <i class="fa-regular fa-bell"></i>
        <span class="notif-dot" id="notifDot"></span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head">
          <b>Notifications</b>
          <a href="notifications.php">View all</a>
        </div>
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
  <div><h2>Everyone in your organization</h2><p>See who is travelling, manage access, and keep an eye on activity.</p></div>
  <button type="button" class="btn-primary" data-open-modal="addTravelerModal"><i class="fa-solid fa-user-plus"></i>Add traveler</button>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="travelerSearch" placeholder="Search travelers by name or phone...">
    <select id="travelerStatusFilter">
      <option value="all">All statuses</option>
      <option value="active">Active now</option>
      <option value="offline">Offline</option>
      <option value="deactivated">Deactivated</option>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="travelersTable">
      <thead>
        <tr><th>Traveler</th><th>Phone</th><th>Status</th><th>Journeys</th><th>Last active</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (empty($travelers)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px 0">No travelers yet. Add someone by their phone number to get started.</td></tr>
        <?php endif; ?>
        <?php foreach ($travelers as $t): ?>
        <?php
          $rowStatus = $t['membership_status'] === 'deactivated' ? 'deactivated' : ($t['has_active_journey'] > 0 ? 'active' : 'offline');
          $badge = ['active' => ['active', 'Active now'], 'offline' => ['completed', 'Offline'], 'deactivated' => ['cancelled', 'Deactivated']][$rowStatus];
        ?>
        <tr data-status="<?= $rowStatus ?>">
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($t['full_name'])) ?></span><?= htmlspecialchars($t['full_name']) ?></div></td>
          <td><?= htmlspecialchars($t['phone']) ?></td>
          <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
          <td><?= (int) $t['journey_count'] ?></td>
          <td><?= admin_last_active($t['last_journey_at']) ?></td>
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
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
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

<?php foreach ($travelers as $t): ?>
<div class="modal-overlay" id="travelerModal<?= (int) $t['row_id'] ?>">
  <div class="modal">
    <div class="modal-head"><div><h3><?= htmlspecialchars($t['full_name']) ?></h3><p><?= htmlspecialchars($t['phone']) ?></p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <p><?= (int) $t['journey_count'] ?> journey<?= $t['journey_count'] == 1 ? '' : 's' ?> logged.
      <?= $t['has_active_journey'] > 0 ? 'Currently on an active journey.' : ($t['last_journey_at'] ? 'Last active ' . strtolower(admin_last_active($t['last_journey_at'])) . '.' : 'No journeys yet.') ?></p>
    </div>
    <div class="modal-actions"><button type="button" class="ghost" data-close-modal>Close</button></div>
  </div>
</div>
<?php endforeach; ?>

<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="org-travelers.js"></script>
</body>
</html>