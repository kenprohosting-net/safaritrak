# SafariTrak design system

## Direction

“Your journey. Safer together.” The entire interface follows the map’s Cyber Aurora palette: midnight navy surfaces, cyan primary actions, magenta and violet secondary accents, orange highlights, green success states, and a distinct emergency red. The light theme uses cool blue-tinted surfaces and accessible darker equivalents of the same accents.

## Shared theme tokens

Tokens live in `theme.css` and apply to the PHP shell and map:

| Token | Dark | Light | Use |
| --- | --- | --- | --- |
| `--bg-app` | `#050817` | `#EDF4FA` | Page canvas |
| `--bg-panel` | `rgba(16, 21, 45, 0.4)` | `rgba(151, 196, 221, 0.4)` | Shared translucent glass for cards, drawers, dialogs |
| `--bg-input` | `#10152D` | `#F2F8FC` | Inputs and controls |
| `--accent-primary` | `#00D9FF` | `#007C95` | Primary actions and focus |
| `--accent-secondary` | `#FF24C8` | `#8B35C9` | Magenta/violet secondary accent |
| `--accent-tertiary` | `#FF8A24` | `#B85A00` | Orange emphasis |
| `--accent-lime` | `#19FF79` | `#087A50` | Success and positive status |
| `--accent-purple` | `#A020F0` | `#7724BF` | Violet distinction |
| `--sos-red` | `#FF2638` | `#D9233A` | SOS actions |
| `--text-primary` | `#E8E8FF` | `#13263E` | Main text |
| `--text-secondary` | `#AAB7D5` | `#425A73` | Supporting text |
| `--border-glass` | `#343D60` | `#B8CEDC` | Borders and dividers |
| `--route-stroke` | `#FF3B4D` | `#D7263D` | Active route |
| `--route-completed-stroke` | `#FF3B4D` | `#D7263D` | Completed route |
| `--group-member-1` … `--group-member-8` | Distinct themed palette | Distinct accessible palette | Group members |

## Cyber Aurora map palette

The palette is applied to the actual CARTO/MapLibre vector layers and the Google Maps style fallback; the rest of the app uses the same navy/cyan/magenta/violet/green/orange visual language with contrast-adjusted light-theme colors.

| Map feature | Dark color |
| --- | --- |
| Background / land | `#050817` |
| Urban areas / buildings | `#10152D` / `#14768B` |
| Major highways / primary roads | `#C97835` / `#FF8A24` |
| Secondary / local streets | `#B14DFF` / `#82AD98` |
| Service roads / trails | `#9685AF` / `#8E85A7` |
| Taller buildings with height data | `#08B9A6` |
| Commercial / industrial / retail land use | `#D6A75F` / `#57B9C8` / `#D65AA9` |
| Parks / water / rivers | `#087A50` / `#082D50` / `#00D9FF` |
| Labels / boundaries | `#E8E8FF` / `#7654C9` |
| Active / completed routes | `#FF3B4D` |

Road casings and route underlays stay dark and restrained for legibility. Tertiary streets use the subdued local-road color; service roads and paths are muted lavender, while major, primary, and secondary roads remain vivid. Commercial, industrial, retail, construction, office, education, and healthcare areas receive category colors. Building-use categories are not exposed by the current vector tiles, so footprints use a distinctive teal, with taller footprints highlighted only when a height value is available. Glow is limited to a soft red route halo rather than applied to the basemap.

Use 4/8px spacing increments, 10–20px control/card radii, readable system typography, and subtle elevation. Desktop navigation is a compact floating bar; tablet widths use a scrollable compact navigation with dedicated side-by-side Explore panels; phone widths use a dismissible drawer and bottom navigation. The theme control persists locally where storage is available. Reduced-motion preferences are respected; keyboard focus has a visible theme-aware ring.

The shared map uses CARTO's Protomaps vector tiles with OpenStreetMap features. Theme colors are applied only to features available in those tiles; ESA WorldCover, DEM terrain, and a geofence overlay are not currently integrated. Map styling must not imply that these unavailable datasets are present.

## Interaction and accessibility requirements

- Keep touch targets approximately 40px or larger and label icon-only controls for assistive technology.
- Preserve semantic headings, form labels, button types, focus visibility, Escape-to-close for dialogs/drawers, and visible loading/empty/error/offline states.
- Never communicate tracking, SOS status, or stale data by colour alone.
- Location sharing must clearly identify recipients, scope, and a revocation path.
- The 2D map remains the default. Any future 3D view is optional and must not hide accessible list/detail alternatives.
- On mobile route views, keep the current route segment close and prominent; users can pan or zoom out to inspect the destination.
- Floating panels use map-tinted, 40%-opaque glass with an 8px backdrop blur; interactive controls use a stronger 12px blur and palette-accented secondary surfaces. Borders and text remain theme-aware. Respect reduced-transparency preferences with solid surfaces.
- Test both themes at narrow/mobile and desktop sizes; reduced-motion and keyboard-only navigation are part of acceptance.

## Reference image inventory

- `docs/design-references/safaritrak-map-route-reference.png` — route with participant markers and a dark map.
- `docs/design-references/safaritrak-mobile-ui-reference.png` — overview collage for events, live map, and group chat; use only as broad direction.
- `docs/design-references/master-change-brief.pdf` — source brief and its additional visual reference pages.

Per-screen, per-device image files are not yet generated. Each screen in the [catalog](product/screen-catalog.md) has a pending reference filename until its own reference is created and reviewed.
