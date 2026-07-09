# WeathermapNG Embed Viewer

## Overview

- Route: `plugin/WeathermapNG/embed/{map}`
- Renders a compact canvas-based viewer suitable for dashboards/iframes
- **Auth**: The embed view is a read endpoint open to all authenticated LibreNMS users; no admin role required. (Map editing and all 24 mutation endpoints are admin-only.)

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
- **Click navigation**: Click a node to open its device page; click a link to open port graphs
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
| `target` | `_blank` | Where node/link click-through opens: `_blank` (new tab) or `self` (same tab) |

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

## Tips

- Keep iframes sized to the map dimensions for crisp rendering
- Use `metric=sum` to visualize total link load (in+out)
- Use `nav=0` to disable pan/zoom for static dashboard widgets
- Use `minz=0.5&maxz=2` to restrict zoom range for embedded views
- Use `kiosk=1&target=self` in an iframe to keep navigation inside the parent dashboard
