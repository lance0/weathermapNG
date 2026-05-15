# WeathermapNG Performance Notes

This document describes current performance considerations and the areas that should be measured before adding large-map features.

## Current Performance Model

WeathermapNG renders maps in browser canvas views and reads live traffic from LibreNMS data sources. The main cost centers are:

- Map JSON size: number of nodes, links, labels, and style metadata
- Live update frequency: polling or Server-Sent Events payload cadence
- RRD lookups for linked ports
- Canvas redraw cost in the editor and embed viewer
- Flow particle animation cost
- Minimap redraw cost
- Label density on large maps

## Cache Service

`src/Services/MapCacheService.php` provides cache helpers for map data. Cache keys are intentionally scoped by map/resource so writes can invalidate the relevant map data without relying on unsafe wildcard cache clearing.

Representative cache areas:

- Map list
- Single map detail
- Map nodes
- Map links
- Editor map data
- Device lookup data
- Port metadata and traffic data

When changing map, node, link, device lookup, or live traffic behavior, verify that cache keys do not collide and that updates invalidate the data users expect to change.

## RRD And Live Data

Live traffic should use LibreNMS RRD data as the source of truth. Avoid reintroducing API or SNMP fallback behavior unless it is explicitly designed, tested, and documented.

Operational checks:

- Port associations should resolve to readable RRD files.
- Missing RRD files should degrade gracefully.
- Demo mode should be used to separate rendering/UI issues from live data issues.
- SSE should not expose sensitive detail beyond the authenticated map data flow.

## Browser Rendering

The editor and embed viewer should remain responsive under normal map sizes.

Before increasing visual complexity, test:

- Initial render time
- Pan and zoom smoothness
- Drag latency in the editor
- Flow animation frame cost
- Minimap update cost
- Tooltip hit-testing cost
- Label clutter at common zoom levels

## Suggested Budgets

These are starting budgets, not guarantees:

| Map Size | Nodes | Links | Expected Behavior |
|----------|-------|-------|-------------------|
| Small | 1-25 | 1-50 | Full labels, minimap, and flow animation should feel smooth |
| Medium | 25-100 | 50-250 | Smooth pan/zoom, with animation cost monitored |
| Large | 100+ | 250+ | Consider reduced particles, simplified labels, or lower update cadence |

Large-map behavior should be measured before promising specific limits.

## Degradation Controls To Consider

Future large-map work should consider:

- Disable or reduce flow particles above a link-count threshold.
- Hide secondary labels until zoomed in.
- Throttle minimap redraws during drag/pan.
- Batch live updates when payloads arrive quickly.
- Avoid expensive per-frame DOM reads or layout calculations.
- Provide per-map rendering settings for dense dashboards.

## Database Considerations

WeathermapNG stores maps, nodes, links, templates, and versions in `wmng_*` tables inside the LibreNMS database.

Useful checks:

```sql
SHOW TABLES LIKE 'wmng_%';
SELECT COUNT(*) FROM wmng_maps;
SELECT COUNT(*) FROM wmng_nodes;
SELECT COUNT(*) FROM wmng_links;
```

Database changes should be handled through the supported plugin setup/upgrade path in `database/setup.php` unless a future release changes that install contract.

## Profiling Checklist

When working on performance-sensitive changes:

1. Test at small, medium, and large map sizes.
2. Test both editor and embed views.
3. Test dark and light themes if layout or canvas styling changed.
4. Test with demo mode and with real RRD-backed links.
5. Check network payload sizes for live updates.
6. Watch browser CPU during flow animation.
7. Run the PHP test suite and route/install checks.

## Roadmap Link

Large-map performance is tracked in [ROADMAP.md](ROADMAP.md) under the medium-term roadmap. Avoid documenting hard limits until they are backed by repeatable measurements.
