<?php

require_once __DIR__ . '/backend/includes/auth-guard.php';

st_require_org_admin($currentUser);

$myOrg = null;
$myOrgSuspended = false;

if (!empty($currentUser['organization_id'])) {
    $orgStmt = $db->prepare('SELECT * FROM organizations WHERE id = ? LIMIT 1');
    $orgStmt->execute([$currentUser['organization_id']]);
    $myOrg = $orgStmt->fetch();

    if ($myOrg && (int) ($myOrg['is_suspended'] ?? 0) === 1) {
        $myOrgSuspended = true;
    }
}

if ($myOrg && !$myOrgSuspended) {
    $orgId = (int) $myOrg['id'];

    $totalTravelersStmt = $db->prepare('SELECT COUNT(*) FROM organization_travelers WHERE organization_id = ? AND status = "active"');
    $totalTravelersStmt->execute([$orgId]);
    $totalTravelers = (int) $totalTravelersStmt->fetchColumn();

    $addedThisMonthStmt = $db->prepare('SELECT COUNT(*) FROM organization_travelers WHERE organization_id = ? AND status = "active" AND joined_at >= DATE_FORMAT(NOW(), "%Y-%m-01")');
    $addedThisMonthStmt->execute([$orgId]);
    $addedThisMonth = (int) $addedThisMonthStmt->fetchColumn();

    $activeJourneysStmt = $db->prepare('SELECT COUNT(*) FROM journeys WHERE status = "active" AND user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")');
    $activeJourneysStmt->execute([$orgId]);
    $activeJourneys = (int) $activeJourneysStmt->fetchColumn();

    $upcomingGroupsStmt = $db->prepare('SELECT COUNT(*) FROM group_journeys WHERE status = "upcoming" AND organizer_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")');
    $upcomingGroupsStmt->execute([$orgId]);
    $upcomingGroups = (int) $upcomingGroupsStmt->fetchColumn();

    $sosThisMonthStmt = $db->prepare('SELECT COUNT(*) FROM sos_alerts WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01") AND user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")');
    $sosThisMonthStmt->execute([$orgId]);
    $sosThisMonth = (int) $sosThisMonthStmt->fetchColumn();

    $sosActiveStmt = $db->prepare('SELECT COUNT(*) FROM sos_alerts WHERE status = "active" AND user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")');
    $sosActiveStmt->execute([$orgId]);
    $sosActive = (int) $sosActiveStmt->fetchColumn();

    $activityStmt = $db->prepare(
        'SELECT * FROM (
            SELECT "journey_started" AS etype, j.started_at AS etime, u.full_name AS name1, j.start_label AS label1, j.end_label AS label2, NULL AS num
            FROM journeys j JOIN users u ON u.id = j.user_id
            WHERE j.user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")

            UNION ALL

            SELECT "journey_completed", j.ended_at, u.full_name, j.start_label, j.end_label, NULL
            FROM journeys j JOIN users u ON u.id = j.user_id
            WHERE j.status = "completed" AND j.user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")

            UNION ALL

            SELECT "group_created", gj.created_at, u.full_name, gj.title, gj.destination_label, (SELECT COUNT(*) FROM group_members gm WHERE gm.group_journey_id = gj.id)
            FROM group_journeys gj JOIN users u ON u.id = gj.organizer_id
            WHERE gj.organizer_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")

            UNION ALL

            SELECT "sos_triggered", sa.created_at, u.full_name, NULL, NULL, NULL
            FROM sos_alerts sa JOIN users u ON u.id = sa.user_id
            WHERE sa.user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")

            UNION ALL

            SELECT "sos_resolved", sa.resolved_at, u.full_name, NULL, NULL, NULL
            FROM sos_alerts sa JOIN users u ON u.id = sa.user_id
            WHERE sa.status = "resolved" AND sa.user_id IN (SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active")
        ) activity
        WHERE etime IS NOT NULL
        ORDER BY etime DESC
        LIMIT 8'
    );
    $activityStmt->execute([$orgId, $orgId, $orgId, $orgId, $orgId]);
    $activity = $activityStmt->fetchAll();
}

