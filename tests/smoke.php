<?php

$checks = 0;

$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $checks++;
    echo "PASS {$message}\n";
};

$screens = require __DIR__ . '/../backend/config/planned-screens.php';
$requiredFields = ['title', 'description', 'phase', 'available_route', 'available_label'];

$expect(is_array($screens) && count($screens) >= 9, 'planned screen catalog is loaded');
$expect(isset($screens['groups'], $screens['group-chat'], $screens['globe'], $screens['android-permissions']), 'future product domains have explicit placeholder routes');

foreach ($screens as $screenId => $screen) {
    $expect((bool) preg_match('/^[a-z0-9-]+$/', $screenId), "screen id {$screenId} is URL-safe");

    foreach ($requiredFields as $field) {
        $expect(isset($screen[$field]) && is_string($screen[$field]) && $screen[$field] !== '', "screen {$screenId} has {$field}");
    }

    $routePath = strtok($screen['available_route'], '?');
    $expect(is_file(__DIR__ . '/../' . $routePath), "screen {$screenId} links to an existing PHP route");
}

foreach ([
    __DIR__ . '/../backend/views/app-shell-start.php',
    __DIR__ . '/../backend/views/app-shell-end.php',
    __DIR__ . '/../planned-screen.php',
    __DIR__ . '/../welcome.php',
] as $path) {
    $expect(is_file($path), basename($path) . ' exists');
}

