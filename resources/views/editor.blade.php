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
.editor-tag {
    display: inline-block;
    font-size: 11px;
    background: var(--editor-panel-header-bg);
    color: var(--editor-text-muted);
    border: 1px solid var(--editor-sidebar-border);
    border-radius: 12px;
    padding: 2px 8px;
    margin: 0 4px 4px 0;
    text-transform: lowercase;
}
.map-tags-preview { margin-top: 6px; }
.editor-sidebar .form-control-sm {
    font-size: 12px; background: var(--editor-input-bg); border-color: var(--editor-input-border);
    color: var(--editor-input-text);
}
.editor-sidebar .form-control-sm:focus {
    background: var(--editor-input-bg); border-color: var(--editor-accent);
    color: var(--editor-input-text); box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25);
}

/* Device autocomplete */
.ac-wrap { position: relative; }
.ac-input { font-size: 12px; background: var(--editor-input-bg); border: 1px solid var(--editor-input-border);
    color: var(--editor-input-text); padding: 4px 8px; border-radius: 4px; width: 100%; box-sizing: border-box; }
.ac-input:focus { outline: none; border-color: var(--editor-accent); box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25); }
.ac-dropdown { position: absolute; left: 0; right: 0; top: 100%; max-height: 220px; overflow-y: auto;
    background: var(--editor-sidebar-bg); border: 1px solid var(--editor-input-border); border-radius: 4px;
    z-index: 1200; display: none; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
.ac-dropdown.show { display: block; }
.ac-item { padding: 6px 10px; font-size: 12px; color: var(--editor-text); cursor: pointer; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; }
.ac-item:hover, .ac-item.active { background: var(--editor-list-hover); }
.ac-empty { padding: 8px 10px; font-size: 11px; color: var(--editor-text-muted); font-style: italic; }
.ac-loading { padding: 8px 10px; font-size: 11px; color: var(--editor-text-muted); }
.ac-status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
.ac-device-name { font-size: 12px; color: var(--editor-text); flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 4px 8px; }
.ac-change-btn { font-size: 11px; padding: 2px 6px; margin-left: 4px; cursor: pointer; flex-shrink: 0; }
.ac-selected-row { display: flex; align-items: center; gap: 4px; }

/* Node/link list items */
#nodes-list > div:hover, #links-list > div:hover {
    background-color: var(--editor-list-hover); border-radius: 3px;
}
#nodes-list, #links-list { color: var(--editor-text); }

/* Tool button states */
.tool-btn.link-active { background: var(--editor-accent-orange) !important; color: #fff !important; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
@media (prefers-reduced-motion: reduce) {
    .tool-btn.link-active { animation: none; }
}

/* Canvas styling */
#map-canvas { background: var(--editor-canvas-surface); box-shadow: 0 2px 8px var(--editor-canvas-shadow); display: block; width: 100%; height: auto; }

/* Minimap */
#editor-minimap {
    background: rgba(255,255,255,0.95); border: 1px solid #ccc;
    border-radius: 4px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    position: absolute; bottom: 20px; right: 20px; z-index: 100;
}
.editor-container.dark-theme #editor-minimap { background: var(--editor-canvas-surface); border-color: var(--editor-sidebar-border); }

