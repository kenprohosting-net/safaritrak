<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$pageTitle = 'Car hire';
$pageHeading = 'Car hire';
$activeNav = 'car-hire';
$loadNotifications = true;
require __DIR__ . '/backend/views/app-shell-start.php';
?>
<section class="rental-page">
  <header class="rental-page-heading">
    <div><span class="explore-eyebrow">LOCAL RENTAL REQUESTS</span><h2>Find a car for your next trip</h2>
      <p>Browse approved car hire services and request a booking. The provider confirms availability and pricing before anything is reserved.</p>
    </div>
  </header>
  <p class="rental-status" id="rentalStatus" role="status" aria-live="polite">Loading available vehicles…</p>
  <div class="rental-vehicle-grid" id="rentalVehicles"></div>
  <section class="rental-bookings">
    <div class="groups-section-heading"><div><span class="explore-eyebrow">YOUR REQUESTS</span><h2>Booking requests</h2></div></div>
    <div id="myRentalBookings" class="rental-booking-list"><p class="groups-chat-empty">Loading your requests…</p></div>
  </section>
</section>
<div class="groups-dialog" id="rentalBookingDialog" hidden>
  <form class="groups-dialog-card" id="rentalBookingForm">
    <button class="groups-dialog-close" type="button" data-close-rental aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <span class="explore-eyebrow">REQUEST A BOOKING</span><h2 id="rentalSelectedVehicle"></h2>
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
