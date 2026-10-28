# WeathermapNG Embed Viewer

## Overview

- Route: `plugin/WeathermapNG/embed/{map}` (session) — token-gated variant: same path with `?token=` (see [Embed API Token Auth](#embed-api-token-auth))
- Renders a compact canvas-based viewer suitable for dashboards/iframes
- **Auth**: The embed view is a read endpoint open to all authenticated LibreNMS users; no admin role required. (Map editing and all 22 mutation endpoints are admin-only.)

## Features

- Metric selector: `percent`, `in`, `out`, `sum`
- Legend showing threshold bands and current metric
- Export PNG button (client-side)
- Node status badges and link utilization labels
- Alert badges: red/yellow badges on nodes and links for active LibreNMS alerts
- **Via points**: Links with `style.via_points` render as multi-segment paths; `style.via_style` controls curve style (straight/angled/curved)
- **Flow particles**: Animated traffic particles follow waypoint paths
- **Pan & zoom**: Mouse-wheel zoom, drag-to-pan, +/-/Reset buttons, double-click zoom
- **Hover tooltips**: Node In/Out/Sum traffic; link utilization, bandwidth
- **RRD graph hover**: Hover a node or link for 300ms to show an inline LibreNMS RRD time-series graph image (device traffic for nodes, port traffic for links). Disabled in kiosk mode; use `?graphs=0` to disable.
- **Drill-down**: Nodes with a `sub_map_id` navigate to the referenced sub-map when clicked; a breadcrumb bar shows the map hierarchy
- **Kiosk / NOC wall mode**: Hide all chrome, auto-cycle maps, and control click-through target (see below)

## Query Parameters

| Param | Default | Description |
|-------|---------|-------------|
| `metric` | `percent` | Metric to display: `percent`, `in`, `out`, `sum` |
| `sse` | `1` | Enable Server-Sent Events (`0` to force polling) |
| `interval` | `60` | Polling interval in seconds (when SSE disabled) |
| `max` | `60` | SSE connection duration in seconds |
| `scale` | `bits` | Display units: `bits` (Gb/s, Mb/s) or `bytes` (GB/s, MB/s) |
| `nav` | `1` | Enable pan/zoom controls (`0` to disable) |
| `minz` | `0.5` | Minimum zoom level |
| `maxz` | `4` | Maximum zoom level |
| `kiosk` | `0` | Enable NOC wall mode: hide nav, controls, legend, minimap, and status bar |
| `cycle` | *(none)* | When `kiosk=1`, rotate to the next map every N seconds (minimum 5) |
| `graphs` | `1` | Show RRD graph hover popups (`0` to disable; auto-disabled in kiosk mode) |
| `metrics` | `1` | Show CPU/memory utilization overlay on nodes (`0` to disable; controlled by `show_node_metrics` config) |
| `target` | `_blank` | Where node/link click-through opens: `_blank` (new tab) or `self` (same tab) |
| `debugDots` | `0` | Diagnostics: `1` draws a magenta cross at each particle position with a transform readout; `2` shows an on-screen panel with full render-pipeline state. Off by default, safe in production |

## Live Data

- Polls `/plugin/WeathermapNG/api/maps/{id}/live` on interval
- If `sse=1`, subscribes to `/plugin/WeathermapNG/api/maps/{id}/sse`
- Colors and labels update without full redraw where possible
- Live payload includes `alerts.nodes` and `alerts.links` with `{ count, severity }`

## Settings (via LibreNMS config)

| Config Key | Default | Description |
|------------|---------|-------------|
| `weathermapng.link_style` | `straight` | Default via_style for links: `straight`, `angled`, `curved` |
| `weathermapng.show_bandwidth` | `true` | Show bandwidth labels (Gb/s, Mb/s) |
| `weathermapng.show_percentages` | `true` | Show utilization percentage labels |
| `weathermapng.show_node_metrics` | `true` | Show CPU/memory utilization overlay on nodes |
| `weathermapng.thresholds` | `[50,80,95]` | Utilization thresholds for green/yellow/red coloring |

## Export

- PNG export composites background + overlay into a downloadable image

## Kiosk / NOC Wall Mode

Use `?kiosk=1` to turn the embed view into a clean wall display. All navigation, controls, legend, minimap, and status chrome is hidden. Move the mouse or press any key to briefly reveal chrome; press `Esc` to toggle chrome on/off. A small **Exit Kiosk** button appears in the bottom-right corner.

Auto-cycle between maps with `?kiosk=1&cycle=30` (cycles every 30 seconds). Maps are visited in alphabetical order by name and the cycle URL preserves kiosk settings.

### Examples

- NOC wall rotating maps every 30 seconds:  
  `plugin/WeathermapNG/embed/1?kiosk=1&cycle=30`
- Static kiosk where clicks stay in the same tab:  
  `plugin/WeathermapNG/embed/1?kiosk=1&target=self`
- Static wall, no cycling:  
  `plugin/WeathermapNG/embed/1?kiosk=1`

## Nested Maps (Drill-Down)

Maps can be arranged in a hierarchy using `parent_map_id`. In the embed view, nodes with a `sub_map_id` in their metadata act as drill-down points: clicking them navigates to the referenced sub-map. All URL query parameters (`kiosk`, `scale`, `metric`, `target`, etc.) are preserved when drilling down.

A breadcrumb bar appears at the top of the viewer when the current map has a parent. Each crumb in the root→current chain is clickable except the last (the current map). The breadcrumb bar is hidden when the map has no parent chain.

## Embed API Token Auth

For external systems that can't hold a browser session — Grafana panels, NOC walls, status pages, scripts — set an embed token and append it to the URL:

```bash
# LibreNMS .env
WEATHERMAPNG_EMBED_TOKEN=<random-secret>
```

```html
<iframe src="https://your-librenms/plugin/WeathermapNG/embed/1?token=<random-secret>"></iframe>
```

The same token works as an `Authorization: Bearer` header on the data endpoints, which is preferable for scripted access (`?token=` can leak via logs and referrers):

```bash
curl -H "Authorization: Bearer <random-secret>" \
  https://your-librenms/plugin/WeathermapNG/api/maps/1/json
```

These endpoints accept either a LibreNMS session or an explicitly supplied token: `embed/{map}`, `api/maps/{map}/json`, `api/maps/{map}/live`, `api/maps/{map}/sse`. Logged-in users do not need an embed token. Without a token, normal LibreNMS login, account-enabled, and two-factor checks apply. An explicit token returns 403 when token auth is unconfigured, or 401 when it is invalid; anonymous requests without a token require login. Bearer tokens take precedence over query tokens.

The viewer forwards its authenticated token to JSON, live updates, SSE, drill-down, kiosk cycling, and kiosk exit. Token responses disable caching and referrers. The token grants access only to these map read endpoints; LibreNMS device pages and RRD graphs still require a LibreNMS session.

## Grafana IFrame Panel

WeathermapNG works in Grafana today:

1. Add a **Text** panel and paste the iframe (with `?token=` if using token auth):
   ```html
   <iframe src="https://your-librenms/plugin/WeathermapNG/embed/1?kiosk=1&nav=1"
           width="100%" height="500" frameborder="0"></iframe>
   ```
2. Or point an **Image** panel at a periodically-refreshed snapshot URL if you generate static PNGs.

Use `kiosk=1` to strip LibreNMS chrome inside the panel; `target=self` keeps drill-down navigation inside the iframe. Grafana's Text panels render raw HTML, so the iframe approach needs no plugin.

## Tips

- Keep iframes sized to the map dimensions for crisp rendering
- Use `metric=sum` to visualize total link load (in+out)
- Use `nav=0` to disable pan/zoom for static dashboard widgets
- Use `minz=0.5&maxz=2` to restrict zoom range for embedded views
- Use `kiosk=1&target=self` in an iframe to keep navigation inside the parent dashboard
- Keep embed tokens in the `Authorization` header rather than `?token=` where the consumer supports it
