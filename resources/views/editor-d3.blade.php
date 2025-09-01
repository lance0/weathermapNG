@extends('layouts.librenmsv1')

@section('title', $title ?? 'Map Editor')

@section('content')
<div class="container-fluid" id="editor-container">
    <div class="row">
        <!-- Header Controls -->
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1><i class="fas fa-project-diagram"></i> {{ $map ? 'Edit: ' . $map->name : 'Create New Map' }}</h1>
                <div class="btn-toolbar">
                    <div class="btn-group me-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.undo()" title="Undo (Ctrl+Z)">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.redo()" title="Redo (Ctrl+Y)">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    <div class="btn-group me-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="editorActions.zoomIn()" title="Zoom In">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="editorActions.zoomOut()" title="Zoom Out">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="editorActions.fitToScreen()" title="Fit to Screen">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-success" onclick="editorActions.saveMap()">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="btn btn-secondary" onclick="window.location.href='{{ url('plugin/WeathermapNG') }}'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Toolbar -->
        <div class="col-md-1">
            <div class="card h-100">
                <div class="card-body p-2">
                    <div class="btn-group-vertical w-100" role="group">
                        <button class="btn btn-sm btn-outline-secondary tool-btn active" data-tool="select" title="Select (V)">
                            <i class="fas fa-mouse-pointer"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="pan" title="Pan (H)">
                            <i class="fas fa-hand-paper"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-node" title="Add Node (N)">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-link" title="Add Link (L)">
                            <i class="fas fa-link"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-text" title="Add Text (T)">
                            <i class="fas fa-font"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="delete" title="Delete (Del)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <hr class="my-2">
                    
                    <div class="form-check form-switch small">
                        <input class="form-check-input" type="checkbox" id="gridToggle" checked>
                        <label class="form-check-label" for="gridToggle">Grid</label>
                    </div>
                    <div class="form-check form-switch small">
                        <input class="form-check-input" type="checkbox" id="snapToggle" checked>
                        <label class="form-check-label" for="snapToggle">Snap</label>
                    </div>
                    <div class="form-check form-switch small">
                        <input class="form-check-input" type="checkbox" id="labelsToggle" checked>
                        <label class="form-check-label" for="labelsToggle">Labels</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Canvas Area -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body p-0 position-relative">
                    <!-- D3.js SVG Container -->
                    <div id="map-svg-container" style="width: 100%; height: 600px; overflow: hidden; position: relative;">
                        <svg id="map-svg" width="100%" height="100%">
                            <defs>
                                <!-- Arrow markers for links -->
                                <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                                    <polygon points="0 0, 10 3.5, 0 7" fill="#666" />
                                </marker>
                                
                                <!-- Grid pattern -->
                                <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#e0e0e0" stroke-width="1"/>
                                </pattern>
                                
                                <!-- Drop shadow filter -->
                                <filter id="dropshadow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur in="SourceAlpha" stdDeviation="3"/>
                                    <feOffset dx="2" dy="2" result="offsetblur"/>
                                    <feComponentTransfer>
                                        <feFuncA type="linear" slope="0.2"/>
                                    </feComponentTransfer>
                                    <feMerge>
                                        <feMergeNode/>
                                        <feMergeNode in="SourceGraphic"/>
                                    </feMerge>
                                </filter>
                            </defs>
                            
                            <!-- Grid background -->
                            <rect width="100%" height="100%" fill="url(#grid)" class="grid-background" />
                            
                            <!-- Main drawing group with zoom/pan transform -->
                            <g id="map-group">
                                <!-- Background image layer -->
                                <g id="background-layer"></g>
                                <!-- Links layer -->
                                <g id="links-layer"></g>
                                <!-- Nodes layer -->
                                <g id="nodes-layer"></g>
                                <!-- Labels layer -->
                                <g id="labels-layer"></g>
                                <!-- Selection layer -->
                                <g id="selection-layer"></g>
                            </g>
                        </svg>
                    </div>
                    
                    <!-- Minimap -->
                    <div id="minimap" class="position-absolute" style="bottom: 10px; right: 10px; width: 150px; height: 100px; background: rgba(255,255,255,0.9); border: 1px solid #ccc; border-radius: 4px;">
                        <svg id="minimap-svg" width="150" height="100"></svg>
                    </div>
                    
                    <!-- Zoom controls -->
                    <div class="position-absolute" style="top: 10px; right: 10px;">
                        <div class="btn-group-vertical">
                            <button class="btn btn-sm btn-light" onclick="editorActions.zoomIn()">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-light" onclick="editorActions.resetZoom()">
                                <i class="fas fa-compress"></i>
                            </button>
                            <button class="btn btn-sm btn-light" onclick="editorActions.zoomOut()">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Coordinates display -->
                    <div class="position-absolute" style="bottom: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                        <span id="coords">X: 0, Y: 0</span> | <span id="zoom-level">100%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Properties Panel -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#properties-tab">Properties</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#style-tab">Style</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#layers-tab">Layers</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Properties Tab -->
                        <div class="tab-pane fade show active" id="properties-tab">
                            <div id="no-selection" class="text-muted text-center py-4">
                                <i class="fas fa-mouse-pointer fa-2x mb-2"></i>
                                <p>Select an element to edit properties</p>
                            </div>
                            
                            <!-- Node Properties (hidden by default) -->
                            <div id="node-properties" style="display: none;">
                                <h6 class="mb-3">Node Properties</h6>
                                <div class="mb-3">
                                    <label class="form-label small">Label</label>
                                    <input type="text" class="form-control form-control-sm" id="node-label">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Device</label>
                                    <select class="form-select form-select-sm" id="node-device">
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Icon</label>
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-sm btn-outline-secondary" data-icon="router">
                                            <i class="fas fa-wifi"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-icon="switch">
                                            <i class="fas fa-network-wired"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-icon="server">
                                            <i class="fas fa-server"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-icon="firewall">
                                            <i class="fas fa-shield-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">X Position</label>
                                        <input type="number" class="form-control form-control-sm" id="node-x">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Y Position</label>
                                        <input type="number" class="form-control form-control-sm" id="node-y">
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-danger w-100" onclick="editorActions.deleteSelected()">
                                    <i class="fas fa-trash"></i> Delete Node
                                </button>
                            </div>
                            
                            <!-- Link Properties (hidden by default) -->
                            <div id="link-properties" style="display: none;">
                                <h6 class="mb-3">Link Properties</h6>
                                <div class="mb-3">
                                    <label class="form-label small">Label</label>
                                    <input type="text" class="form-control form-control-sm" id="link-label">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Bandwidth</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="link-bandwidth">
                                        <select class="form-select" id="link-bandwidth-unit">
                                            <option value="Mbps">Mbps</option>
                                            <option value="Gbps">Gbps</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Port A</label>
                                    <select class="form-select form-select-sm" id="link-port-a">
                                        <option value="">Auto</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Port B</label>
                                    <select class="form-select form-select-sm" id="link-port-b">
                                        <option value="">Auto</option>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-danger w-100" onclick="editorActions.deleteSelected()">
                                    <i class="fas fa-trash"></i> Delete Link
                                </button>
                            </div>
                        </div>
                        
                        <!-- Style Tab -->
                        <div class="tab-pane fade" id="style-tab">
                            <h6 class="mb-3">Map Style</h6>
                            <div class="mb-3">
                                <label class="form-label small">Background</label>
                                <input type="color" class="form-control form-control-sm" id="map-bg-color" value="#ffffff">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Background Image</label>
                                <input type="file" class="form-control form-control-sm" id="map-bg-image" accept="image/*">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Link Style</label>
                                <select class="form-select form-select-sm" id="link-style">
                                    <option value="straight">Straight</option>
                                    <option value="curved">Curved</option>
                                    <option value="orthogonal">Orthogonal</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Node Size</label>
                                <input type="range" class="form-range" id="node-size" min="20" max="60" value="40">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Label Size</label>
                                <input type="range" class="form-range" id="label-size" min="10" max="20" value="12">
                            </div>
                        </div>
                        
                        <!-- Layers Tab -->
                        <div class="tab-pane fade" id="layers-tab">
                            <h6 class="mb-3">Layers</h6>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="layer-nodes" checked>
                                        <label class="form-check-label" for="layer-nodes">
                                            <i class="fas fa-circle"></i> Nodes
                                        </label>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="layer-links" checked>
                                        <label class="form-check-label" for="layer-links">
                                            <i class="fas fa-link"></i> Links
                                        </label>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="layer-labels" checked>
                                        <label class="form-check-label" for="layer-labels">
                                            <i class="fas fa-font"></i> Labels
                                        </label>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="layer-background">
                                        <label class="form-check-label" for="layer-background">
                                            <i class="fas fa-image"></i> Background
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <h6 class="mb-3">Templates</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="editorActions.applyTemplate('star')">
                                    <i class="fas fa-star"></i> Star Topology
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editorActions.applyTemplate('mesh')">
                                    <i class="fas fa-project-diagram"></i> Mesh Network
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editorActions.applyTemplate('tree')">
                                    <i class="fas fa-sitemap"></i> Tree Structure
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editorActions.applyTemplate('ring')">
                                    <i class="fas fa-circle-notch"></i> Ring Topology
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Bar -->
    <div class="row mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center small">
                        <div>
                            <span class="badge bg-secondary">Nodes: <span id="node-count">0</span></span>
                            <span class="badge bg-secondary">Links: <span id="link-count">0</span></span>
                            <span class="badge bg-secondary">Selected: <span id="selected-count">0</span></span>
                        </div>
                        <div>
                            <span id="status-message" class="text-muted">Ready</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.showHelp()">
                                <i class="fas fa-keyboard"></i> Shortcuts
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.exportJSON()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.importJSON()">
                                <i class="fas fa-upload"></i> Import
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Map</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#import-json">JSON</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#import-csv">CSV</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#import-dot">Graphviz DOT</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="import-json">
                        <textarea class="form-control" rows="10" id="import-json-data" placeholder="Paste JSON data here..."></textarea>
                    </div>
                    <div class="tab-pane fade" id="import-csv">
                        <p class="text-muted">CSV Format: type,id,label,x,y,device_id,source,target,bandwidth</p>
                        <textarea class="form-control" rows="10" id="import-csv-data" placeholder="Paste CSV data here..."></textarea>
                    </div>
                    <div class="tab-pane fade" id="import-dot">
                        <textarea class="form-control" rows="10" id="import-dot-data" placeholder="Paste Graphviz DOT notation here..."></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="import-replace">
                        <label class="form-check-label" for="import-replace">
                            Replace existing map (unchecked = merge)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="editorActions.processImport()">Import</button>
            </div>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="modal fade" id="shortcutsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Keyboard Shortcuts</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class="table table-sm">
                    <tr><td><kbd>V</kbd></td><td>Select tool</td></tr>
                    <tr><td><kbd>H</kbd></td><td>Pan tool</td></tr>
                    <tr><td><kbd>N</kbd></td><td>Add node</td></tr>
                    <tr><td><kbd>L</kbd></td><td>Add link</td></tr>
                    <tr><td><kbd>T</kbd></td><td>Add text</td></tr>
                    <tr><td><kbd>Delete</kbd></td><td>Delete selected</td></tr>
                    <tr><td><kbd>Ctrl+A</kbd></td><td>Select all</td></tr>
                    <tr><td><kbd>Ctrl+C</kbd></td><td>Copy</td></tr>
                    <tr><td><kbd>Ctrl+V</kbd></td><td>Paste</td></tr>
                    <tr><td><kbd>Ctrl+Z</kbd></td><td>Undo</td></tr>
                    <tr><td><kbd>Ctrl+Y</kbd></td><td>Redo</td></tr>
                    <tr><td><kbd>Ctrl+S</kbd></td><td>Save map</td></tr>
                    <tr><td><kbd>+/-</kbd></td><td>Zoom in/out</td></tr>
                    <tr><td><kbd>0</kbd></td><td>Reset zoom</td></tr>
                    <tr><td><kbd>G</kbd></td><td>Toggle grid</td></tr>
                    <tr><td><kbd>S</kbd></td><td>Toggle snap</td></tr>
                    <tr><td><kbd>Escape</kbd></td><td>Cancel operation</td></tr>
                    <tr><td><kbd>Arrow Keys</kbd></td><td>Move selected</td></tr>
                    <tr><td><kbd>Shift+Click</kbd></td><td>Multi-select</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- D3.js -->
