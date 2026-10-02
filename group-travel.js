document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('groupMap');

    if (!mapElement) {
        console.error('groupMap element was not found.');
        return;
    }

    if (typeof L === 'undefined') {
        console.error('Leaflet was not loaded.');
        const status = document.getElementById('trackingStatus');

        if (status) {
            status.textContent = 'Map library failed to load.';
        }

        return;
    }

    function validNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const number = parseFloat(value);

        return Number.isFinite(number) ? number : null;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const journeyId = parseInt(
        document.getElementById('groupJourneyId')?.value ||
        window.groupJourneyId ||
        0,
        10
    );

    const currentUserId = parseInt(
        document.getElementById('currentUserId')?.value ||
        window.currentUserId ||
        0,
        10
    );

    const journey = window.groupJourneyData || {};

    const destinationLat = validNumber(journey.destination_lat);
    const destinationLng = validNumber(journey.destination_lng);

    const meetingLat = validNumber(journey.meeting_point_lat);
    const meetingLng = validNumber(journey.meeting_point_lng);

    const statusText = document.getElementById('trackingStatus');
    const connectionDot = document.getElementById('connectionDot');
    const memberList = document.getElementById('memberList');
    const destinationDistance = document.getElementById(
        'groupDestinationDistance'
    );

    let map = null;
    let destinationMarker = null;
    let meetingMarker = null;
    let myMarker = null;

    const memberMarkers = {};

    let distanceLine = null;
    let myPosition = null;
    let watchId = null;

    function setStatus(message, connected, error) {
        if (statusText) {
            statusText.textContent = message;
        }

        if (connectionDot) {
            connectionDot.classList.remove('connected');
            connectionDot.classList.remove('error');

            if (connected) {
                connectionDot.classList.add('connected');
            }

            if (error) {
                connectionDot.classList.add('error');
            }
        }
    }

    function createMap() {
        let initialLat = -1.286389;
        let initialLng = 36.817223;
        let initialZoom = 12;

        if (
            destinationLat !== null &&
            destinationLng !== null
        ) {
            initialLat = destinationLat;
            initialLng = destinationLng;
            initialZoom = 13;
        }

        map = L.map('groupMap', {
            zoomControl: true
        }).setView(
            [initialLat, initialLng],
            initialZoom
        );

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);

        setTimeout(function () {
            map.invalidateSize();
        }, 500);

        addDestinationMarker();
        addMeetingMarker();

        setStatus(
            'Map connected. Loading group members...',
            true,
            false
        );
    }

    function addDestinationMarker() {
        if (
            destinationLat === null ||
            destinationLng === null
        ) {
            return;
        }

        const icon = L.divIcon({
            className: '',
            html: `
                <div style="
                    width:42px;
                    height:42px;
                    border-radius:50%;
                    background:#c0392b;
                    border:4px solid white;
                    box-shadow:0 3px 10px rgba(0,0,0,.35);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:white;
                    font-size:18px;
                    font-weight:bold;
                ">⚑</div>
            `,
            iconSize: [42, 42],
            iconAnchor: [21, 21]
        });

        destinationMarker = L.marker(
            [destinationLat, destinationLng],
            {
                icon: icon
            }
        ).addTo(map);

        destinationMarker.bindPopup(
            '<strong>' +
            escapeHtml(
                journey.destination_label || 'Destination'
            ) +
            '</strong>'
        );
    }

    function addMeetingMarker() {
        if (
            meetingLat === null ||
            meetingLng === null
        ) {
            return;
        }

        const icon = L.divIcon({
            className: '',
            html: `
                <div style="
                    width:38px;
                    height:38px;
                    border-radius:50%;
                    background:#f59e0b;
                    border:4px solid white;
                    box-shadow:0 3px 10px rgba(0,0,0,.35);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:white;
                    font-size:18px;
                    font-weight:bold;
                ">⚑</div>
            `,
            iconSize: [38, 38],
            iconAnchor: [19, 19]
        });

        meetingMarker = L.marker(
            [meetingLat, meetingLng],
            {
                icon: icon
            }
        ).addTo(map);

        meetingMarker.bindPopup(
            '<strong>' +
            escapeHtml(
                journey.meeting_point_label || 'Meeting Point'
            ) +
            '</strong>'
        );
    }

    function createMemberIcon(name, isCurrentUser) {
        const initial =
            name && name.length
                ? name.charAt(0).toUpperCase()
                : '?';

        const background =
            isCurrentUser
                ? '#087f6b'
                : '#2563eb';

        return L.divIcon({
            className: '',
            html: `
                <div style="
                    width:40px;
                    height:40px;
                    border-radius:50%;
                    background:${background};
                    border:4px solid white;
                    box-shadow:0 3px 10px rgba(0,0,0,.35);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:white;
                    font-weight:bold;
                    font-size:15px;
                ">${escapeHtml(initial)}</div>
            `,
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });
    }

    function updateMemberMarker(member) {
        const lat = validNumber(member.last_lat);
        const lng = validNumber(member.last_lng);

        if (
            lat === null ||
            lng === null
        ) {
            return;
        }

        const userId = parseInt(
            member.user_id,
            10
        );

        if (!Number.isFinite(userId)) {
            return;
        }

        const name =
            member.full_name ||
            member.invite_name ||
            member.username ||
            'Member';

        const isCurrentUser =
            userId === currentUserId;

        if (memberMarkers[userId]) {
            memberMarkers[userId].setLatLng([
                lat,
                lng
            ]);
        } else {
            const marker = L.marker(
                [lat, lng],
                {
                    icon: createMemberIcon(
                        name,
                        isCurrentUser
                    )
                }
            ).addTo(map);

            marker.bindPopup(
                '<strong>' +
                escapeHtml(name) +
                '</strong><br>' +
                (
                    isCurrentUser
                        ? 'You'
                        : 'Group member'
                )
            );

            memberMarkers[userId] = marker;
        }

        if (isCurrentUser) {
            myPosition = {
                lat: lat,
                lng: lng
            };

            updateDistanceToDestination();
        }
    }

    function renderMembers(members) {
        if (!memberList) {
            return;
        }

        if (
            !Array.isArray(members) ||
            members.length === 0
        ) {
            memberList.innerHTML = `
                <div style="
                    color:#667085;
                    font-size:13px;
                    padding:10px 0;
                ">
                    No group members with locations yet.
                </div>
            `;

            return;
        }

        memberList.innerHTML = '';

        members.forEach(function (member) {
            const name =
                member.full_name ||
                member.invite_name ||
                member.username ||
                'Group Member';

            const lat = validNumber(member.last_lat);
            const lng = validNumber(member.last_lng);

            const hasLocation =
                lat !== null &&
                lng !== null;

            const userId = parseInt(
                member.user_id,
                10
            );

            const isCurrentUser =
                userId === currentUserId;

            const card =
                document.createElement('div');

            card.className = 'member-card';

            card.innerHTML = `
                <div class="member-top">

                    <div
                        class="member-icon"
                        style="
                            background:${
                                isCurrentUser
                                    ? '#087f6b'
                                    : '#2563eb'
                            };
                        "
                    >
                        ${escapeHtml(
                            name.charAt(0).toUpperCase()
                        )}
                    </div>

                    <div class="member-name">
                        ${escapeHtml(name)}
                    </div>

                    <div class="member-status">
                        ${
                            isCurrentUser
                                ? 'You'
                                : (
                                    member.status ||
                                    'Member'
                                )
                        }
                    </div>

                </div>

                <div class="member-details">

                    <div class="member-stat">
                        <span>Location</span>

                        <strong>
                            ${
                                hasLocation
                                    ? 'Available'
                                    : 'Not available'
                            }
                        </strong>
                    </div>

                    <div class="member-stat">
                        <span>Position</span>

                        <strong>
                            ${
                                hasLocation
                                    ? (
                                        lat.toFixed(5) +
                                        ', ' +
                                        lng.toFixed(5)
                                    )
                                    : '--'
                            }
                        </strong>
                    </div>

                </div>
            `;

            memberList.appendChild(card);

            updateMemberMarker(member);
        });
    }

    async function loadMembers() {
        if (!journeyId) {
            setStatus(
                'Group journey ID is missing.',
                false,
                true
            );

            return;
        }

        const endpoint =
            'api/groups/get_group_members.php' +
            '?group_journey_id=' +
            encodeURIComponent(journeyId);

        try {
            const response = await fetch(
                endpoint,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const responseText =
                await response.text();

            if (!response.ok) {
                throw new Error(
                    'HTTP ' +
                    response.status +
                    ': ' +
                    responseText.substring(0, 300)
                );
            }

            let data;

            try {
                data = JSON.parse(responseText);
            } catch (error) {
                throw new Error(
                    'Backend did not return JSON. ' +
                    responseText.substring(0, 300)
                );
            }

            if (data.success === false) {
                throw new Error(
                    data.message ||
                    'Unable to load group members.'
                );
            }

            let members = [];

            if (Array.isArray(data.members)) {
                members = data.members;
            } else if (Array.isArray(data.data)) {
                members = data.data;
            }

            renderMembers(members);

            setStatus(
                'Group tracking connected.',
                true,
                false
            );

        } catch (error) {
            console.error(
                'Group member loading error:',
                error
            );

            setStatus(
                'Unable to load group members.',
                false,
                true
            );

            if (memberList) {
                memberList.innerHTML = `
                    <div style="
                        color:#c0392b;
                        font-size:13px;
                        line-height:1.5;
                    ">
                        <strong>
                            Unable to load group members.
                        </strong>
                        <br>
                        ${escapeHtml(error.message)}
                    </div>
                `;
            }
        }
    }

    function calculateDistance(
        lat1,
        lng1,
        lat2,
        lng2
    ) {
        const earthRadius = 6371;

        const dLat =
            (lat2 - lat1) *
            Math.PI /
            180;

        const dLng =
            (lng2 - lng1) *
            Math.PI /
            180;

        const a =
            Math.sin(dLat / 2) *
            Math.sin(dLat / 2) +
            Math.cos(
                lat1 * Math.PI / 180
            ) *
            Math.cos(
                lat2 * Math.PI / 180
            ) *
            Math.sin(dLng / 2) *
            Math.sin(dLng / 2);

        const c =
            2 *
            Math.atan2(
                Math.sqrt(a),
                Math.sqrt(1 - a)
            );

        return earthRadius * c;
    }

    function updateDistanceToDestination() {
        if (
            !myPosition ||
            destinationLat === null ||
            destinationLng === null
        ) {
            return;
        }

        const distance =
            calculateDistance(
                myPosition.lat,
                myPosition.lng,
                destinationLat,
                destinationLng
            );

        if (destinationDistance) {
            if (distance < 1) {
                destinationDistance.textContent =
                    Math.round(
                        distance * 1000
                    ) + ' m';
            } else {
                destinationDistance.textContent =
                    distance.toFixed(2) +
                    ' km';
            }
        }

        drawDistanceLine();
    }

    function drawDistanceLine() {
        if (
            !myPosition ||
            destinationLat === null ||
            destinationLng === null
        ) {
            return;
        }

        if (distanceLine) {
            map.removeLayer(distanceLine);
        }

        distanceLine =
            L.polyline(
                [
                    [
                        myPosition.lat,
                        myPosition.lng
                    ],
                    [
                        destinationLat,
                        destinationLng
                    ]
                ],
                {
                    color: '#2563eb',
                    weight: 3,
                    dashArray: '8,10',
                    opacity: 0.8
                }
            ).addTo(map);
    }

    function createMyLocationMarker(
        lat,
        lng,
        placeName
    ) {
        const icon = L.divIcon({
            className: '',
            html: `
                <div style="
                    width:42px;
                    height:42px;
                    border-radius:50%;
                    background:#087f6b;
                    border:4px solid white;
                    box-shadow:0 3px 10px rgba(0,0,0,.35);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:white;
                    font-size:18px;
                    font-weight:bold;
                ">●</div>
            `,
            iconSize: [42, 42],
            iconAnchor: [21, 21]
        });

        if (myMarker) {
            myMarker.setLatLng([
                lat,
                lng
            ]);
        } else {
            myMarker =
                L.marker(
                    [lat, lng],
                    {
                        icon: icon
                    }
                ).addTo(map);
        }

        myMarker.bindPopup(
            '<strong>Your Current Location</strong><br>' +
            escapeHtml(
                placeName ||
                'Current location'
            )
        );
    }

    async function reverseGeocode(
        lat,
        lng
    ) {
        const url =
            'https://nominatim.openstreetmap.org/reverse' +
            '?format=jsonv2' +
            '&lat=' +
            encodeURIComponent(lat) +
            '&lon=' +
            encodeURIComponent(lng) +
            '&zoom=18' +
            '&addressdetails=1';

        try {
            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        headers: {
                            'Accept':
                                'application/json'
                        }
                    }
                );

            if (!response.ok) {
                throw new Error(
                    'Reverse geocoding failed.'
                );
            }

            const data =
                await response.json();

            if (
                data &&
                data.display_name
            ) {
                return data.display_name;
            }

            return 'Current location';

        } catch (error) {
            console.warn(
                'Reverse geocoding error:',
                error
            );

            return 'Current location';
        }
    }

    async function useCurrentLocation() {
        if (!navigator.geolocation) {
            setStatus(
                'Geolocation is not supported by this browser.',
                false,
                true
            );

            return;
        }

        setStatus(
            'Detecting your current location...',
            true,
            false
        );

        navigator.geolocation.getCurrentPosition(
            async function (position) {
                const lat =
                    position.coords.latitude;

                const lng =
                    position.coords.longitude;

                myPosition = {
                    lat: lat,
                    lng: lng
                };

                createMyLocationMarker(
                    lat,
                    lng,
                    'Detecting area...'
                );

                map.setView(
                    [lat, lng],
                    16
                );

                updateDistanceToDestination();

                const placeName =
                    await reverseGeocode(
                        lat,
                        lng
                    );

                createMyLocationMarker(
                    lat,
                    lng,
                    placeName
                );

                updateManualLocationField(
                    placeName
                );

                setStatus(
                    'Current location detected.',
                    true,
                    false
                );

                await saveCurrentLocation(
                    lat,
                    lng
                );
            },
            function (error) {
                console.error(
                    'Geolocation error:',
                    error
                );

                let message =
                    'Unable to detect your location.';

                if (error.code === 1) {
                    message =
                        'Location permission was denied. Allow location access in your browser.';
                }

                if (error.code === 2) {
                    message =
                        'Your location could not be determined.';
                }

                if (error.code === 3) {
                    message =
                        'Location detection timed out.';
                }

                setStatus(
                    message,
                    false,
                    true
                );
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            }
        );
    }

    function startWatchingLocation() {
        if (
            !navigator.geolocation ||
            watchId !== null
        ) {
            return;
        }

        watchId =
            navigator.geolocation.watchPosition(
                function (position) {
                    const lat =
                        position.coords.latitude;

                    const lng =
                        position.coords.longitude;

                    myPosition = {
                        lat: lat,
                        lng: lng
                    };

                    if (myMarker) {
                        myMarker.setLatLng([
                            lat,
                            lng
                        ]);
                    } else {
                        createMyLocationMarker(
                            lat,
                            lng,
                            'Current location'
                        );
                    }

                    updateDistanceToDestination();

                    saveCurrentLocation(
                        lat,
                        lng
                    );
                },
                function (error) {
                    console.warn(
                        'Location watch error:',
                        error
                    );
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 5000,
                    timeout: 15000
                }
            );
    }

    async function saveCurrentLocation(
        lat,
        lng
    ) {
        if (!journeyId) {
            return;
        }

        const endpoint =
            'api/groups/update_group_location.php';

        try {
            const response =
                await fetch(
                    endpoint,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type':
                                'application/json',
                            'Accept':
                                'application/json'
                        },
                        body: JSON.stringify({
                            group_journey_id:
                                journeyId,
                            lat: lat,
                            lng: lng
                        })
                    }
                );

            if (!response.ok) {
                console.warn(
                    'Location update failed:',
                    response.status
                );
            }

        } catch (error) {
            console.warn(
                'Could not save group location:',
                error
            );
        }
    }

    function getLocationInput() {
        const fields = [
            'startPoint',
            'start_point',
            'currentLocation',
            'current_location',
            'myLocation',
            'my_location'
        ];

        for (
            let i = 0;
            i < fields.length;
            i++
        ) {
            const element =
                document.getElementById(
                    fields[i]
                );

            if (
                element &&
                (
                    element.tagName === 'INPUT' ||
                    element.tagName === 'TEXTAREA'
                )
            ) {
                return element;
            }
        }

        return null;
    }

    function updateManualLocationField(
        placeName
    ) {
        const input =
            getLocationInput();

        if (!input) {
            return;
        }

        input.value = placeName;
    }

    async function searchManualLocation() {
        const input =
            getLocationInput();

        if (!input) {
            alert(
                'Location input field was not found.'
            );

            return;
        }

        const query =
            input.value.trim();

        if (!query) {
            alert(
                'Please enter a location first.'
            );

            return;
        }

        setStatus(
            'Searching for ' + query + '...',
            true,
            false
        );

        const url =
            'https://nominatim.openstreetmap.org/search' +
            '?format=jsonv2' +
            '&q=' +
            encodeURIComponent(query) +
            '&limit=1' +
            '&addressdetails=1';

        try {
            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        headers: {
                            'Accept':
                                'application/json'
                        }
                    }
                );

            if (!response.ok) {
                throw new Error(
                    'Location search failed.'
                );
            }

            const results =
                await response.json();

            if (
                !Array.isArray(results) ||
                results.length === 0
            ) {
                throw new Error(
                    'Location was not found.'
                );
            }

            const result =
                results[0];

            const lat =
                parseFloat(result.lat);

            const lng =
                parseFloat(result.lon);

            if (
                !Number.isFinite(lat) ||
                !Number.isFinite(lng)
            ) {
                throw new Error(
                    'Invalid location coordinates.'
                );
            }

            myPosition = {
                lat: lat,
                lng: lng
            };

            createMyLocationMarker(
                lat,
                lng,
                result.display_name
            );

            map.setView(
                [lat, lng],
                16
            );

            updateDistanceToDestination();

            setStatus(
                'Location found.',
                true,
                false
            );

            await saveCurrentLocation(
                lat,
                lng
            );

        } catch (error) {
            console.error(
                'Manual location search error:',
                error
            );

            setStatus(
                'Location could not be found.',
                false,
                true
            );

            alert(
                error.message ||
                'Location could not be found.'
            );
        }
    }

    function fitGroup() {
        const points = [];

        if (
            destinationLat !== null &&
            destinationLng !== null
        ) {
            points.push([
                destinationLat,
                destinationLng
            ]);
        }

        if (
            meetingLat !== null &&
            meetingLng !== null
        ) {
            points.push([
                meetingLat,
                meetingLng
            ]);
        }

        if (myPosition) {
            points.push([
                myPosition.lat,
                myPosition.lng
            ]);
        }

        Object.keys(memberMarkers)
            .forEach(function (id) {
                const marker =
                    memberMarkers[id];

                if (marker) {
                    const position =
                        marker.getLatLng();

                    points.push([
                        position.lat,
                        position.lng
                    ]);
                }
            });

        if (points.length === 0) {
            return;
        }

        if (points.length === 1) {
            map.setView(
                points[0],
                14
            );

            return;
        }

        const bounds =
            L.latLngBounds(points);

        map.fitBounds(
            bounds,
            {
                padding: [40, 40]
            }
        );
    }

    const fitButton =
        document.getElementById(
            'fitGroupBtn'
        );

    if (fitButton) {
        fitButton.addEventListener(
            'click',
            function () {
                fitGroup();
            }
        );
    }

    const locationButton =
        document.getElementById(
            'myLocationBtn'
        );

    if (locationButton) {
        locationButton.addEventListener(
            'click',
            function (event) {
                event.preventDefault();
                useCurrentLocation();
            }
        );
    }

    const searchButtons = [
        'searchLocationBtn',
        'searchStartPointBtn',
        'findLocationBtn',
        'findStartLocationBtn'
    ];

    searchButtons.forEach(
        function (buttonId) {
            const button =
                document.getElementById(
                    buttonId
                );

            if (button) {
                button.addEventListener(
                    'click',
                    function (event) {
                        event.preventDefault();
                        searchManualLocation();
                    }
                );
            }
        }
    );

    const locationInput =
        getLocationInput();

    if (locationInput) {
        locationInput.addEventListener(
            'keydown',
            function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchManualLocation();
                }
            }
        );
    }

    createMap();

    loadMembers();

    startWatchingLocation();

    setInterval(
        loadMembers,
        5000
    );

    window.addEventListener(
        'resize',
        function () {
            if (map) {
                map.invalidateSize();
            }
        }
    );
});