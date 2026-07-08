# WeathermapNG Roadmap

This document outlines the development roadmap for WeathermapNG, a network visualization plugin for LibreNMS.

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

- [ ] **Responsive editor layout** *(v1.8.0: CSS media queries added — stacked sidebar below canvas on narrow screens, horizontal toolbox, flex-wrap topbar. Not yet browser-tested at target breakpoints.)*
  - ~~Replace fixed `calc(100vh - 120px)` assumptions with layout that works inside LibreNMS chrome.~~ *(v1.8.0: CSS added, needs browser verification)*
  - ~~Improve sidebar behavior on smaller screens.~~ *(v1.8.0: stacks below canvas with max-height scroll — CSS added, needs browser verification)*
  - Reduce nested scrolling where possible. *(partially addressed by stacking)*
  - Keep topbar actions usable when map names or counts are long. *(v1.8.0: flex-wrap — needs browser verification)*

- [ ] **Embed view responsive polish** *(v1.7.5: reduced-motion; v1.8.0: CSS flex-wrap nav/controls, responsive offsets, minimap hidden on very small screens. Not yet browser-tested at target breakpoints.)*
  - ~~Make the top navigation and control groups wrap, collapse, or reposition cleanly.~~ *(v1.8.0: CSS flex-wrap added — needs browser verification)*
  - Prevent controls, minimap, legend, and map content from overlapping on smaller embeds. *(v1.8.0: responsive offsets added — needs browser verification)*
  - ~~Add reduced-motion handling for flow animations.~~ *(v1.7.5)*

- [ ] **Index and template gallery polish** *(v1.8.0: escaped template card innerHTML, delegated listener replaces inline onclick, sanitized category class slug)*
  - Replace decorative map-card preview art with real thumbnails or a compact rendered preview. *(deferred)*
  - ~~Convert clickable `div` cards into proper button/link structures.~~ *(v1.8.0: template cards already use `<button>`, replaced inline onclick with delegated listener)*
  - Preserve current search, sort, badges, and map metadata. *(preserved)*

- [ ] **Theme and UI cleanup** *(v1.7.4: narrowed MutationObserver filters; v1.8.0: extracted embed.blade.php inline styles to CSS classes)*
  - ~~Reduce noisy theme-detection console logging.~~ *(none found)*
  - ~~Avoid broad mutation observers where a narrower theme hook will work.~~ *(narrowed in v1.7.4)*
  - Move repeated inline styles toward shared CSS classes. *(embed.blade.php done in v1.8.0; editor.blade.php and index.blade.php deferred)*

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

- [ ] **Validation coverage**
  - Add screenshot checks for the index, editor, and embed view at representative viewport sizes.
  - Add an accessibility smoke test for obvious regressions.
  - Keep Composer, install, route, and version metadata checks green.

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

- [ ] **Bulk Operations**
  - Select multiple nodes/links.
  - Bulk delete.
  - Bulk style changes.
  - Preserve undo/redo support for bulk edits.

- [ ] **Map Templates Gallery Refinement**
  - Keep the existing templates work.
  - Improve template card accessibility and preview quality.
  - Add or refine common topology templates:
    - Data center layout
    - WAN/MPLS network
    - Campus network
    - Simple branch office

- [ ] **Version Comparison**
  - Visual diff between map versions.
  - Show node/link additions, removals, and style/config changes.
  - Restore or compare without losing current version history behavior.

- [ ] **Map Organization**
  - Tags and filtering.
  - Favorites.
  - Basic grouping for users with many maps.

- [ ] **First-run onboarding**
  - Add a lightweight first-run state that helps users create or import their first useful map.
  - Offer demo/sample map creation only when it will not touch production data unexpectedly.
  - Link directly to install checks, docs, and troubleshooting from empty/error states.

- [ ] **Operational diagnostics**
  - Add an admin-facing diagnostics screen for install status, route registration, writable paths, version metadata, and LibreNMS compatibility checks.
  - Surface stale data, missing RRD files, and broken port associations in a way users can act on.
  - Keep public health endpoints minimal and reserve sensitive detail for authenticated admins.

---

## Medium Term

### v1.9.0 - Discovery & Advanced Data

- [ ] **LLDP/CDP Auto-Discovery**
  - Query LibreNMS `links` table for actual topology.
  - Create accurate node/link mapping from LLDP/CDP data.
  - Replace unreliable ifIndex-based matching.
  - Keep auto-discovery optional and reviewable before creating maps.

- [ ] **Custom Metrics**
  - CPU and memory utilization on nodes.
  - Latency visualization.
  - Packet loss indicators.
  - Custom SNMP OID support if it can be implemented without reintroducing unreliable polling behavior.

- [ ] **Advanced Alerts Integration**
  - LibreNMS alert overlay.
  - Alert severity indicators.
  - Click-through to alert details.
  - Alert history on hover.

- [ ] **Large map performance**
  - Set practical performance budgets for node/link counts.
  - Profile canvas rendering, live update frequency, minimap updates, and flow animation cost.
  - Add graceful degradation controls for very large maps: reduced particles, lower update rate, or simplified labels.

### v1.10.0 - Historical Views & Export

- [ ] **Historical Playback**
  - Timeline scrubber.
  - Play/pause controls.
  - Speed adjustment.

- [ ] **Export Formats**
  - High-resolution PNG.
  - SVG export.
  - PDF export.
  - Visio/draw.io format if there is enough demand.

- [ ] **Scheduled Reports**
  - Daily/weekly snapshots.
  - Email delivery.
  - PDF generation.

---

## Long Term

### v2.0.0 - Next Generation

These ideas are intentionally parked until the core product feels polished and maintainable.

- [ ] **Multi-user Editing**
  - Presence indicators.
  - Conflict resolution.
  - Edit locking.

- [ ] **Advanced Auto-Layout Algorithms**
  - Force-directed graphs.
  - Hierarchical layout.
  - Geographic placement from device data.

- [ ] **Plugin Ecosystem**
  - Custom data source plugins.
  - Visualization plugins.
  - Export format plugins.

- [ ] **API v2**
  - GraphQL support.
  - Webhook integrations.
  - External data sources.

- [ ] **3D Visualization**
  - Optional WebGL rendering.
  - Geographic positioning.
  - Building/floor layouts.

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

- [x] Map versioning foundation: storage and services only (editor UI removed in v1.7.0; routes not registered — see VERSIONING.md).
- [x] Auto-save functionality (wiring removed in v1.7.0 — dead code referencing non-existent elements).
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

1. **UX polish**: Editor, embed controls, responsive layout, accessible interactions.
2. **Testing**: Install, UI smoke, accessibility smoke, and large-map coverage.
3. **Documentation**: Install clarity, user guides, and upgrade/release notes.
4. **Performance**: Large map rendering and live update efficiency.
5. **Discovery**: LLDP/CDP topology only after the current UI and install experience are stable.

---

## Feedback

Have ideas for the roadmap?

- Open a [GitHub Issue](https://github.com/lance0/weathermapNG/issues)
- Join the [LibreNMS Community](https://community.librenms.org)
