# WeathermapNG Embed Viewer

## Overview

- Route: `plugin/WeathermapNG/embed/{map}`
- Renders a compact canvas-based viewer suitable for dashboards/iframes
- Supports live updates via polling or optional SSE

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

## Tips

- Keep iframes sized to the map dimensions for crisp rendering
- Use `metric=sum` to visualize total link load (in+out)
- Use `nav=0` to disable pan/zoom for static dashboard widgets
- Use `minz=0.5&maxz=2` to restrict zoom range for embedded views
