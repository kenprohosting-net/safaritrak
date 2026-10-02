<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();
$notifStmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$notifStmt->execute([$currentUser['id']]);
$allNotifications = $notifStmt->fetchAll();

$groups = ['Today' => [], 'Yesterday' => [], 'This week' => [], 'Earlier' => []];
$todayStart = strtotime('today');
$yesterdayStart = strtotime('yesterday');
$weekStart = strtotime('-7 days');

foreach ($allNotifications as &$n) {
    $n['group_invite_id'] = null;
    $n['group_invite_status'] = null;
    $n['group_inviter_name'] = null;

    if ($n['type'] === 'group_journey_invite') {
        $groupJourneyId = isset($n['related_journey_id']) ? (int) $n['related_journey_id'] : 0;

        if ($groupJourneyId > 0) {
            $inviteStmt = $db->prepare(
                'SELECT gji.id, gji.status, gji.inviter_id, u.full_name AS inviter_name
                 FROM group_journey_invite gji
                 LEFT JOIN users u ON u.id = gji.inviter_id
                 WHERE gji.group_journey_id = ? AND gji.invitee_id = ? AND gji.status = "pending"
                 ORDER BY gji.invited_at DESC LIMIT 1'
            );
            $inviteStmt->execute([$groupJourneyId, $currentUser['id']]);
            $invite = $inviteStmt->fetch();

            if ($invite) {
                $n['group_invite_id'] = (int) $invite['id'];
                $n['group_invite_status'] = $invite['status'];
                $n['group_inviter_name'] = $invite['inviter_name'];
            }
        }
    }

    $ts = strtotime($n['created_at']);
    if ($ts >= $todayStart) {
        $groups['Today'][] = $n;
    } elseif ($ts >= $yesterdayStart) {
        $groups['Yesterday'][] = $n;
    } elseif ($ts >= $weekStart) {
        $groups['This week'][] = $n;
    } else {
        $groups['Earlier'][] = $n;
    }
}
unset($n);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Notifications</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
.group-invite-actions { display: flex; gap: 8px; margin-top: 10px; }
.group-invite-actions button { border: none; border-radius: 8px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; }
.group-invite-actions .accept-invite { background: var(--p); color: #fff; }
.group-invite-actions .decline-invite { background: #c94b4b; color: #fff; }
.group-invite-actions button:disabled { opacity: .6; cursor: not-allowed; }
.group-invite-status { margin-top: 8px; font-size: 12px; font-weight: 600; }
.group-invite-status.success { color: var(--p); }
.group-invite-status.error { color: #c94b4b; }
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small></div></div>
  <nav>
    <a href="index.php"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
    <a href="my-journeys.php"><i class="fa-solid fa-map-location-dot"></i>My Journeys</a>
    <a href="live-tracking.php"><i class="fa-solid fa-location-crosshairs"></i>Live Tracking</a>
    <a href="places.php"><i class="fa-solid fa-map-pin"></i>Places</a>
    <a href="messages.php"><i class="fa-regular fa-message"></i>Messages<?= $unreadConversationCount > 0 ? " <em>" . $unreadConversationCount . "</em>" : "" ?></a>
    <a href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i>Trusted Contacts</a>
    <a href="safety.php"><i class="fa-solid fa-shield-halved"></i>Safety</a>
  </nav>
  <div class="bottom">
    <a href="settings.php"><i class="fa-solid fa-gear"></i>Settings</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account"><span><?= st_avatar_inner($currentUser) ?></span><div><b><?= htmlspecialchars($userName) ?></b><small>Traveler</small></div></div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>UPDATES</label><h1>Notifications</h1></div>
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

<div class="page-head">
  <div><h2>Everything that needs your attention</h2><p>Journey updates, messages, group invitations, and safety alerts all in one place.</p></div>
  <button type="button" class="btn-ghost" id="markAllRead"><i class="fa-solid fa-check-double"></i>Mark all as read</button>
</div>

<div class="tabs" id="notifTabs">
  <button type="button" class="tab active" data-filter="all">All</button>
  <button type="button" class="tab" data-filter="unread">Unread</button>
  <button type="button" class="tab" data-filter="safety">Safety</button>
  <button type="button" class="tab" data-filter="messages">Messages</button>
</div>

<div class="card">
  <?php if (empty($allNotifications)): ?>
  <p class="hint" style="padding:24px 21px;color:var(--muted);font-size:11px">Nothing here yet. Journey updates, messages, group invitations and safety alerts will show up here.</p>
  <?php endif; ?>

  <?php foreach ($groups as $label => $items): ?>
  <?php if (empty($items)) continue; ?>
  <div class="notif-date-label"><?= strtoupper($label) ?></div>
  <div class="notif-page-list" data-group="<?= strtolower(str_replace(' ', '-', $label)) ?>">
    <?php foreach ($items as $n): ?>
    <?php
      $isGroupInvite = $n['type'] === 'group_journey_invite';
      $link = $isGroupInvite ? null : st_notif_link($n['type'], $n['related_journey_id'], $n['related_user_id']);
      $tag = $link ? 'a' : 'div';
      $sosClass = $n['type'] === 'sos_alert' ? ' sos' : '';
      $unreadClass = $n['is_read'] ? '' : ' unread';
    ?>
    <<?= $tag ?> <?= $link ? 'href="' . htmlspecialchars($link) . '"' : '' ?> class="notif-row<?= $unreadClass . $sosClass ?>" data-type="<?= st_notif_category($n['type']) ?>" data-id="<?= (int) $n['id'] ?>" style="text-decoration:none;color:inherit">
      <div class="nicon"><i class="fa-solid <?= $isGroupInvite ? 'fa-user-group' : st_notif_icon($n['type']) ?>"></i></div>
      <div class="ninfo">
        <b><?= htmlspecialchars($n['title']) ?></b>
        <?php if ($n['body']): ?><p><?= htmlspecialchars($n['body']) ?></p><?php endif; ?>

        <?php if ($isGroupInvite && $n['group_invite_id']): ?>
          <?php if ($n['group_inviter_name']): ?>
          <p><strong><?= htmlspecialchars($n['group_inviter_name']) ?></strong> invited you to join a group journey.</p>
          <?php endif; ?>
          <div class="group-invite-actions" data-invite="<?= (int) $n['group_invite_id'] ?>">
            <button type="button" class="accept-invite" data-invite-id="<?= (int) $n['group_invite_id'] ?>" data-action="accept"><i class="fa-solid fa-check"></i> Accept</button>
            <button type="button" class="decline-invite" data-invite-id="<?= (int) $n['group_invite_id'] ?>" data-action="decline"><i class="fa-solid fa-xmark"></i> Decline</button>
          </div>
          <div class="group-invite-status" id="invite-status-<?= (int) $n['group_invite_id'] ?>"></div>
        <?php elseif ($isGroupInvite): ?>
          <p>This invitation is no longer pending.</p>
        <?php endif; ?>

        <small><?= (new DateTime($n['created_at']))->format('g:i A, j M') ?></small>
      </div>
      <?php if (!$n['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
    </<?= $tag ?>>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>

<p class="notif-empty" id="notifEmptyState" style="display:none">Nothing here yet.</p>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<script src="notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function respondToInvitation(button) {
    const inviteId = button.dataset.inviteId;
    const action = button.dataset.action;
    const container = button.closest('.group-invite-actions');
    const statusBox = document.getElementById('invite-status-' + inviteId);
    const buttons = container ? container.querySelectorAll('button') : [];

    buttons.forEach(btn => { btn.disabled = true; });
    statusBox.textContent = 'Processing...';
    statusBox.className = 'group-invite-status';

    fetch('backend/api/group/respond.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ invite_id: parseInt(inviteId, 10), action })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          statusBox.textContent = action === 'accept'
            ? 'You joined the group journey successfully.'
            : 'Invitation declined.';
          statusBox.className = 'group-invite-status success';
          if (container) container.style.display = 'none';

          const row = container ? container.closest('.notif-row') : null;
          if (row) {
            row.classList.remove('unread');
            row.querySelector('.unread-dot')?.remove();
          }
        } else {
          statusBox.textContent = data.message || 'Unable to process invitation.';
          statusBox.className = 'group-invite-status error';
          buttons.forEach(btn => { btn.disabled = false; });
        }
      })
      .catch(() => {
        statusBox.textContent = 'A connection error occurred. Please try again.';
        statusBox.className = 'group-invite-status error';
        buttons.forEach(btn => { btn.disabled = false; });
      });
  }

  document.querySelectorAll('.accept-invite, .decline-invite').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      respondToInvitation(button);
    });
  });
});
</script>
</body>
</html>