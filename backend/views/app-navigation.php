<?php
$activeNav = $activeNav ?? match (basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    'my-journeys.php' => 'trips',
    'group-travel.php' => 'groups',
    'groups.php' => 'groups',
    'car-hire.php' => 'car-hire',
    'fleet.php' => 'fleet',
    'messages.php' => 'messages',
    'places.php' => 'places',
    'trusted-contacts.php' => 'contacts',
    'settings.php' => 'settings',
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
?>
<button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Close navigation"></button>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <a class="brand" href="index.php" aria-label="SafariTrak Explore" title="SafariTrak Explore">
    <span class="logo"><i class="fa-solid fa-route"></i></span>
    <span><b>SafariTrak</b><small>Track · Connect · Stay Safe</small></span>
  </a>
  <nav>
    <span class="nav-label">YOUR SAFARITRAK</span>
    <a class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="index.php" title="Explore" aria-label="Explore" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-compass"></i>Explore</a>
    <a class="<?= $activeNav === 'trips' ? 'active' : '' ?>" href="my-journeys.php" title="My trips" aria-label="My trips" <?= $activeNav === 'trips' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-route"></i>My trips</a>
    <a class="<?= in_array($activeNav, ['groups', 'group-trips'], true) ? 'active' : '' ?>" href="groups.php" title="Groups" aria-label="Groups" <?= in_array($activeNav, ['groups', 'group-trips'], true) ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-people-group"></i>Groups</a>
    <a class="<?= $activeNav === 'car-hire' ? 'active' : '' ?>" href="car-hire.php" title="Car hire" aria-label="Car hire" <?= $activeNav === 'car-hire' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-car-side"></i>Car hire</a>
    <?php if (($currentUser['role'] ?? '') === 'org_admin'): ?>
      <a href="org-dashboard.php" title="Organization" aria-label="Organization"><i class="fa-solid fa-building"></i>Organization</a>
    <?php endif; ?>
    <?php if ($isCarHireOrgAdmin): ?>
      <a class="<?= $activeNav === 'fleet' ? 'active' : '' ?>" href="fleet.php" title="Manage fleet" aria-label="Manage fleet" <?= $activeNav === 'fleet' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-car"></i>Manage fleet</a>
    <?php endif; ?>
    <a class="<?= $activeNav === 'messages' ? 'active' : '' ?>" href="messages.php" title="Messages" aria-label="Messages<?= $unreadConversationCount > 0 ? ', ' . (int) $unreadConversationCount . ' unread' : '' ?>" <?= $activeNav === 'messages' ? 'aria-current="page"' : '' ?>><i class="fa-regular fa-message"></i>Messages<?= $unreadConversationCount > 0 ? ' <em>' . (int) $unreadConversationCount . '</em>' : '' ?></a>
    <a class="<?= $activeNav === 'places' ? 'active' : '' ?>" href="places.php" title="Places" aria-label="Places" <?= $activeNav === 'places' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-map-pin"></i>Places</a>
    <span class="nav-label nav-label-spaced">ACCOUNT</span>
    <a class="<?= in_array($activeNav, ['settings', 'contacts'], true) ? 'active' : '' ?>" href="settings.php" title="Account" aria-label="Account" <?= in_array($activeNav, ['settings', 'contacts'], true) ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-user-gear"></i>Account</a>
  </nav>
  <div class="bottom">
    <a href="logout.php" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i>Sign out</a>
    <div class="account"><span><?= st_avatar_inner($currentUser) ?></span><div><b><?= htmlspecialchars($userName) ?></b><small><?= ($currentUser['role'] ?? '') === 'org_admin' ? 'Organization account' : (($currentUser['role'] ?? '') === 'platform_admin' ? 'Platform administrator' : 'Personal account') ?></small></div></div>
  </div>
</aside>
