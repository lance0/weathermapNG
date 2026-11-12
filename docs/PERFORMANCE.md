# WeathermapNG Performance

What a map view costs, which optimizations already ship, and how to measure a
large map before changing rendering code.

| Task | Where |
|------|-------|
| Understand the shipped optimizations | [Current behavior](#current-behavior) |
| Set expectations by map size | [Budgets](#budgets) |
| Measure a slow map | [Benchmark method](#benchmark-method) · [Profiling checklist](#profiling-checklist) |
| Check layout and render regressions | [Visual checks](#visual-checks) |
| Inspect server-side caching | [Data and caching](#data-and-caching) |

## Current behavior

These optimizations are in the current code and run without configuration.

### Viewport culling (v1.12.0)

The embed view (`renderMap`, `renderOverlay`) and the editor (`renderEditor`)
compute the visible world rectangle once per frame and skip work outside it.
Nodes are drawn when their center is inside the rectangle plus a 24 px margin.
Links are drawn unless their path bounding box misses the rectangle, so links
whose endpoints are off-screen still render when the segment crosses the view.
The box includes `style.via_points` and, for curved links, the Catmull-Rom
control points.

Draw cost therefore tracks the number of visible features, not the map total.
Panning a dense map still rebuilds the visible set each frame, so the win grows
with map size and shrinks when most of the map is visible.

### Canvas layer split (v1.11.0)

The embed view draws onto two canvases. The static layer (nodes, links, labels)
is redrawn only when `staticDirty` is set. The overlay layer (flow particles,
animated dashes, debug marks) is redrawn each animation frame. Pan, zoom, a
metric change, or a live payload marks the static layer dirty; particle motion
does not.

### Animation pauses

The requestAnimationFrame loop stops when the map has no traffic on any link
and no down node to pulse. New traffic restarts it. It also stops or pauses:

- when the browser tab is hidden (`visibilitychange` cancels RAF, SSE, and
  polling, and resumes them when the tab returns)
- when `prefers-reduced-motion` is set and flow animation is off
- when the user turns flow animation off with the Flow control

### Constant-time lookups

- `nodeById` is a `Map` rebuilt whenever the node list changes, replacing
  per-frame `Array.find` scans in link rendering.
- The node→links index is built once per payload in `NodeDataService`, so
  traffic aggregation is O(nodes + links), not O(nodes × links).
- The particle budget is memoized by link count and reused across frames.

### Particle budget

The map-wide total stays at or below 1500 particles. Density scales with the
map's link count:

| Link count | Density multiplier | Behavior |
|------------|--------------------|----------|
| 1-150 | 1.0 | Full density |
| 151-300 | 0.5 | Half density |
| 301-1500 | 0.25 | Quarter density, per-link allowance is `1500 / links` |
| 1501+ | 0 | Flow animation skipped rather than exceeding the cap |

The Flow, Particle Density, and Particle Speed controls apply on top of this
budget.

### Zoom-based label hiding

Below 0.6 zoom the embed hides secondary node text (the traffic total and the
CPU/MEM line) while keeping node labels. Link labels are not affected. This
keeps dense maps readable without removing identity.

### Batched server queries

The live, embed, and SSE paths preload everything the serializer reads:

- `RenderController` calls `$map->load(['nodes', 'links'])` at entry.
- `NodeDataService::preloadForMap()` primes device records, port names, and
  RRD port/device metadata in one pass.
- `Node::preloadDevices()`, `Link::preloadPortNames()`, and the request-local
  `RrdDataService` caches then answer per-node and per-link reads without
  issuing a query each.

A recorded run on a 50-node / 50-link map dropped the live/embed/SSE path from
roughly 400 queries to roughly 10. That number is a reference point from one
fixture, not a guarantee; the shape of the win is that query count stays
roughly constant as node and link counts grow.

## Data and caching

There is no dedicated cache service. Caching is inline `Cache::remember` in the
services that fetch data, each with a `weathermapng.*` key scoped by resource
id, so writes invalidate the relevant data without wildcard clearing:

| Service | Key | Scope |
|---------|-----|-------|
| `PortUtilService` | `weathermapng.port.traffic.{id}` | Per-port traffic |
| `PortUtilService` | `weathermapng.device.{id}.aggregate` | Per-device aggregate |
| `DevicePortLookup` | `weathermapng.device_ports.{id}`, `weathermapng.device_search.{hash}`, `weathermapng.all_devices` | Lookup data |
| `RrdDataService` | Request-local only | RRD file metadata |

Persistent entries use `weathermapng.cache_ttl` (default 300 seconds).
`RrdDataService` caches reset per request so polls never read stale RRD
metadata. When changing map, node, link, device lookup, or live traffic
behavior, verify that cache keys do not collide and that writes invalidate what
users expect to change.

## RRD and live data

LibreNMS RRD data is the traffic source of truth. Avoid reintroducing API or
SNMP fallback paths unless they are designed, tested, and documented.

Operational checks:

- Port associations resolve to readable RRD files.
- Missing RRD files degrade gracefully.
- Demo mode separates rendering issues from live data issues.
- SSE does not expose detail beyond the authenticated map payload.

## Budgets

Starting targets for a desktop browser against a local LibreNMS. They are
measurement goals, not hard limits.

| Map size | Nodes | Links | Expected behavior |
|----------|-------|-------|-------------------|
| Small | 1-25 | 1-50 | Full labels, minimap, and flow animation |
| Medium | 25-100 | 50-250 | Smooth pan/zoom; culling active outside the viewport; particle density drops after 150 links |
| Large | 100+ | 250+ | Culling active; reduced particles; secondary labels appear at 0.6 zoom and above; map-wide particle cap 1500 |

Editor and embed share the culling and label behavior but draw different
chrome. Measure both. Above 300 nodes, Canvas 2D remains the engine until the
WebGL migration described in
[ROADMAP.md](../ROADMAP.md#long-term); do not document a node limit
without repeatable measurements.

## Benchmark method

1. Build three fixtures: 25 nodes / 50 links, 100 / 250, and 300 / 600. Use the
   built-in templates or the editor; keep backgrounds and label settings
   identical.
2. Load each map twice and measure the second load so caches are warm.
3. Measure the server payload with an authenticated request. A session cookie
   or the embed token both work:
   ```bash
   curl -sS -o /dev/null -w '%{time_total}\n' \
     'https://librenms.example/plugin/WeathermapNG/api/maps/1/live'
   ```
   Repeat five times and compare the median, not a single run.
4. Record a 5-second pan and zoom in the browser Performance panel. Check frame
   times during pan, not only the idle frame rate.
5. Repeat with `?graphs=0`, `?metrics=0`, and `?sse=0&interval=60` to isolate
   graph hover, metric queries, and the transport.
6. Add `?debugDots=2` to an embed URL for an on-screen panel with the current
   transform, particle cache size, RAF state, and `nodeById` size.
   `?debugDots=1` draws a cross at each particle position.
7. Compare the result with the previous revision on the same host and browser.

## Profiling checklist

Run this before and after a rendering or data-path change:

1. Test small, medium, and large fixtures from the benchmark method.
2. Test the editor and the embed view; they have separate render code.
3. Test dark and light themes if canvas styling changed.
4. Test demo mode and real RRD-backed links.
5. Check live payload sizes and the SSE/poll interval.
6. Watch browser CPU during flow animation and during pan/zoom.
7. Confirm culling still draws links that cross the viewport (including via
   points and curved links).
8. Run `just test` and `just test-js`; run `just test-integration` against the
   dev stack for route or payload changes.
9. Run the visual checks below.

Per-endpoint traffic counters can be logged for diagnosis by setting
`WEATHERMAPNG_DEBUG=true` in the LibreNMS `.env`.

## Visual checks

Screenshots catch layout regressions that timing measurements miss. The
comparison renders the index, editor, and embed views at 480×800, 768×1024,
and 1920×1080.

```bash
# Write fresh PNGs to output/screenshots
just visual http://localhost:8000

# Compare against tests/screenshots/*.png (fails on drift)
just visual-compare http://localhost:8000

# Regenerate baselines; review the PNGs before committing
just visual-baseline http://localhost:8000
```

`just visual` uses a Chromium-family browser and fails when captures are
degenerate (identical stubs for every page). `just visual-compare` needs
`playwright-core` on `NODE_PATH`; CI installs it beside the browser. The
`embed-auth` CI job runs the comparison and uploads `output/screenshot-diff`
on failure. See [CONTRIBUTING.md](../CONTRIBUTING.md) for the full test flow.
