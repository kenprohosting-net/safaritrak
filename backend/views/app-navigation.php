<?php
$activeNav = $activeNav ?? match (basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    'my-journeys.php' => 'trips',
    'end-journey.php' => 'trips',
    'group-travel.php' => 'groups',
    'groups.php' => 'groups',
    'org-dashboard.php' => 'organization',
    'org-travelers.php' => 'org-travelers',
    'org-groups.php' => 'org-groups',
    'org-reports.php' => 'org-reports',
    'school.php' => 'school',
    'school-trips.php' => 'school-child',
    'car-hire.php' => 'car-hire',
    'fleet.php' => 'fleet',
    'messages.php' => 'messages',
    'places.php' => 'places',
    'trusted-contacts.php' => 'contacts',
    'settings.php' => 'settings',
    'admin-dashboard.php' => 'platform-admin',
    'admin-users.php' => 'admin-users',
    'admin-organizations.php' => 'admin-organizations',
    'admin-admins.php' => 'admin-admins',
    default => 'dashboard',
};
$unreadConversationCount = $unreadConversationCount ?? 0;
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
<a class="app-navbar-brand-lockup" href="index.php" aria-label="SafariTrak home">
  <i class="fa-solid fa-route" aria-hidden="true"></i>
  <span class="brand-name">Safari<span>Trak</span></span>
</a>
<aside class="sidebar app-navbar" id="sidebar" aria-label="Primary navigation" data-standard-navbar>
  <a class="app-navbar-brand" href="index.php" title="SafariTrak Explore" aria-label="SafariTrak Explore">
    <i class="fa-solid fa-route" aria-hidden="true"></i>
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
      <a class="<?= $activeNav === 'platform-admin' ? 'active' : '' ?>" href="admin-dashboard.php" title="Admin overview" aria-label="Admin overview" <?= $activeNav === 'platform-admin' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span class="nav-link-label">Admin overview</span></a>
      <a class="<?= $activeNav === 'admin-users' ? 'active' : '' ?>" href="admin-users.php" title="Accounts and roles" aria-label="Accounts and roles" <?= $activeNav === 'admin-users' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-users" aria-hidden="true"></i><span class="nav-link-label">Accounts and roles</span></a>
      <a class="<?= $activeNav === 'admin-organizations' ? 'active' : '' ?>" href="admin-organizations.php" title="Organizations" aria-label="Organizations" <?= $activeNav === 'admin-organizations' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-building" aria-hidden="true"></i><span class="nav-link-label">Organizations</span></a>
      <a class="<?= $activeNav === 'admin-admins' ? 'active' : '' ?>" href="admin-admins.php" title="Platform admins" aria-label="Platform admins" <?= $activeNav === 'admin-admins' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span class="nav-link-label">Platform admins</span></a>
    <?php endif; ?>
  </nav>
  <div class="app-navbar-actions">
    <a href="logout.php" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></a>
    <a class="app-navbar-avatar" href="settings.php" title="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?> — Account" aria-label="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>, account"><?= st_avatar_inner($currentUser) ?></a>
  </div>
</aside>
