# WeathermapNG Roadmap

This document outlines the development roadmap for WeathermapNG, a network visualization plugin for LibreNMS.

*Last reviewed v1.7.8 (2026-07-08) against LibreNMS plugin architecture, network weathermap competitive landscape (PHP Weathermap, Zabbix, NagVis, PRTG, Datadog/Kentik), LibreNMS API surface (LLDP/CDP, health, alerts, RRD), and codebase audit of dormant infrastructure.*

## Current Status: v1.7.8 (Stable)

The plugin is usable today for production-oriented network map visualization, with the core install, rendering, and editor workflows in place, plus waves of performance, authorization, correctness, and install hardening landed in v1.7.0–v1.7.8:

- Professional 3-panel map editor: toolbox, canvas, properties sidebar
- Zoom/pan, undo/redo, keyboard shortcuts, grid snapping
- Dark/light theme auto-detection to match LibreNMS
- Real-time traffic visualization using LibreNMS RRD data
- Flow animations with particle effects
- Map versioning foundation (storage and services; editor UI removed in v1.7.0; routes not registered)
- Server-Sent Events for live updates
- Embeddable views with navigation bar
- Demo mode for testing
- Device-type node icons: router, switch, server, firewall
- Composer path package install flow for LibreNMS
- Weekly/manual install CI coverage against LibreNMS
- Idempotent LibreNMS plugin registration cleanup during reinstall

The next phase should focus on polish, reliability, and maintainability before large new features. The project already has enough capability to be useful; the main opportunity is making the existing workflows feel dependable and finished.

---

## Roadmap Principles

1. **Polish before expansion**: Fix rough edges in the editor, embed view, accessibility, and install experience before adding large new workflows.
2. **Protect installs**: Keep install validation, route discovery, Composer metadata, and LibreNMS compatibility covered by CI.
3. **Stay SemVer-aware**: Patch releases fix bugs and polish existing behavior. Minor releases add user-facing capabilities. Major releases are reserved for breaking changes.
4. **Favor operator workflows**: WeathermapNG should feel like a practical LibreNMS operations tool, not a demo canvas.
5. **Keep old feature ideas visible**: Larger ideas remain tracked, but they should not crowd out short-term product quality.

---

## Immediate Priority

### v1.6.x - Product Polish & Bug Fixes

These are patch-level improvements unless they require new user-facing behavior.

- [x] **Embed controls cleanup**
  - Fix malformed generated zoom button HTML in the embed view.
  - Replace inline string-generated controls with safer DOM construction or templates.
  - Standardize iconography with Font Awesome instead of emoji controls.
  - Ensure all embed controls have accessible labels.

- [x] **Editor accessibility pass**
  - Add `aria-label` and explicit `type="button"` to icon-only toolbar controls.
  - Add keyboard and focus affordances for critical editor actions.
  - Provide useful fallback text or alternate structured editing affordances for canvas-only workflows.
  - Remove focus outline overrides that make keyboard navigation harder.

- [x] **Responsive editor layout** *(v1.7.8: CSS media queries — stacked sidebar below canvas on narrow screens, horizontal toolbox, flex-wrap topbar. Browser-tested at 768px and 1920px.)*
  - ~~Replace fixed `calc(100vh - 120px)` assumptions with layout that works inside LibreNMS chrome.~~ *(v1.7.8)*
  - ~~Improve sidebar behavior on smaller screens.~~ *(v1.7.8: stacks below canvas with max-height scroll)*
  - ~~Reduce nested scrolling where possible.~~ *(v1.7.8: addressed by stacking sidebar below canvas)*
  - ~~Keep topbar actions usable when map names or counts are long.~~ *(v1.7.8: flex-wrap)*

