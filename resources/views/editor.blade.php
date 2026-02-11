@extends('layouts.librenmsv1')

@section('title', $title)

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/weathermapng.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/loading.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/toast.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/a11y.css') }}">
<style>
/* ===== Light Mode (Default) ===== */
.editor-container {
    --editor-toolbox-bg: #f8f9fa;
    --editor-toolbox-btn: #495057;
    --editor-toolbox-btn-hover: #e9ecef;
    --editor-toolbox-btn-hover-text: #212529;
    --editor-toolbox-divider: #dee2e6;
    --editor-canvas-bg: #e9ecef;
    --editor-topbar-bg: #fff;
    --editor-topbar-border: #dee2e6;
    --editor-sidebar-bg: #fff;
    --editor-sidebar-border: #dee2e6;
    --editor-panel-header-bg: #f8f9fa;
    --editor-panel-header-text: #6c757d;
    --editor-input-bg: #fff;
    --editor-input-border: #ced4da;
    --editor-input-text: #495057;
    --editor-canvas-surface: #fff;
    --editor-canvas-shadow: rgba(0,0,0,0.1);
    --editor-text: #212529;
    --editor-text-muted: #6c757d;
    --editor-list-hover: rgba(0,123,255,0.1);
    --editor-list-selected: rgba(0,123,255,0.15);
    --editor-accent: #0d6efd;
    --editor-accent-orange: #fd7e14;
}

/* ===== Dark Mode ===== */
.editor-container.dark-theme {
    --editor-toolbox-bg: #1a1d20;
    --editor-toolbox-btn: #8b929a;
    --editor-toolbox-btn-hover: #2c3136;
    --editor-toolbox-btn-hover-text: #fff;
    --editor-toolbox-divider: #2c3136;
    --editor-canvas-bg: #212529;
    --editor-topbar-bg: #2c3136;
    --editor-topbar-border: #495057;
    --editor-sidebar-bg: #2c3136;
    --editor-sidebar-border: #495057;
    --editor-panel-header-bg: #343a40;
    --editor-panel-header-text: #adb5bd;
    --editor-input-bg: #212529;
    --editor-input-border: #495057;
    --editor-input-text: #e9ecef;
    --editor-canvas-surface: #3d4349;
    --editor-canvas-shadow: rgba(0,0,0,0.3);
    --editor-text: #e9ecef;
    --editor-text-muted: #adb5bd;
    --editor-list-hover: rgba(13,110,253,0.2);
    --editor-list-selected: rgba(13,110,253,0.25);
}

/* ===== Editor Layout ===== */
.editor-container { display: flex; height: calc(100vh - 120px); min-height: 500px; }

/* Left Toolbox */
.editor-toolbox {
    width: 48px; background: var(--editor-toolbox-bg); display: flex; flex-direction: column;
    padding: 8px 4px; gap: 4px; flex-shrink: 0;
}
.editor-toolbox .tool-btn {
    width: 40px; height: 40px; border: none; background: transparent;
    color: var(--editor-toolbox-btn); border-radius: 4px; display: flex; align-items: center;
    justify-content: center; cursor: pointer; transition: all 0.15s; font-size: 16px;
}
.editor-toolbox .tool-btn:hover { background: var(--editor-toolbox-btn-hover); color: var(--editor-toolbox-btn-hover-text); }
.editor-toolbox .tool-btn.active { background: var(--editor-accent); color: #fff; }
.editor-toolbox .tool-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.editor-toolbox .tool-btn:disabled:hover { background: transparent; color: var(--editor-toolbox-btn); }
.editor-toolbox .tool-divider { height: 1px; background: var(--editor-toolbox-divider); margin: 4px 0; }

/* Canvas Area */
.editor-canvas-area { flex: 1; display: flex; flex-direction: column; background: var(--editor-canvas-bg); overflow: hidden; }
.editor-topbar {
    background: var(--editor-topbar-bg); border-bottom: 1px solid var(--editor-topbar-border);
    padding: 6px 12px; display: flex; justify-content: space-between; align-items: center;
    color: var(--editor-text);
}
.editor-topbar .text-muted { color: var(--editor-text-muted) !important; }
.editor-canvas-wrap { flex: 1; overflow: auto; position: relative; padding: 10px; }

/* Right Sidebar */
.editor-sidebar {
    width: 280px; background: var(--editor-sidebar-bg); border-left: 1px solid var(--editor-sidebar-border);
    overflow-y: auto; flex-shrink: 0; color: var(--editor-text);
}
.editor-sidebar .panel { border-bottom: 1px solid var(--editor-sidebar-border); }
.editor-sidebar .panel-header {
    padding: 8px 12px; background: var(--editor-panel-header-bg); font-weight: 600; font-size: 12px;
    text-transform: uppercase; color: var(--editor-panel-header-text);
}
.editor-sidebar .panel-body { padding: 10px 12px; }
.editor-sidebar .form-label { font-size: 11px; margin-bottom: 2px; color: var(--editor-text-muted); }
.editor-sidebar .form-control-sm {
    font-size: 12px; background: var(--editor-input-bg); border-color: var(--editor-input-border);
    color: var(--editor-input-text);
}
.editor-sidebar .form-control-sm:focus {
    background: var(--editor-input-bg); border-color: var(--editor-accent);
    color: var(--editor-input-text); box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25);
}

/* Node/link list items */
#nodes-list > div:hover, #links-list > div:hover {
    background-color: var(--editor-list-hover); border-radius: 3px;
}
#nodes-list, #links-list { color: var(--editor-text); }

