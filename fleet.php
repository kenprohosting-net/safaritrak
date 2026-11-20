<?php
require __DIR__ . '/backend/includes/org-guard.php';
if (!$myOrg || ($myOrg['account_type'] ?? '') !== 'car_hire') {
    header('Location: org-dashboard.php');
    exit;
}
$db = safaritrak_db();
$profileStmt = $db->prepare('SELECT * FROM rental_company_profiles WHERE organization_id = ?');
$profileStmt->execute([(int) $myOrg['id']]);
$companyProfile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$profileValue = static fn(string $field): string => htmlspecialchars((string) ($companyProfile[$field] ?? ''), ENT_QUOTES, 'UTF-8');
$companyServices = json_decode((string) ($companyProfile['services'] ?? '[]'), true);
$companyServices = is_array($companyServices) ? $companyServices : [];
$rentalServices = [
    'self_drive_car_hire' => 'Self-drive car hire',
    'chauffeur_car_hire' => 'Chauffeur-driven car hire',
    'airport_transfers' => 'Airport transfers',
    'corporate_transport' => 'Corporate transportation',
    'wedding_transport' => 'Wedding transportation',
    'safari_tourism_transport' => 'Safari and tourism transport',
    'long_term_hire' => 'Long-term hire',
    'short_term_hire' => 'Short-term hire',
    'event_transport' => 'Event transportation',
    'school_transport' => 'School and institutional transportation',
    'one_way_rentals' => 'One-way rentals',
];
$vehiclesStmt = $db->prepare(
    'SELECT v.*, d.quotation_required,
            (SELECT photo_url FROM rental_vehicle_photos
             WHERE vehicle_id = v.id ORDER BY photo_type = "exterior" DESC, sort_order, id LIMIT 1) AS photo_url
     FROM rental_vehicles v
     LEFT JOIN rental_vehicle_details d ON d.vehicle_id = v.id
     WHERE v.organization_id = ? ORDER BY v.created_at DESC'
);
$vehiclesStmt->execute([(int) $myOrg['id']]);
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
$bookingsStmt = $db->prepare(
    'SELECT b.id, b.vehicle_id, b.pickup_at, b.return_at, b.quoted_daily_rate, b.status, b.customer_note,
            u.full_name, u.email, u.phone, v.make, v.model, v.currency
     FROM rental_bookings b
     JOIN rental_vehicles v ON v.id = b.vehicle_id
     JOIN users u ON u.id = b.customer_id
     WHERE v.organization_id = ?
     ORDER BY FIELD(b.status, "requested", "responded", "approved", "rejected", "cancelled", "completed"), b.created_at DESC
     LIMIT 100'
);
$bookingsStmt->execute([(int) $myOrg['id']]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
$fleetSize = count($vehicles);
$newRequestCount = count(array_filter($bookings, static fn(array $booking): bool => $booking['status'] === 'requested'));
$activeRentalCount = count(array_filter($bookings, static function (array $booking): bool {
    $now = time();
    return $booking['status'] === 'approved'
        && strtotime($booking['pickup_at']) <= $now
        && strtotime($booking['return_at']) >= $now;
}));
$bookedVehicleIds = array_fill_keys(array_map(
    static fn(array $booking): int => (int) $booking['vehicle_id'],
    array_filter($bookings, static fn(array $booking): bool => $booking['status'] === 'approved')
), true);
$organizationName = (string) ($myOrg['name'] ?? 'Verified partner');
$pageTitle = 'Manage fleet';
$pageHeading = 'Fleet & bookings';
$activeNav = 'fleet';
$loadNotifications = true;
$isRentalWorkspace = true;
$isFleetDashboard = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="fleet-page" aria-label="Organization fleet management">
  <nav class="fleet-rail" aria-label="Fleet management">
    <a class="is-active" href="#fleetDashboard"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i>Dashboard</a>
    <a href="#vehicles"><i class="fa-solid fa-car" aria-hidden="true"></i>My Vehicles</a>
    <a href="#rentals"><i class="fa-solid fa-road" aria-hidden="true"></i>Rentals</a>
    <a href="#verified-orgs"><i class="fa-solid fa-shield-check" aria-hidden="true"></i>Verified Orgs</a>
    <a href="#bookings"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i>Bookings<?php if ($newRequestCount): ?><span class="fleet-rail-count"><?= $newRequestCount ?></span><?php endif; ?></a>
    <button type="button" class="rental-primary-button" id="addVehicleOpen"><i class="fa-solid fa-plus" aria-hidden="true"></i> List New Vehicle</button>
  </nav>

  <div class="fleet-workspace">
    <header class="fleet-page-heading" id="fleetDashboard">
      <div><span class="rental-eyebrow">PARTNER ORGANIZATION</span><h1>Fleet Management</h1><p>Welcome back, <?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?>. Review your listings and booking requests.</p></div>
      <span class="rental-verified-badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified Organization</span>
    </header>

    <div class="fleet-kpi-grid" aria-label="Fleet statistics">
      <article class="fleet-kpi-card"><span>Fleet Size</span><strong><?= $fleetSize ?> vehicles</strong><small>Vehicles in your organization</small></article>
      <article class="fleet-kpi-card"><span>Active Rentals</span><strong><?= $activeRentalCount ?></strong><small>Currently approved and in progress</small></article>
      <article class="fleet-kpi-card"><span>Total Earnings (KES)</span><strong>Tracked offline</strong><small>Payments are handled directly with customers</small></article>
      <article class="fleet-kpi-card"><span>New Requests</span><strong><?= $newRequestCount ?></strong><small>Waiting for your review</small></article>
    </div>

    <section class="fleet-dashboard-card fleet-overview" id="rentals">
      <header><div><span class="rental-eyebrow">ORGANIZATION</span><h2>Fleet Overview</h2></div><a class="rental-secondary-button" href="#vehicles">Manage Vehicles</a></header>
      <div class="fleet-table-scroll">
        <table>
          <thead><tr><th>Registered Organization</th><th>Vehicles Posted</th><th>Active Rentals</th><th>Org Verified?</th><th>Action</th></tr></thead>
          <tbody><tr>
            <td><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-circle-check fleet-check-icon" aria-label="Verified"></i></td>
            <td><?= $fleetSize ?></td><td><?= $activeRentalCount ?></td>
            <td><span class="fleet-status-pill is-verified">Verified</span></td>
            <td><a href="#vehicles" class="fleet-manage-link">Manage Vehicles</a></td>
          </tr></tbody>
        </table>
      </div>
    </section>

    <details class="fleet-company-profile fleet-dashboard-card" id="verified-orgs">
    <summary><span><span class="rental-eyebrow">VERIFIED ORGANIZATION</span><strong>Company profile and pickup locations</strong></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
    <form id="companyProfileForm" class="fleet-profile-form">
      <div class="form-grid">
        <label>Business description<textarea name="description" maxlength="2000" rows="3"><?= $profileValue('description') ?></textarea></label>
        <label>Business email<input type="email" name="business_email" value="<?= $profileValue('business_email') ?>" maxlength="160"></label>
        <label>Official telephone<input type="tel" name="official_phone" value="<?= $profileValue('official_phone') ?>" maxlength="32"></label>
        <label>WhatsApp number<input type="tel" name="whatsapp" value="<?= $profileValue('whatsapp') ?>" maxlength="32"></label>
        <label>Website (HTTPS)<input type="url" name="website" value="<?= $profileValue('website') ?>" maxlength="500"></label>
        <label>Logo image URL (HTTPS)<input type="url" name="logo_url" value="<?= $profileValue('logo_url') ?>" maxlength="500"></label>
        <label>Cover photo URL (HTTPS)<input type="url" name="cover_photo_url" value="<?= $profileValue('cover_photo_url') ?>" maxlength="500"></label>
        <label>Year established<input type="number" name="established_year" min="1800" max="<?= (int) date('Y') ?>" value="<?= $profileValue('established_year') ?>"></label>
        <label>Physical office location<input name="office_location" value="<?= $profileValue('office_location') ?>" maxlength="500"></label>
        <label>Town / city<input name="town" value="<?= $profileValue('town') ?>" maxlength="120"></label>
        <label>Default pickup latitude<input type="number" name="office_lat" min="-90" max="90" step="any" value="<?= $profileValue('office_lat') ?>"></label>
        <label>Default pickup longitude<input type="number" name="office_lng" min="-180" max="180" step="any" value="<?= $profileValue('office_lng') ?>"></label>
        <label>Counties / regions served<textarea name="regions_served" maxlength="1000" rows="2"><?= $profileValue('regions_served') ?></textarea></label>
        <label>Operating hours<input name="operating_hours" value="<?= $profileValue('operating_hours') ?>" maxlength="500"></label>
        <label>Service coverage<textarea name="coverage_description" maxlength="1000" rows="2"><?= $profileValue('coverage_description') ?></textarea></label>
        <label>Pickup locations<textarea name="pickup_locations" maxlength="1000" rows="2"><?= $profileValue('pickup_locations') ?></textarea></label>
        <label>Social media links<textarea name="social_links" maxlength="2000" rows="2"><?= $profileValue('social_links') ?></textarea></label>
      </div>
      <fieldset><legend>Services offered</legend>
        <?php foreach ($rentalServices as $service => $label): ?>
          <label class="fleet-check"><input type="checkbox" name="services" value="<?= htmlspecialchars($service) ?>" <?= in_array($service, $companyServices, true) ? 'checked' : '' ?>><?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset><legend>Rental terms provided by your business</legend>
        <div class="form-grid">
          <label>Minimum driver age<input type="number" name="minimum_driver_age" min="16" max="99" value="<?= $profileValue('minimum_driver_age') ?>"></label>
          <label>Minimum driving experience (years)<input type="number" name="minimum_driving_experience" min="0" max="60" value="<?= $profileValue('minimum_driving_experience') ?>"></label>
          <label>Minimum rental days<input type="number" name="minimum_rental_days" min="1" value="<?= $profileValue('minimum_rental_days') ?>"></label>
          <label>Maximum rental days<input type="number" name="maximum_rental_days" min="1" value="<?= $profileValue('maximum_rental_days') ?>"></label>
          <label>Identification requirements<input name="identification_requirements" value="<?= $profileValue('identification_requirements') ?>"></label>
          <label>Booking notice<input name="booking_notice" value="<?= $profileValue('booking_notice') ?>"></label>
          <label>Fuel return policy<input name="fuel_return_policy" value="<?= $profileValue('fuel_return_policy') ?>"></label>
          <label>Mileage restrictions<input name="mileage_restrictions" value="<?= $profileValue('mileage_restrictions') ?>"></label>
          <label>Late return policy<input name="late_return_policy" value="<?= $profileValue('late_return_policy') ?>"></label>
          <label>Cancellation policy<textarea name="cancellation_policy" rows="2"><?= $profileValue('cancellation_policy') ?></textarea></label>
          <label>Refund policy<textarea name="refund_policy" rows="2"><?= $profileValue('refund_policy') ?></textarea></label>
        </div>
        <label class="fleet-check"><input type="checkbox" name="valid_licence_required" value="1" <?= !array_key_exists('valid_licence_required', $companyProfile) || (int) $companyProfile['valid_licence_required'] === 1 ? 'checked' : '' ?>>Valid driving licence required</label>
        <label class="fleet-check"><input type="checkbox" name="cross_border_available" value="1" <?= !empty($companyProfile['cross_border_available']) ? 'checked' : '' ?>>Cross-border travel available</label>
      </fieldset>
      <fieldset><legend>Location and safety</legend>
        <div class="form-grid">
          <label>Emergency contact number<input name="emergency_number" value="<?= $profileValue('emergency_number') ?>"></label>
          <label>Emergency assistance arrangements<textarea name="emergency_arrangements" rows="2"><?= $profileValue('emergency_arrangements') ?></textarea></label>
          <label>Customer response information<input name="response_information" value="<?= $profileValue('response_information') ?>"></label>
        </div>
        <?php foreach ([
          'vehicle_delivery' => 'Vehicle delivery available',
          'airport_pickup' => 'Airport pickup available',
          'gps_tracking_available' => 'Vehicles have GPS tracking',
          'customer_trip_sharing' => 'Customers can share trip locations',
          'roadside_assistance' => 'Roadside assistance provided',
          'breakdown_assistance' => 'Breakdown assistance available',
          'registered_drivers' => 'Chauffeur-driven vehicles have registered drivers',
          'live_trip_monitoring' => 'Supports live trip monitoring through SafariTrak',
        ] as $field => $label): ?>
          <label class="fleet-check"><input type="checkbox" name="<?= htmlspecialchars($field) ?>" value="1" <?= !empty($companyProfile[$field]) ? 'checked' : '' ?>><?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <p class="groups-form-status" id="companyProfileStatus" role="status" aria-live="polite"></p>
      <button class="explore-panel-action" type="submit">Save company profile</button>
    </form>
  </details>
  <section class="fleet-dashboard-card fleet-vehicles-section" id="vehicles">
    <header><div><span class="rental-eyebrow">YOUR FLEET</span><h2>My Posted Vehicles</h2></div><button type="button" class="rental-secondary-button" data-open-add-vehicle><i class="fa-solid fa-plus" aria-hidden="true"></i> Add vehicle</button></header>
    <?php if (!$vehicles): ?><p class="rental-empty">Your fleet is empty. Add a vehicle to publish it in the car-hire marketplace.</p><?php else: ?>
    <div class="fleet-table-scroll">
      <table class="fleet-vehicle-table">
        <thead><tr><th>Photo</th><th>Details</th><th>Vehicle Spec</th><th>Status</th><th>Organization</th><th>Management</th></tr></thead>
        <tbody>
        <?php foreach ($vehicles as $vehicle): ?>
          <?php
            $vehicleId = (int) $vehicle['id'];
            $vehicleStatus = isset($bookedVehicleIds[$vehicleId]) ? 'booked' : $vehicle['status'];
            $vehicleStatusLabel = [
                'available' => 'Active Listing',
                'booked' => 'Booked',
                'unavailable' => 'Unavailable',
                'maintenance' => 'Maintenance',
            ][$vehicleStatus] ?? ucfirst($vehicleStatus);
          ?>
          <tr>
            <td><?php if ($vehicle['photo_url']): ?><img class="fleet-vehicle-thumb" src="<?= htmlspecialchars($vehicle['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(trim($vehicle['make'] . ' ' . $vehicle['model']), ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><?php else: ?><span class="fleet-vehicle-thumb fleet-vehicle-thumb--empty"><i class="fa-solid fa-car-side" aria-hidden="true"></i></span><?php endif; ?></td>
            <td><strong><?= htmlspecialchars(trim($vehicle['make'] . ' ' . $vehicle['model']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= $vehicle['year'] ? (int) $vehicle['year'] . ' · ' : '' ?><?= htmlspecialchars($vehicle['category'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($vehicle['registration_number'], ENT_QUOTES, 'UTF-8') ?></small><small><?= !empty($vehicle['quotation_required']) || $vehicle['daily_rate'] === null ? 'Price on request' : htmlspecialchars($vehicle['currency'], ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) $vehicle['daily_rate']) . '/day' ?></small></td>
            <td><?= htmlspecialchars(ucfirst((string) ($vehicle['transmission'] ?? 'Not specified')), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst((string) ($vehicle['fuel_type'] ?? 'Not specified')), ENT_QUOTES, 'UTF-8') ?><small><?= (int) $vehicle['seats'] ?> seats</small></td>
            <td><span class="fleet-status-pill is-<?= htmlspecialchars($vehicleStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($vehicleStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><span class="fleet-org-tag"><?= htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-circle-check" aria-label="Verified"></i></span></td>
            <td><button type="button" class="fleet-manage-link fleet-open-management" data-vehicle-id="<?= $vehicleId ?>" data-vehicle-name="<?= htmlspecialchars(trim($vehicle['make'] . ' ' . $vehicle['model']), ENT_QUOTES, 'UTF-8') ?>" data-vehicle-rate="<?= htmlspecialchars((string) ($vehicle['daily_rate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-vehicle-status="<?= htmlspecialchars($vehicle['status'], ENT_QUOTES, 'UTF-8') ?>" data-quote-required="<?= !empty($vehicle['quotation_required']) ? '1' : '0' ?>">Management</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="fleet-dashboard-card fleet-bookings" id="bookings">
    <header><div><span class="rental-eyebrow">CUSTOMER REQUESTS</span><h2>Booking Requests</h2><p>Approve requests to hold the dates. Confirm final terms directly with the customer; payments are handled offline.</p></div></header>
    <?php if (!$bookings): ?><p class="explore-empty-note">No rental enquiries yet.</p><?php endif; ?>
    <?php foreach ($bookings as $booking): ?>
      <article class="fleet-booking">
        <div><span class="explore-eyebrow"><?= htmlspecialchars(ucfirst($booking['status'])) ?> · <?= htmlspecialchars(trim($booking['make'] . ' ' . $booking['model'])) ?></span>
          <h3><?= htmlspecialchars($booking['full_name']) ?></h3>
          <p><?= htmlspecialchars($booking['email']) ?> · <?= htmlspecialchars($booking['phone']) ?></p>
          <small><?= htmlspecialchars(date('j M Y, H:i', strtotime($booking['pickup_at']))) ?> – <?= htmlspecialchars(date('j M Y, H:i', strtotime($booking['return_at']))) ?> ·
            <?= $booking['quoted_daily_rate'] === null
              ? 'Quotation requested'
              : htmlspecialchars($booking['currency']) . ' ' . number_format((float) $booking['quoted_daily_rate'], 2) . '/day' ?>
          </small>
          <?php if ($booking['customer_note']): ?><p><?= htmlspecialchars($booking['customer_note']) ?></p><?php endif; ?>
        </div>
        <?php if ($booking['status'] === 'requested'): ?>
          <div class="fleet-booking-actions"><button type="button" class="rental-primary-button fleet-booking-review" data-booking-id="<?= (int) $booking['id'] ?>" data-status="approved">Approve</button><button type="button" class="fleet-booking-review is-reject" data-booking-id="<?= (int) $booking['id'] ?>" data-status="rejected">Decline</button></div>
        <?php elseif ($booking['status'] === 'responded'): ?>
          <div class="fleet-booking-actions"><button type="button" class="explore-panel-action fleet-booking-review" data-booking-id="<?= (int) $booking['id'] ?>" data-status="completed">Mark rental completed</button><small>Mark only after the offline rental has finished; this does not confirm or charge a reservation.</small></div>
        <?php elseif ($booking['status'] === 'approved'): ?>
          <div class="fleet-booking-actions"><span class="fleet-status-pill is-verified">Approved</span><button type="button" class="fleet-booking-review" data-booking-id="<?= (int) $booking['id'] ?>" data-status="completed">Mark completed</button></div>
        <?php elseif ($booking['status'] === 'completed'): ?>
          <small>Rental marked complete. The customer can now leave a rating and review.</small>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
  </div>

  <aside class="fleet-activity-sidebar" aria-label="Recent activity">
    <header><div><span class="rental-eyebrow">UPDATES</span><h2>Recent Activity</h2></div><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></header>
    <?php if (!$bookings): ?><p class="rental-empty">Your organization activity will appear here.</p><?php endif; ?>
    <?php foreach (array_slice($bookings, 0, 8) as $booking): ?>
      <article class="fleet-activity-item">
        <span class="fleet-activity-dot is-<?= $booking['status'] === 'requested' ? 'request' : ($booking['status'] === 'approved' ? 'approved' : 'complete') ?>"></span>
        <div><strong><?= htmlspecialchars(ucfirst($booking['status']) === 'Requested' ? 'Booking Request' : ucfirst($booking['status']), ENT_QUOTES, 'UTF-8') ?></strong>
          <p><?= htmlspecialchars($booking['full_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(trim($booking['make'] . ' ' . $booking['model']), ENT_QUOTES, 'UTF-8') ?></p>
          <small><?= htmlspecialchars(date('j M · H:i', strtotime($booking['pickup_at'])), ENT_QUOTES, 'UTF-8') ?></small>
          <?php if ($booking['status'] === 'requested'): ?><div class="fleet-activity-actions"><button type="button" class="fleet-booking-review" data-booking-id="<?= (int) $booking['id'] ?>" data-status="approved">Approve</button><button type="button" class="fleet-booking-review is-reject" data-booking-id="<?= (int) $booking['id'] ?>" data-status="rejected">Decline</button></div><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </aside>
</section>
<div class="groups-dialog" id="addVehicleDialog" hidden>
  <form class="groups-dialog-card" id="addVehicleForm">
    <button class="groups-dialog-close" type="button" data-close-vehicle aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">FLEET MANAGEMENT</span><h2>Add a vehicle</h2>
    <label for="vehicleMake">Make</label><input id="vehicleMake" maxlength="80" required>
    <label for="vehicleModel">Model</label><input id="vehicleModel" maxlength="100" required>
    <label for="vehicleYear">Year <span>(optional)</span></label><input id="vehicleYear" type="number" min="1900" max="<?= (int) date('Y') + 1 ?>">
    <label for="vehicleCategory">Category</label><input id="vehicleCategory" maxlength="60" required placeholder="SUV, sedan, van…">
    <label for="vehicleSeats">Seats</label><input id="vehicleSeats" type="number" min="1" max="99" value="4" required>
    <label for="vehicleRate">Daily rate</label><input id="vehicleRate" type="number" min="0.01" max="10000000" step="0.01" required>
    <label for="vehiclePickupLat">Pickup latitude <span>(optional)</span></label><input id="vehiclePickupLat" type="number" min="-90" max="90" step="any" placeholder="Use company default if blank">
    <label for="vehiclePickupLng">Pickup longitude <span>(optional)</span></label><input id="vehiclePickupLng" type="number" min="-180" max="180" step="any" placeholder="Use company default if blank">
    <label class="fleet-check"><input type="checkbox" id="vehicleQuoteRequired">Request a quotation instead of showing the daily price</label>
    <label for="vehicleCurrency">Currency</label><input id="vehicleCurrency" maxlength="3" value="KES" required>
    <label for="vehicleRegistration">Registration number</label><input id="vehicleRegistration" maxlength="32" required>
    <label for="vehicleDescription">Description <span>(optional)</span></label><textarea id="vehicleDescription" maxlength="500" rows="3"></textarea>
    <details>
      <summary>Vehicle details, pricing, services and policies</summary>
      <label for="vehicleTransmission">Transmission</label><select id="vehicleTransmission"><option value="">Not specified</option><option value="automatic">Automatic</option><option value="manual">Manual</option></select>
      <label for="vehicleFuel">Fuel type</label><select id="vehicleFuel"><option value="">Not specified</option><option value="petrol">Petrol</option><option value="diesel">Diesel</option><option value="hybrid">Hybrid</option><option value="electric">Electric</option></select>
      <label class="fleet-check"><input type="checkbox" id="vehicleAirConditioning">Air conditioning</label>
      <label for="vehicleLuggage">Luggage capacity (bags)</label><input id="vehicleLuggage" type="number" min="0" max="99">
      <label for="vehicleWeeklyRate">Weekly price (optional)</label><input id="vehicleWeeklyRate" type="number" min="0" step="0.01">
      <label for="vehicleMonthlyRate">Monthly price (optional)</label><input id="vehicleMonthlyRate" type="number" min="0" step="0.01">
      <label for="vehicleChauffeurRate">Chauffeur-driven price (optional)</label><input id="vehicleChauffeurRate" type="number" min="0" step="0.01">
      <label for="vehicleSelfDriveRate">Self-drive price (optional)</label><input id="vehicleSelfDriveRate" type="number" min="0" step="0.01">
      <label for="vehicleDeposit">Security deposit (optional)</label><input id="vehicleDeposit" type="number" min="0" step="0.01">
      <label for="vehicleMileageCharge">Extra mileage charge (optional)</label><input id="vehicleMileageCharge" type="number" min="0" step="0.01">
      <label for="vehicleDriverOption">Driver option</label><select id="vehicleDriverOption"><option value="both">Self-drive or chauffeur</option><option value="self_drive">Self-drive</option><option value="chauffeur">Chauffeur-driven</option></select>
      <label class="fleet-check"><input type="checkbox" id="vehicleNegotiable">Pricing is negotiable</label>
      <fieldset><legend>Services available for this vehicle</legend>
        <?php foreach ($rentalServices as $service => $label): ?>
          <label class="fleet-check"><input type="checkbox" name="vehicleServices" value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <label for="vehicleFuelPolicy">Fuel policy</label><textarea id="vehicleFuelPolicy" maxlength="500" rows="2"></textarea>
      <label for="vehicleAdditionalCharges">Additional charges</label><textarea id="vehicleAdditionalCharges" maxlength="1000" rows="2"></textarea>
      <label for="vehicleExteriorPhotos">Exterior photo URLs (one HTTPS URL per line)</label><textarea id="vehicleExteriorPhotos" rows="3"></textarea>
      <label for="vehicleInteriorPhotos">Interior photo URLs (one HTTPS URL per line)</label><textarea id="vehicleInteriorPhotos" rows="3"></textarea>
      <label for="vehicleAge">Minimum driver age</label><input id="vehicleAge" type="number" min="16" max="99">
      <label for="vehicleExperience">Minimum driving experience (years)</label><input id="vehicleExperience" type="number" min="0" max="60">
      <label for="vehicleIdRequirements">Identification requirements</label><input id="vehicleIdRequirements" maxlength="500">
      <label for="vehicleBookingNotice">Booking notice</label><input id="vehicleBookingNotice" maxlength="200">
      <label for="vehicleMinDays">Minimum rental days</label><input id="vehicleMinDays" type="number" min="1">
      <label for="vehicleMaxDays">Maximum rental days</label><input id="vehicleMaxDays" type="number" min="1">
      <label for="vehicleMileageRestrictions">Mileage restrictions</label><input id="vehicleMileageRestrictions" maxlength="500">
      <label for="vehicleLateReturn">Late return policy</label><input id="vehicleLateReturn" maxlength="500">
      <label for="vehicleCancellation">Cancellation policy</label><textarea id="vehicleCancellation" maxlength="1000" rows="2"></textarea>
      <label for="vehicleRefund">Refund policy</label><textarea id="vehicleRefund" maxlength="1000" rows="2"></textarea>
      <label class="fleet-check"><input type="checkbox" id="vehicleLicenceRequired" checked>Valid licence required</label>
      <label class="fleet-check"><input type="checkbox" id="vehicleCrossBorder">Cross-border travel available</label>
    </details>
    <p class="groups-form-status" id="addVehicleStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Publish vehicle</button>
  </form>
</div>
<div class="groups-dialog" id="vehicleManagementDialog" hidden>
  <form class="groups-dialog-card" id="vehicleManagementForm">
    <button class="groups-dialog-close" type="button" data-close-management aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">LISTING MANAGEMENT</span><h2 id="vehicleManagementTitle">Update vehicle</h2>
    <input type="hidden" id="managementVehicleId">
    <label for="managementVehicleRate">Daily rate (KES)</label><input id="managementVehicleRate" type="number" min="0.01" max="10000000" step="0.01">
    <label class="fleet-check"><input type="checkbox" id="managementQuoteRequired">Request a quotation instead of showing the daily price</label>
    <label for="managementVehicleStatus">Availability</label><select id="managementVehicleStatus"><option value="available">Active listing</option><option value="unavailable">Unavailable</option><option value="maintenance">Maintenance</option></select>
    <p class="groups-form-status" id="vehicleManagementStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Save Listing</button>
  </form>
</div>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
<script src="fleet.js?v=<?= hash_file('sha256', __DIR__ . '/fleet.js') ?>"></script>