/* Selected node panel header */
.panel-header-selected { background: var(--editor-accent); color: #fff; }

/* Scrollable list panels */
.panel-list-scroll { max-height: 120px; overflow-y: auto; }

/* Toolbox spacer */
.toolbox-spacer { flex: 1; }

/* Node list item */
.node-list-item { cursor: pointer; }
.node-list-item.selected { background: var(--editor-list-selected); border-radius: 3px; }
.node-list-item .node-dot { font-size: 8px; }
.node-list-item .node-delete-icon { font-size: 10px; }

/* Responsive: stack sidebar below canvas on narrow screens */
@media (max-width: 768px) {
    .editor-container { height: auto; min-height: 400px; flex-direction: column; }
    .editor-toolbox { flex-direction: row; width: 100%; padding: 4px 8px; flex-wrap: wrap; }
    .editor-toolbox .tool-btn { width: 32px; height: 32px; font-size: 14px; }
    .editor-toolbox .tool-divider { width: 1px; height: 32px; margin: 0 4px; }
    .editor-canvas-area { min-height: 300px; }
    .editor-topbar { padding: 4px 8px; flex-wrap: wrap; gap: 4px; }
    .editor-topbar .text-muted { font-size: 11px; }
    .editor-sidebar { width: 100%; max-height: 300px; border-left: none; border-top: 1px solid var(--editor-sidebar-border); }
}

/* Confirm modal must appear above the version history modal */
#editorConfirmModal { z-index: 1060 !important; }
</style>
@endpush

@section('content')
<div class="editor-container">
    <!-- Left Toolbox -->
    <div class="editor-toolbox">
        <button type="button" class="tool-btn" onclick="addNode()" title="Add Node (from sidebar device)" aria-label="Add node from selected device">
            <i class="fas fa-plus"></i>
        </button>
        <button type="button" class="tool-btn" id="link-mode-btn" onclick="toggleLinkMode()" title="Link Mode - Click two nodes to connect" aria-label="Toggle link mode">
            <i class="fas fa-link"></i>
        </button>
        <div class="tool-divider"></div>
        <button type="button" class="tool-btn" id="snap-grid-btn" onclick="toggleSnapToGrid()" title="Snap to Grid" aria-label="Toggle snap to grid">
            <i class="fas fa-th"></i>
        </button>
        <div class="tool-divider"></div>
        <button type="button" class="tool-btn" id="select-mode-btn" onclick="toggleSelectionMode()" title="Multi-select" aria-label="Toggle multi-select (rubber-band) mode">
            <i class="fas fa-vector-square"></i>
        </button>
        <button type="button" class="tool-btn" onclick="duplicateSelectedNode()" title="Duplicate Selected" id="duplicate-btn" aria-label="Duplicate selected node" disabled>
            <i class="fas fa-copy"></i>
        </button>
        <button type="button" class="tool-btn" onclick="deleteSelectedNode()" title="Delete Selected" id="delete-node-btn" aria-label="Delete selected node" disabled>
            <i class="fas fa-trash"></i>
        </button>
        <button type="button" class="tool-btn" onclick="bulkDeleteSelected()" title="Delete Selected Nodes" id="bulk-delete-btn" aria-label="Bulk delete selected nodes" disabled>
            <i class="fas fa-trash-can"></i>
        </button>
        <div class="tool-divider"></div>
        <button type="button" class="tool-btn" onclick="undo()" title="Undo (Ctrl+Z)" aria-label="Undo">
            <i class="fas fa-undo"></i>
        </button>
        <button type="button" class="tool-btn" onclick="redo()" title="Redo (Ctrl+Y)" aria-label="Redo">
            <i class="fas fa-redo"></i>
        </button>
        <div class="tool-divider"></div>
        <button type="button" class="tool-btn" id="autodiscover-btn" onclick="autoDiscoverMap()" title="Auto-discover from LLDP/CDP" aria-label="Auto-discover nodes and links">
            <i class="fas fa-network-wired"></i>
        </button>
        <div class="toolbox-spacer"></div>
        <button type="button" class="tool-btn" onclick="zoomIn()" title="Zoom In (+)" aria-label="Zoom in">
            <i class="fas fa-search-plus"></i>
        </button>
        <button type="button" class="tool-btn" onclick="zoomOut()" title="Zoom Out (-)" aria-label="Zoom out">
            <i class="fas fa-search-minus"></i>
        </button>
        <button type="button" class="tool-btn" onclick="resetZoom()" title="Reset Zoom (0)" aria-label="Reset zoom">
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
                <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info mr-2" title="Preview" aria-label="Preview map">
                    <i class="fas fa-eye"></i>
                </a>
                @endif
                <button type="button" class="btn btn-sm btn-success" onclick="saveMap()">
                    <i class="fas fa-save"></i> Save
                </button>
                @if($map)
                <button type="button" class="btn btn-sm btn-outline-info ml-1" id="versionHistoryBtn" aria-label="Version history">
                    <i class="fas fa-history"></i> Versions
                </button>
                @endif
            </div>
        </div>

        <!-- Canvas -->
        <div class="editor-canvas-wrap">
            <canvas id="map-canvas"
                    width="{{ $map->width ?? config('weathermapng.default_width', 800) }}"
                    height="{{ $map->height ?? config('weathermapng.default_height', 600) }}">
            </canvas>
            <!-- Minimap -->
            <canvas id="editor-minimap" width="150" height="100">
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
                    <div class="ac-wrap" id="device-ac-wrap">
                        <input type="text" class="ac-input" id="device-search"
                            placeholder="Search devices..." autocomplete="off" role="combobox"
                            aria-expanded="false" aria-autocomplete="list" aria-controls="device-ac-results">
                        <input type="hidden" id="device-select" value="">
                        <div class="ac-dropdown" id="device-ac-results" role="listbox"></div>
                    </div>
                </div>
                <div class="form-group mb-2" id="interface-container" style="display: none;">
                    <label class="form-label">Interface</label>
                    <select class="form-control form-control-sm" id="interface-select">
                        <option value="">Select interface...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-success btn-sm btn-block" onclick="addNode()" aria-label="Add node to canvas">
                    <i class="fas fa-plus"></i> Add to Canvas
                </button>
            </div>
        </div>

        <!-- Selected Node -->
        <div class="panel" id="node-properties-card" style="display: none;">
            <div class="panel-header panel-header-selected">
                <i class="fas fa-circle mr-1"></i> Selected Node
            </div>
            <div class="panel-body">
                <div class="form-group mb-2">
                    <label class="form-label">Label</label>
                    <input type="text" class="form-control form-control-sm" id="node-prop-label">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Device</label>
                    <div class="ac-selected-row">
                        <span class="ac-device-name" id="node-prop-device-name">No device</span>
                        <input type="hidden" id="node-prop-device" value="">
                        <button type="button" class="btn btn-sm btn-outline-secondary ac-change-btn"
                            id="node-prop-device-change" onclick="openNodeDeviceAutocomplete()">Change</button>
                    </div>
                    <div class="ac-wrap" id="node-device-ac-wrap" style="display:none;">
                        <input type="text" class="ac-input" id="node-device-search"
                            placeholder="Search devices..." autocomplete="off" role="combobox"
                            aria-expanded="false" aria-autocomplete="list" aria-controls="node-device-ac-results">
                        <div class="ac-dropdown" id="node-device-ac-results" role="listbox"></div>
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label class="form-label">Interface</label>
                    <select class="form-control form-control-sm" id="node-prop-interface">
                        <option value="">No interface</option>
                    </select>
                </div>
                <div class="btn-group btn-group-sm d-flex">
                    <button type="button" class="btn btn-primary" onclick="saveSelectedNode()" aria-label="Apply node changes"><i class="fas fa-check"></i> Apply</button>
                    <button type="button" class="btn btn-secondary" onclick="duplicateSelectedNode()" title="Duplicate" aria-label="Duplicate selected node"><i class="fas fa-copy"></i></button>
                    <button type="button" class="btn btn-info" id="node-view-device-btn" onclick="viewSelectedNodeDevice()" title="Open device in LibreNMS" aria-label="Open device in LibreNMS" style="display:none;"><i class="fas fa-external-link-alt"></i></button>
                    <button type="button" class="btn btn-danger" onclick="deleteSelectedNode()" title="Delete" aria-label="Delete selected node"><i class="fas fa-trash"></i></button>
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
                <div class="form-group mb-2">
                    <label class="form-label" for="map-tags">Tags</label>
                    <input type="text" class="form-control form-control-sm" id="map-tags"
                           value="{{ implode(', ', $map->tags ?? []) }}" placeholder="e.g. core, wan, datacenter">
                    <small class="text-muted">Comma-separated letters, numbers, hyphens, underscores</small>
                    <div id="map-tags-preview" class="map-tags-preview"></div>
                </div>
            </div>
        </div>

        <!-- Default Styles -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-paint-brush mr-1"></i> Default Styles</div>
            <div class="panel-body">
                <div class="form-group mb-2">
                    <label class="form-label" for="default-node-color">Node Color</label>
                    <input type="text" class="form-control form-control-sm" id="default-node-color" placeholder="#28a745" aria-label="Default node color">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label" for="default-node-label-color">Node Label Color</label>
                    <input type="text" class="form-control form-control-sm" id="default-node-label-color" placeholder="#212529" aria-label="Default node label color">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label" for="default-link-color">Link Color</label>
                    <input type="text" class="form-control form-control-sm" id="default-link-color" placeholder="#6c757d" aria-label="Default link color">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label" for="default-link-width">Link Width</label>
                    <input type="number" class="form-control form-control-sm" id="default-link-width" min="0.5" max="20" step="0.5" placeholder="2" aria-label="Default link width">
                </div>
                <div class="form-group mb-2">
                    <label class="form-label" for="default-link-via-style">Link Style</label>
                    <select class="form-control form-control-sm" id="default-link-via-style" aria-label="Default link via style">
                        <option value="">Use site default</option>
                        <option value="straight">Straight</option>
                        <option value="angled">Angled</option>
                        <option value="curved">Curved</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Nodes List -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-sitemap mr-1"></i> Nodes <span class="badge badge-secondary float-right" id="nodes-badge">0</span></div>
            <div class="panel-body panel-list-scroll" id="nodes-list">
                <small class="text-muted">No nodes yet</small>
            </div>
        </div>

        <!-- Links List -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-link mr-1"></i> Links <span class="badge badge-secondary float-right" id="links-badge">0</span></div>
            <div class="panel-body panel-list-scroll" id="links-list">
                <small class="text-muted">No links yet</small>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-header"><i class="fas fa-ellipsis-h mr-1"></i> Actions</div>
            <div class="panel-body">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-block mb-1" onclick="exportJson()" aria-label="Export map as JSON">
                    <i class="fas fa-download"></i> Export JSON
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm btn-block" onclick="clearCanvas()" aria-label="Clear canvas">
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
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
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
                    <label class="form-label">Bandwidth</label>
                    <div class="input-group">
                        <input type="number" id="link-bandwidth-value" class="form-control" min="0" step="any" placeholder="e.g. 1">
                        <select id="link-bandwidth-unit" class="form-control" style="max-width: 120px;">
                            <option value="bps">bps</option>
                            <option value="Kbps">Kbps</option>
                            <option value="Mbps">Mbps</option>
                            <option value="Gbps">Gbps</option>
                            <option value="KBps">KBps</option>
                            <option value="MBps">MBps</option>
                            <option value="GBps">GBps</option>
                        </select>
                    </div>
                    <small class="form-text text-muted">Stored internally as bits per second.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Via Style</label>
                    <select id="link-via-style" class="form-control">
                        <option value="straight">Straight</option>
                        <option value="angled">Angled</option>
                        <option value="curved">Curved</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="view-port-btn" style="display:none;" onclick="viewLinkPort()">
                    <i class="fas fa-external-link-alt"></i> View Port
                </button>
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
        <!-- Editor Confirmation Modal -->
        <div class="modal fade" id="editorConfirmModal" tabindex="-1" role="dialog" aria-labelledby="editorConfirmTitle" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editorConfirmTitle">Confirm Action</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body" id="editorConfirmBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="editorConfirmCancel" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="editorConfirmAction">Continue</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version History Modal -->
        <div class="modal fade" id="versionHistoryModal" tabindex="-1" role="dialog" aria-labelledby="versionHistoryTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="versionHistoryTitle">Version History</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Save Version Form -->
                        <div class="form-inline mb-3">
                            <input type="text" class="form-control form-control-sm mr-2" id="versionNameInput" placeholder="Version name..." maxlength="100" aria-label="Version name">
                            <button type="button" class="btn btn-sm btn-success" id="saveVersionBtn" aria-label="Save version"><i class="fas fa-save"></i> Save Version</button>
                        </div>
                        <!-- Version List -->
                        <div id="versionList" class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <p class="text-muted text-center">Loading versions...</p>
                        </div>
                        <!-- Compare Diff Area -->
                        <div id="versionDiffArea" class="mt-3" style="display:none;">
                            <h6>Comparison: <span id="diffVersion1Name"></span> &rarr; <span id="diffVersion2Name"></span></h6>
                            <ul id="diffSummary" class="list-unstyled"></ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        @endsection

        @section('scripts')
        <script src="{{ asset('plugins/WeathermapNG/resources/js/wmng-common.js') }}"></script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/ui-helpers.js') }}"></script>
        <script>
            // Shared polyfill + theme (wmng-common.js). Safe if common fails to load.
            if (window.WMNG && typeof WMNG.ensureUiHelpers === 'function') {
                WMNG.ensureUiHelpers();
            } else {
                window.WMNGLoading = window.WMNGLoading || {};
                ['show', 'hide', 'toggle'].forEach(m => {
                    if (typeof window.WMNGLoading[m] !== 'function') {
                        window.WMNGLoading[m] = function() {};
                    }
                });
                window.WMNGToast = window.WMNGToast || {};
                ['success','error','warning','info'].forEach(m => {
                    if (typeof window.WMNGToast[m] !== 'function') {
                        window.WMNGToast[m] = (msg) => console[m === 'error' ? 'error' : 'log'](msg);
                    }
                });
            }
            if (window.WMNG && typeof WMNG.observeTheme === 'function') {
                WMNG.observeTheme('.editor-container');
            }
        </script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-state.js') }}"></script>
        <script>
            // Editor bootstrap: inject page-specific values (map id, editor
            // link style, LibreNMS route URLs) into the shared EditorState
            // before the editor modules read them at parse time.
            window.WMNG = window.WMNG || {};
            window.WMNG.EditorState = window.WMNG.EditorState || {};
            const __WMNGState = window.WMNG.EditorState;
            __WMNGState.mapId = {{ $map->id ?? 'null' }};
            __WMNGState.editorConfig = {
                link_style: '{{ config('weathermapng.link_style', 'straight') }}',
            };
            __WMNGState.uris = {
                map: '{{ url('plugin/WeathermapNG/map') }}',
                maps: '{{ url('plugin/WeathermapNG/api/maps') }}',
                versions: '{{ url('plugin/WeathermapNG/api/versions') }}',
                editor: '{{ url('plugin/WeathermapNG/editor') }}',
                devices: '{{ url('plugin/WeathermapNG/api/devices') }}',
                device: '{{ url('plugin/WeathermapNG/api/device') }}',
                devicePage: '{{ url('device') }}',
                graph: '{{ url('graph') }}',
            };
        </script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-canvas.js') }}"></script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-nodes.js') }}"></script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-links.js') }}"></script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-ui.js') }}"></script>
        <script src="{{ asset('plugins/WeathermapNG/resources/js/editor-versions.js') }}"></script>
@endsection
