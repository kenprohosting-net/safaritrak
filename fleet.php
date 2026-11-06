<?php
require __DIR__ . '/backend/includes/org-guard.php';
if (!$myOrg || ($myOrg['account_type'] ?? '') !== 'car_hire') {
    header('Location: org-dashboard.php');
    exit;
}
$db = safaritrak_db();
$vehiclesStmt = $db->prepare(
    'SELECT * FROM rental_vehicles WHERE organization_id = ? ORDER BY created_at DESC'
);
$vehiclesStmt->execute([(int) $myOrg['id']]);
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
$bookingsStmt = $db->prepare(
    'SELECT b.id, b.pickup_at, b.return_at, b.quoted_daily_rate, b.status, b.customer_note,
            u.full_name, u.email, u.phone, v.make, v.model, v.currency
     FROM rental_bookings b
     JOIN rental_vehicles v ON v.id = b.vehicle_id
     JOIN users u ON u.id = b.customer_id
     WHERE v.organization_id = ?
     ORDER BY FIELD(b.status, "requested", "approved", "rejected", "cancelled", "completed"), b.created_at DESC
     LIMIT 100'
);
$bookingsStmt->execute([(int) $myOrg['id']]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Manage fleet';
$pageHeading = 'Fleet & bookings';
$activeNav = 'fleet';
$loadNotifications = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="fleet-page">
  <div class="groups-section-heading">
    <div><span class="explore-eyebrow">YOUR CAR HIRE SERVICE</span><h2>Fleet</h2><p>Manage vehicles and review customer booking requests. Payments are handled outside SafariTrak.</p></div>
    <button type="button" class="explore-panel-action" id="addVehicleOpen"><i class="fa-solid fa-plus"></i> Add vehicle</button>
  </div>
  <?php if (!$vehicles): ?><p class="explore-empty-note">Your fleet is empty. Add a vehicle to publish it in the car-hire catalog.</p><?php endif; ?>
  <div class="fleet-vehicle-list">
    <?php foreach ($vehicles as $vehicle): ?>
    <article class="fleet-vehicle">
      <div><span class="explore-eyebrow"><?= htmlspecialchars($vehicle['category']) ?> · <?= (int) $vehicle['seats'] ?> seats</span>
        <h3><?= htmlspecialchars(trim($vehicle['make'] . ' ' . $vehicle['model'])) ?><?= $vehicle['year'] ? ' · ' . (int) $vehicle['year'] : '' ?></h3>
        <p><?= htmlspecialchars($vehicle['registration_number']) ?> · <?= htmlspecialchars($vehicle['currency']) ?> <?= number_format((float) $vehicle['daily_rate'], 2) ?>/day</p>
        <?php if ($vehicle['description']): ?><small><?= htmlspecialchars($vehicle['description']) ?></small><?php endif; ?>
      </div>
      <label class="fleet-status-control">Listing status
        <select class="fleet-vehicle-status" data-vehicle-id="<?= (int) $vehicle['id'] ?>">
          <?php foreach (['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance'] as $key => $label): ?>
            <option value="<?= $key ?>" <?= $vehicle['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </article>
    <?php endforeach; ?>
  </div>

  <section class="fleet-bookings">
    <div class="groups-section-heading"><div><span class="explore-eyebrow">CUSTOMER REQUESTS</span><h2>Bookings</h2></div></div>
    <?php if (!$bookings): ?><p class="explore-empty-note">No booking requests yet.</p><?php endif; ?>
    <?php foreach ($bookings as $booking): ?>
      <article class="fleet-booking">
        <div><span class="explore-eyebrow"><?= htmlspecialchars(ucfirst($booking['status'])) ?> · <?= htmlspecialchars(trim($booking['make'] . ' ' . $booking['model'])) ?></span>
          <h3><?= htmlspecialchars($booking['full_name']) ?></h3>
          <p><?= htmlspecialchars($booking['email']) ?> · <?= htmlspecialchars($booking['phone']) ?></p>
          <small><?= htmlspecialchars(date('j M Y, H:i', strtotime($booking['pickup_at']))) ?> – <?= htmlspecialchars(date('j M Y, H:i', strtotime($booking['return_at']))) ?> · <?= htmlspecialchars($booking['currency']) ?> <?= number_format((float) $booking['quoted_daily_rate'], 2) ?>/day</small>
          <?php if ($booking['customer_note']): ?><p><?= htmlspecialchars($booking['customer_note']) ?></p><?php endif; ?>
        </div>
        <?php if ($booking['status'] === 'requested'): ?>
          <div class="fleet-booking-actions"><button type="button" class="explore-panel-action fleet-booking-review" data-booking-id="<?= (int) $booking['id'] ?>" data-status="approved">Approve</button><button type="button" class="fleet-booking-review is-reject" data-booking-id="<?= (int) $booking['id'] ?>" data-status="rejected">Reject</button></div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
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
    <label for="vehicleCurrency">Currency</label><input id="vehicleCurrency" maxlength="3" value="KES" required>
    <label for="vehicleRegistration">Registration number</label><input id="vehicleRegistration" maxlength="32" required>
    <label for="vehicleDescription">Description <span>(optional)</span></label><textarea id="vehicleDescription" maxlength="500" rows="3"></textarea>
    <p class="groups-form-status" id="addVehicleStatus" role="status"></p>
    <button class="explore-panel-action" type="submit">Publish vehicle</button>
  </form>
</div>
<?php require __DIR__ . '/backend/views/app-shell-end.php'; ?>
<script src="fleet.js?v=<?= hash_file('sha256', __DIR__ . '/fleet.js') ?>"></script>
