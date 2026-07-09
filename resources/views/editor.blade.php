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
        <button type="button" class="tool-btn" onclick="duplicateSelectedNode()" title="Duplicate Selected" id="duplicate-btn" aria-label="Duplicate selected node" disabled>
            <i class="fas fa-copy"></i>
        </button>
        <button type="button" class="tool-btn" onclick="deleteSelectedNode()" title="Delete Selected" id="delete-node-btn" aria-label="Delete selected node" disabled>
            <i class="fas fa-trash"></i>
        </button>
        <div class="tool-divider"></div>
        <button type="button" class="tool-btn" onclick="undo()" title="Undo (Ctrl+Z)" aria-label="Undo">
            <i class="fas fa-undo"></i>
        </button>
        <button type="button" class="tool-btn" onclick="redo()" title="Redo (Ctrl+Y)" aria-label="Redo">
            <i class="fas fa-redo"></i>
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
                    <button type="button" class="btn btn-primary" onclick="saveSelectedNode()" aria-label="Apply node changes"><i class="fas fa-check"></i> Apply</button>
                    <button type="button" class="btn btn-secondary" onclick="duplicateSelectedNode()" title="Duplicate" aria-label="Duplicate selected node"><i class="fas fa-copy"></i></button>
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
        <script>
            let pendingEditorConfirmAction = null;
            let pendingEditorCancelAction = null;
            let editorConfirmAccepted = false;

            function showEditorConfirm(title, message, confirmText, confirmClass, onConfirm, onCancel = null) {
                pendingEditorConfirmAction = onConfirm;
                pendingEditorCancelAction = onCancel;
                editorConfirmAccepted = false;

                document.getElementById('editorConfirmTitle').textContent = title;
                document.getElementById('editorConfirmBody').textContent = message;

                const actionButton = document.getElementById('editorConfirmAction');
                actionButton.textContent = confirmText;
                actionButton.className = `btn ${confirmClass}`;

                $('#editorConfirmModal').modal('show');
                // Bump the confirm modal's backdrop above the version modal after it's inserted
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].style.zIndex = 1055;
                    }
                }, 0);
            }

            document.getElementById('editorConfirmAction')?.addEventListener('click', function() {
                const action = pendingEditorConfirmAction;
                pendingEditorConfirmAction = null;
                pendingEditorCancelAction = null;
                editorConfirmAccepted = true;
                $('#editorConfirmModal').modal('hide');

                if (typeof action === 'function') {
                    action();
                }
            });

            $('#editorConfirmModal').on('hidden.bs.modal', function() {
                if (!editorConfirmAccepted && typeof pendingEditorCancelAction === 'function') {
                    pendingEditorCancelAction();
                }
                pendingEditorConfirmAction = null;
                pendingEditorCancelAction = null;
                editorConfirmAccepted = false;
            });

            let mapId = {{ $map->id ?? 'null' }};
            let nodes = [];
            let links = [];
            let selectedNode = null;
            // Load gate: for existing maps, saveMap() must not POST until the
            // /json load resolves — otherwise the empty client arrays would be
            // sent to the destructive replaceMapContent() backend, wiping the map.
            let mapDataLoaded = !mapId;       // new maps have nothing to load
            let mapDataLoadFailed = false;
            let devicesCache = [];
            let canvas = null;
            let ctx = null;
            let isDragging = false;
            let dragOffset = { x: 0, y: 0 };
            let linkMode = false;
            let linkStart = null;

            const editorConfig = {
                link_style: '{{ config('weathermapng.link_style', 'straight') }}'
            };

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
                if (window.WMNG && typeof WMNG.getCsrfToken === 'function') {
                    return WMNG.getCsrfToken();
                }
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            }

            // Escape user-controlled strings before interpolating into innerHTML (XSS hardening).
            function escapeHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

            const BANDWIDTH_UNITS = {
                bps: 1,
                Kbps: 1000,
                Mbps: 1000 * 1000,
                Gbps: 1000 * 1000 * 1000,
                KBps: 8 * 1000,
                MBps: 8 * 1000 * 1000,
                GBps: 8 * 1000 * 1000 * 1000,
            };

            function bandwidthInputsToBps(value, unit) {
                const num = parseFloat(value);
                if (isNaN(num) || num < 0) return null;
                const factor = BANDWIDTH_UNITS[unit] || 1;
                return Math.round(num * factor);
            }

            function setBandwidthInputsFromBps(bps, valueInput, unitSelect) {
                if (!valueInput || !unitSelect) return;
                if (!bps || isNaN(bps) || bps <= 0) {
                    valueInput.value = '';
                    unitSelect.value = 'bps';
                    return;
                }
                const unitsBySize = ['GBps', 'Gbps', 'MBps', 'Mbps', 'KBps', 'Kbps', 'bps'];
                for (const unit of unitsBySize) {
                    const factor = BANDWIDTH_UNITS[unit];
                    const val = bps / factor;
                    if (Math.abs(val - Math.round(val)) < 0.0001 && val >= 1) {
                        valueInput.value = Number.isInteger(val) ? val : parseFloat(val.toFixed(3));
                        unitSelect.value = unit;
                        return;
                    }
                }
                valueInput.value = bps;
                unitSelect.value = 'bps';
            }

            function parseMapTags(raw) {
                if (typeof raw !== 'string' || !raw.trim()) return [];
                const tags = raw.split(',')
                    .map(t => t.trim().toLowerCase())
                    .filter(t => /^[a-z0-9_-]+$/.test(t));
                return Array.from(new Set(tags));
            }

            function renderMapTagsPreview(tags) {
                const el = document.getElementById('map-tags-preview');
                if (!el) return;
                if (!Array.isArray(tags) || tags.length === 0) {
                    el.innerHTML = '';
                    return;
                }
                el.innerHTML = tags.map(t => `<span class="editor-tag">${escapeHtml(t)}</span>`).join(' ');
            }

            document.getElementById('map-tags')?.addEventListener('input', (e) => {
                renderMapTagsPreview(parseMapTags(e.target.value));
            });

            function getDefaultNodeStyle() {
                const colorInput = document.getElementById('default-node-color');
                const labelColorInput = document.getElementById('default-node-label-color');
                const style = {};
                if (colorInput && /^#[0-9a-fA-F]{6}$/.test(colorInput.value)) {
                    style.color = colorInput.value.trim().toLowerCase();
                }
                if (labelColorInput && /^#[0-9a-fA-F]{6}$/.test(labelColorInput.value)) {
                    style.label_color = labelColorInput.value.trim().toLowerCase();
                }
                return style;
            }

            function getDefaultLinkStyle() {
                const colorInput = document.getElementById('default-link-color');
                const widthInput = document.getElementById('default-link-width');
                const viaStyleSelect = document.getElementById('default-link-via-style');
                const style = {};
                if (colorInput && /^#[0-9a-fA-F]{6}$/.test(colorInput.value)) {
                    style.color = colorInput.value.trim().toLowerCase();
                }
                if (widthInput && widthInput.value !== '') {
                    const width = parseFloat(widthInput.value);
                    if (!isNaN(width) && width >= 0.5 && width <= 20) {
                        style.width = width;
                    }
                }
                if (viaStyleSelect && viaStyleSelect.value) {
                    style.via_style = viaStyleSelect.value;
                }
                return style;
            }

            function populateDefaultStyles(options = {}) {
                const dns = options.default_node_style || {};
                const dls = options.default_link_style || {};
                const nodeColor = document.getElementById('default-node-color');
                if (nodeColor) nodeColor.value = dns.color || '';
                const nodeLabelColor = document.getElementById('default-node-label-color');
                if (nodeLabelColor) nodeLabelColor.value = dns.label_color || '';
                const linkColor = document.getElementById('default-link-color');
                if (linkColor) linkColor.value = dls.color || '';
                const linkWidth = document.getElementById('default-link-width');
                if (linkWidth) linkWidth.value = (dls.width !== undefined && dls.width !== null) ? dls.width : '';
                const linkViaStyle = document.getElementById('default-link-via-style');
                if (linkViaStyle) linkViaStyle.value = dls.via_style || '';
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

            /** Convert a mouse event's clientX/Y to canvas-internal pixel coords.
             *  Needed because CSS may scale the canvas display size ≠ its buffer size. */
            function getCanvasPoint(event) {
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const screenX = (event.clientX - rect.left) * scaleX;
                const screenY = (event.clientY - rect.top) * scaleY;
                return {
                    x: (screenX - viewOffsetX) / viewScale,
                    y: (screenY - viewOffsetY) / viewScale,
                };
            }

            // ========== Zoom and Pan Handlers ==========
            function handleWheel(event) {
                event.preventDefault();
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const mouseX = (event.clientX - rect.left) * scaleX;
                const mouseY = (event.clientY - rect.top) * scaleY;

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
                    panStart = { clientX: event.clientX, clientY: event.clientY, offsetX: viewOffsetX, offsetY: viewOffsetY };
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
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    viewOffsetX = panStart.offsetX + (event.clientX - panStart.clientX) * scaleX;
                    viewOffsetY = panStart.offsetY + (event.clientY - panStart.clientY) * scaleY;
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
                const defaultNodeStyle = getDefaultNodeStyle();
                ctx.beginPath();
                ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);

                // Color based on state: link start (orange), selected (blue), normal (default or green)
                if (linkMode && linkStart === node) {
                    ctx.fillStyle = '#fd7e14'; // Orange for link start
                } else if (node === selectedNode) {
                    ctx.fillStyle = '#0d6efd'; // Blue for selected
                } else {
                    ctx.fillStyle = defaultNodeStyle.color || '#28a745';
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

                ctx.fillStyle = defaultNodeStyle.label_color || (isDarkTheme ? '#f8f9fa' : '#212529');
                ctx.fillText(node.label || 'Node', node.x, node.y - 18);
            }

            function drawLink(link) {
                const src = findNodeById(link.srcId);
                const dst = findNodeById(link.dstId);
                if (!src || !dst) return;

                const defaultLinkStyle = getDefaultLinkStyle();
                const viaPoints = (link.style && link.style.via_points) || [];
                const viaStyle = (link.style && link.style.via_style) || defaultLinkStyle.via_style || editorConfig.link_style;
                const points = [{x: src.x, y: src.y}];
                for (const vp of viaPoints) { points.push({x: vp.x, y: vp.y}); }
                points.push({x: dst.x, y: dst.y});

                ctx.beginPath();
                ctx.moveTo(points[0].x, points[0].y);

                if (points.length === 2) {
                    ctx.lineTo(points[1].x, points[1].y);
                } else if (viaStyle === 'curved') {
                    for (let i = 0; i < points.length - 1; i++) {
                        const p0 = points[Math.max(0, i - 1)];
                        const p1 = points[i];
                        const p2 = points[i + 1];
                        const p3 = points[Math.min(points.length - 1, i + 2)];
                        const cp1x = p1.x + (p2.x - p0.x) / 6;
                        const cp1y = p1.y + (p2.y - p0.y) / 6;
                        const cp2x = p2.x - (p3.x - p1.x) / 6;
                        const cp2y = p2.y - (p3.y - p1.y) / 6;
                        ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
                    }
                } else {
                    for (let i = 1; i < points.length; i++) {
                        ctx.lineTo(points[i].x, points[i].y);
                    }
                }

                ctx.strokeStyle = (link.style && link.style.color) ? link.style.color : (defaultLinkStyle.color || '#6c757d');
                ctx.lineWidth = (link.style && link.style.width) ? link.style.width : (defaultLinkStyle.width || 2);
                ctx.stroke();

                // Store segments for hit-testing
                link._segs = [];
                for (let i = 1; i < points.length; i++) {
                    link._segs.push({x1: points[i-1].x, y1: points[i-1].y, x2: points[i].x, y2: points[i].y});
                }
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

                const applyCanvasResize = () => {
                    canvas.width = newWidth;
                    canvas.height = newHeight;
                    renderEditor();
                    WMNGToast.info(`Canvas resized to ${newWidth}x${newHeight}`, { duration: 2000 });
                };

                const revertCanvasResizeInputs = () => {
                    document.getElementById('map-width').value = canvas.width;
                    document.getElementById('map-height').value = canvas.height;
                };

                if (outOfBounds.length > 0) {
                    showEditorConfirm(
                        'Resize Canvas',
                        `${outOfBounds.length} node(s) will be outside the new canvas bounds. Continue to move them inside the new bounds, or cancel to keep the current canvas size.`,
                        'Resize Canvas',
                        'btn-primary',
                        function() {
                            outOfBounds.forEach(node => {
                                node.x = Math.min(node.x, newWidth - nodeRadius);
                                node.y = Math.min(node.y, newHeight - nodeRadius);
                            });
                            applyCanvasResize();
                        },
                        revertCanvasResizeInputs
                    );
                    return;
                }

                applyCanvasResize();
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
                .then(r => {
                    if (!r.ok) {
                        mapDataLoadFailed = true;
                        throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                    }
                    return r.json();
                })
                .then(data => {
                    if (!data) {
                        mapDataLoadFailed = true;
                        return;
                    }
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

                    const mapTags = document.getElementById('map-tags');
                    if (mapTags && Array.isArray(data.options?.tags)) {
                        mapTags.value = data.options.tags.join(', ');
                        renderMapTagsPreview(data.options.tags);
                    }

                    populateDefaultStyles(data.options);

                    if (data.width && canvas) canvas.width = data.width;
                    if (data.height && canvas) canvas.height = data.height;

                    mapDataLoaded = true;
                    renderEditor();
                    renderLinksList();
                    renderNodesList();
                    updateStatusCounts();
                    markSaved();
                })
                .catch(error => {
                    mapDataLoadFailed = true;
                    console.error('Failed to load map data', error);
                    WMNGToast.error('Failed to load map data: ' + error.message + '. Saving is disabled until the map loads.', { duration: 5000 });
                });
            }

            function loadDevices() {
                const deviceSelect = document.getElementById('device-select');
                const interfaceSelect = document.getElementById('interface-select');
                const interfaceContainer = document.getElementById('interface-container');
                if (!deviceSelect || !interfaceSelect) return;

                deviceSelect.innerHTML = '<option value="">Choose a device...</option>';
                fetch('{{ url('plugin/WeathermapNG/api/devices') }}')
                    .then(r => {
                        if (!r.ok) { console.warn('Failed to load devices: HTTP ' + r.status); return []; }
                        return r.json();
                    })
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
                        .then(r => {
                            if (!r.ok) { console.warn('Failed to load ports: HTTP ' + r.status); return { ports: [] }; }
                            return r.json();
                        })
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
                showEditorConfirm(
                    'Clear Canvas',
                    'Clear all nodes and links from this map? This can be undone with the editor undo history.',
                    'Clear Canvas',
                    'btn-danger',
                    function() {
                        nodes = [];
                        links = [];
                        selectedNode = null;
                        renderEditor();
                        renderLinksList();
                    }
                );
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

                // Destructive-save guard: for an existing map, refuse to save
                // until the /json load has completed successfully. The backend
                // replaceMapContent() deletes all nodes/links then recreates
                // from the client arrays — saving before the load resolves (or
                // after it failed) would POST empty arrays and wipe the map.
                if (mapId && !mapDataLoaded) {
                    if (mapDataLoadFailed) {
                        WMNGToast.error('Cannot save: map data failed to load. Reload the page and try again.', { duration: 5000 });
                    } else {
                        WMNGToast.warning('Map is still loading, please wait a moment and try again.', { duration: 3000 });
                    }
                    return;
                }
                const baseHeaders = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                };

                if (!mapId) {
                    WMNGLoading.show('Creating map...');
                    const createOptions = {
                        tags: parseMapTags(document.getElementById('map-tags')?.value),
                        default_node_style: getDefaultNodeStyle(),
                        default_link_style: getDefaultLinkStyle(),
                    };
                    const payload = { name: mapName, title: mapTitle, width: mapWidth, height: mapHeight, options: createOptions };
                    fetch('{{ url("plugin/WeathermapNG/map") }}', {
                        method: 'POST',
                        headers: baseHeaders,
                        body: JSON.stringify(payload),
                    })
                    .then(r => {
                        if (!r.ok) {
                            throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                        }
                        return r.json();
                    })
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
                const defaultNodeStyle = getDefaultNodeStyle();
                const defaultLinkStyle = getDefaultLinkStyle();
                const options = {
                    width: mapWidth,
                    height: mapHeight,
                    tags: parseMapTags(document.getElementById('map-tags')?.value),
                    default_node_style: defaultNodeStyle,
                    default_link_style: defaultLinkStyle,
                };
                const payload = {
                    title: mapTitle,
                    options,
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
                .then(r => {
                    // Surface non-2xx (403 admin gate, 419 CSRF, 500 server)
                    // before trying to parse JSON — otherwise a HTML error page
                    // throws an opaque SyntaxError and the real cause is lost.
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                    }
                    return r.json();
                })
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

    const defaultNodeStyle = getDefaultNodeStyle();
    const defaultLinkStyle = getDefaultLinkStyle();
    const exportData = {
        name: mapName,
        title: mapTitle,
        width: mapWidth,
        height: mapHeight,
        options: {
            width: mapWidth,
            height: mapHeight,
            default_node_style: defaultNodeStyle,
            default_link_style: defaultLinkStyle,
        },
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

    if (!node) {
        if (card) card.style.display = 'none';
        return;
    }

    // Show card and enable inputs
    if (card) card.style.display = 'block';

    // Populate label
    if (label) {
        label.value = node.label || '';
        label.oninput = function() {
            node.label = this.value;
            renderEditor();
            renderNodesList();
        };
    }

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
            node.interfaceId = null;
            renderEditor();
            renderNodesList();
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
    intSel.onchange = function() {
        node.interfaceId = this.value ? parseInt(this.value, 10) : null;
        renderEditor();
        renderNodesList();
    };
    if (!node.deviceId) return;

    fetch('{{ url('plugin/WeathermapNG/api/device') }}/' + node.deviceId + '/ports')
        .then(r => {
            if (!r.ok) { console.warn('Failed to load interfaces: HTTP ' + r.status); return { ports: [] }; }
            return r.json();
        })
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
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }, body: JSON.stringify(payload)
    }).then(r => {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
        }
        return r.json();
    }).then(d => {
        if (d.success) {
            selectedNode.label = label;
            selectedNode.deviceId = payload.device_id;
            selectedNode.interfaceId = payload.meta.interface_id;
            renderEditor();
        } else {
            WMNGToast.error('Failed to save node: ' + (d.message || 'Unknown error'), { duration: 3000 });
        }
    }).catch(error => {
        WMNGToast.error('Failed to save node: ' + error.message, { duration: 3000 });
    });
}

