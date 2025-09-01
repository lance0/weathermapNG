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
                            <div class="btn-group-vertical w-100" role="group" aria-label="Modes">
                        <button class="btn btn-sm btn-outline-secondary tool-btn active" data-tool="select" title="Select (V)" data-toggle="tooltip">
                            <i class="fas fa-mouse-pointer"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="pan" title="Pan (H)" data-toggle="tooltip">
                            <i class="fas fa-hand-paper"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-node" title="Add Node (N)" data-toggle="tooltip">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-link" title="Add Link (L)" data-toggle="tooltip">
                            <i class="fas fa-link"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="add-text" title="Add Text (T)" data-toggle="tooltip">
                            <i class="fas fa-font"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary tool-btn" data-tool="delete" title="Delete (Del)" data-toggle="tooltip">
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
                                <!-- Dark Grid pattern -->
                                <pattern id="grid-dark" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#c0c0c0" stroke-width="1"/>
                                </pattern>
                                <!-- Dots pattern -->
                                <pattern id="dots" width="24" height="24" patternUnits="userSpaceOnUse">
                                    <circle cx="2" cy="2" r="1" fill="#d8d8d8"/>
                                </pattern>
                                <!-- Hex pattern -->
                                <pattern id="hex" width="24" height="21" patternUnits="userSpaceOnUse" patternTransform="translate(0,0)">
                                    <path d="M6,0 L18,0 L24,10.5 L18,21 L6,21 L0,10.5 Z" fill="none" stroke="#e0e0e0" stroke-width="1" />
                                </pattern>
                                <!-- Gradients -->
                                <linearGradient id="bg-gradient-blue" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#eef5ff"/>
                                    <stop offset="100%" stop-color="#cfe0ff"/>
                                </linearGradient>
                                <linearGradient id="bg-gradient-gray" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f9f9f9"/>
                                    <stop offset="100%" stop-color="#e9ecef"/>
                                </linearGradient>
                                
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
                        <!-- Hover tooltip for nodes/links (live preview) -->
                        <div id="editor-tooltip" style="position:absolute; display:none; background: rgba(0,0,0,0.85); color:#fff; padding:6px 8px; border-radius:4px; font-size:12px; pointer-events:none; z-index:10;"></div>
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
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Properties</h6>
                                <span id="prop-mode-badge" class="badge bg-secondary">None</span>
                            </div>
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
                                <div class="mb-3 position-relative">
                                    <label class="form-label small">Device</label>
                                    <input type="text" id="node-device-search" class="form-control form-control-sm mb-1" placeholder="Search devices..." autocomplete="off">
                                    <div id="node-device-suggestions" class="autocomplete-list" style="display:none;"></div>
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
                                <div class="sticky-apply d-grid gap-2 mb-2 pt-2">
                                    <button class="btn btn-sm btn-primary" id="apply-node-btn">
                                        <i class="fas fa-check"></i> Apply Changes
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-danger w-100" onclick="editorActions.deleteSelected()">
                                    <i class="fas fa-trash"></i> Delete Node
                                </button>
                            </div>
                            
                            <!-- Link Properties (hidden by default) -->
                            <div id="link-properties" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Link Properties</h6>
                                    <button class="btn btn-xs btn-link p-0" id="toggle-link-advanced" type="button">
                                        Advanced
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Label</label>
                                    <input type="text" class="form-control form-control-sm" id="link-label">
                                </div>
                                <div id="link-advanced" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label small">Bandwidth</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="link-bandwidth">
                                        <select class="form-select" id="link-bandwidth-unit">
                                            <option value="Mbps">Mbps</option>
                                            <option value="Gbps">Gbps</option>
                                        </select>
                                    </div>
                                    <div class="form-text" id="link-bw-help"></div>
                                </div>
                                <div class="mb-3 position-relative">
                                    <label class="form-label small">Port A</label>
                                    <input type="text" id="link-port-a-search" class="form-control form-control-sm mb-1" placeholder="Filter ports...">
                                    <div id="link-port-a-suggestions" class="autocomplete-list" style="display:none;"></div>
                                    <select class="form-select form-select-sm" id="link-port-a">
                                        <option value="">Auto</option>
                                    </select>
                                    <div class="form-text" id="link-port-a-help"></div>
                                </div>
                                <div class="mb-3 position-relative">
                                    <label class="form-label small">Port B</label>
                                    <input type="text" id="link-port-b-search" class="form-control form-control-sm mb-1" placeholder="Filter ports...">
                                    <div id="link-port-b-suggestions" class="autocomplete-list" style="display:none;"></div>
                                    <select class="form-select form-select-sm" id="link-port-b">
                                        <option value="">Auto</option>
                                    </select>
                                    <div class="form-text" id="link-port-b-help"></div>
                                </div>
                                </div>
                                <div class="sticky-apply d-grid gap-2 mb-2 pt-2">
                                    <button class="btn btn-sm btn-primary" id="apply-link-btn">
                                        <i class="fas fa-check"></i> Apply Changes
                                    </button>
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
                                <label class="form-label small">Preset Background</label>
                                <select class="form-select form-select-sm" id="preset-background">
                                    <option value="none">None</option>
                                    <option value="grid-light" selected>Grid (Light)</option>
                                    <option value="grid-dark">Grid (Dark)</option>
                                    <option value="dots">Dots</option>
                                    <option value="hex">Hex</option>
                                    <option value="gradient-blue">Gradient (Blue)</option>
                                    <option value="gradient-gray">Gradient (Gray)</option>
                                </select>
                            </div>
                            <div class="border rounded p-2 mb-3">
                                <label class="form-label small mb-1">Geographic Background</label>
                                <div class="form-check form-switch small mb-2">
                                    <input class="form-check-input" type="checkbox" id="geoToggle">
                                    <label class="form-check-label" for="geoToggle">Enable</label>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small">Preset</label>
                                        <select class="form-select form-select-sm" id="geo-preset">
                                            <option value="none">None</option>
                                            <option value="world-110m">World (110m)</option>
                                            <option value="world-50m">World (50m)</option>
                                            <option value="us-10m">US States (10m)</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Projection</label>
                                        <select class="form-select form-select-sm" id="geo-proj">
                                            <option value="mercator" selected>Mercator</option>
                                            <option value="equirect">Equirectangular</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-check form-switch small mt-2">
                                    <input class="form-check-input" type="checkbox" id="geo-center-click">
                                    <label class="form-check-label" for="geo-center-click">Center on canvas click</label>
                                </div>
                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <label class="form-label small">Scale</label>
                                        <input type="range" class="form-range" id="geo-scale" min="0.5" max="5" step="0.1" value="1">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small">Offset X</label>
                                        <input type="number" class="form-control form-control-sm" id="geo-offset-x" value="0">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label small">Offset Y</label>
                                        <input type="number" class="form-control form-control-sm" id="geo-offset-y" value="0">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button class="btn btn-sm btn-outline-secondary" id="geo-reset-btn" type="button">
                                        <i class="fas fa-undo"></i> Reset Geo Transform
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" id="geo-fit-btn" type="button">
                                        <i class="fas fa-expand"></i> Fit Geo To Bounds
                                    </button>
                                </div>
                                <div class="form-text">Uses TopoJSON from world-atlas/us-atlas (CDN).</div>
                            </div>
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
                        <div class="mb-3 form-check form-switch small">
                            <input class="form-check-input" type="checkbox" id="previewLiveToggle">
                            <label class="form-check-label" for="previewLiveToggle">Preview Live Utilization</label>
                        </div>
                            <div class="mb-3">
                                <label class="form-label small">Node Size</label>
                                <input type="range" class="form-range" id="node-size" min="20" max="60" value="40">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Label Size</label>
                                <input type="range" class="form-range" id="label-size" min="10" max="20" value="12">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Link Width</label>
                                <input type="range" class="form-range" id="link-width" min="1" max="8" value="2">
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
                                <button class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#autoDiscoverModal">
                                    <i class="fas fa-magic"></i> Auto-Discover Topology
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
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.exportJSON()" data-toggle="tooltip" title="Export JSON">
                                <i class="fas fa-download"></i> Export JSON
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.exportSVG()" data-toggle="tooltip" title="Export SVG">
                                <i class="fas fa-file-code"></i> Export SVG
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.exportPNG()" data-toggle="tooltip" title="Export PNG">
                                <i class="fas fa-image"></i> Export PNG
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editorActions.importJSON()" data-toggle="tooltip" title="Import JSON">
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

