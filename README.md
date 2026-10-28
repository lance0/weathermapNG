# WeathermapNG

<p align="center">
  <img src="wmng.png" alt="WeathermapNG live map: nodes with traffic labels, colored links, minimap and legend" width="900">
</p>

**Network weathermap plugin for LibreNMS — real-time topology visualization with animated traffic flow.**

WeathermapNG renders your LibreNMS topology as live weathermaps: RRD-backed
per-link traffic, CPU/memory overlays, alert badges, and an embeddable viewer
with Server-Sent Events. Design maps in a 3-panel editor with undo/redo,
auto-discovery from LLDP/CDP, templates, versioning, and bulk operations.

[Documentation](docs/README.md) · [Install](#install) ·
[Releases](https://github.com/lance0/weathermapNG/releases) · [Changelog](CHANGELOG.md)

![LibreNMS latest](https://img.shields.io/badge/LibreNMS-latest-88A0CE)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![Version 1.15.0](https://img.shields.io/badge/version-1.15.0-0078D4)

## Install

Use the [v1.15.0 installation and recovery guide](docs/INSTALL.md) for stable
native/Docker installation, configuration, upgrades, diagnostics, and full backups.
Following `main` is an explicit development alternative documented there.

The common installer manages `composer config --global repositories.weathermapng`
and LibreNMS `plugin:add librenms/weathermapng` registration under the runtime
user. Its final read-only `verify-deployment.php` report checks actual runtime
registration. After installation, log in to `/plugin/WeathermapNG` and verify
editor/embed access too.

## What it does

- **Live traffic weathermaps**: RRD-based bandwidth per link, animated flow, percent/in/out/sum metrics
- **CPU & memory overlay**: per-node utilization with warning thresholds
- **Alert integration**: severity-colored badges with click-through to LibreNMS alerts
- **Nested maps / drill-down**: click a summary node to navigate to a detailed sub-map (campus → building → rack); breadcrumb bar tracks the hierarchy
- **3-panel editor**: toolbox, canvas, properties — undo/redo, grid snap, zoom/pan/minimap, keyboard shortcuts
- **Bulk operations**: marquee multi-select, group drag, bulk delete with undo
- **LLDP/CDP auto-discovery**: seed topology from LibreNMS neighbor data
- **Templates, tags & per-map styles**: built-in Data Center, WAN/MPLS, Campus, and Branch Office topologies; tag filtering; default node/link styling
- **Legacy Weathermap import**: read PHP Weathermap-style `.conf` files (round-trip export included)
- **Map versioning**: snapshot, history, restore, compare, delete
- **Embed viewer**: iframes, kiosk/NOC wall with map cycling, RRD graph hover
- **Accessibility**: ARIA labels on icon-only controls, keyboard focus visibility, form labels
- **Render diagnostics**: `?debugDots=1` overlays particle positions; `?debugDots=2` adds a full render-pipeline panel
- **Import/export**: JSON round-trip for backup and sharing
- **Admin-only mutations**: all write endpoints require admin; reads are open to authenticated users

<p align="center">
  <img src="wmng-editor.png" alt="WeathermapNG editor: 3-panel layout with toolbox, canvas, and properties sidebar" width="900">
</p>

## Tasks

| Task | Guide |
|------|-------|
| Install, update, or troubleshoot | [Installation](docs/INSTALL.md) · [Updating](#updating) · [Deployment](docs/DEPLOYMENT.md) |
| Embed a map or run a NOC wall | [Embed Viewer](docs/EMBED.md) |
| Call a route or script against the API | [API reference](docs/API.md) |
| Tune large-map performance | [Performance notes](docs/PERFORMANCE.md) |
| Diagnose a broken install | [API: probes & diagnostics](docs/API.md#health-and-metrics-routes) |
| Contribute or run tests | [Contributing](CONTRIBUTING.md) · [Roadmap](ROADMAP.md) |
| Cut a release | [Release process](RELEASE.md) · [Versioning](docs/VERSIONING.md) |

## Updating

Select the intended release and follow [the upgrade procedure](docs/INSTALL.md#updating).
Back up the full database/configuration first; LibreNMS updates and plugin source
upgrades are separate operations.

## License

MIT License — see [LICENSE](LICENSE)