function deleteSelectedNode() {
    if (!selectedNode) return;
    const nodeToDelete = selectedNode;
    const nodeId = nodeToDelete.id || nodeToDelete.dbId;

    showEditorConfirm(
        'Delete Node',
        'Delete this node and attached links? This can be undone with the editor undo history.',
        'Delete Node',
        'btn-danger',
        function() {
            saveState(); // Save for undo

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
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                }).then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                    }
                    return r.json().catch(() => ({}));
                }).then(data => {
                    if (data.success === false) {
                        throw new Error(data.message || 'Server refused to delete node');
                    }
                    finishDelete();
                }).catch(err => {
                    WMNGToast.error('Failed to delete node: ' + err.message, { duration: 3000 });
                });
            } else {
                // Node only exists locally
                finishDelete();
            }
        }
    );
}

function renderLinksList() {
    const c = document.getElementById('links-list');
    if (!c) return;
    c.textContent = '';
    if (!links.length) {
        const empty = document.createElement('small');
        empty.className = 'text-muted';
        empty.textContent = 'No links yet';
        c.appendChild(empty);
        return;
    }
    links.forEach((l, idx) => {
        const a = findNodeById(l.srcId); const b = findNodeById(l.dstId);
        const aL = a ? a.label : l.srcId; const bL = b ? b.label : l.dstId;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between mb-2';

        const labelDiv = document.createElement('div');
        const icon = document.createElement('i');
        icon.className = 'fas fa-link';
        labelDiv.appendChild(icon);
        labelDiv.appendChild(document.createTextNode(' ' + aL + ' → ' + bL));
        row.appendChild(labelDiv);

        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group btn-group-sm';
        const editBtn = document.createElement('button');
        editBtn.className = 'btn btn-outline-secondary';
        editBtn.addEventListener('click', () => openLinkModal(idx));
        const editIcon = document.createElement('i');
        editIcon.className = 'fas fa-edit';
        editBtn.appendChild(editIcon);
        btnGroup.appendChild(editBtn);
        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-outline-danger';
        delBtn.addEventListener('click', () => deleteLink(idx));
        const delIcon = document.createElement('i');
        delIcon.className = 'fas fa-trash';
        delBtn.appendChild(delIcon);
        btnGroup.appendChild(delBtn);
        row.appendChild(btnGroup);

        c.appendChild(row);
    });
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
    const bandwidthValue = document.getElementById('link-bandwidth-value');
    const bandwidthUnit = document.getElementById('link-bandwidth-unit');
    const deleteBtn = document.getElementById('delete-link-btn');
    const viaStyleSelect = document.getElementById('link-via-style');

    // Reset dropdowns
    srcPortSelect.innerHTML = '<option value="">Select port...</option>';
    dstPortSelect.innerHTML = '<option value="">Select port...</option>';
    setBandwidthInputsFromBps(link.bw, bandwidthValue, bandwidthUnit);
    viaStyleSelect.value = (link.style && link.style.via_style) || 'straight';
    deleteBtn.style.display = 'inline-block';

    // Load source node ports
    if (srcNode && srcNode.deviceId) {
        fetch('{{ url('plugin/WeathermapNG/api/device') }}/' + srcNode.deviceId + '/ports')
            .then(r => {
                if (!r.ok) { console.warn('Failed to load source ports: HTTP ' + r.status); return { ports: [] }; }
                return r.json();
            })
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
            .then(r => {
                if (!r.ok) { console.warn('Failed to load destination ports: HTTP ' + r.status); return { ports: [] }; }
                return r.json();
            })
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
    link.bw = bandwidthInputsToBps(
        document.getElementById('link-bandwidth-value').value,
        document.getElementById('link-bandwidth-unit').value
    );
    const viaStyle = document.getElementById('link-via-style').value || 'straight';
    if (!link.style) link.style = {};
    link.style.via_style = viaStyle;

    $('#linkModal').modal('hide');
    currentLinkIndex = null;
    renderEditor();
    renderLinksList();
    WMNGToast.success('Link updated!', { duration: 2000 });
}

function deleteLink(linkIndex) {
    showEditorConfirm(
        'Delete Link',
        'Delete this link? This can be undone with the editor undo history.',
        'Delete Link',
        'btn-danger',
        function() {
            saveState(); // Save for undo
            links.splice(linkIndex, 1);
            renderEditor();
            renderLinksList();
            $('#linkModal').modal('hide');
            currentLinkIndex = null;
        }
    );
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

    container.textContent = '';

    if (!nodes.length) {
        const empty = document.createElement('small');
        empty.className = 'text-muted';
        empty.textContent = 'No nodes yet';
        container.appendChild(empty);
        return;
    }

    nodes.forEach((node, idx) => {
        const isSelected = node === selectedNode;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between py-1 node-list-item' + (isSelected ? ' selected' : '');
        row.addEventListener('click', () => selectNodeByIndex(idx));

        const label = document.createElement('small');
        if (isSelected) label.classList.add('font-weight-bold');

        const dot = document.createElement('i');
        dot.className = 'fas fa-circle node-dot ' + (isSelected ? 'text-primary' : 'text-success');
        label.appendChild(dot);
        label.appendChild(document.createTextNode(' ' + (node.label || 'Node ' + (idx + 1))));
        row.appendChild(label);

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-outline-danger btn-sm py-0 px-1';
        delBtn.title = 'Delete';
        delBtn.addEventListener('click', (e) => { e.stopPropagation(); deleteNodeByIndex(idx); });
        const delIcon = document.createElement('i');
        delIcon.className = 'fas fa-times node-delete-icon';
        delBtn.appendChild(delIcon);
        row.appendChild(delBtn);

        container.appendChild(row);
    });
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
        showEditorConfirm(
            'Delete Node',
            `Delete node "${node.label || 'Node ' + (idx + 1)}"? This can be undone with the editor undo history.`,
            'Delete Node',
            'btn-danger',
            function() {
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
        );
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
        <script>
            (function() {
                // Version history UI — only applies to existing, saved maps.
                const API_MAPS = '{{ url("plugin/WeathermapNG/api/maps") }}';
                const API_VERSIONS = '{{ url("plugin/WeathermapNG/api/versions") }}';

                let versionsCache = [];

                function csrfHeaders() {
                    return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WMNG.getCsrfToken() };
                }

                function toast(method, msg, opts) {
                    try {
                        if (window.WMNGToast && typeof window.WMNGToast[method] === 'function') {
                            window.WMNGToast[method](msg, opts);
                        }
                    } catch (e) { /* toast failure must never break the UI */ }
                }

                function fetchVersions() {
                    return WMNG.fetchJson(`${API_MAPS}/${mapId}/versions?_=${Date.now()}`, {
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    })
                        .then(data => {
                            versionsCache = Array.isArray(data) ? data : (data.versions || []);
                            renderVersionList(versionsCache);
                        })
                        .catch(err => {
                            renderVersionList([]);
                            const list = document.getElementById('versionList');
                            if (list) {
                                const p = document.createElement('p');
                                p.className = 'text-danger text-center';
                                p.textContent = 'Failed to load versions';
                                list.innerHTML = '';
                                list.appendChild(p);
                            }
                            toast('error', 'Failed to load versions', { duration: 3000 });
                        });
                }

                function renderVersionList(versions) {
                    const list = document.getElementById('versionList');
                    if (!list) return;
                    list.innerHTML = '';

                    if (!versions.length) {
                        const p = document.createElement('p');
                        p.className = 'text-muted text-center';
                        p.textContent = 'No saved versions yet.';
                        list.appendChild(p);
                        return;
                    }

                    versions.forEach(v => {
                        const item = document.createElement('div');
                        item.className = 'list-group-item d-flex justify-content-between align-items-center';
                        item.setAttribute('data-version-id', v.id);

                        const info = document.createElement('div');
                        const name = document.createElement('strong');
                        name.textContent = v.name || `Version ${v.id}`;
                        info.appendChild(name);

                        const meta = document.createElement('div');
                        meta.className = 'small text-muted';
                        meta.textContent = [
                            v.created_at_human || v.created_at || '',
                            v.creator ? (v.creator.realname || v.creator.username || 'unknown') : 'unknown'
                        ].filter(Boolean).join(' \u00b7 ');
                        info.appendChild(document.createElement('br'));
                        info.appendChild(meta);

                        if (v.description) {
                            const desc = document.createElement('div');
                            desc.className = 'small';
                            desc.textContent = v.description;
                            info.appendChild(document.createElement('br'));
                            info.appendChild(desc);
                        }

                        const actions = document.createElement('div');
                        actions.className = 'btn-group btn-group-sm';

                        const restoreBtn = document.createElement('button');
                        restoreBtn.type = 'button';
                        restoreBtn.className = 'btn btn-sm btn-outline-primary';
                        restoreBtn.setAttribute('data-action', 'restore');
                        restoreBtn.setAttribute('data-version-id', v.id);
                        restoreBtn.setAttribute('aria-label', `Restore version ${v.id}`);
                        restoreBtn.textContent = 'Restore';

                        const compareBtn = document.createElement('button');
                        compareBtn.type = 'button';
                        compareBtn.className = 'btn btn-sm btn-outline-info';
                        compareBtn.setAttribute('data-action', 'compare');
                        compareBtn.setAttribute('data-version-id', v.id);
                        compareBtn.setAttribute('aria-label', `Compare version ${v.id}`);
                        compareBtn.textContent = 'Compare';

                        const deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.className = 'btn btn-sm btn-outline-danger';
                        deleteBtn.setAttribute('data-action', 'delete');
                        deleteBtn.setAttribute('data-version-id', v.id);
                        deleteBtn.setAttribute('aria-label', `Delete version ${v.id}`);
                        deleteBtn.textContent = 'Delete';

                        actions.appendChild(restoreBtn);
                        actions.appendChild(compareBtn);
                        actions.appendChild(deleteBtn);

                        item.appendChild(info);
                        item.appendChild(actions);
                        list.appendChild(item);
                    });
                }

                function clearDiff() {
                    const area = document.getElementById('versionDiffArea');
                    if (area) area.style.display = 'none';
                    const summary = document.getElementById('diffSummary');
                    if (summary) summary.innerHTML = '';
                    const n1 = document.getElementById('diffVersion1Name');
                    const n2 = document.getElementById('diffVersion2Name');
                    if (n1) n1.textContent = '';
                    if (n2) n2.textContent = '';
                }

                function showCompareSelect(versionId, btn) {
                    const others = versionsCache.filter(v => v.id !== versionId);
                    if (!others.length) {
                        toast('info', 'No other versions to compare with.', { duration: 3000 });
                        return;
                    }

                    const item = btn.closest('[data-version-id]');
                    let selectWrap = item.querySelector('.compare-select-wrap');
                    if (selectWrap) {
                        selectWrap.remove();
                        return; // toggle off
                    }

                    selectWrap = document.createElement('span');
                    selectWrap.className = 'compare-select-wrap ml-1';

                    const select = document.createElement('select');
                    select.className = 'form-control form-control-sm d-inline-block';
                    select.style.maxWidth = '180px';
                    select.setAttribute('aria-label', 'Compare with version');
                    others.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.name || `Version ${v.id}`;
                        select.appendChild(opt);
                    });
                    selectWrap.appendChild(select);

                    const go = document.createElement('button');
                    go.type = 'button';
                    go.className = 'btn btn-sm btn-info ml-1';
                    go.textContent = 'Go';
                    go.setAttribute('data-action', 'compare-go');
                    go.setAttribute('data-v1', String(versionId));
                    selectWrap.appendChild(go);

                    const cancel = document.createElement('button');
                    cancel.type = 'button';
                    cancel.className = 'btn btn-sm btn-secondary ml-1';
                    cancel.textContent = 'Cancel';
                    cancel.setAttribute('data-action', 'compare-cancel');
                    selectWrap.appendChild(cancel);

                    item.appendChild(selectWrap);
                }

                function loadDiff(v1Id, v2Id) {
                    WMNG.fetchJson(`${API_VERSIONS}/${v1Id}/compare/${v2Id}`)
                        .then(data => {
                            renderDiff(data, v1Id, v2Id);
                        })
                        .catch(() => {
                            toast('error', 'Failed to load comparison', { duration: 3000 });
                        });
                }

                function renderDiff(diff, v1Id, v2Id) {
                    const area = document.getElementById('versionDiffArea');
                    if (!area) return;
                    area.style.display = '';

                    const v1 = versionsCache.find(x => x.id === v1Id);
                    const v2 = versionsCache.find(x => x.id === v2Id);
                    const n1 = document.getElementById('diffVersion1Name');
                    const n2 = document.getElementById('diffVersion2Name');
                    if (n1) n1.textContent = v1 ? (v1.name || `Version ${v1.id}`) : `Version ${v1Id}`;
                    if (n2) n2.textContent = v2 ? (v2.name || `Version ${v2.id}`) : `Version ${v2Id}`;

                    const summary = document.getElementById('diffSummary');
                    summary.innerHTML = '';

                    const d = diff && diff.diff ? diff.diff : diff;
                    const counts = [
                        ['Nodes added', d.nodes_added],
                        ['Nodes removed', d.nodes_removed],
                        ['Nodes modified', d.nodes_modified],
                        ['Links added', d.links_added],
                        ['Links removed', d.links_removed],
                        ['Links modified', d.links_modified]
                    ];

                    counts.forEach(([label, val]) => {
                        const li = document.createElement('li');
                        li.className = 'small';
                        const labelSpan = document.createElement('strong');
                        labelSpan.textContent = label + ': ';
                        li.appendChild(labelSpan);
                        const valSpan = document.createElement('span');
                        valSpan.textContent = Array.isArray(val) ? val.length : (val || 0);
                        li.appendChild(valSpan);
                        summary.appendChild(li);
                    });
                }

                function saveVersion() {
                    const input = document.getElementById('versionNameInput');
                    if (!input) return;
                    const name = input.value.trim();
                    if (!name) {
                        toast('info', 'Please enter a version name.', { duration: 3000 });
                        return;
                    }
                    WMNG.fetchJson(`${API_MAPS}/${mapId}/versions`, {
                        method: 'POST',
                        headers: csrfHeaders(),
                        body: JSON.stringify({ name: name })
                    })
                        .then(data => {
                            input.value = '';
                            toast('success', 'Version saved', { duration: 3000 });
                            clearDiff();
                            // Optimistically prepend the new version from the POST response
                            const newVersion = data && data.version ? data.version : null;
                            if (newVersion) {
                                versionsCache.unshift(newVersion);
                                renderVersionList(versionsCache);
                            }
                            // Re-fetch authoritative list, but preserve the new version if the GET is stale
                            return fetchVersions().then(() => {
                                if (newVersion && !versionsCache.some(v => v.id === newVersion.id)) {
                                    versionsCache.unshift(newVersion);
                                    renderVersionList(versionsCache);
                                }
                            });
                        })
                        .catch(() => {
                            toast('error', 'Failed to save version', { duration: 3000 });
                        });
                }

                function restoreVersion(versionId) {
                    const v = versionsCache.find(x => x.id === versionId);
                    const label = v ? (v.name || `Version ${versionId}`) : `Version ${versionId}`;
                    showEditorConfirm(
                        'Restore Version',
                        `Restore the map to "${label}"? Current unsaved changes will be lost.`,
                        'Restore',
                        'btn-primary',
                        () => {
                            WMNG.fetchJson(`${API_VERSIONS}/${versionId}/restore`, {
                                method: 'POST',
                                headers: csrfHeaders()
                            })
                                .then(() => {
                                    toast('success', 'Version restored. Reloading map data...', { duration: 3000 });
                                    $('#versionHistoryModal').modal('hide');
                                    // Reset load gate then pull fresh map data.
                                    mapDataLoaded = false;
                                    mapDataLoadFailed = false;
                                    loadMapData(mapId);
                                })
                                .catch(() => {
                                    toast('error', 'Failed to restore version', { duration: 3000 });
                                });
                        }
                    );
                }

                function deleteVersion(versionId) {
                    const v = versionsCache.find(x => x.id === versionId);
                    const label = v ? (v.name || `Version ${versionId}`) : `Version ${versionId}`;
                    showEditorConfirm(
                        'Delete Version',
                        `Delete version "${label}"? This cannot be undone.`,
                        'Delete',
                        'btn-danger',
                        () => {
                            WMNG.fetchJson(`${API_VERSIONS}/${versionId}`, {
                                method: 'DELETE',
                                headers: csrfHeaders()
                            })
                                .then(() => {
                                    toast('success', 'Version deleted', { duration: 3000 });
                                    clearDiff();
                                    return fetchVersions();
                                })
                                .catch(() => {
                                    toast('error', 'Failed to delete version', { duration: 3000 });
                                });
                        }
                    );
                }

                // Open modal + load versions
                const versionHistoryBtn = document.getElementById('versionHistoryBtn');
                if (versionHistoryBtn) {
                    versionHistoryBtn.addEventListener('click', () => {
                        if (!mapId) return; // new unsaved maps have no version history
                        clearDiff();
                        $('#versionHistoryModal').modal('show');
                        fetchVersions();
                    });
                }

                // Save version
                const saveVersionBtn = document.getElementById('saveVersionBtn');
                if (saveVersionBtn) {
                    saveVersionBtn.addEventListener('click', saveVersion);
                }

                // Delegated listeners on version list
                const versionList = document.getElementById('versionList');
                if (versionList) {
                    versionList.addEventListener('click', (e) => {
                        const btn = e.target.closest('button[data-action]');
                        if (!btn) return;
                        const action = btn.getAttribute('data-action');
                        const versionId = Number(btn.getAttribute('data-version-id'));

                        if (action === 'restore') {
                            restoreVersion(versionId);
                        } else if (action === 'compare') {
                            showCompareSelect(versionId, btn);
                        } else if (action === 'compare-go') {
                            const v1 = Number(btn.getAttribute('data-v1'));
                            const select = btn.previousElementSibling;
                            const v2 = Number(select.value);
                            const wrap = btn.closest('.compare-select-wrap');
                            if (wrap) wrap.remove();
                            loadDiff(v1, v2);
                        } else if (action === 'compare-cancel') {
                            const wrap = btn.closest('.compare-select-wrap');
                            if (wrap) wrap.remove();
                        } else if (action === 'delete') {
                            deleteVersion(versionId);
                        }
                    });
                }
            })();
        </script>
@endsection
