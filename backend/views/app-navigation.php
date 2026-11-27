<?php
$activeNav = $activeNav ?? match (basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    'my-journeys.php' => 'trips',
    'end-journey.php' => 'trips',
    'start-journey.php' => 'trips',
    'tracking.php' => 'trips',
    'view-journey.php' => 'trips',
    'watch-journey.php' => 'trips',
    'group-travel.php' => 'groups',
    'groups.php' => 'groups',
    'org-dashboard.php' => 'organization',
    'org-travelers.php' => 'org-travelers',
    'org-groups.php' => 'org-groups',
    'org-reports.php' => 'org-reports',
    'school.php' => 'school',
    'school-trips.php' => isset($_GET['child']) && is_string($_GET['child']) && ctype_digit($_GET['child'])
        ? 'school-child-' . (int) $_GET['child']
        : 'school-child',
    'car-hire.php' => 'car-hire',
    'fleet.php' => 'fleet',
    'messages.php' => 'messages',
    'places.php' => 'places',
    'trusted-contacts.php' => 'contacts',
    'notifications.php' => 'notifications',
    'settings.php' => 'settings',
    'admin-dashboard.php' => 'platform-admin',
    'admin-users.php' => 'admin-users',
    'admin-organizations.php' => 'admin-organizations',
    'admin-admins.php' => 'admin-admins',
    default => 'dashboard',
};
$isAdminNavActive = in_array($activeNav, ['platform-admin', 'admin-users', 'admin-organizations', 'admin-admins'], true);
$unreadConversationCount = $unreadConversationCount ?? 0;
$unreadNotificationStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadNotificationStmt->execute([(int) $currentUser['id']]);
$unreadNotificationCount = (int) $unreadNotificationStmt->fetchColumn();
$isCarHireOrgAdmin = false;
if (($currentUser['role'] ?? '') === 'org_admin' && !empty($currentUser['organization_id'])) {
    $fleetTypeStmt = $db->prepare(
        'SELECT 1 FROM organization_profiles
         WHERE organization_id = ? AND account_type = "car_hire" AND review_status = "approved"'
    );
    $fleetTypeStmt->execute([(int) $currentUser['organization_id']]);
    $isCarHireOrgAdmin = (bool) $fleetTypeStmt->fetchColumn();
}
$schoolChildAccess = [];
$schoolTransportVerification = null;
require_once __DIR__ . '/../includes/school-transport.php';
$schoolTransportVerification = st_school_transport_verification($db, (int) $currentUser['id']);
$schoolChildStmt = $db->prepare(
    'SELECT ca.child_user_id, ca.status, child.full_name AS child_name, o.name AS organization_name
     FROM organization_child_access ca
     JOIN organizations o ON o.id = ca.organization_id AND o.is_suspended = 0
     JOIN organization_profiles p ON p.organization_id = o.id
       AND p.account_type = "school" AND p.review_status = "approved"
     JOIN organization_travelers student
       ON student.organization_id = ca.organization_id
      AND student.user_id = ca.child_user_id
      AND student.status = "active" AND student.member_type = "student"
     JOIN organization_travelers guardian
       ON guardian.organization_id = ca.organization_id
      AND guardian.user_id = ca.guardian_user_id
      AND guardian.status = "active" AND guardian.member_type = "guardian"
     JOIN users child ON child.id = ca.child_user_id
     WHERE ca.guardian_user_id = ? AND ca.status IN ("pending", "active")
     ORDER BY ca.status ASC, child.full_name ASC'
);
$schoolChildStmt->execute([(int) $currentUser['id']]);
$schoolChildAccess = $schoolChildStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<aside class="sidebar app-navbar" id="sidebar" aria-label="Primary navigation" data-standard-navbar>
  <a class="app-navbar-brand-lockup" href="index.php" aria-label="SafariTrak home">
    <i class="fa-solid fa-route" aria-hidden="true"></i>
    <span class="brand-name">Safari<span>Trak</span></span>
  </a>
  <nav aria-label="Main navigation">
    <a class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="index.php" title="Home" aria-label="Home" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-house" aria-hidden="true"></i><span class="nav-link-label">Home</span></a>
    <a class="<?= $activeNav === 'trips' ? 'active' : '' ?>" href="my-journeys.php" title="My trips" aria-label="My trips" <?= $activeNav === 'trips' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-route" aria-hidden="true"></i><span class="nav-link-label">My trips</span></a>
    <a class="<?= in_array($activeNav, ['groups', 'group-trips'], true) ? 'active' : '' ?>" href="groups.php" title="Groups" aria-label="Groups" <?= in_array($activeNav, ['groups', 'group-trips'], true) ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-people-group" aria-hidden="true"></i><span class="nav-link-label">Groups</span></a>
    <a class="<?= $activeNav === 'contacts' ? 'active' : '' ?>" href="trusted-contacts.php" title="Trusted contacts" aria-label="Trusted contacts" <?= $activeNav === 'contacts' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span class="nav-link-label">Trusted contacts</span></a>
    <a class="<?= $activeNav === 'car-hire' ? 'active' : '' ?>" href="car-hire.php" title="Car hire" aria-label="Car hire" <?= $activeNav === 'car-hire' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-car-side" aria-hidden="true"></i><span class="nav-link-label">Car hire</span></a>
    <?php if (($currentUser['role'] ?? '') === 'org_admin'): ?>
      <a class="<?= in_array($activeNav, ['organization', 'org-travelers', 'org-groups', 'org-reports'], true) ? 'active' : '' ?>" href="org-dashboard.php" title="Organization" aria-label="Organization" <?= $activeNav === 'organization' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-building" aria-hidden="true"></i><span class="nav-link-label">Organization</span></a>
    <?php endif; ?>
    <?php if ($isCarHireOrgAdmin): ?>
      <a class="<?= $activeNav === 'fleet' ? 'active' : '' ?>" href="fleet.php" title="Manage fleet" aria-label="Manage fleet" <?= $activeNav === 'fleet' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-car" aria-hidden="true"></i><span class="nav-link-label">Manage fleet</span></a>
    <?php endif; ?>
    <?php if ($schoolTransportVerification): ?>
      <a class="<?= in_array($activeNav, ['school', 'school-child'], true) ? 'active' : '' ?>" href="school.php" title="School transport" aria-label="School transport" <?= $activeNav === 'school' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><span class="nav-link-label">School</span></a>
    <?php endif; ?>
    <a class="<?= $activeNav === 'messages' ? 'active' : '' ?>" href="messages.php" title="Messages" aria-label="Messages<?= $unreadConversationCount > 0 ? ', ' . (int) $unreadConversationCount . ' unread' : '' ?>" <?= $activeNav === 'messages' ? 'aria-current="page"' : '' ?>><i class="fa-regular fa-message" aria-hidden="true"></i><span class="nav-link-label">Messages</span><?= $unreadConversationCount > 0 ? ' <em>' . (int) $unreadConversationCount . '</em>' : '' ?></a>
    <a class="<?= $activeNav === 'notifications' ? 'active' : '' ?> nav-mobile-extra" href="notifications.php" title="Notifications" aria-label="Notifications<?= $unreadNotificationCount > 0 ? ', ' . $unreadNotificationCount . ' unread' : '' ?>" <?= $activeNav === 'notifications' ? 'aria-current="page"' : '' ?>><i class="fa-regular fa-bell" aria-hidden="true"></i><span class="nav-link-label">Notifications</span><?= $unreadNotificationCount > 0 ? ' <em>' . $unreadNotificationCount . '</em>' : '' ?></a>
    <a class="<?= $activeNav === 'places' ? 'active' : '' ?>" href="places.php" title="Places" aria-label="Places" <?= $activeNav === 'places' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-map-pin" aria-hidden="true"></i><span class="nav-link-label">Places</span></a>
    <?php foreach ($schoolChildAccess as $schoolChild): ?>
      <?php if ($schoolChild['status'] === 'active'): ?>
        <a class="<?= $activeNav === 'school-child-' . (int) $schoolChild['child_user_id'] ? 'active' : '' ?>" href="school-trips.php?child=<?= (int) $schoolChild['child_user_id'] ?>" title="Trips for <?= htmlspecialchars($schoolChild['child_name'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Trips for <?= htmlspecialchars($schoolChild['child_name'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-child-reaching" aria-hidden="true"></i><span class="nav-link-label">Child: <?= htmlspecialchars($schoolChild['child_name'], ENT_QUOTES, 'UTF-8') ?></span></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (array_filter($schoolChildAccess, static fn(array $access): bool => $access['status'] === 'pending')): ?>
      <a class="<?= $activeNav === 'school-child-invitations' ? 'active' : '' ?>" href="school-trips.php#invitations" title="School invitations" aria-label="School invitations"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i><span class="nav-link-label">School invitations</span></a>
    <?php endif; ?>
    <a class="<?= $activeNav === 'settings' ? 'active' : '' ?>" href="settings.php" title="Account" aria-label="Account" <?= $activeNav === 'settings' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span class="nav-link-label">Account</span></a>
    <?php if (($currentUser['role'] ?? '') === 'platform_admin'): ?>
      <details class="app-navbar-admin-menu" <?= $isAdminNavActive ? 'open data-active="true"' : '' ?>>
        <summary title="Admin" aria-label="Open admin menu"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Admin</span><i class="fa-solid fa-chevron-down app-navbar-admin-chevron" aria-hidden="true"></i></summary>
        <div class="app-navbar-admin-submenu" aria-label="Admin sections">
          <a class="<?= $activeNav === 'platform-admin' ? 'active' : '' ?>" href="admin-dashboard.php" title="Admin overview" aria-label="Admin overview" <?= $activeNav === 'platform-admin' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span class="nav-link-label">Admin overview</span></a>
          <a class="<?= $activeNav === 'admin-users' ? 'active' : '' ?>" href="admin-users.php" title="Accounts and roles" aria-label="Accounts and roles" <?= $activeNav === 'admin-users' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-users" aria-hidden="true"></i><span class="nav-link-label">Accounts and roles</span></a>
          <a class="<?= $activeNav === 'admin-organizations' ? 'active' : '' ?>" href="admin-organizations.php" title="Organizations" aria-label="Organizations" <?= $activeNav === 'admin-organizations' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-building" aria-hidden="true"></i><span class="nav-link-label">Organizations</span></a>
          <a class="<?= $activeNav === 'admin-admins' ? 'active' : '' ?>" href="admin-admins.php" title="Platform admins" aria-label="Platform admins" <?= $activeNav === 'admin-admins' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span class="nav-link-label">Platform admins</span></a>
        </div>
      </details>
    <?php endif; ?>
  </nav>
  <div class="app-navbar-actions">
    <a class="app-navbar-search" href="places.php#placeSearch" title="Search places" aria-label="Search places"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></a>
    <button type="button" class="app-navbar-theme-toggle" data-theme-toggle aria-label="Switch colour theme" title="Switch colour theme" aria-pressed="false">
      <i class="fa-regular fa-sun" aria-hidden="true"></i>
      <span class="app-navbar-theme-switch" aria-hidden="true"><span></span></span>
      <i class="fa-regular fa-moon" aria-hidden="true"></i>
    </button>
    <button type="button" class="app-navbar-sos" data-sos-trigger aria-label="Send an emergency SOS alert" title="SOS — emergency alert">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span class="nav-link-label">SOS</span>
    </button>
    <div class="profile-dropdown-wrap app-navbar-profile-wrap" id="profileDropdownWrap">
      <button type="button" class="avatar-btn app-navbar-avatar" id="profileAvatarBtn" aria-expanded="false" aria-label="Account menu" title="Account menu">
        <?= st_avatar_inner($currentUser) ?>
      </button>
      <div class="profile-dropdown-menu" id="profileDropdownMenu">
        <div class="profile-dropdown-header"><b><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></b><small><?= ($currentUser['role'] ?? '') === 'org_admin' ? 'Organization account' : (($currentUser['role'] ?? '') === 'platform_admin' ? 'Platform administrator' : 'Personal account') ?></small></div>
        <hr class="profile-dropdown-divider">
        <ul class="profile-dropdown-list">
          <li><a href="settings.php" class="profile-dropdown-item"><i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>Account settings</span></a></li>
          <li><a href="logout.php" class="profile-dropdown-item logout-link"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Sign out</span></a></li>
        </ul>
      </div>
    </div>
  </div>
</aside>
<dialog class="app-sos-dialog" id="appSosDialog" aria-labelledby="appSosTitle" aria-describedby="appSosDescription">
  <div class="app-sos-dialog-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
  <h2 id="appSosTitle">Send an emergency alert?</h2>
  <p id="appSosDescription">Your confirmed trusted contacts who are set to receive SOS alerts will be notified. Your current location will be included if available.</p>
  <p class="app-sos-status" id="appSosStatus" role="status" aria-live="polite"></p>
  <div class="app-sos-dialog-actions">
    <button type="button" class="app-sos-cancel" data-sos-cancel>Cancel</button>
    <button type="button" class="app-sos-confirm" data-sos-confirm>Send SOS</button>
  </div>
</dialog>