/* Tool button states */
.tool-btn.link-active { background: var(--editor-accent-orange) !important; color: #fff !important; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }

/* Canvas styling */
#map-canvas { background: var(--editor-canvas-surface); box-shadow: 0 2px 8px var(--editor-canvas-shadow); }

/* Minimap */
#editor-minimap { background: var(--editor-canvas-surface) !important; border-color: var(--editor-sidebar-border) !important; }
</style>
@endpush

@section('content')
<div class="editor-container">
    <!-- Left Toolbox -->
    <div class="editor-toolbox">
        <button class="tool-btn" onclick="addNode()" title="Add Node (from sidebar device)">
            <i class="fas fa-plus"></i>
        </button>
        <button class="tool-btn" id="link-mode-btn" onclick="toggleLinkMode()" title="Link Mode - Click two nodes to connect">
            <i class="fas fa-link"></i>
        </button>
        <div class="tool-divider"></div>
        <button class="tool-btn" id="snap-grid-btn" onclick="toggleSnapToGrid()" title="Snap to Grid">
            <i class="fas fa-th"></i>
        </button>
        <button class="tool-btn" onclick="duplicateSelectedNode()" title="Duplicate Selected" id="duplicate-btn" disabled>
            <i class="fas fa-copy"></i>
        </button>
        <button class="tool-btn" onclick="deleteSelectedNode()" title="Delete Selected" id="delete-node-btn" disabled>
            <i class="fas fa-trash"></i>
        </button>
        <div class="tool-divider"></div>
        <button class="tool-btn" onclick="undo()" title="Undo (Ctrl+Z)">
            <i class="fas fa-undo"></i>
        </button>
        <button class="tool-btn" onclick="redo()" title="Redo (Ctrl+Y)">
            <i class="fas fa-redo"></i>
        </button>
        <div style="flex:1"></div>
        <button class="tool-btn" onclick="zoomIn()" title="Zoom In (+)">
            <i class="fas fa-search-plus"></i>
        </button>
        <button class="tool-btn" onclick="zoomOut()" title="Zoom Out (-)">
            <i class="fas fa-search-minus"></i>
        </button>
        <button class="tool-btn" onclick="resetZoom()" title="Reset Zoom (0)">
            <i class="fas fa-compress-arrows-alt"></i>
        </button>
    </div>

    <!-- Canvas Area -->
    <div class="editor-canvas-area">
        <!-- Top Bar -->
        <div class="editor-topbar">
            <div class="d-flex align-items-center">
                <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-sm btn-outline-secondary mr-2">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <strong class="mr-2">{{ $map ? $map->name : 'New Map' }}</strong>
                <span id="unsaved-indicator" class="badge badge-warning" style="display: none;">Unsaved</span>
            </div>
            <div class="d-flex align-items-center">
                <small class="text-muted mr-3">
                    <span id="node-count">0</span> nodes &bull; <span id="link-count">0</span> links
                    &bull; <span id="zoom-level">100%</span>
                </small>
                @if($map)
                <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank" class="btn btn-sm btn-outline-info mr-2" title="Preview">
                    <i class="fas fa-eye"></i>
                </a>
                @endif
                <button class="btn btn-sm btn-outline-secondary mr-2" onclick="openVersionHistory()" title="Version History">
                    <i class="fas fa-history"></i>
                </button>
                <button class="btn btn-sm btn-success" onclick="saveMap()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>

        <!-- Canvas -->
        <div class="editor-canvas-wrap">
            <canvas id="map-canvas"
                    width="{{ $map->width ?? config('weathermapng.default_width', 800) }}"
                    height="{{ $map->height ?? config('weathermapng.default_height', 600) }}">
            </canvas>
            <div id="link-tooltip" class="editor-link-tooltip"></div>
            <!-- Minimap -->
            <canvas id="editor-minimap" width="150" height="100"
                    style="position: absolute; bottom: 20px; right: 20px; z-index: 100; background: rgba(255,255,255,0.95); border: 1px solid #ccc; border-radius: 4px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
            </canvas>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="editor-sidebar">
        <!-- Device Selection -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-plus-circle mr-1"></i> Add Node</div>
            <div class="panel-body">
                <div class="form-group mb-2">
                    <label class="form-label">Device</label>
                    <select class="form-control form-control-sm" id="device-select">
                        <option value="">Select device...</option>
                    </select>
                </div>
                <div class="form-group mb-2" id="interface-container" style="display: none;">
                    <label class="form-label">Interface</label>
                    <select class="form-control form-control-sm" id="interface-select">
                        <option value="">Select interface...</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-sm btn-block" onclick="addNode()">
                    <i class="fas fa-plus"></i> Add to Canvas
                </button>
            </div>
        </div>

        <!-- Selected Node -->
        <div class="panel" id="node-properties-card" style="display: none;">
            <div class="panel-header" style="background: #0d6efd; color: #fff;">
                <i class="fas fa-circle mr-1"></i> Selected Node
            </div>
            <div class="panel-body">
                <div class="form-group mb-2">
                    <label class="form-label">Label</label>
                    <input type="text" class="form-control form-control-sm" id="node-prop-label">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Device</label>
                    <select class="form-control form-control-sm" id="node-prop-device">
                        <option value="">No device</option>
                    </select>
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Interface</label>
                    <select class="form-control form-control-sm" id="node-prop-interface">
                        <option value="">No interface</option>
                    </select>
                </div>
                <div class="btn-group btn-group-sm d-flex">
                    <button class="btn btn-primary" onclick="saveSelectedNode()"><i class="fas fa-check"></i> Apply</button>
                    <button class="btn btn-secondary" onclick="duplicateSelectedNode()" title="Duplicate"><i class="fas fa-copy"></i></button>
                    <button class="btn btn-danger" onclick="deleteSelectedNode()" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>

        <!-- Map Settings -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-cog mr-1"></i> Map Settings</div>
            <div class="panel-body">
                <div class="form-group mb-2">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control form-control-sm" id="map-name"
                           value="{{ $map->name ?? '' }}" placeholder="map-name">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control form-control-sm" id="map-title"
                           value="{{ $map->title ?? '' }}" placeholder="Map Title">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Size (W × H)</label>
                    <div class="d-flex align-items-center">
                        <input type="number" class="form-control form-control-sm" id="map-width"
                               value="{{ $map->width ?? config('weathermapng.default_width', 800) }}" min="100" max="4096">
                        <span class="mx-2 text-muted">×</span>
                        <input type="number" class="form-control form-control-sm" id="map-height"
                               value="{{ $map->height ?? config('weathermapng.default_height', 600) }}" min="100" max="4096">
                    </div>
                </div>
            </div>
        </div>

        <!-- Nodes List -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-sitemap mr-1"></i> Nodes <span class="badge badge-secondary float-right" id="nodes-badge">0</span></div>
            <div class="panel-body" id="nodes-list" style="max-height: 120px; overflow-y: auto;">
                <small class="text-muted">No nodes yet</small>
            </div>
        </div>

        <!-- Links List -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-link mr-1"></i> Links <span class="badge badge-secondary float-right" id="links-badge">0</span></div>
            <div class="panel-body" id="links-list" style="max-height: 120px; overflow-y: auto;">
                <small class="text-muted">No links yet</small>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-ellipsis-h mr-1"></i> Actions</div>
            <div class="panel-body">
                <button class="btn btn-outline-secondary btn-sm btn-block mb-1" onclick="exportJson()">
                    <i class="fas fa-download"></i> Export JSON
                </button>
                <button class="btn btn-outline-danger btn-sm btn-block" onclick="clearCanvas()">
                    <i class="fas fa-trash"></i> Clear Canvas
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Link Configuration Modal -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Link</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Source Port</label>
                    <select id="link-src-port" class="form-control"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Destination Port</label>
                    <select id="link-dst-port" class="form-control"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bandwidth (bps)</label>
                    <input type="number" id="link-bandwidth" class="form-control" min="0" placeholder="e.g. 1000000000 for 1Gbps">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="delete-link-btn" style="display:none;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-link-btn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

        <!-- Version Save Modal -->
        <div class="modal fade" id="versionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Version</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="version-name" class="form-label">Version Name</label>
                            <input type="text" class="form-control" id="version-name"
                                   placeholder="e.g. Experiment 1" maxlength="100">
                            <small class="form-text text-muted">Max 100 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="version-desc"
                                      placeholder="What did you change in this version?" rows="3" maxlength="1000"></textarea>
                            <small class="form-text text-muted">Max 1000 characters</small>
                        </div>
                        <div class="form-check mb-3">
                            <label>
                                <input type="checkbox" id="auto-save" checked>
                                <span class="ml-2">Auto-save every 5 minutes</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveVersion()">
                            <i class="fas fa-save"></i> Save Version
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version History Modal -->
        <div class="modal fade" id="versionHistoryModal" tabindex="-1">
            <div class="modal-dialog" style="max-width: 800px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Version History</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="version-list" class="editor-version-list">
                            <div class="text-center text-muted py-3">
                                <div class="spinner-border-custom text-primary" style="width: 2rem; height: 2rem;"></div>
                                <small>Loading versions...</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearOldVersions()">
                            <i class="fas fa-trash-alt"></i> Clear Old
                        </button>
                        <button type="button" class="btn btn-info" onclick="exportVersions()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @endsection

        @section('scripts')
        <script src="{{ asset('plugins/WeathermapNG/resources/js/ui-helpers.js') }}"></script>
        <script>
            // ===== Theme Detection =====
            function detectTheme() {
                const container = document.querySelector('.editor-container');
                if (!container) return;

                let isDark = null;

                // Method 1 (Primary): Check actual rendered background color
                // This is the most reliable since it shows what user actually sees
                const navbar = document.querySelector('.navbar, .navbar-default, .navbar-static-top, nav');
                const elementsToCheck = [navbar, document.body].filter(Boolean);

                for (const element of elementsToCheck) {
                    const bg = window.getComputedStyle(element).backgroundColor;
                    const rgb = bg.match(/\d+/g);
                    if (rgb && rgb.length >= 3) {
                        // Skip transparent backgrounds
                        if (rgb.length === 4 && parseInt(rgb[3]) === 0) continue;
                        if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;

                        const brightness = (parseInt(rgb[0]) * 299 + parseInt(rgb[1]) * 587 + parseInt(rgb[2]) * 114) / 1000;
                        isDark = brightness < 128;
                        console.log('WeathermapNG: Detected from', element.tagName, 'bg:', bg, 'brightness:', brightness);
                        break;
                    }
                }

                // Method 2 (Fallback): Check for dark theme class names
                if (isDark === null) {
                    const allClasses = document.body.className + ' ' + document.documentElement.className;
                    if (/\bdark\b|\bnight\b|\bdark-mode\b/i.test(allClasses)) {
                        isDark = true;
                    }
                }

                // Method 3 (Last resort): Default to light
                if (isDark === null) {
                    isDark = false;
                }

                container.classList.toggle('dark-theme', isDark);
                console.log('WeathermapNG Editor: Theme detected as', isDark ? 'dark' : 'light');
            }

            // Run on load with slight delay to ensure styles are applied
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(detectTheme, 100);
                // Watch for class/style changes that might indicate theme switch
                const observer = new MutationObserver(() => setTimeout(detectTheme, 50));
                observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] });
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style', 'data-bs-theme'] });
                // Also watch for stylesheet changes
                observer.observe(document.head, { childList: true, subtree: true });
            });

            let mapId = {{ $map->id ?? 'null' }};
            let nodes = [];
            let links = [];
            let selectedNode = null;
            let devicesCache = [];
            let canvas = null;
            let ctx = null;
            let isDragging = false;
            let dragOffset = { x: 0, y: 0 };
            let linkMode = false;
            let linkStart = null;

            // Zoom and pan state
            let viewScale = 1;
            let viewOffsetX = 0;
            let viewOffsetY = 0;
            let isPanning = false;
            let panStart = { x: 0, y: 0 };
            const MIN_ZOOM = 0.25;
            const MAX_ZOOM = 4;

            // Undo/redo state
            const undoStack = [];
            const redoStack = [];
            const MAX_UNDO = 50;

            // Grid snapping state
            let snapToGrid = false;
            let gridSize = 20;

            document.addEventListener('DOMContentLoaded', function() {
                initCanvas();
                if (mapId) {
                    loadMapData(mapId);
                }
                loadDevices();
            });

            function getCsrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            }

            function initCanvas() {
                canvas = document.getElementById('map-canvas');
                if (!canvas) return;
                ctx = canvas.getContext('2d');

                canvas.addEventListener('mousedown', handleMouseDown);
                canvas.addEventListener('mousemove', handleMouseMove);
                canvas.addEventListener('mouseup', handleMouseUp);
                canvas.addEventListener('mouseleave', handleMouseUp);
                canvas.addEventListener('wheel', handleWheel, { passive: false });
                canvas.addEventListener('contextmenu', e => e.preventDefault());

                renderEditor();
                updateZoomDisplay();
            }

            function getCanvasPoint(event) {
                const rect = canvas.getBoundingClientRect();
                // Convert screen coords to canvas coords accounting for zoom/pan
                const screenX = event.clientX - rect.left;
                const screenY = event.clientY - rect.top;
                return {
                    x: (screenX - viewOffsetX) / viewScale,
                    y: (screenY - viewOffsetY) / viewScale,
                };
            }

            // ========== Zoom and Pan Handlers ==========
            function handleWheel(event) {
                event.preventDefault();
                const rect = canvas.getBoundingClientRect();
                const mouseX = event.clientX - rect.left;
                const mouseY = event.clientY - rect.top;

                // Calculate zoom factor
                const zoomFactor = event.deltaY > 0 ? 0.9 : 1.1;
                const newScale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, viewScale * zoomFactor));

                // Zoom centered on mouse position
                const scaleChange = newScale / viewScale;
                viewOffsetX = mouseX - (mouseX - viewOffsetX) * scaleChange;
                viewOffsetY = mouseY - (mouseY - viewOffsetY) * scaleChange;
                viewScale = newScale;

                renderEditor();
                updateZoomDisplay();
            }

            function zoomIn() {
                const newScale = Math.min(MAX_ZOOM, viewScale * 1.25);
                const centerX = canvas.width / 2;
                const centerY = canvas.height / 2;
                const scaleChange = newScale / viewScale;
                viewOffsetX = centerX - (centerX - viewOffsetX) * scaleChange;
                viewOffsetY = centerY - (centerY - viewOffsetY) * scaleChange;
                viewScale = newScale;
                renderEditor();
                updateZoomDisplay();
            }

            function zoomOut() {
                const newScale = Math.max(MIN_ZOOM, viewScale / 1.25);
                const centerX = canvas.width / 2;
                const centerY = canvas.height / 2;
                const scaleChange = newScale / viewScale;
                viewOffsetX = centerX - (centerX - viewOffsetX) * scaleChange;
                viewOffsetY = centerY - (centerY - viewOffsetY) * scaleChange;
                viewScale = newScale;
                renderEditor();
                updateZoomDisplay();
            }

            function resetZoom() {
                viewScale = 1;
                viewOffsetX = 0;
                viewOffsetY = 0;
                renderEditor();
                updateZoomDisplay();
            }

            function updateZoomDisplay() {
                const display = document.getElementById('zoom-level');
                if (display) display.textContent = Math.round(viewScale * 100) + '%';
            }

            function handleMouseDown(event) {
                if (!canvas) return;

                // Middle-click or right-click for panning
                if (event.button === 1 || event.button === 2) {
                    isPanning = true;
                    panStart = { x: event.clientX - viewOffsetX, y: event.clientY - viewOffsetY };
                    canvas.style.cursor = 'grabbing';
                    return;
                }

                const { x, y } = getCanvasPoint(event);
                const node = getNodeAt(x, y);

                if (linkMode) {
                    if (!node) return;
                    if (!linkStart) {
                        linkStart = node;
                        updateLinkModeUI();
                        renderEditor(); // Highlight the selected node
                        return;
                    }

                    if (linkStart.id !== node.id) {
                        saveState(); // Save for undo
                        links.push({
                            id: `link-${Date.now()}`,
                            dbId: null,
                            srcId: linkStart.id,
                            dstId: node.id,
                            portA: null,
                            portB: null,
                            bw: null,
                            style: {},
                        });
                        linkStart = null;
                        updateLinkModeUI();
                        renderEditor();
                        renderLinksList();
                        renderNodesList();
                        WMNGToast.success('Link created!', { duration: 1500 });
                    }
                    return;
                }

                if (node) {
                    selectedNode = node;
                    isDragging = true;
                    dragOffset = { x: x - node.x, y: y - node.y };
                    saveState(); // Save for undo before dragging
                    populateNodeProperties(node);
                    updateToolbarState();
                    renderNodesList();
                } else {
                    selectedNode = null;
                    populateNodeProperties(null);
                    updateToolbarState();
                    renderNodesList();
                }

                renderEditor();
            }

            function handleMouseMove(event) {
                // Handle panning
                if (isPanning) {
                    viewOffsetX = event.clientX - panStart.x;
                    viewOffsetY = event.clientY - panStart.y;
                    renderEditor();
                    return;
                }

                if (!isDragging || !selectedNode || !canvas) return;
                const { x, y } = getCanvasPoint(event);
                const nodeRadius = 12;
                // Calculate new position
                let newX = x - dragOffset.x;
                let newY = y - dragOffset.y;

                // Apply grid snapping if enabled
                if (snapToGrid) {
                    newX = snapPosition(newX);
                    newY = snapPosition(newY);
                }

                // Constrain node to canvas bounds
                selectedNode.x = Math.max(nodeRadius, Math.min(canvas.width - nodeRadius, newX));
                selectedNode.y = Math.max(nodeRadius, Math.min(canvas.height - nodeRadius, newY));
                renderEditor();
            }

            function snapPosition(pos) {
                if (!snapToGrid) return pos;
                return Math.round(pos / gridSize) * gridSize;
            }

            function toggleSnapToGrid() {
                snapToGrid = !snapToGrid;
                const btn = document.getElementById('snap-grid-btn');
                if (btn) {
                    btn.classList.toggle('active', snapToGrid);
                    btn.title = snapToGrid ? 'Snap to Grid (ON)' : 'Snap to Grid (OFF)';
                }
                renderEditor();
            }

            function handleMouseUp() {
                isDragging = false;
                if (isPanning) {
                    isPanning = false;
                    canvas.style.cursor = 'default';
                }
            }

            function getNodeAt(x, y) {
                const radius = 12;
                return nodes.find(node => {
                    const dx = x - node.x;
                    const dy = y - node.y;
                    return Math.sqrt(dx * dx + dy * dy) <= radius;
                });
            }

            function findNodeById(id) {
                return nodes.find(node => node.id === id || node.dbId === id);
            }

            function drawNode(node) {
                const radius = 12;
                ctx.beginPath();
                ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);

                // Color based on state: link start (orange), selected (blue), normal (green)
                if (linkMode && linkStart === node) {
                    ctx.fillStyle = '#fd7e14'; // Orange for link start
                } else if (node === selectedNode) {
                    ctx.fillStyle = '#0d6efd'; // Blue for selected
                } else {
                    ctx.fillStyle = '#28a745'; // Green for normal
                }
                ctx.fill();

                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Draw pulsing ring for link start node
                if (linkMode && linkStart === node) {
                    ctx.beginPath();
                    ctx.arc(node.x, node.y, radius + 4, 0, Math.PI * 2);
                    ctx.strokeStyle = 'rgba(253, 126, 20, 0.5)';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                }

                // Use theme-aware text color for labels with shadow for readability
                const isDarkTheme = document.querySelector('.editor-container.dark-theme') !== null;
                ctx.font = '12px "Segoe UI", Arial, sans-serif';
                ctx.textAlign = 'center';

                // Add text shadow/outline for better readability
                ctx.strokeStyle = isDarkTheme ? 'rgba(0,0,0,0.7)' : 'rgba(255,255,255,0.8)';
                ctx.lineWidth = 3;
                ctx.strokeText(node.label || 'Node', node.x, node.y - 18);

                ctx.fillStyle = isDarkTheme ? '#f8f9fa' : '#212529';
                ctx.fillText(node.label || 'Node', node.x, node.y - 18);
            }

            function drawLink(link) {
                const src = findNodeById(link.srcId);
                const dst = findNodeById(link.dstId);
                if (!src || !dst) return;

                ctx.beginPath();
                ctx.moveTo(src.x, src.y);
                ctx.lineTo(dst.x, dst.y);
                ctx.strokeStyle = '#6c757d';
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            function renderEditor() {
                if (!ctx || !canvas) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Apply zoom and pan transforms
                ctx.save();
                ctx.translate(viewOffsetX, viewOffsetY);
                ctx.scale(viewScale, viewScale);

                // Draw grid when zoomed or snap is enabled
                if (viewScale !== 1 || snapToGrid) {
                    drawGrid();
                }

                links.forEach(drawLink);
                nodes.forEach(drawNode);

                ctx.restore();

                // Update minimap and status
                renderMinimap();
                updateStatusCounts();
            }

            function drawGrid() {
                const size = snapToGrid ? gridSize : 50;
                ctx.strokeStyle = snapToGrid ? 'rgba(100, 150, 255, 0.3)' : 'rgba(200, 200, 200, 0.3)';
                ctx.lineWidth = 0.5 / viewScale;

                for (let x = 0; x <= canvas.width; x += size) {
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, canvas.height);
                    ctx.stroke();
                }
                for (let y = 0; y <= canvas.height; y += size) {
                    ctx.beginPath();
                    ctx.moveTo(0, y);
                    ctx.lineTo(canvas.width, y);
                    ctx.stroke();
                }
            }

            // ========== Editor Minimap ==========
            let minimapCanvas = null;
            let minimapCtx = null;

            function initMinimap() {
                minimapCanvas = document.getElementById('editor-minimap');
                if (!minimapCanvas) return;
                minimapCtx = minimapCanvas.getContext('2d');
                minimapCanvas.addEventListener('click', handleMinimapClick);
            }

            function renderMinimap() {
                if (!minimapCtx || !minimapCanvas || !canvas) return;
                const mmW = minimapCanvas.width;
                const mmH = minimapCanvas.height;

                minimapCtx.clearRect(0, 0, mmW, mmH);

                // Calculate scale to fit map in minimap
                const scaleX = mmW / canvas.width;
                const scaleY = mmH / canvas.height;
                const scale = Math.min(scaleX, scaleY);

                // Draw map bounds
                minimapCtx.strokeStyle = '#ccc';
                minimapCtx.lineWidth = 1;
                minimapCtx.strokeRect(0, 0, canvas.width * scale, canvas.height * scale);

                // Draw nodes as dots
                nodes.forEach(node => {
                    minimapCtx.beginPath();
                    minimapCtx.arc(node.x * scale, node.y * scale, 3, 0, Math.PI * 2);
                    minimapCtx.fillStyle = node === selectedNode ? '#0d6efd' : '#28a745';
                    minimapCtx.fill();
                });

                // Draw viewport rectangle
                if (viewScale !== 1 || viewOffsetX !== 0 || viewOffsetY !== 0) {
                    const vpX = (-viewOffsetX / viewScale) * scale;
                    const vpY = (-viewOffsetY / viewScale) * scale;
                    const vpW = (canvas.width / viewScale) * scale;
                    const vpH = (canvas.height / viewScale) * scale;

                    minimapCtx.strokeStyle = 'rgba(0, 123, 255, 0.8)';
                    minimapCtx.lineWidth = 2;
                    minimapCtx.strokeRect(vpX, vpY, vpW, vpH);
                }
            }

            function handleMinimapClick(event) {
                if (!minimapCanvas || !canvas) return;
                const rect = minimapCanvas.getBoundingClientRect();
                const clickX = event.clientX - rect.left;
                const clickY = event.clientY - rect.top;

                // Convert minimap coords to map coords
                const scaleX = minimapCanvas.width / canvas.width;
                const scaleY = minimapCanvas.height / canvas.height;
                const scale = Math.min(scaleX, scaleY);

                const mapX = clickX / scale;
                const mapY = clickY / scale;

                // Center view on clicked position
                viewOffsetX = canvas.width / 2 - mapX * viewScale;
                viewOffsetY = canvas.height / 2 - mapY * viewScale;

                renderEditor();
                updateZoomDisplay();
            }

            // Initialize minimap on page load
            document.addEventListener('DOMContentLoaded', initMinimap);

            // ========== Canvas Resize Validation ==========
            function initCanvasResizeValidation() {
                const widthInput = document.getElementById('map-width');
                const heightInput = document.getElementById('map-height');

                if (widthInput) {
                    widthInput.addEventListener('change', function() {
                        const newWidth = parseInt(this.value, 10);
                        if (newWidth && newWidth >= 100) {
                            validateAndApplyCanvasResize(newWidth, canvas.height);
                        }
                    });
                }

                if (heightInput) {
                    heightInput.addEventListener('change', function() {
                        const newHeight = parseInt(this.value, 10);
                        if (newHeight && newHeight >= 100) {
                            validateAndApplyCanvasResize(canvas.width, newHeight);
                        }
                    });
                }
            }

            function validateAndApplyCanvasResize(newWidth, newHeight) {
                const nodeRadius = 12;
                const outOfBounds = nodes.filter(n =>
                    n.x > newWidth - nodeRadius || n.y > newHeight - nodeRadius
                );

                if (outOfBounds.length > 0) {
                    const choice = confirm(
                        `${outOfBounds.length} node(s) will be outside the new canvas bounds.\n\n` +
                        `Click OK to automatically move them inside bounds.\n` +
                        `Click Cancel to keep the current canvas size.`
                    );

                    if (choice) {
                        // Auto-adjust nodes to fit within new bounds
                        outOfBounds.forEach(node => {
                            node.x = Math.min(node.x, newWidth - nodeRadius);
                            node.y = Math.min(node.y, newHeight - nodeRadius);
                        });
                    } else {
                        // Revert input values
                        document.getElementById('map-width').value = canvas.width;
                        document.getElementById('map-height').value = canvas.height;
                        return;
                    }
                }

                // Apply new canvas size
                canvas.width = newWidth;
                canvas.height = newHeight;
                renderEditor();
                WMNGToast.info(`Canvas resized to ${newWidth}x${newHeight}`, { duration: 2000 });
            }

            document.addEventListener('DOMContentLoaded', initCanvasResizeValidation);

            // ========== Keyboard Shortcuts ==========
            function initKeyboardShortcuts() {
                document.addEventListener('keydown', handleKeyDown);
            }

            function handleKeyDown(event) {
                // Ignore if user is typing in an input
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA' || event.target.tagName === 'SELECT') {
                    return;
                }

                const key = event.key.toLowerCase();
                const ctrl = event.ctrlKey || event.metaKey;
                const shift = event.shiftKey;

                // Ctrl+S: Save map
                if (ctrl && key === 's') {
                    event.preventDefault();
                    saveMap();
                    return;
                }

                // Ctrl+Z: Undo
                if (ctrl && key === 'z' && !shift) {
                    event.preventDefault();
                    undo();
                    return;
                }

                // Ctrl+Y or Ctrl+Shift+Z: Redo
                if ((ctrl && key === 'y') || (ctrl && shift && key === 'z')) {
                    event.preventDefault();
                    redo();
                    return;
                }

                // Delete/Backspace: Delete selected node
                if ((key === 'delete' || key === 'backspace') && selectedNode) {
                    event.preventDefault();
                    deleteSelectedNode();
                    return;
                }

                // Escape: Deselect / Cancel link mode
                if (key === 'escape') {
                    if (linkMode) {
                        toggleLinkMode();
                    }
                    if (selectedNode) {
                        selectedNode = null;
                        populateNodeProperties(null);
                        renderEditor();
                    }
                    return;
                }

                // Arrow keys: Nudge selected node
                if (selectedNode && ['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key)) {
                    event.preventDefault();
                    const amount = shift ? 10 : 1;
                    const nodeRadius = 12;

                    saveState(); // Save for undo before moving

                    switch (key) {
                        case 'arrowup':
                            selectedNode.y = Math.max(nodeRadius, selectedNode.y - amount);
                            break;
                        case 'arrowdown':
                            selectedNode.y = Math.min(canvas.height - nodeRadius, selectedNode.y + amount);
                            break;
                        case 'arrowleft':
                            selectedNode.x = Math.max(nodeRadius, selectedNode.x - amount);
                            break;
                        case 'arrowright':
                            selectedNode.x = Math.min(canvas.width - nodeRadius, selectedNode.x + amount);
                            break;
                    }
                    renderEditor();
                    return;
                }

                // + or =: Zoom in
                if (key === '+' || key === '=') {
                    event.preventDefault();
                    zoomIn();
                    return;
                }

                // -: Zoom out
                if (key === '-') {
                    event.preventDefault();
                    zoomOut();
                    return;
                }

                // 0: Reset zoom
                if (key === '0') {
                    event.preventDefault();
                    resetZoom();
                    return;
                }
            }

            document.addEventListener('DOMContentLoaded', initKeyboardShortcuts);

            // ========== Undo/Redo System ==========
            function saveState() {
                const state = {
                    nodes: JSON.parse(JSON.stringify(nodes)),
                    links: JSON.parse(JSON.stringify(links)),
                };
                undoStack.push(JSON.stringify(state));
                if (undoStack.length > MAX_UNDO) {
                    undoStack.shift();
                }
                redoStack.length = 0; // Clear redo stack on new action
                markUnsaved();
            }

            function undo() {
                if (undoStack.length === 0) {
                    WMNGToast.info('Nothing to undo', { duration: 1500 });
                    return;
                }
                // Save current state to redo stack
                const currentState = {
                    nodes: JSON.parse(JSON.stringify(nodes)),
                    links: JSON.parse(JSON.stringify(links)),
                };
                redoStack.push(JSON.stringify(currentState));

                // Restore previous state
                const previousState = JSON.parse(undoStack.pop());
                nodes = previousState.nodes;
                links = previousState.links;
                selectedNode = null;
                populateNodeProperties(null);
                renderEditor();
                renderLinksList();
                WMNGToast.info('Undone', { duration: 1000 });
            }

            function redo() {
                if (redoStack.length === 0) {
                    WMNGToast.info('Nothing to redo', { duration: 1500 });
                    return;
                }
                // Save current state to undo stack
                const currentState = {
                    nodes: JSON.parse(JSON.stringify(nodes)),
                    links: JSON.parse(JSON.stringify(links)),
                };
                undoStack.push(JSON.stringify(currentState));

                // Restore redo state
                const redoState = JSON.parse(redoStack.pop());
                nodes = redoState.nodes;
                links = redoState.links;
                selectedNode = null;
                populateNodeProperties(null);
                renderEditor();
                renderLinksList();
                WMNGToast.info('Redone', { duration: 1000 });
            }

            function loadMapData(id) {
                fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${id}/json`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (!data) return;
                    nodes = (data.nodes || []).map(node => ({
                        id: node.id,
                        dbId: node.id,
                        label: node.label,
                        x: node.x,
                        y: node.y,
                        deviceId: node.device_id,
                        interfaceId: node.meta?.interface_id || null,
                    }));

                    links = (data.links || []).map(link => ({
                        id: link.id,
                        dbId: link.id,
                        srcId: link.src,
                        dstId: link.dst,
                        portA: link.port_id_a || null,
                        portB: link.port_id_b || null,
                        bw: link.bandwidth_bps || null,
                        style: link.style || {},
                    }));

                    const mapTitle = document.getElementById('map-title');
                    const mapWidth = document.getElementById('map-width');
                    const mapHeight = document.getElementById('map-height');
                    if (mapTitle && data.title) mapTitle.value = data.title;
                    if (mapWidth && data.width) mapWidth.value = data.width;
                    if (mapHeight && data.height) mapHeight.value = data.height;

                    if (data.width && canvas) canvas.width = data.width;
                    if (data.height && canvas) canvas.height = data.height;

                    renderEditor();
                    renderLinksList();
                    renderNodesList();
                    updateStatusCounts();
                    markSaved();
                })
                .catch(error => {
                    console.error('Failed to load map data', error);
                });
            }

            function loadDevices() {
                const deviceSelect = document.getElementById('device-select');
                const interfaceSelect = document.getElementById('interface-select');
                const interfaceContainer = document.getElementById('interface-container');
                if (!deviceSelect || !interfaceSelect) return;

                deviceSelect.innerHTML = '<option value="">Choose a device...</option>';
                fetch('{{ url('plugin/WeathermapNG/api/devices') }}')
                    .then(r => r.json())
                    .then(data => {
                        const devices = Array.isArray(data) ? data : (data.devices || []);
                        devicesCache = devices;
                        devices.forEach(device => {
                            const option = document.createElement('option');
                            option.value = device.device_id;
                            option.textContent = device.hostname || device.sysName || `Device ${device.device_id}`;
                            deviceSelect.appendChild(option);
                        });
                    });

                deviceSelect.addEventListener('change', function() {
                    const deviceId = this.value;
                    interfaceSelect.innerHTML = '<option value="">Choose an interface...</option>';
                    if (!deviceId) {
                        if (interfaceContainer) interfaceContainer.style.display = 'none';
                        return;
                    }
                    if (interfaceContainer) interfaceContainer.style.display = 'block';
                    fetch(`{{ url('plugin/WeathermapNG/api/device') }}/${deviceId}/ports`)
                        .then(r => r.json())
                        .then(data => {
                            (data.ports || []).forEach(port => {
                                const option = document.createElement('option');
                                option.value = port.port_id;
                                option.textContent = port.ifName || port.ifIndex || `Port ${port.port_id}`;
                                interfaceSelect.appendChild(option);
                            });
                        });
                });
            }

            function addNode() {
                if (!canvas) return;
                saveState(); // Save for undo

                const deviceSelect = document.getElementById('device-select');
                const interfaceSelect = document.getElementById('interface-select');
                const deviceId = deviceSelect?.value ? parseInt(deviceSelect.value, 10) : null;
                const interfaceId = interfaceSelect?.value ? parseInt(interfaceSelect.value, 10) : null;
                const device = devicesCache.find(d => d.device_id === deviceId);
                const label = device?.hostname || device?.sysName || `Node ${nodes.length + 1}`;

                // Smart placement: spiral outward from center to avoid overlap
                const existingCount = nodes.length;
                const spacing = 60;
                const angle = existingCount * 0.8; // Golden angle approximation
                const radius = Math.sqrt(existingCount) * spacing;
                let x = canvas.width / 2 + Math.cos(angle) * radius;
                let y = canvas.height / 2 + Math.sin(angle) * radius;

                // Constrain to canvas bounds
                const nodeRadius = 12;
                x = Math.max(nodeRadius, Math.min(canvas.width - nodeRadius, x));
                y = Math.max(nodeRadius, Math.min(canvas.height - nodeRadius, y));

                const newNode = {
                    id: `node-${Date.now()}`,
                    dbId: null,
                    label: label,
                    x: x,
                    y: y,
                    deviceId: deviceId,
                    interfaceId: interfaceId,
                };

                nodes.push(newNode);
                selectedNode = newNode;
                renderEditor();
                renderLinksList();
                populateNodeProperties(newNode);
            }

            function toggleLinkMode() {
                linkMode = !linkMode;
                linkStart = null;
                updateLinkModeUI();
            }

            function updateLinkModeUI() {
                const btn = document.getElementById('link-mode-btn');
                if (btn) {
                    // Remove all state classes first
                    btn.classList.remove('active', 'link-active');

                    if (linkMode && linkStart) {
                        btn.classList.add('link-active'); // Orange pulsing - waiting for 2nd node
                        btn.title = 'Click another node to complete link';
                    } else if (linkMode) {
                        btn.classList.add('active'); // Blue - link mode on
                        btn.title = 'Click a node to start link';
                    } else {
                        btn.title = 'Link Mode - Click two nodes to connect';
                    }
                }
                // Change canvas cursor in link mode
                if (canvas) {
                    canvas.style.cursor = linkMode ? 'crosshair' : 'default';
                }
            }

            function clearCanvas() {
                if (!confirm('Clear all nodes and links?')) return;
                nodes = [];
                links = [];
                selectedNode = null;
                renderEditor();
                renderLinksList();
            }

            function openVersionSaveModal() {
                if (!mapId) {
                    WMNGToast.error('Save the map before creating versions.', { duration: 3000 });
                    return;
                }
                $('#versionModal').modal('show');
            }

            function saveVersion() {
                if (!mapId) {
                    WMNGToast.error('Save the map before creating versions.', { duration: 3000 });
                    return;
                }
                const versionName = document.getElementById('version-name').value.trim();
                const versionDesc = document.getElementById('version-desc').value.trim();
                const autoSave = document.getElementById('auto-save').checked;
                
                WMNGLoading.show('Saving version...');
                
                const payload = {
                    name: versionName || `v${Date.now()}`,
                    description: versionDesc,
                    auto_save: autoSave ? 1 : 0,
                };
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version saved successfully!', { duration: 3000 });
                        $('#versionModal').modal('hide');
                    } else {
                        WMNGToast.error('Failed to save version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error saving version: ' + error.message, { duration: 3000 });
                });
            }

            function openVersionHistory() {
                if (!mapId) {
                    WMNGToast.error('Save the map before viewing versions.', { duration: 3000 });
                    return;
                }
                $('#versionHistoryModal').modal('show');
                loadVersions();
            }

            function loadVersions() {
                if (!mapId) return;
                WMNGLoading.show('Loading versions...');
                
                const versionList = document.getElementById('version-list');
                versionList.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border-custom text-primary" style="width: 2rem; height: 2rem;"></div><small class="d-block mt-2">Loading versions...</small></div>';
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions', {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.versions)) {
                        const versions = data.versions;
                        let html = '';
                        
                        versions.forEach((version, idx) => {
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>v${versions.length - idx}</strong>
                                            <small class="text-muted">
                                                ${version.created_at_human}
                                                by ${version.created_by || 'Unknown'}
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="restoreVersion(${version.id})">
                                            <i class="fas fa-undo"></i> Restore
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteVersion(${version.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <hr>
                            `;
                        });
                        
                        versionList.innerHTML = html || '<div class="text-center text-muted py-3">No versions saved yet. <small>Save your first version to start tracking.</small></div>';
                    } else {
                        versionList.innerHTML = '<div class="text-center text-muted py-3">No versions found</div>';
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    versionList.innerHTML = '<div class="text-center text-danger py-3">Error loading versions: ' + error.message + '</div>';
                })
                .finally(() => {
                    WMNGLoading.hide();
                });
            }

            function restoreVersion(versionId) {
                if (!confirm('Restore to this version? Current changes will be lost.')) {
                    return;
                }
                
                WMNGLoading.show('Restoring version...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/' + versionId + '/restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version restored successfully!', { duration: 3000 });
                        $('#versionHistoryModal').modal('hide');
                        
                        if (data.snapshot) {
                            const snapshot = JSON.parse(data.snapshot);
                            if (snapshot && Array.isArray(snapshot.nodes)) {
                                nodes = snapshot.nodes;
                            }
                            if (snapshot && Array.isArray(snapshot.links)) {
                                links = snapshot.links;
                            }
                            if (snapshot && snapshot.map) {
                                const mapData = snapshot.map;
                                if (mapData.title) document.getElementById('map-title').value = mapData.title;
                                if (mapData.width) document.getElementById('map-width').value = mapData.width;
                                if (mapData.height) document.getElementById('map-height').value = mapData.height;
                            }
                        }
                        
                        renderEditor();
                    } else {
                        WMNGToast.error('Failed to restore version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error restoring version: ' + error.message, { duration: 3000 });
                });
            }

            function deleteVersion(versionId) {
                if (!confirm('Delete this version permanently? This cannot be undone.')) {
                    return;
                }
                
                WMNGLoading.show('Deleting version...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/' + versionId, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version deleted successfully!', { duration: 3000 });
                        loadVersions();
                    } else {
                        WMNGToast.error('Failed to delete version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error deleting version: ' + error.message, { duration: 3000 });
                });
            }

            function clearOldVersions() {
                if (!confirm('Delete all but the latest 20 versions?')) {
                    return;
                }
                
                WMNGLoading.show('Clearing old versions...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/cleanup', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        const count = data.deleted_count || 0;
                        WMNGToast.success('Cleared ' + count + ' old versions!', { duration: 3000 });
                        loadVersions();
                    } else {
                        WMNGToast.error('Failed to clear versions: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error clearing versions: ' + error.message, { duration: 3000 });
                });
            }

            function exportVersions() {
                WMNGLoading.show('Exporting versions...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/export', {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Versions exported successfully!', { duration: 3000 });
                        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'weathermap-versions-' + mapId + '.json';
                        a.click();
                    } else {
                        WMNGToast.error('Failed to export versions: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error exporting versions: ' + error.message, { duration: 3000 });
                });
            }

            function saveMap() {
                const mapName = document.getElementById('map-name').value.trim();
                const mapTitle = document.getElementById('map-title').value.trim();
                const mapWidth = parseInt(document.getElementById('map-width').value, 10);
                const mapHeight = parseInt(document.getElementById('map-height').value, 10);

                if (!mapName) {
                    WMNGToast.error('Please enter a map name.', { duration: 3000 });
                    return;
                }

                const baseHeaders = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                };

                if (!mapId) {
                    WMNGLoading.show('Creating map...');
                    const payload = { name: mapName, title: mapTitle, width: mapWidth, height: mapHeight };
                    fetch('{{ url("plugin/WeathermapNG/map") }}', {
                        method: 'POST',
                        headers: baseHeaders,
                        body: JSON.stringify(payload),
                    })
                    .then(r => r.json())
                    .then(data => {
                        WMNGLoading.hide();
                        if (data.success) {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                                return;
                            }
                            if (data.map?.id) {
                                window.location.href = '{{ url("plugin/WeathermapNG/editor") }}/' + data.map.id;
                                return;
                            }
                            WMNGToast.success('Map created successfully!', { duration: 3000 });
                        } else {
                            WMNGToast.error('Failed to create map: ' + (data.message || 'Unknown error'), { duration: 3000 });
                        }
                    })
                    .catch(error => {
                        WMNGLoading.hide();
                        WMNGToast.error('Error creating map: ' + error.message, { duration: 3000 });
                    });
                    return;
                }

                WMNGLoading.show('Saving map...');
                const payload = {
                    title: mapTitle,
                    options: {
                        width: mapWidth,
                        height: mapHeight,
                    },
                    nodes: nodes.map(n => ({
                        id: n.id,
                        label: n.label,
                        x: n.x,
                        y: n.y,
                        device_id: n.deviceId || null,
                        meta: { interface_id: n.interfaceId || null },
                    })),
                    links: links.map(l => ({
                        src_node_id: l.srcId,
                        dst_node_id: l.dstId,
                        port_id_a: l.portA || null,
                        port_id_b: l.portB || null,
                        bandwidth_bps: l.bw || null,
                        style: l.style || {},
                    })),
                };

                fetch('{{ url('plugin/WeathermapNG/api/maps') }}' + '/' + mapId + '/save', {
                    method: 'POST',
                    headers: baseHeaders,
                    body: JSON.stringify(payload),
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Map saved successfully!', { duration: 3000 });
                        markSaved();
                    } else {
                        WMNGToast.error('Error saving map: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(err => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error saving map: ' + err.message, { duration: 3000 });
                });
            }

