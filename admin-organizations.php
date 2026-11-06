<?php
require __DIR__ . '/backend/includes/platform-guard.php';

if (!$myPlatformRole) {
    header('Location: admin-dashboard.php');
    exit;
}

$db = safaritrak_db();

$orgsStmt = $db->query(
    'SELECT o.id, o.name, o.is_suspended, o.created_at, u.full_name AS owner_name, u.phone AS owner_phone,
            COALESCE(p.account_type, "school") AS account_type,
            COALESCE(p.review_status, "approved") AS review_status,
            (SELECT COUNT(*) FROM organization_travelers ot WHERE ot.organization_id = o.id AND ot.status = "active") AS traveler_count
     FROM organizations o
     JOIN organization_admins oa ON oa.organization_id = o.id AND oa.role = "owner"
     JOIN users u ON u.id = oa.user_id
     LEFT JOIN organization_profiles p ON p.organization_id = o.id
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
  <div><label>BUSINESSES ON SAFARITRAK</label><h1>Organizations</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar">S</div></div>
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
      <option value="approved">Approved</option>
      <option value="pending">Pending review</option>
      <option value="rejected">Rejected</option>
      <option value="suspended">Suspended</option>
    </select>
  </div>
  <div class="data-table-wrap">
    <table class="data-table" id="orgsTable">
      <thead>
        <tr><th>Organization</th><th>Type</th><th>Owner</th><th>Travelers</th><th>Registered</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (empty($orgs)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px 0">No organizations registered yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($orgs as $o): ?>
        <?php $status = $o['is_suspended'] ? 'suspended' : $o['review_status']; ?>
        <tr data-status="<?= $status ?>">
          <td><b><?= htmlspecialchars($o['name']) ?></b></td>
          <td><?= $o['account_type'] === 'car_hire' ? 'Car hire' : 'School' ?></td>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($o['owner_name'])) ?></span><?= htmlspecialchars($o['owner_name']) ?></div></td>
          <td><?= (int) $o['traveler_count'] ?></td>
          <td><?= (new DateTime($o['created_at']))->format('j M Y') ?></td>
          <td><span class="badge <?= $status === 'approved' ? 'completed' : ($status === 'pending' ? 'active' : 'cancelled') ?>"><?= $status === 'approved' ? 'Approved' : ucfirst($status) ?></span></td>
          <td>
            <?php if ($status === 'pending' || $status === 'rejected'): ?>
            <button type="button" class="table-action org-status-btn" data-org-id="<?= (int) $o['id'] ?>" data-action="approve" style="background:none;border:0;cursor:pointer;font-family:inherit">Approve</button>
            <?php if ($status === 'pending'): ?><button type="button" class="table-action danger org-status-btn" data-org-id="<?= (int) $o['id'] ?>" data-action="reject" style="background:none;border:0;cursor:pointer;font-family:inherit">Reject</button><?php endif; ?>
            <?php elseif ($status === 'approved'): ?>
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
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="admin-organizations.js"></script>
</body>
</html>
