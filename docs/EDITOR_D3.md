WeathermapNG D3 Editor
======================

Overview
- Route: `plugin/WeathermapNG/editor-d3/{map}`
- Loads map JSON from: `plugin/WeathermapNG/api/maps/{map}/json`
- Saves full state to: `POST plugin/WeathermapNG/api/maps/{map}/save`
- Per-item CRUD:
  - Create node: `POST plugin/WeathermapNG/map/{map}/node`
  - Update node: `PATCH plugin/WeathermapNG/map/{map}/node/{node}`
  - Delete node: `DELETE plugin/WeathermapNG/map/{map}/node/{node}`
  - Create link: `POST plugin/WeathermapNG/map/{map}/link`
  - Update link: `PATCH plugin/WeathermapNG/map/{map}/link/{link}`
  - Delete link: `DELETE plugin/WeathermapNG/map/{map}/link/{link}`

Key Features
- Tools: select, pan, add-node, add-link, add-text (WIP), delete
- Link creation: click source node then destination (preview line shows while selecting)
- Properties panel:
  - Nodes: label, device, position, icon (persisted as `node.meta.icon`)
  - Links: label (stored in `link.style.label`), bandwidth with unit inference, port selection (per-endpoint)
- Link labels render on-canvas (respect Labels toggle) with background for readability
- Device port lookups cached client-side per device to reduce requests

Selection & Editing
- Box-select with drag marquee; hold Shift to add/remove from selection
- Bulk link edit mode when multiple links selected (bandwidth, style)
- Inline validation disables Apply until required fields are valid
- Debounced auto-save of node positions when dragging

Styling & Sliders
- Label Size slider controls node/link font-size
- Node Size slider adjusts radius, icon scale, and label offset
- Link Width slider sets stroke width for selected or all links
- Labels use white text with black outline for readability

Backgrounds & Geo
- Preset backgrounds: grid, dots, hex, gradients, or TopoJSON (world/us)
- Geo controls: projection, scale, offset sliders; center-on-click
- Selections persist in `map.options` (no DB schema changes required)

Device/Port Search
- Device autocomplete with server-side filter (`?q`) and keyboard nav
- Port search debounced; dropdown appears after 2+ chars; keyboard nav

Export
- Export SVG and Export PNG from toolbar
- JSON Export/Import for map data

Live Preview
- Toggle to poll `/plugin/WeathermapNG/api/maps/{map}/live` every 5s
- Links recolor based on selected metric or computed percent
- Nodes/links can display alert badges based on LibreNMS active alerts

Auto-Discovery
- Modal with filters: min degree and OS
- Calls `POST /plugin/WeathermapNG/map/{map}/autodiscover` and reloads map

Data Mapping
- Nodes from JSON: `meta.icon` → `node.icon`; on save/patch `meta.icon` is preserved
- Links from JSON: `{src, dst}` → `{source, target}` for D3
- Links on save: `{source, target}` → `{src_node_id, dst_node_id}` for API
- Link label persisted in `link.style.label` (no DB schema changes)

Tips
- Hold Shift to multi-select; arrow keys nudge selected nodes (Shift for 10px)
- Grid and snap toggles for layout
- Export/Import JSON available from the toolbar