<script src="https://d3js.org/d3.v7.min.js"></script>

<!-- Editor JavaScript -->
<script>
// Global editor state
const editorState = {
    mapId: {{ $map->id ?? 'null' }},
    tool: 'select',
    nodes: [],
    links: [],
    selectedElements: [],
    history: [],
    historyIndex: -1,
    zoom: 1,
    pan: { x: 0, y: 0 },
    grid: true,
    snap: true,
    gridSize: 20,
    isDragging: false,
    isLinking: false,
    linkStart: null,
    clipboard: null
};

// Initialize D3.js editor
class WeathermapEditor {
    constructor(containerId) {
        this.container = d3.select(containerId);
        this.svg = this.container.select('#map-svg');
        this.mapGroup = this.svg.select('#map-group');
        
        // Initialize zoom behavior
        this.zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on('zoom', (event) => this.handleZoom(event));
        
        this.svg.call(this.zoom);
        
        // Initialize drag behavior
        this.drag = d3.drag()
            .on('start', (event, d) => this.dragStart(event, d))
            .on('drag', (event, d) => this.dragMove(event, d))
            .on('end', (event, d) => this.dragEnd(event, d));
        
        // Load initial data if editing
        if (editorState.mapId) {
            this.loadMap();
        }
        
        // Setup event listeners
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
        
        // Initialize minimap
        this.initMinimap();
        
        // Start render loop
        this.render();
    }
    