$shellStart = file_get_contents(__DIR__ . '/../backend/views/app-shell-start.php');
$shellEnd = file_get_contents(__DIR__ . '/../backend/views/app-shell-end.php');
$dashboard = file_get_contents(__DIR__ . '/../index.php');
$dashboardScript = file_get_contents(__DIR__ . '/../dashboard.js');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, "localStorage.getItem('safaritrak-theme')") && str_contains($dashboardScript, "document.documentElement.dataset.theme = savedTheme"), 'shared page scripts restore the saved theme on legacy routes');
$fallbackDashboardMapScript = file_get_contents(__DIR__ . '/../dashboard-map.js');
$dashboardPlanner = file_get_contents(__DIR__ . '/../dashboard-planner.js');
$routeDirections = file_get_contents(__DIR__ . '/../route-directions.js');
$directionsApi = file_get_contents(__DIR__ . '/../backend/api/routes/directions.php');
$googleDashboardMapScript = file_get_contents(__DIR__ . '/../dashboard-map-google.js');
$mapLibreAppMapBackground = file_get_contents(__DIR__ . '/../app-map-background.js');
$googleMapLoader = file_get_contents(__DIR__ . '/../google-maps-loader.js');
$googleLocationMarker = file_get_contents(__DIR__ . '/../google-location-marker.js');
$googleDarkMapStyle = file_get_contents(__DIR__ . '/../assets/google-maps-dark-style.json');
$dashboardCss = file_get_contents(__DIR__ . '/../dashboard.css');
$mapViewState = file_get_contents(__DIR__ . '/../map-view-state.js');
$googleAppMapBackground = file_get_contents(__DIR__ . '/../app-map-background-google.js');
$appMapBackgroundView = file_get_contents(__DIR__ . '/../backend/views/app-map-background.php');
$sharedLocationsApi = file_get_contents(__DIR__ . '/../backend/api/journeys/active-shared-locations.php');
$locationAutocomplete = file_get_contents(__DIR__ . '/../location-autocomplete.js');
$locationSuggestionsApi = file_get_contents(__DIR__ . '/../backend/api/places/suggest.php');
$journeyStartScript = file_get_contents(__DIR__ . '/../start-journey.js');
$journeyStartApi = file_get_contents(__DIR__ . '/../backend/api/journeys/start.php');
$journeyActivationApi = file_get_contents(__DIR__ . '/../backend/api/journeys/activate.php');
$plannedJourneyMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0008_planned_journeys.php');
$contactSearchApi = file_get_contents(__DIR__ . '/../backend/api/contacts/search.php');
$contactRequestApi = file_get_contents(__DIR__ . '/../backend/api/contacts/add.php');
$contactInviteApi = file_get_contents(__DIR__ . '/../backend/api/contacts/invite.php');
$registerApi = file_get_contents(__DIR__ . '/../backend/api/register.php');
$liveShareApi = file_get_contents(__DIR__ . '/../backend/api/journeys/public-share.php');
$liveShareCreationApi = file_get_contents(__DIR__ . '/../backend/api/journeys/share-link.php');
$appUrlHelper = file_get_contents(__DIR__ . '/../backend/includes/app-url.php');
$inviteMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0005_trusted_contact_invites.sql');
$liveShareMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0006_journey_share_links.sql');
$trustedContactsScript = file_get_contents(__DIR__ . '/../trusted-contacts.js');
$expect(is_string($shellStart) && str_contains($shellStart, 'id="startPoint"') && str_contains($shellStart, 'id="endPoint"'), 'Explore planner offers separate origin and destination fields');
$expect(is_string($shellEnd) && str_contains($shellEnd, 'dashboard-planner.js'), 'Explore loads the route planning and trip actions');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'SafariTrakRouteDirections.getDrivingRoute') && str_contains($dashboardPlanner, 'setPlannedRoute'), 'Explore previews a road route on the map');
$expect(is_string($directionsApi) && str_contains($directionsApi, 'st_require_login()') && str_contains($directionsApi, 'routing.openstreetmap.de') && str_contains($directionsApi, 'CURLOPT_TIMEOUT => 6'), 'Road directions use an authenticated, bounded server request with a provider fallback');
$expect(is_string($routeDirections) && str_contains($routeDirections, 'directions.php') && str_contains($routeDirections, 'router.project-osrm.org') && str_contains($routeDirections, 'Promise.any') && str_contains($routeDirections, 'Route search timed out'), 'Shared route client races browser-accessible directions against the authenticated fallback');
$expect(is_string($fallbackDashboardMapScript) && str_contains($fallbackDashboardMapScript, 'setPlannedRoute: setPlannedDashboardRoute') && str_contains($fallbackDashboardMapScript, "'#C026D3'") && str_contains($fallbackDashboardMapScript, 'safaritrak-planned-route-core'), 'Backup flat map draws a fitted, glowing magenta planned road route');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'routeAvailable') && str_contains($dashboardPlanner, 'retryDashboardRoute') && str_contains($dashboardPlanner, 'dashboard-route-focus'), 'Explore requires a route before trip actions and can focus the map');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'routeRequestId') && str_contains($dashboardPlanner, 'up to 15 seconds'), 'Explore ignores stale route responses and explains its bounded search time');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, "fields.startLabel.addEventListener('safaritrak-location-selected'") && str_contains($dashboardPlanner, 'locationSelected(event, fields.endLat, fields.endLng)'), 'Selecting either Explore suggestion directly supplies coordinates and starts route lookup');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'select(options[activeIndex >= 0 ? activeIndex : 0])') && str_contains($locationAutocomplete, 'Choose a place from the suggestions before finding a route'), 'Explore location suggestions can be selected with Enter and explain the route prerequisite');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'fetchBrowserSuggestions') && str_contains($locationAutocomplete, 'Promise.any') && str_contains($locationAutocomplete, 'activeController?.abort()'), 'Autocomplete races Google against browser geocoders and cancels stale searches');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'timeoutMilliseconds = 12000') && str_contains($locationAutocomplete, 'Google Places search timed out.'), 'Autocomplete allows slow public geocoders to return while keeping searches bounded');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'session_write_close()') && str_contains($locationSuggestionsApi, 'CURLOPT_TIMEOUT => 3'), 'Server geocoding releases the session lock and bounds provider delays');
$expect(is_string($directionsApi) && str_contains($directionsApi, 'session_write_close()'), 'Route provider requests do not hold the authenticated session lock');
$expect(is_string($shellStart) && str_contains($shellStart, 'Type and select both locations from the suggestions to preview a route'), 'Explore explains that route locations must be selected from autocomplete');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'dashboard-route-spinner') && str_contains($dashboardCss, '.explore-shortcuts a:nth-child(3) i') && str_contains($dashboardCss, '.group-hub-tabs'), 'Explore and shared navigation use route loading feedback and a unified groups hub');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'planned_departure_at: plannedDeparture') && str_contains($dashboardPlanner, 'share-link.php') && is_string($shellStart) && str_contains($shellStart, 'Schedule trip'), 'Explore can schedule and share a saved trip');
$expect(is_string($shellStart) && str_contains($shellStart, 'id="useCurrentStartLocation"') && str_contains($shellStart, 'id="sharedJourneyUrl"') && str_contains($shellStart, 'View shared journey'), 'Explore offers current-location origin and pasted shared-journey controls');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'startUsesCurrentLocation') && str_contains($dashboardPlanner, 'getCurrentPosition') && str_contains($dashboardPlanner, 'action === \'start\' && !startUsesCurrentLocation'), 'Starting immediately requires an explicitly selected current device location');
$expect(is_string($dashboardPlanner) && str_contains($dashboardPlanner, 'public-share.php') && str_contains($dashboardPlanner, 'shared-location.php') && str_contains($dashboardPlanner, 'setInterval') && str_contains($dashboardPlanner, 'showSharedJourney'), 'Pasted shared links are validated, refreshed, and displayed on the map');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'showSharedJourney') && str_contains($googleDashboardMapScript, "strokeColor: '#C026D3'") && str_contains($googleDashboardMapScript, 'publicJourneyLines'), 'Google map renders shared journey locations and its magenta route glow');
$expect(is_string($fallbackDashboardMapScript) && str_contains($fallbackDashboardMapScript, 'showSharedDashboardJourney') && str_contains($fallbackDashboardMapScript, "'#C026D3'") && str_contains($fallbackDashboardMapScript, 'public-shared-current'), 'Fallback map renders shared journey locations and its magenta route glow');
$expect(is_string($shellEnd) && str_contains($shellEnd, 'mobile-bottom-nav'), 'shared shell provides mobile quick navigation');
$expect(is_string($dashboard) && str_contains($dashboard, 'safariDashboardMapConfig'), 'Explore dashboard provides its map configuration');
$expect(is_string($dashboard) && str_contains($dashboard, 'id="dashboardMapCanvas"'), 'Explore keeps map controls outside the Google Maps rendering container');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "getElementById('dashboardMapCanvas')"), 'Explore initializes Google Maps on its dedicated canvas');
$expect(is_string($dashboard) && str_contains($dashboard, 'data-dashboard-projection="flat" aria-selected="true"'), 'Explore defaults to a flat map');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'mapTypeId: saved?.projection'), 'Explore supports Google road and satellite map modes');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'window.SafariTrakMapView?.save'), 'Explore saves its Google map camera for other pages');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'styles: mapStyles()') && str_contains($googleDashboardMapScript, 'mapId ? { mapId }'), 'Explore uses the Cloud Map ID when configured and falls back to local styles');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'billingWarning') && str_contains($googleDashboardMapScript, 'Google Maps reports a configuration problem') && str_contains($googleDashboardMapScript, 'browser-key restrictions'), 'Explore explains the generic Google Maps configuration warning without misdiagnosing billing');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'bounds.contains(marker.getPosition())'), 'Explore recentres on the user marker when it is outside the visible map bounds');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-heading.has-error') && str_contains($dashboardCss, 'max-width: min(340px'), 'Explore map billing errors stay in a compact overlay');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "visibility: 'off'"), 'Explore hides point-of-interest icons and labels');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'SafariTrakGoogleLocationMarker.create'), 'Explore uses the pulsing HTML user-location marker');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'cachedLocation || saved') && str_contains($googleDashboardMapScript, 'if (!state.pendingSearchLocation) locateOnMap(true, 16)'), 'Explore starts centered and zoomed on the fresh device location');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'scrollwheel: false'), 'Explore disables wheel zoom');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'sharedMarkerListeners'), 'Explore does not accumulate shared-location marker listeners');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, 'maps.googleapis.com/maps/api/js'), 'Google Maps JavaScript API loader is configured');
$expect(is_string($shellStart) && preg_match('/<script src="google-maps-fallback\.js\?[^"]+"><\/script>/', $shellStart) === 1, 'Explore loads the backup map runtime before dashboard initialization');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, 'libraries=places'), 'Google Places library loads with Maps');
$expect(is_string($googleMapLoader) && str_contains($googleMapLoader, '}, 30_000)') && str_contains($googleMapLoader, "importLibrary('maps')") && str_contains($googleMapLoader, "typeof window.google?.maps?.Map === 'function'"), 'Google Maps loader waits for its Maps library and tolerates slow API responses');
$googleStyleRules = json_decode((string) $googleDarkMapStyle, true, 512, JSON_THROW_ON_ERROR);
$expect(is_array($googleStyleRules) && count($googleStyleRules) >= 12, 'Google Maps dark style import is valid JSON');
$expect(is_string($googleDarkMapStyle) && str_contains($googleDarkMapStyle, '#0F172A') && str_contains($googleDarkMapStyle, '#1E293B'), 'Google Maps style uses the Cyber Dark land and border colors');
$expect(is_string($googleDarkMapStyle) && str_contains($googleDarkMapStyle, '"featureType": "poi"') && str_contains($googleDarkMapStyle, '"visibility": "off"'), 'Google Maps style hides point-of-interest icons and labels');
$expect(is_string($googleLocationMarker) && str_contains($googleLocationMarker, 'OverlayView') && str_contains($googleLocationMarker, 'google-user-location-marker__pulse'), 'Custom current-location marker is an accessible Google Maps HTML overlay');
$expect(is_string($mapViewState) && str_contains($mapViewState, 'safaritrak-map-view-v3') && str_contains($mapViewState, "state.projection === 'satellite' ? 'satellite' : 'flat'"), 'Shared map camera state is scoped to the current tab and preserves flat-map defaults');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'cachedLocation || mapView') && str_contains($googleAppMapBackground, 'requestUserLocation(true)'), 'Shared map backgrounds prioritize current device location over a saved camera');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .sidebar {'), 'Explore navigation floats as a glass panel');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.sidebar:is(:hover, :focus-within)') && str_contains($dashboardCss, 'width: 244px;') && str_contains($dashboardCss, 'font-size: 12px;'), 'Desktop side navigation expands on hover or keyboard focus to show page names');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore > main > .app-header') && str_contains($dashboardCss, 'background: transparent !important;') && str_contains($dashboardCss, '.app--explore:has(> .sidebar:is(:hover, :focus-within)) .app-search-row') && str_contains($dashboardCss, 'left: 274px;'), 'Explore removes the empty full-width header bar and shifts floating content clear of the expanded navigation');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '--p: #10B981;') && str_contains($dashboardCss, '--panel: #111827;') && str_contains($dashboardCss, '--bg: #090D16;'), 'Signed-in pages use the Cyber Dark palette');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'journey-filter-bar::after') && str_contains($dashboardCss, '.content .card:has(.journey-list)'), 'Map page filters and repeated records render as separate floating tiles');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-heading {'), 'Explore map status uses a glass container');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '):is(:hover, :focus-within, .is-engaged)::after'), 'animated glass outline is limited to the interacted panel');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '@media (prefers-reduced-motion: reduce)'), 'floating outline respects reduced-motion preference');
$authThemeCss = file_get_contents(__DIR__ . '/../auth-theme.css');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, '.login-card:is(:hover, :focus-within, .is-engaged)::after'), 'auth panel outline activates only during interaction');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, 'backdrop-filter: blur(28px) saturate(145%)') && str_contains($authThemeCss, '--auth-panel: #111827d9') && str_contains($authThemeCss, '--auth-panel: #FFFFFFdc'), 'auth panels remain translucent and blurred in dark and light themes');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '--glass-surface: #111827d9') && str_contains($dashboardCss, '--glass-surface: #FFFFFFdc') && str_contains($dashboardCss, '--glass-blur: blur(28px) saturate(145%)'), 'dashboard glass surface tokens retain the same translucent blur in dark and light themes');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '[class$="-panel"]') && str_contains($dashboardCss, '[class$="-card"]') && str_contains($dashboardCss, 'input:not([type="checkbox"])'), 'all map-backed panels, forms, and text controls share the glass treatment');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, '--auth-glass-surface: #111827d9') && str_contains($authThemeCss, '--auth-glass-surface: #FFFFFFdc') && str_contains($authThemeCss, '--auth-glass-blur: blur(28px) saturate(145%)'), 'authentication panels and fields share the matching dark and light glass treatment');
$expect(is_string($authThemeCss) && str_contains($authThemeCss, 'body:has(.login-card)') && str_contains($authThemeCss, 'overflow: visible'), 'auth sheets scroll with the page instead of showing an inner side rail');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'google-user-location-marker__pulse'), 'current-location marker has a neon pulse treatment');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--map-layout:not(.app--explore) > main > header') && str_contains($dashboardCss, 'width: max-content'), 'signed-in page header is a compact corner control group');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .content--explore {'), 'Explore map fills the page beneath floating controls');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'app-map-background.js') && !str_contains($appMapBackgroundView, 'app-map-background-google.js') && str_contains($appMapBackgroundView, 'showAppMapBackground'), 'Shared map background is opt-in and uses the direct MapLibre runtime');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'appMapLocate'), 'Map-backed app pages provide an explicit location control');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'appMapFocus'), 'Map-backed pages provide an accessible map focus toggle');
$expect(is_string($appMapBackgroundView) && str_contains($appMapBackgroundView, 'appMapPanelsToggle'), 'Map-backed pages provide a control to restore hidden panels');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, 'page-panel-focus') && str_contains($dashboardScript, 'is-page-panel-selected'), 'Selecting a page panel focuses it over the map across map-backed screens');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, "event.target.closest('a, button, input, select, textarea, [role=\"button\"], [contenteditable=\"true\"]')"), 'Interactive controls do not trigger page-panel focus');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, ':has(.is-page-panel-selected)') && str_contains($dashboardCss, '.page-panel-focus .app--map-layout'), 'Map-backed pages hide competing panels while keeping the selected panel available');
$expect(is_string($mapLibreAppMapBackground) && str_contains($mapLibreAppMapBackground, 'function activateMap()') && str_contains($mapLibreAppMapBackground, 'mapLoadStarted = false') && !str_contains($mapLibreAppMapBackground, '  loadMapLibre();' . PHP_EOL . PHP_EOL . '  const themeObserver'), 'Page maps load only after a map feature is activated');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'map-focus-mode'), 'Map gestures hide floating panels and can restore them');
$navigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$expect(is_string($navigation) && str_contains($navigation, 'href="index.php"') && str_contains($navigation, 'href="places.php"'), 'Authenticated pages share the Explore navigation');
$expect(!is_string($navigation) || !str_contains($navigation, 'safety.php'), 'Standalone Safety navigation is removed');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "|| { lat: -1.286389, lng: 36.817223 }"), 'Shared Google map uses a non-personal default center');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'gestureHandling: \'greedy\''), 'Shared Google map preserves map panning');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'scrollwheel: false'), 'Shared Google map disables wheel zoom');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'focusFeatures') && str_contains($googleAppMapBackground, 'map.fitBounds(bounds, 100)'), 'Shared Google map fits bounds only when a page explicitly focuses selected features');
$expect(is_string($dashboardCss) && (bool) preg_match('/#appMapBackground\s*\{\s*position:\s*fixed;/', $dashboardCss), 'Shared map background is positioned on desktop as well as mobile');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'requestUserLocation(true)'), 'Shared Google map recenters and zooms to the device location');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'google.maps.Polyline'), 'Shared Google map renders supplied recorded routes');
$expect(is_string($mapLibreAppMapBackground) && str_contains($mapLibreAppMapBackground, 'function focusFeatures') && str_contains($mapLibreAppMapBackground, 'map.fitBounds('), 'Backup page map focuses and fits the selected trip route');
$expect(is_string($mapLibreAppMapBackground) && str_contains($mapLibreAppMapBackground, '!document.querySelector(\'.app--journeys\')'), 'My Trips loads only the selected journey onto the background map');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, '!document.querySelector(\'.app--journeys\')'), 'My Trips keeps the selected route in view instead of recentering on device location');
$journeysSource = file_get_contents(__DIR__ . '/../my-journeys.php');
$journeysScript = file_get_contents(__DIR__ . '/../journeys.js');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'journey_positions'), 'Journey history provides recorded route geometry to the map');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'journeyMapDetail') && str_contains($journeysSource, 'data-tab="planned"'), 'My trips shows selected route details and scheduled journeys');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'data-start-lat=') && str_contains($journeysSource, 'data-end-lat=') && str_contains($journeysScript, 'journeyMapDetailRouteStatus') && str_contains($journeysScript, 'focusFeatures(features)'), 'My trips focuses the selected trip route and updates its route details');
$expect(is_string($journeysSource) && str_contains($journeysSource, 'app--journeys') && str_contains($journeysSource, 'journeyPickerPanel') && str_contains($journeysScript, 'journeyPickerToggle'), 'My Trips keeps the journey picker collapsed while the selected trip panel stays visible');
$expect(is_string($journeysScript) && str_contains($journeysScript, "routeFeature.routeColor = 'planned'"), 'Selected My Trips route is rendered in red');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'width: 72px') && str_contains($dashboardCss, '.app--explore .sidebar') && str_contains($dashboardCss, 'content: attr(title)'), 'Desktop navigation is an icon rail with accessible hover labels across pages');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.app--explore .explore-shortcuts') && str_contains($dashboardCss, '.map-focus-mode .app--journeys .journey-map-detail'), 'Explore removes duplicate shaded shortcuts and map focus preserves selected trip details');
$expect(is_string($journeyActivationApi) && str_contains($journeyActivationApi, 'status = "planned"') && is_string($plannedJourneyMigration) && str_contains($plannedJourneyMigration, "ENUM('planned', 'active', 'completed', 'cancelled')"), 'Scheduled trips can be explicitly activated after their schema migration');
$expect(is_string($contactSearchApi) && str_contains($contactSearchApi, 'contact-search-user') && str_contains($contactSearchApi, 'LEFT(LOWER(u.username)'), 'Contacts can search verified people by public name or username');
$expect(is_string($contactRequestApi) && str_contains($contactRequestApi, 'contact_request') && str_contains($contactRequestApi, 'status)') && !str_contains($contactRequestApi, 'Direct contact creation is disabled'), 'Social contact requests create pending friend connections');
$placesScript = file_get_contents(__DIR__ . '/../places.js');
$expect(is_string($placesScript) && str_contains($placesScript, 'setFeatures(placeMapFeatures'), 'Nearby search results are drawn as map markers');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'setTimeout(() => fetchSuggestions(query), 250)'), 'Location suggestions debounce network requests while typing');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'aria-activedescendant'), 'Location suggestions support keyboard navigation');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'AutocompleteSuggestion.fetchAutocompleteSuggestions'), 'Location suggestions use Google Places typeahead');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'fetchServerSuggestions(query, signal)'), 'Location suggestions retain the authenticated server fallback');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'geocoding-api.open-meteo.com') && str_contains($locationSuggestionsApi, 'photon.komoot.io'), 'Location suggestions fall back from Open-Meteo to Photon when providers fail');
$expect(is_string($locationSuggestionsApi) && str_contains($locationSuggestionsApi, 'Location suggestions are temporarily unavailable'), 'Suggestion failures do not imply un-geocoded manual trip locations are valid');
$expect(is_string($locationAutocomplete) && str_contains($locationAutocomplete, 'location-suggestion-status'), 'Location search announces suggestion and configuration errors');
$expect(is_string($placesScript) && str_contains($placesScript, 'Place.searchNearby'), 'Nearby place search uses Google Places');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'SafariTrakRouteDirections.getDrivingRoute') && str_contains($journeyStartScript, 'routePreviewAvailable'), 'Trip planner requests a real road route and waits for its result');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, "routeColor: 'planned'"), 'Planned route preview is marked separately for red rendering');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'end_lat:'), 'Trip creation submits selected destination coordinates');
$expect(is_string($journeyStartScript) && str_contains($journeyStartScript, 'validPoint(startLatVal, startLngVal)') && str_contains($journeyStartScript, 'Choose both locations from the suggestions'), 'Trip submission requires valid coordinates from both selected map locations');
$expect(is_string($journeyStartApi) && str_contains($journeyStartApi, 'Choose both coordinates'), 'Journey API rejects partial coordinate pairs');
$expect(is_string($contactInviteApi) && str_contains($contactInviteApi, 'random_bytes(32)'), 'Trusted-contact invites use high-entropy random tokens');
$expect(is_string($registerApi) && str_contains($registerApi, 'contactInviteToken') && !str_contains($registerApi, 'WHERE invite_phone = ? AND contact_user_id IS NULL'), 'Phone matches alone no longer auto-link trusted contacts');
$expect(is_string($liveShareCreationApi) && str_contains($liveShareCreationApi, 'time() + 3600') && str_contains($liveShareCreationApi, '$departureTimestamp + 3600'), 'Public trip links expire one hour after creation or scheduled departure');
$expect(is_string($liveShareCreationApi) && str_contains($liveShareCreationApi, "includes/app-url.php") && is_string($appUrlHelper) && str_contains($appUrlHelper, 'function st_app_url()'), 'Live location links load the shared application URL helper');
$expect(is_string($liveShareApi) && str_contains($liveShareApi, 'j.status IN ("active", "planned")') && str_contains($liveShareApi, '$journey[\'status\'] === \'active\''), 'Public trip links expose active location or planned route data only');
$expect(is_string($inviteMigration) && str_contains($inviteMigration, 'trusted_contact_invites'), 'Trusted-contact invites have an additive migration');
$expect(is_string($liveShareMigration) && str_contains($liveShareMigration, 'journey_share_links'), 'One-hour live-share links have an additive migration');
$expect(is_string($trustedContactsScript) && str_contains($trustedContactsScript, 'shareLiveShareBtn'), 'Trusted contacts offers copy/share/revoke controls for live-location links');
$expect(is_file(__DIR__ . '/../assets/auth-map-landscape.svg') && is_file(__DIR__ . '/../assets/auth-map-portrait.svg'), 'Original landscape and portrait auth visuals are present');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'styles: mapStyles()') && str_contains($googleAppMapBackground, 'mapId ? { mapId }'), 'Shared Google map uses the Cloud Map ID when configured and falls back to local styles');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'billingWarning') && str_contains($googleAppMapBackground, 'Google Maps reports a configuration problem') && str_contains($googleAppMapBackground, 'browser-key restrictions'), 'Shared map backgrounds explain generic Google Maps configuration warnings');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'bounds.contains(position)'), 'Shared map recentres on the user marker when it is outside the visible map bounds');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "visibility: 'off'"), 'Shared Google map hides point-of-interest icons and labels');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, 'SafariTrakGoogleLocationMarker.create'), 'Shared Google map uses the pulsing HTML user-location marker');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "mapType === 'hybrid'"), 'Explore retains Satellite mode when saving its camera');
$expect(is_string($googleAppMapBackground) && str_contains($googleAppMapBackground, "mapType === 'hybrid'"), 'Shared map retains Satellite mode when saving its camera');
$expect(is_string($shellStart) && str_contains($shellStart, "if (!\$isExploreDashboard)") && str_contains($shellStart, 'app--map-layout'), 'Shared application shell uses the map layout outside Explore');
$mapBackedScreens = [
    'admin-admins.php',
    'admin-dashboard.php',
    'admin-organizations.php',
    'admin-users.php',
    'group-travel.php',
    'messages.php',
    'my-journeys.php',
    'notifications.php',
    'org-dashboard.php',
    'org-groups.php',
    'org-reports.php',
    'org-travelers.php',
    'places.php',
    'settings.php',
    'start-journey.php',
    'trusted-contacts.php',
    'view-journey.php',
    'watch-journey.php',
];
foreach ($mapBackedScreens as $screen) {
    $screenSource = file_get_contents(__DIR__ . '/../' . $screen);
    $expect(is_string($screenSource) && str_contains($screenSource, 'app-map-background.php') && str_contains($screenSource, 'app--map-layout'), "{$screen} uses the shared glass map layout");
}
$navigationScreens = [
    'admin-admins.php', 'admin-dashboard.php', 'admin-organizations.php', 'admin-users.php',
    'group-travel.php', 'messages.php', 'my-journeys.php',
    'notifications.php', 'org-dashboard.php', 'org-groups.php', 'org-reports.php',
    'org-travelers.php', 'places.php', 'settings.php', 'start-journey.php',
    'trusted-contacts.php', 'view-journey.php', 'watch-journey.php',
];
foreach ($navigationScreens as $screen) {
    $source = file_get_contents(__DIR__ . '/../' . $screen);
    $expect(is_string($source) && str_contains($source, 'app-navigation.php'), "{$screen} uses the shared Explore navigation");
}
$liveTrackingCompatibilityRoute = file_get_contents(__DIR__ . '/../live-tracking.php');
$expect(is_string($liveTrackingCompatibilityRoute) && str_contains($liveTrackingCompatibilityRoute, "header('Location: my-journeys.php?selected=") && str_contains($liveTrackingCompatibilityRoute, "header('Location: watch-journey.php?id=") && !str_contains($liveTrackingCompatibilityRoute, '<!doctype html>'), 'Live map is removed while owner and shared active-trip links keep safe destinations');
$groupsHub = file_get_contents(__DIR__ . '/../groups.php');
$groupTripsPage = file_get_contents(__DIR__ . '/../group-travel.php');
$expect(is_string($groupsHub) && str_contains($groupsHub, 'Groups sections') && str_contains($groupsHub, "require __DIR__ . '/group-travel.php'") && str_contains($groupsHub, 'href="groups.php?tab=trips"') && is_string($groupTripsPage) && str_contains($groupTripsPage, "header('Location: groups.php?tab=trips')"), 'Persistent groups and group trips share one Groups destination with tabbed views and a legacy redirect');
$demoCarHireSeeder = file_get_contents(__DIR__ . '/../backend/scripts/seed-demo-car-hire.php');
$expect(is_string($demoCarHireSeeder) && str_contains($demoCarHireSeeder, "PHP_SAPI !== 'cli'") && str_contains($demoCarHireSeeder, "random_bytes(16)") && str_contains($demoCarHireSeeder, 'Existing password is not changed or displayed'), 'Demo car-hire seeding is local CLI-only, additive, and does not reset existing passwords');
$expect(!is_file(__DIR__ . '/../safety.php') && !is_file(__DIR__ . '/../admin-safety.php'), 'standalone traveler and admin safety pages are removed');
foreach (['login.php', 'signup.php', 'forgot-password.php', 'reset-password.php', 'org-signup.php', 'verify-email.php', 'welcome.php'] as $authScreen) {
    $source = file_get_contents(__DIR__ . '/../' . $authScreen);
    $expect(is_string($source) && str_contains($source, 'auth-theme.css') && str_contains($source, 'auth-theme.js'), "{$authScreen} follows the application theme");
}
foreach (['login.php', 'signup.php', 'forgot-password.php', 'reset-password.php', 'welcome.php'] as $publicScreen) {
    $screenSource = file_get_contents(__DIR__ . '/../' . $publicScreen);
    $expect(is_string($screenSource) && !str_contains($screenSource, 'app-map-background.php'), "{$publicScreen} remains outside the authenticated map layout");
}
$expect(is_string($sharedLocationsApi) && str_contains($sharedLocationsApi, 'st_active_shared_journey_locations'), 'Explore uses the permission-filtered shared-location API');
$expect(is_string($dashboardScript) && str_contains($dashboardScript, 'more.dataset.mobileMore'), 'legacy app pages receive the responsive More navigation control');
$journeyFilters = file_get_contents(__DIR__ . '/../my-journeys.php');
$expect(is_string($journeyFilters) && str_contains($journeyFilters, 'journey-filter-bar'), 'My Journeys uses a compact labeled filter bar');
$expect(is_string($journeyFilters) && str_contains($journeyFilters, 'data-theme="dark"') && str_contains($journeyFilters, "localStorage.getItem('safaritrak-theme')") && str_contains($journeyFilters, 'data-theme-toggle'), 'My Journeys restores and switches the shared theme so its glass panels use the right theme tokens');
$expect(is_string($journeyFilters) && preg_match('/<\/div>\s*<\/div>\s*<aside class="journey-map-detail"/', $journeyFilters) === 1 && str_contains((string) $dashboardCss, '.app--journeys .journey-map-detail') && str_contains((string) $dashboardCss, 'pointer-events: auto;'), 'My Journeys selected-trip panel stays visible and interactive outside the mobile-hidden content area');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.journey-filter-options .tab'), 'My Journeys status filters use compact controls');
$exportApi = file_get_contents(__DIR__ . '/../backend/api/export-data.php');
$expect(is_string($exportApi) && str_contains($exportApi, 'safaritrak-account-export'), 'User data export API is implemented');
$expect(is_string($exportApi) && str_contains($exportApi, 'gm.last_lat, gm.last_lng'), 'Account export includes the user own group location');
$consentMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0004_privacy_consent_events.sql');
$expect(is_string($consentMigration) && str_contains($consentMigration, 'privacy_consents'), 'Versioned consent events have a schema migration');
$liveJourneyMap = file_get_contents(__DIR__ . '/../safaritrak-map.js');
$expect(is_string($liveJourneyMap) && !str_contains($liveJourneyMap, 'NavigationControl'), 'Live journey map has no zoom control');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'touchZoomRotate: false'), 'Live journey map disables zoom gestures');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'window.SafariTrakMapView?.read()'), 'Live journey map restores the shared camera');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'hasUserMovedMap') && str_contains($liveJourneyMap, 'state.hasFittedJourney = false'), 'Live journey prioritizes fitting its route over a stale saved camera until the user moves the map');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, "'line-color': '#C026D3'") && str_contains($liveJourneyMap, 'safaritrak-route-core') && str_contains($liveJourneyMap, 'directEstimate'), 'Live journey keeps a solid magenta route visible while road routing loads or fails');
$routeCoordinatesAssignment = strpos($liveJourneyMap, 'state.routeCoordinates = coordinates;');
$routeStyleReadyGuard = strpos($liveJourneyMap, 'if (!state.styleReady) return;');
$expect(is_string($liveJourneyMap) && $routeCoordinatesAssignment !== false && $routeStyleReadyGuard !== false && $routeCoordinatesAssignment < $routeStyleReadyGuard, 'Live journey retains route geometry until the map style can render it');
$expect(is_string($liveJourneyMap) && !str_contains($liveJourneyMap, 'animateRouteDash') && str_contains($liveJourneyMap, 'else scheduleRotation()'), 'Live map motion preference changes do not call the removed route animation');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'function startLocationWatch(followLocation = false)') && str_contains($liveJourneyMap, 'if (followLocation && state.map)'), 'GPS sharing does not pan away from the planned route unless the user resumes map following');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'html[data-theme] .app--map-layout.app--live-map-layout:not(.app--explore) > main > .content > .map-full') && str_contains($dashboardCss, 'height: 100dvh;'), 'Active live tracking map fills the viewport without its glass card constraining it');
$expect(is_string($liveJourneyMap) && str_contains($liveJourneyMap, 'zoom: Math.max(state.map.getZoom(), 16)'), 'Live journey map follows device location at neighborhood zoom');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, 'height: calc(100dvh - 222px - env(safe-area-inset-bottom))') && str_contains($dashboardCss, 'grid-template-rows: minmax(0, .7fr) minmax(0, 1.3fr)'), 'Groups panels fit above mobile navigation and remain internally scrollable');
$expect(is_string($mapViewState) && str_contains($mapViewState, 'scopedViewKey'), 'Map camera storage is isolated by signed-in user');
$expect(is_string($mapViewState) && str_contains($mapViewState, "typeof result === 'function'"), 'Map camera supports Google Maps coordinate accessors');
$sharedUiStatus = file_get_contents(__DIR__ . '/../dashboard.js');
$expect(is_string($sharedUiStatus) && str_contains($sharedUiStatus, 'window.SafariTrakUI.setStatus'), 'Shared app UI provides accessible status states');
$expect(is_string($sharedUiStatus) && str_contains($sharedUiStatus, 'aria-live'), 'Shared status states announce loading and errors to assistive technology');
$messagesPage = file_get_contents(__DIR__ . '/../messages.php');
$messagesScript = file_get_contents(__DIR__ . '/../messages.js');
$expect(is_string($messagesPage) && str_contains($messagesPage, 'role="log"') && str_contains($messagesPage, 'aria-label="Conversation messages"'), 'Messages provide a labeled live conversation log');
$expect(is_string($messagesPage) && str_contains($messagesPage, 'for="chatSearch"') && str_contains($messagesPage, 'aria-label="Send message"'), 'Message search and send controls have accessible labels');
$expect(is_string($messagesScript) && str_contains($messagesScript, "button.className = 'chat-item'") && str_contains($messagesScript, "button.type = 'button'"), 'Conversation selection uses keyboard-operable buttons');
$expect(is_string($messagesScript) && str_contains($messagesScript, 'button.dataset.name = conversation.full_name'), 'Conversation names are available to the search filter');
$expect(is_string($messagesScript) && str_contains($messagesScript, "setAttribute('aria-pressed'"), 'Selected conversations expose their pressed state');
$expect(is_string($messagesScript) && str_contains($messagesScript, 'No conversations match your search.'), 'Conversation filtering announces an empty search result');
$notificationsWidget = file_get_contents(__DIR__ . '/../notifications-widget.js');
$expect(is_string($notificationsWidget) && str_contains($notificationsWidget, 'You are offline'), 'Notification loading exposes a distinct offline state');
$expect(is_string($notificationsWidget) && str_contains($notificationsWidget, 'window.SafariTrakUI?.setStatus'), 'Notification loading uses shared status states');