- [x] **Embed view responsive polish** *(v1.7.5: reduced-motion; v1.7.8: CSS flex-wrap nav/controls, responsive offsets, minimap hidden on very small screens. Browser-tested at 480px.)*
  - ~~Make the top navigation and control groups wrap, collapse, or reposition cleanly.~~ *(v1.7.8)*
  - ~~Prevent controls, minimap, legend, and map content from overlapping on smaller embeds.~~ *(v1.7.8: responsive offsets)*
  - ~~Add reduced-motion handling for flow animations.~~ *(v1.7.5)*

- [ ] **Index and template gallery polish** *(v1.7.8: escaped template card innerHTML, delegated listener replaces inline onclick, sanitized category class slug, fixed template creation 403/redirect)*
  - Replace decorative map-card preview art with real thumbnails or a compact rendered preview. *(deferred)*
  - ~~Convert clickable `div` cards into proper button/link structures.~~ *(v1.7.8: template cards already use `<button>`, replaced inline onclick with delegated listener)*
  - Preserve current search, sort, badges, and map metadata. *(preserved)*

- [ ] **Theme and UI cleanup** *(v1.7.4: narrowed MutationObserver filters; v1.7.8: extracted static inline styles to CSS classes in editor and index views)*
  - ~~Reduce noisy theme-detection console logging.~~ *(none found)*
  - ~~Avoid broad mutation observers where a narrower theme hook will work.~~ *(narrowed in v1.7.4)*
  - ~~Move repeated inline styles toward shared CSS classes.~~ *(v1.7.8: static inline styles extracted in editor.blade.php and index.blade.php; embed.blade.php JS-generated tooltip/legend spans and a few decorative spans remain inline)*

- [x] **LibreNMS hook and legacy view polish**
  - Align hook and compatibility views with LibreNMS Bootstrap button conventions.
  - Add safe external-link attributes and accessible labels to remaining map entry points.
  - Remove stale legacy rendering placeholder copy in favor of live-map entry points.
  - Replace remaining legacy map delete browser prompt with Bootstrap confirmation UI.

- [x] **Settings admin polish**
  - Replace browser alert and confirm flows with Bootstrap feedback and confirmation UI.
  - Keep settings preview rendering text-safe.
  - Make unavailable restore behavior explicit instead of presenting placeholder implementation copy.

- [x] **Index and editor confirmation polish**
  - Replace browser destructive-action prompts in active index and editor views with Bootstrap modals.
  - Preserve existing delete, restore, cleanup, resize, and undo-aware editor behavior after confirmation.
  - Route editor save errors through toast feedback instead of browser alerts.

- [ ] **Validation coverage** *(moved to v1.8.0)*

- [x] **Release readiness checklist**
  - Document the exact pre-release validation flow: Composer validate, PHPUnit, install CI, smoke install, changelog, tag, release notes.
  - Keep a short manual QA checklist for editor, embed, settings, install, and upgrade paths.
  - Define what qualifies as patch, minor, and major work for this plugin.

- [x] **Upgrade safety** *(v1.7.4: added "Upgrade safety" subsection to INSTALL.md)*
  - ~~Add explicit upgrade notes for users moving from older install methods.~~ *(v1.7.4)*
  - ~~Verify upgrade behavior when config files, output directories, or validation tables already exist.~~ *(v1.7.4)*
  - ~~Make failure messages actionable when Composer registration, route discovery, or database setup fails.~~ *(v1.7.4)*

### v1.7.0 - Performance, Authorization & Correctness Hardening ✅

These improvements shipped in v1.7.0. The bulk of the work was reliability, safety, and maintainability rather than new user-facing features.

- [x] **N+1 query elimination (Wave 1 + Wave 2)**
  - Eager-load map, node, link, and version relations instead of lazy loading.
  - Batch per-row caches (node data, device metadata, port names) so a 50-node map goes from ~400 queries down to ~10.
  - Covers MapCacheService eager loads, NodeDevice batch loading, and LinkPortName batch resolution.

