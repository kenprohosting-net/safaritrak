(() => {
  const status = document.getElementById('rentalStatus');
  const mapStatus = document.getElementById('rentalMapStatus');
  const vehicleList = document.getElementById('rentalVehicles');
  const bookingsList = document.getElementById('myRentalBookings');
  const dialog = document.getElementById('rentalBookingDialog');
  const preview = document.getElementById('rentalPreview');
  const previewContent = document.getElementById('rentalPreviewContent');
  const pickupDate = document.getElementById('rentalPickupDate');
  const returnDate = document.getElementById('rentalReturnDate');
  const pickupArea = document.getElementById('rentalPickupArea');
  const filters = {
    search: document.getElementById('rentalSearch'),
    category: '',
    priceSort: false,
  };
  let allVehicles = [];
  let currentFeatures = [];
  let selectedVehicleId = null;
  let hasFocusedCatalog = false;

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function localDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function initializeDates() {
    const pickup = new Date();
    pickup.setDate(pickup.getDate() + 1);
    const returning = new Date(pickup);
    returning.setDate(returning.getDate() + 2);
    pickupDate.min = localDateString(pickup);
    pickupDate.value = localDateString(pickup);
    returnDate.min = localDateString(pickup);
    returnDate.value = localDateString(returning);
  }

  function vehiclePhoto(vehicle) {
    return vehicle.photos?.exterior?.[0] || vehicle.photos?.interior?.[0] || vehicle.cover_photo_url || '';
  }

  function vehicleRate(vehicle) {
    if (vehicle.quotation_required || vehicle.daily_rate === null) return 'Price on request';
    return `${vehicle.currency} ${Number(vehicle.daily_rate).toLocaleString()} / day`;
  }

  function vehicleFeature(vehicle) {
    const hasVehiclePickup = vehicle.pickup_lat !== null && vehicle.pickup_lat !== undefined && vehicle.pickup_lat !== ''
      && vehicle.pickup_lng !== null && vehicle.pickup_lng !== undefined && vehicle.pickup_lng !== '';
    const latitude = hasVehiclePickup ? vehicle.pickup_lat : vehicle.office_lat;
    const longitude = hasVehiclePickup ? vehicle.pickup_lng : vehicle.office_lng;
    if (latitude === null || latitude === undefined || latitude === ''
      || longitude === null || longitude === undefined || longitude === '') return null;
    const lat = Number(latitude);
    const lng = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    const price = vehicle.quotation_required || vehicle.daily_rate === null
      ? 'Request a quote'
      : `${vehicle.currency} ${Number(vehicle.daily_rate).toLocaleString()}/day`;
    return {
      lat,
      lng,
      kind: 'rental',
      vehicleId: Number(vehicle.id),
      label: price,
      description: `${vehicle.make} ${vehicle.model} · ${vehicle.organization_name}`,
    };
  }

  function matchesQuickFilter(vehicle) {
    if (!filters.category) return true;
    const category = String(vehicle.category || '').toLocaleLowerCase();
    if (filters.category === 'suv') return category.includes('suv');
    if (filters.category === 'sedan') return category.includes('sedan');
    if (filters.category === '7-seater') return Number(vehicle.seats) >= 7;
    if (filters.category === 'automatic') return vehicle.transmission === 'automatic';
    return true;
  }

  function visibleVehicles() {
    const search = filters.search.value.trim().toLocaleLowerCase();
    const vehicles = allVehicles.filter(vehicle => {
      const searchText = `${vehicle.organization_name} ${vehicle.make} ${vehicle.model} ${vehicle.category} ${vehicle.town || ''}`.toLocaleLowerCase();
      return (!search || searchText.includes(search)) && matchesQuickFilter(vehicle);
    });
    if (filters.priceSort) {
      vehicles.sort((left, right) => {
        const leftRate = left.daily_rate === null ? Number.POSITIVE_INFINITY : Number(left.daily_rate);
        const rightRate = right.daily_rate === null ? Number.POSITIVE_INFINITY : Number(right.daily_rate);
        return leftRate - rightRate;
      });
    }
    return vehicles;
  }

  function openBooking(vehicle) {
    document.getElementById('rentalVehicleId').value = vehicle.id;
    document.getElementById('rentalSelectedVehicle').textContent =
      `${vehicle.make} ${vehicle.model} · ${vehicleRate(vehicle)}`;
    document.getElementById('rentalBookingStatus').textContent =
      `Your request will be sent to ${vehicle.organization_name}. The provider must approve it and confirm final terms.`;
    document.getElementById('rentalPickup').value = `${pickupDate.value}T10:00`;
    document.getElementById('rentalReturn').value = `${returnDate.value}T10:00`;
    document.getElementById('rentalNote').value = pickupArea.value.trim()
      ? `Pickup area: ${pickupArea.value.trim()}`
      : '';
    dialog.hidden = false;
  }

  function openPreview(vehicle, focusMap = false) {
    selectedVehicleId = Number(vehicle.id);
    previewContent.replaceChildren();
    const photo = vehiclePhoto(vehicle);
    if (photo) {
      const image = element('img', 'rental-preview-photo');
      image.src = photo;
      image.alt = `${vehicle.make} ${vehicle.model}`;
      image.loading = 'lazy';
      previewContent.append(image);
    } else {
      const placeholder = element('div', 'rental-preview-placeholder');
      placeholder.innerHTML = '<i class="fa-solid fa-car-side" aria-hidden="true"></i>';
      previewContent.append(placeholder);
    }
    previewContent.append(
      element('span', 'rental-verified-badge', '✓ Verified Org'),
      element('h2', '', `${vehicle.make} ${vehicle.model}${vehicle.year ? ` · ${vehicle.year}` : ''}`),
      element('p', 'rental-provider', vehicle.organization_name),
      element('strong', 'rental-rate', vehicleRate(vehicle))
    );
    const specs = element('div', 'rental-preview-specs');
    [
      vehicle.transmission ? `${vehicle.transmission[0].toLocaleUpperCase()}${vehicle.transmission.slice(1)}` : '',
      vehicle.fuel_type ? `${vehicle.fuel_type[0].toLocaleUpperCase()}${vehicle.fuel_type.slice(1)}` : '',
      `${vehicle.seats} seats`,
    ].filter(Boolean).forEach(spec => specs.append(element('span', '', spec)));
    previewContent.append(specs);
    const confirm = element('button', 'rental-primary-button', 'Confirm Details');
    confirm.type = 'button';
    confirm.addEventListener('click', () => openBooking(vehicle));
    previewContent.append(confirm);
    preview.hidden = false;
    vehicleList.querySelectorAll('.rental-vehicle-card').forEach(card => {
      card.classList.toggle('is-selected', Number(card.dataset.vehicleId) === selectedVehicleId);
    });
    if (focusMap) {
      const feature = vehicleFeature(vehicle);
      if (feature) window.SafariTrakPageMap?.focusFeatures([feature]);
    }
  }

  function renderVehicles(vehicles) {
    vehicleList.replaceChildren();
    currentFeatures = vehicles.map(vehicleFeature).filter(Boolean);
    window.SafariTrakPageMap?.setFeatures(currentFeatures);
    mapStatus.textContent = currentFeatures.length
      ? `${currentFeatures.length} price marker${currentFeatures.length === 1 ? '' : 's'} shown on the map. Select a marker or vehicle to preview details.`
      : 'Pickup coordinates are not available for these listings yet.';

    if (!vehicles.length) {
      vehicleList.append(element('p', 'rental-empty', 'No vehicles match these filters.'));
      return;
    }
    vehicles.forEach(vehicle => {
      const card = element('article', 'rental-vehicle-card');
      card.dataset.vehicleId = vehicle.id;
      card.tabIndex = 0;
      card.setAttribute('aria-label', `${vehicle.make} ${vehicle.model}, ${vehicleRate(vehicle)}. Select for details.`);
      const photo = vehiclePhoto(vehicle);
      if (photo) {
        const image = element('img', 'rental-vehicle-photo');
        image.src = photo;
        image.alt = `${vehicle.make} ${vehicle.model}`;
        image.loading = 'lazy';
        card.append(image);
      } else {
        const placeholder = element('div', 'rental-vehicle-placeholder');
        placeholder.innerHTML = '<i class="fa-solid fa-car-side" aria-hidden="true"></i>';
        card.append(placeholder);
      }

      const copy = element('div', 'rental-vehicle-copy');
      copy.append(
        element('span', 'rental-vehicle-meta', `${vehicle.category} · ${vehicle.year || 'Year not listed'}`),
        element('h2', '', `${vehicle.make} ${vehicle.model}`),
        element('strong', 'rental-rate', vehicleRate(vehicle))
      );
      const verified = element('p', 'rental-verified-line');
      verified.append(element('span', 'rental-verified-icon', '✓'), element('span', '', `${vehicle.organization_name} · Verified Org`));
      copy.append(verified);
      const request = element('button', 'rental-primary-button', 'Request Booking');
      request.type = 'button';
      request.addEventListener('click', event => {
        event.stopPropagation();
        openBooking(vehicle);
      });
      card.append(copy, request);
      card.addEventListener('click', () => openPreview(vehicle, true));
      card.addEventListener('keydown', event => {
        if (event.target === card && (event.key === 'Enter' || event.key === ' ')) {
          event.preventDefault();
          openPreview(vehicle, true);
        }
      });
      if (Number(vehicle.id) === selectedVehicleId) card.classList.add('is-selected');
      vehicleList.append(card);
    });

    if (!hasFocusedCatalog && currentFeatures.length) {
      window.SafariTrakPageMap?.focusFeatures(currentFeatures);
      hasFocusedCatalog = true;
    }
  }

  function renderBookings(items) {
    bookingsList.replaceChildren();
    if (!items.length) {
      bookingsList.append(element('p', 'rental-empty', 'You have not sent a booking request yet.'));
      return;
    }
    items.forEach(booking => {
      const item = element('article', 'rental-booking-item');
      const price = booking.quoted_daily_rate === null
        ? 'Quotation requested'
        : `${booking.currency} ${Number(booking.quoted_daily_rate).toLocaleString()}/day`;
      const state = {
        requested: 'Awaiting provider approval',
        approved: 'Approved · coordinate final terms',
        responded: 'Provider replied',
        rejected: 'Declined',
        cancelled: 'Cancelled',
        completed: 'Completed',
      }[booking.status] || booking.status;
      item.append(
        element('strong', '', `${booking.make} ${booking.model} · ${booking.organization_name}`),
        element('span', '', `${new Date(booking.pickup_at.replace(' ', 'T')).toLocaleString()} – ${new Date(booking.return_at.replace(' ', 'T')).toLocaleString()}`),
        element('small', 'rental-booking-state', `${price} · ${state}`)
      );
      if (booking.contact_user_id && ['requested', 'responded', 'approved', 'completed'].includes(booking.status)) {
        const messageLink = element('a', 'rental-contact-link', 'Message company');
        messageLink.href = `messages.php?to=${encodeURIComponent(booking.contact_user_id)}`;
        item.append(messageLink);
      }
      if (booking.status === 'completed') {
        const reviewForm = element('form', 'rental-review-form');
        reviewForm.dataset.bookingId = booking.id;
        const ratingSelect = element('select');
        ratingSelect.name = 'rating';
        ratingSelect.setAttribute('aria-label', 'Your rating from 1 to 5');
        for (let rating = 5; rating >= 1; rating -= 1) {
          const option = element('option', '', `${rating} / 5`);
          option.value = String(rating);
          if (Number(booking.rating) === rating) option.selected = true;
          ratingSelect.append(option);
        }
        const reviewInput = element('textarea');
        reviewInput.name = 'review_text';
        reviewInput.maxLength = 1000;
        reviewInput.rows = 2;
        reviewInput.placeholder = 'Share your experience (optional).';
        reviewInput.value = booking.review_text || '';
        const submit = element('button', 'rental-secondary-button', booking.rating ? 'Update review' : 'Leave review');
        submit.type = 'submit';
        reviewForm.append(ratingSelect, reviewInput, submit);
        item.append(reviewForm);
      }
      bookingsList.append(item);
    });
  }

  function applyFilters() {
    const vehicles = visibleVehicles();
    renderVehicles(vehicles);
    status.textContent = `${vehicles.length} available vehicle${vehicles.length === 1 ? '' : 's'}. Requests are sent to verified providers for approval.`;
  }

  async function loadCatalog() {
    try {
      const response = await fetch('backend/api/rentals/catalog.php', { credentials: 'same-origin', cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Rental catalog could not be loaded.');
      allVehicles = data.vehicles;
      renderBookings(data.bookings);
      applyFilters();
    } catch (error) {
      status.textContent = error.message || 'Rental catalog could not be loaded.';
    }
  }

  initializeDates();
  filters.search.addEventListener('input', applyFilters);
  document.querySelectorAll('[data-rental-filter]').forEach(button => button.addEventListener('click', () => {
    const value = button.dataset.rentalFilter;
    if (value === 'price') {
      filters.priceSort = !filters.priceSort;
      button.classList.toggle('is-active', filters.priceSort);
      button.setAttribute('aria-pressed', String(filters.priceSort));
    } else {
      filters.category = value === 'all' ? '' : value;
      document.querySelectorAll('[data-rental-filter]:not([data-rental-filter="price"])').forEach(filter => {
        const active = filter === button;
        filter.classList.toggle('is-active', active);
        filter.setAttribute('aria-pressed', String(active));
      });
    }
    applyFilters();
  }));
  pickupDate.addEventListener('change', () => {
    returnDate.min = pickupDate.value;
    if (returnDate.value <= pickupDate.value) {
      const nextDay = new Date(`${pickupDate.value}T12:00:00`);
      nextDay.setDate(nextDay.getDate() + 1);
      returnDate.value = localDateString(nextDay);
    }
  });
  document.getElementById('rentalPreviewClose').addEventListener('click', () => {
    preview.hidden = true;
    selectedVehicleId = null;
    vehicleList.querySelectorAll('.rental-vehicle-card').forEach(card => card.classList.remove('is-selected'));
  });
  window.addEventListener('safaritrak:rental-feature-click', event => {
    const vehicle = allVehicles.find(item => Number(item.id) === Number(event.detail?.vehicleId));
    if (vehicle) openPreview(vehicle);
  });
  document.querySelectorAll('[data-close-rental]').forEach(button => button.addEventListener('click', () => { dialog.hidden = true; }));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.hidden = true; });

  document.getElementById('rentalBookingForm').addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('[type="submit"]');
    const feedback = document.getElementById('rentalBookingStatus');
    button.disabled = true;
    feedback.textContent = 'Sending your booking request…';
    try {
      const note = document.getElementById('rentalNote').value.trim();
      const area = pickupArea.value.trim();
      const response = await fetch('backend/api/rentals/book.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          vehicle_id: document.getElementById('rentalVehicleId').value,
          pickup_at: document.getElementById('rentalPickup').value,
          return_at: document.getElementById('rentalReturn').value,
          note: area && !note.toLocaleLowerCase().includes(area.toLocaleLowerCase())
            ? [note, `Pickup area: ${area}`].filter(Boolean).join(' · ')
            : note,
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Your booking request could not be sent.');
      feedback.textContent = 'Request sent. The verified provider will review it and contact you to confirm availability and final terms.';
      setTimeout(() => location.reload(), 1200);
    } catch (error) {
      feedback.textContent = error.message || 'Your booking request could not be sent.';
      button.disabled = false;
    }
  });

  bookingsList.addEventListener('submit', async event => {
    const form = event.target.closest('.rental-review-form');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch('backend/api/rentals/review.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          booking_id: Number(form.dataset.bookingId),
          rating: Number(form.querySelector('[name="rating"]').value),
          review_text: form.querySelector('[name="review_text"]').value.trim(),
        }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Your review could not be saved.');
      status.textContent = data.message;
      loadCatalog();
    } catch (error) {
      status.textContent = error.message || 'Your review could not be saved.';
      button.disabled = false;
    }
  });

  loadCatalog();
})();
