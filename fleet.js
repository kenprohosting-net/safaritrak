(() => {
  const dialog = document.getElementById('addVehicleDialog');
  const openAddVehicle = () => { dialog.hidden = false; };
  document.getElementById('addVehicleOpen')?.addEventListener('click', openAddVehicle);
  document.querySelectorAll('[data-open-add-vehicle]').forEach(button => button.addEventListener('click', openAddVehicle));
  document.querySelectorAll('[data-close-vehicle]').forEach(button => button.addEventListener('click', () => { dialog.hidden = true; }));
  dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.hidden = true; });
  const managementDialog = document.getElementById('vehicleManagementDialog');
  document.querySelectorAll('[data-close-management]').forEach(button => button.addEventListener('click', () => { managementDialog.hidden = true; }));
  managementDialog?.addEventListener('click', event => { if (event.target === managementDialog) managementDialog.hidden = true; });
  document.querySelectorAll('.fleet-open-management').forEach(button => button.addEventListener('click', () => {
    const quoteRequired = document.getElementById('managementQuoteRequired');
    const dailyRate = document.getElementById('managementVehicleRate');
    document.getElementById('managementVehicleId').value = button.dataset.vehicleId;
    document.getElementById('vehicleManagementTitle').textContent = `Manage ${button.dataset.vehicleName}`;
    dailyRate.value = button.dataset.vehicleRate;
    document.getElementById('managementVehicleStatus').value = button.dataset.vehicleStatus;
    quoteRequired.checked = button.dataset.quoteRequired === '1';
    dailyRate.disabled = quoteRequired.checked;
    dailyRate.required = !quoteRequired.checked;
    managementDialog.hidden = false;
  }));
  document.getElementById('managementQuoteRequired')?.addEventListener('change', event => {
    const rate = document.getElementById('managementVehicleRate');
    rate.disabled = event.currentTarget.checked;
    rate.required = !event.currentTarget.checked;
    if (event.currentTarget.checked) rate.value = '';
  });

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

  document.getElementById('companyProfileForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('[type="submit"]');
    const status = document.getElementById('companyProfileStatus');
    const values = Object.fromEntries(new FormData(form).entries());
    values.services = [...form.querySelectorAll('input[name="services"]:checked')].map(input => input.value);
    for (const input of form.querySelectorAll('input[type="checkbox"]:not([name="services"])')) {
      values[input.name] = input.checked;
    }
    button.disabled = true;
    status.textContent = 'Saving company profile…';
    try {
      await send('save_company_profile', values);
      status.textContent = 'Company profile saved.';
    } catch (error) {
      status.textContent = error.message || 'Company profile could not be saved.';
    } finally {
      button.disabled = false;
    }
  });

  const quoteRequired = document.getElementById('vehicleQuoteRequired');
  const dailyRate = document.getElementById('vehicleRate');
  quoteRequired?.addEventListener('change', () => {
    dailyRate.required = !quoteRequired.checked;
    dailyRate.disabled = quoteRequired.checked;
    if (quoteRequired.checked) dailyRate.value = '';
  });

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
        daily_rate: dailyRate.value,
        pickup_lat: document.getElementById('vehiclePickupLat').value,
        pickup_lng: document.getElementById('vehiclePickupLng').value,
        quotation_required: quoteRequired.checked,
        currency: document.getElementById('vehicleCurrency').value.trim(),
        registration_number: document.getElementById('vehicleRegistration').value.trim(),
        description: document.getElementById('vehicleDescription').value.trim(),
        transmission: document.getElementById('vehicleTransmission').value,
        fuel_type: document.getElementById('vehicleFuel').value,
        air_conditioning: document.getElementById('vehicleAirConditioning').checked,
        luggage_capacity: document.getElementById('vehicleLuggage').value,
        weekly_rate: document.getElementById('vehicleWeeklyRate').value,
        monthly_rate: document.getElementById('vehicleMonthlyRate').value,
        chauffeur_rate: document.getElementById('vehicleChauffeurRate').value,
        self_drive_rate: document.getElementById('vehicleSelfDriveRate').value,
        security_deposit: document.getElementById('vehicleDeposit').value,
        extra_mileage_charge: document.getElementById('vehicleMileageCharge').value,
        driver_option: document.getElementById('vehicleDriverOption').value,
        negotiable: document.getElementById('vehicleNegotiable').checked,
        services: [...form.querySelectorAll('input[name="vehicleServices"]:checked')].map(input => input.value),
        fuel_policy: document.getElementById('vehicleFuelPolicy').value.trim(),
        additional_charges: document.getElementById('vehicleAdditionalCharges').value.trim(),
        exterior_photos: document.getElementById('vehicleExteriorPhotos').value.split(/\r?\n/).map(url => url.trim()).filter(Boolean),
        interior_photos: document.getElementById('vehicleInteriorPhotos').value.split(/\r?\n/).map(url => url.trim()).filter(Boolean),
        minimum_driver_age: document.getElementById('vehicleAge').value,
        minimum_driving_experience: document.getElementById('vehicleExperience').value,
        identification_requirements: document.getElementById('vehicleIdRequirements').value.trim(),
        booking_notice: document.getElementById('vehicleBookingNotice').value.trim(),
        minimum_rental_days: document.getElementById('vehicleMinDays').value,
        maximum_rental_days: document.getElementById('vehicleMaxDays').value,
        mileage_restrictions: document.getElementById('vehicleMileageRestrictions').value.trim(),
        late_return_policy: document.getElementById('vehicleLateReturn').value.trim(),
        cancellation_policy: document.getElementById('vehicleCancellation').value.trim(),
        refund_policy: document.getElementById('vehicleRefund').value.trim(),
        valid_licence_required: document.getElementById('vehicleLicenceRequired').checked,
        cross_border_available: document.getElementById('vehicleCrossBorder').checked,
      });
      location.reload();
    } catch (error) {
      status.textContent = error.message || 'Could not publish vehicle.';
      button.disabled = false;
    }
  });

  document.getElementById('vehicleManagementForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('[type="submit"]');
    const quoteRequired = document.getElementById('managementQuoteRequired').checked;
    const feedback = document.getElementById('vehicleManagementStatus');
    button.disabled = true;
    feedback.textContent = 'Saving listing changes…';
    try {
      await send('update_vehicle', {
        vehicle_id: document.getElementById('managementVehicleId').value,
        daily_rate: document.getElementById('managementVehicleRate').value,
        quotation_required: quoteRequired,
        status: document.getElementById('managementVehicleStatus').value,
      });
      location.reload();
    } catch (error) {
      feedback.textContent = error.message || 'Could not update this listing.';
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
