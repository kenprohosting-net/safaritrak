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

foreach ($allNotifications as $n) {
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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Notifications</title>
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
  <div><h2>Everything that needs your attention</h2><p>Journey updates, messages, and safety alerts all in one place.</p></div>
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
  <p class="hint" style="padding:24px 21px;color:var(--muted);font-size:11px">Nothing here yet. Journey updates, messages and safety alerts will show up here.</p>
  <?php endif; ?>

  <?php foreach ($groups as $label => $items): ?>
  <?php if (empty($items)) continue; ?>
  <div class="notif-date-label"><?= strtoupper($label) ?></div>
  <div class="notif-page-list" data-group="<?= strtolower(str_replace(' ', '-', $label)) ?>">
    <?php foreach ($items as $n): ?>
    <?php
      $link = st_notif_link($n['type'], $n['related_journey_id'], $n['related_user_id']);
      $tag = $link ? 'a' : 'div';
      $sosClass = $n['type'] === 'sos_alert' ? ' sos' : '';
      $unreadClass = $n['is_read'] ? '' : ' unread';
    ?>
    <<?= $tag ?> <?= $link ? 'href="' . htmlspecialchars($link) . '"' : '' ?> class="notif-row<?= $unreadClass . $sosClass ?>" data-type="<?= st_notif_category($n['type']) ?>" data-id="<?= (int) $n['id'] ?>" style="text-decoration:none;color:inherit">
      <div class="nicon"><i class="fa-solid <?= st_notif_icon($n['type']) ?>"></i></div>
      <div class="ninfo"><b><?= htmlspecialchars($n['title']) ?></b><?php if ($n['body']): ?><p><?= htmlspecialchars($n['body']) ?></p><?php endif; ?><small><?= (new DateTime($n['created_at']))->format('g:i A, j M') ?></small></div>
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
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="notifications.js"></script>
</body>
</html>
