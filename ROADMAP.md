# WeathermapNG Roadmap

Where WeathermapNG is going, what already ships, and what remains open.

Current release: **v1.16.0**. Last reviewed v1.16.0 (2026-11-12) against the
LibreNMS plugin architecture, the network weathermap landscape, the LibreNMS API
surface (LLDP/CDP, health, alerts, RRD), and a codebase audit. Unreleased work
on `main` is listed under [Unreleased](CHANGELOG.md#unreleased).

## Contents

| Section | Scope |
|---------|-------|
| [Current status](#current-status) | Shipped feature set, plus unreleased work on `main` |
| [Roadmap principles](#roadmap-principles) | How work is prioritized |
| [Open work](#open-work) | Future and long-term items; all unshipped |
| [Completed by version](#completed-by-version) | Shipped history from v1.1.x to v1.16.0 |
| [Contributing](#contributing) | Where help is useful |
| [Feedback](#feedback) | Where to file ideas |

## Current Status: v1.16.0 (Stable) <a id="current-status"></a>

Stable. The core install, editor, rendering, and map-management workflows are in
place, with the hardening and operator features below.

Shipped:

- Professional 3-panel map editor: toolbox, canvas, properties sidebar
- Zoom/pan, undo/redo, keyboard shortcuts, grid snapping
- Dark/light theme auto-detection to match LibreNMS
- Real-time traffic from LibreNMS RRD data, with flow animations
- Map versioning in the editor: save, restore, compare, delete
- Server-Sent Events for live updates, with polling fallback
- Embeddable views with navigation bar and NOC wall / kiosk mode with map cycling
- Nested maps and drill-down with a breadcrumb bar
- Operational diagnostics with data-integrity checks
- Map tags, filtering, and first-run onboarding
- Per-map default styles for nodes and links
- Demo mode with deterministic traffic
- Device-type node icons and status rings
- LLDP/CDP auto-discovery from LibreNMS topology data
- Editor bulk operations: multi-select, marquee, group drag, bulk delete with undo
- Viewport culling for large-map pan/zoom performance
- Node CPU/memory utilization overlay in the embed view
- RRD graph hover popups and click-through to LibreNMS device/port pages
- CLI commands for map create/list/export/discover
- JSON and legacy `.conf` import/export
- Embed API token auth for sessionless consumers, with Grafana IFrame docs
- Map lifecycle webhooks with optional HMAC signatures
- Gallery previews of real map and template topology
- Composer path package install flow for LibreNMS
- Weekly/manual install CI, upgrade lanes, and idempotent registration cleanup

Unreleased on `main`:

- Per-node style overrides (fill, label color, shape, size) with one-undo bulk
  apply, plus Ctrl/Cmd+A and bulk delete
- Per-user map favorites with an index star toggle and filter
- Static SVG export of the embed view
- Screenshot comparison in CI, with committed baselines at 480×800, 768×1024,
  and 1920×1080
- Particle budget tiers above 150 and 300 links (1500-particle cap) and
  secondary-label hiding below 0.6 zoom

The next phase focuses on saved views, historical playback, scheduled reports,
and the remaining export formats.

## Roadmap principles

1. **Polish before expansion**: fix rough edges in the editor, embed view,
   accessibility, and install experience before adding large new workflows.
2. **Protect installs**: keep install validation, route discovery, Composer
   metadata, and LibreNMS compatibility covered by CI.
3. **Stay SemVer-aware**: patch releases fix bugs and polish existing behavior;
   minor releases add user-facing capabilities; major releases are reserved for
   breaking changes.
4. **Favor operator workflows**: WeathermapNG should feel like a practical
   LibreNMS operations tool, not a demo canvas.
5. **Keep larger ideas visible**: track them here without letting them crowd out
   short-term product quality.

---

## Open work

Everything in this section is unshipped. Items move to
[Completed by version](#completed-by-version) when they land.

### Future

- [ ] **Saved views per user** — save named index filters (tags, favorites,
  search) per LibreNMS user. Favorites are on `main`; saved views are not
  implemented.
- [ ] **Historical playback** — timeline scrubber, play/pause controls, and
  speed adjustment, using the existing `RRDTool` time-range reads.
- [ ] **Export formats beyond PNG and SVG** — 2x/4x PNG scaling, PDF, and
  Visio/draw.io output if demand justifies the work.
- [ ] **Scheduled reports** — daily/weekly snapshots, email delivery, and
  PDF/PNG generation for NOC walls, extending `bin/map-poller.php`.
- [ ] **Metrics beyond CPU/memory** — latency (LibreNMS services API,
  `service_type=ping`), packet loss (`service_ds` loss datasource), and custom
  SNMP OID support if it can avoid unreliable polling.

### Long term

#### v2.0.0 - Next generation

These ideas are parked until the core product feels polished and maintainable.

- [ ] **Multi-user editing**
  - Presence indicators.
  - Conflict resolution.
  - Edit locking.
  - Per-map access control / visibility (requires user_id or role mapping on maps).
- [ ] **Advanced auto-layout algorithms**
  - Force-directed graphs.
  - Hierarchical layout.
  - Circular layout.
  - Geographic placement from device data (LibreNMS `locations` has lat/lng).
  - Semantic zooming: auto-clustering at low zoom, drill-down at high zoom.
- [ ] **Plugin ecosystem**
  - Custom data source plugins.
  - Visualization plugins.
  - Export format plugins.
- [ ] **API v2**
  - GraphQL support.
  - External data sources.
- [ ] **WebGL rendering engine** (2D graph scale, not 3D)
  - Migrate from Canvas 2D to Sigma.js + Graphology for 10k+ node graphs.
  - Keep the data model (nodes, links, live updates) unchanged; the SSE and
    polling layers are rendering-agnostic.
  - Use Three.js or deck.gl separately if 3D geographic positioning is needed.
  - Triggered when maps regularly exceed 300 nodes. No migration is needed
    before that.
- [ ] **Mobile app**
  - iOS/Android companion app.
  - Push notifications for alerts.
  - Quick map viewing.

---

## Completed by version

Shipped work, newest first. Summaries for v1.13.0 and later condense
[CHANGELOG.md](CHANGELOG.md); earlier sections keep the detail recorded when the
work landed.

### v1.16.0

- [x] **Version comparison UI**: side-by-side topology, before/after field
  details, explicit unavailable legacy fields, and read-only compare.
- [x] **Safe restore**: validate the snapshot and references, write a
  **Before restore** checkpoint, restore atomically, and record a
  **Restored version** entry.
- [x] **Complete history export**: export every saved version for a map, not
  only the displayed 20.
- [x] **Gallery topology previews** for maps and templates, with empty states
  and a bounded overview for larger maps.
- [x] **First-run sample maps**: deliberate, named creation from the welcome
  screen; canceling leaves the install empty.
- [x] **Install and upgrade hardening**: native Ubuntu/PHP-FPM upgrade CI lane,
  serialized install/deploy operations, stricter schema checks, and Composer
  repository ownership checks.

### v1.13.0 - v1.15.1

- [x] **Common topology templates**: Data Center, WAN/MPLS, Campus, and Branch
  Office, seeded through every install path.
- [x] **Nested maps and drill-down**: `parent_map_id`, cycle-bounded breadcrumbs,
  and `meta.sub_map_id` navigation in the embed view.
- [x] **Legacy `.conf` import/export** through `LegacyConfService`, including
  `?format=conf` and the CLI.
- [x] **Accessibility smoke suite**, `tests/screenshot-check.sh` + `just visual`,
  and the `justfile` task runner.
- [x] **Embed modularization**: `embed.blade.php` reduced from 2062 to 113
  lines; JS moved to `resources/js/embed-app.js`, styles to
  `resources/css/embed.css`.
- [x] **Map lifecycle webhooks** on create/update/delete/version-save, with
  optional HMAC-SHA256 signatures.
- [x] **Embed API token auth** for Grafana panels, NOC walls, and scripts.
- [x] **Canonical install guide** with backup/recovery, plus the v1.15.1 legacy
  dimension fallback for maps whose options omit width or height.

### v1.12.0

- [x] **Editor bulk operations**: multi-select via shift/ctrl-click or
  rubber-band marquee, bulk delete with undo, group drag, group keyboard nudge.
- [x] **LLDP/CDP auto-discovery**: `AutoDiscoveryService` reads the LibreNMS
  `links` table, dedupes by device pair, and creates missing nodes and links.
  Admin-gated endpoint, editor trigger, and Artisan command.
- [x] **Admin data-integrity diagnostics**: broken port associations, missing
  RRD files, orphan nodes and links, and dangling rows.
- [x] **Viewport culling**: embed `renderMap`/`renderOverlay` and editor
  `renderEditor` skip off-screen nodes/links via a world-rect AABB test. Curved
  bezier control points are included. The view rect is cached per frame.
- [x] **Node CPU/memory overlay**: CPU/MEM line below each node's traffic
  aggregate, gated by `show_node_metrics` and `?metrics=0`. Server-side metric
  queries are skipped when disabled.
- [x] **Editor canvas fit**: ResizeObserver preserves aspect ratio; the canvas
  buffer matches display × DPR.
- [x] **Editor coordinate system**: world dimensions separate from buffer size;
  pan, zoom, drag, grid, minimap, and culling use world coordinates.
- [x] **Selection state consistency** across add, duplicate, delete, group drag,
  and nudge. Snap-to-grid recalculates the drag offset.
- [x] **SSE connection management**: DB released before sleep, 15s heartbeat,
  and reconnect timers cleared on stop.
- [x] **Embed lifecycle**: graph popup images aborted on new hover;
  `visibilitychange` pauses RAF, SSE, and polling when backgrounded.
- [x] **Data clamping and lookup gating**: CPU/memory and link percentages
  clamped to 0-100; `LookupController` admin-gated.

### v1.11.0

- [x] **RRD graph hover popups**: hover a node or link for 300ms to see an
  inline LibreNMS graph. `?graphs=0` disables; kiosk mode disables automatically.
- [x] **Editor click-through**: "View Device" and "View Port" open LibreNMS
  pages in a new tab.
- [x] **Dead code cleanup** (−406 lines): 25+ dead methods across 14 source
  files; deleted the root `WeathermapNG.php` stub.
- [x] **Backend performance**: `DeviceDataService` N+1 fixed with preloaded
  device cache; `rrdtool fetch` calls halved; kiosk query skipped when unused.
- [x] **Frontend performance**: O(1) `nodeById` lookup, minimap decoupled from
  the animation loop, canvas layer separation, RAF pause on zero traffic.
- [x] **Map-poller fixes**: constructor, method name, batch preloads, and
  `LOCK_EX` file locking.
- [x] **CLI commands**: `weathermapng:create-map`, `list-maps`, `export`,
  `discover`.
- [x] **Editor device autocomplete**: debounced search replaces the full device
  `<select>`; reuses `/api/devices?q=`.
- [x] **Editor status-aware node colors**: green, red, gray, plus a dashed ring
  for down nodes.
- [x] **Embed status visuals**: pulsing red ring for down, yellow dashed for
  warning (CPU/MEM threshold), gray dashed for unknown. `node_warning` is
  admin-configurable.
- [x] **Alert badges**: clickable badges to `/device/{id}/tab=alerts/`, with
  hit detection ahead of node/link clicks.
- [x] **Alert tooltip and initial load**: alert count and severity in the hover
  tooltip; alerts included in the first page load, not only SSE.
- [x] **Alert history queries**: `deviceAlertHistory()` and
  `portAlertHistory()` without a state filter; 14 new tests.
- [x] **JSON export round-trip**: `_format` version stamp, import form
  auto-fill, `Map::createFromJsonData()`.
- [x] **O(N×L) → O(L) node-to-links index** in `NodeDataService`.
- [x] **Batch link validation**: `LinkService::storeLinks` pre-fetches nodes and
  ports in two queries.

### v1.10.0

- [x] **Dead auto-save removal**: orphaned `resources/js/versioning.js`,
  unreachable `MapVersionController::autoSave()`, `auto_save` config keys, and
  the unused validation field.
- [x] **Dead FormRequest and cache removal**: `CreateLinkRequest`,
  `CreateNodeRequest`, and `MapCacheService` (zero callers).
- [x] **Node label normalization**: shared `NodeLabelNormalizer` for
  `NodeService` and `SaveMapRequest`.
- [x] **Ambiguous node rate label** (issue #11): `humanBits()` formatter with a
  `Σ` prefix and "Total (In + Out)" tooltip.
- [x] **Settings page fix**: `Settings::authorize()` resolves `auth()->user()`;
  the form uses `settings[...]` notation so saves persist.
- [x] **Debug-gated traffic logging**: `WEATHERMAPNG_DEBUG=true` logs raw
  per-endpoint counters for issue #11 diagnosis.

### v1.9.0

- [x] **Map tags and filtering**: lowercase tags in `wmng_maps.options`, index
  tag chips and filter, normalized and deduplicated on save.
- [x] **First-run onboarding**: welcome links to Templates, Create, Import,
  Diagnostics, and Docs when no maps exist.
- [x] **Per-map default styles**: node and link defaults stored in map options,
  merged at render time, with allowlisted keys and hex validation.
- [x] **Operational diagnostics page**: health, counts, per-check status, route
  registration, and writable paths. Data-integrity checks followed in v1.12.0.
- [x] **NOC wall / kiosk mode**: `?kiosk=1` hides chrome and auto-hides the
  cursor; `?cycle=N` rotates maps; `Esc` toggles chrome.
- [x] **Embed click-through navigation**: clicking a node opens its LibreNMS
  device page and a link opens its port page. `?target=self` keeps navigation
  in the same tab. Editor buttons followed in v1.11.0.

### v1.8.0

- [x] **Map version history activated**: registered routes and built the editor
  UI for save, list, restore, delete, compare, and export.
- [x] **Version backend fixes**: `json_decode()` TypeError, wrong snapshot field
  names, upsert-only restore replaced by true rollback with ID preservation,
  and delete-only-the-selected-version semantics.
- [x] **Version admin gates**: `compare()`, `export()`, `store()`, and restore
  require an admin; `SaveMapVersionRequest` sanitizes text.
- [x] **Save validation hardening**: `SaveMapRequest` FormRequest, allowlisted
  link style, bounded via points, and `strip_tags()` on titles and labels.
- [x] **Health endpoint admin gates**: stats, metrics, and detailed health now
  require an admin; public probes stay minimal.
- [x] **Shared client helpers**: `wmng-common.js` and `ui-helpers.js`; the
  toast/loading class-shadowing crash was fixed.
- [x] **Dead duplicate assets removed** from the repository root.

### v1.7.0

- [x] **N+1 query elimination (Wave 1 + Wave 2)**: eager-load map, node, link,
  and version relations, and batch per-row caches so a 50-node map goes from
  ~400 queries to ~10. `MapCacheService` was later deleted as dead code; the
  eager-load pattern lives on in the controllers and `RrdDataService`
  request-local caches.
- [x] **Admin-only authorization**: all mutation endpoints require an admin via
  the `AdminCheck` trait. Reads are open to authenticated users.
  `MapPolicy` and `NodePolicy` were removed.
- [x] **Editor accessibility**: `aria-label` and explicit `type="button"` on
  icon-only controls; focus-visible outlines preserved.
- [x] **`mergeMapOptions` data-loss fix**: all option keys survive a save, not
  only `width`/`height`/`background`.
- [x] **Release readiness checklist**: `RELEASE.md` created.
- [x] **Dead code removal**: `SseStreamService`, `MapDataBuilder`, `MapPolicy`,
  `NodePolicy`, `test_hooks.php`, `debug_plugin_web.php`, and the root
  `map-poller.php`; canonical poller is `bin/map-poller.php`.
- [x] **Deterministic demo traffic**: per-id sine waves instead of `rand()`.
- [x] **Correctness fixes**: `NodeService::deleteNode` `orWhere`,
  `LinkService` validation, import transaction, eager-load fixes, and a
  narrowed `catch` in `MapLinkController`.

### v1.6.x - v1.7.8

- [x] **Embed controls cleanup**: valid zoom-button HTML, safer DOM
  construction, Font Awesome icons, accessible labels.
- [x] **Editor accessibility pass**: labels, button types, keyboard and focus
  affordances, and no focus-outline overrides.
- [x] **Responsive editor layout** (v1.7.8): CSS media queries stack the sidebar
  below the canvas on narrow screens, with a horizontal toolbox and wrapping
  topbar. Browser-tested at 768px and 1920px.
- [x] **Embed responsive polish** (v1.7.5, v1.7.8): wrapping nav and controls,
  responsive offsets, minimap hidden on very small screens, and reduced-motion
  handling for flow animations. Browser-tested at 480px.
- [x] **Index and template gallery polish** (v1.7.8, v1.13.0, v1.16.0): escaped
  template cards, delegated listeners, sanitized category classes, fixed
  creation redirect, four built-in topology templates, and real previews.
- [x] **Theme and UI cleanup** (v1.7.4, v1.7.8, v1.13.0): narrowed
  MutationObserver filters, static inline styles moved to CSS, and the embed
  view split into `embed.css` and `embed-app.js`.
- [x] **LibreNMS hook and legacy view polish**: Bootstrap button conventions,
  safe external links, accessible labels, live-map entry points, and
  confirmation UI for legacy delete.
- [x] **Settings admin polish**: Bootstrap feedback and confirmation instead of
  browser alerts; text-safe preview; explicit unavailable restore behavior.
- [x] **Index and editor confirmation polish**: Bootstrap modals for destructive
  actions; editor save errors route through toast feedback.
- [x] **Validation coverage** (v1.13.0): `AccessibilitySmokeTest` (12 plain
  PHPUnit cases), `tests/screenshot-check.sh` and `just visual` at
  480/768/1920 px with a degenerate-capture guard, and the `justfile` facades.
  Screenshot comparison in CI is unreleased.
- [x] **Release readiness checklist**: documented the pre-release validation
  flow, manual QA, and patch/minor/major definitions.
- [x] **Upgrade safety** (v1.7.4): added upgrade notes to `INSTALL.md`, verified
  existing config/output/validation tables, and made failure messages
  actionable.

### Earlier releases (v1.1.x - v1.6.5)

#### v1.6.5

- [x] Quick install normalizes LibreNMS plugin registration after enablement.
- [x] Duplicate inactive `WeathermapNG` rows are cleaned from the `plugins` table.
- [x] Install docs explain expected `wmng_*` and JSON-column `utf8mb4_bin`
  warnings.
- [x] Legacy map list delete action uses Bootstrap confirmation.

#### v1.6.4

- [x] Composer path package registration during quick install.
- [x] Weekly/manual LibreNMS install smoke test in CI.
- [x] Mocked quick-install validation flow in CI.
- [x] Install documentation alignment checks.
- [x] Route verification during quick install.
- [x] Output directory configuration and readiness fixes.
- [x] PHP 8.2+ requirement alignment across Composer, runtime checks, and tests.
- [x] Health route exposure tightened: public liveness/readiness, authenticated
  details/metrics.
- [x] Version metadata guardrails across `VERSION`, `composer.json`, changelog,
  and release tag.
- [x] Release workflow validation for SemVer and version consistency.

#### v1.6.2

- [x] Via points and via style for links: curved, angled, and straight paths.
- [x] Interactive via point editing: add, drag, delete.
- [x] Flow particles follow waypoint paths.
- [x] Catmull-Rom spline interpolation for curved via style.
- [x] Global `link_style` setting wired to embed and blade editors.
- [x] `show_bandwidth` and `show_percentages` honored in the embed viewer.
- [x] Via style dropdown in the blade editor link modal.
- [x] Version read from Composer metadata.
- [x] Modern editor `saveMap` endpoint fixed.
- [x] Settings terminology aligned: orthogonal to angled.

#### v1.6.1

- [x] Professional 3-panel editor layout: toolbox, canvas, properties sidebar.
- [x] Dark/light theme auto-detection matching LibreNMS.
- [x] Zoom and pan: mouse wheel plus middle-click panning.
- [x] Undo/redo with 50-state history.
- [x] Keyboard shortcuts: Ctrl+S/Z/Y, Delete, arrow nudge, +/-/0 zoom, Esc.
- [x] Editor minimap with click-to-navigate.
- [x] Grid snapping toggle with overlay.
- [x] Smart spiral node placement.
- [x] Node boundary checking and canvas resize validation.
- [x] Node duplication and inline property editing.
- [x] Link mode with visual feedback.
- [x] Unsaved-changes indicator (auto-save wiring removed in v1.7.0 because it
  referenced non-existent elements).
- [x] Fixed link bandwidth utilization calculation.
- [x] Templates gallery with built-in templates.
- [x] Index redesign: search, sort, cards, empty state.
- [x] Demo mode with simulated traffic.
- [x] Docker development environment.

#### v1.6.0

- [x] Simplified data fetching: RRD-only; buggy API/SNMP fallback removed.
- [x] RRD path resolution matched to LibreNMS naming.
- [x] Utilization calculation fixed for full-duplex links.
- [x] Device-type node icons: router, switch, server, firewall.
- [x] Link tooltips include bandwidth capacity.
- [x] Embed navigation bar with map title and edit link.
- [x] Service registration in the ServiceProvider.
- [x] Admin-only settings authorization.
- [x] Cache key collision fix.
- [x] Unreliable ifIndex auto-discovery disabled, later replaced by the
  LLDP/CDP rewrite in v1.12.0.

#### v1.5.x

- [x] Map versioning foundation: storage and services shipped dormant and were
  activated in v1.8.0, which registered the routes and added the editor UI (see
  [VERSIONING.md](docs/VERSIONING.md)).
- [x] Demo mode for testing without devices.
- [x] Improved install scripts.
- [x] Removed heatmap due to pan/zoom sync issues.

#### v1.4.x - v1.5.0

- [x] Security hardening: XSS prevention and input validation.
- [x] Authorization policies (replaced by admin-only `AdminCheck` in v1.7.0).
- [x] FormRequest validation.

#### v1.3.x

- [x] Performance caching system.
- [x] E2E installation tests.

#### v1.2.x

- [x] Map templates.
- [x] Accessibility improvements.
- [x] Toast notifications.
- [x] Loading states.
- [x] Web installer.

#### v1.1.x

- [x] Database-driven architecture.
- [x] MVC structure with services.
- [x] Real-time SSE updates.
- [x] RESTful JSON API.
- [x] D3.js editor.
- [x] Embed viewer.
- [x] Early auto-discovery.

---

## Contributing

Want to help? Check out:

- [CONTRIBUTING.md](CONTRIBUTING.md) - How to contribute
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues) - Feature
  requests and bugs

### Priority areas

1. **Saved views and dashboard organization**: per-user saved index filters
   alongside favorites.
2. **Historical playback and reports**: RRD time-range playback and scheduled
   NOC-wall snapshots.
3. **Export formats**: PDF, Visio/draw.io, and 2x/4x PNG.
4. **Metrics beyond CPU/memory**: latency, packet loss, and custom OIDs.
5. **Multi-user editing**: presence, conflict resolution, and edit locking.
6. **Rendering above 300 nodes**: the WebGL engine work.
7. **Integration**: API v2, plugin ecosystem, and external data sources.

## Feedback

Have ideas for the roadmap?

- Open a [GitHub Issue](https://github.com/lance0/weathermapNG/issues)
- Join the [LibreNMS Community](https://community.librenms.org)
