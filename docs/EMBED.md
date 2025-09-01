WeathermapNG Embed Viewer
=========================

Overview
- Route: `plugin/WeathermapNG/embed/{map}`
- Renders a compact canvas-based viewer suitable for dashboards/iframes
- Supports live updates via polling or optional SSE

Features
- Metric selector: `percent`, `in`, `out`, `sum`
- Legend showing threshold bands and current metric
- Export PNG button (client-side)
- Node status badges (if provided) and link utilization labels
- Alert badges: red/yellow badges on nodes and links for active LibreNMS alerts

Query Parameters
- `metric`: which metric to display (`percent` default)
- `sse=1`: enable Server-Sent Events if supported by backend
- `w`, `h`: override canvas width/height (pixels)

Live Data
- Polls `/plugin/WeathermapNG/api/maps/{id}/live` on interval
- If `sse=1`, subscribes to `/plugin/WeathermapNG/api/maps/{id}/sse`
- Colors and labels update without full redraw where possible
- Live payload includes `alerts.nodes` and `alerts.links` with `{ count, severity }`

Export
- PNG export composites background + overlay into a downloadable image
- SVG export is a future enhancement

Tips
- Keep iframes sized to the map dimensions for crisp rendering
- Use `metric=sum` to visualize total link load (in+out)