    handleZoom(event) {
        editorState.zoom = event.transform.k;
        editorState.pan = { x: event.transform.x, y: event.transform.y };
        this.mapGroup.attr('transform', event.transform);
        document.getElementById('zoom-level').textContent = Math.round(editorState.zoom * 100) + '%';
        this.updateMinimap();
    }
    
    dragStart(event, d) {
        if (editorState.tool !== 'select') return;
        editorState.isDragging = true;
        d.fx = d.x;
        d.fy = d.y;
    }
    
    dragMove(event, d) {
        if (!editorState.isDragging) return;
        
        let x = event.x;
        let y = event.y;
        
        // Snap to grid if enabled
        if (editorState.snap) {
            x = Math.round(x / editorState.gridSize) * editorState.gridSize;
            y = Math.round(y / editorState.gridSize) * editorState.gridSize;
        }
        
        d.fx = x;
        d.fy = y;
        d.x = x;
        d.y = y;
        
        this.updateNodePosition(d);
        this.updateLinks();
    }
    
    dragEnd(event, d) {
        editorState.isDragging = false;
        delete d.fx;
        delete d.fy;
        
        // Save position to server
        this.saveNodePosition(d);
    }
    
    updateNodePosition(node) {
        const nodeElement = d3.select(`#node-${node.id}`);
        nodeElement.attr('transform', `translate(${node.x}, ${node.y})`);
        
        // Update properties panel if this node is selected
        if (editorState.selectedElements.includes(node)) {
            document.getElementById('node-x').value = Math.round(node.x);
            document.getElementById('node-y').value = Math.round(node.y);
        }
    }
    
    updateLinks() {
        this.mapGroup.select('#links-layer')
            .selectAll('.link')
            .attr('d', d => this.getLinkPath(d));
    }
    
