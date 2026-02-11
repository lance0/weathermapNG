# WeathermapNG Roadmap

This document outlines the development roadmap for WeathermapNG, a network visualization plugin for LibreNMS.

## Current Status: v1.6.0 (Stable)

The plugin is production-ready with core features complete:
- Interactive map editor with drag-and-drop
- Real-time traffic visualization (RRD-based)
- Flow animations with particle effects
- Map versioning and history
- Server-Sent Events for live updates
- Embeddable views with navigation bar
- Demo mode for testing
- Device-type node icons (router, switch, server, firewall)

---

## Short Term (Next Release)

### v1.7.0 - Auto-Discovery & Templates

- [ ] **LLDP/CDP Auto-Discovery**: Real neighbor detection
  - Query LibreNMS `links` table for actual topology
  - Create accurate node/link mapping from LLDP/CDP data
  - Replace broken ifIndex-based matching

- [ ] **Map Templates Gallery**: Pre-built templates for common topologies
  - Data center layout
  - WAN/MPLS network
  - Campus network
  - Simple branch office

- [x] **Keyboard Shortcuts**: Power user features (DONE)
  - Ctrl+S save, Ctrl+Z/Y undo/redo
  - Delete key for selected items
  - Arrow keys for nudging (Shift for 10px)
  - +/-/0 for zoom control, Esc to deselect

- [x] **Editor Enhancements** (DONE)
  - Zoom & pan (mouse wheel + middle-click)
  - Editor minimap with click navigation
  - Undo/redo system (50-state history)
  - Grid snapping toggle
  - Node boundary checking
  - Smart node placement (spiral)
  - Canvas resize validation

- [ ] **Bulk Operations**: Multi-select and bulk edit
  - Select multiple nodes/links
  - Bulk delete
  - Bulk style changes

---

## Medium Term

### v1.7.0 - Advanced Visualization

- [ ] **Historical Playback**: View traffic patterns over time
  - Timeline scrubber
  - Play/pause controls
  - Speed adjustment

- [ ] **Custom Metrics**: Beyond bandwidth
  - CPU/Memory utilization on nodes
  - Latency visualization
  - Packet loss indicators
  - Custom SNMP OID support

- [ ] **Advanced Alerts Integration**
  - LibreNMS alert overlay
  - Alert severity indicators
  - Click-through to alert details
  - Alert history on hover

- [ ] **Map Groups/Folders**: Organization for many maps
  - Hierarchical folders
  - Tags and filtering
  - Favorites

### v1.8.0 - Collaboration & Export

- [ ] **Multi-user Editing**: Real-time collaboration
  - Presence indicators
  - Conflict resolution
  - Edit locking

- [ ] **Export Formats**
  - SVG export
  - PDF export
  - High-res PNG
  - Visio/Draw.io format

- [ ] **Scheduled Reports**
  - Daily/weekly snapshots
  - Email delivery
  - PDF generation

---

## Long Term

### v2.0.0 - Next Generation

- [ ] **3D Visualization**: Optional 3D map view
  - WebGL rendering
  - Geographic positioning
  - Building/floor layouts

- [ ] **Auto-Layout Algorithms**
  - Force-directed graphs
  - Hierarchical layout
  - Geographic placement from device data

- [ ] **Plugin Ecosystem**
  - Custom data source plugins
  - Visualization plugins
  - Export format plugins

- [ ] **Mobile App**
  - iOS/Android companion app
  - Push notifications for alerts
  - Quick map viewing

- [ ] **API v2**
  - GraphQL support
  - Webhook integrations
  - External data sources

---

## Completed Features

### v1.6.0
- [x] Simplified data fetching (RRD-only, removed buggy API/SNMP)
- [x] Fixed RRD path resolution to match LibreNMS naming
- [x] Fixed utilization calculation for full-duplex links
- [x] Device-type node icons (router, switch, server, firewall)
- [x] Enhanced link tooltips with bandwidth capacity
- [x] Embed navigation bar with map title and edit link
- [x] Proper service registration in ServiceProvider
- [x] Admin-only settings authorization
- [x] Fixed cache key collisions

### v1.5.x
- [x] Map versioning with history and restore
- [x] Auto-save functionality
- [x] Demo mode for testing without devices
- [x] Docker development environment
- [x] Improved install scripts
- [x] Removed heatmap (pan/zoom sync issues)

### v1.4.x - v1.5.0
- [x] Security hardening (XSS prevention, input validation)
- [x] Authorization policies
- [x] FormRequest validation

### v1.3.x
- [x] Performance caching system
- [x] E2E installation tests

### v1.2.x
- [x] Map templates
- [x] Accessibility improvements (WCAG 2.1 AA)
- [x] Toast notifications
- [x] Loading states
- [x] Web installer

### v1.1.x
- [x] Database-driven architecture
- [x] MVC structure with services
- [x] Real-time SSE updates
- [x] RESTful JSON API
- [x] D3.js editor
- [x] Embed viewer
- [x] Auto-discovery

---

## Contributing

Want to help? Check out:
- [CONTRIBUTING.md](CONTRIBUTING.md) - How to contribute
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues) - Feature requests and bugs

### Priority Areas
1. **Testing**: More test coverage, especially E2E
2. **Documentation**: User guides, video tutorials
3. **Accessibility**: Screen reader improvements
4. **Performance**: Large map optimization

---

## Feedback

Have ideas for the roadmap?
- Open a [GitHub Issue](https://github.com/lance0/weathermapNG/issues)
- Join the [LibreNMS Community](https://community.librenms.org)
