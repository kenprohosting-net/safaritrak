<?php
require __DIR__ . '/backend/includes/auth-guard.php';
require_once __DIR__ . '/backend/includes/helpers.php';
require_once __DIR__ . '/backend/includes/consent.php';

$profileCountsStmt = $db->prepare(
    'SELECT
        (SELECT COUNT(*) FROM journeys WHERE user_id = ?) AS journey_count,
        (SELECT COUNT(*) FROM trusted_contacts WHERE owner_id = ? AND status = "confirmed") AS trusted_count'
);
$profileCountsStmt->execute([$currentUser['id'], $currentUser['id']]);
$profileCounts = $profileCountsStmt->fetch() ?: ['journey_count' => 0, 'trusted_count' => 0];

$consentVersions = st_current_consent_versions();
$consentStmt = $db->prepare(
    'SELECT consent_type, document_version, accepted_at
     FROM privacy_consents WHERE user_id = ? ORDER BY accepted_at DESC'
);
$consentStmt->execute([$currentUser['id']]);
$consentRecords = $consentStmt->fetchAll();
$acceptedConsentVersions = [];
foreach ($consentRecords as $consentRecord) {
    $acceptedConsentVersions[$consentRecord['consent_type']] ??= $consentRecord['document_version'];
}
$currentConsentAccepted = true;
foreach ($consentVersions as $consentType => $consentVersion) {
    if (($acceptedConsentVersions[$consentType] ?? null) !== $consentVersion) {
        $currentConsentAccepted = false;
        break;
    }
}

$appMapPage = 'profile';
$showAppMapControls = false;
$showAppMapPanelsToggle = false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafariTrak | Account</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="account-workspace.css?v=<?= hash_file('sha256', __DIR__ . '/account-workspace.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--workspace app--account-workspace">
<?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

<main>
<div class="content account-workspace-content">

<section class="account-profile-card" aria-label="Account profile">
<section class="account-profile-hero" aria-labelledby="accountProfileName">
  <div class="account-profile-cover">
    <span><i class="fa-solid fa-compass" aria-hidden="true"></i> SAFARITRAK PROFILE</span>
    <p>Every journey is better when your people know you are safe.</p>
  </div>
  <div class="account-profile-body">
    <div class="account-profile-avatar" id="accountProfileAvatar"><?= st_avatar_inner($currentUser) ?></div>
    <div class="account-profile-identity">
      <h2 id="accountProfileName"><?= htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8') ?></h2>
      <p><span id="accountProfileUsername">@<?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></span> <span>·</span> <?= ($currentUser['role'] ?? '') === 'org_admin' ? 'Organization account' : (($currentUser['role'] ?? '') === 'platform_admin' ? 'Platform administrator' : 'SafariTrak traveler') ?></p>
    </div>
    <div class="account-profile-stats" aria-label="Profile activity">
      <div><i class="fa-solid fa-route" aria-hidden="true"></i><strong><?= (int) $profileCounts['journey_count'] ?></strong><span>Journeys</span></div>
      <div><i class="fa-solid fa-user-shield" aria-hidden="true"></i><strong><?= (int) $profileCounts['trusted_count'] ?></strong><span>Trusted people</span></div>
    </div>
    <div class="account-profile-actions">
      <button class="btn-primary" type="button" data-open-modal="editProfileModal"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit profile</button>
    </div>
  </div>
</section>
</section>

<section class="account-settings-grid" aria-label="Account settings and support">
  <div class="account-settings-column">
    <h2>Profile &amp; Safety Settings</h2>
    <a class="account-setting-link" href="trusted-contacts.php">
      <span class="account-setting-icon"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i></span>
      <span><b>Emergency Contacts</b><small>Manage trusted contacts &amp; SOS alerts</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </a>
    <a class="account-setting-link" href="trusted-contacts.php">
      <span class="account-setting-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
      <span><b>Live Location Share Settings</b><small>Control who sees your active route</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </a>
    <button class="account-setting-link" type="button" data-open-modal="editProfileModal" data-settings-tab="privacy">
      <span class="account-setting-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
      <span><b>Profile Privacy</b><small>Manage visibility &amp; account security</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </button>
  </div>
  <div class="account-settings-column">
    <h2>Journey History &amp; Support</h2>
    <a class="account-setting-link" href="my-journeys.php">
      <span class="account-setting-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
      <span><b>Recent Journeys</b><small>View past trips &amp; saved routes</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </a>
    <a class="account-setting-link" href="car-hire.php#myRentalBookings">
      <span class="account-setting-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
      <span><b>Payment Methods</b><small>Review car hire requests &amp; provider payment terms</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </a>
    <button class="account-setting-link" type="button" data-account-support aria-expanded="false">
      <span class="account-setting-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span>
      <span><b>Help &amp; Feedback</b><small>Support center &amp; app feedback</small></span>
      <i class="fa-solid fa-chevron-right account-setting-chevron" aria-hidden="true"></i>
    </button>
    <p class="account-support-note" id="accountSupportNote" role="status" hidden>In-app help and feedback are not available in this build yet. For account questions, contact your SafariTrak service administrator.</p>
  </div>