- [x] **Admin-only authorization**
  - All 24 mutation endpoints require an admin via the `AdminCheck` trait (`requireAdmin()`).
  - Read endpoints remain open to any authenticated user.
  - `MapPolicy` and `NodePolicy` (dead authorization stubs) were removed in favor of the central trait.

- [x] **Editor accessibility pass**
  - `aria-label` and explicit `type="button"` on icon-only toolbar controls.
  - Focus-visible outlines preserved for keyboard navigation instead of overriding them.

- [x] **`mergeMapOptions` data-loss fix**
  - `mergeMapOptions` now preserves all option keys on save, not just `width`/`height`/`background`.
  - Custom options are no longer silently dropped when a map is updated.

- [x] **Release readiness checklist**
  - `RELEASE.md` created with the exact pre-release validation flow and manual QA checklist.

- [x] **Dead code removal**
  - Removed `SseStreamService`, `MapDataBuilder`, `MapPolicy`, `NodePolicy`, `test_hooks.php`, `debug_plugin_web.php`, and the root `map-poller.php`.
  - Canonical poller is now `bin/map-poller.php`.

- [x] **Demo traffic deterministic**
  - Demo mode now generates traffic from deterministic per-id sine waves instead of `rand()`, so traffic is smooth and jitter-free across renders.

- [x] **Correctness fixes**
  - `NodeService::deleteNode`: removed an `orWhere` that could delete the wrong rows.
  - `LinkService`: added input validation for link create/update.
  - `RenderController::import`: wrapped in a transaction so a failed import rolls back cleanly.
  - `MapCacheService`: eager-load fixes prevent stale/missing relation data.
  - `MapLinkController`: narrowed a broad `catch` that was swallowing real errors.

---

## Near Term

### v1.8.0 - Editor Workflow & Map Management

This release should make existing authoring workflows faster and less error-prone before adding major visualization features.

- [x] **Version Comparison** *(activated dormant backend v1.8.0)*
  - Registered version routes in `routes/web.php` and built editor UI: save-version button, version list modal, restore confirmation, compare diff view.
  - Fixed dormant backend bugs: `json_decode()` TypeError on cast array in compare/show/export, `captureSnapshot()` wrong field names (`database_id` → `device_id`, removed nonexistent `link.meta`), `restoreVersion()` now does true rollback with `forceCreate` preserving original IDs, `destroy()` now deletes only the selected version, `compareVersions()` returns flat lists.
  - Admin gates on all mutating endpoints. `SaveMapVersionRequest` with `strip_tags()` sanitization on store/autoSave.
  - *Highest ROI v1.8.0 item — major feature with minimal new backend work.*

- [ ] **Bulk Operations**
  - Select multiple nodes/links (rubber-band, shift-click, ctrl-click).
  - Bulk delete.
  - Bulk style changes (apply to all selected).
  - Preserve undo/redo support for bulk edits.

- [ ] **Operational diagnostics** *(backend already exists)*
  - `HealthController` already has health/ready/live/detailed/stats/metrics endpoints. The diagnostics screen is a UI layer on top.
  - Add admin-facing diagnostics Blade view: install status, route registration, writable paths, version metadata, LibreNMS compatibility checks.
  - Surface stale data, missing RRD files, and broken port associations.
  - Keep public health endpoints minimal and reserve sensitive detail for authenticated admins.

- [ ] **First-run onboarding**
  - Add a lightweight first-run state that helps users create or import their first useful map.
  - Offer demo/sample map creation only when it will not touch production data unexpectedly.
  - Link directly to install checks, docs, and troubleshooting from empty/error states.

- [ ] **Map Organization** *(tags only this cycle)*
  - Tags and filtering — store in existing `wmng_maps.options` JSON column, no migration needed.
  - Basic grouping for users with many maps.
  - *Defer favorites (requires user_id mapping table) to v1.9.0.*

