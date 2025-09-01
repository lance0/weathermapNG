# Changelog

All notable changes to WeathermapNG will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-29

### Added
- **Complete rewrite with database-driven architecture**
  - Migrated from file-based to MySQL/PostgreSQL database storage
  - Added proper Laravel Eloquent models (Map, Node, Link)
  - Implemented database migrations for schema management
  - Added database seeders for demo data

- **MVC Architecture Implementation**
  - Controllers: MapController, RenderController, HealthController
  - Service Layer: PortUtilService, DevicePortLookup
  - Policy-based authorization with MapPolicy
  - Resource organization with proper view structure

- **Real-time Data Integration**
  - Enhanced RRD file handling with multiple path detection
  - LibreNMS API fallback with proper error handling
  - Live data polling with caching
  - Robust data parsing for different RRD formats

- **Interactive Web Interface**
  - Drag-and-drop map editor with device integration
  - Create Map modal with form validation
  - Real-time map viewer with auto-refresh
  - Embeddable viewers for dashboards and iframes

- **RESTful JSON API**
  - Complete CRUD operations for maps
  - Live utilization data endpoints
  - Device and port lookup APIs
  - Import/export functionality
  - Health check and statistics endpoints

- **Production Features**
  - CLI poller for background processing
  - Backup and restore utilities
  - Comprehensive logging and error handling
  - Health monitoring and system checks
  - Security hardening with input validation

- **Developer Experience**
  - PSR-12 compliant code structure
  - Comprehensive documentation
  - PHPUnit test framework setup
  - Contribution guidelines and code standards
  - Service provider for proper Laravel integration

### Changed
- **Architecture**: Complete migration from file-based to database-driven
- **Data Storage**: INI configuration files replaced with relational database
- **API**: Enhanced with live data, health checks, and better error handling
- **Security**: Improved with policy-based authorization and input validation
- **Performance**: Added caching layers and optimized data fetching

### Fixed
- **Composer autoloading**: Fixed malformed composer.json with proper PSR-4 structure
- **API integration**: Corrected PortUtilService to use proper LibreNMSAPI methods
- **Node status detection**: Enhanced to handle both string and numeric status values
- **Poller bootstrap**: Fixed to work with multiple LibreNMS installation paths
- **Route conflicts**: Resolved middleware and routing issues
- **Embed functionality**: Fixed view variables and data passing

### Security
- **Authentication**: All routes protected by LibreNMS auth middleware
- **Authorization**: Policy-based access control for map operations
- **Input validation**: Comprehensive validation on all user inputs
- **File security**: Protected sensitive directories with .htaccess
- **RRD access**: Read-only operations with proper error handling

### Deprecated
- **File-based storage**: Replaced with database-driven approach
- **Old INI configuration**: Migrated to database schema
- **Legacy API endpoints**: Updated to RESTful JSON API

### Removed
- **Old file-based map storage system**
- **Legacy configuration file parsing**
- **Deprecated API methods**

### Technical Details
- **PHP Version**: Requires PHP 8.0+
- **Database**: MySQL 5.7+ or PostgreSQL 9.5+
- **Dependencies**: Laravel components, GD extension
- **File Structure**: MVC organization with service layer
- **Testing**: PHPUnit framework with database testing
- **Documentation**: Comprehensive README, API docs, contribution guidelines

---

## [1.1.0] - 2025-09-01

### Added
- D3.js editor enhancements: link creation mode, per-item Apply buttons, debounced position saves, bulk link editing, box-select, inline validation, sliders (node size, label size, link width), device/port autocomplete, geo backgrounds (TopoJSON, projection, scale/offset), export (SVG/PNG), snackbar notifications, and help modal.
- Live preview in editor: optional polling of live metrics with on-canvas recoloring.
- Embed viewer upgrades: metric selector (percent/in/out/sum), dynamic legend, PNG export, hover tooltips for link metrics.
- Auto-discovery: seed nodes/links from LibreNMS topology with filters (min degree, OS) and initial layout.
- API endpoints: map save (`POST /plugin/WeathermapNG/api/maps/{id}/save`), node/link CRUD, autodiscover (`POST /plugin/WeathermapNG/map/{id}/autodiscover`).
- Alerts overlay wiring: live/SSE payloads now include alert summaries for nodes and links; embed renders alert badges.

### Changed
- Editor/Embed JSON mapping standardized; link labels and styles stored in `style` block.
- Routes consolidated under `plugin/WeathermapNG/...` paths.

### Documentation
- Updated `API.md` with editor CRUD, save, autodiscover, and embed query params.
- Expanded `docs/EDITOR_D3.md` with features, backgrounds, live preview, export, and bulk editing.
- Added `docs/EMBED.md` for metrics, legend, live updates, and export.

### Notes
- Alert overlays currently surface active alerts per device and per port where available; additional detail panes and transports remain future work.

---

## [0.1.0] - 2025-01-28 (Pre-release)

### Added
- Initial file-based weathermap implementation
- Basic map creation and viewing
- RRD data integration
- Simple web interface
- Plugin structure for LibreNMS

### Known Issues
- File-based storage limitations
- Limited error handling
- Basic security measures
- No comprehensive testing

---

## Types of changes
- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for now removed features
- `Fixed` for any bug fixes
- `Security` in case of vulnerabilities

## Versioning
This project uses [Semantic Versioning](https://semver.org/).

Given a version number MAJOR.MINOR.PATCH, increment the:
- **MAJOR** version when you make incompatible API changes
- **MINOR** version when you add functionality in a backwards compatible manner
- **PATCH** version when you make backwards compatible bug fixes

---

## Contributing to Changelog
- Keep entries brief but descriptive
- Group related changes together
- Use present tense for changes ("Add feature" not "Added feature")
- Reference issue numbers when applicable
- Update version numbers according to semantic versioning

---

## Future Plans
- [ ] Advanced map styling and theming
- [ ] Historical data visualization
- [ ] Alert integration with LibreNMS
- [ ] Multi-user collaboration features
- [ ] Advanced network topology algorithms
- [ ] Performance optimizations for large networks
- [ ] Mobile app companion
- [ ] Integration with external monitoring systems
