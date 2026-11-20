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
            (SELECT CASE WHEN m.attachment_path IS NOT NULL
                    THEN CONCAT("Photo · ", COALESCE(NULLIF(m.trip_label, ""), "Trip"))
                    ELSE m.body END
             FROM persistent_group_messages m
             WHERE m.group_id = g.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM persistent_group_messages unread
             WHERE unread.group_id = g.id AND unread.sender_id <> gm.user_id
               AND unread.id > COALESCE(gm.last_read_message_id, 0)) AS unread_count
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
$groupAvatars = [];
if ($groups) {
    $groupIds = array_map(static fn(array $group): int => (int) $group['id'], $groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $avatarStmt = $db->prepare(
        "SELECT gm.group_id, u.full_name, u.avatar_path
         FROM persistent_group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id IN ($placeholders)
         ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), u.full_name"
    );
    $avatarStmt->execute($groupIds);
    foreach ($avatarStmt->fetchAll(PDO::FETCH_ASSOC) as $avatar) {
        $groupAvatars[(int) $avatar['group_id']][] = $avatar;
    }
}
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
$appMapPage = 'groups';
$isGroupsDashboard = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="groups-page" aria-label="Your groups">
  <div class="groups-list-panel">
    <nav class="group-hub-tabs" aria-label="Groups sections">
      <a href="groups.php" class="is-active" aria-current="page"><i class="fa-solid fa-people-group" aria-hidden="true"></i>Groups</a>
      <a href="groups.php?tab=trips"><i class="fa-solid fa-route" aria-hidden="true"></i>Group trips</a>
    </nav>
    <div class="groups-section-heading">
      <div><span class="explore-eyebrow">YOUR CIRCLES</span><h2>Groups</h2></div>
      <button type="button" class="explore-panel-action" id="createGroupOpen"><i class="fa-solid fa-plus"></i> New group</button>
    </div>
    <label class="groups-search" for="groupSearch">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input id="groupSearch" type="search" placeholder="Search" autocomplete="off">
    </label>
    <div class="groups-list" id="groupDirectory">
      <?php if (!$groups): ?>
        <p class="explore-empty-note">Create a group for friends, family, or your school community.</p>
      <?php else: ?>
        <?php foreach ($groups as $group): ?>
          <?php $groupId = (int) $group['id']; ?>
          <?php $lastMessage = (string) ($group['last_message'] ?? ''); ?>
          <?php if (str_starts_with($lastMessage, '__safaritrak_location__:')): ?><?php $lastMessage = 'Shared a location point.'; ?><?php endif; ?>
          <a class="groups-list-item<?= $selectedGroup && (int) $selectedGroup['id'] === $groupId ? ' is-active' : '' ?>"
             data-group-search-item
             data-group-id="<?= $groupId ?>"
             data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>"
             href="groups.php?id=<?= (int) $group['id'] ?>">
            <span class="groups-avatar-stack" aria-hidden="true">
              <?php foreach (array_slice($groupAvatars[$groupId] ?? [], 0, 3) as $avatar): ?>
                <span class="groups-member-avatar"><?= st_avatar_inner($avatar) ?></span>
              <?php endforeach; ?>
            </span>
            <span class="groups-list-copy">
              <strong><?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <small><?= (int) $group['member_count'] ?> members<?= $group['organization_name'] ? ' · ' . htmlspecialchars($group['organization_name'], ENT_QUOTES, 'UTF-8') : '' ?></small>
              <?php if ($lastMessage !== ''): ?><small class="groups-preview"><?= htmlspecialchars($lastMessage, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            </span>
            <?php if ((int) $group['unread_count'] > 0): ?>
              <span class="groups-unread-badge" data-group-unread="<?= $groupId ?>" aria-label="<?= (int) $group['unread_count'] ?> unread messages"><?= (int) $group['unread_count'] ?></span>
            <?php else: ?>
              <span class="groups-unread-badge" data-group-unread="<?= $groupId ?>" hidden></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <p class="groups-directory-empty" id="groupSearchEmpty" hidden>No groups match your search.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($selectedGroup): ?>
    <section class="groups-chat-panel" aria-label="<?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES, 'UTF-8') ?> group chat">
      <header class="groups-chat-heading">
        <div><span class="explore-eyebrow"><?= $selectedGroup['organization_name'] ? 'ORGANIZATION GROUP' : 'PRIVATE GROUP' ?></span>
          <h2><?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES, 'UTF-8') ?></h2>
          <small><?= count($members) ?> members</small>
        </div>
        <div class="groups-heading-actions">
          <button type="button" class="groups-invite-button" id="groupSettingsOpen" aria-label="Group settings" title="Group settings"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>
          <?php if (in_array($selectedGroup['role'], ['owner', 'admin'], true)): ?>
            <button type="button" class="groups-invite-button" id="groupInviteOpen" aria-label="Invite members" title="Invite members"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></button>
          <?php endif; ?>
        </div>
      </header>
      <a class="groups-view-trips" href="groups.php?tab=trips"><i class="fa-solid fa-route" aria-hidden="true"></i>View Group Trips</a>
      <div class="groups-chat-messages" id="groupMessages" role="log" aria-live="polite" aria-label="Group chat messages" aria-relevant="additions text">
        <p class="groups-chat-empty" role="status">Loading group chat…</p>
      </div>
      <div class="groups-photo-details" id="groupPhotoDetails" hidden>
        <img id="groupPhotoPreview" alt="Photo preview" hidden>
        <div class="groups-photo-fields">
          <label for="groupPhotoTripLabel">Trip label<input id="groupPhotoTripLabel" maxlength="120" required placeholder="e.g. Amboseli weekend"></label>
          <label for="groupPhotoCaption">Caption <span>(optional)</span><input id="groupPhotoCaption" maxlength="2000" placeholder="Add a note"></label>
        </div>
        <div class="groups-photo-actions">
          <button type="button" class="groups-photo-cancel" id="cancelGroupPhoto">Cancel</button>
          <button type="button" class="groups-photo-send" id="sendGroupPhoto">Share photo</button>
        </div>
      </div>
      <input type="file" id="groupPhotoFile" accept="image/jpeg,image/png,image/webp" hidden>
      <form class="groups-chat-composer" id="groupMessageForm">
        <button class="groups-share-location" id="shareGroupLocation" type="button" aria-label="Share your live location with this group" title="Share your live location with this group" aria-pressed="false"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i></button>
        <button class="groups-attach-photo" id="attachGroupPhoto" type="button" aria-label="Attach a trip photo" title="Attach a trip photo"><i class="fa-regular fa-image" aria-hidden="true"></i></button>
        <label class="visually-hidden" for="groupMessageInput">Message your group</label>
        <input id="groupMessageInput" maxlength="2000" autocomplete="off" placeholder="Type a message..." required>
        <button type="submit" aria-label="Send group message"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
      </form>
      <p id="groupChatStatus" class="groups-chat-status" role="status" aria-live="polite"></p>
      <p id="groupLocationStatus" class="groups-chat-status" role="status" aria-live="polite"></p>
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
<div class="groups-dialog" id="createGroupDialog" role="dialog" aria-modal="true" aria-labelledby="createGroupTitle" hidden>
  <form class="groups-dialog-card" id="createGroupForm">
    <button class="groups-dialog-close" type="button" data-close-group-dialog aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">START YOUR CIRCLE</span><h2 id="createGroupTitle">Create a group</h2>
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
<?php if ($selectedGroup): ?>
<div class="groups-dialog" id="groupSettingsDialog" role="dialog" aria-modal="true" aria-labelledby="groupSettingsTitle" hidden>
  <section class="groups-dialog-card groups-settings-card">
    <button class="groups-dialog-close" type="button" data-close-group-dialog aria-label="Close group settings"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">GROUP DETAILS</span>
    <h2 id="groupSettingsTitle"><?= htmlspecialchars($selectedGroup['name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="groups-settings-summary"><?= htmlspecialchars($selectedGroup['description'] ?: 'A private place for this group to stay connected.', ENT_QUOTES, 'UTF-8') ?></p>
    <div class="groups-settings-actions">
      <?php if (in_array($selectedGroup['role'], ['owner', 'admin'], true)): ?>
        <button type="button" class="explore-panel-action" id="groupSettingsInvite"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Add people</button>
      <?php endif; ?>
      <a class="groups-trusted-link" href="trusted-contacts.php"><i class="fa-solid fa-user-shield" aria-hidden="true"></i>Trusted contacts</a>
    </div>
    <div class="groups-member-list" aria-label="Group members">
      <h3>Members · <?= count($members) ?></h3>
      <?php foreach ($members as $member): ?>
        <div class="groups-member-row" data-group-member="<?= (int) $member['id'] ?>">
          <span class="groups-member-avatar"><?= st_avatar_inner($member) ?></span>
          <span class="groups-member-copy"><strong><?= htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(ucfirst($member['role']), ENT_QUOTES, 'UTF-8') ?></small></span>
          <?php if (
              in_array($selectedGroup['role'], ['owner', 'admin'], true)
              && $member['role'] !== 'owner'
              && ($selectedGroup['role'] === 'owner' || $member['role'] === 'member')
          ): ?>
            <button type="button" class="groups-member-remove" data-remove-group-member="<?= (int) $member['id'] ?>" data-member-name="<?= htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Remove <?= htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-user-minus" aria-hidden="true"></i></button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="groups-form-status" id="groupSettingsStatus" role="status" aria-live="polite"></p>
    <button type="button" class="groups-leave-button" id="leaveGroupButton">Leave group</button>
  </section>
</div>
<?php endif; ?>
<?php if ($selectedGroup && in_array($selectedGroup['role'], ['owner', 'admin'], true)): ?>
<div class="groups-dialog" id="inviteGroupDialog" role="dialog" aria-modal="true" aria-labelledby="inviteGroupTitle" hidden>
  <form class="groups-dialog-card" id="groupInviteForm">
    <button class="groups-dialog-close" type="button" data-close-group-dialog aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">INVITE MEMBERS</span><h2 id="inviteGroupTitle">Add people to your group</h2>
    <label for="groupInviteUsername">Invite by username</label>
    <input id="groupInviteUsername" maxlength="40" autocomplete="off" placeholder="e.g. safari_friend" required>
    <p class="groups-invite-help">They will receive a private invitation and choose whether to join.</p>
    <p class="groups-form-status" id="groupInviteStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Invite username</button>
    <div class="groups-invite-divider" aria-hidden="true">OR SHARE A LINK</div>
    <label for="groupInviteEmail">Email a reusable invite link <span>(optional)</span></label>
    <input id="groupInviteEmail" type="email" placeholder="friend@example.com">
    <button class="explore-panel-action groups-share-link-button" type="button" id="createGroupInviteLink">Create share link</button>
    <div class="groups-invite-result" id="groupInviteResult" hidden>
      <label for="groupInviteUrl" id="groupInviteUrlLabel">Invitation link · reusable for 7 days</label>
      <div><input type="url" id="groupInviteUrl" readonly><button type="button" id="copyGroupInvite">Copy</button></div>
    </div>
  </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
<script src="groups.js?v=<?= hash_file('sha256', __DIR__ . '/groups.js') ?>"></script>
