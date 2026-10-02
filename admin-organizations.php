<?php
require __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole) {
    header('Location: admin-dashboard.php');
    exit;
}

$db = safaritrak_db();

$orgsStmt = $db->query(
    'SELECT o.id, o.name, o.is_suspended, o.created_at, u.full_name AS owner_name, u.phone AS owner_phone,
            (SELECT COUNT(*) FROM organization_travelers ot WHERE ot.organization_id = o.id AND ot.status = "active") AS traveler_count
     FROM organizations o
     JOIN organization_admins oa ON oa.organization_id = o.id AND oa.role = "owner"
     JOIN users u ON u.id = oa.user_id
     ORDER BY o.created_at DESC'
);
$orgs = $orgsStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Organizations</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar platform-sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="platform-badge">PLATFORM ADMIN</span></div></div>
  <nav>
    <a href="admin-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <a href="admin-users.php"><i class="fa-solid fa-users"></i>Users</a>
    <a class="active" href="admin-organizations.php"><i class="fa-solid fa-building"></i>Organizations</a>
    <a href="admin-safety.php"><i class="fa-solid fa-triangle-exclamation"></i>Safety</a>
    <?php if ($myPlatformRole['role'] === 'owner'): ?>
    <a href="admin-admins.php"><i class="fa-solid fa-user-shield"></i>Admins</a>
    <?php endif; ?>
  </nav>
  <div class="bottom">
    <a href="index.php"><i class="fa-solid fa-arrow-right-arrow-left"></i>Switch to traveler view</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account">
      <span><?= st_avatar_inner($currentUser) ?></span>
      <div>
        <b><?= htmlspecialchars($userName) ?></b>
        <small>SafariTrak <?= htmlspecialchars(ucfirst($myPlatformRole['role'])) ?></small>
      </div>
    </div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>BUSINESSES ON SAFARITRAK</label><h1>Organizations</h1></div>
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
  <div><h2>Every registered organization</h2><p>See who owns each organization and how many travelers they manage.</p></div>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="orgSearch" placeholder="Search by organization or owner name...">
    <select id="orgStatusFilter">
      <option value="all">All statuses</option>
      <option value="active">Active</option>
      <option value="suspended">Suspended</option>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="orgsTable">
      <thead>
        <tr><th>Organization</th><th>Owner</th><th>Travelers</th><th>Registered</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (empty($orgs)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px 0">No organizations registered yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($orgs as $o): ?>
        <?php $status = $o['is_suspended'] ? 'suspended' : 'active'; ?>
        <tr data-status="<?= $status ?>">
          <td><b><?= htmlspecialchars($o['name']) ?></b></td>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($o['owner_name'])) ?></span><?= htmlspecialchars($o['owner_name']) ?></div></td>
          <td><?= (int) $o['traveler_count'] ?></td>
          <td><?= (new DateTime($o['created_at']))->format('j M Y') ?></td>
          <td><span class="badge <?= $status === 'active' ? 'completed' : 'cancelled' ?>"><?= ucfirst($status) ?></span></td>
          <td>
            <?php if ($status === 'active'): ?>
            <button type="button" class="table-action danger org-status-btn" data-org-id="<?= (int) $o['id'] ?>" data-action="suspend" style="background:none;border:0;cursor:pointer;font-family:inherit">Suspend</button>
            <?php else: ?>
            <button type="button" class="table-action org-status-btn" data-org-id="<?= (int) $o['id'] ?>" data-action="reactivate" style="background:none;border:0;cursor:pointer;font-family:inherit">Reactivate</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint" id="orgsEmptyState" style="display:none;padding:0 21px 21px;color:var(--muted);font-size:11px">No organizations match your search.</p>
</div>

</div>
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="admin-organizations.js"></script>
</body>
</html>