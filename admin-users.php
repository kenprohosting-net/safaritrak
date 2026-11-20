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

$platformRoles = [];
foreach ($db->query('SELECT user_id, role FROM platform_admins')->fetchAll() as $role) {
    $platformRoles[(int) $role['user_id']] = $role['role'];
}

$organizationRoles = [];
$organizationRolesStmt = $db->query(
    'SELECT oa.user_id, oa.role, o.name AS organization_name, o.is_suspended
     FROM organization_admins oa
     JOIN organizations o ON o.id = oa.organization_id
     ORDER BY o.name, oa.role'
);
foreach ($organizationRolesStmt->fetchAll() as $role) {
    $organizationRoles[(int) $role['user_id']][] = $role;
}

$schoolRoles = [];
$schoolRolesStmt = $db->query(
    'SELECT sv.user_id, sv.role, sv.verification_status, o.name AS school_name
     FROM school_verifications sv
     JOIN organizations o ON o.id = sv.school_id
     ORDER BY o.name, sv.role'
);
foreach ($schoolRolesStmt->fetchAll() as $role) {
    $schoolRoles[(int) $role['user_id']][] = $role;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Users</title>
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
  <div><label>EVERYONE ON SAFARITRAK</label><h1>Users</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar">S</div></div>
</header>

<div class="content">

<div class="page-head">
  <div><h2>Registered accounts</h2><p>Review account roles and status. Organization and school access remains tied to its approved membership and verification workflow.</p></div>
  <a class="btn-primary" href="admin-admins.php"><i class="fa-solid fa-user-shield"></i>Platform admins</a>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="userSearch" placeholder="Search by name, username, phone or email...">
    <select id="userRoleFilter" aria-label="Filter by account role">
      <option value="all">All roles</option>
      <option value="traveler">Traveler only</option>
      <option value="platform_admin">Platform admin</option>
      <option value="organization_admin">Organization admin</option>
      <option value="school_admin">School admin</option>
      <option value="verified_parent">Verified parent</option>
    </select>
    <select id="userStatusFilter">
      <option value="all">All statuses</option>
      <option value="active">Active</option>
      <option value="suspended">Suspended</option>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="usersTable">
      <thead>
        <tr><th>User</th><th>Phone</th><th>Email</th><th>Roles &amp; membership</th><th>Journeys</th><th>Joined</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <?php $status = $u['is_suspended'] ? 'suspended' : 'active'; ?>
        <?php
          $userId = (int) $u['id'];
          $roleKeys = [];
          if (isset($platformRoles[$userId])) {
              $roleKeys[] = 'platform_admin';
          }
          if (!empty($organizationRoles[$userId])) {
              $roleKeys[] = 'organization_admin';
          }
          foreach ($schoolRoles[$userId] ?? [] as $schoolRole) {
              if ($schoolRole['verification_status'] === 'verified') {
                  $roleKeys[] = $schoolRole['role'] === 'school_admin' ? 'school_admin' : 'verified_parent';
              }
          }
          $roleKeys = array_values(array_unique($roleKeys));
          $filterRoles = $roleKeys ?: ['traveler'];
        ?>
        <tr data-status="<?= $status ?>" data-roles="<?= htmlspecialchars(implode(' ', $filterRoles), ENT_QUOTES, 'UTF-8') ?>">
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($u['full_name'])) ?></span><?= htmlspecialchars($u['full_name']) ?><?= $u['is_platform_admin'] ? ' <i class="fa-solid fa-shield-halved" style="color:var(--accent-primary);font-size:9px" title="Platform admin"></i>' : '' ?></div></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php if (isset($platformRoles[$userId])): ?>
              <span class="badge active">Platform <?= htmlspecialchars(ucfirst($platformRoles[$userId])) ?></span>
            <?php endif; ?>
            <?php foreach ($organizationRoles[$userId] ?? [] as $organizationRole): ?>
              <span class="badge completed">Organization <?= htmlspecialchars(ucfirst($organizationRole['role'])) ?></span>
              <br><small><?= htmlspecialchars($organizationRole['organization_name']) ?><?= (int) $organizationRole['is_suspended'] === 1 ? ' · suspended' : '' ?></small>
            <?php endforeach; ?>
            <?php foreach ($schoolRoles[$userId] ?? [] as $schoolRole): ?>
              <?php $schoolBadge = $schoolRole['verification_status'] === 'verified' ? 'completed' : ($schoolRole['verification_status'] === 'pending' ? 'active' : 'cancelled'); ?>
              <span class="badge <?= $schoolBadge ?>"><?= $schoolRole['role'] === 'school_admin' ? 'School admin' : 'Verified parent' ?> · <?= htmlspecialchars(ucfirst($schoolRole['verification_status'])) ?></span>
              <br><small><?= htmlspecialchars($schoolRole['school_name']) ?></small>
            <?php endforeach; ?>
            <?php if (!$roleKeys): ?><span class="badge">Traveler</span><?php endif; ?>
          </td>
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
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="admin-users.js"></script>
</body>
</html>
