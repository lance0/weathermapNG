<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeathermapNG - {{ $mapId }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
        }

        #map-container {
            width: 100vw;
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #map-canvas {
            border: none;
            display: block;
        }

        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #6c757d;
            background: #f8f9fa;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            opacity: 0.7;
        }

        .loading span {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            height: 100%;
            color: #dc3545;
        }

        .error i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .status-bar {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-light {
            color: #212529;
            background-color: #fff;
            border-color: #ccc;
        }

        .wmng-control-label {
            background: #fff;
            border: 1px solid #ccc;
            padding: 0.25rem 0.5rem;
            border-radius: 0.2rem;
            font-size: 0.875rem;
        }

        .wmng-control-label select {
            border: 0;
            font-size: 0.875rem;
            background: transparent;
        }

        /* Nav bar */
        .embed-nav-bar {
            position: absolute; top: 0; left: 0; right: 0;
            background: rgba(52,58,64,0.95); color: #fff;
            padding: 8px 16px; display: flex; justify-content: space-between;
            align-items: center; z-index: 1001; font-size: 13px;
        }
        .embed-nav-left { display: flex; align-items: center; gap: 16px; }
        .embed-nav-right { display: flex; align-items: center; gap: 12px; }
        .embed-nav-link { color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .embed-nav-edit { color: #ffc107; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .embed-nav-demo { background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }

        /* Controls bar */
        .embed-controls {
            position: absolute; top: 55px; left: 10px; z-index: 1000;
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        }
        .embed-viz-menu {
            position: absolute; top: 100%; left: 0; background: #fff;
            border: 1px solid #ccc; border-radius: 4px; padding: 10px;
            min-width: 250px; display: none; margin-top: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .embed-viz-section { font-size: 12px; font-weight: bold; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .embed-viz-row { margin-bottom: 8px; }
        .embed-viz-label { font-size: 11px; display: block; }

        /* Legend */
        .embed-legend {
            position: absolute; bottom: 10px; left: 10px;
            background: rgba(255,255,255,0.9); border: 1px solid #ddd;
            border-radius: 4px; font-size: 12px; padding: 6px 8px; z-index: 1000;
        }
        .embed-legend-title { font-weight: 600; margin-bottom: 4px; }

        /* Tooltip */
        .embed-tooltip {
            position: absolute; background: rgba(0,0,0,0.8); color: #fff;
            padding: 6px 8px; border-radius: 4px; font-size: 12px;
            display: none; pointer-events: none;
        }

        /* Minimap */
        .embed-minimap {
            position: absolute; top: 55px; right: 10px;
            background: rgba(255,255,255,0.85); border: 1px solid #ddd; border-radius: 4px;
        }

        /* Responsive: wrap nav and controls on small screens */
        @media (max-width: 640px) {
            .embed-nav-bar { flex-wrap: wrap; padding: 6px 10px; font-size: 12px; }
            .embed-nav-left { gap: 8px; flex-wrap: wrap; }
            .embed-nav-right { gap: 8px; }
            /* Push controls/minimap below the nav, which may wrap to 2 rows (~56px) */
            .embed-controls { top: 74px; gap: 4px; }
            .embed-minimap { top: 74px; }
            .embed-legend { font-size: 11px; padding: 4px 6px; }
        }
        @media (max-width: 480px) {
            .embed-minimap { display: none; }
            .embed-controls { top: 80px; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="map-container">
        <div id="nav-bar" class="embed-nav-bar">
            <div class="embed-nav-left">
                <a href="{{ url('plugin/WeathermapNG') }}" class="embed-nav-link">
                    <i class="fas fa-arrow-left"></i> All Maps
                </a>
                <span style="color:#adb5bd;">|</span>
                <span style="font-weight:500;">{{ $mapData['title'] ?? $mapData['name'] ?? 'Map' }}</span>
            </div>
            <div class="embed-nav-right">
                @if($demoMode ?? false)
                <span class="embed-nav-demo">DEMO MODE</span>
                @endif
                <a href="{{ url('plugin/WeathermapNG/editor/' . $mapId) }}" class="embed-nav-edit">
                    <i class="fas fa-edit"></i> Edit Map
                </a>
            </div>
        </div>
        <div id="loading" class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <div>Loading map...</div>
        </div>
        <canvas id="map-canvas"></canvas>
        <canvas id="minimap" width="160" height="120" class="embed-minimap"></canvas>
        <div id="status-bar" class="status-bar" style="display: none;">
            <i class="fas fa-clock"></i> Updated: <span id="last-updated">Never</span>
        </div>
        <div id="tooltip" class="embed-tooltip"></div>
        <div id="controls" class="embed-controls">
            <button type="button" id="toggle-transport" class="btn btn-light btn-sm" aria-label="Live update status">Live: loading…</button>
            <button type="button" id="toggle-flow" class="btn btn-primary btn-sm" aria-label="Toggle flow animation" title="Toggle flow animation"><i class="fas fa-water" aria-hidden="true"></i> Flow</button>
            <div style="position:relative;">
                <button type="button" id="viz-settings" class="btn btn-light btn-sm" aria-label="Visualization settings" title="Visualization settings"><i class="fas fa-cog" aria-hidden="true"></i></button>
                <div id="viz-menu" class="embed-viz-menu">
                    <div class="embed-viz-section">Flow Animation</div>
                    <div class="embed-viz-row">
                        <label class="embed-viz-label">Particle Density: <span id="density-value">1.0</span></label>
                        <input type="range" id="particle-density" min="0.5" max="2" step="0.1" value="1" style="width:100%;">
                    </div>
                    <div class="embed-viz-row">
                        <label class="embed-viz-label">Particle Speed: <span id="speed-value">1.0</span></label>
                        <input type="range" id="particle-speed" min="0.5" max="2" step="0.1" value="1" style="width:100%;">
                    </div>
                </div>
            </div>
            <label class="wmng-control-label">
                Metric
                <select id="metric-select">
                    <option value="percent">Percent</option>
                    <option value="in">Inbound</option>
                    <option value="out">Outbound</option>
                    <option value="sum">In+Out</option>
                </select>
            </label>
            <button type="button" id="export-png" class="btn btn-light btn-sm" aria-label="Export map as PNG" title="Export PNG">Export PNG</button>
        </div>
        <div id="legend" class="embed-legend">
            <div class="embed-legend-title">Legend</div>
            <div id="legend-rows"></div>
        </div>
    </div>

    <script>
        function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
        const mapId = '{{ $mapId }}';
        const baseUrl = '{{ url("/") }}';
        const deviceBaseUrl = '{{ url("device") }}';
        const WMNG_CONFIG = {
            thresholds: {!! json_encode(config('weathermapng.thresholds') ?? [50, 80, 95]) !!},
            colors: {
                link_normal: '{{ config('weathermapng.colors.link_normal', '#28a745') }}',
                link_warning: '{{ config('weathermapng.colors.link_warning', '#ffc107') }}',
                link_critical: '{{ config('weathermapng.colors.link_critical', '#dc3545') }}',
                node_up: '{{ config('weathermapng.colors.node_up', '#28a745') }}',
                node_down: '{{ config('weathermapng.colors.node_down', '#dc3545') }}',
                node_unknown: '{{ config('weathermapng.colors.node_unknown', '#6c757d') }}'
            },
            enable_sse: {!! json_encode(config('weathermapng.enable_sse') ?? true) !!},
            client_refresh: {!! json_encode(config('weathermapng.client_refresh') ?? 60) !!},
            scale: {!! json_encode(config('weathermapng.scale') ?? 'bits') !!},
            link_style: '{{ config('weathermapng.link_style', 'straight') }}',
            show_bandwidth: {!! json_encode(config('weathermapng.show_bandwidth', true)) !!},
            show_percentages: {!! json_encode(config('weathermapng.show_percentages', true)) !!}
        };
        const urlParams = new URLSearchParams(window.location.search);
        const param = (k, d) => urlParams.has(k) ? urlParams.get(k) : d;
        let scale = (param('scale', WMNG_CONFIG.scale) || '').toLowerCase();
        if (scale !== 'bytes') scale = 'bits';
        let intervalSec = parseInt(param('interval', WMNG_CONFIG.client_refresh), 10) || WMNG_CONFIG.client_refresh;
        let sseEnabled = param('sse', WMNG_CONFIG.enable_sse ? '1' : '0') !== '0' && !!window.EventSource;
        let sseMax = parseInt(param('max', 300), 10) || 300;  // 5 minutes default
        let currentTransport = 'init';
        let eventSourceRef = null;
        let sseReconnectAttempts = 0;
        const maxReconnectAttempts = 5;
        const reconnectDelay = 2000; // 2 seconds
        let lastDataUpdate = null;
        let mapData = {};
        try {
            mapData = {!! json_encode($mapData ?? []) !!};
            // Apply initial live data if provided
            const initialLive = {!! json_encode($liveData ?? []) !!};
            if (initialLive && initialLive.links && Array.isArray(mapData.links)) {
                mapData.links.forEach(l => {
                    const id = l.id ?? l.link_id ?? null;
                    if (id && initialLive.links[id]) {
                        l.live = initialLive.links[id];
                    }
                });
                lastDataUpdate = Date.now();
            }
        } catch (e) {
            console.error('Failed to parse map data:', e);
            mapData = { error: 'Invalid map data' };
        }
        let canvas, ctx, minimap;
        let viewScale = 1, viewOffsetX = 0, viewOffsetY = 0;
        let animationId;
        let lastUpdate = Date.now();
        let animTick = 0;
        let bgImg = null;
        let currentMetric = (param('metric', 'percent') || 'percent').toLowerCase();
        // Navigation (pan/zoom)
        const navEnabled = (param('nav', '1') !== '0');
        const MIN_ZOOM = parseFloat(param('minz', '0.5')) || 0.5;
        const MAX_ZOOM = parseFloat(param('maxz', '4')) || 4;
        let baseScale = 1, baseOffsetX = 0, baseOffsetY = 0;
        let userScale = 1, userOffsetX = 0, userOffsetY = 0;
        let isPanning = false, panLastX = 0, panLastY = 0;
        
        // Flow animation particles
        let particles = [];
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let flowAnimationEnabled = !reducedMotion;
        let particleDensity = 1.0; // 0.5 to 2.0
        let particleSpeed = 1.0; // 0.5 to 2.0
        
        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            // Sync flow toggle button with prefers-reduced-motion default
            const _flowBtn = document.getElementById('toggle-flow');
            if (_flowBtn && !flowAnimationEnabled) {
                _flowBtn.classList.remove('btn-primary');
                _flowBtn.classList.add('btn-secondary');
            }
            if (mapData && !mapData.error) {
                renderMap();
                startLiveUpdates();
                renderLegend();
                const ms = document.getElementById('metric-select');
                if (ms) { ms.value = currentMetric; ms.addEventListener('change', () => { currentMetric = ms.value; renderLegend(); renderMap(); }); }
                const ex = document.getElementById('export-png');
                if (ex) ex.addEventListener('click', exportPNG);
                if (navEnabled) initNavControls();
            } else {
                showError(mapData.error || 'Failed to load map');
            }
        });

        function initCanvas() {
            canvas = document.getElementById('map-canvas');
            ctx = canvas.getContext('2d');

            // Set canvas size
            const container = document.getElementById('map-container');
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            minimap = document.getElementById('minimap');

            // Hide loading
            document.getElementById('loading').style.display = 'none';
            const bgUrl = mapData.options?.background_image;
            if (bgUrl) {
                bgImg = new Image();
                bgImg.onload = () => { renderMap(); };
                bgImg.src = bgUrl;
            }
        }

        function renderMap() {
            if (!mapData || !mapData.nodes) return;

            // Clear geometry arrays for hover/click detection
            nodeGeoms.length = 0;
            linkGeoms.length = 0;

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Draw background
            if (bgImg) {
                ctx.drawImage(bgImg, 0, 0, canvas.width, canvas.height);
            } else {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }

            // Calculate scale to fit map in canvas
            const mapWidth = mapData?.width || (mapData?.metadata && mapData.metadata.width) || 800;
            const mapHeight = mapData?.height || (mapData?.metadata && mapData.metadata.height) || 600;
            const scaleX = canvas.width / mapWidth;
            const scaleY = canvas.height / mapHeight;
            baseScale = Math.min(scaleX, scaleY, 1); // Don't scale up

            // Center the map (base)
            baseOffsetX = (canvas.width - mapWidth * baseScale) / 2;
            baseOffsetY = (canvas.height - mapHeight * baseScale) / 2;

            // Effective transform for rendering and hit testing
            viewScale = baseScale * userScale;
            viewOffsetX = baseOffsetX + userOffsetX;
            viewOffsetY = baseOffsetY + userOffsetY;

            ctx.save();
            ctx.translate(viewOffsetX, viewOffsetY);
            ctx.scale(viewScale, viewScale);

            // Draw links first (behind nodes)
            if (Array.isArray(mapData.links)) {
                mapData.links.forEach(link => {
                    drawLink(link);
                });
            }

            // Draw nodes
            if (Array.isArray(mapData.nodes)) {
                mapData.nodes.forEach(node => {
                    drawNode(node);
                });
            }

            ctx.restore();

            // Update status and overlays
            updateStatus();
            drawMinimap();
        }

        function initNavControls() {
            const controls = document.getElementById('controls');
            if (controls) {
                const group = document.createElement('div');
                group.style.display = 'inline-flex';
                group.style.gap = '4px';
                group.style.marginLeft = '6px';
                function createZoomButton(id, label, text) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.id = id;
                    button.className = 'btn btn-light btn-sm';
                    button.title = label;
                    button.setAttribute('aria-label', label);
                    button.textContent = text;
                    return button;
                }
                group.appendChild(createZoomButton('zoom-in', 'Zoom In (+)', '+'));
                group.appendChild(createZoomButton('zoom-out', 'Zoom Out (-)', '-'));
                group.appendChild(createZoomButton('zoom-reset', 'Reset zoom', 'Reset'));
                controls.appendChild(group);
                const c = canvas;
                document.getElementById('zoom-in').addEventListener('click', () => zoomAt(c.width/2, c.height/2, 1.2));
                document.getElementById('zoom-out').addEventListener('click', () => zoomAt(c.width/2, c.height/2, 1/1.2));
                document.getElementById('zoom-reset').addEventListener('click', resetView);
            }
            // Wheel zoom
            canvas.addEventListener('wheel', (e) => {
                e.preventDefault();
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const factor = e.deltaY > 0 ? 0.9 : 1.1;
                zoomAt(x, y, factor);
            }, { passive: false });
            // Drag to pan
            canvas.addEventListener('mousedown', (e) => {
                isPanning = true; panLastX = e.clientX; panLastY = e.clientY; canvas.style.cursor = 'grabbing';
            });
            window.addEventListener('mousemove', (e) => {
                if (!isPanning) return;
                const dx = e.clientX - panLastX; const dy = e.clientY - panLastY;
                panLastX = e.clientX; panLastY = e.clientY;
                userOffsetX += dx; userOffsetY += dy;
                renderMap();
            });
            window.addEventListener('mouseup', () => { if (isPanning) { isPanning = false; canvas.style.cursor = 'default'; } });
            // Double-click zoom (Shift to zoom out)
            canvas.addEventListener('dblclick', (e) => {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const factor = e.shiftKey ? 0.9 : 1.1;
                zoomAt(x, y, factor);
            });
        }

        function zoomAt(cx, cy, factor) {
            const newUserScale = clamp(userScale * factor, MIN_ZOOM, MAX_ZOOM);
            factor = newUserScale / userScale;
            // world coords before zoom
            const wx = (cx - (baseOffsetX + userOffsetX)) / (baseScale * userScale);
            const wy = (cy - (baseOffsetY + userOffsetY)) / (baseScale * userScale);
            userScale = newUserScale;
            // adjust offsets to keep cursor stable
            const vx = wx * (baseScale * userScale) + baseOffsetX;
            const vy = wy * (baseScale * userScale) + baseOffsetY;
            userOffsetX = cx - vx;
            userOffsetY = cy - vy;
            renderMap();
        }

        function resetView() { userScale = 1; userOffsetX = 0; userOffsetY = 0; renderMap(); }
        function clamp(v,a,b){ return Math.max(a, Math.min(b, v)); }

        const nodeGeoms = [];
        const linkGeoms = [];
        function getNodeType(node) {
            const label = (node.label || '').toLowerCase();
            if (label.includes('router') || label.includes('core')) return 'router';
            if (label.includes('switch')) return 'switch';
            if (label.includes('server') || label.includes('db') || label.includes('app') || label.includes('web') || label.includes('file')) return 'server';
            if (label.includes('firewall') || label.includes('fw')) return 'firewall';
            return 'default';
        }

        function drawNode(node) {
            const x = (node.position?.x ?? node.x) || 0;
            const y = (node.position?.y ?? node.y) || 0;
            const nodeType = getNodeType(node);
            const color = getNodeColor(node);
            const radius = 10; // Base radius for hit testing and badges

            ctx.fillStyle = color;
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1.5;

            // Draw shape based on device type
            if (nodeType === 'router') {
                // Diamond
                ctx.beginPath();
                ctx.moveTo(x, y - 10);
                ctx.lineTo(x + 10, y);
                ctx.lineTo(x, y + 10);
                ctx.lineTo(x - 10, y);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
            } else if (nodeType === 'switch') {
                // Rounded rectangle (horizontal)
                const w = 18, h = 10, r = 3;
                ctx.beginPath();
                ctx.moveTo(x - w/2 + r, y - h/2);
                ctx.lineTo(x + w/2 - r, y - h/2);
                ctx.quadraticCurveTo(x + w/2, y - h/2, x + w/2, y - h/2 + r);
                ctx.lineTo(x + w/2, y + h/2 - r);
                ctx.quadraticCurveTo(x + w/2, y + h/2, x + w/2 - r, y + h/2);
                ctx.lineTo(x - w/2 + r, y + h/2);
                ctx.quadraticCurveTo(x - w/2, y + h/2, x - w/2, y + h/2 - r);
                ctx.lineTo(x - w/2, y - h/2 + r);
                ctx.quadraticCurveTo(x - w/2, y - h/2, x - w/2 + r, y - h/2);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
            } else if (nodeType === 'server') {
                // Tall rectangle (server rack style)
                ctx.beginPath();
                ctx.rect(x - 7, y - 12, 14, 24);
                ctx.fill();
                ctx.stroke();
                // Rack lines
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(x - 5, y - 6); ctx.lineTo(x + 5, y - 6);
                ctx.moveTo(x - 5, y); ctx.lineTo(x + 5, y);
                ctx.moveTo(x - 5, y + 6); ctx.lineTo(x + 5, y + 6);
                ctx.stroke();
                ctx.strokeStyle = '#000';
            } else if (nodeType === 'firewall') {
                // Shield shape
                ctx.beginPath();
                ctx.moveTo(x, y - 12);
                ctx.lineTo(x + 10, y - 6);
                ctx.lineTo(x + 10, y + 4);
                ctx.quadraticCurveTo(x + 10, y + 12, x, y + 14);
                ctx.quadraticCurveTo(x - 10, y + 12, x - 10, y + 4);
                ctx.lineTo(x - 10, y - 6);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
            } else {
                // Default: circle
                ctx.beginPath();
                ctx.arc(x, y, 10, 0, 2 * Math.PI);
                ctx.fill();
                ctx.stroke();
            }

            // Node label
            ctx.fillStyle = '#000';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            const labelY = (nodeType === 'server') ? y - 18 : y - 16;
            ctx.fillText(node.label || node.id, x, labelY);

            // Status indicator
            if (node.current_value !== null && node.current_value !== undefined) {
                ctx.font = '10px Arial';
                ctx.fillStyle = '#666';
                const value = formatValue(node.current_value);
                if (value) {
                    ctx.fillText(value, x, y + radius + 15);
                }
            }

            // Alert badge (if any)
            if (node.alerts && node.alerts.count > 0) {
                const bx = x + radius - 3;
                const by = y - radius + 3;
                ctx.beginPath();
                ctx.arc(bx, by, 5, 0, Math.PI * 2);
                const sev = (node.alerts.severity || 'warning');
                ctx.fillStyle = (sev === 'severe' || sev === 'critical') ? '#dc3545' : '#ffc107';
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#fff';
                ctx.stroke();
            }

            // store geometry for hover
            nodeGeoms.push({ x, y, r: radius, node });
        }

        function buildLinkPath(link, x1, y1, x2, y2) {
            const viaPoints = (link.style && link.style.via_points) || [];
            const viaStyle = (link.style && link.style.via_style) || WMNG_CONFIG.link_style || 'straight';
            const points = [{x: x1, y: y1}];
            for (const vp of viaPoints) { points.push({x: vp.x, y: vp.y}); }
            points.push({x: x2, y: y2});
            return { points, viaStyle };
        }

        function traceLinkPath(ctx, points, viaStyle) {
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            if (points.length === 2) {
                ctx.lineTo(points[1].x, points[1].y);
                return;
            }
            if (viaStyle === 'curved') {
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
        }

        function getPathMidpoint(points) {
            if (points.length === 2) return points[1];
            let totalLen = 0;
            const segs = [];
            for (let i = 1; i < points.length; i++) {
                const dx = points[i].x - points[i-1].x;
                const dy = points[i].y - points[i-1].y;
                const len = Math.sqrt(dx*dx + dy*dy);
                segs.push(len);
                totalLen += len;
            }
            let half = totalLen / 2, accum = 0;
            for (let i = 0; i < segs.length; i++) {
                if (accum + segs[i] >= half) {
                    const t = (half - accum) / Math.max(segs[i], 0.001);
                    return {
                        x: points[i].x + (points[i+1].x - points[i].x) * t,
                        y: points[i].y + (points[i+1].y - points[i].y) * t
                    };
                }
                accum += segs[i];
            }
            return points[Math.floor(points.length / 2)];
        }

        function getPointOnPath(points, progress) {
            let totalLen = 0;
            const segs = [];
            for (let i = 1; i < points.length; i++) {
                const dx = points[i].x - points[i-1].x;
                const dy = points[i].y - points[i-1].y;
                const len = Math.sqrt(dx*dx + dy*dy);
                segs.push(len);
                totalLen += len;
            }
            if (totalLen === 0) return points[0];
            const target = progress * totalLen;
            let accum = 0;
            for (let i = 0; i < segs.length; i++) {
                if (accum + segs[i] >= target) {
                    const t = (target - accum) / Math.max(segs[i], 0.001);
                    return {
                        x: points[i].x + (points[i+1].x - points[i].x) * t,
                        y: points[i].y + (points[i+1].y - points[i].y) * t
                    };
                }
                accum += segs[i];
            }
            return points[points.length - 1];
        }

        function pathLength(points) {
            let len = 0;
            for (let i = 1; i < points.length; i++) {
                const dx = points[i].x - points[i-1].x;
                const dy = points[i].y - points[i-1].y;
                len += Math.sqrt(dx*dx + dy*dy);
            }
            return len;
        }

        function drawLink(link) {
            const srcId = link.source ?? link.src ?? link.source_id;
            const dstId = link.target ?? link.dst ?? link.destination_id;
            const sourceNode = mapData.nodes.find(n => (n.id ?? n.src_node_id) === srcId);
            const targetNode = mapData.nodes.find(n => (n.id ?? n.dst_node_id) === dstId);

            if (!sourceNode || !targetNode) return;

            const x1 = (sourceNode.position?.x ?? sourceNode.x) || 0;
            const y1 = (sourceNode.position?.y ?? sourceNode.y) || 0;
            const x2 = (targetNode.position?.x ?? targetNode.x) || 0;
            const y2 = (targetNode.position?.y ?? targetNode.y) || 0;

            const { points, viaStyle } = buildLinkPath(link, x1, y1, x2, y2);

            // Draw link line
            traceLinkPath(ctx, points, viaStyle);
            const metric = getLinkMetric(link);
            const pct = getLinkPct(link, metric);
            ctx.strokeStyle = getLinkColor(pct);
            const width = Math.max(1, (link.width || 2));
            ctx.lineWidth = width;
            
            // Use solid line if flow animation is enabled
            if (!flowAnimationEnabled) {
                const dash = Math.max(6, width * 3);
                ctx.setLineDash([dash, dash]);
                // Animate dash offset only when motion is not reduced;
                // otherwise keep the dashed line static for prefers-reduced-motion.
                if (!reducedMotion) {
                    const speed = Math.max(0.5, Math.min(5, ((pct ?? 10)) / 20));
                    ctx.lineDashOffset = -(animTick * speed);
                }
            }
            ctx.stroke();
            ctx.setLineDash([]);
            
            // Draw flow particles if enabled
            if (flowAnimationEnabled) {
                drawFlowParticles(link, x1, y1, x2, y2, pct, points);
            }

            // Link utilization label
            if (metric !== null && metric !== undefined) {
                const showLabel = (currentMetric === 'percent')
                    ? WMNG_CONFIG.show_percentages !== false
                    : WMNG_CONFIG.show_bandwidth !== false;
                if (showLabel) {
                    const mid = getPathMidpoint(points);
                    ctx.fillStyle = '#111';
                    ctx.font = '11px Arial';
                    ctx.textAlign = 'center';
                    const label = (currentMetric === 'percent') ? (Math.round(pct) + '%') : humanBits(metric);
                    ctx.strokeStyle = '#fff';
                    ctx.lineWidth = 3;
                    ctx.strokeText(label, mid.x, mid.y - 5);
                    ctx.fillText(label, mid.x, mid.y - 5);
                }
            }
            // Link alert badge (diamond)
            if (link.alerts && link.alerts.count > 0) {
                const mid = getPathMidpoint(points);
                const size = 5;
                ctx.save();
                ctx.translate(mid.x + 10, mid.y - 10);
                ctx.rotate(Math.PI / 4);
                const sev = (link.alerts.severity || 'warning');
                ctx.fillStyle = (sev === 'severe' || sev === 'critical') ? '#dc3545' : '#ffc107';
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.rect(-size, -size, size * 2, size * 2);
                ctx.fill();
                ctx.stroke();
                ctx.restore();
            }
            // store geometry for hover
            storeLinkGeom(link, x1, y1, x2, y2, pct, points);
        }
        
        function drawFlowParticles(link, x1, y1, x2, y2, pct, pathPoints) {
            const live = link.live || {};
            const inBps = live.in_bps || 0;
            const outBps = live.out_bps || 0;
            
            if (inBps === 0 && outBps === 0) return;

            const points = pathPoints || [{x:x1,y:y1},{x:x2,y:y2}];
            const length = pathLength(points);
            if (length === 0) return;
            
            const relativeFlow = pct ? pct / 100 : 0.5;
            const particleCount = Math.max(1, Math.floor((length / 50) * relativeFlow * particleDensity));
            const speedFactor = 0.5 + (relativeFlow * 1.5) * particleSpeed;
            
            const linkId = `${link.id || (x1 + '-' + y1 + '-' + x2 + '-' + y2)}`;
            
            if (!particles[linkId]) {
                particles[linkId] = {
                    forward: [],
                    backward: [],
                    inRatio: inBps / (inBps + outBps + 0.001),
                    outRatio: outBps / (inBps + outBps + 0.001)
                };
                
                if (outBps > 0) {
                    for (let i = 0; i < particleCount * particles[linkId].outRatio; i++) {
                        particles[linkId].forward.push({
                            progress: (i / particleCount),
                            speed: speedFactor * (0.8 + Math.random() * 0.4),
                            size: 2 + Math.random() * 2,
                            opacity: 0.6 + Math.random() * 0.4
                        });
                    }
                }
                
                if (inBps > 0) {
                    for (let i = 0; i < particleCount * particles[linkId].inRatio; i++) {
                        particles[linkId].backward.push({
                            progress: (i / particleCount),
                            speed: speedFactor * (0.8 + Math.random() * 0.4),
                            size: 2 + Math.random() * 2,
                            opacity: 0.6 + Math.random() * 0.4
                        });
                    }
                }
            }
            
            const linkParticles = particles[linkId];
            
            ctx.save();
            if (linkParticles.forward && Array.isArray(linkParticles.forward)) {
                linkParticles.forward.forEach(particle => {
                particle.progress += (particle.speed * 0.005);
                if (particle.progress > 1) particle.progress -= 1;
                
                const pos = getPointOnPath(points, particle.progress);
                
                ctx.globalAlpha = particle.opacity * 0.3;
                ctx.fillStyle = '#00ff00';
                ctx.beginPath();
                ctx.arc(pos.x, pos.y, particle.size * 2, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.globalAlpha = particle.opacity;
                ctx.fillStyle = '#40ff40';
                ctx.beginPath();
                ctx.arc(pos.x, pos.y, particle.size, 0, Math.PI * 2);
                ctx.fill();
                });
            }
            
            if (linkParticles.backward && Array.isArray(linkParticles.backward)) {
                linkParticles.backward.forEach(particle => {
                particle.progress += (particle.speed * 0.005);
                if (particle.progress > 1) particle.progress -= 1;
                
                const pos = getPointOnPath(points, 1 - particle.progress);
                
                ctx.globalAlpha = particle.opacity * 0.3;
                ctx.fillStyle = '#0080ff';
                ctx.beginPath();
                ctx.arc(pos.x, pos.y, particle.size * 2, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.globalAlpha = particle.opacity;
                ctx.fillStyle = '#40a0ff';
                ctx.beginPath();
                ctx.arc(pos.x, pos.y, particle.size, 0, Math.PI * 2);
                ctx.fill();
                });
            }
            ctx.restore();
        }

        function getNodeColor(node) {
            const status = node.status || 'unknown';
            const colors = WMNG_CONFIG.colors || {};
            if (status === 'down') return colors.node_down || '#dc3545';
            // If up but CPU or MEM high, warn
            const cpu = node.metrics?.cpu;
            const mem = node.metrics?.mem;
            const warn = (v) => typeof v === 'number' && v >= ((WMNG_CONFIG.thresholds && WMNG_CONFIG.thresholds[1]) || 80);
            if (status === 'up' && (warn(cpu) || warn(mem))) return colors.node_warning || '#ffc107';
            if (status === 'up') return colors.node_up || '#28a745';
            return colors.node_unknown || '#6c757d';
        }

        function getLinkMetric(link) {
            const live = link.live || {};
            if (currentMetric === 'in') return (typeof live.in_bps === 'number') ? live.in_bps : null;
            if (currentMetric === 'out') return (typeof live.out_bps === 'number') ? live.out_bps : null;
            if (currentMetric === 'sum') {
                const a = (typeof live.in_bps === 'number') ? live.in_bps : 0;
                const b = (typeof live.out_bps === 'number') ? live.out_bps : 0;
                return (a + b) || null;
            }
            // percent - return the max of in/out bps for calculation
            const inBps = (typeof live.in_bps === 'number') ? live.in_bps : 0;
            const outBps = (typeof live.out_bps === 'number') ? live.out_bps : 0;
            return Math.max(inBps, outBps) || null;
        }

        function getLinkPct(link, metricBps) {
            // If we have a pre-calculated pct from live data, use it
            const live = link.live || {};
            if (typeof live.pct === 'number') return live.pct;

            // Otherwise calculate from bps and bandwidth
            if (typeof metricBps === 'number') {
                const bw = link.bandwidth_bps || link.bandwidth || null;
                if (bw) return Math.max(0, Math.min(100, (metricBps / bw) * 100));
                return null;
            }
            return null;
        }

        function getLinkColor(pct) {
            if (pct === null) return WMNG_CONFIG.colors.link_normal || '#28a745';
            const [t1, t2, t3] = WMNG_CONFIG.thresholds || [50, 80, 95];
            if (pct >= t2) return WMNG_CONFIG.colors.link_critical || '#dc3545';
            if (pct >= t1) return WMNG_CONFIG.colors.link_warning || '#ffc107';
            return WMNG_CONFIG.colors.link_normal || '#28a745';
        }

        // Single RAF loop helper. When reduced-motion is preferred and flow
        // animation is disabled, the loop renders once then stops (animationId
        // nulled) so we don't burn continuous animation work. Toggling flow
        // back on calls this again to restart the loop.
        function startAnimationLoop() {
            function tick() {
                animTick += 1;
                renderMap();
                if (flowAnimationEnabled || !reducedMotion) {
                    animationId = requestAnimationFrame(tick);
                } else {
                    animationId = null;
                }
            }
            if (animationId) { cancelAnimationFrame(animationId); }
            animationId = requestAnimationFrame(tick);
        }

        // Live updates via SSE (fallback to polling)
        function startLiveUpdates() {
            updateTransportButton();
            if (sseEnabled) {
                startSSE();
            } else {
                startAutoUpdate();
            }
            const btn = document.getElementById('toggle-transport');
            btn.addEventListener('click', () => {
                if (currentTransport === 'sse') {
                    stopSSE();
                    sseEnabled = false;
                    startAutoUpdate();
                } else {
                    stopPolling();
                    sseEnabled = true;
                    startSSE();
                }
            });
            
            // Flow animation controls
            document.getElementById('toggle-flow').addEventListener('click', () => {
                flowAnimationEnabled = !flowAnimationEnabled;
                const btn = document.getElementById('toggle-flow');
                if (flowAnimationEnabled) {
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                    // Restart RAF loop if it was stopped by the reduced-motion gate
                    if (!animationId) {
                        startAnimationLoop();
                    }
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                    particles = []; // Clear particles when disabled
                }
            });

            // Visualization settings menu toggle
            document.getElementById('viz-settings').addEventListener('click', (e) => {
                e.stopPropagation();
                const menu = document.getElementById('viz-menu');
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', () => {
                document.getElementById('viz-menu').style.display = 'none';
            });
            
            document.getElementById('particle-density').addEventListener('input', (e) => {
                particleDensity = parseFloat(e.target.value);
                document.getElementById('density-value').textContent = particleDensity.toFixed(1);
                particles = []; // Reset particles to apply new density
            });
            
            document.getElementById('particle-speed').addEventListener('input', (e) => {
                particleSpeed = parseFloat(e.target.value);
                document.getElementById('speed-value').textContent = particleSpeed.toFixed(1);
            });


            // Start animation loop — only needed when something actually animates.
            // When reduced-motion is preferred and flow is disabled, render once
            // and rely on live-update polls / manual interactions to re-render.
            startAnimationLoop();
        }

        function startSSE() {
            try {
                stopPolling();
                if (eventSourceRef) { try { eventSourceRef.close(); } catch {} }
                const url = `${baseUrl}/plugin/WeathermapNG/api/maps/${mapId}/sse?interval=${intervalSec}&max=${sseMax}`;
                const es = new EventSource(url);
                eventSourceRef = es;
                currentTransport = 'sse';
                updateTransportButton();
                es.onmessage = (e) => {
                    try {
                        sseReconnectAttempts = 0; // Reset on successful message
                        const live = JSON.parse(e.data);
                        applyLiveUpdate(live);
                    } catch {}
                };
                es.onerror = () => {
                    es.close();
                    eventSourceRef = null;
                    // Try to reconnect if SSE was enabled
                    if (sseEnabled && sseReconnectAttempts < maxReconnectAttempts) {
                        sseReconnectAttempts++;
                        setTimeout(() => {
                            if (sseEnabled) startSSE();
                        }, reconnectDelay);
                    } else {
                        // Fall back to polling after max attempts
                        currentTransport = 'poll';
                        sseEnabled = false;
                        sseReconnectAttempts = 0;
                        startAutoUpdate();
                    }
                };
            } catch (e) {
                currentTransport = 'poll';
                sseEnabled = false;
                startAutoUpdate();
            }
        }

        function stopSSE() {
            if (eventSourceRef) {
                try { eventSourceRef.close(); } catch {}
                eventSourceRef = null;
            }
        }

        function applyLiveUpdate(live) {
            lastDataUpdate = Date.now();
            // Attach link live data by id
            if (live && live.links && Array.isArray(mapData.links)) {
                mapData.links.forEach(l => {
                    const id = l.id ?? l.link_id ?? null;
                    if (id && live.links[id]) {
                        l.live = live.links[id];
                    }
                });
            }
            // Update node status by id
            if (live && live.nodes && Array.isArray(mapData.nodes)) {
                mapData.nodes.forEach(n => {
                    const id = n.id ?? n.node_id ?? null;
                    if (id && live.nodes[id]) {
                        n.status = live.nodes[id].status || n.status;
                        // Attach aggregated traffic and expose a simple value for label
                        if (live.nodes[id].traffic) {
                            n.traffic = live.nodes[id].traffic;
                            const sum = Number(live.nodes[id].traffic.sum_bps || 0);
                            n.current_value = isFinite(sum) ? sum : null;
                        }
                    }
                });
            }
            // Alert overlays
            if (live && live.alerts) {
                if (live.alerts.nodes && Array.isArray(mapData.nodes)) {
                    mapData.nodes.forEach(n => {
                        const id = n.id ?? n.node_id ?? null;
                        n.alerts = (id && live.alerts.nodes[id]) ? live.alerts.nodes[id] : { count: 0, severity: 'ok' };
                    });
                }
                if (live.alerts.links && Array.isArray(mapData.links)) {
                    mapData.links.forEach(l => {
                        const id = l.id ?? l.link_id ?? null;
                        l.alerts = (id && live.alerts.links[id]) ? live.alerts.links[id] : { count: 0, severity: 'ok' };
                    });
                }
            }
            renderMap();
        }

        function formatValue(value) {
            if (value === null || value === undefined) {
                return '';
            }
            if (typeof value !== 'number') {
                return String(value);
            }
            if (value >= 1000000000) {
                return (value / 1000000000).toFixed(1) + 'G';
            } else if (value >= 1000000) {
                return (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                return (value / 1000).toFixed(1) + 'K';
            }
            return value.toFixed(0);
        }

        function updateStatus() {
            const statusBar = document.getElementById('status-bar');
            const lastUpdated = document.getElementById('last-updated');

            if (lastDataUpdate) {
                const seconds = Math.floor((Date.now() - lastDataUpdate) / 1000);
                if (seconds < 5) {
                    lastUpdated.textContent = 'Just now';
                } else if (seconds < 60) {
                    lastUpdated.textContent = `${seconds}s ago`;
                } else {
                    const mins = Math.floor(seconds / 60);
                    lastUpdated.textContent = `${mins}m ago`;
                }
            } else {
                lastUpdated.textContent = 'Waiting...';
            }
            statusBar.style.display = 'block';
        }

        function renderLegend() {
            const rows = document.getElementById('legend-rows');
            if (!rows) return;
            rows.innerHTML = '';
            const [t1, t2, t3] = WMNG_CONFIG.thresholds || [50,80,95];
            const items = [
                { c: WMNG_CONFIG.colors.link_normal || '#28a745', l: `< ${t1}%` },
                { c: WMNG_CONFIG.colors.link_warning || '#ffc107', l: `${t1}–${t2}%` },
                { c: WMNG_CONFIG.colors.link_critical || '#dc3545', l: `≥ ${t2}%` }
            ];
            items.forEach(it => {
                const div = document.createElement('div');
                div.style.display = 'flex';
                div.style.alignItems = 'center';
                div.style.gap = '6px';
                div.innerHTML = `<span style="display:inline-block; width:18px; height:10px; background:${escapeHtml(it.c)}; border:1px solid #999;"></span><span>${escapeHtml(it.l)}</span>`;
                rows.appendChild(div);
            });
            const metricLabel = document.createElement('div');
            metricLabel.style.marginTop = '6px';
            metricLabel.style.color = '#444';
            metricLabel.textContent = `Metric: ${currentMetric}`;
            rows.appendChild(metricLabel);
        }

        function exportPNG() {
            try {
                const out = document.createElement('canvas');
                out.width = canvas.width; out.height = canvas.height;
                const octx = out.getContext('2d');
                octx.drawImage(canvas, 0, 0);
                const a = document.createElement('a');
                a.href = out.toDataURL('image/png');
                a.download = `weathermap-${mapId}.png`;
                a.click();
            } catch (e) { console.error('Export failed', e); }
        }

        function startAutoUpdate() {
            stopPolling();
            currentTransport = 'poll';
            updateTransportButton();
            fetchMapData();
            pollTimer = setInterval(() => {
                fetchMapData();
            }, intervalSec * 1000);
        }

        function fetchMapData() {
            fetch(`${baseUrl}/plugin/WeathermapNG/api/maps/${mapId}/json`)
                .then(response => {
                    if (!response.ok) { console.warn('Map data fetch failed: HTTP ' + response.status); return null; }
                    return response.json();
                })
                .then(data => {
                    if (data && !data.error) {
                        mapData = data;
                        lastDataUpdate = Date.now();
                        renderMap();
                    }
                })
                .catch(error => {
                    console.error('Error updating map:', error);
                });
        }

        function showError(message) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('map-container').innerHTML = `
                <div class="error">
                    <div>${escapeHtml(message)}</div>
                </div>
            `;
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (canvas) {
                const container = document.getElementById('map-container');
                canvas.width = container.clientWidth;
                canvas.height = container.clientHeight;
                renderMap();
            }
        });

        // Hover tooltip for link bandwidth
        function storeLinkGeom(link, x1, y1, x2, y2, pct, pathPoints) {
            const inBps = link.live?.in_bps ?? 0;
            const outBps = link.live?.out_bps ?? 0;
            const bandwidth = link.bandwidth_bps || link.bandwidth || null;
            const points = pathPoints || [{x:x1,y:y1},{x:x2,y:y2}];
            linkGeoms.push({x1,y1,x2,y2,pct,inBps,outBps,bandwidth,link,points});
        }

        function distToSegment(px, py, x1, y1, x2, y2) {
            const dx = x2 - x1, dy = y2 - y1;
            const len2 = dx*dx + dy*dy;
            if (len2 === 0) return Math.hypot(px - x1, py - y1);
            let t = ((px - x1)*dx + (py - y1)*dy) / len2;
            t = Math.max(0, Math.min(1, t));
            const projX = x1 + t*dx, projY = y1 + t*dy;
            return Math.hypot(px - projX, py - projY);
        }

        function distToPath(px, py, points) {
            let minDist = Infinity;
            for (let i = 1; i < points.length; i++) {
                const d = distToSegment(px, py, points[i-1].x, points[i-1].y, points[i].x, points[i].y);
                if (d < minDist) minDist = d;
            }
            return minDist;
        }

        function humanBits(v) {
            if (scale === 'bytes') {
                if (v >= 8e9) return (v/8e9).toFixed(2) + ' GB/s';
                if (v >= 8e6) return (v/8e6).toFixed(2) + ' MB/s';
                if (v >= 8e3) return (v/8e3).toFixed(2) + ' KB/s';
                return (v/8).toFixed(0) + ' B/s';
            }
            if (v >= 1e9) return (v/1e9).toFixed(2) + ' Gb/s';
            if (v >= 1e6) return (v/1e6).toFixed(2) + ' Mb/s';
            if (v >= 1e3) return (v/1e3).toFixed(2) + ' Kb/s';
            return v + ' b/s';
        }

        function updateTransportButton() {
            const btn = document.getElementById('toggle-transport');
            if (!btn) return;
            if (currentTransport === 'sse') {
                btn.textContent = `Live: SSE (${intervalSec}s)`;
            } else if (currentTransport === 'poll') {
                btn.textContent = `Live: Poll (${intervalSec}s)`;
            } else {
                btn.textContent = 'Live: …';
            }
        }

        document.getElementById('map-canvas').addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const mx = (x - viewOffsetX) / Math.max(0.0001, viewScale);
            const my = (y - viewOffsetY) / Math.max(0.0001, viewScale);
            // Node hover first
            let nbest = null; let nd = 1e9;
            for (const g of nodeGeoms) {
                const d = Math.hypot(mx - g.x, my - g.y) - g.r;
                if (d < nd && d < 8) { nd = d; nbest = g; }
            }
            let best = null, bestDist = 12; // threshold px
            if (!nbest) {
                for (const g of linkGeoms) {
                    if (g.points && g.points.length > 2) {
                        const pts = g.points.map(p => ({
                            x: p.x * viewScale + viewOffsetX,
                            y: p.y * viewScale + viewOffsetY
                        }));
                        const d = distToPath(x, y, pts);
                        if (d < bestDist) { bestDist = d; best = g; }
                    } else {
                        const lx1 = g.x1 * viewScale + viewOffsetX;
                        const ly1 = g.y1 * viewScale + viewOffsetY;
                        const lx2 = g.x2 * viewScale + viewOffsetX;
                        const ly2 = g.y2 * viewScale + viewOffsetY;
                        const d = distToSegment(x, y, lx1, ly1, lx2, ly2);
                        if (d < bestDist) { bestDist = d; best = g; }
                    }
                }
            }
            const tooltip = document.getElementById('tooltip');
            if (nbest) {
                const n = nbest.node;
                const t = n.traffic || {};
                tooltip.style.display = 'block';
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY + 10) + 'px';
                const sum = t.sum_bps ?? n.current_value ?? 0;
                const srcMap = { ports: 'ports', links: 'links', device: 'device', device_guess: 'device*', none: 'unknown' };
                const src = t.source ? (srcMap[t.source] || 'unknown') : 'unknown';
                tooltip.innerHTML = `${escapeHtml(n.label || n.id)}<br>` +
                  `In: ${humanBits(t.in_bps ?? 0)}<br>` +
                  `Out: ${humanBits(t.out_bps ?? 0)}<br>` +
                  `Sum: ${humanBits(sum ?? 0)}<br>` +
                  `<span style="opacity:0.75;">Source: ${src}</span>`;
            } else if (best) {
                tooltip.style.display = 'block';
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY + 10) + 'px';
                const pctVal = best.pct !== null ? Math.round(best.pct) + '%' : 'N/A';
                const bwLine = best.bandwidth ? `<br><span style="opacity:0.75;">Capacity: ${humanBits(best.bandwidth)}</span>` : '';
                tooltip.innerHTML = `<b>Utilization: ${pctVal}</b><br>` +
                    `<span style="color:#40ff40;">▼</span> In: ${humanBits(best.inBps)}<br>` +
                    `<span style="color:#40a0ff;">▲</span> Out: ${humanBits(best.outBps)}` + bwLine;
            } else {
                tooltip.style.display = 'none';
            }
        });
        // Click: node → device page; else link → port graphs
        document.getElementById('map-canvas').addEventListener('click', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const mx = (x - viewOffsetX) / Math.max(0.0001, viewScale);
            const my = (y - viewOffsetY) / Math.max(0.0001, viewScale);
            // Node first
            for (const g of nodeGeoms) {
                if (Math.hypot(mx - g.x, my - g.y) <= g.r + 4) {
                    const n = g.node;
                    const did = n.device_id || n.deviceId || n.deviceid;
                    if (did) {
                        const url = deviceBaseUrl + '/' + did;
                        window.open(url, '_blank');
                        return;
                    }
                }
            }
            // Else link
            let best = null, bestDist = 10;
            for (const g of linkGeoms) {
                if (g.points && g.points.length > 2) {
                    const pts = g.points.map(p => ({
                        x: p.x * viewScale + viewOffsetX,
                        y: p.y * viewScale + viewOffsetY
                    }));
                    const d = distToPath(x, y, pts);
                    if (d < bestDist) { bestDist = d; best = g; }
                } else {
                    const lx1 = g.x1 * viewScale + viewOffsetX;
                    const ly1 = g.y1 * viewScale + viewOffsetY;
                    const lx2 = g.x2 * viewScale + viewOffsetX;
                    const ly2 = g.y2 * viewScale + viewOffsetY;
                    const d = distToSegment(x, y, lx1, ly1, lx2, ly2);
                    if (d < bestDist) { bestDist = d; best = g; }
                }
            }
            if (best && best.link) {
                const pA = best.link.port_id_a || null;
                const pB = best.link.port_id_b || null;
                const now = Math.floor(Date.now()/1000);
                const from = now - 86400;
                const openGraph = (portId) => {
                    if (!portId) return;
                    const graphBaseUrl = '{{ url("graph") }}';
                    const url = graphBaseUrl + '?type=port_bits&id=' + portId + '&from=' + from + '&to=' + now;
                    window.open(url, '_blank');
                };
                if (pA) openGraph(pA);
                if (pB) openGraph(pB);
            }
        });

        function drawMinimap() {
            if (!minimap || !Array.isArray(mapData.nodes) || mapData.nodes.length === 0) return;

            // Calculate actual bounds from node positions
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            mapData.nodes.forEach(n => {
                const nx = (n.position?.x ?? n.x) || 0;
                const ny = (n.position?.y ?? n.y) || 0;
                minX = Math.min(minX, nx);
                minY = Math.min(minY, ny);
                maxX = Math.max(maxX, nx);
                maxY = Math.max(maxY, ny);
            });

            // Use larger of map dimensions or node extent (with padding)
            const padding = 50;
            const mw = Math.max(mapData.width || 800, maxX + padding);
            const mh = Math.max(mapData.height || 600, maxY + padding);

            const w = minimap.width, h = minimap.height;
            const s = Math.min(w/mw, h/mh);
            // Center the map in minimap
            const offsetX = (w - mw * s) / 2;
            const offsetY = (h - mh * s) / 2;
            const ctxm = minimap.getContext('2d');
            ctxm.clearRect(0,0,w,h);
            ctxm.fillStyle = '#fafafa'; ctxm.fillRect(0,0,w,h);

            // Draw map boundary
            ctxm.strokeStyle = '#ddd';
            ctxm.strokeRect(offsetX, offsetY, mw * s, mh * s);

            // Draw nodes
            mapData.nodes.forEach(n => {
                const x = offsetX + ((n.position?.x ?? n.x)||0) * s;
                const y = offsetY + ((n.position?.y ?? n.y)||0) * s;
                ctxm.fillStyle = getNodeColor(n);
                ctxm.beginPath();
                ctxm.arc(x, y, 3, 0, Math.PI * 2);
                ctxm.fill();
            });

            // Draw viewport rectangle (what's currently visible)
            if (viewScale > 0 && canvas) {
                const vpLeft = (-viewOffsetX / viewScale) * s + offsetX;
                const vpTop = (-viewOffsetY / viewScale) * s + offsetY;
                const vpWidth = (canvas.width / viewScale) * s;
                const vpHeight = (canvas.height / viewScale) * s;
                ctxm.strokeStyle = 'rgba(0, 123, 255, 0.8)';
                ctxm.lineWidth = 2;
                ctxm.strokeRect(vpLeft, vpTop, vpWidth, vpHeight);
                ctxm.lineWidth = 1;
            }

            // Border
            ctxm.strokeStyle = '#ccc'; ctxm.strokeRect(0,0,w,h);
        }
    </script>
</body>
</html>
