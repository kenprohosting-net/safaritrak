<?php
require __DIR__ . '/backend/includes/auth-guard.php';
$showAppMapBackground = true;
$appMapPage = 'places';
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#060b16">
<title>Places nearby | SafariTrak</title>
<link rel="stylesheet" href="dashboard.css?v=<?= hash_file('sha256', __DIR__ . '/dashboard.css') ?>">
<link rel="stylesheet" href="places-workspace.css?v=<?= hash_file('sha256', __DIR__ . '/places-workspace.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/backend/views/app-map-background.php'; ?>
<div class="app app--map-layout app--workspace app--places">
  <?php require __DIR__ . '/backend/views/app-navigation.php'; ?>

  <main>
    <div class="content places-workspace" aria-label="Places workspace">
      <section class="places-panel places-nearby-panel" aria-labelledby="placesTitle">
        <header class="places-panel-header">
          <div>
            <label>USEFUL STOPS</label>
            <h1 id="placesTitle">Places nearby</h1>
          </div>
          <div class="places-header-actions">
            <button class="places-add-custom-button" id="addPlaceButton" type="button" data-open-modal="addPlaceModal">
              <i class="fa-solid fa-plus" aria-hidden="true"></i><span>Add a place</span>
            </button>
            <button class="places-mobile-saved-toggle" id="showSavedPlaces" type="button" aria-controls="savedPlacesPanel">
            <i class="fa-solid fa-bookmark" aria-hidden="true"></i><span>Saved</span>
            </button>
          </div>
        </header>

        <form class="places-search" id="placesSearchForm">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <label class="visually-hidden" for="placeSearch">Search places or a town</label>
          <input id="placeSearch" type="search" data-location-autocomplete autocomplete="off" placeholder="Search places or a town, e.g. Thika">
          <button id="placeSearchBtn" type="submit">Search</button>
        </form>

        <div class="places-mobile-tabs" role="tablist" aria-label="Places panels">
          <button type="button" role="tab" id="showNearbyPlaces" aria-selected="true" aria-controls="placesResultsPanel">Nearby</button>
          <button type="button" role="tab" id="showSavedPlacesTab" aria-selected="false" aria-controls="savedPlacesPanel">Saved locations</button>
        </div>

        <div class="places-category-grid" id="placeTabs" aria-label="Filter nearby places">
          <button type="button" class="places-category is-active" data-category="all" aria-pressed="true"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>All</span></button>
          <button type="button" class="places-category" data-category="hospital" aria-pressed="false"><i class="fa-solid fa-kit-medical" aria-hidden="true"></i><span>Hospitals</span></button>
          <button type="button" class="places-category" data-category="police" aria-pressed="false"><i class="fa-solid fa-building-shield" aria-hidden="true"></i><span>Police</span></button>
          <button type="button" class="places-category" data-category="fuel" aria-pressed="false"><i class="fa-solid fa-gas-pump" aria-hidden="true"></i><span>Fuel</span></button>
          <button type="button" class="places-category" data-category="hotel" aria-pressed="false"><i class="fa-solid fa-bed" aria-hidden="true"></i><span>Hotels</span></button>
          <button type="button" class="places-category" data-category="restaurant" aria-pressed="false"><i class="fa-solid fa-utensils" aria-hidden="true"></i><span>Restaurants</span></button>
          <button type="button" class="places-category" data-category="car_wash" aria-pressed="false"><i class="fa-solid fa-car-side" aria-hidden="true"></i><span>Car washes</span></button>
          <button type="button" class="places-category" data-category="pharmacy" aria-pressed="false"><i class="fa-solid fa-prescription-bottle-medical" aria-hidden="true"></i><span>Pharmacies</span></button>
          <button type="button" class="places-category" data-category="school" aria-pressed="false"><i class="fa-solid fa-school" aria-hidden="true"></i><span>Schools</span></button>
        </div>

        <section class="places-results-section" id="placesResultsPanel" aria-label="Nearby stops">
          <div class="places-results-heading">
            <h2 id="resultsCount">Finding places near you…</h2>
          </div>
          <div class="places-results-grid" id="placesList" aria-live="polite"></div>
          <div class="places-status" id="placesStatus" role="status" aria-live="polite">Getting your location…</div>
        </section>
      </section>

      <aside class="places-panel places-saved-panel" id="savedPlacesPanel" aria-labelledby="savedPlacesTitle">
        <header class="places-panel-header">
          <div>
            <label>YOUR SAVED LOCATIONS</label>
            <h2 id="savedPlacesTitle">Places to use in trip planning</h2>
          </div>
          <button class="places-mobile-close" id="hideSavedPlaces" type="button" aria-label="Show nearby places">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </header>
        <div class="places-saved-list" id="savedPlacesList" aria-live="polite">
          <p class="places-empty">Loading saved locations…</p>
        </div>
        <p class="places-save-feedback" id="placeSaveStatus" role="status" aria-live="polite"></p>
      </aside>
    </div>
  </main>
