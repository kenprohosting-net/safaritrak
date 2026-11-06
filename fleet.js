(() => {
  const dialog = document.getElementById('addVehicleDialog');
  document.getElementById('addVehicleOpen')?.addEventListener('click', () => { dialog.hidden = false; });
  document.querySelectorAll('[data-close-vehicle]').forEach(button => button.addEventListener('click', () => { dialog.hidden = true; }));
  dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.hidden = true; });

  async function send(action, values) {
    const response = await fetch('backend/api/rentals/fleet-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...values }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Fleet action failed.');
    return data;
  }

  document.getElementById('addVehicleForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('[type="submit"]');
    const status = document.getElementById('addVehicleStatus');
    button.disabled = true;
    status.textContent = 'Publishing vehicle…';
    try {
      await send('add_vehicle', {
        make: document.getElementById('vehicleMake').value.trim(),
        model: document.getElementById('vehicleModel').value.trim(),
        year: document.getElementById('vehicleYear').value,
        category: document.getElementById('vehicleCategory').value.trim(),
        seats: document.getElementById('vehicleSeats').value,
        daily_rate: document.getElementById('vehicleRate').value,
        currency: document.getElementById('vehicleCurrency').value.trim(),
        registration_number: document.getElementById('vehicleRegistration').value.trim(),
        description: document.getElementById('vehicleDescription').value.trim(),
      });
      location.reload();
    } catch (error) {
      status.textContent = error.message || 'Could not publish vehicle.';
      button.disabled = false;
    }
  });

  document.querySelectorAll('.fleet-vehicle-status').forEach(select => {
    select.addEventListener('change', async () => {
      const previous = select.dataset.previousStatus || select.value;
      select.disabled = true;
      try {
        await send('vehicle_status', { vehicle_id: select.dataset.vehicleId, status: select.value });
        select.dataset.previousStatus = select.value;
      } catch (error) {
        alert(error.message || 'Could not update vehicle status.');
        select.value = previous;
      } finally {
        select.disabled = false;
      }
    });
  });

  document.querySelectorAll('.fleet-booking-review').forEach(button => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await send('booking_status', { booking_id: button.dataset.bookingId, status: button.dataset.status });
        location.reload();
      } catch (error) {
        alert(error.message || 'Could not update the booking.');
        button.disabled = false;
      }
    });
  });
})();
