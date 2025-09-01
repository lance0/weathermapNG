<?php
/**
 * WeathermapNG Modern Map Editor
 * Enhanced with professional features
 */

// Get map details
$map = dbFetchRow("SELECT * FROM wmng_maps WHERE id = ?", [$mapId]);
if (!$map) {
    echo "<div class='alert alert-danger'>Map not found!</div>";
    exit;
}

// Get nodes for this map
$nodes = dbFetchRows("SELECT * FROM wmng_nodes WHERE map_id = ? ORDER BY id", [$mapId]);

// Get links for this map  
$links = dbFetchRows("SELECT * FROM wmng_links WHERE map_id = ? ORDER BY id", [$mapId]);

// Get devices for dropdown
$devices = dbFetchRows("SELECT device_id, hostname, sysName, type FROM devices ORDER BY hostname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit: <?php echo htmlspecialchars($map['name']); ?> - WeathermapNG</title>
    
    <!-- Include WeathermapNG CSS -->
    <link href="/plugins/WeathermapNG/css/weathermapng.css" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f2f5;
        }
        
        .editor-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Toolbar */
        .editor-toolbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .toolbar-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-toolbar {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-toolbar:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        
        /* Sidebar */
        .editor-sidebar {
            width: 320px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
            margin-top: 60px;
        }
        
        .sidebar-section {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .sidebar-section h3 {
            margin: 0 0 1rem 0;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        
        /* Canvas Area */
        .editor-canvas-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            margin-top: 60px;
            position: relative;
            overflow: auto;
        }
        
        .canvas-wrapper {
            position: relative;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        #mapCanvas {
            display: block;
            cursor: crosshair;
        }
        
        /* Tool Buttons */
        .tool-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        
        .tool-btn {
            padding: 0.75rem;
            border: 2px solid #e1e4e8;
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .tool-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
        }
        
        .tool-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .tool-btn i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .tool-btn span {
            font-size: 0.75rem;
        }
        
        /* Properties Panel */
        .properties-panel {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .property-group {
            margin-bottom: 1rem;
        }
        
        .property-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #495057;
        }
        
        .property-group input,
        .property-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        /* Grid Overlay */
        .grid-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(0deg, transparent, transparent 19px, rgba(0,0,0,0.05) 19px, rgba(0,0,0,0.05) 20px),
                repeating-linear-gradient(90deg, transparent, transparent 19px, rgba(0,0,0,0.05) 19px, rgba(0,0,0,0.05) 20px);
            pointer-events: none;
            display: none;
        }
        
        .grid-overlay.active {
            display: block;
        }
        
        /* Context Menu */
        .context-menu {
            position: fixed;
            background: white;
            border: 1px solid #e1e4e8;
            border-radius: 0.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 0.5rem 0;
            display: none;
            z-index: 2000;
            min-width: 160px;
        }
        
        .context-menu-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .context-menu-item:hover {
            background: #f8f9fa;
        }
        
        .context-menu-item i {
            width: 20px;
            margin-right: 0.5rem;
            color: #6c757d;
        }
        
        /* Status Bar */
        .status-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #2d3748;
            color: white;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            z-index: 999;
        }
        
        .status-info {
            display: flex;
            gap: 2rem;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Mini Map */
        .minimap {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 150px;
            background: white;
            border: 2px solid #e1e4e8;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .minimap canvas {
            width: 100%;
            height: 100%;
        }
        
        /* Zoom Controls */
        .zoom-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .zoom-btn {
            padding: 0.75rem;
            border: none;
            background: white;
            cursor: pointer;
            transition: background 0.2s;
            display: block;
            width: 40px;
            height: 40px;
        }
        
        .zoom-btn:hover {
            background: #f8f9fa;
        }
        
        .zoom-btn + .zoom-btn {
            border-top: 1px solid #e1e4e8;
        }
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="editor-toolbar">
        <div class="toolbar-title">
            <h2 style="margin: 0;">
                <i class="fas fa-edit"></i> 
                <?php echo htmlspecialchars($map['name']); ?>
            </h2>
        </div>
        <div class="toolbar-actions">
            <button class="btn-toolbar" onclick="toggleGrid()">
                <i class="fas fa-border-all"></i> Grid
            </button>
            <button class="btn-toolbar" onclick="toggleSnap()">
                <i class="fas fa-magnet"></i> Snap
            </button>
            <button class="btn-toolbar" onclick="undo()">
                <i class="fas fa-undo"></i> Undo
            </button>
            <button class="btn-toolbar" onclick="redo()">
                <i class="fas fa-redo"></i> Redo
            </button>
            <button class="btn-toolbar" onclick="clearAll()">
                <i class="fas fa-trash"></i> Clear
            </button>
            <button class="btn-toolbar" onclick="saveMap()" style="background: rgba(40,167,69,0.2); border-color: rgba(40,167,69,0.3);">
                <i class="fas fa-save"></i> Save
            </button>
            <button class="btn-toolbar" onclick="exitEditor()">
                <i class="fas fa-times"></i> Exit
            </button>
        </div>
    </div>

    <div class="editor-container">
        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- Tools Section -->
            <div class="sidebar-section">
                <h3>Tools</h3>
                <div class="tool-grid">
                    <button class="tool-btn" onclick="setTool('select')" data-tool="select">
                        <i class="fas fa-mouse-pointer"></i>
                        <span>Select</span>
                    </button>
                    <button class="tool-btn" onclick="setTool('node')" data-tool="node">
                        <i class="fas fa-circle"></i>
                        <span>Add Node</span>
                    </button>
                    <button class="tool-btn" onclick="setTool('link')" data-tool="link">
                        <i class="fas fa-link"></i>
                        <span>Add Link</span>
                    </button>
                    <button class="tool-btn" onclick="setTool('text')" data-tool="text">
                        <i class="fas fa-font"></i>
                        <span>Add Text</span>
                    </button>
                    <button class="tool-btn" onclick="setTool('move')" data-tool="move">
                        <i class="fas fa-arrows-alt"></i>
                        <span>Pan</span>
                    </button>
                    <button class="tool-btn" onclick="setTool('delete')" data-tool="delete">
                        <i class="fas fa-eraser"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>

            <!-- Node Templates -->
            <div class="sidebar-section">
                <h3>Node Templates</h3>
                <div class="tool-grid">
                    <button class="tool-btn" onclick="addTemplateNode('router')">
                        <i class="fas fa-server"></i>
                        <span>Router</span>
                    </button>
                    <button class="tool-btn" onclick="addTemplateNode('switch')">
                        <i class="fas fa-network-wired"></i>
                        <span>Switch</span>
                    </button>
                    <button class="tool-btn" onclick="addTemplateNode('firewall')">
                        <i class="fas fa-shield-alt"></i>
                        <span>Firewall</span>
                    </button>
                    <button class="tool-btn" onclick="addTemplateNode('server')">
                        <i class="fas fa-database"></i>
                        <span>Server</span>
                    </button>
                </div>
            </div>

            <!-- Properties -->
            <div class="sidebar-section">
                <h3>Properties</h3>
                <div class="properties-panel" id="propertiesPanel">
                    <p class="text-muted">Select an element to edit its properties</p>
                </div>
            </div>

            <!-- Device Assignment -->
            <div class="sidebar-section">
                <h3>Device Assignment</h3>
                <div class="property-group">
                    <label>LibreNMS Device</label>
                    <select id="deviceSelect" class="form-control">
                        <option value="">-- No Device --</option>
                        <?php foreach ($devices as $device): ?>
                            <option value="<?php echo $device['device_id']; ?>" data-type="<?php echo $device['type']; ?>">
                                <?php echo htmlspecialchars($device['hostname']); ?>
                                <?php if ($device['sysName'] != $device['hostname']): ?>
                                    (<?php echo htmlspecialchars($device['sysName']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm mt-2" onclick="assignDevice()">
                        Assign to Selected
                    </button>
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="editor-canvas-container">
            <div class="canvas-wrapper">
                <div class="grid-overlay" id="gridOverlay"></div>
                <canvas id="mapCanvas" 
                        width="<?php echo $map['width']; ?>" 
                        height="<?php echo $map['height']; ?>">
                </canvas>
                
                <!-- Zoom Controls -->
                <div class="zoom-controls">
                    <button class="zoom-btn" onclick="zoomIn()">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="zoom-btn" onclick="resetZoom()">
                        <i class="fas fa-compress"></i>
                    </button>
                    <button class="zoom-btn" onclick="zoomOut()">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
                
                <!-- Mini Map -->
                <div class="minimap">
                    <canvas id="minimapCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="status-bar">
        <div class="status-info">
            <div class="status-item">
                <i class="fas fa-mouse-pointer"></i>
                <span id="cursorPos">0, 0</span>
            </div>
            <div class="status-item">
                <i class="fas fa-circle"></i>
                <span id="nodeCount"><?php echo count($nodes); ?> nodes</span>
            </div>
            <div class="status-item">
                <i class="fas fa-link"></i>
                <span id="linkCount"><?php echo count($links); ?> links</span>
            </div>
            <div class="status-item">
                <i class="fas fa-search"></i>
                <span id="zoomLevel">100%</span>
            </div>
        </div>
        <div class="status-actions">
            <span id="statusMessage">Ready</span>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" onclick="contextAction('properties')">
            <i class="fas fa-cog"></i> Properties
        </div>
        <div class="context-menu-item" onclick="contextAction('duplicate')">
            <i class="fas fa-copy"></i> Duplicate
        </div>
        <div class="context-menu-item" onclick="contextAction('delete')">
            <i class="fas fa-trash"></i> Delete
        </div>
        <div class="context-menu-item" onclick="contextAction('bring-front')">
            <i class="fas fa-layer-group"></i> Bring to Front
        </div>
        <div class="context-menu-item" onclick="contextAction('send-back')">
            <i class="fas fa-layer-group"></i> Send to Back
        </div>
    </div>

    <script>
    // Editor State
    const mapId = <?php echo $mapId; ?>;
    const canvas = document.getElementById('mapCanvas');
    const ctx = canvas.getContext('2d');
    const miniCanvas = document.getElementById('minimapCanvas');
    const miniCtx = miniCanvas.getContext('2d');
    
    let nodes = <?php echo json_encode($nodes); ?>;
    let links = <?php echo json_encode($links); ?>;
    let selectedElement = null;
    let currentTool = 'select';
    let isDragging = false;
    let dragStart = null;
    let linkStart = null;
    let zoom = 1;
    let pan = { x: 0, y: 0 };
    let gridEnabled = false;
    let snapEnabled = true;
    let history = [];
    let historyIndex = -1;
    
    // Node types with icons
    const nodeTypes = {
        router: { icon: '🔧', color: '#4a90e2', size: 30 },
        switch: { icon: '🔌', color: '#50c878', size: 28 },
        firewall: { icon: '🛡️', color: '#ff6b6b', size: 30 },
        server: { icon: '💾', color: '#9b59b6', size: 28 },
        default: { icon: '📍', color: '#95a5a6', size: 25 }
    };

    // Initialize
    function init() {
        setupEventListeners();
        drawMap();
        updateMinimap();
        updateStatus();
        saveHistory();
    }

    // Event Listeners
    function setupEventListeners() {
        canvas.addEventListener('mousedown', handleMouseDown);
        canvas.addEventListener('mousemove', handleMouseMove);
        canvas.addEventListener('mouseup', handleMouseUp);
        canvas.addEventListener('dblclick', handleDoubleClick);
        canvas.addEventListener('contextmenu', handleRightClick);
        canvas.addEventListener('wheel', handleWheel);
        
        document.addEventListener('keydown', handleKeyDown);
    }

    // Drawing Functions
    function drawMap() {
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Apply zoom and pan
        ctx.save();
        ctx.scale(zoom, zoom);
        ctx.translate(pan.x, pan.y);
        
        // Draw grid if enabled
        if (gridEnabled) {
            drawGrid();
        }
        
        // Draw links
        links.forEach(link => drawLink(link));
        
        // Draw nodes
        nodes.forEach(node => drawNode(node));
        
        // Draw selection
        if (selectedElement) {
            drawSelection();
        }
        
        ctx.restore();
    }

    function drawNode(node) {
        const type = nodeTypes[node.type] || nodeTypes.default;
        
        // Draw shadow
        ctx.shadowColor = 'rgba(0,0,0,0.2)';
        ctx.shadowBlur = 5;
        ctx.shadowOffsetX = 2;
        ctx.shadowOffsetY = 2;
        
        // Draw node circle
        ctx.fillStyle = type.color;
        ctx.beginPath();
        ctx.arc(node.x, node.y, type.size, 0, 2 * Math.PI);
        ctx.fill();
        
        // Reset shadow
        ctx.shadowColor = 'transparent';
        
        // Draw border
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.stroke();
        
        // Draw icon
        ctx.font = '20px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(type.icon, node.x, node.y);
        
        // Draw label
        if (node.label) {
            ctx.fillStyle = '#2d3748';
            ctx.font = 'bold 12px Arial';
            ctx.fillText(node.label, node.x, node.y + type.size + 15);
        }
        
        // Draw status indicator
        if (node.device_id) {
            ctx.fillStyle = '#10b981'; // Green for connected
            ctx.beginPath();
            ctx.arc(node.x + type.size * 0.7, node.y - type.size * 0.7, 5, 0, 2 * Math.PI);
            ctx.fill();
        }
    }

    function drawLink(link) {
        const srcNode = nodes.find(n => n.id == link.src_node_id);
        const dstNode = nodes.find(n => n.id == link.dst_node_id);
        
        if (!srcNode || !dstNode) return;
        
        // Calculate link path
        const dx = dstNode.x - srcNode.x;
        const dy = dstNode.y - srcNode.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        // Draw link line
        ctx.strokeStyle = link.color || '#718096';
        ctx.lineWidth = link.width || 3;
        ctx.setLineDash(link.style === 'dashed' ? [5, 5] : []);
        
        ctx.beginPath();
        ctx.moveTo(srcNode.x, srcNode.y);
        
        if (link.curved) {
            // Draw curved link
            const cx = (srcNode.x + dstNode.x) / 2 + dy * 0.2;
            const cy = (srcNode.y + dstNode.y) / 2 - dx * 0.2;
            ctx.quadraticCurveTo(cx, cy, dstNode.x, dstNode.y);
        } else {
            // Draw straight link
            ctx.lineTo(dstNode.x, dstNode.y);
        }
        
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Draw arrow
        if (link.directional) {
            const angle = Math.atan2(dy, dx);
            const arrowSize = 10;
            
            ctx.save();
            ctx.translate(dstNode.x - dx/distance * 30, dstNode.y - dy/distance * 30);
            ctx.rotate(angle);
            
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(-arrowSize, -arrowSize/2);
            ctx.lineTo(-arrowSize, arrowSize/2);
            ctx.closePath();
            ctx.fillStyle = ctx.strokeStyle;
            ctx.fill();
            
            ctx.restore();
        }
        
        // Draw bandwidth label
        if (link.bandwidth) {
            const midX = (srcNode.x + dstNode.x) / 2;
            const midY = (srcNode.y + dstNode.y) / 2;
            
            ctx.fillStyle = 'white';
            ctx.fillRect(midX - 30, midY - 10, 60, 20);
            
            ctx.fillStyle = '#2d3748';
            ctx.font = '11px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(link.bandwidth, midX, midY);
        }
    }

    function drawGrid() {
        const gridSize = 20;
        ctx.strokeStyle = 'rgba(0,0,0,0.05)';
        ctx.lineWidth = 1;
        
        for (let x = 0; x < canvas.width; x += gridSize) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, canvas.height);
            ctx.stroke();
        }
        
        for (let y = 0; y < canvas.height; y += gridSize) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(canvas.width, y);
            ctx.stroke();
        }
    }

    function drawSelection() {
        if (selectedElement.type === 'node') {
            const node = nodes.find(n => n.id === selectedElement.id);
            if (node) {
                const type = nodeTypes[node.type] || nodeTypes.default;
                ctx.strokeStyle = '#667eea';
                ctx.lineWidth = 2;
                ctx.setLineDash([5, 5]);
                ctx.beginPath();
                ctx.arc(node.x, node.y, type.size + 5, 0, 2 * Math.PI);
                ctx.stroke();
                ctx.setLineDash([]);
            }
        }
    }

    // Tool Functions
    function setTool(tool) {
        currentTool = tool;
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tool="${tool}"]`)?.classList.add('active');
        
        // Update cursor
        switch(tool) {
            case 'select': canvas.style.cursor = 'default'; break;
            case 'node': canvas.style.cursor = 'copy'; break;
            case 'link': canvas.style.cursor = 'crosshair'; break;
            case 'move': canvas.style.cursor = 'move'; break;
            case 'delete': canvas.style.cursor = 'not-allowed'; break;
            default: canvas.style.cursor = 'default';
        }
        
        updateStatus(`Tool: ${tool}`);
    }

    // Event Handlers
    function handleMouseDown(e) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / zoom - pan.x;
        const y = (e.clientY - rect.top) / zoom - pan.y;
        
        switch(currentTool) {
            case 'select':
                selectElement(x, y);
                if (selectedElement) {
                    isDragging = true;
                    dragStart = { x, y };
                }
                break;
                
            case 'node':
                addNode(x, y);
                break;
                
            case 'link':
                handleLinkCreation(x, y);
                break;
                
            case 'delete':
                deleteElement(x, y);
                break;
                
            case 'move':
                isDragging = true;
                dragStart = { x: e.clientX, y: e.clientY };
                break;
        }
    }

    function handleMouseMove(e) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / zoom - pan.x;
        const y = (e.clientY - rect.top) / zoom - pan.y;
        
        // Update cursor position
        document.getElementById('cursorPos').textContent = `${Math.round(x)}, ${Math.round(y)}`;
        
        if (isDragging) {
            if (currentTool === 'select' && selectedElement) {
                // Move selected element
                if (selectedElement.type === 'node') {
                    const node = nodes.find(n => n.id === selectedElement.id);
                    if (node) {
                        node.x = snapEnabled ? Math.round(x / 20) * 20 : x;
                        node.y = snapEnabled ? Math.round(y / 20) * 20 : y;
                        drawMap();
                    }
                }
            } else if (currentTool === 'move') {
                // Pan canvas
                pan.x += (e.clientX - dragStart.x) / zoom;
                pan.y += (e.clientY - dragStart.y) / zoom;
                dragStart = { x: e.clientX, y: e.clientY };
                drawMap();
            }
        }
    }

    function handleMouseUp(e) {
        if (isDragging && currentTool === 'select') {
            saveHistory();
        }
        isDragging = false;
        dragStart = null;
    }

    function handleDoubleClick(e) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / zoom - pan.x;
        const y = (e.clientY - rect.top) / zoom - pan.y;
        
        // Find element at position
        const element = getElementAt(x, y);
        if (element) {
            showProperties(element);
        }
    }

    function handleRightClick(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / zoom - pan.x;
        const y = (e.clientY - rect.top) / zoom - pan.y;
        
        const element = getElementAt(x, y);
        if (element) {
            selectedElement = element;
            showContextMenu(e.clientX, e.clientY);
        }
    }

    function handleWheel(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        zoom = Math.max(0.5, Math.min(3, zoom * delta));
        document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
        drawMap();
    }

    function handleKeyDown(e) {
        switch(e.key) {
            case 'Delete':
                if (selectedElement) {
                    deleteSelectedElement();
                }
                break;
            case 'Escape':
                selectedElement = null;
                linkStart = null;
                drawMap();
                break;
            case 'g':
                toggleGrid();
                break;
            case 's':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    saveMap();
                }
                break;
            case 'z':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if (e.shiftKey) {
                        redo();
                    } else {
                        undo();
                    }
                }
                break;
        }
    }

    // Node Management
    function addNode(x, y) {
        const node = {
            id: 'new_' + Date.now(),
            map_id: mapId,
            label: prompt('Node label:') || 'Node ' + (nodes.length + 1),
            x: snapEnabled ? Math.round(x / 20) * 20 : x,
            y: snapEnabled ? Math.round(y / 20) * 20 : y,
            type: 'default',
            device_id: null
        };
        
        nodes.push(node);
        saveHistory();
        drawMap();
        updateStatus(`Added node: ${node.label}`);
        updateNodeCount();
    }

    function addTemplateNode(type) {
        const centerX = canvas.width / 2 / zoom - pan.x;
        const centerY = canvas.height / 2 / zoom - pan.y;
        
        const node = {
            id: 'new_' + Date.now(),
            map_id: mapId,
            label: type.charAt(0).toUpperCase() + type.slice(1) + ' ' + (nodes.length + 1),
            x: centerX + Math.random() * 100 - 50,
            y: centerY + Math.random() * 100 - 50,
            type: type,
            device_id: null
        };
        
        nodes.push(node);
        saveHistory();
        drawMap();
        updateStatus(`Added ${type} node`);
        updateNodeCount();
    }

    // Link Management
    function handleLinkCreation(x, y) {
        const node = getNodeAt(x, y);
        
        if (!linkStart) {
            if (node) {
                linkStart = node;
                updateStatus('Select destination node for link');
            }
        } else {
            if (node && node.id !== linkStart.id) {
                // Create link
                const link = {
                    id: 'new_' + Date.now(),
                    map_id: mapId,
                    src_node_id: linkStart.id,
                    dst_node_id: node.id,
                    bandwidth: '',
                    color: '#718096',
                    width: 3,
                    style: 'solid',
                    curved: false,
                    directional: false
                };
                
                links.push(link);
                saveHistory();
                drawMap();
                updateStatus(`Created link from ${linkStart.label} to ${node.label}`);
                updateLinkCount();
            }
            linkStart = null;
        }
    }

    // Selection
    function selectElement(x, y) {
        const node = getNodeAt(x, y);
        const link = getLinkAt(x, y);
        
        selectedElement = node ? { type: 'node', id: node.id } : 
                         link ? { type: 'link', id: link.id } : null;
        
        drawMap();
        
        if (selectedElement) {
            showProperties(selectedElement);
        }
    }

    function getElementAt(x, y) {
        const node = getNodeAt(x, y);
        if (node) return { type: 'node', id: node.id };
        
        const link = getLinkAt(x, y);
        if (link) return { type: 'link', id: link.id };
        
        return null;
    }

    function getNodeAt(x, y) {
        for (let node of nodes) {
            const type = nodeTypes[node.type] || nodeTypes.default;
            const dist = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
            if (dist <= type.size) {
                return node;
            }
        }
        return null;
    }

    function getLinkAt(x, y) {
        // Simplified link detection
        for (let link of links) {
            const srcNode = nodes.find(n => n.id == link.src_node_id);
            const dstNode = nodes.find(n => n.id == link.dst_node_id);
            
            if (srcNode && dstNode) {
                // Check if point is near line
                const dist = pointToLineDistance(x, y, srcNode.x, srcNode.y, dstNode.x, dstNode.y);
                if (dist < 5) {
                    return link;
                }
            }
        }
        return null;
    }

    function pointToLineDistance(px, py, x1, y1, x2, y2) {
        const A = px - x1;
        const B = py - y1;
        const C = x2 - x1;
        const D = y2 - y1;
        
        const dot = A * C + B * D;
        const len_sq = C * C + D * D;
        let param = -1;
        
        if (len_sq != 0) {
            param = dot / len_sq;
        }
        
        let xx, yy;
        
        if (param < 0) {
            xx = x1;
            yy = y1;
        } else if (param > 1) {
            xx = x2;
            yy = y2;
        } else {
            xx = x1 + param * C;
            yy = y1 + param * D;
        }
        
        const dx = px - xx;
        const dy = py - yy;
        
        return Math.sqrt(dx * dx + dy * dy);
    }

    // Deletion
    function deleteElement(x, y) {
        const node = getNodeAt(x, y);
        if (node) {
            deleteNode(node);
            return;
        }
        
        const link = getLinkAt(x, y);
        if (link) {
            deleteLink(link);
        }
    }

    function deleteSelectedElement() {
        if (!selectedElement) return;
        
        if (selectedElement.type === 'node') {
            const node = nodes.find(n => n.id === selectedElement.id);
            if (node) deleteNode(node);
        } else if (selectedElement.type === 'link') {
            const link = links.find(l => l.id === selectedElement.id);
            if (link) deleteLink(link);
        }
        
        selectedElement = null;
    }

    function deleteNode(node) {
        // Remove node
        nodes = nodes.filter(n => n.id !== node.id);
        
        // Remove connected links
        links = links.filter(l => l.src_node_id !== node.id && l.dst_node_id !== node.id);
        
        saveHistory();
        drawMap();
        updateStatus(`Deleted node: ${node.label}`);
        updateNodeCount();
        updateLinkCount();
    }

    function deleteLink(link) {
        links = links.filter(l => l.id !== link.id);
        saveHistory();
        drawMap();
        updateStatus('Deleted link');
        updateLinkCount();
    }

    // Properties
    function showProperties(element) {
        const panel = document.getElementById('propertiesPanel');
        
        if (element.type === 'node') {
            const node = nodes.find(n => n.id === element.id);
            if (!node) return;
            
            panel.innerHTML = `
                <div class="property-group">
                    <label>Label</label>
                    <input type="text" id="propLabel" value="${node.label || ''}" onchange="updateNodeProperty('label', this.value)">
                </div>
                <div class="property-group">
                    <label>Type</label>
                    <select id="propType" onchange="updateNodeProperty('type', this.value)">
                        <option value="default" ${node.type === 'default' ? 'selected' : ''}>Default</option>
                        <option value="router" ${node.type === 'router' ? 'selected' : ''}>Router</option>
                        <option value="switch" ${node.type === 'switch' ? 'selected' : ''}>Switch</option>
                        <option value="firewall" ${node.type === 'firewall' ? 'selected' : ''}>Firewall</option>
                        <option value="server" ${node.type === 'server' ? 'selected' : ''}>Server</option>
                    </select>
                </div>
                <div class="property-group">
                    <label>Position</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" id="propX" value="${Math.round(node.x)}" onchange="updateNodeProperty('x', this.value)" style="width: 50%;">
                        <input type="number" id="propY" value="${Math.round(node.y)}" onchange="updateNodeProperty('y', this.value)" style="width: 50%;">
                    </div>
                </div>
            `;
        } else if (element.type === 'link') {
            const link = links.find(l => l.id === element.id);
            if (!link) return;
            
            panel.innerHTML = `
                <div class="property-group">
                    <label>Bandwidth</label>
                    <input type="text" id="propBandwidth" value="${link.bandwidth || ''}" onchange="updateLinkProperty('bandwidth', this.value)">
                </div>
                <div class="property-group">
                    <label>Color</label>
                    <input type="color" id="propColor" value="${link.color || '#718096'}" onchange="updateLinkProperty('color', this.value)">
                </div>
                <div class="property-group">
                    <label>Width</label>
                    <input type="range" id="propWidth" min="1" max="10" value="${link.width || 3}" onchange="updateLinkProperty('width', this.value)">
                </div>
                <div class="property-group">
                    <label>Style</label>
                    <select id="propStyle" onchange="updateLinkProperty('style', this.value)">
                        <option value="solid" ${link.style === 'solid' ? 'selected' : ''}>Solid</option>
                        <option value="dashed" ${link.style === 'dashed' ? 'selected' : ''}>Dashed</option>
                    </select>
                </div>
                <div class="property-group">
                    <label>
                        <input type="checkbox" ${link.curved ? 'checked' : ''} onchange="updateLinkProperty('curved', this.checked)">
                        Curved
                    </label>
                </div>
                <div class="property-group">
                    <label>
                        <input type="checkbox" ${link.directional ? 'checked' : ''} onchange="updateLinkProperty('directional', this.checked)">
                        Directional
                    </label>
                </div>
            `;
        }
    }

    function updateNodeProperty(prop, value) {
        if (!selectedElement || selectedElement.type !== 'node') return;
        
        const node = nodes.find(n => n.id === selectedElement.id);
        if (node) {
            if (prop === 'x' || prop === 'y') {
                node[prop] = parseFloat(value);
            } else {
                node[prop] = value;
            }
            saveHistory();
            drawMap();
        }
    }

    function updateLinkProperty(prop, value) {
        if (!selectedElement || selectedElement.type !== 'link') return;
        
        const link = links.find(l => l.id === selectedElement.id);
        if (link) {
            if (prop === 'width') {
                link[prop] = parseInt(value);
            } else if (prop === 'curved' || prop === 'directional') {
                link[prop] = value;
            } else {
                link[prop] = value;
            }
            saveHistory();
            drawMap();
        }
    }

    // History Management
    function saveHistory() {
        history = history.slice(0, historyIndex + 1);
        history.push({
            nodes: JSON.parse(JSON.stringify(nodes)),
            links: JSON.parse(JSON.stringify(links))
        });
        historyIndex++;
        
        // Limit history size
        if (history.length > 50) {
            history.shift();
            historyIndex--;
        }
    }

    function undo() {
        if (historyIndex > 0) {
            historyIndex--;
            const state = history[historyIndex];
            nodes = JSON.parse(JSON.stringify(state.nodes));
            links = JSON.parse(JSON.stringify(state.links));
            drawMap();
            updateStatus('Undo');
        }
    }

    function redo() {
        if (historyIndex < history.length - 1) {
            historyIndex++;
            const state = history[historyIndex];
            nodes = JSON.parse(JSON.stringify(state.nodes));
            links = JSON.parse(JSON.stringify(state.links));
            drawMap();
            updateStatus('Redo');
        }
    }

    // Utility Functions
    function toggleGrid() {
        gridEnabled = !gridEnabled;
        document.getElementById('gridOverlay').classList.toggle('active', gridEnabled);
        drawMap();
    }

    function toggleSnap() {
        snapEnabled = !snapEnabled;
        updateStatus(`Snap: ${snapEnabled ? 'ON' : 'OFF'}`);
    }

    function clearAll() {
        if (confirm('Clear all nodes and links?')) {
            nodes = [];
            links = [];
            saveHistory();
            drawMap();
            updateStatus('Cleared all elements');
            updateNodeCount();
            updateLinkCount();
        }
    }

    function zoomIn() {
        zoom = Math.min(3, zoom * 1.2);
        document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
        drawMap();
    }

    function zoomOut() {
        zoom = Math.max(0.5, zoom / 1.2);
        document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
        drawMap();
    }

    function resetZoom() {
        zoom = 1;
        pan = { x: 0, y: 0 };
        document.getElementById('zoomLevel').textContent = '100%';
        drawMap();
    }

    function updateMinimap() {
        // TODO: Implement minimap
    }

    function updateStatus(message) {
        document.getElementById('statusMessage').textContent = message || 'Ready';
    }

    function updateNodeCount() {
        document.getElementById('nodeCount').textContent = nodes.length + ' nodes';
    }

    function updateLinkCount() {
        document.getElementById('linkCount').textContent = links.length + ' links';
    }

    function showContextMenu(x, y) {
        const menu = document.getElementById('contextMenu');
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.display = 'block';
        
        // Hide on click outside
        setTimeout(() => {
            document.addEventListener('click', hideContextMenu);
        }, 100);
    }

    function hideContextMenu() {
        document.getElementById('contextMenu').style.display = 'none';
        document.removeEventListener('click', hideContextMenu);
    }

    function contextAction(action) {
        switch(action) {
            case 'properties':
                if (selectedElement) {
                    showProperties(selectedElement);
                }
                break;
            case 'duplicate':
                // TODO: Implement duplication
                break;
            case 'delete':
                deleteSelectedElement();
                break;
            case 'bring-front':
                // TODO: Implement z-order
                break;
            case 'send-back':
                // TODO: Implement z-order
                break;
        }
        hideContextMenu();
    }

    function assignDevice() {
        if (!selectedElement || selectedElement.type !== 'node') {
            alert('Please select a node first');
            return;
        }
        
        const deviceId = document.getElementById('deviceSelect').value;
        const node = nodes.find(n => n.id === selectedElement.id);
        
        if (node) {
            node.device_id = deviceId || null;
            
            // Update node type based on device type
            if (deviceId) {
                const option = document.querySelector(`#deviceSelect option[value="${deviceId}"]`);
                const deviceType = option?.dataset.type;
                
                if (deviceType) {
                    if (deviceType.includes('router')) node.type = 'router';
                    else if (deviceType.includes('switch')) node.type = 'switch';
                    else if (deviceType.includes('firewall')) node.type = 'firewall';
                    else if (deviceType.includes('server')) node.type = 'server';
                }
            }
            
            saveHistory();
            drawMap();
            updateStatus(deviceId ? 'Device assigned' : 'Device unassigned');
        }
    }

    function saveMap() {
        updateStatus('Saving...');
        
        const formData = new FormData();
        formData.append('id', mapId);
        formData.append('nodes', JSON.stringify(nodes));
        formData.append('links', JSON.stringify(links));
        formData.append('ajax_action', 'save-map');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        fetch('/plugin/v1/WeathermapNG', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatus('Saved successfully!');
                setTimeout(() => updateStatus('Ready'), 3000);
            } else {
                alert('Error saving: ' + (data.message || 'Unknown error'));
                updateStatus('Save failed');
            }
        })
        .catch(error => {
            alert('Error saving map: ' + error);
            updateStatus('Save failed');
        });
    }

    function exitEditor() {
        if (confirm('Exit editor? Make sure you have saved your changes.')) {
            window.location.href = '/plugin/v1/WeathermapNG';
        }
    }

    // Initialize editor
    init();
    </script>
</body>
</html>