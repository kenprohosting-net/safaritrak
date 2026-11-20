    </div>
    <footer class="<?= $isExploreDashboard ? 'explore-footer' : '' ?>"><span>© <?= date('Y') ?> SafariTrak</span><span>Your journey. Safer together.</span></footer>
  </main>
</div>
<?php if ($loadMapLibre): ?>
  <script src="https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.js"></script>
<?php elseif ($loadLeaflet): ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard.js') ?>"></script>
<script src="map-view-state.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-view-state.js') ?>"></script>
<script src="location-autocomplete.js?v=<?= hash_file('sha256', __DIR__ . '/../../location-autocomplete.js') ?>"></script>
<?php if (!empty($loadNotifications)): ?>
  <script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/../../notifications-widget.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($loadDashboardMap)): ?>
  <script src="dashboard-map.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard-map.js') ?>"></script>
  <script src="dashboard-planner.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard-planner.js') ?>"></script>
<?php endif; ?>
</body>
</html>