</div>

<div class="modal-overlay" id="addPlaceModal">
  <div class="modal custom-place-modal" role="dialog" aria-modal="true" aria-labelledby="addPlaceTitle">
    <div class="modal-head">
      <div>
        <h3 id="addPlaceTitle">Add a place</h3>
        <p>Save a useful location for future trip planning.</p>
      </div>
      <button class="modal-close" type="button" data-close-modal aria-label="Close add place dialog"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="modal-body">
      <section id="customPlaceTypeStep" class="custom-place-step" aria-labelledby="customPlaceTypeLabel">
        <label id="customPlaceTypeLabel" for="customPlaceType">What type of place are you adding?</label>
        <select id="customPlaceType" required>
          <option value="">Choose a place type</option>
          <option value="home">Home</option>
          <option value="work">Work</option>
          <option value="hospital">Hospital</option>
          <option value="police">Police station</option>
          <option value="fuel">Fuel station</option>
          <option value="hotel">Hotel</option>
          <option value="restaurant">Restaurant</option>
          <option value="car_wash">Car wash</option>
          <option value="pharmacy">Pharmacy</option>
          <option value="school">School</option>
          <option value="other">Other</option>
        </select>
        <p id="customPlaceTypeStatus" class="custom-place-status" role="status" aria-live="polite"></p>
      </section>
      <section id="customPlaceLocationStep" class="custom-place-step" aria-labelledby="customPlaceLocationLabel" hidden>
        <label id="customPlaceLocationLabel" for="customPlaceSearch">Search for a location</label>
        <div class="custom-place-search-row">
          <input id="customPlaceSearch" type="search" data-location-autocomplete autocomplete="off" placeholder="Search a place or address">
          <button id="pickCustomPlaceOnMap" class="places-secondary-button" type="button" aria-pressed="false">
            <i class="fa-solid fa-map-pin" aria-hidden="true"></i><span>Pick on map</span>
          </button>
        </div>
        <p id="customPlaceSelectionStatus" class="custom-place-status" role="status" aria-live="polite">Choose a search result or pick a point on the map.</p>
        <label for="customPlaceName">Name this saved location</label>
        <input id="customPlaceName" type="text" maxlength="160" autocomplete="off" placeholder="Place name">
        <p id="customPlaceFormStatus" class="custom-place-status" role="status" aria-live="polite"></p>
      </section>
    </div>
    <div class="modal-actions">
      <button id="backCustomPlaceStep" type="button" class="ghost" hidden>Back</button>
      <button type="button" class="ghost" data-close-modal>Cancel</button>
      <button id="continueCustomPlace" type="button" class="primary">Continue</button>
      <button id="saveCustomPlaceButton" type="button" class="primary" hidden disabled>Save place</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="placeDetailModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="placeDetailTitle">
    <div class="modal-head">
      <div>
        <h3 id="placeDetailTitle">Place name</h3>
        <p id="placeDetailSubtitle">Category · distance</p>
      </div>
      <button class="modal-close" type="button" data-close-modal aria-label="Close place details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="modal-body">
      <p id="placeDetailAddress"></p>
      <p id="placeDetailHours" style="margin-top:8px"></p>
      <label for="placeSaveCategory" style="display:block;margin-top:16px">Save this location as</label>
      <select id="placeSaveCategory" style="width:100%;margin-top:6px">
        <option value="other">Other saved place</option>
        <option value="home">Home</option>
        <option value="work">Work</option>
        <option value="hospital">Hospital</option>
        <option value="police">Police station</option>
        <option value="fuel">Fuel station</option>
        <option value="hotel">Hotel</option>
        <option value="restaurant">Restaurant</option>
        <option value="car_wash">Car wash</option>
        <option value="pharmacy">Pharmacy</option>
        <option value="school">School</option>
      </select>
      <label for="placeSaveNote" style="display:block;margin-top:16px">Note (optional)</label>
      <textarea id="placeSaveNote" maxlength="500" rows="3" placeholder="Add a helpful detail for trip planning" style="width:100%;margin-top:6px;resize:vertical"></textarea>
      <p id="placeSaveModalStatus" role="status" aria-live="polite"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <button id="savePlaceButton" class="primary" type="button">Save location</button>
      <a id="placeDetailDirections" class="primary" target="_blank" rel="noopener" style="text-decoration:none;text-align:center">Get directions</a>
    </div>
  </div>
</div>

<script src="dashboard.js?v=<?= hash_file('sha256', __DIR__ . '/dashboard.js') ?>"></script>
<script src="location-autocomplete.js?v=<?= hash_file('sha256', __DIR__ . '/location-autocomplete.js') ?>"></script>
<script src="places.js?v=<?= hash_file('sha256', __DIR__ . '/places.js') ?>"></script>
</body>
</html>
