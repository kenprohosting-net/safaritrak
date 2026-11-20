<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$contactsStmt = $db->prepare(
    'SELECT tc.id, tc.invite_name, tc.invite_phone, tc.relationship, tc.status,
            tc.share_live_location, tc.journey_alerts, tc.sos_alerts, tc.contact_user_id,
            u.full_name AS linked_name
     FROM trusted_contacts tc
     LEFT JOIN users u ON u.id = tc.contact_user_id
     WHERE tc.owner_id = ? AND tc.status != "declined"
     ORDER BY FIELD(tc.status, "confirmed", "pending"), tc.created_at DESC'
);
$contactsStmt->execute([$currentUser['id']]);
$contacts = $contactsStmt->fetchAll();

$incomingStmt = $db->prepare(
    'SELECT tc.id, tc.relationship, u.full_name AS owner_name
     FROM trusted_contacts tc
     JOIN users u ON u.id = tc.owner_id
     WHERE tc.contact_user_id = ? AND tc.status = "pending"
     ORDER BY tc.created_at DESC'
);
$incomingStmt->execute([$currentUser['id']]);
$incoming = $incomingStmt->fetchAll();
$showAppMapControls = false;
$showAppMapPanelsToggle = false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Trusted Contacts</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="trusted-contacts.css?v=<?= hash_file('sha256', __DIR__ . '/trusted-contacts.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--trusted-contacts">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<header>
  <button class="menu" id="menu"><i class="fa-solid fa-bars"></i></button>
  <div><label>PEOPLE YOU TRUST</label><h1>Trusted Contacts</h1></div>
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

<div class="trusted-contacts-workspace">
<section class="trusted-people-panel" aria-label="Trusted people">
  <div class="page-head">
    <div><h2>Who can keep an eye on you</h2><p>Manage who can see your journeys and get notified if something goes wrong.</p></div>
    <button type="button" class="btn-primary" data-open-modal="addContactModal"><i class="fa-solid fa-user-plus"></i>Add trusted contact</button>
  </div>