    getLinkPath(link) {
        const source = editorState.nodes.find(n => n.id === link.source);
        const target = editorState.nodes.find(n => n.id === link.target);
        
        if (!source || !target) return '';
        
        const style = document.getElementById('link-style').value;
        
        switch (style) {
            case 'curved':
                const dx = target.x - source.x;
                const dy = target.y - source.y;
                const dr = Math.sqrt(dx * dx + dy * dy);
                return `M${source.x},${source.y}A${dr},${dr} 0 0,1 ${target.x},${target.y}`;
            
            case 'orthogonal':
                const midX = (source.x + target.x) / 2;
                return `M${source.x},${source.y}L${midX},${source.y}L${midX},${target.y}L${target.x},${target.y}`;
            
            default: // straight
                return `M${source.x},${source.y}L${target.x},${target.y}`;
        }
    }
    
    render() {
        // Render nodes
        const nodes = this.mapGroup.select('#nodes-layer')
            .selectAll('.node')
            .data(editorState.nodes, d => d.id);
        
        const nodeEnter = nodes.enter()
            .append('g')
            .attr('class', 'node')
            .attr('id', d => `node-${d.id}`)
            .attr('transform', d => `translate(${d.x}, ${d.y})`)
            .call(this.drag)
            .on('click', (event, d) => this.selectNode(event, d))
            .on('dblclick', (event, d) => this.editNode(d));
        
        // Add node circle
        nodeEnter.append('circle')
            .attr('r', 20)
            .attr('fill', '#667eea')
            .attr('stroke', '#fff')
            .attr('stroke-width', 2)
            .attr('filter', 'url(#dropshadow)');
        
        // Add node icon
        nodeEnter.append('text')
            .attr('text-anchor', 'middle')
            .attr('dominant-baseline', 'middle')
            .attr('fill', 'white')
            .attr('font-family', 'Font Awesome 5 Free')
            .attr('font-weight', 900)
            .attr('font-size', '14px')
            .text(d => this.getNodeIcon(d.icon));
        
        // Update existing nodes
        nodes.merge(nodeEnter)
            .attr('transform', d => `translate(${d.x}, ${d.y})`)
            .select('circle')
            .attr('stroke', d => editorState.selectedElements.includes(d) ? '#ffc107' : '#fff')
            .attr('stroke-width', d => editorState.selectedElements.includes(d) ? 3 : 2);
        
        nodes.exit().remove();
        
        // Render links
        const links = this.mapGroup.select('#links-layer')
            .selectAll('.link')
            .data(editorState.links, d => d.id);
        
        const linkEnter = links.enter()
            .append('path')
            .attr('class', 'link')
            .attr('id', d => `link-${d.id}`)
            .attr('fill', 'none')
            .attr('stroke', '#666')
            .attr('stroke-width', 2)
            .attr('marker-end', 'url(#arrowhead)')
            .on('click', (event, d) => this.selectLink(event, d));
        
        links.merge(linkEnter)
            .attr('d', d => this.getLinkPath(d))
            .attr('stroke', d => editorState.selectedElements.includes(d) ? '#ffc107' : '#666')
            .attr('stroke-width', d => editorState.selectedElements.includes(d) ? 3 : 2);
        
        links.exit().remove();
        
        // Render labels
        if (document.getElementById('labelsToggle').checked) {
            const labels = this.mapGroup.select('#labels-layer')
                .selectAll('.label')
                .data(editorState.nodes, d => d.id);
            
            const labelEnter = labels.enter()
                .append('text')
                .attr('class', 'label')
                .attr('text-anchor', 'middle')
                .attr('font-size', '12px')
                .attr('dy', 35);
            
            labels.merge(labelEnter)
                .attr('x', d => d.x)
                .attr('y', d => d.y)
                .text(d => d.label);
            
            labels.exit().remove();
        }
        
        // Update counts
        document.getElementById('node-count').textContent = editorState.nodes.length;
        document.getElementById('link-count').textContent = editorState.links.length;
        document.getElementById('selected-count').textContent = editorState.selectedElements.length;
        
        requestAnimationFrame(() => this.render());
    }
    
    getNodeIcon(icon) {
        const icons = {
            'router': '\uf519',     // fa-wifi
            'switch': '\uf6ff',     // fa-network-wired
            'server': '\uf233',     // fa-server
            'firewall': '\uf3ed',   // fa-shield-alt
            'cloud': '\uf0c2',      // fa-cloud
            'database': '\uf1c0',   // fa-database
            'laptop': '\uf109',     // fa-laptop
            'phone': '\uf3cd'       // fa-mobile-alt
        };
        return icons[icon] || '\uf111'; // fa-circle as default
    }
    
    selectNode(event, node) {
        if (editorState.tool !== 'select') return;
        
        if (!event.shiftKey) {
            editorState.selectedElements = [];
        }
        
        if (!editorState.selectedElements.includes(node)) {
            editorState.selectedElements.push(node);
        } else if (event.shiftKey) {
            editorState.selectedElements = editorState.selectedElements.filter(n => n !== node);
        }
        
        this.updatePropertiesPanel();
    }
    
    selectLink(event, link) {
        if (editorState.tool !== 'select') return;
        
        event.stopPropagation();
        
        if (!event.shiftKey) {
            editorState.selectedElements = [];
        }
        
        if (!editorState.selectedElements.includes(link)) {
            editorState.selectedElements.push(link);
        }
        
        this.updatePropertiesPanel();
    }
    
