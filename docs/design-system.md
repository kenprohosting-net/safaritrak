# SafariTrak design system (Phase 1)

## Direction

“Your journey. Safer together.” Use a premium midnight interface with restrained violet/magenta highlights and cyan accents. Reserve red/pink for urgent safety states; do not make green the brand colour. Offer a high-contrast light counterpart. The existing supplied map and phone collage are references, not production artwork.

## Shared tokens

Tokens currently live in `dashboard.css` and apply to the PHP shell:

| Token | Dark | Light | Use |
| --- | --- | --- | --- |
| `--bg` | `#0a0810` | `#f7f4fa` | Page canvas |
| `--panel` | `#14111b` | `#ffffff` | Cards, drawers, dialogs |
| `--panel-raised` | `#1b1724` | `#f4eef9` | Controls and raised surfaces |
| `--p` | `#a855f7` | `#7e22ce` | Primary actions |
| `--pd` | `#c084fc` | `#9333ea` | Highlight |
| `--gold` | `#22d3ee` | `#0891b2` | Cyan accent / route marker |
| `--danger` | `#fb477a` | `#be123c` | SOS and urgent danger only |
| `--ink` | `#f5f1fb` | `#21162f` | Main text |
| `--muted` | `#aaa1b8` | `#655b73` | Supporting text |
| `--line` | `#30293b` | `#e6ddec` | Borders and dividers |

Use 4/8px spacing increments, 10–20px control/card radii, readable system typography, and subtle elevation. Desktop navigation is fixed at the left; mobile navigation is a dismissible drawer with backdrop and keyboard-operable button. The theme control persists locally where storage is available. Reduced-motion preferences are respected; keyboard focus has a visible cyan ring.

## Interaction and accessibility requirements

- Keep touch targets approximately 40px or larger and label icon-only controls for assistive technology.
- Preserve semantic headings, form labels, button types, focus visibility, Escape-to-close for dialogs/drawers, and visible loading/empty/error/offline states.
- Never communicate tracking, SOS status, or stale data by colour alone.
- Location sharing must clearly identify recipients, scope, and a revocation path.
- The 2D map remains the default. Any future 3D view is optional and must not hide accessible list/detail alternatives.
- Floating glass panels keep a thin violet/cyan/magenta outline idle; animate it only on the panel under pointer hover, keyboard focus, or explicit engagement. Respect reduced-motion settings by stopping the outline animation.
- Test both themes at narrow/mobile and desktop sizes; reduced-motion and keyboard-only navigation are part of acceptance.

## Reference image inventory

- `docs/design-references/safaritrak-map-route-reference.png` — route with participant markers and a dark map.
- `docs/design-references/safaritrak-mobile-ui-reference.png` — overview collage for events, live map, and group chat; use only as broad direction.
- `docs/design-references/master-change-brief.pdf` — source brief and its additional visual reference pages.

Per-screen, per-device image files are not yet generated. Each screen in the [catalog](product/screen-catalog.md) has a pending reference filename until its own reference is created and reviewed.
