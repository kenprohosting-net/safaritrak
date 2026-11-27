<?php if (($showAppMapPanelsToggle ?? true) !== false): ?>
<button id="appMapPanelsToggle" class="app-map-panels-toggle" type="button" aria-label="Back" title="Back" hidden>
  <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back</span>
</button>
<?php endif; ?>
<?php $showAppMapBackground = $showAppMapBackground ?? true; ?>
<?php if (!empty($showAppMapBackground)): ?>
<div id="appMapBackground" data-map-user-id="<?= (int) $currentUser['id'] ?>" data-map-page="<?= in_array(($appMapPage ?? ''), ['journeys', 'groups', 'messages', 'car-hire', 'places', 'profile', 'school'], true) ? htmlspecialchars($appMapPage, ENT_QUOTES, 'UTF-8') : 'default' ?>" role="region" aria-label="Map centered on your current location when available; trip or location selections update the view."></div>
<?php if (($showAppMapControls ?? true) !== false): ?>
<div id="appMapControls" class="app-map-controls" aria-label="Map controls">
  <?php if (($showAppMapFocusControl ?? true) !== false): ?>
  <button id="appMapFocus" type="button" aria-pressed="false" aria-label="Focus on the map">
    <i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>
  </button>
  <?php endif; ?>
  <button id="appMapLocate" type="button" aria-label="Show my current location on the map" title="Show my location">
    <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><span>My location</span>
  </button>
</div>
<?php endif; ?>
<?php if (!empty($mapFeatures)): ?>
<script type="application/json" id="safariTrakMapFeatures"><?= json_encode($mapFeatures, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
<script defer src="map-view-state.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-view-state.js') ?>"></script>
<script src="map-style.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-style.js') ?>"></script>
<script src="app-map-background.js?v=<?= hash_file('sha256', __DIR__ . '/../../app-map-background.js') ?>"></script>
<script src="route-directions.js?v=<?= hash_file('sha256', __DIR__ . '/../../route-directions.js') ?>"></script>
<?php endif; ?>
