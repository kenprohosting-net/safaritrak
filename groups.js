(() => {
  const createDialog = document.getElementById('createGroupDialog');
  const inviteDialog = document.getElementById('inviteGroupDialog');
  const settingsDialog = document.getElementById('groupSettingsDialog');
  const groupDialogs = [createDialog, inviteDialog, settingsDialog];
  const groupId = document.getElementById('selectedGroupConfig')?.dataset.groupId;
  const messages = document.getElementById('groupMessages');
  const status = document.getElementById('groupChatStatus');
  const locationStatus = document.getElementById('groupLocationStatus');
  const search = document.getElementById('groupSearch');
  const locationButton = document.getElementById('shareGroupLocation');
  let locationWatchId = null;
  let locationSharing = false;
  let locationPostInProgress = false;
  let stoppingLocationShare = false;
  let locationUpdatePromise = Promise.resolve();
  let lastLocationPostAt = 0;
  let lastKnownPosition = null;
  let hasFittedGroupLocations = false;
  let refreshTimer;

  function showDialog(dialog) {
    if (!dialog) return;
    if (dialog.parentElement !== document.body) document.body.append(dialog);
    dialog.hidden = false;
    document.body.classList.add('groups-dialog-open');
  }

  function hideDialog(dialog) {
    if (!dialog) return;
    dialog.hidden = true;
    if (groupDialogs.some(openDialog =>
      openDialog && openDialog !== dialog && !openDialog.hidden
    )) {
      return;
    }
    document.body.classList.remove('groups-dialog-open');
  }

  document.getElementById('createGroupOpen')?.addEventListener('click', () => showDialog(createDialog));
  document.getElementById('groupInviteOpen')?.addEventListener('click', () => showDialog(inviteDialog));
  document.getElementById('groupSettingsOpen')?.addEventListener('click', () => showDialog(settingsDialog));
  document.getElementById('groupSettingsInvite')?.addEventListener('click', () => {
    hideDialog(settingsDialog);
    showDialog(inviteDialog);
  });
  document.querySelectorAll('[data-close-group-dialog]').forEach(button => {
    button.addEventListener('click', () => hideDialog(button.closest('.groups-dialog')));
  });
  groupDialogs.forEach(dialog => dialog?.addEventListener('click', event => {
    if (event.target === dialog) hideDialog(dialog);
  }));
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    groupDialogs.forEach(dialog => {
      if (dialog && !dialog.hidden) hideDialog(dialog);
    });
  });
  document.getElementById('groupOrganization')?.addEventListener('change', event => {
    document.getElementById('organizationGroupMembers').hidden = !event.currentTarget.checked;
  });

  search?.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('[data-group-search-item]').forEach(item => {
      const matches = item.dataset.groupName.toLocaleLowerCase().includes(query);
      item.hidden = !matches;
      if (matches) visibleCount += 1;
    });
    const empty = document.getElementById('groupSearchEmpty');
    if (empty) {
      empty.hidden = visibleCount > 0;
      empty.textContent = query ? 'No groups match your search.' : 'No groups are available.';
    }
  });

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
    }
    return payload;
  }

  document.querySelectorAll('[data-remove-group-member]').forEach(button => {
    button.addEventListener('click', async () => {
      const memberId = Number.parseInt(button.dataset.removeGroupMember, 10);
      if (!Number.isSafeInteger(memberId) || memberId < 1) return;
      const memberName = button.dataset.memberName || 'this member';
      if (!window.confirm(`Remove ${memberName} from this group?`)) return;
      const feedback = document.getElementById('groupSettingsStatus');
      button.disabled = true;
      feedback.textContent = `Removing ${memberName}…`;
      try {
        const result = await postJson('backend/api/groups/persistent/membership.php', {
          group_id: groupId,
          action: 'remove',
          user_id: memberId,
        });
        feedback.textContent = result.message;
        window.location.reload();
      } catch (error) {
        feedback.textContent = error.message || 'The member could not be removed.';
        button.disabled = false;
      }
    });
  });

  document.getElementById('leaveGroupButton')?.addEventListener('click', async event => {
    if (!window.confirm('Leave this group? You will no longer see its messages or shared locations.')) return;
    const button = event.currentTarget;
    const feedback = document.getElementById('groupSettingsStatus');
    button.disabled = true;
    feedback.textContent = 'Leaving group…';
    try {
      await postJson('backend/api/groups/persistent/membership.php', {
        group_id: groupId,
        action: 'leave',
      });
      window.location.href = 'groups.php';
    } catch (error) {
      feedback.textContent = error.message || 'You could not leave the group.';
      button.disabled = false;
    }
  });

  document.getElementById('createGroupForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = document.getElementById('createGroupStatus');
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    feedback.textContent = 'Creating group…';
    try {
      const result = await postJson('backend/api/groups/persistent/create.php', {
        name: document.getElementById('groupName').value.trim(),
        description: document.getElementById('groupDescription').value.trim(),
        organization_id: document.getElementById('groupOrganization')?.checked
          ? document.getElementById('groupOrganization').value
          : null,
        member_ids: Array.from(document.querySelectorAll('.organization-group-member:checked'))
          .map(input => Number.parseInt(input.value, 10)),
      });
      location.href = `groups.php?id=${encodeURIComponent(result.group_id)}`;
    } catch (error) {
      feedback.textContent = error.message;
      button.disabled = false;
    }
  });

  async function createGroupInvite(button, inviteData, purpose) {
    const feedback = document.getElementById('groupInviteStatus');
    button.disabled = true;
    feedback.textContent = purpose === 'username' ? 'Sending invitation…' : 'Creating share link…';
    try {
      const result = await postJson('backend/api/groups/persistent/invite.php', { group_id: groupId, ...inviteData });
      document.getElementById('groupInviteUrl').value = result.invite_url;
      document.getElementById('groupInviteUrlLabel').textContent = purpose === 'username'
        ? `Private invite for @${result.invited_username} · expires in 7 days`
        : 'Reusable invite link · expires in 7 days';
      document.getElementById('groupInviteResult').hidden = false;
      if (purpose === 'username') {
        feedback.textContent = result.email_sent
          ? `Invitation sent to @${result.invited_username}. Only that account can use this link.`
          : `The invitation link for @${result.invited_username} is ready, but the email could not be sent. Copy and share it directly.`;
      } else if (inviteData.email) {
        feedback.textContent = result.email_sent
          ? 'Reusable link created and emailed. You can also copy it below.'
          : 'Reusable link created, but the email could not be sent. Copy the link below.';
      } else {
        feedback.textContent = 'Reusable link ready. Anyone with the link can join until it expires.';
      }
    } catch (error) {
      feedback.textContent = error.message;
    }
    button.disabled = false;
  }

  document.getElementById('groupInviteForm')?.addEventListener('submit', event => {
    event.preventDefault();
    const username = document.getElementById('groupInviteUsername').value.trim();
    if (!username) return;
    createGroupInvite(event.currentTarget.querySelector('[type="submit"]'), { username }, 'username');
  });
  document.getElementById('createGroupInviteLink')?.addEventListener('click', event => {
    const email = document.getElementById('groupInviteEmail').value.trim();
    createGroupInvite(event.currentTarget, { email }, 'link');
  });

  document.getElementById('copyGroupInvite')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const input = document.getElementById('groupInviteUrl');
    try {
      await navigator.clipboard.writeText(input.value);
      button.textContent = 'Copied';
    } catch (error) {
      input.select();
      const copied = document.execCommand('copy');
      button.textContent = copied ? 'Copied' : 'Copy';
      if (!copied) document.getElementById('groupInviteStatus').textContent = 'Select and copy the link from the field.';
    }
  });

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).filter(Boolean).slice(0, 2)
      .map(part => Array.from(part)[0] || '').join('').toLocaleUpperCase() || '?';
  }

  function createAvatar(name, path) {
    const avatar = document.createElement('span');
    avatar.className = 'groups-message-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    const avatarUrl = path ? new URL(String(path), location.href) : null;
    if (avatarUrl && avatarUrl.origin === location.origin && ['http:', 'https:'].includes(avatarUrl.protocol)) {
      const image = document.createElement('img');
      image.src = avatarUrl.href;
      image.alt = '';
      image.loading = 'lazy';
      avatar.append(image);
    } else {
      avatar.textContent = initials(name);
    }
    return avatar;
  }

  function localMessagePhotoUrl(path) {
    if (typeof path !== 'string' || path === '') return '';
    try {
      const url = new URL(path, location.href);
      return url.origin === location.origin ? url.href : '';
    } catch (error) {
      return '';
    }
  }

  function renderMessages(items) {
    const fragment = document.createDocumentFragment();
    if (!items.length) {
      const empty = document.createElement('li');
      empty.className = 'groups-chat-empty';
      empty.textContent = 'No messages yet. Say hello to your group.';
      fragment.append(empty);
    }
    for (const item of items) {
      const row = document.createElement('li');
      row.className = `groups-chat-message${item.is_mine ? ' is-mine' : ''}`;
      const content = document.createElement('div');
      content.className = 'groups-message-content';
      const metadata = document.createElement('div');
      metadata.className = 'groups-message-metadata';
      metadata.append(createAvatar(item.sender_name, item.sender_avatar_path));
      const author = document.createElement('strong');
      author.textContent = item.is_mine ? 'You' : item.sender_name;
      const time = document.createElement('small');
      time.textContent = new Date(item.created_at.replace(' ', 'T')).toLocaleString([], {
        hour: '2-digit',
        minute: '2-digit',
      });
      metadata.append(author, time);
      content.append(metadata);
      if (item.message_type === 'photo') {
        const photoUrl = localMessagePhotoUrl(item.attachment_url);
        if (photoUrl) {
          const figure = document.createElement('figure');
          figure.className = 'groups-message-photo';
          const image = document.createElement('img');
          image.src = photoUrl;
          image.alt = `Photo from ${item.trip_label || 'a shared trip'}`;
          image.loading = 'lazy';
          const label = document.createElement('figcaption');
          label.textContent = `Trip · ${item.trip_label || 'Trip'}`;
          figure.append(image, label);
          content.append(figure);
        }
        if (item.body) {
          const caption = document.createElement('p');
          caption.className = 'groups-message-photo-caption';
          caption.textContent = item.body;
          content.append(caption);
        }
      } else if (item.message_type === 'location' && item.location
        && Number.isFinite(Number(item.location.lat)) && Number.isFinite(Number(item.location.lng))) {
        const preview = document.createElement('button');
        preview.type = 'button';
        preview.className = 'groups-location-preview';
        preview.setAttribute('aria-label', 'Open shared location on the map');
        const mapPreview = document.createElement('span');
        mapPreview.className = 'groups-mini-map';
        mapPreview.setAttribute('aria-hidden', 'true');
        const pin = document.createElement('i');
        pin.className = 'fa-solid fa-location-dot';
        mapPreview.append(pin);
        const description = document.createElement('span');
        description.className = 'groups-location-description';
        description.textContent = 'Shared a location point.';
        const action = document.createElement('small');
        action.textContent = 'Open on map';
        preview.append(mapPreview, description, action);
        preview.addEventListener('click', () => {
          try {
            const pageMap = window.SafariTrakPageMap;
            if (typeof pageMap?.showSearchLocation !== 'function') {
              throw new Error('The map is not available right now.');
            }
            pageMap.showSearchLocation({
              lat: Number(item.location.lat),
              lng: Number(item.location.lng),
              label: 'Shared location',
            });
            status.textContent = 'Shared location shown on the map.';
          } catch (error) {
            status.textContent = error.message || 'The shared location could not be shown on the map.';
          }
        });
        content.append(preview);
      } else {
        const body = document.createElement('p');
        body.textContent = item.body;
        content.append(body);
      }
      row.append(content);
      fragment.append(row);
    }
    messages.replaceChildren(fragment);
    messages.scrollTop = messages.scrollHeight;
  }

  function markGroupRead() {
    const badge = document.querySelector(`[data-group-unread="${groupId}"]`);
    if (badge) {
      badge.hidden = true;
      badge.textContent = '';
      badge.removeAttribute('aria-label');
    }
  }

  async function refreshMessages() {
    if (!groupId || !messages) return;
    try {
      const url = new URL('backend/api/groups/persistent/messages.php', location.href);
      url.searchParams.set('group_id', groupId);
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || !payload.success || !Array.isArray(payload.messages)) {
        throw new Error(payload.message || 'Group messages could not be loaded.');
      }
      renderMessages(payload.messages);
      markGroupRead();
      status.textContent = '';
    } catch (error) {
      status.textContent = error.message;
    }
  }

  document.getElementById('groupMessageForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const input = document.getElementById('groupMessageInput');
    const button = event.currentTarget.querySelector('[type="submit"]');
    if (!input.value.trim()) return;
    button.disabled = true;
    try {
      await postJson('backend/api/groups/persistent/messages.php', {
        group_id: groupId,
        body: input.value.trim(),
      });
      input.value = '';
      await refreshMessages();
    } catch (error) {
      status.textContent = error.message;
    } finally {
      button.disabled = false;
      input.focus();
    }
  });

  const groupPhotoFile = document.getElementById('groupPhotoFile');
  const groupPhotoDetails = document.getElementById('groupPhotoDetails');
  const groupPhotoPreview = document.getElementById('groupPhotoPreview');
  let groupPhotoPreviewUrl = '';
  function clearGroupPhoto() {
    if (groupPhotoPreviewUrl) URL.revokeObjectURL(groupPhotoPreviewUrl);
    groupPhotoPreviewUrl = '';
    if (groupPhotoFile) groupPhotoFile.value = '';
    const tripLabel = document.getElementById('groupPhotoTripLabel');
    const caption = document.getElementById('groupPhotoCaption');
    if (tripLabel) tripLabel.value = '';
    if (caption) caption.value = '';
    if (groupPhotoPreview) {
      groupPhotoPreview.removeAttribute('src');
      groupPhotoPreview.hidden = true;
    }
    if (groupPhotoDetails) groupPhotoDetails.hidden = true;
  }

  document.getElementById('attachGroupPhoto')?.addEventListener('click', () => groupPhotoFile?.click());
  groupPhotoFile?.addEventListener('change', () => {
    const file = groupPhotoFile.files?.[0];
    if (!file) return;
    if (file.size > 6 * 1024 * 1024) {
      locationStatus.textContent = 'Choose a photo no larger than 6 MB.';
      clearGroupPhoto();
      return;
    }
    if (groupPhotoPreviewUrl) URL.revokeObjectURL(groupPhotoPreviewUrl);
    groupPhotoPreviewUrl = URL.createObjectURL(file);
    groupPhotoPreview.src = groupPhotoPreviewUrl;
    groupPhotoPreview.hidden = false;
    groupPhotoDetails.hidden = false;
    document.getElementById('groupPhotoTripLabel')?.focus();
  });
  document.getElementById('cancelGroupPhoto')?.addEventListener('click', clearGroupPhoto);
  document.getElementById('sendGroupPhoto')?.addEventListener('click', async event => {
    const file = groupPhotoFile?.files?.[0];
    const tripLabel = document.getElementById('groupPhotoTripLabel');
    const caption = document.getElementById('groupPhotoCaption');
    const button = event.currentTarget;
    if (!groupId || !file || !tripLabel || !caption) return;
    tripLabel.value = tripLabel.value.trim();
    if (!tripLabel.reportValidity()) return;

    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('trip_label', tripLabel.value);
    formData.append('caption', caption.value.trim());
    formData.append('photo', file);
    button.disabled = true;
    locationStatus.textContent = 'Sharing photo…';
    try {
      const response = await fetch('backend/api/groups/persistent/send-photo.php', {
        method: 'POST',
        body: formData,
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'The photo could not be shared.');
      }
      clearGroupPhoto();
      locationStatus.textContent = '';
      await refreshMessages();
    } catch (error) {
      locationStatus.textContent = error.message || 'The photo could not be shared.';
    } finally {
      button.disabled = false;
    }
  });

  function setLocationButton(sharing) {
    if (!locationButton) return;
    locationButton.classList.toggle('is-sharing', sharing);
    locationButton.setAttribute('aria-pressed', String(sharing));
    locationButton.setAttribute('aria-label', sharing ? 'Stop sharing your live location' : 'Share your live location with this group');
    locationButton.title = sharing ? 'Stop sharing your live location' : 'Share your live location with this group';
    locationButton.innerHTML = sharing
      ? '<i class="fa-solid fa-location-dot" aria-hidden="true"></i>'
      : '<i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>';
  }

  function stopWatchingLocation() {
    if (locationWatchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(locationWatchId);
    }
    locationWatchId = null;
  }

  async function refreshGroupLocations() {
    if (!groupId) return;
    const url = new URL('backend/api/groups/persistent/locations.php', location.href);
    url.searchParams.set('group_id', groupId);
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    const payload = await response.json();
    if (!response.ok || !payload.success || !Array.isArray(payload.locations)) {
      throw new Error(payload.message || 'Group locations could not be loaded.');
    }
    const features = payload.locations.map(item => ({
      kind: 'shared-location',
      lat: Number(item.lat),
      lng: Number(item.lng),
      label: item.is_mine ? 'You' : item.name,
      description: `${item.name} is sharing their live location with this group.`,
    }));
    const pageMap = window.SafariTrakPageMap;
    if (typeof pageMap?.setSharedLocationFeatures === 'function') {
      pageMap.setSharedLocationFeatures(features, {
        fit: !hasFittedGroupLocations && features.length > 0,
      });
      if (features.length) hasFittedGroupLocations = true;
    } else {
      throw new Error('The group map is not available right now.');
    }
    if (!stoppingLocationShare) {
      locationSharing = Boolean(payload.sharing);
      setLocationButton(locationSharing);
      if (locationSharing && locationWatchId === null) startWatchingLocation();
      if (!locationSharing) stopWatchingLocation();
    }
  }

  function startWatchingLocation() {
    if (!navigator.geolocation || locationWatchId !== null) return;
    locationWatchId = navigator.geolocation.watchPosition(position => {
      lastKnownPosition = position;
      if (locationSharing) updateSharedLocation(position);
    }, error => {
      locationStatus.textContent = error.code === error.PERMISSION_DENIED
        ? 'Location permission was denied. Stop sharing or allow location access in browser settings.'
        : 'Your live location could not be updated. Check GPS and try again.';
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 });
  }

  async function updateSharedLocation(position) {
    if (!locationSharing || locationPostInProgress || Date.now() - lastLocationPostAt < 10000) return;
    locationPostInProgress = true;
    lastLocationPostAt = Date.now();
    locationUpdatePromise = (async () => {
      try {
        await postJson('backend/api/groups/persistent/locations.php', {
          group_id: groupId,
          action: 'share',
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        });
        if (locationSharing) await refreshGroupLocations();
      } catch (error) {
        locationStatus.textContent = error.message || 'Your live location could not be updated.';
      } finally {
        locationPostInProgress = false;
      }
    })();
    return locationUpdatePromise;
  }

  async function beginLocationSharing(button) {
    if (!navigator.geolocation) {
      locationStatus.textContent = 'This browser does not support device location.';
      return;
    }
    button.disabled = true;
    locationStatus.textContent = 'Waiting for location permission…';
    navigator.geolocation.getCurrentPosition(async position => {
      lastKnownPosition = position;
      try {
        await postJson('backend/api/groups/persistent/locations.php', {
          group_id: groupId,
          action: 'share',
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        });
        locationSharing = true;
        lastLocationPostAt = Date.now();
        setLocationButton(true);
        startWatchingLocation();
      } catch (error) {
        locationSharing = false;
        lastKnownPosition = null;
        setLocationButton(false);
        locationStatus.textContent = error.message;
        button.disabled = false;
        return;
      }
      try {
        await refreshGroupLocations();
        locationStatus.textContent = 'Live location sharing is on for this group. Turn it off here when you are done.';
      } catch (error) {
        locationStatus.textContent = error.message || 'Location sharing is on, but the group map could not be refreshed.';
      } finally {
        button.disabled = false;
      }
    }, error => {
      locationStatus.textContent = error.code === error.PERMISSION_DENIED
        ? 'Location permission was denied. Allow it to share your live location with this group.'
        : 'Your location could not be determined. Try again when GPS is available.';
      button.disabled = false;
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 });
  }

  locationButton?.addEventListener('click', async () => {
    if (!locationSharing) {
      beginLocationSharing(locationButton);
      return;
    }
    locationButton.disabled = true;
    locationSharing = false;
    stoppingLocationShare = true;
    stopWatchingLocation();
    try {
      await locationUpdatePromise;
      await postJson('backend/api/groups/persistent/locations.php', {
        group_id: groupId,
        action: 'stop',
      });
      lastKnownPosition = null;
      setLocationButton(false);
      stoppingLocationShare = false;
      await refreshGroupLocations();
      locationStatus.textContent = 'You stopped sharing your location with this group.';
    } catch (error) {
      stoppingLocationShare = false;
      locationSharing = true;
      setLocationButton(true);
      startWatchingLocation();
      locationStatus.textContent = error.message || 'Location sharing could not be stopped. Try again.';
    } finally {
      locationButton.disabled = false;
    }
  });

  if (groupId && messages) {
    refreshMessages();
    refreshGroupLocations().catch(error => {
      locationStatus.textContent = error.message || 'Group locations could not be loaded.';
    });
    refreshTimer = window.setInterval(() => {
      refreshMessages();
      refreshGroupLocations().catch(error => {
        locationStatus.textContent = error.message || 'Group locations could not be loaded.';
      });
      if (locationSharing && lastKnownPosition && Date.now() - lastLocationPostAt >= 45000) {
        updateSharedLocation(lastKnownPosition);
      }
    }, 10000);
    window.addEventListener('pagehide', () => {
      window.clearInterval(refreshTimer);
      stopWatchingLocation();
    }, { once: true });
  }
})();