function exportJson() {
    const mapName = document.getElementById('map-name').value.trim() || 'untitled';
    const mapTitle = document.getElementById('map-title').value.trim() || 'Network Map';
    const mapWidth = parseInt(document.getElementById('map-width').value, 10) || 800;
    const mapHeight = parseInt(document.getElementById('map-height').value, 10) || 600;

    const exportData = {
        name: mapName,
        title: mapTitle,
        width: mapWidth,
        height: mapHeight,
        options: { width: mapWidth, height: mapHeight },
        nodes: nodes.map(n => ({
            id: n.id,
            label: n.label,
            x: n.x,
            y: n.y,
            device_id: n.deviceId || null,
            meta: { interface_id: n.interfaceId || null }
        })),
        links: links.map(l => ({
            src: l.srcId,
            dst: l.dstId,
            port_id_a: l.portA || null,
            port_id_b: l.portB || null,
            bandwidth_bps: l.bw || null,
            style: l.style || {}
        }))
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = mapName + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    WMNGToast.success('Map exported as JSON', { duration: 2000 });
}

function populateNodeProperties(node) {
    const card = document.getElementById('node-properties-card');
    const label = document.getElementById('node-prop-label');
    const devSel = document.getElementById('node-prop-device');
    const intSel = document.getElementById('node-prop-interface');
    const saveBtn = document.getElementById('node-prop-save');
    const delBtn = document.getElementById('node-prop-delete');

    if (!node) {
        if (card) card.style.display = 'none';
        return;
    }

    // Show card and enable inputs
    if (card) card.style.display = 'block';
    [label, devSel, intSel, saveBtn, delBtn].forEach(el => { if (el) el.disabled = false; });

    // Populate label
    if (label) label.value = node.label || '';

    // Populate device dropdown from cache
    if (devSel) {
        devSel.innerHTML = '<option value="">No device</option>';
        devicesCache.forEach(device => {
            const opt = document.createElement('option');
            opt.value = device.device_id;
            opt.textContent = device.hostname || device.sysName || `Device ${device.device_id}`;
            if (node.deviceId == device.device_id) opt.selected = true;
            devSel.appendChild(opt);
        });

        // Update interface when device changes
        devSel.onchange = function() {
            node.deviceId = this.value ? parseInt(this.value, 10) : null;
            loadInterfacesForNode(node);
        };
    }

    // Load interfaces
    loadInterfacesForNode(node);
}

function loadInterfacesForNode(node) {
    const intSel = document.getElementById('node-prop-interface');
    if (!intSel) return;

    intSel.innerHTML = '<option value="">No interface</option>';
    if (!node.deviceId) return;

    fetch('{{ url('plugin/WeathermapNG/api/device') }}/' + node.deviceId + '/ports')
        .then(r => r.json())
        .then(data => {
            (data.ports || []).forEach(port => {
                const opt = document.createElement('option');
                opt.value = port.port_id;
                opt.textContent = port.ifName || `Port ${port.port_id}`;
                if (node.interfaceId == port.port_id) opt.selected = true;
                intSel.appendChild(opt);
            });
        });
}

function saveSelectedNode() {
    if (!selectedNode || !mapId || !selectedNode.dbId) return;
    const label = document.getElementById('node-prop-label').value.trim();
    const deviceId = document.getElementById('node-prop-device').value || null;
    const ifaceId = document.getElementById('node-prop-interface').value || null;
    const payload = { label: label, device_id: deviceId ? parseInt(deviceId, 10) : null, meta: { interface_id: ifaceId ? parseInt(ifaceId, 10) : null } };
    fetch('{{ url('plugin/WeathermapNG/map') }}' + '/' + mapId + '/node/' + selectedNode.dbId, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            selectedNode.label = label;
            selectedNode.deviceId = payload.device_id;
            selectedNode.interfaceId = payload.meta.interface_id;
            renderEditor();
        } else {
            alert('Failed to save node: ' + (d.message || 'Unknown error'));
        }
    });
}

