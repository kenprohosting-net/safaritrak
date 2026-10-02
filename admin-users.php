<?php
require __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole) {
    header('Location: admin-dashboard.php');
    exit;
}

$db = safaritrak_db();

$usersStmt = $db->query(
    'SELECT u.id, u.full_name, u.username, u.phone, u.email, u.is_suspended, u.created_at,
            (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id) AS journey_count,
            (SELECT COUNT(*) FROM journeys j WHERE j.user_id = u.id AND j.status = "active") AS has_active_journey,
            (SELECT COUNT(*) FROM platform_admins pa WHERE pa.user_id = u.id) AS is_platform_admin
     FROM users u
     ORDER BY u.created_at DESC'
);
$users = $usersStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Users</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar platform-sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="platform-badge">PLATFORM ADMIN</span></div></div>
  <nav>
    <a href="admin-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <a class="active" href="admin-users.php"><i class="fa-solid fa-users"></i>Users</a>
    <a href="admin-organizations.php"><i class="fa-solid fa-building"></i>Organizations</a>
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
  <div><label>EVERYONE ON SAFARITRAK</label><h1>Users</h1></div>
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
  <div><h2>Every traveler account</h2><p>Search, review, and suspend accounts when needed.</p></div>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="userSearch" placeholder="Search by name, username, phone or email...">
    <select id="userStatusFilter">
      <option value="all">All statuses</option>
      <option value="active">Active</option>
      <option value="suspended">Suspended</option>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="usersTable">
      <thead>
        <tr><th>User</th><th>Phone</th><th>Email</th><th>Journeys</th><th>Joined</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <?php $status = $u['is_suspended'] ? 'suspended' : 'active'; ?>
        <tr data-status="<?= $status ?>">
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($u['full_name'])) ?></span><?= htmlspecialchars($u['full_name']) ?><?= $u['is_platform_admin'] ? ' <i class="fa-solid fa-shield-halved" style="color:#7c1d3f;font-size:9px" title="Platform admin"></i>' : '' ?></div></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= (int) $u['journey_count'] ?><?= $u['has_active_journey'] ? ' <span class="badge active">Active now</span>' : '' ?></td>
          <td><?= (new DateTime($u['created_at']))->format('j M Y') ?></td>
          <td><span class="badge <?= $status === 'active' ? 'completed' : 'cancelled' ?>"><?= ucfirst($status) ?></span></td>
          <td>
            <?php if ((int) $u['id'] !== $currentUser['id']): ?>
            <?php if ($status === 'active'): ?>
            <button type="button" class="table-action danger status-btn" data-user-id="<?= (int) $u['id'] ?>" data-action="suspend" style="background:none;border:0;cursor:pointer;font-family:inherit">Suspend</button>
            <?php else: ?>
            <button type="button" class="table-action status-btn" data-user-id="<?= (int) $u['id'] ?>" data-action="reactivate" style="background:none;border:0;cursor:pointer;font-family:inherit">Reactivate</button>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--muted);font-size:10px">You</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint" id="usersEmptyState" style="display:none;padding:0 21px 21px;color:var(--muted);font-size:11px">No users match your search.</p>
</div>

</div>
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="admin-users.js"></script>
</body>
</html>