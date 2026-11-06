(() => {
  function create(map, point, label = 'Your location') {
    const position = new google.maps.LatLng(Number(point.lat), Number(point.lng));

    class PulsingLocationOverlay extends google.maps.OverlayView {
      constructor() {
        super();
        this.position = position;
        this.label = label;
        this.element = null;
        this.setMap(map);
      }

      onAdd() {
        const marker = document.createElement('div');
        marker.className = 'google-user-location-marker';
        marker.setAttribute('role', 'img');
        marker.setAttribute('aria-label', this.label);
        marker.title = this.label;

        const pulse = document.createElement('span');
        pulse.className = 'google-user-location-marker__pulse';
        const center = document.createElement('span');
        center.className = 'google-user-location-marker__center';
        const text = document.createElement('span');
        text.className = 'google-user-location-marker__label';
        text.textContent = this.label;
        marker.append(pulse, center, text);
        this.element = marker;
        this.getPanes().overlayMouseTarget.append(marker);
      }

      draw() {
        if (!this.element) return;
        const pixel = this.getProjection().fromLatLngToDivPixel(this.position);
        if (!pixel) return;
        this.element.style.left = `${pixel.x}px`;
        this.element.style.top = `${pixel.y}px`;
      }

      onRemove() {
        this.element?.remove();
        this.element = null;
      }

      setPosition(nextPosition) {
        this.position = nextPosition instanceof google.maps.LatLng
          ? nextPosition
          : new google.maps.LatLng(Number(nextPosition.lat), Number(nextPosition.lng));
        this.draw();
      }

      getPosition() {
        return this.position;
      }

      setTitle(nextLabel) {
        this.label = String(nextLabel);
        if (!this.element) return;
        this.element.title = this.label;
        this.element.setAttribute('aria-label', this.label);
        const text = this.element.querySelector('.google-user-location-marker__label');
        if (text) text.textContent = this.label;
      }
    }

    return new PulsingLocationOverlay();
  }

  window.SafariTrakGoogleLocationMarker = { create };
})();