function deleteSelectedNode() {
    if (!selectedNode) return;
    if (!confirm('Delete this node and attached links?')) return;
    saveState(); // Save for undo

    const nodeToDelete = selectedNode;
    const nodeId = nodeToDelete.id || nodeToDelete.dbId;

    // Helper to clean up after deletion
    function finishDelete() {
        nodes = nodes.filter(n => n !== nodeToDelete);
        links = links.filter(l => l.srcId !== nodeId && l.dstId !== nodeId && l.srcId !== nodeToDelete.dbId && l.dstId !== nodeToDelete.dbId);
        selectedNode = null;
        populateNodeProperties(null);
        renderEditor();
        renderLinksList();
    }

    // If node is saved in DB, delete from server
    if (mapId && nodeToDelete.dbId) {
        fetch('{{ url('plugin/WeathermapNG/map') }}' + '/' + mapId + '/node/' + nodeToDelete.dbId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(() => finishDelete());
    } else {
        // Node only exists locally
        finishDelete();
    }
}

function renderLinksList() {
    const c = document.getElementById('links-list');
    if (!c) return;
    if (!links.length) { c.innerHTML = '<small class="text-muted">No links yet</small>'; return; }
    const item = (l, idx) => {
        const a = findNodeById(l.srcId); const b = findNodeById(l.dstId);
        const aL = a ? a.label : l.srcId; const bL = b ? b.label : l.dstId;
        return `<div class=\"d-flex align-items-center justify-content-between mb-2\">
            <div><i class=\"fas fa-link\"></i> ${aL} → ${bL}</div>
            <div class=\"btn-group btn-group-sm\">
                <button class=\"btn btn-outline-secondary\" onclick=\"openLinkModal(${idx})\"><i class=\"fas fa-edit\"></i></button>
                <button class=\"btn btn-outline-danger\" onclick=\"deleteLink(${idx})\"><i class=\"fas fa-trash\"></i></button>
            </div>
        </div>`
    };
    c.innerHTML = links.map((l,i) => item(l,i)).join('');
}

// ========== Link Modal Functions ==========
let currentLinkIndex = null;

function openLinkModal(linkIndex) {
    currentLinkIndex = linkIndex;
    const link = links[linkIndex];
    if (!link) return;

    const srcNode = findNodeById(link.srcId);
    const dstNode = findNodeById(link.dstId);
    const srcPortSelect = document.getElementById('link-src-port');
    const dstPortSelect = document.getElementById('link-dst-port');
    const bandwidthInput = document.getElementById('link-bandwidth');
    const deleteBtn = document.getElementById('delete-link-btn');

    // Reset dropdowns
    srcPortSelect.innerHTML = '<option value="">Select port...</option>';
    dstPortSelect.innerHTML = '<option value="">Select port...</option>';
    bandwidthInput.value = link.bw || '';
    deleteBtn.style.display = 'inline-block';

    // Load source node ports
    if (srcNode && srcNode.deviceId) {
        fetch('{{ url('plugin/WeathermapNG/api/device') }}/' + srcNode.deviceId + '/ports')
            .then(r => r.json())
            .then(data => {
                (data.ports || []).forEach(port => {
                    const opt = document.createElement('option');
                    opt.value = port.port_id;
                    opt.textContent = port.ifName || `Port ${port.port_id}`;
                    if (link.portA == port.port_id) opt.selected = true;
                    srcPortSelect.appendChild(opt);
                });
            });
    }

    // Load destination node ports
    if (dstNode && dstNode.deviceId) {
        fetch('{{ url('plugin/WeathermapNG/api/device') }}/' + dstNode.deviceId + '/ports')
            .then(r => r.json())
            .then(data => {
                (data.ports || []).forEach(port => {
                    const opt = document.createElement('option');
                    opt.value = port.port_id;
                    opt.textContent = port.ifName || `Port ${port.port_id}`;
                    if (link.portB == port.port_id) opt.selected = true;
                    dstPortSelect.appendChild(opt);
                });
            });
    }

    $('#linkModal').modal('show');
}

function saveLink() {
    if (currentLinkIndex === null) return;
    const link = links[currentLinkIndex];
    if (!link) return;

    link.portA = document.getElementById('link-src-port').value || null;
    link.portB = document.getElementById('link-dst-port').value || null;
    link.bw = parseInt(document.getElementById('link-bandwidth').value, 10) || null;

    $('#linkModal').modal('hide');
    currentLinkIndex = null;
    renderLinksList();
    WMNGToast.success('Link updated!', { duration: 2000 });
}

function deleteLink(linkIndex) {
    if (!confirm('Delete this link?')) return;
    saveState(); // Save for undo
    links.splice(linkIndex, 1);
    renderEditor();
    renderLinksList();
    $('#linkModal').modal('hide');
    currentLinkIndex = null;
}

// Wire up modal buttons
document.addEventListener('DOMContentLoaded', function() {
    const saveLinkBtn = document.getElementById('save-link-btn');
    const deleteLinkBtn = document.getElementById('delete-link-btn');
    if (saveLinkBtn) saveLinkBtn.addEventListener('click', saveLink);
    if (deleteLinkBtn) deleteLinkBtn.addEventListener('click', () => {
        if (currentLinkIndex !== null) deleteLink(currentLinkIndex);
    });
});

// ========== Auto-Save Implementation ==========
let autoSaveInterval = null;

function toggleAutoSave(enabled) {
    if (autoSaveInterval) {
        clearInterval(autoSaveInterval);
        autoSaveInterval = null;
    }
    if (enabled && mapId) {
        autoSaveInterval = setInterval(() => {
            saveMap();
            WMNGToast.info('Auto-saved', { duration: 1500 });
        }, 5 * 60 * 1000); // 5 minutes
    }
}

// Wire up auto-save toggle (toolbar version)
document.addEventListener('DOMContentLoaded', function() {
    const autoSaveToggle = document.getElementById('auto-save-toggle');
    if (autoSaveToggle) {
        autoSaveToggle.addEventListener('change', function() {
            toggleAutoSave(this.checked);
        });
        // Start auto-save if checked by default and map exists
        if (autoSaveToggle.checked && mapId) {
            toggleAutoSave(true);
        }
    }
});

// ========== Status Updates ==========
let hasUnsavedChanges = false;

function updateStatusCounts() {
    const nodeCount = document.getElementById('node-count');
    const linkCount = document.getElementById('link-count');
    const nodesBadge = document.getElementById('nodes-badge');
    const linksBadge = document.getElementById('links-badge');

    if (nodeCount) nodeCount.textContent = nodes.length;
    if (linkCount) linkCount.textContent = links.length;
    if (nodesBadge) nodesBadge.textContent = nodes.length;
    if (linksBadge) linksBadge.textContent = links.length;
}

function markUnsaved() {
    hasUnsavedChanges = true;
    const indicator = document.getElementById('unsaved-indicator');
    if (indicator) indicator.style.display = 'inline';
}

function markSaved() {
    hasUnsavedChanges = false;
    const indicator = document.getElementById('unsaved-indicator');
    if (indicator) indicator.style.display = 'none';
}

// ========== Nodes List ==========
function renderNodesList() {
    const container = document.getElementById('nodes-list');
    if (!container) return;

    if (!nodes.length) {
        container.innerHTML = '<small class="text-muted">No nodes yet</small>';
        return;
    }

    const items = nodes.map((node, idx) => {
        const isSelected = node === selectedNode;
        return `<div class="d-flex align-items-center justify-content-between py-1" style="cursor: pointer;${isSelected ? ' background: var(--editor-list-selected); border-radius: 3px;' : ''} onclick="selectNodeByIndex(${idx})">
            <small class="${isSelected ? 'font-weight-bold' : ''}">
                <i class="fas fa-circle ${isSelected ? 'text-primary' : 'text-success'}" style="font-size: 8px;"></i>
                ${node.label || 'Node ' + (idx + 1)}
            </small>
            <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="event.stopPropagation(); deleteNodeByIndex(${idx})" title="Delete">
                <i class="fas fa-times" style="font-size: 10px;"></i>
            </button>
        </div>`;
    });

    container.innerHTML = items.join('');
}

function selectNodeByIndex(idx) {
    if (idx >= 0 && idx < nodes.length) {
        selectedNode = nodes[idx];
        populateNodeProperties(selectedNode);
        updateToolbarState();
        renderEditor();
        renderNodesList();
    }
}

function deleteNodeByIndex(idx) {
    if (idx >= 0 && idx < nodes.length) {
        const node = nodes[idx];
        if (!confirm(`Delete node "${node.label}"?`)) return;
        saveState();

        const nodeId = node.id || node.dbId;
        nodes.splice(idx, 1);
        links = links.filter(l => l.srcId !== nodeId && l.dstId !== nodeId && l.srcId !== node.dbId && l.dstId !== node.dbId);

        if (selectedNode === node) {
            selectedNode = null;
            populateNodeProperties(null);
        }

        markUnsaved();
        renderEditor();
        renderNodesList();
        renderLinksList();
        updateStatusCounts();
        updateToolbarState();
    }
}

// ========== Duplicate Node ==========
function duplicateSelectedNode() {
    if (!selectedNode) return;
    saveState();

    const newNode = {
        id: `node-${Date.now()}`,
        dbId: null,
        label: selectedNode.label + ' (copy)',
        x: Math.min(canvas.width - 12, selectedNode.x + 30),
        y: Math.min(canvas.height - 12, selectedNode.y + 30),
        deviceId: selectedNode.deviceId,
        interfaceId: selectedNode.interfaceId,
    };

    nodes.push(newNode);
    selectedNode = newNode;

    markUnsaved();
    renderEditor();
    renderNodesList();
    populateNodeProperties(newNode);
    updateStatusCounts();
    updateToolbarState();
    WMNGToast.success('Node duplicated', { duration: 1500 });
}

// ========== Toolbar State ==========
function updateToolbarState() {
    const duplicateBtn = document.getElementById('duplicate-btn');
    const deleteBtn = document.getElementById('delete-node-btn');
    const hasSelection = selectedNode !== null;

    if (duplicateBtn) duplicateBtn.disabled = !hasSelection;
    if (deleteBtn) deleteBtn.disabled = !hasSelection;
}

// Initial state updates
document.addEventListener('DOMContentLoaded', function() {
    updateStatusCounts();
    renderNodesList();
    updateToolbarState();
});
</script>
@endsection
