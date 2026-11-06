<?php
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'group-travel.php') {
    header('Location: groups.php?tab=trips');
    exit;
}

require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();
$myId = $currentUser['id'];

$groupsStmt = $db->prepare(
    'SELECT gj.*, gm.id AS member_row_id, gm.status AS my_status,
            (SELECT COUNT(*) FROM group_members x WHERE x.group_journey_id = gj.id AND x.status = "confirmed") AS confirmed_count
     FROM group_journeys gj
     JOIN group_members gm ON gm.group_journey_id = gj.id AND gm.user_id = ?
     WHERE gm.status IN ("confirmed", "invited")
     ORDER BY FIELD(gj.status, "active", "upcoming", "completed", "cancelled"), gj.departure_at DESC, gj.created_at DESC'
);
$groupsStmt->execute([$myId]);
$myGroups = $groupsStmt->fetchAll();

$pendingInvites = array_filter($myGroups, fn($g) => $g['my_status'] === 'invited');

$activeTrip = null;
foreach ($myGroups as $g) {
    if ($g['status'] === 'active' && $g['my_status'] === 'confirmed') {
        $activeTrip = $g;
        break;
    }
}

$activeMembers = [];
if ($activeTrip) {
    $membersStmt = $db->prepare(
        'SELECT gm.id, gm.user_id, gm.status, gm.last_lat, gm.last_lng, u.full_name, u.avatar_path
         FROM group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_journey_id = ?
         ORDER BY FIELD(gm.status, "confirmed", "invited", "declined")'
    );
    $membersStmt->execute([$activeTrip['id']]);
    $activeMembers = $membersStmt->fetchAll();
}

$contactsStmt = $db->prepare(
    'SELECT tc.id, u.full_name AS display_name
     FROM trusted_contacts tc
     JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status = "confirmed"
     ORDER BY u.full_name ASC'
);
$contactsStmt->execute([$myId]);
$confirmedContacts = $contactsStmt->fetchAll();
$persistentGroupsStmt = $db->prepare(
    'SELECT g.id, g.name,
            (SELECT COUNT(*) FROM persistent_group_members x WHERE x.group_id = g.id) AS member_count
     FROM persistent_groups g
     JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
     WHERE g.status = "active"
     ORDER BY g.name'
);
$persistentGroupsStmt->execute([$myId]);
$persistentGroups = $persistentGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Group Travel</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?php if ($activeTrip): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>TRAVEL TOGETHER</label><h1>Group Travel</h1></div>
  <div class="head-actions">
    <div class="notif-wrap">
      <button type="button" class="notif-bell" id="notifBell"><i class="fa-regular fa-bell"></i><span class="notif-dot" id="notifDot"></span></button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
        <div class="notif-list" id="notifDropdownList">
          <p class="notif-empty">Loading...</p>
        </div>
      </div>
    </div>
    <div class="avatar"><?= st_avatar_inner($currentUser) ?></div>
  </div>
</header>

<div class="content">

<nav class="group-hub-tabs" aria-label="Groups sections">
  <a href="groups.php"><i class="fa-solid fa-people-group" aria-hidden="true"></i>Groups</a>
  <a href="groups.php?tab=trips" class="is-active" aria-current="page"><i class="fa-solid fa-route" aria-hidden="true"></i>Group trips</a>
</nav>
<div class="page-head">
  <div><h2>Travelling as a group</h2><p>Create a group journey, invite the people coming with you, and see everyone who is authorized to be tracked.</p></div>
  <button type="button" class="btn-primary" data-open-modal="createGroupModal"><i class="fa-solid fa-plus"></i>Create group journey</button>
</div>

