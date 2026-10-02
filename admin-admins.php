<?php
require __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole) {
    header('Location: admin-dashboard.php');
    exit;
}

$db = safaritrak_db();

$adminsStmt = $db->query(
    'SELECT pa.id, pa.role, pa.created_at, u.id AS user_id, u.full_name, u.username, u.email
     FROM platform_admins pa JOIN users u ON u.id = pa.user_id
     ORDER BY FIELD(pa.role, "owner", "staff"), pa.created_at ASC'
);
$admins = $adminsStmt->fetchAll();

$isOwner = $myPlatformRole['role'] === 'owner';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Platform Admins</title>
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
    <a href="admin-safety.php"><i class="fa-solid fa-triangle-exclamation"></i>Safety</a>
    <a class="active" href="admin-admins.php"><i class="fa-solid fa-user-shield"></i>Admins</a>
  </nav>
  <div class="bottom">
    <a href="index.php"><i class="fa-solid fa-arrow-right-arrow-left"></i>Switch to traveler view</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account"><span>S</span><div><b><?= htmlspecialchars($userName) ?></b><small>SafariTrak <?= htmlspecialchars(ucfirst($myPlatformRole['role'])) ?></small></div></div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>WHO CAN MANAGE SAFARITRAK</label><h1>Platform Admins</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar">S</div></div>
</header>

<div class="content">

<div class="page-head">
  <div><h2>People with platform-wide access</h2><p>Owners can add or remove staff admins. Staff admins can manage users, organizations and safety alerts.</p></div>
  <?php if ($isOwner): ?>
  <button type="button" class="btn-primary" data-open-modal="addAdminModal"><i class="fa-solid fa-user-plus"></i>Add admin</button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Admin</th><th>Username</th><th>Email</th><th>Role</th><th>Added</th><?php if ($isOwner): ?><th>Action</th><?php endif; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($a['full_name'])) ?></span><?= htmlspecialchars($a['full_name']) ?></div></td>
          <td>@<?= htmlspecialchars($a['username']) ?></td>
          <td><?= htmlspecialchars($a['email']) ?></td>
          <td><span class="badge <?= $a['role'] === 'owner' ? 'active' : 'completed' ?>"><?= ucfirst($a['role']) ?></span></td>
          <td><?= (new DateTime($a['created_at']))->format('j M Y') ?></td>
          <?php if ($isOwner): ?>
          <td>
            <?php if ($a['role'] !== 'owner' && (int) $a['user_id'] !== $currentUser['id']): ?>
            <button type="button" class="table-action danger remove-admin-btn" data-admin-id="<?= (int) $a['id'] ?>" style="background:none;border:0;cursor:pointer;font-family:inherit">Remove</button>
            <?php else: ?>
            <span style="color:var(--muted);font-size:10px">-</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<?php if ($isOwner): ?>
<div class="modal-overlay" id="addAdminModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Add a platform admin</h3><p>They must already have a SafariTrak account.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div class="form-field"><label>Username, email or phone</label><input type="text" id="addAdminIdentifier" placeholder="e.g. jamesm"></div>
      <p class="hint" id="addAdminError" style="color:#c94b4b;margin-top:10px;display:none"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="primary" id="addAdminBtn">Add admin</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="dashboard.js"></script>
<?php if ($isOwner): ?>
<script src="admin-admins.js"></script>
<?php endif; ?>
</body>
</html>
