<?php
require __DIR__ . '/backend/includes/org-guard.php';

if (!$myOrg || $myOrgSuspended) {
    header('Location: org-dashboard.php');
    exit;
}

$db = safaritrak_db();
$orgId = $myOrg['id'];

$groupsStmt = $db->prepare(
    'SELECT gj.*, u.full_name AS organizer_name,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_journey_id = gj.id AND gm.status = "confirmed") AS confirmed_count,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_journey_id = gj.id) AS total_invited
     FROM group_journeys gj
     JOIN users u ON u.id = gj.organizer_id
     WHERE gj.organizer_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")
     ORDER BY FIELD(gj.status, "active", "upcoming", "completed", "cancelled"), gj.created_at DESC'
);
$groupsStmt->execute([$orgId]);
$groups = $groupsStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Group Journeys</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="org-badge">ORGANIZATION</span></div></div>
  <nav>
    <a href="org-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <a href="org-travelers.php"><i class="fa-solid fa-users"></i>Travelers</a>
    <a class="active" href="org-groups.php"><i class="fa-solid fa-user-group"></i>Group Journeys</a>
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
  <div><label>TRAVELLING TOGETHER</label><h1>Group Journeys</h1></div>
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
  <div><h2>Group trips across your organization</h2><p>See every group journey organized by your travelers and who is authorized to be tracked.</p></div>
</div>

<div class="card">
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Trip</th><th>Organizer</th><th>Members</th><th>Date</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (empty($groups)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px 0">No group journeys organized by your travelers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($groups as $g): ?>
        <?php $badgeClass = ['active' => 'active', 'upcoming' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled'][$g['status']]; ?>
        <tr>
          <td><b><?= htmlspecialchars($g['title']) ?></b></td>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($g['organizer_name'])) ?></span><?= htmlspecialchars($g['organizer_name']) ?></div></td>
          <td><?= (int) $g['confirmed_count'] ?> / <?= (int) $g['total_invited'] ?></td>
          <td><?= $g['departure_at'] ? (new DateTime($g['departure_at']))->format('j M Y') : (new DateTime($g['created_at']))->format('j M Y') ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($g['status']) ?></span></td>
          <td><a class="table-action" href="#" data-open-modal="orgGroupModal<?= (int) $g['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<?php foreach ($groups as $g): ?>
<?php
  $membersStmt = $db->prepare('SELECT u.full_name, gm.status FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_journey_id = ? ORDER BY FIELD(gm.status,"confirmed","invited","declined")');
  $membersStmt->execute([$g['id']]);
  $groupMembers = $membersStmt->fetchAll();
?>
<div class="modal-overlay" id="orgGroupModal<?= (int) $g['id'] ?>">
  <div class="modal">
    <div class="modal-head"><div><h3><?= htmlspecialchars($g['title']) ?></h3><p>To <?= htmlspecialchars($g['destination_label']) ?> &middot; <?= ucfirst($g['status']) ?></p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <p>Organized by <?= htmlspecialchars($g['organizer_name']) ?>.</p>
      <div class="share-contacts" style="margin-top:10px">
        <?php foreach ($groupMembers as $gm): ?>
        <div class="share-contact-row"><span class="person"><?= htmlspecialchars(st_initials($gm['full_name'])) ?></span><span><?= htmlspecialchars($gm['full_name']) ?> &middot; <?= ucfirst($gm['status']) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="modal-actions"><button type="button" class="ghost" data-close-modal>Close</button></div>
  </div>
</div>
<?php endforeach; ?>

<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
</body>
</html>