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

Data Mapping
- Nodes from JSON: `meta.icon` → `node.icon`; on save/patch `meta.icon` is preserved
- Links from JSON: `{src, dst}` → `{source, target}` for D3
- Links on save: `{source, target}` → `{src_node_id, dst_node_id}` for API
- Link label persisted in `link.style.label` (no DB schema changes)

Tips
- Hold Shift to multi-select; arrow keys nudge selected nodes (Shift for 10px)
- Grid and snap toggles for layout
- Export/Import JSON available from the toolbar

