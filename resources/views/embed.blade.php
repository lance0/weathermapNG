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
        }

        .loading i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
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
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="map-container">
        <div id="loading" class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <div>Loading map...</div>
        </div>
        <canvas id="map-canvas"></canvas>
        <canvas id="heat-canvas" style="position:absolute; top:0; left:0; pointer-events:none;"></canvas>
        <canvas id="minimap" width="160" height="120" style="position:absolute; top:10px; right:10px; background:rgba(255,255,255,0.85); border:1px solid #ddd; border-radius:4px;"></canvas>
        <div id="status-bar" class="status-bar" style="display: none;">
            <i class="fas fa-clock"></i> Updated: <span id="last-updated">Never</span>
        </div>
        <div id="tooltip" style="position:absolute; background: rgba(0,0,0,0.8); color:#fff; padding:6px 8px; border-radius:4px; font-size:12px; display:none; pointer-events:none;"></div>
        <div id="controls" style="position:absolute; top:10px; left:10px; z-index:1000; display:flex; gap:8px; align-items:center;">
            <button id="toggle-transport" style="background:#fff; border:1px solid #ccc; padding:4px 8px; border-radius:4px; cursor:pointer;">Live: loading…</button>
            <button id="toggle-flow" style="background:#007bff; color:#fff; border:1px solid #007bff; padding:4px 8px; border-radius:4px; cursor:pointer;" title="Toggle flow animation">🌊 Flow</button>
            <button id="toggle-heatmap" style="background:#6c757d; color:#fff; border:1px solid #6c757d; padding:4px 8px; border-radius:4px; cursor:pointer;" title="Toggle heatmap overlay">🔥 Heat</button>
            <div style="position:relative;">
                <button id="viz-settings" style="background:#fff; border:1px solid #ccc; padding:4px 8px; border-radius:4px; cursor:pointer;" title="Visualization settings">⚙️</button>
                <div id="viz-menu" style="position:absolute; top:100%; left:0; background:#fff; border:1px solid #ccc; border-radius:4px; padding:10px; min-width:250px; display:none; margin-top:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size:12px; font-weight:bold; margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:4px;">Flow Animation</div>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:11px; display:block;">Particle Density: <span id="density-value">1.0</span></label>
                        <input type="range" id="particle-density" min="0.5" max="2" step="0.1" value="1" style="width:100%;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; display:block;">Particle Speed: <span id="speed-value">1.0</span></label>
                        <input type="range" id="particle-speed" min="0.5" max="2" step="0.1" value="1" style="width:100%;">
                    </div>
                    <div style="font-size:12px; font-weight:bold; margin-bottom:8px; border-bottom:1px solid #ddd; padding-bottom:4px;">Heatmap Overlay</div>
                    <div>
                        <label style="font-size:11px; display:block;">Intensity: <span id="heatmap-intensity-value">1.0</span></label>
                        <input type="range" id="heatmap-intensity" min="0.5" max="2" step="0.1" value="1" style="width:100%;">
                    </div>
                </div>
            </div>
            <label style="background:#fff; border:1px solid #ccc; padding:2px 6px; border-radius:4px; font-size:12px;">
                Metric
                <select id="metric-select" style="border:none; outline:none; font-size:12px;">
                    <option value="percent">Percent</option>
                    <option value="in">Inbound</option>
                    <option value="out">Outbound</option>
                    <option value="sum">In+Out</option>
                </select>
            </label>
            <button id="export-png" title="Export PNG" style="background:#fff; border:1px solid #ccc; padding:4px 8px; border-radius:4px; cursor:pointer;">Export PNG</button>
        </div>
        <div id="legend" style="position:absolute; bottom:10px; right:10px; background:rgba(255,255,255,0.9); border:1px solid #ddd; border-radius:4px; font-size:12px; padding:6px 8px; z-index:1000;">
            <div style="font-weight:600; margin-bottom:4px;">Legend</div>
            <div id="legend-rows"></div>
        </div>
    </div>

    <script>
        const mapId = '{{ $mapId }}';
        const baseUrl = '{{ url("/") }}';
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
            scale: {!! json_encode(config('weathermapng.scale') ?? 'bits') !!}
        };
        const urlParams = new URLSearchParams(window.location.search);
        const param = (k, d) => urlParams.has(k) ? urlParams.get(k) : d;
        let scale = (param('scale', WMNG_CONFIG.scale) || '').toLowerCase();
        if (scale !== 'bytes') scale = 'bits';
        let intervalSec = parseInt(param('interval', WMNG_CONFIG.client_refresh), 10) || WMNG_CONFIG.client_refresh;
        let sseEnabled = param('sse', WMNG_CONFIG.enable_sse ? '1' : '0') !== '0' && !!window.EventSource;
        let sseMax = parseInt(param('max', 60), 10) || 60;
        let currentTransport = 'init';
        let eventSourceRef = null;
        let mapData = {};
        try {
            mapData = {!! json_encode($mapData ?? []) !!};
        } catch (e) {
            console.error('Failed to parse map data:', e);
            mapData = { error: 'Invalid map data' };
        }
        let canvas, ctx, heatCanvas, heatCtx, minimap;
        let animationId;
        let lastUpdate = Date.now();
        let animTick = 0;
        let bgImg = null;
        let currentMetric = (param('metric', 'percent') || 'percent').toLowerCase();
        
        // Flow animation particles
        let particles = [];
        let flowAnimationEnabled = true;
        let particleDensity = 1.0; // 0.5 to 2.0
        let particleSpeed = 1.0; // 0.5 to 2.0
        
        // Heatmap overlay
        let heatmapEnabled = false;
        let heatmapIntensity = 1.0; // 0.5 to 2.0

        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            if (mapData && !mapData.error) {
                renderMap();
                startLiveUpdates();
                renderLegend();
                const ms = document.getElementById('metric-select');
                if (ms) { ms.value = currentMetric; ms.addEventListener('change', () => { currentMetric = ms.value; renderLegend(); renderMap(); }); }
                const ex = document.getElementById('export-png');
                if (ex) ex.addEventListener('click', exportPNG);
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
            heatCanvas = document.getElementById('heat-canvas');
            heatCanvas.width = canvas.width;
            heatCanvas.height = canvas.height;
            heatCtx = heatCanvas.getContext('2d');
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
            const scale = Math.min(scaleX, scaleY, 1); // Don't scale up

            // Center the map
            const offsetX = (canvas.width - mapWidth * scale) / 2;
            const offsetY = (canvas.height - mapHeight * scale) / 2;

            ctx.save();
            ctx.translate(offsetX, offsetY);
            ctx.scale(scale, scale);

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
            if (heatmapEnabled) {
                drawHeatOverlay();
            }
        }

        function drawNode(node) {
            const x = (node.position?.x ?? node.x) || 0;
            const y = (node.position?.y ?? node.y) || 0;
            const radius = 8;

            // Node body
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, 2 * Math.PI);
            ctx.fillStyle = getNodeColor(node);
            ctx.fill();
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1;
            ctx.stroke();

            // Node label
            ctx.fillStyle = '#000';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(node.label || node.id, x, y - radius - 5);

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
        }

        function drawLink(link) {
            const srcId = link.source ?? link.src ?? link.source_id;
            const dstId = link.target ?? link.dst ?? link.destination_id;
            const sourceNode = mapData.nodes.find(n => (n.id ?? n.src_node_id) === srcId);
            const targetNode = mapData.nodes.find(n => (n.id ?? n.dst_node_id) === dstId);

            if (!sourceNode || !targetNode) return;

            const x1 = sourceNode.position?.x || 0;
            const y1 = sourceNode.position?.y || 0;
            const x2 = targetNode.position?.x || 0;
            const y2 = targetNode.position?.y || 0;

            // Draw link line
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            const metric = getLinkMetric(link);
            const pct = getLinkPct(link, metric);
            ctx.strokeStyle = getLinkColor(pct);
            const width = Math.max(1, (link.width || 2));
            ctx.lineWidth = width;
            
            // Use solid line if flow animation is enabled
            if (!flowAnimationEnabled) {
                const dash = Math.max(6, width * 3);
                ctx.setLineDash([dash, dash]);
                const speed = Math.max(0.5, Math.min(5, ((pct ?? 10)) / 20));
                ctx.lineDashOffset = - (animTick * speed);
            }
            ctx.stroke();
            ctx.setLineDash([]);
            
            // Draw flow particles if enabled
            if (flowAnimationEnabled) {
                drawFlowParticles(link, x1, y1, x2, y2, pct);
            }

            // Link utilization label
            if (metric !== null && metric !== undefined) {
                const midX = (x1 + x2) / 2;
                const midY = (y1 + y2) / 2;
                ctx.fillStyle = '#111';
                ctx.font = '11px Arial';
                ctx.textAlign = 'center';
                const label = (currentMetric === 'percent') ? (Math.round(pct) + '%') : humanBits(metric);
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 3;
                ctx.strokeText(label, midX, midY - 5);
                ctx.fillText(label, midX, midY - 5);
            }
            // Link alert badge (diamond)
            if (link.alerts && link.alerts.count > 0) {
                const midX = (x1 + x2) / 2;
                const midY = (y1 + y2) / 2;
                const size = 5;
                ctx.save();
                ctx.translate(midX + 10, midY - 10);
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
            storeLinkGeom(link, x1, y1, x2, y2, pct);
        }
        
        function drawFlowParticles(link, x1, y1, x2, y2, pct) {
            const live = link.live || {};
            const inBps = live.in_bps || 0;
            const outBps = live.out_bps || 0;
            
            // Skip if no traffic
            if (inBps === 0 && outBps === 0) return;
            
            const dx = x2 - x1;
            const dy = y2 - y1;
            const length = Math.sqrt(dx * dx + dy * dy);
            if (length === 0) return;
            
            const nx = dx / length;
            const ny = dy / length;
            
            // Calculate particle properties based on traffic
            const maxFlow = Math.max(inBps, outBps);
            const relativeFlow = pct ? pct / 100 : 0.5;
            const particleCount = Math.max(1, Math.floor((length / 50) * relativeFlow * particleDensity));
            const particleSpacing = length / (particleCount + 1);
            const speedFactor = 0.5 + (relativeFlow * 1.5) * particleSpeed;
            
            // Create unique link ID
            const linkId = `${link.id || (x1 + '-' + y1 + '-' + x2 + '-' + y2)}`;
            
            // Initialize particles for this link if needed
            if (!particles[linkId]) {
                particles[linkId] = {
                    forward: [],
                    backward: [],
                    inRatio: inBps / (inBps + outBps + 0.001),
                    outRatio: outBps / (inBps + outBps + 0.001)
                };
                
                // Create forward particles (source to dest)
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
                
                // Create backward particles (dest to source)
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
            
            // Update and draw forward particles
            ctx.save();
            if (linkParticles.forward && Array.isArray(linkParticles.forward)) {
                linkParticles.forward.forEach(particle => {
                particle.progress += (particle.speed * 0.005);
                if (particle.progress > 1) particle.progress -= 1;
                
                const px = x1 + (dx * particle.progress);
                const py = y1 + (dy * particle.progress);
                
                // Draw particle with glow effect
                ctx.globalAlpha = particle.opacity * 0.3;
                ctx.fillStyle = '#00ff00';
                ctx.beginPath();
                ctx.arc(px, py, particle.size * 2, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.globalAlpha = particle.opacity;
                ctx.fillStyle = '#40ff40';
                ctx.beginPath();
                ctx.arc(px, py, particle.size, 0, Math.PI * 2);
                ctx.fill();
                });
            }
            
            // Update and draw backward particles
            if (linkParticles.backward && Array.isArray(linkParticles.backward)) {
                linkParticles.backward.forEach(particle => {
                particle.progress += (particle.speed * 0.005);
                if (particle.progress > 1) particle.progress -= 1;
                
                const px = x2 - (dx * particle.progress);
                const py = y2 - (dy * particle.progress);
                
                // Draw particle with glow effect
                ctx.globalAlpha = particle.opacity * 0.3;
                ctx.fillStyle = '#0080ff';
                ctx.beginPath();
                ctx.arc(px, py, particle.size * 2, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.globalAlpha = particle.opacity;
                ctx.fillStyle = '#40a0ff';
                ctx.beginPath();
                ctx.arc(px, py, particle.size, 0, Math.PI * 2);
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
            // percent
            if (typeof live.pct === 'number') return live.pct;
            const bps = (typeof live.in_bps === 'number') ? live.in_bps : ((live.in_bps || 0) + (live.out_bps || 0));
            return getLinkPct(link, bps);
        }

        function getLinkPct(link, metricBps) {
            if (typeof metricBps === 'number') {
                const bw = link.bandwidth_bps || link.bandwidth || null;
                if (bw) return Math.max(0, Math.min(100, (metricBps / bw) * 100));
                return null;
            }
            if (metricBps === null || typeof metricBps === 'undefined') return null;
            return metricBps; // already percent
        }

        function getLinkColor(pct) {
            if (pct === null) return WMNG_CONFIG.colors.link_normal || '#28a745';
            const [t1, t2, t3] = WMNG_CONFIG.thresholds || [50, 80, 95];
            if (pct >= t2) return WMNG_CONFIG.colors.link_critical || '#dc3545';
            if (pct >= t1) return WMNG_CONFIG.colors.link_warning || '#ffc107';
            return WMNG_CONFIG.colors.link_normal || '#28a745';
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
                    btn.style.background = '#007bff';
                    btn.style.color = '#fff';
                    btn.style.borderColor = '#007bff';
                } else {
                    btn.style.background = '#6c757d';
                    btn.style.color = '#fff';
                    btn.style.borderColor = '#6c757d';
                    particles = []; // Clear particles when disabled
                }
            });
            
            // Heatmap toggle
            document.getElementById('toggle-heatmap').addEventListener('click', () => {
                heatmapEnabled = !heatmapEnabled;
                const btn = document.getElementById('toggle-heatmap');
                if (heatmapEnabled) {
                    btn.style.background = '#dc3545';
                    btn.style.borderColor = '#dc3545';
                } else {
                    btn.style.background = '#6c757d';
                    btn.style.borderColor = '#6c757d';
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
            
            document.getElementById('heatmap-intensity').addEventListener('input', (e) => {
                heatmapIntensity = parseFloat(e.target.value);
                document.getElementById('heatmap-intensity-value').textContent = heatmapIntensity.toFixed(1);
            });
            
            // start animation loop
            function tick() {
                animTick += 1;
                renderMap();
                animationId = requestAnimationFrame(tick);
            }
            animationId = requestAnimationFrame(tick);
        }

        let pollTimer = null;
        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
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
                        const live = JSON.parse(e.data);
                        applyLiveUpdate(live);
                    } catch {}
                };
                es.onerror = () => {
                    es.close();
                    eventSourceRef = null;
                    // fall back to polling
                    currentTransport = 'poll';
                    sseEnabled = false;
                    startAutoUpdate();
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

            lastUpdated.textContent = new Date().toLocaleTimeString();
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
                div.innerHTML = `<span style=\"display:inline-block; width:18px; height:10px; background:${it.c}; border:1px solid #999;\"></span><span>${it.l}</span>`;
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
                octx.drawImage(heatCanvas, 0, 0);
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
                .then(response => response.json())
                .then(data => {
                    if (!data.error) {
                        mapData = data;
                        renderMap();
                        lastUpdate = Date.now();
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
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>${message}</div>
                </div>
            `;
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (canvas) {
                const container = document.getElementById('map-container');
                canvas.width = container.clientWidth;
                canvas.height = container.clientHeight;
                const heat = document.getElementById('heat-canvas');
                heat.width = canvas.width;
                heat.height = canvas.height;
                renderMap();
            }
        });

        // Hover tooltip for link bandwidth
        const linkGeoms = [];
        function storeLinkGeom(link, x1, y1, x2, y2, pct) {
            const inBps = link.live?.in_bps ?? 0;
            const outBps = link.live?.out_bps ?? 0;
            linkGeoms.push({x1,y1,x2,y2,pct,inBps,outBps, link});
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
            // find nearest segment
            let best = null, bestDist = 12; // threshold px
            for (const g of linkGeoms) {
                const d = distToSegment(x, y, g.x1, g.y1, g.x2, g.y2);
                if (d < bestDist) { bestDist = d; best = g; }
            }
            const tooltip = document.getElementById('tooltip');
            if (best) {
                tooltip.style.display = 'block';
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY + 10) + 'px';
                tooltip.innerHTML = `Util: ${best.pct ?? 0}%<br>In: ${humanBits(best.inBps)}<br>Out: ${humanBits(best.outBps)}`;
            } else {
                tooltip.style.display = 'none';
            }
        });
        // Click link to open historical graphs
        document.getElementById('map-canvas').addEventListener('click', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            let best = null, bestDist = 10;
            for (const g of linkGeoms) {
                const d = distToSegment(x, y, g.x1, g.y1, g.x2, g.y2);
                if (d < bestDist) { bestDist = d; best = g; }
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
            if (!minimap || !Array.isArray(mapData.nodes)) return;
            const mw = mapData.width || 800, mh = mapData.height || 600;
            const w = minimap.width, h = minimap.height;
            const s = Math.min(w/mw, h/mh);
            const ctxm = minimap.getContext('2d');
            ctxm.clearRect(0,0,w,h);
            ctxm.fillStyle = '#fafafa'; ctxm.fillRect(0,0,w,h);
            // draw nodes
            mapData.nodes.forEach(n => {
                const x = ((n.position?.x ?? n.x)||0) * s;
                const y = ((n.position?.y ?? n.y)||0) * s;
                ctxm.fillStyle = getNodeColor(n);
                ctxm.fillRect(x-2, y-2, 4, 4);
            });
            ctxm.strokeStyle = '#ccc'; ctxm.strokeRect(0,0,w,h);
        }

        function drawHeatOverlay() {
            if (!heatCtx) return;
            heatCtx.clearRect(0, 0, heatCanvas.width, heatCanvas.height);
            
            // Create temporary canvas for gradient processing
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = heatCanvas.width;
            tempCanvas.height = heatCanvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            
            // Draw heat points for links
            linkGeoms.forEach(g => {
                if (g.pct == null || g.pct === 0) return;
                
                // Calculate intensity based on utilization
                const intensity = (g.pct / 100) * heatmapIntensity;
                const radius = 40 + (intensity * 30);
                
                // Draw heat points along the link
                const steps = Math.max(3, Math.floor(Math.sqrt((g.x2-g.x1)**2 + (g.y2-g.y1)**2) / 50));
                for (let i = 0; i <= steps; i++) {
                    const t = i / steps;
                    const x = g.x1 + (g.x2 - g.x1) * t;
                    const y = g.y1 + (g.y2 - g.y1) * t;
                    
                    const gradient = tempCtx.createRadialGradient(x, y, 0, x, y, radius);
                    
                    // Color based on utilization level
                    if (g.pct < 30) {
                        gradient.addColorStop(0, `rgba(0, 255, 0, ${intensity * 0.5})`);
                        gradient.addColorStop(0.5, `rgba(0, 255, 0, ${intensity * 0.2})`);
                        gradient.addColorStop(1, 'rgba(0, 255, 0, 0)');
                    } else if (g.pct < 60) {
                        gradient.addColorStop(0, `rgba(255, 255, 0, ${intensity * 0.6})`);
                        gradient.addColorStop(0.5, `rgba(255, 255, 0, ${intensity * 0.3})`);
                        gradient.addColorStop(1, 'rgba(255, 255, 0, 0)');
                    } else if (g.pct < 80) {
                        gradient.addColorStop(0, `rgba(255, 165, 0, ${intensity * 0.7})`);
                        gradient.addColorStop(0.5, `rgba(255, 165, 0, ${intensity * 0.35})`);
                        gradient.addColorStop(1, 'rgba(255, 165, 0, 0)');
                    } else {
                        gradient.addColorStop(0, `rgba(255, 0, 0, ${intensity * 0.8})`);
                        gradient.addColorStop(0.5, `rgba(255, 0, 0, ${intensity * 0.4})`);
                        gradient.addColorStop(1, 'rgba(255, 0, 0, 0)');
                    }
                    
                    tempCtx.fillStyle = gradient;
                    tempCtx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
                }
            });
            
            // Draw heat for problematic nodes
            (mapData.nodes || []).forEach(n => {
                const x = (n.position?.x ?? n.x) || 0;
                const y = (n.position?.y ?? n.y) || 0;
                const status = n.status || 'unknown';
                const cpu = n.metrics?.cpu || 0;
                const mem = n.metrics?.mem || 0;
                
                let drawHeat = false;
                let color, intensity;
                
                if (status === 'down') {
                    drawHeat = true;
                    color = [220, 53, 69]; // red
                    intensity = 0.8 * heatmapIntensity;
                } else if (cpu > 80 || mem > 80) {
                    drawHeat = true;
                    color = [255, 193, 7]; // yellow
                    intensity = (Math.max(cpu, mem) / 100) * 0.6 * heatmapIntensity;
                }
                
                if (drawHeat) {
                    const radius = 50 * (1 + intensity);
                    const gradient = tempCtx.createRadialGradient(x, y, 0, x, y, radius);
                    gradient.addColorStop(0, `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${intensity})`);
                    gradient.addColorStop(0.5, `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${intensity * 0.5})`);
                    gradient.addColorStop(1, `rgba(${color[0]}, ${color[1]}, ${color[2]}, 0)`);
                    
                    tempCtx.fillStyle = gradient;
                    tempCtx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
                }
            });
            
            // Apply blur for smooth heatmap effect
            heatCtx.filter = 'blur(15px)';
            heatCtx.globalCompositeOperation = 'source-over';
            heatCtx.drawImage(tempCanvas, 0, 0);
            heatCtx.filter = 'none';
        }
    </script>
</body>
</html>
