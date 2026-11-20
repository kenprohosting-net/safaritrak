<?php
require __DIR__ . '/backend/includes/auth-guard.php';

$partnerPortalUrl = 'org-signup.php?type=car_hire';
$partnerPortalDescription = 'Register a car-hire organization';
if (($currentUser['role'] ?? '') === 'org_admin') {
    $partnerOrgStmt = $db->prepare(
        'SELECT p.account_type, p.review_status
         FROM organization_admins oa
         JOIN organization_profiles p ON p.organization_id = oa.organization_id
         WHERE oa.user_id = ? LIMIT 1'
    );
    $partnerOrgStmt->execute([(int) $currentUser['id']]);
    $partnerStatus = $partnerOrgStmt->fetch(PDO::FETCH_ASSOC);
    if ($partnerStatus && $partnerStatus['account_type'] === 'car_hire') {
        $partnerPortalUrl = $partnerStatus['review_status'] === 'approved' ? 'fleet.php' : 'org-dashboard.php';
        $partnerPortalDescription = $partnerStatus['review_status'] === 'approved'
            ? 'Open your fleet dashboard'
            : 'View your organization verification status';
    }
}

$pageTitle = 'Car hire';
$pageHeading = 'Car hire';
$activeNav = 'car-hire';
$loadNotifications = true;
$appMapPage = 'car-hire';
$isCarHireMarketplace = true;
$isRentalWorkspace = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="rental-page" aria-label="Car hire marketplace">
  <div class="rental-marketplace-panel">
    <header class="rental-marketplace-heading">
      <h1>Car Hire</h1>
      <a class="rental-partner-link" href="<?= htmlspecialchars($partnerPortalUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($partnerPortalDescription, ENT_QUOTES, 'UTF-8') ?>">
        Partner Portal <span>· List Fleet</span> <i class="fa-solid fa-plus" aria-hidden="true"></i>
      </a>
    </header>

    <div class="rental-date-bar">
      <label class="rental-date-location" for="rentalPickupArea">
        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        <span class="visually-hidden">Pickup area</span>
        <input id="rentalPickupArea" type="search" value="Nairobi" placeholder="Pickup area">
      </label>
      <label class="rental-date-field" for="rentalPickupDate">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <span class="visually-hidden">Pickup date</span>
        <input id="rentalPickupDate" type="date">
      </label>
      <span class="rental-date-separator" aria-hidden="true">–</span>
      <label class="rental-date-field" for="rentalReturnDate">
        <span class="visually-hidden">Return date</span>
        <input id="rentalReturnDate" type="date">
      </label>
    </div>

    <div class="rental-filter-row" role="group" aria-label="Quick vehicle filters">
      <button type="button" class="is-active" data-rental-filter="all" aria-pressed="true">All</button>
      <button type="button" data-rental-filter="suv" aria-pressed="false">SUV</button>
      <button type="button" data-rental-filter="sedan" aria-pressed="false">Sedan</button>
      <button type="button" data-rental-filter="7-seater" aria-pressed="false">7-Seater</button>
      <button type="button" data-rental-filter="automatic" aria-pressed="false">Automatic</button>
      <button type="button" data-rental-filter="price" aria-pressed="false" aria-label="Sort by price">Price <i class="fa-solid fa-arrow-down-short-wide" aria-hidden="true"></i></button>
    </div>

    <label class="rental-search" for="rentalSearch">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input id="rentalSearch" type="search" placeholder="Search company, make or model" autocomplete="off">
    </label>
    <p class="rental-status" id="rentalStatus" role="status" aria-live="polite">Loading available vehicles…</p>
    <div class="rental-vehicle-list" id="rentalVehicles" aria-live="polite"></div>
    <p class="rental-map-status" id="rentalMapStatus" role="status" aria-live="polite">Vehicle pickup locations appear on the map when provided by partners.</p>

    <details class="rental-my-bookings">
      <summary><span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> My booking requests</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
      <div id="myRentalBookings" class="rental-booking-list"><p class="rental-empty">Loading your requests…</p></div>
    </details>
  </div>

  <aside class="rental-preview" id="rentalPreview" aria-label="Selected vehicle details" hidden>
    <button class="rental-preview-close" type="button" id="rentalPreviewClose" aria-label="Close vehicle preview"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <div id="rentalPreviewContent"></div>
  </aside>
</section>

<div class="groups-dialog rental-dialog" id="rentalBookingDialog" hidden>
  <form class="groups-dialog-card" id="rentalBookingForm">
    <button class="groups-dialog-close" type="button" data-close-rental aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">BOOKING REQUEST</span><h2 id="rentalSelectedVehicle"></h2>
    <p class="rental-dialog-note">This request goes to the verified vehicle provider. A booking is confirmed only after the provider approves and agrees the final details with you.</p>
    <input type="hidden" id="rentalVehicleId">
    <label for="rentalPickup">Pickup</label><input id="rentalPickup" type="datetime-local" required>
    <label for="rentalReturn">Return</label><input id="rentalReturn" type="datetime-local" required>
    <label for="rentalNote">Note <span>(optional)</span></label><textarea id="rentalNote" maxlength="500" rows="3" placeholder="Pickup area or other details"></textarea>
    <p class="groups-form-status" id="rentalBookingStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Send booking request</button>
  </form>
</div>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
<script src="car-hire.js?v=<?= hash_file('sha256', __DIR__ . '/car-hire.js') ?>"></script>
