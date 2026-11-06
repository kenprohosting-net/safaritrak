<button id="appMapPanelsToggle" class="app-map-panels-toggle" type="button" aria-label="Show hidden page panels" title="Back to panels" hidden>
  <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to panels</span>
</button>
<?php if (!empty($showAppMapBackground)): ?>
<div id="appMapBackground" data-map-user-id="<?= (int) $currentUser['id'] ?>" role="region" aria-label="Map background centered on your device location when browser permission is granted."></div>
<div id="appMapControls" class="app-map-controls" aria-label="Map controls">
  <button id="appMapFocus" type="button" aria-pressed="false" aria-label="Focus on the map">
    <i class="fa-solid fa-expand" aria-hidden="true"></i><span>Focus map</span>
  </button>
  <button id="appMapLocate" type="button" aria-label="Show my current location on the map" title="Show my location">
    <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><span>My location</span>
  </button>
</div>
<?php if (!empty($mapFeatures)): ?>
<script type="application/json" id="safariTrakMapFeatures"><?= json_encode($mapFeatures, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
<script defer src="map-view-state.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-view-state.js') ?>"></script>
<script src="app-map-background.js?v=<?= hash_file('sha256', __DIR__ . '/../../app-map-background.js') ?>"></script>
<script src="route-directions.js?v=<?= hash_file('sha256', __DIR__ . '/../../route-directions.js') ?>"></script>
<?php endif; ?>