$persistentGroupsMigration = file_get_contents(__DIR__ . '/../backend/sql/migrations/0007_accounts_groups_and_rentals.php');
$groupsPage = file_get_contents(__DIR__ . '/../groups.php');
$groupsNavigation = file_get_contents(__DIR__ . '/../backend/views/app-navigation.php');
$settingsSource = file_get_contents(__DIR__ . '/../settings.php');
$appShellStart = file_get_contents(__DIR__ . '/../backend/views/app-shell-start.php');
$groupClient = file_get_contents(__DIR__ . '/../groups.js');
$groupInvitePage = file_get_contents(__DIR__ . '/../group-invite.php');
$signupPage = file_get_contents(__DIR__ . '/../signup.php');
$authScript = file_get_contents(__DIR__ . '/../auth.js');
$verificationMailer = file_get_contents(__DIR__ . '/../backend/includes/mailer.php');
$verifyEmailPage = file_get_contents(__DIR__ . '/../verify-email.php');
$groupMessagesApi = file_get_contents(__DIR__ . '/../backend/api/groups/persistent/messages.php');
$groupTripApi = file_get_contents(__DIR__ . '/../backend/api/groups/create.php');
$groupTripClient = file_get_contents(__DIR__ . '/../group-travel.js');
$orgSignupApi = file_get_contents(__DIR__ . '/../backend/api/org/signup.php');
$orgReviewApi = file_get_contents(__DIR__ . '/../backend/api/platform/org-status.php');
$schoolInviteApi = file_get_contents(__DIR__ . '/../backend/api/org/invite.php');
$orgStatusRoute = file_get_contents(__DIR__ . '/../org-status.php');
$orgGuardRoute = file_get_contents(__DIR__ . '/../org-guard.php');
$sharedJourneyRoute = file_get_contents(__DIR__ . '/../shared-journey.php');
$testRoute = file_get_contents(__DIR__ . '/../test.php');
$dbRoute = file_get_contents(__DIR__ . '/../db.php');
$schoolTravelersPage = file_get_contents(__DIR__ . '/../org-travelers.php');
$schoolRequestApi = file_get_contents(__DIR__ . '/../backend/api/org/request-membership.php');
$schoolReviewApi = file_get_contents(__DIR__ . '/../backend/api/org/review-membership.php');
$schoolInvitePage = file_get_contents(__DIR__ . '/../organization-invite.php');
$rentalBookApi = file_get_contents(__DIR__ . '/../backend/api/rentals/book.php');
$rentalCatalogApi = file_get_contents(__DIR__ . '/../backend/api/rentals/catalog.php');
$fleetPage = file_get_contents(__DIR__ . '/../fleet.php');
$fleetScript = file_get_contents(__DIR__ . '/../fleet.js');
$rentalPage = file_get_contents(__DIR__ . '/../car-hire.php');
$expect(is_string($persistentGroupsMigration) && str_contains($persistentGroupsMigration, 'CREATE TABLE IF NOT EXISTS persistent_group_messages') && str_contains($persistentGroupsMigration, 'CREATE TABLE IF NOT EXISTS rental_bookings'), 'migration 0007 additively creates group chat and rental booking tables');
$expect(is_string($persistentGroupsMigration) && str_contains($persistentGroupsMigration, '"school", "approved"'), 'migration 0007 preserves existing organization access as approved school accounts');
$expect(is_string($groupsNavigation) && str_contains($groupsNavigation, 'href="groups.php"') && is_string($groupsPage), 'persistent groups have a real route in shared navigation');
$expect(is_string($settingsSource) && str_contains($settingsSource, 'settings-account-shortcut') && str_contains($settingsSource, 'trusted-contacts.php') && is_string($appShellStart) && !str_contains($appShellStart, 'href="trusted-contacts.php"'), 'Trusted people is grouped under account access instead of a separate profile-menu item');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '.dashboard-route-focus .app--explore .explore-panels') && str_contains($dashboardCss, '.dashboard-route-focus .app--explore .app-search-row'), 'Explore docks the route planner and hides competing panels while planning');
$expect(is_string($dashboardCss) && str_contains($dashboardCss, '--accent-pink: #ff4fa3') && str_contains($dashboardCss, '--accent-amber: #ffd166'), 'Map panels use additional coordinated accent colors');
$expect(is_string($groupsPage) && str_contains($groupsPage, 'persistent_group_messages') && str_contains($groupsPage, 'groupInviteForm'), 'groups page renders private chat and member invitation controls');
$expect(is_string($groupClient) && str_contains($groupClient, 'backend/api/groups/persistent/messages.php') && str_contains($groupClient, 'backend/api/groups/persistent/create.php'), 'persistent group UI is wired to the group APIs');
$expect(is_string($groupMessagesApi) && str_contains($groupMessagesApi, 'persistent_group_members') && str_contains($groupMessagesApi, 'organization_travelers'), 'group chat enforces membership and current school approval');
$expect(is_string($groupInvitePage) && str_contains($groupInvitePage, 'data-token') && str_contains($groupInvitePage, 'backend/api/groups/persistent/accept.php'), 'group invitations can be opened and accepted through a public link');
$expect(is_string($groupInvitePage) && str_contains($groupInvitePage, 'signup.php?group_invite=') && is_string($schoolInvitePage) && str_contains($schoolInvitePage, 'signup.php?org_invite='), 'group and school invitation pages preserve invite links during account creation');
$expect(is_string($signupPage) && str_contains($signupPage, 'groupInviteToken') && str_contains($signupPage, 'organizationInviteToken') && str_contains($signupPage, 'signupInviteError') && is_string($authScript) && str_contains($authScript, 'group_invite_token') && str_contains($authScript, 'org_invite_token') && str_contains($authScript, "group_invite_token: 'signupInviteError'"), 'individual signup submits invitation tokens and displays invitation errors');
$expect(is_string($registerApi) && str_contains($registerApi, 'group_invite_token') && str_contains($registerApi, 'org_invite_token') && is_string($verificationMailer) && str_contains($verificationMailer, 'group_invite') && str_contains($verificationMailer, 'org_invite'), 'email verification retains an allow-listed invitation continuation');
$expect(is_string($verificationMailer) && str_contains($verificationMailer, 'SMTP username and password are not configured') && str_contains($verificationMailer, 'ENCRYPTION_SMTPS'), 'mail delivery reports missing SMTP configuration and supports implicit TLS');
$rateLimitSource = file_get_contents(__DIR__ . '/../backend/includes/rate-limit.php');
$expect(is_string($rateLimitSource) && str_contains($rateLimitSource, 'st_local_development_rate_limit_maximum') && str_contains($rateLimitSource, "['127.0.0.1', '::1']"), 'higher signup limits are restricted to loopback development requests');
$expect(is_string($registerApi) && str_contains($registerApi, 'st_local_development_rate_limit_maximum(5)') && is_string($orgSignupApi) && str_contains($orgSignupApi, 'st_local_development_rate_limit_maximum(5)'), 'individual and organization registration use the loopback-only development limit');
$expect(is_string($registerApi) && str_contains($registerApi, 'Local development mode is enabled') && str_contains($registerApi, 'could not send the verification email') && is_string($orgSignupApi) && str_contains($orgSignupApi, 'could not send the verification email'), 'personal and organization signup report delivery failures and provide development verification links');
$resendVerificationApi = file_get_contents(__DIR__ . '/../backend/api/resend-verification.php');
$expect(is_string($resendVerificationApi) && str_contains($resendVerificationApi, 'dev_verification_link') && str_contains($resendVerificationApi, 'new verification link has been requested'), 'resend verification exposes the local link without revealing whether an account exists');
$expect(is_string($verifyEmailPage) && str_contains($verifyEmailPage, 'group-invite.php?token=') && str_contains($verifyEmailPage, 'organization-invite.php?token='), 'verified invitees return to the correct invitation without an open redirect');
$expect(is_string($groupTripClient) && str_contains($groupTripClient, 'backend/api/groups/create.php') && str_contains($groupTripClient, 'persistent_group_ids'), 'temporary group trips use the correct API path and can invite persistent-group members');
$expect(is_string($groupTripApi) && str_contains($groupTripApi, 'persistent_group_members') && str_contains($groupTripApi, 'One or more selected groups'), 'group-trip API verifies group membership before inviting members');
$expect(is_string($orgSignupApi) && str_contains($orgSignupApi, 'account_type') && str_contains($orgSignupApi, '"pending"'), 'new school and car-hire accounts wait for platform review');
$expect(is_string($orgReviewApi) && str_contains($orgReviewApi, "'approve', 'reject'") && str_contains($orgReviewApi, 'reviewed_by'), 'platform admins can approve or reject organization applications');
$expect(is_string($schoolInviteApi) && str_contains($schoolInviteApi, 'account_type') && str_contains($schoolInviteApi, 'organization_invites'), 'school admins can create expiring enrollment links');
$expect(is_string($orgStatusRoute) && str_contains($orgStatusRoute, "'/backend/config/database.php'") && str_contains($orgStatusRoute, "'/backend/includes/session.php'"), 'root organization-status endpoint resolves backend includes from its actual location');
$expect(is_string($orgGuardRoute) && str_contains($orgGuardRoute, "'/backend/includes/org-guard.php'") && str_contains($orgGuardRoute, "header('Location: org-dashboard.php')"), 'root organization-guard route delegates to the shared guard and returns to the organization page');
$expect(is_string($sharedJourneyRoute) && str_contains($sharedJourneyRoute, 'shared-location.php?') && str_contains($testRoute, "header('Location: welcome.php'") && str_contains($dbRoute, "header('Location: welcome.php'"), 'legacy empty and utility routes now redirect to supported user-facing pages');
$expect(is_string($schoolTravelersPage) && str_contains($schoolTravelersPage, 'Search school members by name...') && str_contains($schoolTravelersPage, 'Search travelers by name or phone...'), 'school roster search label matches its privacy-limited name-only data');
$expect(is_string($schoolRequestApi) && str_contains($schoolRequestApi, 'organization_membership_requests') && !str_contains($schoolRequestApi, 'INSERT INTO organization_travelers'), 'school invite acceptance creates a request rather than immediate traveler access');
$expect(is_string($schoolReviewApi) && str_contains($schoolReviewApi, 'status = "pending"') && str_contains($schoolReviewApi, 'INSERT INTO organization_travelers'), 'school traveler visibility begins only after admin approval');
$expect(is_string($schoolInvitePage) && str_contains($schoolInvitePage, 'request-membership.php'), 'school enrollment links provide a request-to-join flow');
$expect(is_string($rentalCatalogApi) && str_contains($rentalCatalogApi, 'review_status = "approved"') && is_string($rentalPage), 'car-hire catalog exposes only approved providers');
$expect(is_string($rentalBookApi) && str_contains($rentalBookApi, 'FOR UPDATE') && str_contains($rentalBookApi, 'status = "approved"') && str_contains($rentalBookApi, 'quoted_daily_rate'), 'rental booking requests validate vehicle dates and preserve quoted rates');
$expect(is_string($fleetPage) && str_contains($fleetPage, 'rental_vehicles') && is_string($fleetScript) && str_contains($fleetScript, 'fleet-action.php'), 'approved car-hire accounts can manage fleet and booking requests');
$exploreChatScript = file_get_contents(__DIR__ . '/../explore-chat.js');
$mapFallbackScript = file_get_contents(__DIR__ . '/../google-maps-fallback.js');
$expect(is_string($exploreChatScript) && str_contains($exploreChatScript, 'backend/api/groups/persistent/messages.php') && str_contains($exploreChatScript, 'textContent = body'), 'Explore group chat sends safely through the member-authorized chat API');
$expect(is_string($dashboard) && str_contains($dashboard, 'explore-chat-panel') && str_contains($dashboard, 'dashboardGroupMessages') && str_contains($dashboard, 'notifications.php'), 'Explore dashboard shows real group chat and location, trip, contact, and alert shortcuts');
$expect(is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, "saved?.projection === 'satellite' ? 'hybrid' : 'roadmap'") && is_string($dashboardCss) && str_contains($dashboardCss, '.explore-map-canvas') && str_contains($dashboardCss, 'border-radius: 0'), 'Explore preserves the full-width flat map presentation');
$expect(is_string($mapFallbackScript) && str_contains($mapFallbackScript, 'maplibregl') && str_contains($mapFallbackScript, 'backup map library could not be reached') && is_string($googleDashboardMapScript) && str_contains($googleDashboardMapScript, 'fallbackToMapLibre') && str_contains($googleDashboardMapScript, 'backup flat map'), 'Google Maps failures fall back to an interactive flat map with visible failure states');

echo "Completed {$checks} SafariTrak smoke checks.\n";