    editNode(node) {
        editorState.selectedElements = [node];
        this.updatePropertiesPanel();
        document.querySelector('[href="#properties-tab"]').click();
        document.getElementById('node-label').focus();
    }
    
    updatePropertiesPanel() {
        const selected = editorState.selectedElements;
        
        // Hide all property panels
        document.getElementById('no-selection').style.display = selected.length === 0 ? 'block' : 'none';
        document.getElementById('node-properties').style.display = 'none';
        document.getElementById('link-properties').style.display = 'none';
        
        if (selected.length === 1) {
            const element = selected[0];
            
            if (element.type === 'node' || !element.type) {
                // Show node properties
                document.getElementById('node-properties').style.display = 'block';
                document.getElementById('node-label').value = element.label || '';
                document.getElementById('node-device').value = element.device_id || '';
                document.getElementById('node-x').value = Math.round(element.x);
                document.getElementById('node-y').value = Math.round(element.y);
            } else if (element.type === 'link') {
                // Show link properties
                document.getElementById('link-properties').style.display = 'block';
                document.getElementById('link-label').value = element.label || '';
                document.getElementById('link-bandwidth').value = element.bandwidth || '';
            }
        }
    }
    
    saveNodePosition(node) {
        // Debounced save to server
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            fetch(`{{ url('plugin/WeathermapNG/api/node') }}/${node.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    x: Math.round(node.x),
                    y: Math.round(node.y)
                })
            });
        }, 300);
    }
    
    loadMap() {
        fetch(`{{ url('plugin/WeathermapNG/api/map') }}/${editorState.mapId}`)
            .then(response => response.json())
            .then(data => {
                editorState.nodes = data.nodes || [];
                editorState.links = data.links || [];
                this.render();
            });
    }
    
    setupEventListeners() {
        // Tool buttons
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                editorState.tool = btn.dataset.tool;
                this.svg.style('cursor', this.getToolCursor());
            });
        });
        
        // Canvas click for adding nodes
        this.svg.on('click', (event) => {
            if (editorState.tool === 'add-node') {
                const coords = d3.pointer(event, this.mapGroup.node());
                this.addNode(coords[0], coords[1]);
            }
        });
        
        // Grid toggle
        document.getElementById('gridToggle').addEventListener('change', (e) => {
            editorState.grid = e.target.checked;
            this.svg.select('.grid-background')
                .style('display', editorState.grid ? 'block' : 'none');
        });
        
        // Snap toggle
        document.getElementById('snapToggle').addEventListener('change', (e) => {
            editorState.snap = e.target.checked;
        });
        
        // Mouse position tracking
        this.svg.on('mousemove', (event) => {
            const coords = d3.pointer(event, this.mapGroup.node());
            document.getElementById('coords').textContent = 
                `X: ${Math.round(coords[0])}, Y: ${Math.round(coords[1])}`;
        });
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Tool shortcuts
            if (!e.ctrlKey && !e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'v': this.setTool('select'); break;
                    case 'h': this.setTool('pan'); break;
                    case 'n': this.setTool('add-node'); break;
                    case 'l': this.setTool('add-link'); break;
                    case 't': this.setTool('add-text'); break;
                    case 'g': document.getElementById('gridToggle').click(); break;
                    case 's': 
                        if (!e.ctrlKey) document.getElementById('snapToggle').click(); 
                        break;
                    case 'delete': this.deleteSelected(); break;
                    case 'escape': this.cancelOperation(); break;
                }
            }
            
            // Ctrl shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'a': e.preventDefault(); this.selectAll(); break;
                    case 'c': e.preventDefault(); this.copy(); break;
                    case 'v': e.preventDefault(); this.paste(); break;
                    case 'z': e.preventDefault(); this.undo(); break;
                    case 'y': e.preventDefault(); this.redo(); break;
                    case 's': e.preventDefault(); this.saveMap(); break;
                }
            }
            
            // Arrow keys for moving selected nodes
            if (editorState.selectedElements.length > 0 && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                e.preventDefault();
                const delta = e.shiftKey ? 10 : 1;
                const dx = e.key === 'ArrowLeft' ? -delta : e.key === 'ArrowRight' ? delta : 0;
                const dy = e.key === 'ArrowUp' ? -delta : e.key === 'ArrowDown' ? delta : 0;
                
                editorState.selectedElements.forEach(element => {
                    if (element.type === 'node' || !element.type) {
                        element.x += dx;
                        element.y += dy;
                        this.updateNodePosition(element);
                        this.saveNodePosition(element);
                    }
                });
                this.updateLinks();
            }
        });
    }
    
    setTool(tool) {
        document.querySelector(`[data-tool="${tool}"]`).click();
    }
    
    getToolCursor() {
        const cursors = {
            'select': 'default',
            'pan': 'grab',
            'add-node': 'crosshair',
            'add-link': 'crosshair',
            'add-text': 'text',
            'delete': 'not-allowed'
        };
        return cursors[editorState.tool] || 'default';
    }
    
    addNode(x, y) {
        if (editorState.snap) {
            x = Math.round(x / editorState.gridSize) * editorState.gridSize;
            y = Math.round(y / editorState.gridSize) * editorState.gridSize;
        }
        
        const node = {
            id: 'node-' + Date.now(),
            x: x,
            y: y,
            label: 'New Node',
            icon: 'router',
            type: 'node'
        };
        
        editorState.nodes.push(node);
        this.addToHistory();
        
        // Save to server
        fetch('{{ url("plugin/WeathermapNG/api/node") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                map_id: editorState.mapId,
                ...node
            })
        }).then(response => response.json())
          .then(data => {
              node.id = data.id;
          });
    }
    
    deleteSelected() {
        if (editorState.selectedElements.length === 0) return;
        
        editorState.selectedElements.forEach(element => {
            if (element.type === 'node' || !element.type) {
                // Remove node and its links
                editorState.nodes = editorState.nodes.filter(n => n !== element);
                editorState.links = editorState.links.filter(l => 
                    l.source !== element.id && l.target !== element.id
                );
                
                // Delete from server
                fetch(`{{ url('plugin/WeathermapNG/api/node') }}/${element.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
            } else if (element.type === 'link') {
                editorState.links = editorState.links.filter(l => l !== element);
                
                // Delete from server
                fetch(`{{ url('plugin/WeathermapNG/api/link') }}/${element.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
            }
        });
        
        editorState.selectedElements = [];
        this.updatePropertiesPanel();
        this.addToHistory();
    }
    
    selectAll() {
        editorState.selectedElements = [...editorState.nodes, ...editorState.links];
        this.updatePropertiesPanel();
    }
    
    copy() {
        if (editorState.selectedElements.length > 0) {
            editorState.clipboard = JSON.parse(JSON.stringify(editorState.selectedElements));
            this.showStatus('Copied ' + editorState.clipboard.length + ' elements');
        }
    }
    
    paste() {
        if (!editorState.clipboard || editorState.clipboard.length === 0) return;
        
        const offset = 20;
        const pasted = [];
        
        editorState.clipboard.forEach(element => {
            const copy = JSON.parse(JSON.stringify(element));
            copy.id = (element.type || 'node') + '-' + Date.now() + '-' + Math.random();
            
            if (copy.type === 'node' || !copy.type) {
                copy.x += offset;
                copy.y += offset;
                editorState.nodes.push(copy);
            } else if (copy.type === 'link') {
                // Update link references
                editorState.links.push(copy);
            }
            
            pasted.push(copy);
        });
        
        editorState.selectedElements = pasted;
        this.updatePropertiesPanel();
        this.addToHistory();
        this.showStatus('Pasted ' + pasted.length + ' elements');
    }
    
    undo() {
        if (editorState.historyIndex > 0) {
            editorState.historyIndex--;
            this.restoreHistory(editorState.history[editorState.historyIndex]);
            this.showStatus('Undo');
        }
    }
    
    redo() {
        if (editorState.historyIndex < editorState.history.length - 1) {
            editorState.historyIndex++;
            this.restoreHistory(editorState.history[editorState.historyIndex]);
            this.showStatus('Redo');
        }
    }
    
    addToHistory() {
        const state = {
            nodes: JSON.parse(JSON.stringify(editorState.nodes)),
            links: JSON.parse(JSON.stringify(editorState.links))
        };
        
        // Remove any history after current index
        editorState.history = editorState.history.slice(0, editorState.historyIndex + 1);
        
        // Add new state
        editorState.history.push(state);
        editorState.historyIndex++;
        
        // Limit history size
        if (editorState.history.length > 50) {
            editorState.history.shift();
            editorState.historyIndex--;
        }
    }
    
    restoreHistory(state) {
        editorState.nodes = JSON.parse(JSON.stringify(state.nodes));
        editorState.links = JSON.parse(JSON.stringify(state.links));
        editorState.selectedElements = [];
        this.updatePropertiesPanel();
    }
    
    saveMap() {
        const data = {
            id: editorState.mapId,
            name: document.getElementById('map-name')?.value || 'Untitled',
            nodes: editorState.nodes,
            links: editorState.links,
            options: {
                background: document.getElementById('map-bg-color').value,
                link_style: document.getElementById('link-style').value,
                node_size: document.getElementById('node-size').value,
                label_size: document.getElementById('label-size').value
            }
        };
        
        fetch('{{ url("plugin/WeathermapNG/api/map/save") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  this.showStatus('Map saved successfully');
                  if (!editorState.mapId) {
                      editorState.mapId = data.id;
                      window.history.replaceState({}, '', `{{ url('plugin/WeathermapNG/editor') }}/${data.id}`);
                  }
              } else {
                  this.showStatus('Error saving map: ' + data.message, 'error');
              }
          });
    }
    
    cancelOperation() {
        editorState.isLinking = false;
        editorState.linkStart = null;
        editorState.selectedElements = [];
        this.updatePropertiesPanel();
        this.setTool('select');
    }
    
    showStatus(message, type = 'info') {
        const statusElement = document.getElementById('status-message');
        statusElement.textContent = message;
        statusElement.className = type === 'error' ? 'text-danger' : 'text-muted';
        
        setTimeout(() => {
            statusElement.textContent = 'Ready';
            statusElement.className = 'text-muted';
        }, 3000);
    }
    
    initMinimap() {
        // Initialize minimap rendering
        this.minimapSvg = d3.select('#minimap-svg');
        this.updateMinimap();
    }
    
    updateMinimap() {
        const scale = 0.1;
        
        // Clear minimap
        this.minimapSvg.selectAll('*').remove();
        
        // Draw minimap nodes
        this.minimapSvg.selectAll('.minimap-node')
            .data(editorState.nodes)
            .enter()
            .append('circle')
            .attr('cx', d => d.x * scale)
            .attr('cy', d => d.y * scale)
            .attr('r', 2)
            .attr('fill', '#667eea');
        
        // Draw minimap links
        this.minimapSvg.selectAll('.minimap-link')
            .data(editorState.links)
            .enter()
            .append('line')
            .attr('x1', d => {
                const source = editorState.nodes.find(n => n.id === d.source);
                return source ? source.x * scale : 0;
            })
            .attr('y1', d => {
                const source = editorState.nodes.find(n => n.id === d.source);
                return source ? source.y * scale : 0;
            })
            .attr('x2', d => {
                const target = editorState.nodes.find(n => n.id === d.target);
                return target ? target.x * scale : 0;
            })
            .attr('y2', d => {
                const target = editorState.nodes.find(n => n.id === d.target);
                return target ? target.y * scale : 0;
            })
            .attr('stroke', '#666')
            .attr('stroke-width', 0.5);
        
        // Draw viewport rectangle
        const viewBox = this.svg.node().getBoundingClientRect();
        this.minimapSvg.append('rect')
            .attr('x', -editorState.pan.x * scale / editorState.zoom)
            .attr('y', -editorState.pan.y * scale / editorState.zoom)
            .attr('width', viewBox.width * scale / editorState.zoom)
            .attr('height', viewBox.height * scale / editorState.zoom)
            .attr('fill', 'none')
            .attr('stroke', '#ff0000')
            .attr('stroke-width', 1);
    }
}

// Editor actions
const editorActions = {
    undo: () => editor.undo(),
    redo: () => editor.redo(),
    zoomIn: () => {
        const newScale = Math.min(editorState.zoom * 1.2, 4);
        editor.svg.transition().duration(300).call(editor.zoom.scaleTo, newScale);
    },
    zoomOut: () => {
        const newScale = Math.max(editorState.zoom * 0.8, 0.1);
        editor.svg.transition().duration(300).call(editor.zoom.scaleTo, newScale);
    },
    resetZoom: () => {
        editor.svg.transition().duration(300).call(editor.zoom.transform, d3.zoomIdentity);
    },
    fitToScreen: () => {
        const bounds = editor.mapGroup.node().getBBox();
        const fullWidth = editor.svg.node().clientWidth;
        const fullHeight = editor.svg.node().clientHeight;
        const width = bounds.width;
        const height = bounds.height;
        const midX = bounds.x + width / 2;
        const midY = bounds.y + height / 2;
        const scale = 0.8 / Math.max(width / fullWidth, height / fullHeight);
        const translate = [fullWidth / 2 - scale * midX, fullHeight / 2 - scale * midY];
        
        editor.svg.transition().duration(300).call(
            editor.zoom.transform,
            d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
        );
    },
    saveMap: () => editor.saveMap(),
    deleteSelected: () => editor.deleteSelected(),
    showHelp: () => {
        $('#shortcutsModal').modal('show');
    },
    exportJSON: () => {
        const data = {
            version: '2.0',
            map: {
                id: editorState.mapId,
                name: document.getElementById('map-name')?.value || 'Untitled'
            },
            nodes: editorState.nodes,
            links: editorState.links,
            options: {
                background: document.getElementById('map-bg-color').value,
                link_style: document.getElementById('link-style').value
            }
        };
        
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `weathermap-${editorState.mapId || 'new'}.json`;
        a.click();
        URL.revokeObjectURL(url);
    },
    importJSON: () => {
        $('#importModal').modal('show');
    },
    processImport: () => {
        const activeTab = document.querySelector('#importModal .tab-pane.active');
        const format = activeTab.id.replace('import-', '');
        const replace = document.getElementById('import-replace').checked;
        
        let data;
        switch (format) {
            case 'json':
                try {
                    data = JSON.parse(document.getElementById('import-json-data').value);
                    if (replace) {
                        editorState.nodes = data.nodes || [];
                        editorState.links = data.links || [];
                    } else {
                        editorState.nodes.push(...(data.nodes || []));
                        editorState.links.push(...(data.links || []));
                    }
                    editor.addToHistory();
                    editor.showStatus('Import successful');
                } catch (e) {
                    editor.showStatus('Invalid JSON: ' + e.message, 'error');
                }
                break;
            
            case 'csv':
                // Parse CSV
                const csv = document.getElementById('import-csv-data').value;
                // Implementation for CSV parsing
                editor.showStatus('CSV import not yet implemented', 'error');
                break;
            
            case 'dot':
                // Parse Graphviz DOT
                const dot = document.getElementById('import-dot-data').value;
                // Implementation for DOT parsing
                editor.showStatus('DOT import not yet implemented', 'error');
                break;
        }
        
        bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
    },
    applyTemplate: (template) => {
        const templates = {
            star: () => {
                const center = { x: 400, y: 300 };
                const radius = 150;
                const nodeCount = 8;
                
                editorState.nodes = [{
                    id: 'center',
                    x: center.x,
                    y: center.y,
                    label: 'Core',
                    icon: 'router',
                    type: 'node'
                }];
                
                for (let i = 0; i < nodeCount; i++) {
                    const angle = (i * 2 * Math.PI) / nodeCount;
                    const node = {
                        id: `node-${i}`,
                        x: center.x + radius * Math.cos(angle),
                        y: center.y + radius * Math.sin(angle),
                        label: `Node ${i + 1}`,
                        icon: 'switch',
                        type: 'node'
                    };
                    editorState.nodes.push(node);
                    
                    editorState.links.push({
                        id: `link-${i}`,
                        source: 'center',
                        target: node.id,
                        type: 'link'
                    });
                }
            },
            mesh: () => {
                const grid = 4;
                const spacing = 100;
                const offset = { x: 200, y: 150 };
                
                editorState.nodes = [];
                editorState.links = [];
                
                // Create grid of nodes
                for (let i = 0; i < grid; i++) {
                    for (let j = 0; j < grid; j++) {
                        const node = {
                            id: `node-${i}-${j}`,
                            x: offset.x + j * spacing,
                            y: offset.y + i * spacing,
                            label: `${i},${j}`,
                            icon: 'switch',
                            type: 'node'
                        };
                        editorState.nodes.push(node);
                        
                        // Connect to adjacent nodes
                        if (j > 0) {
                            editorState.links.push({
                                id: `link-h-${i}-${j}`,
                                source: `node-${i}-${j-1}`,
                                target: node.id,
                                type: 'link'
                            });
                        }
                        if (i > 0) {
                            editorState.links.push({
                                id: `link-v-${i}-${j}`,
                                source: `node-${i-1}-${j}`,
                                target: node.id,
                                type: 'link'
                            });
                        }
                    }
                }
            },
            tree: () => {
                const levels = 4;
                const width = 600;
                const height = 400;
                const ySpacing = height / levels;
                
                editorState.nodes = [];
                editorState.links = [];
                
                let nodeId = 0;
                const addLevel = (parentId, level, position) => {
                    if (level >= levels) return;
                    
                    const childCount = level === 0 ? 1 : 2;
                    const xSpacing = width / Math.pow(2, level);
                    
                    for (let i = 0; i < childCount; i++) {
                        const node = {
                            id: `node-${nodeId++}`,
                            x: position + (i - (childCount - 1) / 2) * xSpacing,
                            y: 100 + level * ySpacing,
                            label: `L${level}-${i}`,
                            icon: level === 0 ? 'router' : 'switch',
                            type: 'node'
                        };
                        editorState.nodes.push(node);
                        
                        if (parentId !== null) {
                            editorState.links.push({
                                id: `link-${parentId}-${node.id}`,
                                source: parentId,
                                target: node.id,
                                type: 'link'
                            });
                        }
                        
                        addLevel(node.id, level + 1, node.x);
                    }
                };
                
                addLevel(null, 0, 400);
            },
            ring: () => {
                const nodeCount = 12;
                const radius = 150;
                const center = { x: 400, y: 300 };
                
                editorState.nodes = [];
                editorState.links = [];
                
                for (let i = 0; i < nodeCount; i++) {
                    const angle = (i * 2 * Math.PI) / nodeCount;
                    const node = {
                        id: `node-${i}`,
                        x: center.x + radius * Math.cos(angle),
                        y: center.y + radius * Math.sin(angle),
                        label: `Node ${i + 1}`,
                        icon: 'switch',
                        type: 'node'
                    };
                    editorState.nodes.push(node);
                    
                    // Connect to next node in ring
                    const nextIndex = (i + 1) % nodeCount;
                    editorState.links.push({
                        id: `link-${i}`,
                        source: node.id,
                        target: `node-${nextIndex}`,
                        type: 'link'
                    });
                }
            }
        };
        
        if (templates[template]) {
            templates[template]();
            editor.addToHistory();
            editor.showStatus(`Applied ${template} template`);
        }
    }
};

// Initialize editor when DOM is ready
let editor;
document.addEventListener('DOMContentLoaded', () => {
    editor = new WeathermapEditor('#map-svg-container');
    
    // Load devices for dropdown
    fetch('{{ url("plugin/WeathermapNG/api/devices") }}')
        .then(response => response.json())
        .then(devices => {
            const select = document.getElementById('node-device');
            devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.device_id;
                option.textContent = device.hostname;
                select.appendChild(option);
            });
        });
});
</script>
@endsection

@section('styles')
<style>
#editor-container {
    height: calc(100vh - 100px);
}

.card {
    height: 100%;
}

.tool-btn {
    padding: 0.5rem;
    margin-bottom: 0.25rem;
}

.tool-btn i {
    font-size: 1.2rem;
}

#map-svg-container {
    background: #f8f9fa;
}

.node {
    cursor: pointer;
}

.link {
    cursor: pointer;
}

.link:hover {
    stroke-width: 4px !important;
}

.label {
    pointer-events: none;
    user-select: none;
}

#minimap {
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.nav-link.active {
    color: #667eea;
    border-color: #dee2e6 #dee2e6 #fff;
}

kbd {
    background: #f4f4f4;
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 2px 4px;
    font-size: 0.9em;
}
</style>
@endsection