</section>

<div class="modal-overlay account-edit-modal" id="editProfileModal">
  <div class="modal account-edit-modal-card">
    <div class="modal-head">
      <div><h3>Edit your account</h3><p>Update your profile and account preferences.</p></div>
      <button class="modal-close" type="button" data-close-modal aria-label="Close profile editor"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
<div class="card">
  <div class="settings-tabs" data-tab-group="settings">
    <button type="button" class="tab active" data-tab="profile">Profile</button>
    <button type="button" class="tab" data-tab="notifications">Notifications</button>
    <button type="button" class="tab" data-tab="privacy">Privacy</button>
    <button type="button" class="tab" data-tab="account">Account</button>
  </div>

  <div class="settings-panel active" data-tab-panel-group="settings" data-tab-panel="profile">
    <div class="avatar-row">
      <div class="big-avatar" id="profileAvatarPreview"><?= st_avatar_inner($currentUser) ?></div>
      <div>
        <input type="file" id="avatarFileInput" accept="image/png,image/jpeg,image/webp" style="display:none">
        <button type="button" class="btn-ghost" id="changePhotoBtn">Change photo</button>
        <?php if (!empty($currentUser['avatar_path'])): ?>
        <button type="button" class="btn-ghost" id="removePhotoBtn" style="color:#c94b4b">Remove photo</button>
        <?php endif; ?>
        <p class="hint" style="margin-top:8px">JPG, PNG or WEBP, up to 4 MB</p>
      </div>
    </div>

    <p class="form-message" id="profileMessage" style="display:none"></p>
    <section class="settings-account-shortcut" aria-label="Trusted people">
      <div>
        <strong>Trusted people &amp; trip sharing</strong>
        <p>Manage friend requests, journey access, and temporary share links.</p>
      </div>
      <a class="btn-ghost" href="trusted-contacts.php"><i class="fa-solid fa-user-group"></i>Manage trusted people</a>
    </section>

    <div class="form-grid" style="padding:0">
      <div class="form-field">
        <label>Full name</label>
        <input type="text" id="settingFullName" value="<?= htmlspecialchars($currentUser['full_name']) ?>">
        <p class="field-error" id="settingFullNameError"></p>
      </div>
      <div class="form-field">
        <label>Username</label>
        <input type="text" id="settingUsername" value="<?= htmlspecialchars($currentUser['username']) ?>">
        <p class="field-error" id="settingUsernameError"></p>
      </div>
      <div class="form-field">
        <label>Phone number</label>
        <input type="tel" id="settingPhone" placeholder="0712 345 678" value="<?= htmlspecialchars(st_display_phone($currentUser['phone'])) ?>">
        <p class="field-error" id="settingPhoneError"></p>
      </div>
      <div class="form-field">
        <label>Email address</label>
        <input type="email" id="settingEmail" placeholder="you@example.com" value="<?= htmlspecialchars($currentUser['email']) ?>">
        <p class="field-error" id="settingEmailError"></p>
      </div>
      <div class="form-field full">
        <label>Home address</label>
        <input type="text" id="settingHomeAddress" placeholder="Used to suggest your usual routes" value="<?= htmlspecialchars($currentUser['home_address'] ?? '') ?>">
        <p class="field-error" id="settingHomeAddressError"></p>
      </div>
    </div>
    <div class="form-actions" style="padding-left:0;padding-right:0">
      <button type="button" class="btn-primary" id="saveProfileBtn">Save changes</button>
    </div>
  </div>

  <div class="settings-panel" data-tab-panel-group="settings" data-tab-panel="notifications">
    <div class="toggle-row"><span><b>Journey started and completed</b><small>Get notified when your journeys begin and end</small></span><label class="toggle"><input type="checkbox" data-setting="arrival_notifications" <?= $currentUser['arrival_notifications'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="toggle-row"><span><b>New messages</b><small>Get notified when a trusted contact sends you a message</small></span><label class="toggle"><input type="checkbox" checked disabled title="Coming soon"><span></span></label></div>
    <div class="toggle-row"><span><b>Route deviation</b><small>Get notified if you move off your planned route</small></span><label class="toggle"><input type="checkbox" data-setting="route_deviation_alerts" <?= $currentUser['route_deviation_alerts'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="toggle-row"><span><b>SOS alerts from contacts</b><small>Get notified if someone you are watching sends an SOS</small></span><label class="toggle"><input type="checkbox" data-setting="sos_alert_notifications" <?= $currentUser['sos_alert_notifications'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="toggle-row"><span><b>Product updates</b><small>Occasional emails about new SafariTrak features</small></span><label class="toggle"><input type="checkbox" data-setting="product_update_emails" <?= $currentUser['product_update_emails'] ? 'checked' : '' ?>><span></span></label></div>
  </div>

  <div class="settings-panel" data-tab-panel-group="settings" data-tab-panel="privacy">
    <div class="toggle-row"><span><b>Show my journey history to trusted contacts</b><small>They can see your past trips, not just live journeys</small></span><label class="toggle"><input type="checkbox" data-setting="show_history_to_contacts" <?= $currentUser['show_history_to_contacts'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="toggle-row"><span><b>Allow group journeys</b><small>Let organizers add you to group trips</small></span><label class="toggle"><input type="checkbox" data-setting="allow_group_journeys" <?= $currentUser['allow_group_journeys'] ? 'checked' : '' ?>><span></span></label></div>
    <div class="toggle-row"><span><b>Discoverable by phone number</b><small>Let people find and add you as a trusted contact using your number</small></span><label class="toggle"><input type="checkbox" data-setting="discoverable_by_phone" <?= $currentUser['discoverable_by_phone'] ? 'checked' : '' ?>><span></span></label></div>
    <section class="settings-consent" aria-labelledby="consentHeading">
      <h3 id="consentHeading">Terms and privacy consent</h3>
      <p class="hint" id="consentStatus" role="status">
        <?php if ($currentConsentAccepted): ?>
          You accepted the current Terms (<?= htmlspecialchars($consentVersions['terms']) ?>) and Privacy Policy (<?= htmlspecialchars($consentVersions['privacy']) ?>).
        <?php else: ?>
          We do not have recorded acceptance of the current documents for this account. Review and accept both to record your current consent.
        <?php endif; ?>
      </p>
      <?php if (!$currentConsentAccepted): ?>
      <div class="consent-acceptance" id="consentAcceptance">
        <label><input type="checkbox" id="acceptCurrentTerms"> I have read and agree to the <a href="terms.html" target="_blank" rel="noopener">Terms &amp; Conditions</a> (<?= htmlspecialchars($consentVersions['terms']) ?>).</label>
        <label><input type="checkbox" id="acceptCurrentPrivacy"> I have read the <a href="privacy.html" target="_blank" rel="noopener">Privacy Policy</a> (<?= htmlspecialchars($consentVersions['privacy']) ?>).</label>
        <p class="field-error" id="consentError"></p>
        <button type="button" class="btn-ghost" id="saveConsentBtn">Record acceptance</button>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="settings-panel" data-tab-panel-group="settings" data-tab-panel="account">
    <p class="form-message" id="passwordMessage" style="display:none"></p>
    <div class="form-field" style="max-width:360px;margin-bottom:14px">
      <label>Current password</label>
      <input type="password" id="settingCurrentPassword" placeholder="Enter current password" autocomplete="current-password">
      <p class="field-error" id="settingCurrentPasswordError"></p>
    </div>
    <div class="form-field" style="max-width:360px;margin-bottom:14px">
      <label>New password</label>
      <input type="password" id="settingNewPassword" placeholder="At least 12 characters" minlength="12" maxlength="1024" autocomplete="new-password">
      <p class="field-error" id="settingNewPasswordError"></p>
    </div>
    <div class="form-field" style="max-width:360px;margin-bottom:14px">
      <label>Confirm new password</label>
      <input type="password" id="settingConfirmPassword" placeholder="Re-enter new password" maxlength="1024" autocomplete="new-password">
      <p class="field-error" id="settingConfirmPasswordError"></p>
    </div>
    <button type="button" class="btn-primary" id="updatePasswordBtn">Update password</button>
    <div class="settings-export">
      <h3>Download your data</h3>
      <p class="hint">Download a private JSON copy of your profile, journeys and location history, contacts, messages, account roles, and recorded consent.</p>
      <a class="btn-ghost" href="backend/api/export-data.php" download><i class="fa-solid fa-download"></i>Download my data</a>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:22px 0">
    <button type="button" class="btn-ghost" style="color:#c94b4b;border-color:#f3d4d4" data-open-modal="deleteAccountModal">Delete my account</button>
    <hr style="border:0;border-top:1px solid var(--line);margin:22px 0">
    <a class="btn-ghost" href="org-dashboard.php"><i class="fa-solid fa-building"></i>Manage an organization</a>
  </div>

</div>
  </div>
  </div>
  </div>

</div>
<footer>&copy; <?= date('Y') ?> SafariTrak <span>Navigate. Track. Share. Connect. Stay Safe.</span></footer>
</main>
</div>

<div class="modal-overlay" id="deleteAccountModal">
  <div class="modal">
    <div class="modal-head"><div><h3>Delete your account?</h3><p>This permanently removes your journeys, contacts and messages. Group trips you organize may also be removed for participants.</p></div><button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <p>This cannot be undone. Platform and organization administrators must transfer or remove their duties first. Enter your password to confirm.</p>
      <div class="form-field" style="margin-top:10px">
        <input type="password" id="deleteAccountPassword" placeholder="Your password">
        <p class="field-error" id="deleteAccountPasswordError"></p>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button type="button" class="danger" id="confirmDeleteAccountBtn">Delete account</button>
    </div>
  </div>
</div>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="settings.js"></script>
</body>
</html>