<?php if (!empty($pendingInvites)): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><div><label>INVITATIONS</label><h3>Group trips you have been invited to</h3></div></div>
  <div class="journey-list">
    <?php foreach ($pendingInvites as $inv): ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon"><i class="fa-solid fa-user-group"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($inv['title']) ?></b><small>To <?= htmlspecialchars($inv['destination_label']) ?></small></div>
      <div class="jmeta" style="display:flex;gap:8px">
        <button type="button" class="btn-ghost respond-btn" data-id="<?= (int) $inv['member_row_id'] ?>" data-action="decline">Decline</button>
        <button type="button" class="btn-primary respond-btn" data-id="<?= (int) $inv['member_row_id'] ?>" data-action="confirm">Accept</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><div><label>YOUR GROUPS</label><h3>Group journeys</h3></div></div>
  <div class="journey-list">

    <?php if (empty($myGroups)): ?>
    <p class="hint" style="padding:20px 21px;color:var(--muted);font-size:11px">You are not part of any group journeys yet.</p>
    <?php endif; ?>

    <?php foreach ($myGroups as $g): ?>
    <?php
      $badgeClass = ['active' => 'active', 'upcoming' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled'][$g['status']];
      $statusLabel = ucfirst($g['status'] === 'invited' ? 'invited' : $g['status']);
      $subline = $g['my_status'] === 'invited'
        ? 'Awaiting your response'
        : (int) $g['confirmed_count'] . ' confirmed member' . ($g['confirmed_count'] == 1 ? '' : 's');
    ?>
    <div class="journey-row" data-open-modal="groupModal<?= (int) $g['id'] ?>">
      <div class="jicon"><i class="fa-solid fa-user-group"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($g['title']) ?></b><small><?= htmlspecialchars($subline) ?></small></div>
      <div class="jmeta"><strong><?= htmlspecialchars($g['destination_label']) ?></strong><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span></div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<section class="lower" style="margin-top:18px">
  <div class="card">
    <div class="card-head"><div><label>THIS TRIP</label><h3><?= $activeTrip ? htmlspecialchars($activeTrip['title']) : 'No active group trip' ?></h3></div><?php if ($activeTrip): ?><span class="status"><?= (int) $activeTrip['confirmed_count'] ?> members</span><?php endif; ?></div>
    <?php if ($activeTrip): ?>
    <div id="groupMap"></div>
    <div class="legend"><span><i class="current"></i>Group members</span><?php if ($activeTrip['destination_lat']): ?><span><i class="destination"></i>Destination</span><?php endif; ?></div>
    <?php else: ?>
    <div class="empty" style="margin:21px"><i class="fa-solid fa-user-group"></i><div><b>No trip in progress</b><p>When one of your group journeys is started, the live map will show up here.</p></div></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><label>MEMBERS</label><h3>Who is authorized</h3></div></div>
    <div class="rows contacts">
      <?php if (empty($activeMembers)): ?>
      <p class="hint" style="padding:16px 21px;color:var(--muted);font-size:11px">No active trip right now.</p>
      <?php endif; ?>
      <?php foreach ($activeMembers as $m): ?>
      <div><span class="person"><?= htmlspecialchars(st_initials($m['full_name'])) ?></span><div><b><?= htmlspecialchars($m['full_name']) ?><?= (int) $m['user_id'] === $myId ? ' (you)' : '' ?></b><small>&#9679; <?= ucfirst($m['status']) ?></small></div><?php if ((int) $m['user_id'] !== $myId): ?><a class="msg-link" href="messages.php?to=<?= (int) $m['user_id'] ?>"><i class="fa-regular fa-message"></i></a><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="createGroupModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Create a group journey</h3><p>Set up a trip and invite the people travelling with you.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div class="form-field" style="margin-bottom:12px"><label>Trip name</label><input type="text" id="groupTitle" placeholder="e.g. Family trip to Diani"></div>
      <div class="form-field" style="margin-bottom:12px"><label>Destination</label><input type="text" id="groupDestination" placeholder="e.g. Diani Beach"></div>
      <div class="form-field" style="margin-bottom:12px"><label>Departure</label><input type="datetime-local" id="groupDeparture"></div>
      <div class="form-field">
        <label>Invite from your trusted contacts</label>
        <?php if (empty($confirmedContacts)): ?>
        <p class="hint">You have no confirmed trusted contacts yet. <a href="trusted-contacts.php" style="color:var(--p);font-weight:700;text-decoration:none">Add one first</a>.</p>
        <?php else: ?>
        <div class="share-contacts">
          <?php foreach ($confirmedContacts as $c): ?>
          <div class="share-contact-row">
            <span class="person"><?= htmlspecialchars(st_initials($c['display_name'])) ?></span>
            <span><?= htmlspecialchars($c['display_name']) ?></span>
            <label class="toggle"><input type="checkbox" class="invite-checkbox" value="<?= (int) $c['id'] ?>"><span></span></label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-field">
        <label>Invite members of your persistent groups</label>
        <?php if (empty($persistentGroups)): ?>
        <p class="hint">You have no persistent groups yet. <a href="groups.php" style="color:var(--p);font-weight:700;text-decoration:none">Create a group</a>.</p>
        <?php else: ?>
        <div class="share-contacts">
          <?php foreach ($persistentGroups as $group): ?>
          <div class="share-contact-row">
            <span class="person"><i class="fa-solid fa-people-group"></i></span>
            <span><?= htmlspecialchars($group['name']) ?> · <?= (int) $group['member_count'] ?> members</span>
            <label class="toggle"><input type="checkbox" class="invite-group-checkbox" value="<?= (int) $group['id'] ?>"><span></span></label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <p class="hint" id="createGroupError" style="color:#c94b4b;margin-top:10px;display:none"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="primary" id="createGroupBtn">Create and invite</button>
    </div>
  </div>
</div>

<?php foreach ($myGroups as $g): ?>
<?php
  $isOrganizer = (int) $g['organizer_id'] === $myId;
  $membersListStmt = $db->prepare(
      'SELECT gm.id, gm.user_id, gm.status, u.full_name
       FROM group_members gm JOIN users u ON u.id = gm.user_id
       WHERE gm.group_journey_id = ? ORDER BY FIELD(gm.status,"confirmed","invited","declined")'
  );
  $membersListStmt->execute([$g['id']]);
  $groupMembersList = $membersListStmt->fetchAll();
?>
<div class="modal-overlay" id="groupModal<?= (int) $g['id'] ?>">
  <div class="modal">
    <div class="modal-head"><div><h3><?= htmlspecialchars($g['title']) ?></h3><p>To <?= htmlspecialchars($g['destination_label']) ?> &middot; <?= ucfirst($g['status']) ?></p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div class="share-contacts">
        <?php foreach ($groupMembersList as $gm): ?>
        <div class="share-contact-row">
          <span class="person"><?= htmlspecialchars(st_initials($gm['full_name'])) ?></span>
          <span><?= htmlspecialchars($gm['full_name']) ?><?= (int) $gm['user_id'] === $myId ? ' (you)' : '' ?> &middot; <?= ucfirst($gm['status']) ?></span>
          <?php if ($isOrganizer && (int) $gm['user_id'] !== $myId && $gm['status'] !== 'declined'): ?>
          <button type="button" class="btn-ghost remove-member-btn" data-member-id="<?= (int) $gm['id'] ?>" style="color:#c94b4b;padding:5px 8px;font-size:9px">Remove</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <?php if ($isOrganizer && $g['status'] === 'upcoming'): ?>
      <button type="button" class="primary group-action-btn" data-action="start" data-group-id="<?= (int) $g['id'] ?>">Start trip</button>
      <button type="button" class="danger group-action-btn" data-action="cancel" data-group-id="<?= (int) $g['id'] ?>">Cancel trip</button>
      <?php elseif ($isOrganizer && $g['status'] === 'active'): ?>
      <button type="button" class="primary group-action-btn" data-action="end" data-group-id="<?= (int) $g['id'] ?>">End trip</button>
      <button type="button" class="danger group-action-btn" data-action="cancel" data-group-id="<?= (int) $g['id'] ?>">Cancel trip</button>
      <?php elseif (!$isOrganizer && $g['my_status'] === 'confirmed' && in_array($g['status'], ['upcoming', 'active'], true)): ?>
      <button type="button" class="danger respond-btn" data-id="<?= (int) $g['member_row_id'] ?>" data-action="leave">Leave group</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
window.ACTIVE_GROUP_ID = <?= $activeTrip ? (int) $activeTrip['id'] : 'null' ?>;
window.ACTIVE_GROUP_DEST_LAT = <?= ($activeTrip && $activeTrip['destination_lat'] !== null) ? (float) $activeTrip['destination_lat'] : 'null' ?>;
window.ACTIVE_GROUP_DEST_LNG = <?= ($activeTrip && $activeTrip['destination_lng'] !== null) ? (float) $activeTrip['destination_lng'] : 'null' ?>;
window.ACTIVE_GROUP_MEMBERS = <?= json_encode(array_map(function ($m) {
    return [
        'user_id' => (int) $m['user_id'],
        'name' => $m['full_name'],
        'status' => $m['status'],
        'lat' => $m['last_lat'] !== null ? (float) $m['last_lat'] : null,
        'lng' => $m['last_lng'] !== null ? (float) $m['last_lng'] : null,
    ];
}, $activeMembers)) ?>;
window.MY_USER_ID = <?= (int) $myId ?>;
</script>

<?php if ($activeTrip): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="group-travel.js"></script>
</body>
</html>
