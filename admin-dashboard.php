<?php

require_once __DIR__ . '/backend/includes/auth-guard.php';

st_require_platform_admin($currentUser);

$myPlatformRole = null;
$platformAdminExists = true;

$paStmt = $db->prepare('SELECT role FROM platform_admins WHERE user_id = ? LIMIT 1');
$paStmt->execute([$currentUser['id']]);
$paData = $paStmt->fetch();

if ($paData) {
    $myPlatformRole = $paData;
} else {
    $countStmt = $db->query('SELECT COUNT(*) FROM platform_admins');
    $platformAdminExists = ((int) $countStmt->fetchColumn()) > 0;
}

if ($myPlatformRole) {
    $totalUsersStmt = $db->query('SELECT COUNT(*) FROM users');
    $totalUsers = (int) $totalUsersStmt->fetchColumn();

    $newUsersStmt = $db->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
    $newUsersThisMonth = (int) $newUsersStmt->fetchColumn();

    $totalOrgsStmt = $db->query('SELECT COUNT(*) FROM organizations');
    $totalOrgs = (int) $totalOrgsStmt->fetchColumn();

    $activeJourneysStmt = $db->query('SELECT COUNT(*) FROM journeys WHERE status = "active"');
    $activeJourneysNow = (int) $activeJourneysStmt->fetchColumn();

    $activeSosStmt = $db->query(
        'SELECT sa.id, sa.created_at, sa.lat, sa.lng, u.id AS user_id, u.full_name, u.phone
         FROM sos_alerts sa JOIN users u ON u.id = sa.user_id
         WHERE sa.status = "active"
         ORDER BY sa.created_at ASC'
    );
    $activeSosAlerts = $activeSosStmt->fetchAll();

    $suspendedUsersStmt = $db->query('SELECT COUNT(*) FROM users WHERE is_suspended = 1');
    $suspendedUsers = (int) $suspendedUsersStmt->fetchColumn();

    $recentUsersStmt = $db->query('SELECT id, full_name, username, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    $recentUsers = $recentUsersStmt->fetchAll();

    $recentOrgsStmt = $db->query(
        'SELECT o.id, o.name, o.created_at, u.full_name AS owner_name
         FROM organizations o
         JOIN organization_admins oa ON oa.organization_id = o.id AND oa.role = "owner"
         JOIN users u ON u.id = oa.user_id
         ORDER BY o.created_at DESC LIMIT 5'
    );
    $recentOrgs = $recentOrgsStmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Platform Admin</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar platform-sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="platform-badge">PLATFORM ADMIN</span></div></div>
  <nav>
    <a class="active" href="admin-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <?php if ($myPlatformRole): ?>
    <a href="admin-users.php"><i class="fa-solid fa-users"></i>Users</a>
    <a href="admin-organizations.php"><i class="fa-solid fa-building"></i>Organizations</a>
    <a href="admin-safety.php"><i class="fa-solid fa-triangle-exclamation"></i>Safety<?= !empty($activeSosAlerts) ? ' <em>' . count($activeSosAlerts) . '</em>' : '' ?></a>
    <?php if (($myPlatformRole['role'] ?? '') === 'owner'): ?>
    <a href="admin-admins.php"><i class="fa-solid fa-user-shield"></i>Admins</a>
    <?php endif; ?>
    <?php endif; ?>
  </nav>
  <div class="bottom">
    <a href="index.php"><i class="fa-solid fa-arrow-right-arrow-left"></i>Switch to traveler view</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account">
      <span><?= st_avatar_inner($currentUser) ?></span>
      <div>
        <b><?= htmlspecialchars($userName) ?></b>
        <small>SafariTrak <?= $myPlatformRole ? htmlspecialchars(ucfirst($myPlatformRole['role'])) : 'Staff' ?></small>
      </div>
    </div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>PLATFORM OVERVIEW</label><h1>SafariTrak Admin</h1></div>
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

<?php if (!$myPlatformRole && $platformAdminExists): ?>

<div class="card" style="max-width:480px;margin:30px auto">
  <div class="card-head"><div><label>ACCESS RESTRICTED</label><h3>You are not a platform admin</h3></div></div>
  <div style="padding:0 21px 21px">
    <p style="font-size:12px;color:var(--muted);line-height:1.7">This area is for SafariTrak's own team. If you believe you should have access, ask an existing platform admin to add you from the Admins page.</p>
  </div>
</div>

<?php elseif (!$myPlatformRole && !$platformAdminExists): ?>

<div class="card" style="max-width:480px;margin:30px auto">
  <div class="card-head"><div><label>ONE-TIME SETUP</label><h3>Claim SafariTrak ownership</h3></div></div>
  <div style="padding:0 21px 21px">
    <p style="font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:16px">No one manages SafariTrak at the platform level yet. Since this is the first time this has happened, you can claim ownership now. This gives you oversight of every user, organization and safety alert on the platform.</p>
    <p class="hint" id="claimError" style="color:#c94b4b;margin-bottom:10px;display:none"></p>
    <button type="button" class="btn-primary" id="claimOwnershipBtn" style="width:100%;justify-content:center">Claim ownership</button>
  </div>
</div>

<?php else: ?>

<div class="page-head">
  <div><h2>How SafariTrak is doing</h2><p>Everything happening across every traveler and organization.</p></div>
</div>

<?php if (!empty($activeSosAlerts)): ?>
<div class="card" style="margin-bottom:18px;border:1px solid #f3c8c8">
  <div class="card-head"><div><label style="color:#c94b4b">URGENT</label><h3><?= count($activeSosAlerts) ?> active SOS alert<?= count($activeSosAlerts) == 1 ? '' : 's' ?></h3></div><a href="admin-safety.php">View all</a></div>
  <div class="journey-list">
    <?php foreach (array_slice($activeSosAlerts, 0, 4) as $sa): ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon" style="background:#fdecec;color:#c94b4b"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($sa['full_name']) ?></b><small><?= htmlspecialchars($sa['phone']) ?> &middot; <?= (new DateTime($sa['created_at']))->format('g:i A, j M') ?></small></div>
      <div class="jmeta"><a href="admin-safety.php" class="btn-primary">Respond</a></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><label>TOTAL USERS</label><strong><?= $totalUsers ?></strong><small><?= $newUsersThisMonth ?> new this month</small></div>
  <div class="stat-card"><label>ORGANIZATIONS</label><strong><?= $totalOrgs ?></strong><small>Registered</small></div>
  <div class="stat-card"><label>ACTIVE JOURNEYS</label><strong><?= $activeJourneysNow ?></strong><small>Right now</small></div>
  <div class="stat-card"><label>SUSPENDED ACCOUNTS</label><strong><?= $suspendedUsers ?></strong><small>Users</small></div>
</div>

<section class="lower">
  <div class="card">
    <div class="card-head"><div><label>NEWEST USERS</label><h3>Recent signups</h3></div><a href="admin-users.php">View all</a></div>
    <div class="rows">
      <?php if (empty($recentUsers)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No users yet.</p>
      <?php endif; ?>
      <?php foreach ($recentUsers as $ru): ?>
      <div><i class="fa-solid fa-user"></i><div><b><?= htmlspecialchars($ru['full_name']) ?></b><small>@<?= htmlspecialchars($ru['username']) ?> &middot; <?= (new DateTime($ru['created_at']))->format('j M') ?></small></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><div><label>NEWEST ORGANIZATIONS</label><h3>Recently registered</h3></div><a href="admin-organizations.php">View all</a></div>
    <div class="rows">
      <?php if (empty($recentOrgs)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No organizations yet.</p>
      <?php endif; ?>
      <?php foreach ($recentOrgs as $ro): ?>
      <div><i class="fa-solid fa-building"></i><div><b><?= htmlspecialchars($ro['name']) ?></b><small>Owned by <?= htmlspecialchars($ro['owner_name']) ?> &middot; <?= (new DateTime($ro['created_at']))->format('j M') ?></small></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php endif; ?>

</div>
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<?php if (!$myPlatformRole && !$platformAdminExists): ?>
<script src="platform-onboarding.js"></script>
<?php endif; ?>
</body>
</html>