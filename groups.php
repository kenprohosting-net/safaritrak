<?php
require __DIR__ . '/backend/includes/auth-guard.php';

if (($_GET['tab'] ?? '') === 'trips') {
    require __DIR__ . '/group-travel.php';
    exit;
}

$managedOrganization = null;
$organizationTravelers = [];
if (($currentUser['role'] ?? '') === 'org_admin') {
    $orgProfileStmt = $db->prepare(
        'SELECT o.id, o.name, p.account_type
         FROM organization_admins oa
         JOIN organizations o ON o.id = oa.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id AND p.review_status = "approved"
         WHERE oa.user_id = ? LIMIT 1'
    );
    $orgProfileStmt->execute([(int) $currentUser['id']]);
    $managedOrganization = $orgProfileStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($managedOrganization) {
        $orgTravelersStmt = $db->prepare(
            'SELECT u.id, u.full_name, u.email
             FROM organization_travelers ot
             JOIN users u ON u.id = ot.user_id
             WHERE ot.organization_id = ? AND ot.status = "active" AND u.id <> ?
             ORDER BY u.full_name'
        );
        $orgTravelersStmt->execute([(int) $managedOrganization['id'], (int) $currentUser['id']]);
        $organizationTravelers = $orgTravelersStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$groupStmt = $db->prepare(
    'SELECT g.id, g.name, g.description, g.organization_id, g.created_at,
            gm.role, o.name AS organization_name,
            (SELECT COUNT(*) FROM persistent_group_members x WHERE x.group_id = g.id) AS member_count,
            (SELECT body FROM persistent_group_messages m WHERE m.group_id = g.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message
     FROM persistent_groups g
     JOIN persistent_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
     LEFT JOIN organizations o ON o.id = g.organization_id
     WHERE g.status = "active"
       AND (
         g.organization_id IS NULL
         OR (
           o.is_suspended = 0
           AND EXISTS (
             SELECT 1 FROM organization_profiles p
             WHERE p.organization_id = g.organization_id AND p.review_status = "approved"
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
     ORDER BY g.updated_at DESC, g.id DESC'
);
$groupStmt->execute([(int) $currentUser['id']]);
$groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedGroup = null;
$members = [];
$groupId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
foreach ($groups as $group) {
    if ((int) $group['id'] === $groupId) {
        $selectedGroup = $group;
        break;
    }
}
if ($selectedGroup) {
    $memberStmt = $db->prepare(
        'SELECT u.id, u.full_name, u.avatar_path, gm.role, gm.joined_at
         FROM persistent_group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?
         ORDER BY FIELD(gm.role, "owner", "admin", "member"), u.full_name'
    );
    $memberStmt->execute([(int) $selectedGroup['id']]);
    $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Groups';
$pageHeading = 'Groups';
$activeNav = 'groups';
$loadNotifications = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<nav class="group-hub-tabs" aria-label="Groups sections">
  <a href="groups.php" class="is-active" aria-current="page"><i class="fa-solid fa-people-group" aria-hidden="true"></i>Groups</a>
  <a href="groups.php?tab=trips"><i class="fa-solid fa-route" aria-hidden="true"></i>Group trips</a>
</nav>
<section class="groups-page" aria-label="Your groups">
  <div class="groups-list-panel">
    <div class="groups-section-heading">
      <div><span class="explore-eyebrow">YOUR CIRCLES</span><h2>Groups</h2></div>
      <button type="button" class="explore-panel-action" id="createGroupOpen"><i class="fa-solid fa-plus"></i> New group</button>
    </div>
    <?php if (!$groups): ?>
      <p class="explore-empty-note">Create a group for friends, family, or your school community.</p>
    <?php else: ?>
      <div class="groups-list">
        <?php foreach ($groups as $group): ?>
          <a class="groups-list-item<?= $selectedGroup && (int) $selectedGroup['id'] === (int) $group['id'] ? ' is-active' : '' ?>"
             href="groups.php?id=<?= (int) $group['id'] ?>">
            <span class="groups-list-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span>
            <span class="groups-list-copy">
              <strong><?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <small><?= (int) $group['member_count'] ?> members<?= $group['organization_name'] ? ' · ' . htmlspecialchars($group['organization_name'], ENT_QUOTES, 'UTF-8') : '' ?></small>
              <?php if ($group['last_message']): ?><small class="groups-preview"><?= htmlspecialchars($group['last_message'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            </span>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($selectedGroup): ?>
    <section class="groups-chat-panel" aria-label="<?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES, 'UTF-8') ?> group chat">
      <header class="groups-chat-heading">
        <div><span class="explore-eyebrow"><?= $selectedGroup['organization_name'] ? 'ORGANIZATION GROUP' : 'PRIVATE GROUP' ?></span>
          <h2><?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES, 'UTF-8') ?></h2>
          <small><?= count($members) ?> members<?= $selectedGroup['description'] ? ' · ' . htmlspecialchars($selectedGroup['description'], ENT_QUOTES, 'UTF-8') : '' ?></small>
        </div>
        <?php if (in_array($selectedGroup['role'], ['owner', 'admin'], true)): ?>
          <button type="button" class="explore-panel-action" id="groupInviteOpen"><i class="fa-solid fa-link"></i> Invite</button>
        <?php endif; ?>
      </header>
      <ul class="groups-member-list" aria-label="Group members">
        <?php foreach ($members as $member): ?>
          <li><span class="groups-member-avatar"><?= st_avatar_inner($member) ?></span>
            <span><strong><?= htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(ucfirst($member['role']), ENT_QUOTES, 'UTF-8') ?></small></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="groups-chat-messages" id="groupMessages" role="log" aria-live="polite" aria-label="Group chat messages" aria-relevant="additions text">
        <p class="groups-chat-empty" role="status">Loading group chat…</p>
      </div>
      <form class="groups-chat-composer" id="groupMessageForm">
        <label class="visually-hidden" for="groupMessageInput">Message your group</label>
        <input id="groupMessageInput" maxlength="2000" autocomplete="off" placeholder="Message the group…" required>
        <button type="submit" aria-label="Send group message"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
      </form>
      <p id="groupChatStatus" class="visually-hidden" role="status" aria-live="polite"></p>
      <div id="selectedGroupConfig" data-group-id="<?= (int) $selectedGroup['id'] ?>"></div>
    </section>
  <?php else: ?>
    <section class="groups-chat-panel groups-empty-panel">
      <div class="groups-empty-illustration"><i class="fa-solid fa-people-group" aria-hidden="true"></i></div>
      <h2>Travel together, stay connected</h2>
      <p>Choose a group to open its member-only chat, or start a new circle.</p>
      <?php if ($groups): ?><a class="explore-panel-action" href="groups.php?id=<?= (int) $groups[0]['id'] ?>">Open <?= htmlspecialchars($groups[0]['name'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
    </section>
  <?php endif; ?>
</section>
<div class="groups-dialog" id="createGroupDialog" hidden>
  <form class="groups-dialog-card" id="createGroupForm">
    <button class="groups-dialog-close" type="button" data-close-group-dialog aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">START YOUR CIRCLE</span><h2>Create a group</h2>
    <label for="groupName">Group name</label><input id="groupName" maxlength="120" required placeholder="Family, road-trip crew…">
    <label for="groupDescription">Description <span>(optional)</span></label><textarea id="groupDescription" maxlength="500" rows="3" placeholder="What is this group for?"></textarea>
    <?php if ($managedOrganization && $managedOrganization['account_type'] === 'school'): ?>
      <label class="groups-org-toggle"><input type="checkbox" id="groupOrganization" value="<?= (int) $managedOrganization['id'] ?>"> Create as a <?= htmlspecialchars($managedOrganization['name'], ENT_QUOTES, 'UTF-8') ?> school group</label>
      <div class="groups-org-member-picker" id="organizationGroupMembers" hidden>
        <label>Start with approved school members</label>
        <?php if ($organizationTravelers): ?>
          <?php foreach ($organizationTravelers as $traveler): ?>
          <label class="groups-org-member"><input type="checkbox" class="organization-group-member" value="<?= (int) $traveler['id'] ?>"><span><?= htmlspecialchars($traveler['full_name'], ENT_QUOTES, 'UTF-8') ?></span></label>
          <?php endforeach; ?>
        <?php else: ?><p class="groups-form-status">There are no approved members to add yet.</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <p class="groups-form-status" id="createGroupStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Create group</button>
  </form>
</div>
<?php if ($selectedGroup && in_array($selectedGroup['role'], ['owner', 'admin'], true)): ?>
<div class="groups-dialog" id="inviteGroupDialog" hidden>
  <form class="groups-dialog-card" id="groupInviteForm">
    <button class="groups-dialog-close" type="button" data-close-group-dialog aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">INVITE MEMBERS</span><h2>Share your group</h2>
    <label for="groupInviteEmail">Email address <span>(optional for shareable link)</span></label><input id="groupInviteEmail" type="email" placeholder="friend@example.com">
    <p class="groups-form-status" id="groupInviteStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Create invite link</button>
    <div class="groups-invite-result" id="groupInviteResult" hidden>
      <label for="groupInviteUrl">Invitation link · expires in 7 days</label>
      <div><input type="url" id="groupInviteUrl" readonly><button type="button" id="copyGroupInvite">Copy</button></div>
      <a id="emailGroupInvite" href="#">Open email</a>
    </div>
  </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
<script src="groups.js?v=<?= hash_file('sha256', __DIR__ . '/groups.js') ?>"></script>
