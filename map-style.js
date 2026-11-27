(() => {
  const styleUrl = () => document.documentElement.dataset.theme === 'light'
    ? 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json'
    : 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';
  const leafletBaseMaps = new WeakMap();

  function cssColor(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  }

  function getMapPalette() {
    return {
      background: cssColor('--map-background', '#050817'),
      land: cssColor('--map-land', '#050817'),
      urban: cssColor('--map-urban', '#10152D'),
      forest: cssColor('--map-forest', '#087A50'),
      park: cssColor('--map-park', '#087A50'),
      grass: cssColor('--map-grass', '#087A50'),
      soil: cssColor('--map-soil', '#765435'),
      farmland: cssColor('--map-farmland', '#A17A42'),
      ocean: cssColor('--map-ocean', '#082D50'),
      lake: cssColor('--map-lake', '#082D50'),
      rocky: cssColor('--map-rocky', '#53616C'),
      wetland: cssColor('--map-wetland', '#087A50'),
      roadCasing: cssColor('--map-road-casing', '#10152D'),
      roadHighway: cssColor('--map-road-highway', '#C97835'),
      roadMajor: cssColor('--map-road-major', '#FF8A24'),
      roadSecondary: cssColor('--map-road-secondary', '#B14DFF'),
      roadResidential: cssColor('--map-road-residential', '#82AD98'),
      roadService: cssColor('--map-road-service', '#9685AF'),
      trail: cssColor('--map-trail', '#8E85A7'),
      building: cssColor('--map-building', '#14768B'),
      buildingHighlight: cssColor('--map-building-highlight', '#23A9BD'),
      buildingOutline: cssColor('--map-building-outline', '#155966'),
      buildingTall: cssColor('--map-building-tall', '#08B9A6'),
      buildingTallHighlight: cssColor('--map-building-tall-highlight', '#47E1C5'),
      landuseCommercial: cssColor('--map-landuse-commercial', '#D6A75F'),
      landuseIndustrial: cssColor('--map-landuse-industrial', '#57B9C8'),
      landuseRetail: cssColor('--map-landuse-retail', '#D65AA9'),
      landuseConstruction: cssColor('--map-landuse-construction', '#D96D68'),
      landuseOffice: cssColor('--map-landuse-office', '#9676C7'),
      landuseEducation: cssColor('--map-landuse-education', '#D4BD69'),
      landuseHealthcare: cssColor('--map-landuse-healthcare', '#E36C78'),
      boundary: cssColor('--map-boundary', '#7654C9'),
      countryBorder: cssColor('--map-country-border', '#7654C9'),
      waterway: cssColor('--map-waterway', '#00D9FF'),
      waterEdge: cssColor('--map-water-edge', '#0B5A75'),
      label: cssColor('--map-label', '#E8E8FF'),
      majorLabel: cssColor('--map-label-major', '#E8E8FF'),
      waterLabel: cssColor('--map-label-water', '#E8E8FF'),
      labelHalo: cssColor('--map-label-halo', '#050817'),
      poiPark: cssColor('--map-poi-park', '#19FF79'),
      poiLandmark: cssColor('--map-poi-landmark', '#FF8A24'),
    };
  }

  function getRouteColors() {
    return {
      stroke: cssColor('--route-stroke', '#FF3B4D'),
      glow: cssColor('--route-glow', 'rgba(255, 59, 77, 0.24)'),
      completedStroke: cssColor('--route-completed-stroke', '#FF3B4D'),
      completedGlow: cssColor('--route-completed-glow', 'rgba(255, 59, 77, 0.14)'),
      casing: cssColor('--map-road-casing', '#10152D'),
    };
  }

  function getTrackingColors() {
    return {
      user: cssColor('--map-marker-user', '#A020F0'),
      start: cssColor('--map-marker-start', '#00D9FF'),
      destination: cssColor('--map-marker-destination', '#FF8A24'),
      sos: cssColor('--map-marker-sos', '#FF2638'),
      geofence: cssColor('--map-marker-geofence', '#A020F0'),
      groupMembers: [1, 2, 3, 4, 5, 6, 7, 8]
        .map(index => cssColor(`--group-member-${index}`, '#D1D5DB')),
    };
  }

  function addClassifiedLandUse(map, palette) {
    if (!map.getSource('protomaps')) return;

    const roadLayer = map.getStyle().layers.find(layer => layer.id.startsWith('road_'))?.id;
    const layers = [
      {
        id: 'safaritrak-atlas-landuse',
        kinds: [
          'farmland', 'orchard', 'vineyard', 'allotments', 'farmyard', 'plant_nursery',
          'greenhouse_horticulture', 'wetland', 'marsh', 'bare_rock', 'rock', 'scree',
          'shingle', 'sand', 'desert',
        ],
        paint: {
          'fill-color': [
            'match',
            ['get', 'kind'],
            ['farmland', 'orchard', 'vineyard', 'allotments', 'farmyard', 'plant_nursery', 'greenhouse_horticulture'],
            palette.farmland,
            ['wetland', 'marsh'],
            palette.wetland,
            ['bare_rock', 'rock', 'scree', 'shingle'],
            palette.rocky,
            ['sand', 'desert'],
            palette.soil,
            palette.land,
          ],
          'fill-opacity': ['interpolate', ['linear'], ['zoom'], 5, 0.48, 10, 0.78, 15, 0.9],
          'fill-outline-color': palette.land,
        },
      },
      {
        id: 'safaritrak-atlas-urban',
        kinds: [
          'commercial', 'industrial', 'retail', 'construction', 'office',
          'school', 'university', 'college', 'hospital', 'healthcare',
        ],
        paint: {
          'fill-color': [
            'match',
            ['get', 'kind'],
            'commercial', palette.landuseCommercial,
            'industrial', palette.landuseIndustrial,
            'retail', palette.landuseRetail,
            'construction', palette.landuseConstruction,
            'office', palette.landuseOffice,
            ['school', 'university', 'college'], palette.landuseEducation,
            ['hospital', 'healthcare'], palette.landuseHealthcare,
            palette.urban,
          ],
          'fill-opacity': ['interpolate', ['linear'], ['zoom'], 5, 0.28, 10, 0.48, 15, 0.68],
          'fill-outline-color': palette.buildingOutline,
        },
      },
    ];

    for (const layer of layers) {
      if (map.getLayer(layer.id)) continue;
      const styleLayer = {
        id: layer.id,
        type: 'fill',
        source: 'protomaps',
        'source-layer': 'landuse',
        minzoom: 5,
        filter: ['in', ['get', 'kind'], ['literal', layer.kinds]],
        paint: layer.paint,
      };
      if (roadLayer) map.addLayer(styleLayer, roadLayer);
      else map.addLayer(styleLayer);
    }
  }

  function stylePoiLayers(map, palette, layers) {
    const attractionLayer = layers.find(layer => layer.id === 'poi_stadium');
    if (attractionLayer) {
      map.setFilter(attractionLayer.id, [
        'all',
        ['in', ['get', 'kind'], ['literal', ['stadium', 'attraction']]],
        ['<=', ['coalesce', ['get', 'min_zoom'], 0], 13],
      ]);
      map.setPaintProperty(attractionLayer.id, 'text-color', palette.poiLandmark);
      map.setPaintProperty(attractionLayer.id, 'text-halo-color', palette.labelHalo);
      map.setPaintProperty(attractionLayer.id, 'icon-color', palette.poiLandmark);

      if (!map.getLayer('safaritrak-atlas-poi-cemetery')) {
        const cemeteryLayer = {
          ...attractionLayer,
          id: 'safaritrak-atlas-poi-cemetery',
          filter: [
            'all',
            ['==', ['get', 'kind'], 'cemetery'],
            ['<=', ['coalesce', ['get', 'min_zoom'], 0], 13],
          ],
          paint: {
            ...attractionLayer.paint,
            'text-color': palette.label,
            'text-halo-color': palette.labelHalo,
            'icon-color': palette.boundary,
          },
        };
        const beforeLayer = map.getLayer('poi_park')?.id;
        if (beforeLayer) map.addLayer(cemeteryLayer, beforeLayer);
        else map.addLayer(cemeteryLayer);
      }
    }

    const parkLayer = map.getLayer('poi_park');
    if (parkLayer) {
      map.setPaintProperty(parkLayer.id, 'text-color', palette.poiPark);
      map.setPaintProperty(parkLayer.id, 'text-halo-color', palette.labelHalo);
      map.setPaintProperty(parkLayer.id, 'icon-color', palette.poiPark);
    }
  }

  function apply(map) {
    const layers = map?.getStyle()?.layers;
    if (!layers) return;

    const palette = getMapPalette();
    const layerIds = new Set(layers.map(layer => layer.id));
    const setPaint = (id, property, value) => {
      if (layerIds.has(id)) map.setPaintProperty(id, property, value);
    };
    const landcoverColor = [
      'match',
      ['get', 'kind'],
      ['wood', 'forest'],
      palette.forest,
      ['grass', 'grassland', 'meadow', 'village_green'],
      palette.grass,
      ['park', 'recreation_ground', 'garden', 'golf_course'],
      palette.park,
      ['allotments'],
      palette.farmland,
      ['scrub', 'heath'],
      palette.grass,
      palette.land,
    ];
    const waterColor = [
      'match',
      ['get', 'kind'],
      ['ocean', 'sea'],
      palette.ocean,
      ['lake', 'pond', 'reservoir'],
      palette.lake,
      palette.lake,
    ];
    const buildingHeight = ['to-number', ['get', 'height'], 0];
    const buildingColor = [
      'case',
      ['>=', buildingHeight, 20],
      palette.buildingTall,
      palette.building,
    ];
    const buildingHighlightColor = [
      'case',
      ['>=', buildingHeight, 20],
      palette.buildingTallHighlight,
      palette.buildingHighlight,
    ];
    const roadLabelMinZooms = {
      roadname_major: 8,
      roadname_pri: 9,
      roadname_sec: 10,
      roadname_minor: 13,
    };

    addClassifiedLandUse(map, palette);
    setPaint('background', 'background-color', palette.background);
    setPaint('landcover', 'fill-color', landcoverColor);
    setPaint('landcover', 'fill-opacity', 0.96);
    setPaint('park_national_park', 'fill-color', palette.park);
    setPaint('park_nature_reserve', 'fill-color', palette.park);
    setPaint('landuse_residential', 'fill-color', palette.urban);
    setPaint('landuse_residential', 'fill-opacity', 0.72);
    setPaint('landuse', 'fill-color', palette.urban);
    setPaint('water', 'fill-color', waterColor);
    setPaint('water', 'fill-outline-color', palette.waterEdge);
    setPaint('waterway', 'line-color', palette.waterway);
    setPaint('waterway', 'line-opacity', 0.82);
    setPaint('building', 'fill-color', buildingColor);
    setPaint('building', 'fill-opacity', 0.84);
    setPaint('building', 'fill-outline-color', palette.buildingOutline);
    setPaint('building-top', 'fill-color', buildingHighlightColor);
    setPaint('building-top', 'fill-opacity', 0.76);
    setPaint('building-top', 'fill-outline-color', palette.buildingOutline);
    setPaint('boundary_county', 'line-color', palette.boundary);
    setPaint('boundary_state', 'line-color', palette.boundary);
    setPaint('boundary_country_outline', 'line-color', palette.countryBorder);
    setPaint('boundary_country_inner', 'line-color', palette.countryBorder);

    for (const layer of layers) {
      const id = layer.id.toLowerCase();
      if (Object.hasOwn(roadLabelMinZooms, layer.id)) {
        map.setLayerZoomRange(layer.id, roadLabelMinZooms[layer.id], 24);
      }
      if ((layer.id === 'building' || layer.id === 'building-top') && layer.type === 'fill') {
        map.setLayerZoomRange(layer.id, Math.max(layer.minzoom || 0, 14), layer.maxzoom || 24);
      }
      if (layer.type === 'line' && id.startsWith('road_')) {
        if (id.includes('_case')) {
          map.setPaintProperty(layer.id, 'line-color', palette.roadCasing);
          map.setPaintProperty(layer.id, 'line-opacity', 0.96);
        } else if (id.startsWith('road_mot') || id.startsWith('road_trunk')) {
          map.setPaintProperty(layer.id, 'line-color', palette.roadHighway);
          map.setPaintProperty(layer.id, 'line-opacity', 0.98);
        } else if (id.startsWith('road_pri')) {
          map.setPaintProperty(layer.id, 'line-color', palette.roadMajor);
          map.setPaintProperty(layer.id, 'line-opacity', 0.96);
        } else if (id.startsWith('road_sec')) {
          map.setPaintProperty(layer.id, 'line-color', [
            'match',
            ['get', 'kind_detail'],
            ['tertiary', 'tertiary_link'],
            palette.roadResidential,
            palette.roadSecondary,
          ]);
          map.setPaintProperty(layer.id, 'line-opacity', [
            'match',
            ['get', 'kind_detail'],
            ['tertiary', 'tertiary_link'],
            0.78,
            0.96,
          ]);
        } else if (id.startsWith('road_path')) {
          map.setPaintProperty(layer.id, 'line-color', palette.trail);
          map.setPaintProperty(layer.id, 'line-opacity', 0.76);
        } else if (id.startsWith('road_service')) {
          map.setPaintProperty(layer.id, 'line-color', palette.roadService);
          map.setPaintProperty(layer.id, 'line-opacity', 0.76);
        } else {
          map.setPaintProperty(layer.id, 'line-color', palette.roadResidential);
          map.setPaintProperty(layer.id, 'line-opacity', 0.78);
        }
      } else if (layer.type === 'symbol' && layer.layout?.['text-field']) {
        const isWaterLabel = id.startsWith('water');
        const isMajorPlace = /^place_(country|state|continent|city|town)/.test(id);
        const isLandmark = id === 'poi_stadium';
        const isPark = id === 'poi_park';
        map.setPaintProperty(
          layer.id,
          'text-color',
          isWaterLabel
            ? palette.waterLabel
            : isMajorPlace
              ? palette.majorLabel
              : isLandmark
                ? palette.poiLandmark
                : isPark ? palette.poiPark : palette.label
        );
        map.setPaintProperty(layer.id, 'text-halo-color', palette.labelHalo);
        map.setPaintProperty(layer.id, 'text-halo-width', isMajorPlace ? 1.5 : 1.2);
        map.setPaintProperty(layer.id, 'text-opacity', 0.96);
      }
    }

    stylePoiLayers(map, palette, layers);
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
      style: styleUrl(),
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

  window.SafariTrakMapStyle = Object.freeze({
    get styleUrl() { return styleUrl(); },
    apply,
    attachLeafletBase,
    getRouteColors,
    getTrackingColors,
  });
})();