function admin_relative_time(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'yesterday';
    return floor($diff / 86400) . ' days ago';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Organization Overview</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="logo"><i class="fa-solid fa-route"></i></div><div><b>SafariTrak</b><small>Travel smarter</small><span class="org-badge">ORGANIZATION</span></div></div>
  <nav>
    <a class="active" href="org-dashboard.php"><i class="fa-solid fa-grid-2"></i>Overview</a>
    <?php if ($myOrg): ?>
    <a href="org-travelers.php"><i class="fa-solid fa-users"></i>Travelers</a>
    <a href="org-groups.php"><i class="fa-solid fa-user-group"></i>Group Journeys</a>
    <a href="org-reports.php"><i class="fa-solid fa-chart-simple"></i>Reports</a>
    <?php endif; ?>
  </nav>
  <div class="bottom">
    <a href="index.php"><i class="fa-solid fa-arrow-right-arrow-left"></i>Switch to traveler view</a>
    <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
    <div class="account">
      <span><?= st_avatar_inner($currentUser) ?></span>
      <div>
        <b><?= htmlspecialchars($userName) ?></b>
        <small><?= htmlspecialchars($myOrg['name'] ?? 'Organization admin') ?></small>
      </div>
    </div>
  </div>
</aside>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>ORGANIZATION OVERVIEW</label><h1><?= htmlspecialchars($myOrg['name'] ?? 'Get started') ?></h1></div>
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

<?php if ($myOrg && $myOrgSuspended): ?>

<div class="card" style="max-width:480px;margin:30px auto;border:1px solid #f3c8c8">
  <div class="card-head"><div><label style="color:#c94b4b">SUSPENDED</label><h3>This organization has been suspended</h3></div></div>
  <div style="padding:0 21px 21px">
    <p style="font-size:12px;color:var(--muted);line-height:1.7">SafariTrak has suspended <?= htmlspecialchars($myOrg['name']) ?>. Your travelers cannot be managed while this is in effect. Contact SafariTrak support if you believe this is a mistake.</p>
  </div>
</div>

<?php elseif (!$myOrg): ?>

<div class="card" style="max-width:480px;margin:30px auto">
  <div class="card-head"><div><label>SET UP YOUR ORGANIZATION</label><h3>Create your organization</h3></div></div>
  <div style="padding:0 21px 21px">
    <p style="font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:16px">Manage the people who travel under your organization, keep an eye on their journeys, and see safety reports in one place.</p>
    <div class="form-field" style="margin-bottom:14px"><label>Organization name</label><input type="text" id="orgName" placeholder="e.g. Meru Transport Sacco"></div>
    <p class="hint" id="createOrgError" style="color:#c94b4b;margin-bottom:10px;display:none"></p>
    <button type="button" class="btn-primary" id="createOrgBtn" style="width:100%;justify-content:center">Create organization</button>
  </div>
</div>

<?php else: ?>

<div class="page-head">
  <div><h2>How your organization is travelling</h2><p>A snapshot of your travelers, group journeys, and safety activity.</p></div>
</div>

<div class="stat-grid">
  <div class="stat-card"><label>TOTAL TRAVELERS</label><strong><?= $totalTravelers ?></strong><small><?= $addedThisMonth ?> added this month</small></div>
  <div class="stat-card"><label>ACTIVE JOURNEYS</label><strong><?= $activeJourneys ?></strong><small>Right now</small></div>
  <div class="stat-card"><label>UPCOMING GROUP TRIPS</label><strong><?= $upcomingGroups ?></strong><small>Scheduled</small></div>
  <div class="stat-card"><label>SOS ALERTS</label><strong><?= $sosThisMonth ?></strong><small>This month, <?= $sosActive ?> still active</small></div>
</div>

<div class="card">
  <div class="card-head"><div><label>RECENT ACTIVITY</label><h3>What's happening</h3></div></div>
  <div class="journey-list">
    <?php if (empty($activity)): ?>
    <p class="hint" style="padding:20px 21px;color:var(--muted);font-size:11px">No activity yet. Once your travelers start journeys or trigger alerts, you will see it here.</p>
    <?php endif; ?>
    <?php foreach ($activity as $a): ?>
    <?php
      $icon = ['journey_started' => 'fa-route', 'journey_completed' => 'fa-check', 'group_created' => 'fa-user-group', 'sos_triggered' => 'fa-triangle-exclamation', 'sos_resolved' => 'fa-check'][$a['etype']];
      $badgeClass = ['journey_started' => 'active', 'journey_completed' => 'completed', 'group_created' => 'active', 'sos_triggered' => 'cancelled', 'sos_resolved' => 'completed'][$a['etype']];
      $badgeLabel = ['journey_started' => 'Active', 'journey_completed' => 'Completed', 'group_created' => 'Upcoming', 'sos_triggered' => 'Alert', 'sos_resolved' => 'Resolved'][$a['etype']];
      $title = [
        'journey_started' => htmlspecialchars($a['name1']) . ' started a journey',
        'journey_completed' => htmlspecialchars($a['name1']) . ' completed a journey',
        'group_created' => htmlspecialchars($a['name1']) . ' created a group journey',
        'sos_triggered' => htmlspecialchars($a['name1']) . ' sent an SOS alert',
        'sos_resolved' => htmlspecialchars($a['name1']) . "'s SOS alert was resolved",
      ][$a['etype']];
      $sub = in_array($a['etype'], ['journey_started', 'journey_completed'], true)
        ? htmlspecialchars($a['label1']) . ' &rarr; ' . htmlspecialchars($a['label2']) . ' &middot; ' . admin_relative_time($a['etime'])
        : ($a['etype'] === 'group_created'
            ? htmlspecialchars($a['label1']) . ' &middot; ' . (int) $a['num'] . ' members &middot; ' . admin_relative_time($a['etime'])
            : admin_relative_time($a['etime']));
    ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon"><i class="fa-solid <?= $icon ?>"></i></div>
      <div class="jinfo"><b><?= $title ?></b><small><?= $sub ?></small></div>
      <div class="jmeta"><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>

</div>
<footer><?= '© ' . date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
<?php if (!$myOrg): ?>
<script src="org-onboarding.js"></script>
<?php endif; ?>
</body>
</html>