<?php if (!empty($incoming)): ?>
<div class="card trusted-requests-panel">
  <div class="card-head"><div><label>REQUESTS</label><h3>People who want to trust you</h3></div></div>
  <div class="journey-list">
    <?php foreach ($incoming as $req): ?>
    <div class="journey-row" style="cursor:default">
      <div class="jicon"><i class="fa-solid fa-user-plus"></i></div>
      <div class="jinfo"><b><?= htmlspecialchars($req['owner_name']) ?></b><small><?= htmlspecialchars($req['relationship'] ?: 'Wants to add you as a trusted contact') ?></small></div>
      <div class="jmeta" style="display:flex;gap:8px">
        <button type="button" class="btn-ghost respond-btn" data-id="<?= (int) $req['id'] ?>" data-action="decline">Decline</button>
        <button type="button" class="btn-primary respond-btn" data-id="<?= (int) $req['id'] ?>" data-action="confirm">Accept</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="contacts-grid">

  <?php foreach ($contacts as $contact): ?>
  <?php
    $displayName = $contact['linked_name'] ?: $contact['invite_name'];
    $isLinked = $contact['contact_user_id'] !== null;
    $isConfirmed = $contact['status'] === 'confirmed';
    $statusLabel = $isConfirmed ? 'Confirmed' : ($isLinked ? 'Pending confirmation' : 'Invited, not on SafariTrak yet');
  ?>
  <div class="contact-card" data-contact-id="<?= (int) $contact['id'] ?>" data-contact-name="<?= htmlspecialchars($displayName) ?>">
    <div class="top"><span class="person"><?= htmlspecialchars(st_initials($displayName)) ?></span><div><b><?= htmlspecialchars($displayName) ?></b><small><?= htmlspecialchars($contact['relationship'] ?: 'Trusted contact') ?> &middot; <?= htmlspecialchars($statusLabel) ?></small></div></div>
    <div class="permission-row"><span>See my live location</span><label class="toggle"><input type="checkbox" class="perm-toggle" data-field="share_live_location" <?= $contact['share_live_location'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="permission-row"><span>Journey start and end alerts</span><label class="toggle"><input type="checkbox" class="perm-toggle" data-field="journey_alerts" <?= $contact['journey_alerts'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="permission-row"><span>SOS alerts</span><label class="toggle"><input type="checkbox" class="perm-toggle" data-field="sos_alerts" <?= $contact['sos_alerts'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="card-buttons">
      <?php if ($isLinked && $isConfirmed): ?>
      <a href="messages.php?to=<?= (int) $contact['contact_user_id'] ?>" class="btn-ghost">Message</a>
      <?php else: ?>
      <button type="button" class="btn-ghost" disabled style="opacity:.5;cursor:not-allowed">Message</button>
      <?php endif; ?>
      <button type="button" class="btn-ghost remove-btn">Remove</button>
    </div>
  </div>
  <?php endforeach; ?>

  <button type="button" class="add-contact-card" data-open-modal="addContactModal">
    <i class="fa-solid fa-user-plus"></i>
    <span>Add someone new</span>
  </button>

</div>

<?php if (empty($contacts)): ?>
<p class="hint contacts-empty-note">You have not added any trusted contacts yet. Add someone so they can watch your journeys and step in if you need help.</p>
<?php endif; ?>

</section>

<section class="card live-share-card trusted-sharing-panel" aria-labelledby="liveShareTitle">
  <div>
    <label>SHARE A JOURNEY OR CONNECT</label>
    <h3 id="liveShareTitle">Give someone a simple way to follow you</h3>
    <p>Share a temporary journey link with anyone, or send a sign-up link so they can join SafariTrak and become a trusted contact. You can create and copy either link below.</p>
  </div>
  <div class="live-share-options">
    <div class="live-share-option">
      <h4>View a temporary journey</h4>
      <p>Anyone with the link can view your active or scheduled journey. It expires automatically and can be revoked.</p>
      <div class="live-share-actions">
        <button type="button" class="btn-primary" id="createLiveShareBtn">Create journey link</button>
        <button type="button" class="btn-ghost" id="revokeLiveShareBtn" disabled>Revoke link</button>
      </div>
      <input type="url" id="liveShareUrl" aria-label="Temporary journey link" readonly hidden>
      <div class="live-share-actions" id="liveShareLinkActions" hidden>
        <button type="button" class="btn-ghost" id="copyLiveShareBtn">Copy journey link</button>
        <button type="button" class="btn-ghost" id="shareLiveShareBtn">Share link</button>
      </div>
    </div>
    <div class="live-share-option">
      <h4>Join as a trusted contact</h4>
      <p>No name, phone number, or email is needed to create this sign-up invitation.</p>
      <div class="live-share-actions">
        <button type="button" class="btn-ghost" id="createSignupInviteBtn">Create sign-up link</button>
        <button type="button" class="btn-ghost" id="copySignupInviteBtn" disabled>Copy sign-up link</button>
      </div>
      <input type="url" id="signupInviteUrl" aria-label="Trusted-contact sign-up link" readonly hidden>
    </div>
  </div>
  <p id="liveShareStatus" role="status" aria-live="polite"></p>
</section>
</div>
</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="addContactModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Add a trusted contact</h3><p>Invite someone to keep track of your journeys.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <section class="contact-finder" aria-labelledby="contactFinderHeading">
        <h4 id="contactFinderHeading">Find a SafariTrak friend</h4>
        <p>Enter a SafariTrak username to send a request. You’ll be connected when they accept.</p>
        <label class="form-field" for="contactSearchInput">
          <span>Search people</span>
          <input type="search" id="contactSearchInput" autocomplete="off" minlength="2" maxlength="60" placeholder="SafariTrak username">
        </label>
        <p class="contact-search-status" id="contactSearchStatus" role="status" aria-live="polite"></p>
        <div class="contact-search-results" id="contactSearchResults"></div>
      </section>
      <div class="contact-invite-divider"><span>or create a link for someone new</span></div>
      <p class="hint">A name and email are optional. The invitation link lets them sign up and accept your trusted-contact request.</p>
      <div class="form-field" style="margin-bottom:12px"><label for="addContactName">Name (optional)</label><input type="text" id="addContactName" maxlength="120" placeholder="SafariTrak friend"></div>
      <div class="form-field" style="margin-bottom:12px"><label for="addContactEmail">Email (optional)</label><input type="email" id="addContactEmail" maxlength="160" placeholder="name@example.com"></div>
      <div class="form-field"><label for="addContactRelationship">Relationship (optional)</label><input type="text" id="addContactRelationship" maxlength="60" placeholder="e.g. Sister, Friend, Colleague"></div>
      <p class="hint" id="addContactError" role="alert" style="color:#c94b4b;margin-top:10px;display:none"></p>
      <p class="invite-status" id="addContactStatus" role="status" aria-live="polite"></p>
      <div class="invite-share-actions" id="inviteShareActions" hidden>
        <label for="inviteUrl">Invitation link (expires in 7 days)</label>
        <input type="url" id="inviteUrl" readonly>
        <div class="invite-share-buttons">
          <button type="button" class="ghost" id="copyInviteBtn">Copy link</button>
          <button type="button" class="ghost" id="messageInviteBtn">Message link</button>
          <button type="button" class="ghost" id="emailInviteBtn">Open email app</button>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="primary" id="sendInviteBtn">Create invitation link</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="removeContactModal">
  <div class="modal">
    <div class="modal-head"><div><h3 id="removeContactName">Remove this contact?</h3><p>They will no longer be able to see your journeys.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="danger" id="confirmRemoveBtn">Remove</button>
    </div>
  </div>
</div>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/notifications-widget.js') ?>"></script>
<script src="trusted-contacts.js?v=<?= hash_file('sha256', __DIR__ . '/trusted-contacts.js') ?>"></script>
</body>
</html>
