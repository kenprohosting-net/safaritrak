<?php
require __DIR__ . '/backend/includes/org-guard.php';

if (!$myOrg || $myOrgSuspended) {
    header('Location: org-dashboard.php');
    exit;
}

$db = safaritrak_db();
$orgId = $myOrg['id'];
$travelersSub = 'SELECT user_id FROM organization_travelers WHERE organization_id = ? AND status = "active"';

$totalDistanceStmt = $db->prepare("SELECT COALESCE(SUM(distance_km), 0) FROM journeys WHERE status = 'completed' AND user_id IN ($travelersSub)");
$totalDistanceStmt->execute([$orgId]);
$totalDistance = (float) $totalDistanceStmt->fetchColumn();

$journeysCompletedStmt = $db->prepare("SELECT COUNT(*) FROM journeys WHERE status = 'completed' AND user_id IN ($travelersSub) AND started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$journeysCompletedStmt->execute([$orgId]);
$journeysThisMonth = (int) $journeysCompletedStmt->fetchColumn();

$avgDurationStmt = $db->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) FROM journeys WHERE status = 'completed' AND ended_at IS NOT NULL AND user_id IN ($travelersSub)");
$avgDurationStmt->execute([$orgId]);
$avgMinutes = (float) $avgDurationStmt->fetchColumn();

$totalJourneysStmt = $db->prepare("SELECT COUNT(*) FROM journeys WHERE status IN ('completed','cancelled') AND user_id IN ($travelersSub)");
$totalJourneysStmt->execute([$orgId]);
$totalJourneysAllTime = (int) $totalJourneysStmt->fetchColumn();

$incidentJourneysStmt = $db->prepare(
    "SELECT COUNT(DISTINCT j.id) FROM journeys j
     WHERE j.user_id IN ($travelersSub) AND j.status IN ('completed','cancelled')
     AND EXISTS (SELECT 1 FROM sos_alerts sa WHERE sa.journey_id = j.id)"
);
$incidentJourneysStmt->execute([$orgId]);
$incidentJourneys = (int) $incidentJourneysStmt->fetchColumn();
$safetyScore = $totalJourneysAllTime > 0 ? round((($totalJourneysAllTime - $incidentJourneys) / $totalJourneysAllTime) * 100) : 100;

$topTravelersStmt = $db->prepare(
    "SELECT u.full_name, u.id AS user_id,
            COUNT(j.id) AS journey_count,
            COALESCE(SUM(j.distance_km), 0) AS total_distance,
            (SELECT COUNT(*) FROM sos_alerts sa WHERE sa.user_id = u.id) AS sos_count
     FROM organization_travelers ot
     JOIN users u ON u.id = ot.user_id
     LEFT JOIN journeys j ON j.user_id = u.id AND j.status = 'completed'
     WHERE ot.organization_id = ? AND ot.status = 'active'
     GROUP BY u.id, u.full_name
     ORDER BY journey_count DESC, total_distance DESC
     LIMIT 5"
);
$topTravelersStmt->execute([$orgId]);
$topTravelers = $topTravelersStmt->fetchAll();

$monthlyStmt = $db->prepare(
    "SELECT DATE_FORMAT(started_at, '%Y-%m') AS ym, DATE_FORMAT(started_at, '%M %Y') AS label,
            COUNT(*) AS journeys, COALESCE(SUM(distance_km), 0) AS distance,
            (SELECT COUNT(*) FROM sos_alerts sa WHERE sa.user_id IN ($travelersSub) AND DATE_FORMAT(sa.created_at, '%Y-%m') = DATE_FORMAT(j.started_at, '%Y-%m')) AS sos
     FROM journeys j
     WHERE j.user_id IN ($travelersSub) AND j.status = 'completed'
     GROUP BY ym, label
     ORDER BY ym DESC
     LIMIT 6"
);
$monthlyStmt->execute([$orgId, $orgId]);
$monthly = $monthlyStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Reports</title>
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
  <div><label>THE BIG PICTURE</label><h1>Reports</h1></div>
  <div class="head-actions"><button><i class="fa-regular fa-bell"></i></button><div class="avatar">O</div></div>
</header>

<div class="content">

<div class="page-head">
  <div><h2>How your organization travelled</h2><p>A summary you can share with leadership or use for planning.</p></div>
</div>

<div class="stat-grid">
  <div class="stat-card"><label>TOTAL DISTANCE</label><strong><?= number_format($totalDistance, 0) ?> km</strong><small>All completed journeys</small></div>
  <div class="stat-card"><label>JOURNEYS THIS MONTH</label><strong><?= $journeysThisMonth ?></strong><small>Completed</small></div>
  <div class="stat-card"><label>AVERAGE JOURNEY TIME</label><strong><?= $avgMinutes > 0 ? floor($avgMinutes / 60) . 'h ' . round($avgMinutes % 60) . 'm' : 'No data yet' ?></strong><small>Door to door</small></div>
  <div class="stat-card"><label>SAFETY SCORE</label><strong><?= $safetyScore ?>%</strong><small>Journeys with no SOS alert</small></div>
</div>

<div class="card">
  <div class="card-head"><div><label>MOST ACTIVE TRAVELERS</label><h3>By completed journeys</h3></div></div>
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Traveler</th><th>Journeys</th><th>Distance</th><th>SOS alerts</th></tr>
      </thead>
      <tbody>
        <?php if (empty($topTravelers)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px 0">No journey activity yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($topTravelers as $tt): ?>
        <tr>
          <td><div class="table-person"><span class="person"><?= htmlspecialchars(st_initials($tt['full_name'])) ?></span><?= htmlspecialchars($tt['full_name']) ?></div></td>
          <td><?= (int) $tt['journey_count'] ?></td>
          <td><?= number_format((float) $tt['total_distance'], 0) ?> km</td>
          <td><?= (int) $tt['sos_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-head"><div><label>MONTHLY TREND</label><h3>Journeys over the last 6 months</h3></div></div>
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Month</th><th>Journeys</th><th>Distance</th><th>SOS alerts</th></tr>
      </thead>
      <tbody>
        <?php if (empty($monthly)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px 0">No completed journeys yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($monthly as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['label']) ?></td>
          <td><?= (int) $m['journeys'] ?></td>
          <td><?= number_format((float) $m['distance'], 0) ?> km</td>
          <td><?= (int) $m['sos'] ?></td>
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
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
</body>
</html>
