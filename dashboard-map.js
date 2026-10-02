document.addEventListener('DOMContentLoaded', function () {

    const mapElement = document.getElementById('map');

    if (!mapElement) {
        return;
    }

    let initialLat = -1.286389;
    let initialLng = 36.817223;

    if (
        window.SAFARITRAK_JOURNEY &&
        window.SAFARITRAK_JOURNEY.start_lat &&
        window.SAFARITRAK_JOURNEY.start_lng
    ) {
        initialLat =
            parseFloat(window.SAFARITRAK_JOURNEY.start_lat);

        initialLng =
            parseFloat(window.SAFARITRAK_JOURNEY.start_lng);
    }

    window.safariMap =
        L.map('map', {
            zoomControl: false,
            attributionControl: true
        }).setView(
            [initialLat, initialLng],
            14
        );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(window.safariMap);

    L.control.zoom({
        position: 'topright'
    }).addTo(window.safariMap);

    window.startMarker = null;
    window.currentMarker = null;
    window.destinationMarker = null;
    window.routeLine = null;

    const currentIcon =
        L.divIcon({
            className: 'safaritrak-current-icon',
            html:
                '<div style="' +
                'width:20px;' +
                'height:20px;' +
                'border-radius:50%;' +
                'background:#10b981;' +
                'border:4px solid white;' +
                'box-shadow:0 2px 8px rgba(0,0,0,.35);' +
                '"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

    const startIcon =
        L.divIcon({
            className: 'safaritrak-start-icon',
            html:
                '<div style="' +
                'width:30px;' +
                'height:30px;' +
                'border-radius:50%;' +
                'background:#147968;' +
                'color:white;' +
                'display:flex;' +
                'align-items:center;' +
                'justify-content:center;' +
                'font-size:14px;' +
                'box-shadow:0 2px 8px rgba(0,0,0,.3);' +
                '">' +
                '<i class="fas fa-play"></i>' +
                '</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

    const destinationIcon =
        L.divIcon({
            className: 'safaritrak-destination-icon',
            html:
                '<div style="' +
                'width:32px;' +
                'height:32px;' +
                'border-radius:50%;' +
                'background:#e5a82c;' +
                'color:white;' +
                'display:flex;' +
                'align-items:center;' +
                'justify-content:center;' +
                'font-size:15px;' +
                'box-shadow:0 2px 8px rgba(0,0,0,.3);' +
                '">' +
                '<i class="fas fa-flag-checkered"></i>' +
                '</div>',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

    window.SafariMapIcons = {
        current: currentIcon,
        start: startIcon,
        destination: destinationIcon
    };

    window.setStartMarker =
        function (lat, lng, label) {

            if (window.startMarker) {
                window.startMarker.remove();
            }

            window.startMarker =
                L.marker(
                    [lat, lng],
                    {
                        icon: startIcon
                    }
                )
                .addTo(window.safariMap)
                .bindPopup(
                    '<strong>Starting point</strong><br>' +
                    (label || 'Journey start')
                );
        };

    window.setCurrentMarker =
        function (lat, lng, accuracy) {

            if (!window.currentMarker) {

                window.currentMarker =
                    L.marker(
                        [lat, lng],
                        {
                            icon: currentIcon
                        }
                    ).addTo(window.safariMap);

            } else {

                window.currentMarker.setLatLng(
                    [lat, lng]
                );
            }

            window.currentMarker.bindPopup(
                '<strong>Current location</strong><br>' +
                'Accuracy: approximately ' +
                Math.round(accuracy || 0) +
                ' metres'
            );
        };

    window.setDestinationMarker =
        function (lat, lng, label) {

            if (window.destinationMarker) {
                window.destinationMarker.remove();
            }

            window.destinationMarker =
                L.marker(
                    [lat, lng],
                    {
                        icon: destinationIcon
                    }
                )
                .addTo(window.safariMap)
                .bindPopup(
                    '<strong>Destination</strong><br>' +
                    (label || 'Destination')
                );
        };

    window.drawRoute =
        function (coordinates) {

            if (
                !coordinates ||
                !coordinates.length
            ) {
                return;
            }

            if (window.routeLine) {
                window.routeLine.remove();
            }

            window.routeLine =
                L.polyline(
                    coordinates,
                    {
                        color: '#147968',
                        weight: 5,
                        opacity: 0.8
                    }
                ).addTo(window.safariMap);
        };

    window.fitJourney =
        function () {

            const layers = [];

            if (window.startMarker) {
                layers.push(
                    window.startMarker
                );
            }

            if (window.currentMarker) {
                layers.push(
                    window.currentMarker
                );
            }

            if (window.destinationMarker) {
                layers.push(
                    window.destinationMarker
                );
            }

            if (window.routeLine) {
                layers.push(
                    window.routeLine
                );
            }

            if (!layers.length) {
                return;
            }

            const group =
                L.featureGroup(layers);

            window.safariMap.fitBounds(
                group.getBounds().pad(0.15)
            );
        };

    if (window.SAFARITRAK_JOURNEY) {

        const journey =
            window.SAFARITRAK_JOURNEY;

        if (
            journey.start_lat &&
            journey.start_lng
        ) {

            window.setStartMarker(
                parseFloat(journey.start_lat),
                parseFloat(journey.start_lng),
                journey.start_label
            );
        }

        if (
            journey.end_lat &&
            journey.end_lng
        ) {

            window.setDestinationMarker(
                parseFloat(journey.end_lat),
                parseFloat(journey.end_lng),
                journey.end_label
            );
        }

        if (
            journey.current_lat &&
            journey.current_lng
        ) {

            window.setCurrentMarker(
                parseFloat(journey.current_lat),
                parseFloat(journey.current_lng),
                0
            );
        }

        setTimeout(function () {

            window.safariMap.invalidateSize();

            window.fitJourney();

        }, 300);
    }

});

    function locateAndCenter(button, opts = {}) {
        if (!button) return;

        if (!navigator.geolocation) {
            button.dataset.originalText = button.dataset.originalText || button.textContent;
            button.textContent = 'Not supported';
            setTimeout(() => { button.textContent = button.dataset.originalText; }, 2500);
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Locating…';

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                window.setCurrentMarker(lat, lng, pos.coords.accuracy);
                window.safariMap.setView([lat, lng], 15);

                if (opts.fillDestinationInput) {
                    const destInput = document.getElementById('destination');
                    if (destInput) {
                        destInput.value = 'My location (' + lat.toFixed(4) + ', ' + lng.toFixed(4) + ')';
                        destInput.dataset.lat = lat;
                        destInput.dataset.lng = lng;
                    }
                }

                button.disabled = false;
                button.textContent = 'Located';
                setTimeout(() => { button.textContent = originalText; }, 1800);
            },
            function (err) {
                button.disabled = false;
                button.textContent =
                    err.code === err.PERMISSION_DENIED ? 'Permission denied' : 'Could not locate';
                setTimeout(() => { button.textContent = originalText; }, 2500);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    }

    document.getElementById('locate')?.addEventListener('click', function () {
        locateAndCenter(this, { fillDestinationInput: true });
    });

    document.getElementById('myLocation')?.addEventListener('click', function () {
        locateAndCenter(this);
    });



    document.querySelectorAll('[data-shortcut]').forEach(function (btn) {
        const key = 'safaritrak_shortcut_' + btn.dataset.shortcut;

        btn.addEventListener('click', function () {
            const saved = localStorage.getItem(key);

            if (saved) {
                const { lat, lng } = JSON.parse(saved);
                window.setCurrentMarker(lat, lng, 0);
                window.safariMap.setView([lat, lng], 15);
                return;
            }

            if (!navigator.geolocation) return;

            const originalText = btn.textContent;
            btn.textContent = 'Saving…';

            navigator.geolocation.getCurrentPosition(function (pos) {
                localStorage.setItem(key, JSON.stringify({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude
                }));
                window.setCurrentMarker(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                window.safariMap.setView([pos.coords.latitude, pos.coords.longitude], 15);
                btn.textContent = originalText;
            }, function () {
                btn.textContent = originalText;
            });
        });
    });