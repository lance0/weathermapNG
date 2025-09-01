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
        <div id="status-bar" class="status-bar" style="display: none;">
            <i class="fas fa-clock"></i> Updated: <span id="last-updated">Never</span>
        </div>
        <div id="tooltip" style="position:absolute; background: rgba(0,0,0,0.8); color:#fff; padding:6px 8px; border-radius:4px; font-size:12px; display:none; pointer-events:none;"></div>
        <div id="controls" style="position:absolute; top:10px; left:10px; z-index:1000; display:flex; gap:8px;">
            <button id="toggle-transport" style="background:#fff; border:1px solid #ccc; padding:4px 8px; border-radius:4px; cursor:pointer;">Live: loading…</button>
        </div>
    </div>

    <script>
        const mapId = '{{ $mapId }}';
        const baseUrl = '{{ url("/") }}';
        const WMNG_CONFIG = {
            thresholds: @json(config('weathermapng.thresholds', [50, 80, 95])),
            colors: @json(config('weathermapng.colors', [
                'link_normal' => '#28a745',
                'link_warning' => '#ffc107',
                'link_critical' => '#dc3545',
                'node_up' => '#28a745',
                'node_down' => '#dc3545',
                'node_unknown' => '#6c757d'
            ])),
            enable_sse: @json(config('weathermapng.enable_sse', true)),
            client_refresh: @json(config('weathermapng.client_refresh', 60)),
            scale: @json(config('weathermapng.scale', 'bits')),
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
        let mapData = @json($mapData);
        let canvas, ctx;
        let animationId;
        let lastUpdate = Date.now();

        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            if (mapData && !mapData.error) {
                renderMap();
                startLiveUpdates();
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

            // Hide loading
            document.getElementById('loading').style.display = 'none';
        }

        function renderMap() {
            if (!mapData || !mapData.nodes) return;

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Draw background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Calculate scale to fit map in canvas
            const mapWidth = mapData.width || mapData.metadata?.width || 800;
            const mapHeight = mapData.height || mapData.metadata?.height || 600;
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

            // Update status
            updateStatus();
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
            if (node.current_value !== null) {
                ctx.font = '10px Arial';
                ctx.fillStyle = '#666';
                const value = formatValue(node.current_value);
                ctx.fillText(value, x, y + radius + 15);
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
            const pct = getLinkPct(link);
            ctx.strokeStyle = getLinkColor(pct);
            ctx.lineWidth = Math.max(1, (link.width || 2));
            ctx.stroke();

            // Link utilization label
            if (pct !== null && pct !== undefined) {
                const midX = (x1 + x2) / 2;
                const midY = (y1 + y2) / 2;
                ctx.fillStyle = '#666';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(Math.round(pct) + '%', midX, midY - 5);
            }
            // store geometry for hover
            storeLinkGeom(link, x1, y1, x2, y2, pct);
        }

        function getNodeColor(node) {
            const status = node.status || 'unknown';
            const colors = WMNG_CONFIG.colors || {};
            if (status === 'down') return colors.node_down || '#dc3545';
            // If up but CPU or MEM high, warn
            const cpu = node.metrics?.cpu;
            const mem = node.metrics?.mem;
            const warn = (v) => typeof v === 'number' && v >= (WMNG_CONFIG.thresholds?.[1] ?? 80);
            if (status === 'up' && (warn(cpu) || warn(mem))) return colors.node_warning || '#ffc107';
            if (status === 'up') return colors.node_up || '#28a745';
            return colors.node_unknown || '#6c757d';
        }

        function getLinkPct(link) {
            // live data may be attached on link.live or link.utilization/pct
            if (link.live && typeof link.live.pct === 'number') return link.live.pct;
            if (typeof link.pct === 'number') return link.pct;
            if (typeof link.utilization === 'number') return link.utilization * 100;
            return null;
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
            renderMap();
        }

        function formatValue(value) {
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
                renderMap();
            }
        });

        // Hover tooltip for link bandwidth
        const linkGeoms = [];
        function storeLinkGeom(link, x1, y1, x2, y2, pct) {
            const inBps = link.live?.in_bps ?? 0;
            const outBps = link.live?.out_bps ?? 0;
            linkGeoms.push({x1,y1,x2,y2,pct,inBps,outBps});
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
    </script>
</body>
</html>
