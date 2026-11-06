(() => {
  const status = document.getElementById('rentalStatus');
  const vehicleList = document.getElementById('rentalVehicles');
  const bookingsList = document.getElementById('myRentalBookings');
  const dialog = document.getElementById('rentalBookingDialog');

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  async function loadCatalog() {
    try {
      const response = await fetch('backend/api/rentals/catalog.php', { credentials: 'same-origin', cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Rental catalog could not be loaded.');
      renderVehicles(data.vehicles);
      renderBookings(data.bookings);
      status.textContent = data.vehicles.length
        ? `${data.vehicles.length} vehicles available from approved car hire services.`
        : 'No vehicles are currently listed. Check back later.';
    } catch (error) {
      status.textContent = error.message || 'Rental catalog could not be loaded.';
    }
  }

  function renderVehicles(vehicles) {
    vehicleList.replaceChildren();
    for (const vehicle of vehicles) {
      const card = element('article', 'rental-vehicle-card');
      const icon = element('span', 'rental-vehicle-icon');
      icon.append(element('i', 'fa-solid fa-car-side'));
      const name = element('h3', '', `${vehicle.make} ${vehicle.model}${vehicle.year ? ` · ${vehicle.year}` : ''}`);
      const provider = element('p', 'rental-provider', vehicle.organization_name);
      const details = element('p', 'rental-details', `${vehicle.category} · ${vehicle.seats} seats`);
      const rate = element('strong', 'rental-rate', `${vehicle.currency} ${Number(vehicle.daily_rate).toLocaleString()} / day`);
      const description = element('small', 'rental-description', vehicle.description || 'Contact the provider for pickup details.');
      const button = element('button', 'explore-panel-action', 'Request booking');
      button.type = 'button';
      button.addEventListener('click', () => {
        document.getElementById('rentalVehicleId').value = vehicle.id;
        document.getElementById('rentalSelectedVehicle').textContent = `${vehicle.make} ${vehicle.model} · ${vehicle.currency} ${Number(vehicle.daily_rate).toLocaleString()}/day`;
        dialog.hidden = false;
      });
      card.append(icon, name, provider, details, rate, description, button);
      vehicleList.append(card);
    }
  }

  function renderBookings(bookings) {
    bookingsList.replaceChildren();
    if (!bookings.length) {
      bookingsList.append(element('p', 'groups-chat-empty', 'You have not requested a rental yet.'));
      return;
    }
    for (const booking of bookings) {
      const item = element('article', 'rental-booking-item');
      item.append(
        element('strong', '', `${booking.make} ${booking.model} · ${booking.organization_name}`),
        element('span', '', `${new Date(booking.pickup_at.replace(' ', 'T')).toLocaleString()} – ${new Date(booking.return_at.replace(' ', 'T')).toLocaleString()}`),
        element('small', '', `${booking.currency} ${Number(booking.quoted_daily_rate).toLocaleString()}/day · ${booking.status}`)
      );
      bookingsList.append(item);
    }
  }

  document.querySelectorAll('[data-close-rental]').forEach(button => button.addEventListener('click', () => { dialog.hidden = true; }));
  dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.hidden = true; });

  document.getElementById('rentalBookingForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('[type="submit"]');
    const feedback = document.getElementById('rentalBookingStatus');
    button.disabled = true;
    feedback.textContent = 'Sending your request…';
    try {
      const response = await fetch('backend/api/rentals/book.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          vehicle_id: document.getElementById('rentalVehicleId').value,
          pickup_at: document.getElementById('rentalPickup').value,
          return_at: document.getElementById('rentalReturn').value,
          note: document.getElementById('rentalNote').value.trim(),
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Your booking request could not be sent.');
      feedback.textContent = 'Request sent. The provider must confirm availability; no payment has been taken.';
      setTimeout(() => location.reload(), 900);
    } catch (error) {
      feedback.textContent = error.message || 'Your booking request could not be sent.';
      button.disabled = false;
    }
  });

  loadCatalog();
})();