- [ ] **Per-map default styling** *(NODE DEFAULT / LINK DEFAULT inheritance)*
  - Map-level default style that propagates to all nodes/links. Change one default, restyle the entire map.
  - Store as `default_node_style` / `default_link_style` in the map's `options` JSON column.
  - Merge defaults at render time (existing `mergeMapOptions` pattern).
  - *Addresses a feature operators miss from legacy weathermap tools (ranked #4 in competitive research).*

- [ ] **NOC wall / kiosk mode**
  - Fullscreen toggle with all UI chrome hidden.
  - Auto-cycling between maps on a timer.
  - The embed view is the foundation — add cycling and fullscreen.

- [ ] **Frontend modularization** *(maintainability prerequisite)*
  - Extract `editor.blade.php` (2047 lines) and `embed.blade.php` (1501 lines) inline JS into separate JS modules.
  - Split canvas rendering, interaction handling, live-update logic, and planned version-comparison/bulk-ops UI into modules.
  - Plain ES modules or IIFE namespaces — no build step required.
  - *Not a user-facing feature but a prerequisite for v1.9.0 and v2.0.0 feature delivery.*

- [ ] **Map Templates Gallery Refinement** *(stretch goal)*
  - Add or refine common topology templates: data center, WAN/MPLS, campus, branch office.
  - Template CRUD backend already exists (`MapTemplateController`). New templates are seeding work.
  - Accessibility and preview quality improvements mostly done per v1.7.8.

- [ ] **Validation coverage** *(moved from stale v1.6.x)*
  - Add screenshot checks for the index, editor, and embed view at representative viewport sizes.
  - Add an accessibility smoke test for obvious regressions.
  - Keep Composer, install, route, and version metadata checks green.
---

## Medium Term
### v1.9.0 - Discovery, Operator Integration & Advanced Data

This release closes the biggest gaps vs legacy weathermap tools and native LibreNMS maps: click-through navigation, RRD graph hover, nested maps, and LLDP/CDP auto-discovery.

- [ ] **Click-through navigation** *(CRITICAL — #1 feature gap vs legacy tools)*
  - Click a node to open the LibreNMS device page (`/device/{id}`). Nodes already store `device_id`.
  - Click a link to open the LibreNMS interface/port page (`/device/{id}/port/{port_id}`). Links already store port data.
  - Configurable: open in same tab, new tab, or hover tooltip with link.
  - *Every major competitor (legacy LibreNMS weathermap, PHP Weathermap, Zabbix, NagVis) has this. LOW effort — LibreNMS routes are stable, data already stored.*

- [ ] **RRD graph hover popups** *(HIGH impact — defining weathermap UX feature)*
  - Hover over a node/link to see an embedded RRD time-series graph, not just a text tooltip.
  - LibreNMS exposes graph image endpoints. Use a modern CSS/JS tooltip with embedded `<img>` or `<iframe>`.
  - *The most beloved feature of the classic PHP Weathermap (OverLib graphs). WeathermapNG's text-only tooltips are a step back.*

- [ ] **Nested maps / drill-down hierarchy** *(HIGH impact for multi-scale networks)*
  - Add `parent_map_id` foreign key to `wmng_maps` — a node can link to a sub-map instead of a device.
  - Click a "summary" node to navigate to a detailed sub-map (e.g., campus → building → rack).
  - Breadcrumb navigation showing the map hierarchy.
  - *Zabbix's killer feature for networks with multiple scales. Not previously in the roadmap.*

- [ ] **LLDP/CDP Auto-Discovery**
  - Query LibreNMS `links` table for actual topology (protocol field: lldp/xdp/cdp, remote_port_id, remote_device_id).
  - Create accurate node/link mapping from LLDP/CDP data.
  - Replace unreliable ifIndex-based matching.
  - Keep auto-discovery optional and reviewable before creating maps.
  - `AutoDiscoveryService` class already referenced in `MapController` constructor.

- [ ] **Device status-based node icons**
  - Node icons reflect device up/down/warning state (green/red/pulse), not just bandwidth on links.
  - LibreNMS device status is available. WeathermapNG nodes already store `device_id`.
  - *Partially covered by alerts integration, but the node icon itself should change, not just an overlay badge.*

- [ ] **Custom Metrics**
  - CPU and memory utilization on nodes (via LibreNMS `/health/processor` and `/health/mempool` endpoints).
  - Latency visualization (via LibreNMS services API — service_type=ping).
  - Packet loss indicators (via services API — service_ds with loss datasource).
  - Custom SNMP OID support if it can be implemented without reintroducing unreliable polling behavior.
  - *All data sources confirmed feasible via LibreNMS API research.*

- [ ] **Advanced Alerts Integration** *(backend substantially implemented)*
  - ~~LibreNMS alert overlay~~ *(done: `AlertService` fetches device+port alerts, embed view renders alert badges with severity coloring)*
  - ~~Alert severity indicators~~ *(done: critical=red, warning=yellow)*
  - Click-through to alert details — link to LibreNMS alert detail page using device_id and alert id.
  - Alert history on hover — query alerts without state filter, sort by timestamp.
  - *The roadmap previously underestimated how much alert work was already done.*

- [ ] **Large map performance**
  - Set practical performance budgets for node/link counts.
  - Profile canvas rendering, live update frequency, minimap updates, and flow animation cost.
  - Graceful degradation controls: viewport culling (only draw visible nodes/links), minimap redraw throttling (max once per 100ms during pan), auto-reduce particle density above link-count threshold, hide secondary labels below zoom threshold.
  - *Canvas 2D is appropriate for 1-300 nodes with these optimizations. No engine migration needed until v2.0.0.*

- [ ] **CLI tools for map management**
  - `lnms weathermapng:create-map`, `weathermapng:list-maps`, `weathermapng:export`, `weathermapng:discover`.
  - Follow `bin/map-poller.php` bootstrap pattern for LibreNMS environment loading.
  - Enables automation and headless map management.

- [ ] **External embedding API documentation**
  - Document the existing `/api/maps/{map}/json` and `/api/maps/{map}/live` endpoints as a public embedding API.
  - Add optional API token auth for non-session access (NOC dashboards, external systems).
  - *Enables Grafana iframe panels, custom NOC walls, and status page integration without iframe hacks.*

- [ ] **Map Organization favorites** *(deferred from v1.8.0)*
  - Per-user favorites (requires user_id mapping table or column).
  - Saved views per user.

### v1.10.0 - Historical Views & Export

- [ ] **Historical Playback**
  - Timeline scrubber.
  - Play/pause controls.
  - Speed adjustment.
  - Uses RRD time-range fetch via existing `RRDTool.php` class.

- [ ] **Export Formats**
  - ~~High-resolution PNG~~ *(done: exists in embed view via `canvas.toDataURL`)*
  - SVG export.
  - PDF export.
  - Visio/draw.io format if there is enough demand.

- [ ] **Config file import/export** *(portable map definitions)*
  - Export maps to JSON or legacy `.conf` text format for version control, scripting, and migration.
  - Import maps from config files (enables migration from legacy PHP Weathermap).
  - *Not visual export — enables portability and automation.*

- [ ] **Scheduled Reports**
  - Daily/weekly snapshots.
  - Email delivery.
  - PDF generation.
  - Scheduled PNG/PDF snapshot generation for NOC wall displays (extend `bin/map-poller.php`).

- [ ] **Grafana integration**
  - Document Grafana IFrame panel setup (works today with `allow_embedding=true`).
  - Explore JSON data source for native Grafana panel rendering weathermap data.

- [ ] **Map lifecycle webhooks**
  - Notify external systems (Slack, webhook) on map created/updated/deleted/version-saved.
  - Laravel events on Eloquent model events + webhook dispatcher.
  - *Extracted from v2.0.0 API v2 — achievable with current architecture.*

---

## Long Term

### v2.0.0 - Next Generation

These ideas are intentionally parked until the core product feels polished and maintainable.

- [ ] **Multi-user Editing**
  - Presence indicators.
  - Conflict resolution.
  - Edit locking.
  - Per-map access control / visibility (requires user_id or role mapping on maps).

- [ ] **Advanced Auto-Layout Algorithms**
  - Force-directed graphs.
  - Hierarchical layout.
  - Circular layout *(from NagVis competitive analysis)*.
  - Geographic placement from device data (LibreNMS locations table has lat/lng).
  - Semantic zooming: auto-clustering at low zoom, drill-down at high zoom *(matches Datadog/Kentik approach)*.

- [ ] **Plugin Ecosystem**
  - Custom data source plugins.
  - Visualization plugins.
  - Export format plugins.

- [ ] **API v2**
  - GraphQL support.
  - External data sources.

- [ ] **WebGL Rendering Engine** *(clarified: 2D graph scale, not 3D)*
  - Migrate from Canvas 2D to **Sigma.js + Graphology** for 10k+ node graph rendering.
  - Keep the data model (nodes, links, live updates) unchanged — SSE/polling layer is rendering-agnostic.
  - If 3D geographic positioning is needed, use **Three.js or deck.gl** separately.
  - *Triggered when maps regularly exceed 300 nodes. No migration needed before that.*

- [ ] **Mobile App**
  - iOS/Android companion app.
  - Push notifications for alerts.
  - Quick map viewing.
---

## Completed Features

### v1.7.0 ✅

- [x] N+1 query elimination (Wave 1 + Wave 2): eager-loads + batch caches, ~400 → ~10 queries on a 50-node map.
- [x] Admin-only authorization: all 24 mutation endpoints require admin via the `AdminCheck` trait.
- [x] Editor accessibility pass: `aria-label`, `type="button"`, focus-visible outlines.
- [x] `mergeMapOptions` data-loss fix: preserves all option keys, not just `width`/`height`/`background`.
- [x] Release readiness checklist: `RELEASE.md` created.
- [x] Dead code removal: `SseStreamService`, `MapDataBuilder`, `MapPolicy`, `NodePolicy`, `test_hooks.php`, `debug_plugin_web.php`, root `map-poller.php`; canonical poller is now `bin/map-poller.php`.
- [x] Demo traffic deterministic: per-id sine waves instead of `rand()`, smooth and jitter-free.
- [x] Correctness fixes: `NodeService::deleteNode` `orWhere`, `LinkService` validation, `RenderController` import transaction, `MapCacheService` eager loads, `MapLinkController` catch widening.

### v1.6.5

- [x] Quick install normalizes LibreNMS plugin registration after enablement.
- [x] Duplicate inactive `WeathermapNG` rows are cleaned from the LibreNMS `plugins` table.
- [x] Install docs explain expected `wmng_*` and JSON-column `utf8mb4_bin` warnings.
- [x] Legacy map list delete action uses Bootstrap confirmation instead of a browser prompt.

### v1.6.4

- [x] Composer path package registration during quick install.
- [x] Weekly/manual LibreNMS install smoke test in CI.
- [x] Mocked quick-install validation flow in CI.
- [x] Install documentation alignment checks.
- [x] Route verification during quick install.
- [x] Output directory configuration and readiness fixes.
- [x] PHP 8.2+ requirement alignment across Composer, runtime checks, and tests.
- [x] Health route exposure tightened: public liveness/readiness, authenticated details/metrics.
- [x] Version metadata guardrails with `VERSION`, `composer.json`, changelog, and release tag checks.
- [x] Release workflow validation for SemVer/version consistency.

### v1.6.2

- [x] Via points and via style for links: curved, angled, and straight path routing.
- [x] Interactive via point editing in modern editor: add, drag, delete.
- [x] Flow particles follow waypoint paths.
- [x] Catmull-Rom spline interpolation for curved via style.
- [x] Global `link_style` setting wired to embed and blade editors.
- [x] `show_bandwidth` and `show_percentages` settings honored in embed viewer.
- [x] Via style dropdown in blade editor link modal.
- [x] Version dynamically read from Composer metadata at the time.
- [x] Fixed modern editor `saveMap` endpoint.
- [x] Settings terminology aligned: orthogonal to angled.

### v1.6.1

- [x] Professional 3-panel editor layout: toolbox, canvas, properties sidebar.
- [x] Dark/light theme auto-detection matching LibreNMS.
- [x] Zoom and pan: mouse wheel plus middle-click panning.
- [x] Undo/redo system with 50-state history.
- [x] Keyboard shortcuts: Ctrl+S/Z/Y, Delete, arrow nudge, +/-/0 zoom, Esc.
- [x] Editor minimap with click-to-navigate.
- [x] Grid snapping toggle with visual overlay.
- [x] Smart spiral node placement.
- [x] Node boundary checking and canvas resize validation.
- [x] Node duplication and inline property editing.
- [x] Link mode with visual feedback.
- [x] Unsaved changes indicator (auto-save wiring removed in v1.7.0 — referenced non-existent elements).
- [x] Fixed link bandwidth utilization calculation accuracy.
- [x] Templates gallery with built-in map templates.
- [x] Main index page redesign with search, sort, improved cards, and empty state.
- [x] Demo mode with simulated traffic data.
- [x] Docker development environment.

### v1.6.0

- [x] Simplified data fetching: RRD-only, removed buggy API/SNMP fallback paths.
- [x] Fixed RRD path resolution to match LibreNMS naming.
- [x] Fixed utilization calculation for full-duplex links.
- [x] Device-type node icons: router, switch, server, firewall.
- [x] Enhanced link tooltips with bandwidth capacity.
- [x] Embed navigation bar with map title and edit link.
- [x] Proper service registration in ServiceProvider.
- [x] Admin-only settings authorization.
- [x] Fixed cache key collisions.
- [x] Disabled unreliable ifIndex-based auto-discovery pending LLDP/CDP rewrite.

### v1.5.x

- [x] Map versioning foundation: storage and services written, but **dormant** — editor UI removed in v1.7.0, routes not registered. Activate in v1.8.0 by registering routes and building UI (see VERSIONING.md).
- ~~Auto-save functionality~~ *(removed in v1.7.0 — dead code referencing non-existent elements)*
- [x] Demo mode for testing without devices.
- [x] Docker development environment.
- [x] Improved install scripts.
- [x] Removed heatmap due to pan/zoom sync issues.

### v1.4.x - v1.5.0

- [x] Security hardening: XSS prevention and input validation.
- [x] Authorization policies.
- [x] FormRequest validation.

### v1.3.x

- [x] Performance caching system.
- [x] E2E installation tests.

### v1.2.x

- [x] Map templates.
- [x] Accessibility improvements.
- [x] Toast notifications.
- [x] Loading states.
- [x] Web installer.

### v1.1.x

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
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues) - Feature requests and bugs

### Priority Areas

1. **Operator workflows**: Click-through navigation to LibreNMS device pages, RRD graph hover popups, device status-based node icons — the #1 gaps vs legacy weathermap tools.
2. **Editor productivity**: Version comparison (dormant backend ready to activate), bulk operations, per-map default styling.
3. **Map scale**: Nested maps / drill-down hierarchy for multi-scale networks, NOC wall/kiosk mode.
4. **Performance**: Large map rendering (viewport culling, particle throttling) and live update efficiency.
5. **Discovery**: LLDP/CDP topology, custom metrics (CPU/memory/latency), alerts click-through.
6. **Integration**: External embedding API, CLI tools, Grafana integration, config file import/export.

---

## Feedback

Have ideas for the roadmap?

- Open a [GitHub Issue](https://github.com/lance0/weathermapNG/issues)
- Join the [LibreNMS Community](https://community.librenms.org)
