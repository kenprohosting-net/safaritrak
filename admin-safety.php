<?php
require __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole) {
    header('Location: admin-dashboard.php');
    exit;
}

$db = safaritrak_db();

$activeStmt = $db->query(
    'SELECT sa.id, sa.created_at, sa.lat, sa.lng, sa.journey_id, u.id AS user_id, u.full_name, u.phone
     FROM sos_alerts sa JOIN users u ON u.id = sa.user_id
     WHERE sa.status = "active"
     ORDER BY sa.created_at ASC'
);
$activeAlerts = $activeStmt->fetchAll();

$resolvedStmt = $db->query(
    'SELECT sa.id, sa.created_at, sa.resolved_at, u.full_name, u.phone, r.full_name AS resolved_by_name
     FROM sos_alerts sa
     JOIN users u ON u.id = sa.user_id
     LEFT JOIN users r ON r.id = sa.resolved_by
     WHERE sa.status = "resolved"
     ORDER BY sa.resolved_at DESC
     LIMIT 20'
);
$resolvedAlerts = $resolvedStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Safety Oversight</title>
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
    <a href="admin-organizations.php"><i class="fa-solid fa-building"></i>Organizations</a>
    <a class="active" href="admin-safety.php"><i class="fa-solid fa-triangle-exclamation"></i>Safety<?= !empty($activeAlerts) ? ' <em>' . count($activeAlerts) . '</em>' : '' ?></a>
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
  <div><label>PLATFORM-WIDE</label><h1>Safety Oversight</h1></div>
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
  <div><h2>Every SOS alert on SafariTrak</h2><p>Active alerts need a response. Resolve one once you have confirmed the traveler is safe.</p></div>
</div>

<div class="card" style="<?= !empty($activeAlerts) ? 'border:1px solid #f3c8c8' : '' ?>">
  <div class="card-head"><div><label style="<?= !empty($activeAlerts) ? 'color:#c94b4b' : '' ?>">ACTIVE NOW</label><h3><?= count($activeAlerts) ?> alert<?= count($activeAlerts) == 1 ? '' : 's' ?> awaiting response</h3></div></div>
  <div class="journey-list">
    <?php if (empty($activeAlerts)): ?>
    <p class="hint" style="padding:20px 21px;color:var(--muted);font-size:11px">No active SOS alerts right now.</p>
    <?php endif; ?>
    <?php foreach ($activeAlerts as $a): ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon" style="background:#fdecec;color:#c94b4b"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="jinfo">
        <b><?= htmlspecialchars($a['full_name']) ?></b>
        <small><?= htmlspecialchars($a['phone']) ?> &middot; <?= (new DateTime($a['created_at']))->format('g:i A, j M') ?><?= $a['lat'] !== null ? ' &middot; Location available' : ' &middot; No location shared' ?></small>
      </div>
      <div class="jmeta" style="display:flex;gap:8px">
        <?php if ($a['journey_id']): ?>
        <a href="watch-journey.php?id=<?= (int) $a['journey_id'] ?>" class="btn-ghost">View journey</a>
        <?php endif; ?>
        <button type="button" class="btn-primary resolve-sos-btn" data-alert-id="<?= (int) $a['id'] ?>">Mark resolved</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-head"><div><label>HISTORY</label><h3>Recently resolved</h3></div></div>
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Traveler</th><th>Phone</th><th>Triggered</th><th>Resolved</th><th>Resolved by</th></tr>
      </thead>
      <tbody>
        <?php if (empty($resolvedAlerts)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px 0">No resolved alerts yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($resolvedAlerts as $r): ?>
        <tr>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($r['full_name'])) ?></span><?= htmlspecialchars($r['full_name']) ?></div></td>
          <td><?= htmlspecialchars($r['phone']) ?></td>
          <td><?= (new DateTime($r['created_at']))->format('g:i A, j M') ?></td>
          <td><?= $r['resolved_at'] ? (new DateTime($r['resolved_at']))->format('g:i A, j M') : '-' ?></td>
          <td><?= $r['resolved_by_name'] ? htmlspecialchars($r['resolved_by_name']) : 'Traveler themselves' ?></td>
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
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="admin-safety.js"></script>
</body>
</html>