<!-- Help & Shortcuts Modal -->
<div class="modal fade" id="shortcutsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Help & Shortcuts</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <h6 class="mb-2">Tools</h6>
                <ul class="small mb-3">
                    <li><i class="fas fa-mouse-pointer"></i> Select (V) — select/edit, Shift+Click to multi-select</li>
                    <li><i class="fas fa-hand-paper"></i> Pan (H) — drag canvas, scroll to zoom</li>
                    <li><i class="fas fa-plus-circle"></i> Add Node (N) — click to place node</li>
                    <li><i class="fas fa-link"></i> Add Link (L) — click source, then destination</li>
                    <li><i class="fas fa-font"></i> Add Text (T) — add annotation (coming soon)</li>
                    <li><i class="fas fa-trash"></i> Delete — removes selected items</li>
                </ul>

                <h6 class="mb-2">Canvas</h6>
                <ul class="small mb-3">
                    <li>Zoom: mouse wheel or buttons (top-right)</li>
                    <li>Fit to Screen: zoom to fit visible content</li>
                    <li>Grid: toggle background grid; Snap: snap nodes to grid</li>
                </ul>

                <h6 class="mb-2">Geographic Background</h6>
                <ul class="small mb-3">
                    <li>Enable TopoJSON presets; choose projection</li>
                    <li>Scale/Offset to size/position the map</li>
                    <li>Center on canvas click: shift geo center to click point</li>
                    <li>Reset/Fit Geo: quick restore of geo sizing/position</li>
                </ul>

                <h6 class="mb-2">Shortcuts</h6>
                <table class="table table-sm mb-3">
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
                    <tr><td><kbd>?</kbd></td><td>Open Help</td></tr>
                </table>
                <div class="small text-muted">See docs/EDITOR_D3.md for more details.</div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- D3.js -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://unpkg.com/topojson-client@3"></script>

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
    clipboard: null,
    portCache: {},
    geoCache: {},
    marqueeActive: false,
    marqueeStart: null,
    livePreview: false,
    liveData: null,
    liveTimer: null
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
        
        // Load persisted UI prefs
        try {
            const lastTool = localStorage.getItem('wmng.lastTool');
            if (lastTool) {
                const btn = document.querySelector(`.tool-btn[data-tool="${lastTool}"]`);
                if (btn) btn.click();
            }
            const gridPref = localStorage.getItem('wmng.grid');
            if (gridPref !== null) {
                const val = gridPref === '1';
                document.getElementById('gridToggle').checked = val;
                editorState.grid = val;
                this.svg.select('.grid-background').style('display', val ? 'block' : 'none');
            }
            const snapPref = localStorage.getItem('wmng.snap');
            if (snapPref !== null) {
                const val = snapPref === '1';
                document.getElementById('snapToggle').checked = val;
                editorState.snap = val;
            }
        } catch (e) {}

        // Load initial data if editing
        if (editorState.mapId) {
            this.loadMap();
        }
        
        // Setup event listeners
        this.setupEventListeners();
        this.setupKeyboardShortcuts();
        
        // Initialize minimap
        this.initMinimap();
        
        // Start render loop (event-driven dirty flag)
        editorState.needsRender = true;
        this.render();
    }
    
    handleZoom(event) {
        editorState.zoom = event.transform.k;
        editorState.pan = { x: event.transform.x, y: event.transform.y };
        this.mapGroup.attr('transform', event.transform);
        document.getElementById('zoom-level').textContent = Math.round(editorState.zoom * 100) + '%';
        this.updateMinimap();
        editorState.needsRender = true;
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
        editorState.needsRender = true;
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

    // Live link utilization helpers (editor preview)
    getLinkPct(link) {
        try {
            const live = editorState.liveData && editorState.liveData.links ? editorState.liveData.links[link.id] : null;
            if (!live) return null;
            if (typeof live.pct === 'number') return live.pct;
            const inBps = typeof live.in_bps === 'number' ? live.in_bps : 0;
            const outBps = typeof live.out_bps === 'number' ? live.out_bps : 0;
            const bps = inBps + outBps;
            if (bps && link.bandwidth_bps) {
                return Math.max(0, Math.min(100, (bps / link.bandwidth_bps) * 100));
            }
        } catch (e) {}
        return null;
    }
    getLinkColorByPct(pct) {
        const t1 = 50, t2 = 80;
        if (pct === null) return '#666';
        if (pct >= t2) return '#dc3545';
        if (pct >= t1) return '#ffc107';
        return '#28a745';
    }
    
    render() {
        // Only re-render when needed
        if (!editorState.needsRender) {
            requestAnimationFrame(() => this.render());
            return;
        }
        editorState.needsRender = false;
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
            .on('click', (event, d) => this.onNodeClick(event, d))
            .on('mouseover', (event, d) => {
                if (editorState.tool === 'add-link') {
                    d3.select(event.currentTarget).classed('hover-target', true);
                }
                // Show node tooltip with live traffic if available
                const tip = document.getElementById('editor-tooltip');
                const live = editorState.liveData && editorState.liveData.nodes ? editorState.liveData.nodes[d.id] : null;
                const tr = live && live.traffic ? live.traffic : null;
                if (tip && tr) {
                    const human = (v) => {
                        if (v >= 1e9) return (v/1e9).toFixed(2) + ' Gb/s';
                        if (v >= 1e6) return (v/1e6).toFixed(2) + ' Mb/s';
                        if (v >= 1e3) return (v/1e3).toFixed(2) + ' Kb/s';
                        return (v||0) + ' b/s';
                    };
                    const srcMap = { ports: 'ports', links: 'links', device: 'device', none: 'unknown' };
                    const src = tr.source ? (srcMap[tr.source] || 'unknown') : 'unknown';
                    tip.innerHTML = `${d.label || ('Node ' + d.id)}<br>` +
                        `In: ${human(tr.in_bps||0)}<br>` +
                        `Out: ${human(tr.out_bps||0)}<br>` +
                        `Sum: ${human(tr.sum_bps||0)}<br>` +
                        `<span style="opacity:0.75;">Source: ${src}</span>`;
                    tip.style.display = 'block';
                    tip.style.left = (event.pageX + 10) + 'px';
                    tip.style.top = (event.pageY + 10) + 'px';
                }
            })
            .on('mouseout', (event, d) => {
                d3.select(event.currentTarget).classed('hover-target', false);
                const tip = document.getElementById('editor-tooltip');
                if (tip) tip.style.display = 'none';
            })
            .on('dblclick', (event, d) => this.editNode(d));
        
        // Add node circle
        const nsVal = parseInt(document.getElementById('node-size')?.value || '40', 10);
        const radius = Math.max(6, Math.round(nsVal / 2));
        nodeEnter.append('circle')
            .attr('r', radius)
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
            .attr('font-size', `${Math.max(10, Math.round(radius * 0.7))}px`)
            .text(d => this.getNodeIcon(d.icon));
        
        // Update existing nodes
        const mergedGroups = nodes.merge(nodeEnter)
            .attr('transform', d => `translate(${d.x}, ${d.y})`);

        const nsValUpd = parseInt(document.getElementById('node-size')?.value || '40', 10);
        const radiusUpd = Math.max(6, Math.round(nsValUpd / 2));
        mergedGroups.select('circle')
            .attr('stroke', d => editorState.selectedElements.includes(d) ? '#ffc107' : '#fff')
            .attr('stroke-width', d => editorState.selectedElements.includes(d) ? 4 : 2)
            .attr('stroke-dasharray', d => editorState.selectedElements.includes(d) ? '4 2' : null)
            .attr('r', radiusUpd);

        // Ensure node icon updates when changed
        mergedGroups.select('text')
            .text(d => editor.getNodeIcon(d.icon))
            .attr('font-size', `${Math.max(10, Math.round(radiusUpd * 0.7))}px`);
        
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
        
        const linkBase = parseInt(document.getElementById('link-width')?.value || '2', 10);
        const linkSel = Math.max(linkBase + 2, Math.round(linkBase * 2));
        links.merge(linkEnter)
            .attr('d', d => this.getLinkPath(d))
            .attr('stroke', d => {
                if (editorState.livePreview) {
                    const pct = this.getLinkPct(d);
                    return this.getLinkColorByPct(pct);
                }
                return editorState.selectedElements.includes(d) ? '#ffc107' : '#666';
            })
            .attr('stroke-width', d => editorState.selectedElements.includes(d) ? linkSel : linkBase)
            .attr('stroke-dasharray', d => editorState.selectedElements.includes(d) ? '6 3' : null);
        
        links.exit().remove();

        // Render link labels (simple, high-contrast text)
        const linkLabelGroups = this.mapGroup.select('#labels-layer')
            .selectAll('.link-label')
            .data(document.getElementById('labelsToggle').checked
                ? editorState.links.filter(l => (l.label || '').length > 0)
                : [], d => d.id);

        const linkLabelSize = parseInt(document.getElementById('label-size')?.value || '12', 10);
        const linkLabelEnter = linkLabelGroups.enter()
            .append('g')
            .attr('class', 'link-label');
        linkLabelEnter.append('text')
            .attr('text-anchor', 'middle')
            .attr('font-size', `${linkLabelSize}px`)
            .attr('font-weight', '600')
            .attr('fill', '#ffffff')
            .attr('stroke', '#000000')
            .attr('stroke-width', 2)
            .attr('paint-order', 'stroke fill');

        const linkLabelMerged = linkLabelGroups.merge(linkLabelEnter);
        linkLabelMerged.select('rect').remove(); // remove any old backgrounds if present
        linkLabelMerged.select('text')
            .attr('font-size', `${linkLabelSize}px`)
            .attr('x', d => {
                const s = editorState.nodes.find(n => n.id === d.source);
                const t = editorState.nodes.find(n => n.id === d.target);
                return s && t ? (s.x + t.x) / 2 : 0;
            })
            .attr('y', d => {
                const s = editorState.nodes.find(n => n.id === d.source);
                const t = editorState.nodes.find(n => n.id === d.target);
                return s && t ? (s.y + t.y) / 2 - 6 : 0;
            })
            .text(d => d.label || '');

        linkLabelGroups.exit().remove();

        // Render node labels (simple, high-contrast text)
        if (document.getElementById('labelsToggle').checked) {
            const nodeLabels = this.mapGroup.select('#labels-layer')
                .selectAll('.node-label')
                .data(editorState.nodes, d => d.id);

            const nodeLabelSize = parseInt(document.getElementById('label-size')?.value || '12', 10);
            const nodeLabelEnter = nodeLabels.enter()
                .append('g')
                .attr('class', 'node-label');
            nodeLabelEnter.append('text')
                .attr('text-anchor', 'middle')
                .attr('font-size', `${nodeLabelSize}px`)
                .attr('font-weight', '600')
                .attr('fill', '#ffffff')
                .attr('stroke', '#000000')
                .attr('stroke-width', 2)
                .attr('paint-order', 'stroke fill');

            const nodeLabelMerged = nodeLabels.merge(nodeLabelEnter);
            nodeLabelMerged.select('rect').remove(); // remove any old backgrounds
            nodeLabelMerged.select('text')
                .attr('font-size', `${nodeLabelSize}px`)
                .attr('x', d => d.x)
                .attr('y', d => d.y + 35)
                .text(d => d.label || '');

            nodeLabels.exit().remove();
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

    // Handle node click for both selection and linking
    onNodeClick(event, node) {
        // Ctrl/Cmd click opens device page in a new tab
        if ((event.ctrlKey || event.metaKey) && (node.device_id || node.deviceId)) {
            const base = '{{ url('device') }}';
            const did = node.device_id || node.deviceId;
            window.open(base + '/' + did, '_blank');
            event.stopPropagation();
            return;
        }
        if (editorState.tool === 'add-link') {
            event.stopPropagation();
            if (!editorState.linkStart) {
                editorState.linkStart = node;
                editorState.selectedElements = [node];
                this.updatePropertiesPanel();
                this.showStatus('Select destination node to create link');
            } else if (editorState.linkStart && editorState.linkStart.id !== node.id) {
                const srcId = editorState.linkStart.id;
                const dstId = node.id;
                fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/link`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ src_node_id: srcId, dst_node_id: dstId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.link) {
                        const newLink = {
                            id: data.link.id,
                            source: data.link.src_node_id,
                            target: data.link.dst_node_id,
                            port_id_a: data.link.port_id_a || null,
                            port_id_b: data.link.port_id_b || null,
                            style: data.link.style || {},
                            label: (data.link.style && (data.link.style.label || data.link.style["label"])) || '',
                            type: 'link'
                        };
                        editorState.links.push(newLink);
                        this.addToHistory();
                        this.showStatus('Link created');
                    } else {
                        this.showStatus('Failed to create link', 'error');
                    }
                })
                .catch(() => this.showStatus('Failed to create link', 'error'))
                .finally(() => {
                    editorState.linkStart = null;
                    // Clear preview
                    this.mapGroup.select('#selection-layer').select('#link-preview').remove();
                });
            }
            return;
        }
        this.selectNode(event, node);
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
        const modeBadge = document.getElementById('prop-mode-badge');
        const setBadge = (text, cls) => { if (modeBadge) { modeBadge.textContent = text; modeBadge.className = `badge ${cls}`; } };
        
        // Bulk link edit support
        const allLinks = selected.length > 1 && selected.every(e => e.type === 'link');

        if (selected.length === 0) {
            setBadge('None', 'bg-secondary');
        }

        if (selected.length === 1 || allLinks) {
            const element = selected[0];
            
            if (!allLinks && (element.type === 'node' || !element.type)) {
                // Show node properties
                document.getElementById('node-properties').style.display = 'block';
                document.getElementById('node-label').value = element.label || '';
                document.getElementById('node-device').value = element.device_id || '';
                document.getElementById('node-x').value = Math.round(element.x);
                document.getElementById('node-y').value = Math.round(element.y);
                setBadge('Node', 'bg-primary');
            } else if (element.type === 'link' || allLinks) {
                // Show link properties
                document.getElementById('link-properties').style.display = 'block';
                const linkLabel = document.getElementById('link-label');
                const bwHelp = document.getElementById('link-bw-help');
                linkLabel.value = allLinks ? '' : (element.label || '');
                // Infer units from bandwidth_bps
                const unitSel = document.getElementById('link-bandwidth-unit');
                const bwField = document.getElementById('link-bandwidth');
                if (!allLinks && element.bandwidth_bps && element.bandwidth_bps >= 1e9) {
                    unitSel.value = 'Gbps';
                    bwField.value = Math.round(element.bandwidth_bps / 1e9);
                } else if (!allLinks && element.bandwidth_bps) {
                    unitSel.value = 'Mbps';
                    bwField.value = Math.round(element.bandwidth_bps / 1e6);
                } else {
                    unitSel.value = 'Mbps';
                    bwField.value = '';
                }
                bwHelp.textContent = allLinks ? 'Bulk edit: applies bandwidth/label to all selected links. Ports disabled.' : '';
                // Populate ports for endpoints when single link
                this.populateLinkPorts(allLinks ? null : element);
                // Disable port selects for bulk edits
                document.getElementById('link-port-a').disabled = allLinks;
                document.getElementById('link-port-b').disabled = allLinks;
                // Advanced open/close logic
                const adv = document.getElementById('link-advanced');
                const advKey = allLinks ? 'wmng.link.adv.bulk' : 'wmng.link.adv.single';
                let advPref = null;
                try { advPref = localStorage.getItem(advKey); } catch (e) {}
                const hasNonDefault = !allLinks && (!!element.bandwidth_bps || !!element.port_id_a || !!element.port_id_b);
                const shouldOpen = (advPref === '1') || (advPref === null && hasNonDefault);
                if (adv) adv.style.display = shouldOpen ? 'block' : 'none';
                // If we auto-opened due to non-defaults, persist that for next time
                if (shouldOpen && advPref === null) {
                    try { localStorage.setItem(advKey, '1'); } catch (e) {}
                }
                setBadge(allLinks ? `Bulk Links (${selected.length})` : 'Link', allLinks ? 'bg-warning text-dark' : 'bg-success');
                // Validate after populating
                this.validateLinkForm();
            }
        }
    }

    // Validate link form and enable/disable Apply accordingly
    validateLinkForm() {
        const sel = editorState.selectedElements || [];
        const allLinks = sel.length > 1 && sel.every(e => e.type === 'link');
        const applyBtn = document.getElementById('apply-link-btn');
        const bwField = document.getElementById('link-bandwidth');
        const unitField = document.getElementById('link-bandwidth-unit');
        const portA = document.getElementById('link-port-a');
        const portB = document.getElementById('link-port-b');
        const helpBw = document.getElementById('link-bw-help');
        const helpA = document.getElementById('link-port-a-help');
        const helpB = document.getElementById('link-port-b-help');
        let valid = true;
        // Bandwidth: allow blank, otherwise number >= 0
        const bwVal = (bwField?.value || '').trim();
        if (bwVal !== '') {
            const n = Number(bwVal);
            if (!isFinite(n) || n < 0) {
                valid = false;
                if (helpBw) { helpBw.textContent = 'Enter a non-negative number or leave blank.'; helpBw.classList.add('text-danger'); }
            } else if (helpBw) { helpBw.textContent = ''; helpBw.classList.remove('text-danger'); }
        } else if (helpBw) { helpBw.textContent = ''; helpBw.classList.remove('text-danger'); }
        // Ports validation only for single link mode
        if (!allLinks && sel.length === 1 && sel[0]?.type === 'link') {
            const link = sel[0];
            const srcNode = editorState.nodes.find(n => n.id === link.source);
            const dstNode = editorState.nodes.find(n => n.id === link.target);
            const portAVal = portA?.value || '';
            const portBVal = portB?.value || '';
            // If a port is chosen, require corresponding device
            if (portAVal && !srcNode?.device_id) { valid = false; if (helpA) { helpA.textContent = 'Select a source device to use ports.'; helpA.classList.add('text-danger'); } }
            else if (helpA) { /* keep previous info if any */ if (!helpA.classList.contains('text-muted')) helpA.classList.remove('text-danger'); }
            if (portBVal && !dstNode?.device_id) { valid = false; if (helpB) { helpB.textContent = 'Select a destination device to use ports.'; helpB.classList.add('text-danger'); } }
            else if (helpB) { if (!helpB.classList.contains('text-muted')) helpB.classList.remove('text-danger'); }
        } else {
            if (helpA) { helpA.classList.remove('text-danger'); }
            if (helpB) { helpB.classList.remove('text-danger'); }
        }
        if (applyBtn) applyBtn.disabled = !valid;
        return valid;
    }

    // Populate port dropdowns for selected link based on endpoint devices
    async populateLinkPorts(link) {
        const helpA = document.getElementById('link-port-a-help');
        const helpB = document.getElementById('link-port-b-help');
        helpA.textContent = '';
        helpB.textContent = '';
        if (!link) {
            // bulk mode: clear selects
            document.getElementById('link-port-a').innerHTML = '<option value="">Auto</option>';
            document.getElementById('link-port-b').innerHTML = '<option value="">Auto</option>';
            return;
        }
        const srcNode = editorState.nodes.find(n => n.id === link.source);
        const dstNode = editorState.nodes.find(n => n.id === link.target);
        const selA = document.getElementById('link-port-a');
        const selB = document.getElementById('link-port-b');
        const resetSelect = (sel) => {
            while (sel.options.length > 0) sel.remove(0);
            const opt = document.createElement('option');
            opt.value = '';
            opt.text = 'Auto';
            sel.add(opt);
        };
        resetSelect(selA);
        resetSelect(selB);
        const loadPorts = async (deviceId) => {
            if (!deviceId) return [];
            if (editorState.portCache[deviceId]) {
                return editorState.portCache[deviceId];
            }
            const res = await fetch(`{{ url('plugin/WeathermapNG/api/device') }}/${deviceId}/ports`);
            const data = await res.json();
            const ports = data.ports || [];
            editorState.portCache[deviceId] = ports;
            return ports;
        };
        try {
            if (srcNode && srcNode.device_id) {
                const portsA = await loadPorts(srcNode.device_id);
                if (!portsA.length) {
                    helpA.textContent = 'No ports found on source device.';
                }
                portsA.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.port_id;
                    opt.text = p.ifName || `Port ${p.port_id}`;
                    selA.add(opt);
                });
            } else {
                helpA.textContent = 'No device selected on source node.';
            }
            if (dstNode && dstNode.device_id) {
                const portsB = await loadPorts(dstNode.device_id);
                if (!portsB.length) {
                    helpB.textContent = 'No ports found on destination device.';
                }
                portsB.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.port_id;
                    opt.text = p.ifName || `Port ${p.port_id}`;
                    selB.add(opt);
                });
            } else {
                helpB.textContent = 'No device selected on destination node.';
            }
        } catch (e) {
            this.showStatus('Failed to load ports', 'error');
        }
        // Set selected values if present
        selA.value = link.port_id_a ? String(link.port_id_a) : '';
        selB.value = link.port_id_b ? String(link.port_id_b) : '';

        // Change listeners to update local state
        selA.onchange = () => { link.port_id_a = selA.value ? parseInt(selA.value, 10) : null; };
        selB.onchange = () => { link.port_id_b = selB.value ? parseInt(selB.value, 10) : null; };
    }
    
    saveNodePosition(node) {
        // Debounced save to server
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/node/${node.id}`, {
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
        fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${editorState.mapId}/json`)
            .then(response => response.json())
            .then(data => {
                // Apply background preset if present
                const preset = (data.options && data.options.background_preset) ? data.options.background_preset : 'grid-light';
                editorState.backgroundPreset = preset;
                document.getElementById('preset-background').value = preset;
                this.applyBackgroundPreset(preset);
                // Apply geographic options
                const geo = (data.options && data.options.geo) ? data.options.geo : null;
                if (geo) {
                    document.getElementById('geoToggle').checked = !!geo.enabled;
                    if (geo.preset) document.getElementById('geo-preset').value = geo.preset;
                    if (geo.projection) document.getElementById('geo-proj').value = geo.projection;
                    if (typeof geo.scale !== 'undefined') document.getElementById('geo-scale').value = geo.scale;
                    if (typeof geo.offsetX !== 'undefined') document.getElementById('geo-offset-x').value = geo.offsetX;
                    if (typeof geo.offsetY !== 'undefined') document.getElementById('geo-offset-y').value = geo.offsetY;
                    // Center-on-click is local UI preference; not persisted (leave unchecked by default)
                    this.renderGeoBackground();
                }
                editorState.nodes = (data.nodes || []).map(n => ({
                    id: n.id,
                    x: n.x,
                    y: n.y,
                    label: n.label,
                    device_id: n.device_id || null,
                    icon: (n.meta && n.meta.icon) ? n.meta.icon : 'router',
                    meta: n.meta || {},
                    type: 'node'
                }));
                editorState.links = (data.links || []).map(l => ({
                    id: l.id,
                    source: l.src,
                    target: l.dst,
                    bandwidth_bps: l.bandwidth_bps || null,
                    port_id_a: l.port_id_a || null,
                    port_id_b: l.port_id_b || null,
                    style: l.style || {},
                    label: (l.style && (l.style.label || l.style["label"])) || '',
                    type: 'link'
                }));
                this.render();
                // Restore label size option when available
                try {
                    const opts = data.options || {};
                    const lbl = opts.label_size ? parseInt(opts.label_size, 10) : null;
                    const ns = opts.node_size ? parseInt(opts.node_size, 10) : null;
                    const lw = opts.link_width ? parseInt(opts.link_width, 10) : null;
                    if (lbl && document.getElementById('label-size')) document.getElementById('label-size').value = lbl;
                    if (ns && document.getElementById('node-size')) document.getElementById('node-size').value = ns;
                    if (lw && document.getElementById('link-width')) document.getElementById('link-width').value = lw;
                    editorState.needsRender = true;
                } catch (e) {}
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
                if (editorState.tool !== 'add-link') {
                    editorState.linkStart = null;
                    // Clear preview
                    this.mapGroup.select('#selection-layer').select('#link-preview').remove();
                }
                // Toggle linking class for subtle dimming
                this.mapGroup.classed('linking', editorState.tool === 'add-link');
                // persist last tool
                try { localStorage.setItem('wmng.lastTool', editorState.tool); } catch (e) {}
            });
        });
        
        // Canvas click: add node or center geo
        this.svg.on('click', (event) => {
            const coords = d3.pointer(event, this.mapGroup.node());
            if (editorState.tool === 'add-node') {
                this.addNode(coords[0], coords[1]);
                return;
            }
            // Center geo background on click if enabled
            const geoEnabled = document.getElementById('geoToggle')?.checked;
            const centerOnClick = document.getElementById('geo-center-click')?.checked;
            if (geoEnabled && centerOnClick && this.geoProjection && this.geoFeature) {
                const width = this.svg.node().clientWidth;
                const height = this.svg.node().clientHeight;
                const path = d3.geoPath(this.geoProjection);
                const b = path.bounds(this.geoFeature);
                const cx = (b[0][0] + b[1][0]) / 2;
                const cy = (b[0][1] + b[1][1]) / 2;
                const dx = coords[0] - cx;
                const dy = coords[1] - cy;
                const offXEl = document.getElementById('geo-offset-x');
                const offYEl = document.getElementById('geo-offset-y');
                let offX = parseFloat(offXEl?.value || '0') + dx;
                let offY = parseFloat(offYEl?.value || '0') + dy;
                // Clamp offsets to reasonable bounds
                const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
                const limX = width * 2;
                const limY = height * 2;
                offX = clamp(offX, -limX, limX);
                offY = clamp(offY, -limY, limY);
                if (offXEl) offXEl.value = offX;
                if (offYEl) offYEl.value = offY;
                this.renderGeoBackground();
            }
        });

        // Marquee select (drag on empty canvas when in select tool)
        this.svg.on('mousedown', (event) => {
            if (editorState.tool !== 'select') return;
            // ignore if clicking on a node/link element
            if (event.target.closest('.node') || event.target.closest('.link')) return;
            const start = d3.pointer(event, this.mapGroup.node());
            editorState.marqueeActive = true;
            editorState.marqueeStart = { x: start[0], y: start[1], shift: event.shiftKey };
            const layer = this.mapGroup.select('#selection-layer');
            let rect = layer.select('#marquee');
            if (rect.empty()) {
                rect = layer.append('rect').attr('id', 'marquee').attr('class', 'marquee');
            }
            rect.attr('x', start[0]).attr('y', start[1]).attr('width', 0).attr('height', 0);
        });
        // Update marquee on mousemove (reuse existing handler)
        // Finalize on mouseup anywhere
        document.addEventListener('mouseup', (e) => {
            if (!editorState.marqueeActive) return;
            editorState.marqueeActive = false;
            const end = d3.pointer(e, this.mapGroup.node());
            const x1 = editorState.marqueeStart.x;
            const y1 = editorState.marqueeStart.y;
            const x2 = end[0];
            const y2 = end[1];
            const minX = Math.min(x1, x2);
            const minY = Math.min(y1, y2);
            const maxX = Math.max(x1, x2);
            const maxY = Math.max(y1, y2);
            // Determine selected elements
            const within = (x, y) => x >= minX && x <= maxX && y >= minY && y <= maxY;
            const selectedNodes = editorState.nodes.filter(n => within(n.x, n.y));
            const selectedLinks = editorState.links.filter(l => {
                const s = editorState.nodes.find(n => n.id === l.source);
                const t = editorState.nodes.find(n => n.id === l.target);
                return s && t && within(s.x, s.y) && within(t.x, t.y);
            });
            if (!editorState.marqueeStart.shift) {
                editorState.selectedElements = [];
            }
            // Merge unique
            const addUnique = (arr, el) => { if (!arr.includes(el)) arr.push(el); };
            selectedNodes.forEach(n => addUnique(editorState.selectedElements, n));
            selectedLinks.forEach(l => addUnique(editorState.selectedElements, l));
            this.updatePropertiesPanel();
            editorState.needsRender = true;
            this.mapGroup.select('#selection-layer').select('#marquee').remove();
        });
        
        // Grid toggle
        document.getElementById('gridToggle').addEventListener('change', (e) => {
            editorState.grid = e.target.checked;
            this.svg.select('.grid-background')
                .style('display', editorState.grid ? 'block' : 'none');
            try { localStorage.setItem('wmng.grid', editorState.grid ? '1' : '0'); } catch (e) {}
            editorState.needsRender = true;
        });
        
        // Snap toggle
        document.getElementById('snapToggle').addEventListener('change', (e) => {
            editorState.snap = e.target.checked;
            try { localStorage.setItem('wmng.snap', editorState.snap ? '1' : '0'); } catch (e) {}
        });

        // Live preview toggle
        const liveToggle = document.getElementById('previewLiveToggle');
        if (liveToggle) {
            liveToggle.addEventListener('change', (e) => {
                editorState.livePreview = e.target.checked;
                if (editorState.livePreview) { this.startLivePreview(); } else { this.stopLivePreview(); }
            });
        }
        
        // Node property field bindings (live updates) and apply buttons
        const nodeLabel = document.getElementById('node-label');
        const nodeDevice = document.getElementById('node-device');
        const nodeDeviceSearch = document.getElementById('node-device-search');
        const nodeX = document.getElementById('node-x');
        const nodeY = document.getElementById('node-y');
        const applyNodeBtn = document.getElementById('apply-node-btn');
        // Icon buttons
        document.querySelectorAll('#node-properties [data-icon]')?.forEach(btn => {
            btn.addEventListener('click', () => {
                if (editorState.selectedElements[0]) {
                    editorState.selectedElements[0].icon = btn.getAttribute('data-icon');
                }
            });
        });
        if (nodeLabel) nodeLabel.addEventListener('input', () => {
            if (editorState.selectedElements[0]) {
                editorState.selectedElements[0].label = nodeLabel.value;
            }
        });
        if (nodeDevice) nodeDevice.addEventListener('change', () => {
            if (editorState.selectedElements[0]) {
                editorState.selectedElements[0].device_id = nodeDevice.value || null;
            }
        });
        if (nodeDeviceSearch && nodeDevice) {
            // Debounced server autocomplete with keyboard navigation
            let ddTimer = null; let activeIdx = -1; let results = [];
            const sug = document.getElementById('node-device-suggestions');
            const hideSug = () => { if (sug) { sug.style.display = 'none'; sug.innerHTML=''; activeIdx = -1; } };
            const renderSug = () => {
                if (!sug) return; sug.innerHTML='';
                results.forEach((dev, idx) => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item' + (idx===activeIdx ? ' active' : '');
                    item.textContent = dev.hostname || dev.sysName || `Device ${dev.device_id}`;
                    item.onclick = () => selectDev(dev);
                    sug.appendChild(item);
                });
                sug.style.display = results.length ? 'block' : 'none';
            };
            const selectDev = (dev) => {
                // ensure option exists in select and select it
                let opt = Array.from(nodeDevice.options).find(o => o.value == dev.device_id);
                if (!opt) { opt = document.createElement('option'); opt.value = dev.device_id; opt.textContent = dev.hostname || dev.sysName || `Device ${dev.device_id}`; nodeDevice.appendChild(opt); }
                nodeDevice.value = String(dev.device_id);
                nodeDevice.dispatchEvent(new Event('change'));
                nodeDeviceSearch.value = opt.textContent;
                hideSug();
            };
            nodeDeviceSearch.addEventListener('keydown', (e) => {
                if (!results.length) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = (activeIdx + 1) % results.length; renderSug(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = (activeIdx - 1 + results.length) % results.length; renderSug(); }
                else if (e.key === 'Enter') { e.preventDefault(); if (activeIdx >= 0) selectDev(results[activeIdx]); }
                else if (e.key === 'Escape') { hideSug(); }
            });
            nodeDeviceSearch.addEventListener('blur', () => setTimeout(hideSug, 150));
            nodeDeviceSearch.addEventListener('input', () => {
                const q = nodeDeviceSearch.value.trim();
                clearTimeout(ddTimer);
                ddTimer = setTimeout(async () => {
                    if (q.length < 2) { hideSug(); return; }
                    try {
                        const res = await fetch(`{{ url('plugin/WeathermapNG/api/devices') }}?q=${encodeURIComponent(q)}`);
                        results = await res.json();
                        activeIdx = -1; renderSug();
                    } catch (e) { hideSug(); }
                }, 200);
            });
        }
        const applyPos = () => {
            if (editorState.selectedElements[0]) {
                const n = editorState.selectedElements[0];
                n.x = parseInt(nodeX.value || '0', 10);
                n.y = parseInt(nodeY.value || '0', 10);
                this.updateNodePosition(n);
                this.updateLinks();
            }
        };
        if (nodeX) nodeX.addEventListener('input', applyPos);
        if (nodeY) nodeY.addEventListener('input', applyPos);
        if (applyNodeBtn) applyNodeBtn.addEventListener('click', () => this.applyNodeChanges());

        // Link apply
        const applyLinkBtn = document.getElementById('apply-link-btn');
        if (applyLinkBtn) applyLinkBtn.addEventListener('click', () => this.applyLinkChanges());

        // Preset background select
        const presetSel = document.getElementById('preset-background');
        if (presetSel) {
            presetSel.addEventListener('change', () => {
                const preset = presetSel.value;
                editorState.backgroundPreset = preset;
                this.applyBackgroundPreset(preset);
            });
        }

        // Label size live update
        const labelSize = document.getElementById('label-size');
        if (labelSize) labelSize.addEventListener('input', () => { editorState.needsRender = true; });
        const nodeSize = document.getElementById('node-size');
        if (nodeSize) nodeSize.addEventListener('input', () => { editorState.needsRender = true; });
        const linkWidth = document.getElementById('link-width');
        if (linkWidth) linkWidth.addEventListener('input', () => { editorState.needsRender = true; });

        // Link validation events
        // Port search filters
        const portASearch = document.getElementById('link-port-a-search');
        const portBSearch = document.getElementById('link-port-b-search');
        const selA2 = document.getElementById('link-port-a');
        const selB2 = document.getElementById('link-port-b');
        const filterSelect = (sel, q) => { Array.from(sel?.options || []).forEach(o => { if (!o.value) return; o.style.display = (o.text.toLowerCase().includes(q)) ? '' : 'none'; }); };
        const fetchAndFillPorts = async (deviceId, q, sel) => {
            if (!deviceId || !sel) return;
            try {
                const res = await fetch(`{{ url('plugin/WeathermapNG/api/device') }}/${deviceId}/ports?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                const ports = data.ports || [];
                while (sel.options.length > 0) sel.remove(0);
                const opt0 = document.createElement('option'); opt0.value = ''; opt0.text = 'Auto'; sel.add(opt0);
                ports.forEach(p => { const o=document.createElement('option'); o.value=p.port_id; o.text=p.ifName || `Port ${p.port_id}`; sel.add(o); });
            } catch (e) {}
        };
        if (portASearch && selA2) {
          let idxA = -1; const sugA = document.getElementById('link-port-a-suggestions');
          const hideA = ()=>{ if(sugA){sugA.style.display='none';sugA.innerHTML='';idxA=-1;} };
          portASearch.addEventListener('keydown', (e)=>{
            const items = Array.from(sugA?.children||[]);
            if (!items.length) return;
            if (e.key==='ArrowDown'){ e.preventDefault(); idxA=(idxA+1)%items.length; items.forEach((it,i)=>it.classList.toggle('active',i===idxA)); }
            else if (e.key==='ArrowUp'){ e.preventDefault(); idxA=(idxA-1+items.length)%items.length; items.forEach((it,i)=>it.classList.toggle('active',i===idxA)); }
            else if (e.key==='Enter'){ e.preventDefault(); if(idxA>=0){ items[idxA].click(); hideA(); } }
            else if (e.key==='Escape'){ hideA(); }
          });
          portASearch.addEventListener('blur', ()=> setTimeout(hideA,150));
          portASearch.addEventListener('input', async () => {
            const q = portASearch.value.toLowerCase();
            const link = editorState.selectedElements.find(e => e.type === 'link');
            const srcNode = link ? editorState.nodes.find(n => n.id === link.source) : null;
            if (q.length >= 2 && srcNode?.device_id) { await fetchAndFillPorts(srcNode.device_id, q, selA2); }
            else { filterSelect(selA2, q); }
            // suggestions dropdown
            const sug = sugA; if (!sug) return;
            while (sug.firstChild) sug.removeChild(sug.firstChild);
            if (q.length < 2) { sug.style.display='none'; return; }
            Array.from(selA2.options).slice(1, 11).forEach((opt) => {
                if (opt.style.display==='none') return;
                const div = document.createElement('div'); div.className='autocomplete-item'; div.textContent=opt.text; div.onclick=()=>{ selA2.value=opt.value; selA2.dispatchEvent(new Event('change')); sug.style.display='none'; portASearch.value=opt.text; };
                sug.appendChild(div);
            });
            sug.style.display = sug.children.length ? 'block' : 'none';
          });
        }
        if (portBSearch && selB2) {
          let idxB = -1; const sugB = document.getElementById('link-port-b-suggestions');
          const hideB = ()=>{ if(sugB){sugB.style.display='none';sugB.innerHTML='';idxB=-1;} };
          portBSearch.addEventListener('keydown', (e)=>{
            const items = Array.from(sugB?.children||[]);
            if (!items.length) return;
            if (e.key==='ArrowDown'){ e.preventDefault(); idxB=(idxB+1)%items.length; items.forEach((it,i)=>it.classList.toggle('active',i===idxB)); }
            else if (e.key==='ArrowUp'){ e.preventDefault(); idxB=(idxB-1+items.length)%items.length; items.forEach((it,i)=>it.classList.toggle('active',i===idxB)); }
            else if (e.key==='Enter'){ e.preventDefault(); if(idxB>=0){ items[idxB].click(); hideB(); } }
            else if (e.key==='Escape'){ hideB(); }
          });
          portBSearch.addEventListener('blur', ()=> setTimeout(hideB,150));
          portBSearch.addEventListener('input', async () => {
            const q = portBSearch.value.toLowerCase();
            const link = editorState.selectedElements.find(e => e.type === 'link');
            const dstNode = link ? editorState.nodes.find(n => n.id === link.target) : null;
            if (q.length >= 2 && dstNode?.device_id) { await fetchAndFillPorts(dstNode.device_id, q, selB2); }
            else { filterSelect(selB2, q); }
            const sug = sugB; if (!sug) return;
            while (sug.firstChild) sug.removeChild(sug.firstChild);
            if (q.length < 2) { sug.style.display='none'; return; }
            Array.from(selB2.options).slice(1, 11).forEach((opt) => {
                if (opt.style.display==='none') return;
                const div = document.createElement('div'); div.className='autocomplete-item'; div.textContent=opt.text; div.onclick=()=>{ selB2.value=opt.value; selB2.dispatchEvent(new Event('change')); sug.style.display='none'; portBSearch.value=opt.text; };
                sug.appendChild(div);
            });
            sug.style.display = sug.children.length ? 'block' : 'none';
          });
        }

        const bwField = document.getElementById('link-bandwidth');
        const unitField = document.getElementById('link-bandwidth-unit');
        const portA = document.getElementById('link-port-a');
        const portB = document.getElementById('link-port-b');
        const revalidate = () => this.validateLinkForm();
        if (bwField) bwField.addEventListener('input', revalidate);
        if (unitField) unitField.addEventListener('change', revalidate);
        if (portA) portA.addEventListener('change', revalidate);
        if (portB) portB.addEventListener('change', revalidate);

        // Link advanced toggle (separate prefs for single vs bulk)
        const advBtn = document.getElementById('toggle-link-advanced');
        const advSection = document.getElementById('link-advanced');
        if (advBtn && advSection) {
            advBtn.addEventListener('click', () => {
                const show = advSection.style.display === 'none';
                advSection.style.display = show ? 'block' : 'none';
                const sel = editorState.selectedElements || [];
                const allLinks = sel.length > 1 && sel.every(e => e.type === 'link');
                const key = allLinks ? 'wmng.link.adv.bulk' : 'wmng.link.adv.single';
                try { localStorage.setItem(key, show ? '1' : '0'); } catch (e) {}
            });
        }

        // Geographic background controls
        const geoToggle = document.getElementById('geoToggle');
        const geoPreset = document.getElementById('geo-preset');
        const geoProj = document.getElementById('geo-proj');
        const geoScale = document.getElementById('geo-scale');
        const geoOffX = document.getElementById('geo-offset-x');
        const geoOffY = document.getElementById('geo-offset-y');
        if (geoToggle) geoToggle.addEventListener('change', () => { this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.enabled', geoToggle.checked ? '1' : '0'); } catch (e) {} });
        if (geoPreset) geoPreset.addEventListener('change', () => { this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.preset', geoPreset.value); } catch (e) {} });
        if (geoProj) geoProj.addEventListener('change', () => { this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.proj', geoProj.value); } catch (e) {} });
        if (geoScale) geoScale.addEventListener('input', () => { this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.scale', geoScale.value); } catch (e) {} });
        if (geoOffX) geoOffX.addEventListener('input', () => {
            const width = this.svg.node().clientWidth;
            const limX = width * 2;
            geoOffX.value = Math.max(-limX, Math.min(limX, parseFloat(geoOffX.value || '0')));
            this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.offx', geoOffX.value); } catch (e) {}
        });
        if (geoOffY) geoOffY.addEventListener('input', () => {
            const height = this.svg.node().clientHeight;
            const limY = height * 2;
            geoOffY.value = Math.max(-limY, Math.min(limY, parseFloat(geoOffY.value || '0')));
            this.renderGeoBackground(); try { localStorage.setItem('wmng.geo.offy', geoOffY.value); } catch (e) {}
        });
        const geoReset = document.getElementById('geo-reset-btn');
        const geoFit = document.getElementById('geo-fit-btn');
        if (geoReset) geoReset.addEventListener('click', () => {
            const s = document.getElementById('geo-scale');
            const ox = document.getElementById('geo-offset-x');
            const oy = document.getElementById('geo-offset-y');
            if (s) s.value = 1;
            if (ox) ox.value = 0;
            if (oy) oy.value = 0;
            this.renderGeoBackground();
        });
        if (geoFit) geoFit.addEventListener('click', () => {
            // Same as reset, ensures projection refits via render
            const s = document.getElementById('geo-scale');
            const ox = document.getElementById('geo-offset-x');
            const oy = document.getElementById('geo-offset-y');
            if (s) s.value = 1;
            if (ox) ox.value = 0;
            if (oy) oy.value = 0;
            this.renderGeoBackground();
        });
        
        // Mouse position tracking + link preview
        this.svg.on('mousemove', (event) => {
            const coords = d3.pointer(event, this.mapGroup.node());
            document.getElementById('coords').textContent = 
                `X: ${Math.round(coords[0])}, Y: ${Math.round(coords[1])}`;
            // Link preview
            if (editorState.tool === 'add-link' && editorState.linkStart) {
                const s = editorState.linkStart;
                const preview = this.mapGroup.select('#selection-layer').select('#link-preview');
                const path = `M${s.x},${s.y}L${coords[0]},${coords[1]}`;
                const dx = coords[0] - s.x;
                const dy = coords[1] - s.y;
                const dist = Math.sqrt(dx*dx + dy*dy);
                const width = dist > 400 ? 3.5 : dist > 200 ? 2.5 : 1.8;
                if (preview.empty()) {
                    this.mapGroup.select('#selection-layer')
                        .append('path')
                        .attr('id', 'link-preview')
                        .attr('fill', 'none')
                        .attr('stroke', '#999')
                        .attr('stroke-dasharray', '4 2')
                        .attr('stroke-width', width)
                        .attr('pointer-events', 'none')
                        .attr('d', path);
                } else {
                    preview.attr('d', path).attr('stroke-width', width);
                }
            }
            // Marquee update
            if (editorState.marqueeActive && editorState.marqueeStart) {
                const x1 = editorState.marqueeStart.x;
                const y1 = editorState.marqueeStart.y;
                const x2 = coords[0];
                const y2 = coords[1];
                const minX = Math.min(x1, x2);
                const minY = Math.min(y1, y2);
                const w = Math.abs(x2 - x1);
                const h = Math.abs(y2 - y1);
                const rect = this.mapGroup.select('#selection-layer').select('#marquee');
                rect.attr('x', minX).attr('y', minY).attr('width', w).attr('height', h);
            }
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
                    case '?': try { $('#shortcutsModal').modal('show'); } catch(err) {} break;
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
        fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/node`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                label: node.label,
                x: node.x,
                y: node.y,
                device_id: node.device_id || null
            })
        }).then(response => response.json())
          .then(data => {
              if (data && data.success && data.node) {
                  node.id = data.node.id;
              }
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
                fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/node/${element.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
            } else if (element.type === 'link') {
                editorState.links = editorState.links.filter(l => l !== element);
                
                // Delete from server
                fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/link/${element.id}`, {
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
        
        fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${editorState.mapId}/save`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                title: data.title,
                options: { 
                    ...data.options, 
                    background_preset: (document.getElementById('preset-background')?.value || 'grid-light'),
                    geo: {
                        enabled: document.getElementById('geoToggle')?.checked || false,
                        preset: document.getElementById('geo-preset')?.value || 'none',
                        projection: document.getElementById('geo-proj')?.value || 'mercator',
                        scale: parseFloat(document.getElementById('geo-scale')?.value || '1'),
                        offsetX: parseFloat(document.getElementById('geo-offset-x')?.value || '0'),
                        offsetY: parseFloat(document.getElementById('geo-offset-y')?.value || '0')
                    },
                    node_size: parseInt(document.getElementById('node-size')?.value || '40', 10),
                    label_size: parseInt(document.getElementById('label-size')?.value || '12', 10),
                    link_width: parseInt(document.getElementById('link-width')?.value || '2', 10)
                },
                nodes: editorState.nodes.map((n, idx) => ({
                    id: n.id ?? idx, // include client id to aid server-side mapping
                    label: n.label,
                    x: n.x,
                    y: n.y,
                    device_id: n.device_id || null,
                    meta: { ...(n.meta || {}), icon: n.icon || 'router' }
                })),
                links: editorState.links.map(l => {
                    const src = (l.source && typeof l.source === 'object') ? (l.source.id ?? l.source.node_id ?? l.source.index ?? l.source) : l.source;
                    const dst = (l.target && typeof l.target === 'object') ? (l.target.id ?? l.target.node_id ?? l.target.index ?? l.target) : l.target;
                    return {
                    src_node_id: src,
                    dst_node_id: dst,
                    port_id_a: l.port_id_a || null,
                    port_id_b: l.port_id_b || null,
                    bandwidth_bps: l.bandwidth_bps || null,
                    style: { ...(l.style || {}), label: l.label || '' }
                    };
                })
            })
        }).then(async response => {
            let payload = {};
            try { payload = await response.json(); } catch (e) { payload = { success: false, message: 'Invalid response' }; }
            if (response.ok && payload.success) {
                this.showStatus('Map saved successfully');
            } else {
                this.showStatus('Error saving map: ' + (payload.message || response.status + ' ' + response.statusText), 'error');
            }
        }).catch(err => this.showStatus('Error saving map: ' + err.message, 'error'));
    }
    
    cancelOperation() {
        editorState.isLinking = false;
        editorState.linkStart = null;
        // Clear preview
        this.mapGroup.select('#selection-layer').select('#link-preview').remove();
        this.mapGroup.classed('linking', false);
        editorState.selectedElements = [];
        this.updatePropertiesPanel();
        this.setTool('select');
    }
    
    // Apply changes from the node properties panel
    applyNodeChanges() {
        if (!editorState.selectedElements[0]) return;
        const n = editorState.selectedElements[0];
        const payload = {
            label: n.label,
            x: Math.round(n.x),
            y: Math.round(n.y),
            device_id: n.device_id || null,
            meta: { ...(n.meta || {}), icon: n.icon || 'router' }
        };
        fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/node/${n.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        }).then(r => r.json())
          .then(data => {
              if (data && data.success) {
                  this.showStatus('Node updated');
                  this.addToHistory();
              } else {
                  this.showStatus('Failed to update node', 'error');
              }
          })
          .catch(() => this.showStatus('Failed to update node', 'error'));
    }

    // Apply changes from the link properties panel
    applyLinkChanges() {
        if (!editorState.selectedElements[0]) return;
        if (!this.validateLinkForm()) return;
        const selected = editorState.selectedElements.filter(e => e.type === 'link');
        const l = selected[0];
        const bwField = document.getElementById('link-bandwidth');
        const unitField = document.getElementById('link-bandwidth-unit');
        const labelField = document.getElementById('link-label');
        const bwVal = parseFloat(bwField?.value || '0');
        const unit = unitField?.value || 'Mbps';
        let bps = null;
        if (!isNaN(bwVal) && bwVal > 0) {
            bps = Math.round(bwVal * (unit === 'Gbps' ? 1e9 : 1e6));
        }
        const newLabel = labelField?.value || '';
        const isBulk = selected.length > 1;
        const updates = [];
        selected.forEach(link => {
            const payload = {
                bandwidth_bps: bps,
                style: { ...(link.style || {}), label: newLabel }
            };
            if (!isBulk) {
                payload.port_id_a = (document.getElementById('link-port-a')?.value || '') || null;
                payload.port_id_b = (document.getElementById('link-port-b')?.value || '') || null;
            }
            // Update local state
            link.bandwidth_bps = bps;
            link.label = newLabel;
            link.style = payload.style;
            if (!isBulk) {
                link.port_id_a = payload.port_id_a ? parseInt(payload.port_id_a, 10) : null;
                link.port_id_b = payload.port_id_b ? parseInt(payload.port_id_b, 10) : null;
            }
            updates.push(fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/link/${link.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            }).then(r => r.json()));
        });

        Promise.all(updates)
            .then(responses => {
                const ok = responses.every(d => d && d.success);
                this.showStatus(ok ? (isBulk ? `Updated ${selected.length} links` : 'Link updated') : 'Some updates failed', ok ? 'info' : 'error');
                if (ok) this.addToHistory();
            })
            .catch(() => this.showStatus('Failed to update links', 'error'));
    }
    
    showStatus(message, type = 'info') {
        const bar = document.getElementById('snackbar');
        if (!bar) return;
        bar.textContent = message;
        bar.className = `snackbar show ${type}`;
        setTimeout(() => {
            bar.className = 'snackbar';
            const inline = document.getElementById('status-inline');
            if (inline) inline.textContent = 'Ready';
        }, 2200);
        const inline = document.getElementById('status-inline');
        if (inline) inline.textContent = message;
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

    // Live preview polling
    startLivePreview() {
        if (!editorState.mapId) return;
        this.stopLivePreview();
        const fetchLive = () => {
            fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${editorState.mapId}/live`)
                .then(r => r.json())
                .then(data => { editorState.liveData = data; editorState.needsRender = true; })
                .catch(() => {});
        };
        fetchLive();
        editorState.liveTimer = setInterval(fetchLive, 5000);
    }
    stopLivePreview() {
        if (editorState.liveTimer) { clearInterval(editorState.liveTimer); editorState.liveTimer = null; }
        editorState.liveData = null;
        editorState.needsRender = true;
    }

    // Apply a preset background fill
    applyBackgroundPreset(preset) {
        const bgRect = this.svg.select('.grid-background');
        switch (preset) {
            case 'grid-light':
                bgRect.attr('fill', 'url(#grid)');
                break;
            case 'grid-dark':
                bgRect.attr('fill', 'url(#grid-dark)');
                break;
            case 'dots':
                bgRect.attr('fill', 'url(#dots)');
                break;
            case 'hex':
                bgRect.attr('fill', 'url(#hex)');
                break;
            case 'gradient-blue':
                bgRect.attr('fill', 'url(#bg-gradient-blue)');
                break;
            case 'gradient-gray':
                bgRect.attr('fill', 'url(#bg-gradient-gray)');
                break;
            default:
                bgRect.attr('fill', document.getElementById('map-bg-color').value || '#ffffff');
        }
    }

    // Geographic background helpers
    getGeoPresetInfo(preset) {
        switch (preset) {
            case 'world-110m':
                return { url: 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json', object: 'countries' };
            case 'world-50m':
                return { url: 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-50m.json', object: 'countries' };
            case 'us-10m':
                return { url: 'https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json', object: 'states' };
            default:
                return null;
        }
    }

    async renderGeoBackground() {
        const enabled = document.getElementById('geoToggle').checked;
        const preset = document.getElementById('geo-preset').value;
        const projName = document.getElementById('geo-proj').value;
        const scaleFactor = parseFloat(document.getElementById('geo-scale')?.value || '1');
        const offsetX = parseFloat(document.getElementById('geo-offset-x')?.value || '0');
        const offsetY = parseFloat(document.getElementById('geo-offset-y')?.value || '0');
        const layer = this.mapGroup.select('#background-layer');
        layer.selectAll('.geo-layer').remove();
        if (!enabled || preset === 'none') return;

        const info = this.getGeoPresetInfo(preset);
        if (!info) return;
        try {
            let topo = editorState.geoCache[info.url];
            if (!topo) {
                const res = await fetch(info.url);
                topo = await res.json();
                editorState.geoCache[info.url] = topo;
            }
            const feature = topojson.feature(topo, topo.objects[info.object]);
            const width = this.svg.node().clientWidth;
            const height = this.svg.node().clientHeight;
            let projection = projName === 'equirect' ? d3.geoEquirectangular() : d3.geoMercator();
            projection.fitSize([width, height], feature);
            // Apply user scaling and offsets (in pixels)
            const baseScale = projection.scale();
            projection.scale(baseScale * (isFinite(scaleFactor) ? scaleFactor : 1));
            const [tx, ty] = projection.translate();
            projection.translate([tx + (isFinite(offsetX) ? offsetX : 0), ty + (isFinite(offsetY) ? offsetY : 0)]);
            const path = d3.geoPath(projection);
            // keep for centering behavior
            this.geoProjection = projection;
            this.geoFeature = feature;

            // Land
            layer.append('path')
                .datum(feature)
                .attr('class', 'geo-layer')
                .attr('fill', '#f5faff')
                .attr('stroke', '#cbd5e1')
                .attr('stroke-width', 0.8)
                .attr('d', path);
        } catch (e) {
            this.showStatus('Failed to load map preset', 'error');
        }
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
    exportSVG: () => {
        try {
            const svg = document.getElementById('map-svg');
            const serializer = new XMLSerializer();
            let source = serializer.serializeToString(svg);
            if (!source.match(/^<svg[^>]+xmlns=/)) {
                source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
            }
            const blob = new Blob([source], {type: 'image/svg+xml;charset=utf-8'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = `weathermap-${editorState.mapId || 'new'}.svg`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (e) { editor.showStatus('SVG export failed', 'error'); }
    },
    exportPNG: () => {
        try {
            const svg = document.getElementById('map-svg');
            const serializer = new XMLSerializer();
            let source = serializer.serializeToString(svg);
            if (!source.match(/^<svg[^>]+xmlns=/)) {
                source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
            }
            const svgBlob = new Blob([source], {type: 'image/svg+xml;charset=utf-8'});
            const url = URL.createObjectURL(svgBlob);
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const bbox = svg.getBoundingClientRect();
                canvas.width = Math.max(1, Math.floor(bbox.width));
                canvas.height = Math.max(1, Math.floor(bbox.height));
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
                URL.revokeObjectURL(url);
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = `weathermap-${editorState.mapId || 'new'}.png`;
                a.click();
            };
            img.onerror = function() { editor.showStatus('PNG export failed', 'error'); };
            img.src = url;
        } catch (e) { editor.showStatus('PNG export failed', 'error'); }
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
    },
    autoDiscover: () => {
        if (!editorState.mapId) { editor.showStatus('Save map first to enable auto-discovery', 'error'); return; }
        fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/autodiscover`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(r => r.json()).then(data => {
            if (data && data.success) {
                editor.showStatus('Auto-discovery complete');
                editor.loadMap();
            } else {
                editor.showStatus('Auto-discovery failed', 'error');
            }
        }).catch(() => editor.showStatus('Auto-discovery failed', 'error'));
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
    
    // Initialize bootstrap/jQuery tooltips if available
    try { $('[data-toggle="tooltip"]').tooltip(); } catch (e) {}
});
</script>
<!-- Auto-Discover Modal -->
<div class="modal fade" id="autoDiscoverModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Auto-Discover Topology</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Minimum Links (degree)</label>
          <input type="number" class="form-control form-control-sm" id="ad-min-degree" min="0" value="0">
          <div class="form-text">Only include devices with at least this many links.</div>
        </div>
        <div class="mb-3">
          <label class="form-label small">OS filter (comma separated)</label>
          <input type="text" class="form-control form-control-sm" id="ad-os" placeholder="e.g., ios, junos, arista">
          <div class="form-text">Include only devices whose OS contains these keywords.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="ad-run-btn">Run Discovery</button>
      </div>
    </div>
  </div>
  <script>
    document.getElementById('ad-run-btn')?.addEventListener('click', async () => {
      try {
        const minDeg = parseInt(document.getElementById('ad-min-degree').value || '0', 10);
        const os = (document.getElementById('ad-os').value || '').trim();
        if (!editorState.mapId) { editor.showStatus('Save map first', 'error'); return; }
        const res = await fetch(`{{ url('plugin/WeathermapNG/map') }}/${editorState.mapId}/autodiscover`, {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ min_degree: isFinite(minDeg) ? minDeg : 0, os: os })
        });
        const data = await res.json();
        if (data && data.success) { editor.showStatus('Auto-discovery complete'); editor.loadMap(); }
        else { editor.showStatus('Auto-discovery failed', 'error'); }
      } catch (e) { editor.showStatus('Auto-discovery failed', 'error'); }
      try { $('#autoDiscoverModal').modal('hide'); } catch(e) {}
    });
  </script>
</div>
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

/* Autocomplete dropdown */
.autocomplete-list {
    position: absolute;
    top: 52px;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 180px;
    overflow-y: auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.autocomplete-item {
    padding: 6px 8px;
    font-size: 12px;
    cursor: pointer;
}
.autocomplete-item:hover, .autocomplete-item.active {
    background: #f1f5ff;
}

.marquee {
    fill: rgba(102, 126, 234, 0.1);
    stroke: #667eea;
    stroke-width: 1.5px;
    stroke-dasharray: 4 2;
}

.snackbar {
    visibility: hidden;
    min-width: 200px;
    background-color: rgba(33,37,41,0.9);
    color: #fff;
    text-align: left;
    border-radius: 4px;
    padding: 8px 12px;
    position: absolute;
    z-index: 1000;
    left: 14px;
    bottom: 14px;
    font-size: 12px;
}
.snackbar.show { visibility: visible; transition: opacity 0.2s ease; }
.snackbar.error { background-color: rgba(220,53,69,0.95); }
.snackbar.info { background-color: rgba(33,37,41,0.9); }

.label-bg {
    fill: #ffffff;
    stroke: #dddddd;
    opacity: 0.9;
    filter: drop-shadow(0 1px 1px rgba(0,0,0,0.2));
}

.node.hover-target circle {
    stroke: #17a2b8 !important; /* info */
    stroke-width: 3 !important;
}

/* Subtle dimming while in linking mode */
#map-group.linking .node { opacity: 0.75; }
#map-group.linking .node.hover-target { opacity: 1; }
#map-group.linking .link { opacity: 0.5; }
#map-group.linking .link-label { opacity: 0.7; }

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

/* Sticky apply rows in property panels */
.sticky-apply {
    position: sticky;
    bottom: 0;
    background: linear-gradient(to top, rgba(255,255,255,1), rgba(255,255,255,0.85));
    z-index: 2;
}
</style>
@endsection
