<?php
require_once __DIR__ . '/backend/includes/session.php';
st_start_session();

if (empty($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

require __DIR__ . '/backend/includes/auth-guard.php';

$pageTitle = 'Explore';
$pageHeading = st_greeting() . ', ' . $userName;
$pageEyebrow = 'EXPLORE';
$activeNav = 'dashboard';
$loadMapLibre = false;
$loadNotifications = true;
$loadDashboardMap = true;
$isExploreDashboard = true;

$db = safaritrak_db();

$activeStmt = $db->prepare('SELECT id, start_label, end_label, distance_km, started_at, status FROM journeys WHERE user_id = ? AND status = "active" ORDER BY started_at DESC LIMIT 1');
$activeStmt->execute([$currentUser['id']]);
$activeJourney = $activeStmt->fetch();

$recentStmt = $db->prepare('SELECT id, start_label, end_label, distance_km, status, started_at FROM journeys WHERE user_id = ? AND status != "active" ORDER BY started_at DESC LIMIT 2');
$recentStmt->execute([$currentUser['id']]);
$recentJourneys = $recentStmt->fetchAll();

$dashboardGroupStmt = $db->prepare(
    'SELECT g.id, g.name
     FROM persistent_groups g
     JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
     WHERE g.status = "active"
       AND (
         g.organization_id IS NULL
         OR (
           EXISTS (
             SELECT 1 FROM organizations o
             JOIN organization_profiles p ON p.organization_id = o.id
             WHERE o.id = g.organization_id AND o.is_suspended = 0 AND p.review_status = "approved"
           )
           AND (
             EXISTS (
               SELECT 1 FROM organization_travelers ot
               WHERE ot.organization_id = g.organization_id AND ot.user_id = gm.user_id AND ot.status = "active"
             )
             OR EXISTS (
               SELECT 1 FROM organization_admins oa
               WHERE oa.organization_id = g.organization_id AND oa.user_id = gm.user_id
             )
           )
         )
       )
     ORDER BY g.updated_at DESC, g.id DESC
     LIMIT 1'
);
$dashboardGroupStmt->execute([(int) $currentUser['id']]);
$dashboardGroup = $dashboardGroupStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$dashboardGroupMessages = [];
if ($dashboardGroup) {
    $dashboardGroupMessagesStmt = $db->prepare(
        'SELECT m.sender_id, u.full_name AS sender_name, m.body, m.created_at
         FROM persistent_group_messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.group_id = ?
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT 3'
    );
    $dashboardGroupMessagesStmt->execute([(int) $dashboardGroup['id']]);
    $dashboardGroupMessages = array_reverse($dashboardGroupMessagesStmt->fetchAll(PDO::FETCH_ASSOC));
}

require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="explore-dashboard" aria-label="Explore SafariTrak">
  <div class="explore-map" id="dashboardMap" data-map-user-id="<?= (int) $currentUser['id'] ?>" role="application" aria-label="Interactive Google map">
    <div class="explore-map-canvas" id="dashboardMapCanvas" aria-hidden="true"></div>
    <div class="explore-map-heading">
      <span class="explore-live-indicator"></span>
      <div><strong>Explore</strong><small id="dashboardMapStatus" role="status" aria-live="polite">Loading the live map…</small></div>
    </div>
    <div class="explore-map-modes" role="tablist" aria-label="Map mode">
      <button type="button" role="tab" data-dashboard-projection="flat" aria-selected="true" aria-controls="dashboardMap" tabindex="0">
        <i class="fa-solid fa-map" aria-hidden="true"></i> Map
      </button>
      <button type="button" role="tab" data-dashboard-projection="satellite" aria-selected="false" aria-controls="dashboardMap" tabindex="-1">
        <i class="fa-solid fa-satellite" aria-hidden="true"></i> Satellite
      </button>
    </div>
    <div class="explore-map-controls" role="group" aria-label="Map controls">
      <button type="button" id="dashboardLocate" aria-label="Show my current location"><i class="fa-solid fa-location-crosshairs"></i></button>
    </div>
    <div class="explore-map-legend" aria-label="Map marker legend">
      <span><i class="is-you"></i>Your location</span>
      <span><i class="is-shared"></i>Shared with you</span>
    </div>
  </div>
  <div id="safariDashboardMapConfig"
       data-trip-id="<?= $activeJourney ? (int) $activeJourney['id'] : '' ?>"
       data-telemetry-url="backend/api/journeys/telemetry.php"
       data-shared-locations-url="backend/api/journeys/active-shared-locations.php"
       data-owner-name="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"></div>

  <aside class="explore-panels" aria-label="Journey and group chat">
    <section class="explore-panel explore-trip-panel">
      <div class="explore-panel-heading">
        <div><span class="explore-eyebrow">ACTIVE TRIP</span><h2><?= $activeJourney ? htmlspecialchars($activeJourney['start_label'], ENT_QUOTES, 'UTF-8') : 'Your next journey' ?></h2></div>
        <span class="explore-trip-status<?= $activeJourney ? ' is-on-trip' : '' ?>"><?= $activeJourney ? 'On trip' : 'Ready' ?></span>
      </div>
      <?php if ($activeJourney): ?>
        <p class="explore-trip-destination"><?= htmlspecialchars($activeJourney['end_label'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="explore-trip-stats">
          <span><i class="fa-solid fa-route"></i> <?= $activeJourney['distance_km'] !== null ? number_format((float) $activeJourney['distance_km'], 1) . ' km' : 'Distance updating' ?></span>
          <span><i class="fa-regular fa-clock"></i> Started <?= (new DateTime($activeJourney['started_at']))->format('g:i A') ?></span>
        </div>
        <div class="explore-trip-live-stats">
          <span><small>Speed</small><strong id="dashboardSpeed">—</strong></span>
          <span><small>Remaining</small><strong id="dashboardRemaining">—</strong></span>
          <span><small>ETA</small><strong id="dashboardEta">—</strong></span>
        </div>
        <a class="explore-panel-action" href="my-journeys.php?selected=<?= (int) $activeJourney['id'] ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> View full route</a>
      <?php else: ?>
        <p class="explore-trip-destination">Plan a route, then share your live location only with the people you choose.</p>
        <a class="explore-panel-action" href="start-journey.php"><i class="fa-solid fa-plus"></i> Plan a journey</a>
      <?php endif; ?>
    </section>

    <section class="explore-panel explore-chat-panel">
      <div class="explore-panel-heading">
        <div><span class="explore-eyebrow">GROUP CHAT</span><h2><?= $dashboardGroup ? htmlspecialchars($dashboardGroup['name'], ENT_QUOTES, 'UTF-8') : 'Your travel group' ?></h2></div>
        <a class="explore-count" href="<?= $dashboardGroup ? 'groups.php?id=' . (int) $dashboardGroup['id'] : 'groups.php' ?>" aria-label="Open group chat"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
      </div>
      <?php if ($dashboardGroup): ?>
        <div class="explore-chat-messages" id="exploreChatMessages" role="log" aria-live="polite" aria-label="Recent group messages">
          <?php if ($dashboardGroupMessages): ?>
            <?php foreach ($dashboardGroupMessages as $message): ?>
              <div class="explore-chat-message<?= (int) $message['sender_id'] === (int) $currentUser['id'] ? ' is-mine' : '' ?>">
                <span class="explore-person-avatar"><?= htmlspecialchars(st_initials($message['sender_name']), ENT_QUOTES, 'UTF-8') ?></span>
                <span><strong><?= htmlspecialchars((int) $message['sender_id'] === (int) $currentUser['id'] ? 'You' : $message['sender_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8') ?></small></span>
                <time><?= htmlspecialchars((new DateTime($message['created_at']))->format('H:i'), ENT_QUOTES, 'UTF-8') ?></time>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="explore-empty-note">Start a conversation with your group.</p>
          <?php endif; ?>
        </div>
        <form class="explore-chat-composer" id="exploreChatForm" data-group-id="<?= (int) $dashboardGroup['id'] ?>">
          <label class="visually-hidden" for="exploreChatInput">Send a group message</label>
          <input id="exploreChatInput" maxlength="2000" autocomplete="off" placeholder="Type a message…" required>
          <button type="submit" aria-label="Send message"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
        </form>
        <p id="exploreChatStatus" class="visually-hidden" role="status" aria-live="polite"></p>
      <?php else: ?>
        <p class="explore-empty-note">Create or join a group to plan trips and chat together.</p>
        <a class="explore-panel-action" href="groups.php"><i class="fa-solid fa-people-group"></i> Open your groups</a>
      <?php endif; ?>
      <a class="explore-text-link" href="groups.php<?= $dashboardGroup ? '?id=' . (int) $dashboardGroup['id'] : '' ?>"><i class="fa-solid fa-comments"></i> View all group messages</a>
    </section>

    <?php if ($recentJourneys): ?>
      <section class="explore-panel explore-recent-panel">
        <div class="explore-panel-heading">
          <div><span class="explore-eyebrow">RECENT</span><h2>Previous trips</h2></div>
          <a class="explore-count" href="my-journeys.php" aria-label="View all trips"><i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <?php foreach ($recentJourneys as $recent): ?>
          <a class="explore-recent-trip" href="view-journey.php?id=<?= (int) $recent['id'] ?>">
            <i class="fa-solid fa-route"></i>
            <span><strong><?= htmlspecialchars($recent['start_label'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($recent['end_label'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= (new DateTime($recent['started_at']))->format('j M') ?> · <?= ucfirst($recent['status']) ?></small></span>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </aside>

  <nav class="explore-shortcuts" aria-label="Explore shortcuts">
    <a href="places.php"><i class="fa-solid fa-location-dot"></i><strong>Locations</strong><small>Places and nearby</small></a>
    <a href="my-journeys.php"><i class="fa-solid fa-route"></i><strong>Trips</strong><small>Plan and review</small></a>
    <a href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i><strong>Contacts</strong><small>People you trust</small></a>
    <a href="notifications.php"><i class="fa-solid fa-bell"></i><strong>Alerts</strong><small>Safety and updates</small></a>
  </nav>
</section>
<?php if ($dashboardGroup): ?><script src="explore-chat.js?v=<?= hash_file('sha256', __DIR__ . '/explore-chat.js') ?>"></script><?php endif; ?>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
