(() => {
  const styleUrl = 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';
  const leafletBaseMaps = new WeakMap();

  function getRouteColors() {
    const styles = getComputedStyle(document.documentElement);
    return {
      stroke: styles.getPropertyValue('--route-stroke').trim() || '#FF2D2D',
      glow: styles.getPropertyValue('--route-glow').trim() || 'rgba(255, 45, 45, 0.55)',
    };
  }

  function apply(map) {
    if (!map?.getStyle()?.layers) return;

    const roadLabelMinZooms = {
      roadname_major: 8,
      roadname_pri: 9,
      roadname_sec: 10,
      roadname_minor: 11,
    };

    for (const layer of map.getStyle().layers) {
      if (Object.hasOwn(roadLabelMinZooms, layer.id)) {
        map.setLayerZoomRange(layer.id, roadLabelMinZooms[layer.id], 24);
      }
      if (!layer.paint) continue;

      const id = layer.id.toLowerCase();
      if (layer.type === 'background') {
        map.setPaintProperty(layer.id, 'background-color', '#090D16');
      } else if (layer.type === 'fill' && id.includes('water')) {
        map.setPaintProperty(layer.id, 'fill-color', '#050B24');
      } else if (layer.type === 'fill' && /park|green|wood|forest/.test(id)) {
        map.setPaintProperty(layer.id, 'fill-color', '#064E3B');
      } else if (layer.type === 'fill' && (id.includes('landcover') || id.includes('landuse'))) {
        map.setPaintProperty(layer.id, 'fill-color', '#0F172A');
      } else if (layer.type === 'line' && id.startsWith('road_') && id.includes('case')) {
        map.setPaintProperty(layer.id, 'line-color', '#1E293B');
        map.setPaintProperty(layer.id, 'line-opacity', 0.96);
      } else if (layer.type === 'line' && id.startsWith('road_')) {
        const majorRoad = /motorway|_mot_|trunk|primary|_pri_/.test(id);
        const secondaryRoad = /secondary|tertiary|_sec_/.test(id);
        const color = majorRoad ? '#F97316' : secondaryRoad ? '#64748B' : '#334155';
        map.setPaintProperty(layer.id, 'line-color', color);
        map.setPaintProperty(layer.id, 'line-opacity', majorRoad || secondaryRoad ? 0.98 : 0.86);
        if (map.getPaintProperty(layer.id, 'line-blur') !== undefined) {
          map.setPaintProperty(layer.id, 'line-blur', 0.35);
        }
      } else if (layer.type === 'line' && id.includes('waterway')) {
        map.setPaintProperty(layer.id, 'line-color', '#22D3EE');
        map.setPaintProperty(layer.id, 'line-opacity', 0.92);
      } else if (layer.type === 'symbol' && layer.layout?.['text-field']) {
        const roadLabel = id.includes('roadname');
        const cityLabel = /place|city|town|suburb|village/.test(id);
        map.setPaintProperty(layer.id, 'text-color', '#F8FAFC');
        map.setPaintProperty(layer.id, 'text-halo-color', '#000000');
        map.setPaintProperty(layer.id, 'text-halo-width', roadLabel || cityLabel ? 2 : 1.8);
        map.setPaintProperty(layer.id, 'text-opacity', 1);
        if (map.getPaintProperty(layer.id, 'text-halo-blur') !== undefined) {
          map.setPaintProperty(layer.id, 'text-halo-blur', 0.45);
        }
      }
    }
  }

  function attachLeafletBase(leafletMap) {
    if (!leafletMap || typeof leafletMap.getCenter !== 'function') {
      throw new TypeError('A Leaflet map is required.');
    }
    if (!window.maplibregl?.Map) {
      throw new Error('MapLibre GL JS is required for the shared map style.');
    }
    if (leafletBaseMaps.has(leafletMap)) return leafletBaseMaps.get(leafletMap);

    const pane = leafletMap.createPane('safaritrakMapLibreBase');
    pane.style.zIndex = '200';
    pane.style.pointerEvents = 'none';
    const container = document.createElement('div');
    container.className = 'safaritrak-maplibre-leaflet-base';
    pane.append(container);

    const positionPane = () => {
      const size = leafletMap.getSize();
      const containerRect = leafletMap.getContainer().getBoundingClientRect();
      const mapPaneRect = leafletMap.getPane('mapPane').getBoundingClientRect();
      pane.style.left = `${containerRect.left - mapPaneRect.left}px`;
      pane.style.top = `${containerRect.top - mapPaneRect.top}px`;
      pane.style.width = `${size.x}px`;
      pane.style.height = `${size.y}px`;
    };
    positionPane();

    const center = leafletMap.getCenter();
    const map = new window.maplibregl.Map({
      container,
      style: styleUrl,
      center: [center.lng, center.lat],
      zoom: leafletMap.getZoom(),
      attributionControl: false,
      interactive: false,
      fadeDuration: 0,
      renderWorldCopies: false,
    });

    const syncCamera = () => {
      const currentCenter = leafletMap.getCenter();
      positionPane();
      map.jumpTo({
        center: [currentCenter.lng, currentCenter.lat],
        zoom: leafletMap.getZoom(),
      });
    };
    const resize = () => {
      positionPane();
      map.resize();
      syncCamera();
    };

    leafletMap.on('move', syncCamera);
    leafletMap.on('resize', resize);
    leafletMap.on('zoom', syncCamera);
    leafletMap.once('unload', () => map.remove());
    leafletMap.attributionControl?.addAttribution(
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/about-carto/">CARTO</a>'
    );
    map.on('style.load', () => {
      apply(map);
      syncCamera();
    });
    map.on('error', event => {
      if (event?.error) console.error('SafariTrak shared map tiles could not be loaded.', event.error);
    });

    leafletBaseMaps.set(leafletMap, map);
    return map;
  }

  window.SafariTrakMapStyle = Object.freeze({ styleUrl, apply, attachLeafletBase, getRouteColors });
})();
