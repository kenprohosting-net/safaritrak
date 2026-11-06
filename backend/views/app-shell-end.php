    </div>
    <footer class="<?= $isExploreDashboard ? 'explore-footer' : '' ?>"><span>© <?= date('Y') ?> SafariTrak</span><span>Your journey. Safer together.</span></footer>
    <nav class="mobile-bottom-nav" aria-label="Quick navigation">
      <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-map"></i><span>Map</span></a>
      <a href="messages.php" class="<?= $activeNav === 'messages' ? 'active' : '' ?>" <?= $activeNav === 'messages' ? 'aria-current="page"' : '' ?>><i class="fa-regular fa-message"></i><span>Chat</span><?php if ($unreadConversationCount > 0): ?><em><?= $unreadConversationCount ?></em><?php endif; ?></a>
      <a href="places.php" class="<?= $activeNav === 'places' ? 'active' : '' ?>" <?= $activeNav === 'places' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-map-pin"></i><span>Places</span></a>
      <a href="my-journeys.php" class="<?= $activeNav === 'trips' ? 'active' : '' ?>" <?= $activeNav === 'trips' ? 'aria-current="page"' : '' ?>><i class="fa-solid fa-route"></i><span>Trips</span></a>
      <button type="button" data-mobile-more aria-controls="sidebar" aria-label="Open more navigation"><i class="fa-solid fa-ellipsis"></i><span>More</span></button>
    </nav>
  </main>
</div>
<?php if ($loadLeaflet): ?>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard.js') ?>"></script>
<script src="map-view-state.js?v=<?= hash_file('sha256', __DIR__ . '/../../map-view-state.js') ?>"></script>
<script src="location-autocomplete.js?v=<?= hash_file('sha256', __DIR__ . '/../../location-autocomplete.js') ?>"></script>
<?php if (!empty($loadNotifications)): ?>
  <script src="notifications-widget.js?v=<?= hash_file('sha256', __DIR__ . '/../../notifications-widget.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($loadDashboardMap)): ?>
  <script src="dashboard-map-google.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard-map-google.js') ?>"></script>
  <script src="dashboard-planner.js?v=<?= hash_file('sha256', __DIR__ . '/../../dashboard-planner.js') ?>"></script>
<?php endif; ?>
</body>
</html>
