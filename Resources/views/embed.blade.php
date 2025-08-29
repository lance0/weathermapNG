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
    </div>

    <script>
        const mapId = '{{ $mapId }}';
        const baseUrl = '{{ url("/") }}';
        let mapData = @json($mapData);
        let canvas, ctx;
        let animationId;
        let lastUpdate = Date.now();

        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            if (mapData && !mapData.error) {
                renderMap();
                startAutoUpdate();
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
            const mapWidth = mapData.metadata?.width || 800;
            const mapHeight = mapData.metadata?.height || 600;
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
            mapData.links.forEach(link => {
                drawLink(link);
            });

            // Draw nodes
            mapData.nodes.forEach(node => {
                drawNode(node);
            });

            ctx.restore();

            // Update status
            updateStatus();
        }

        function drawNode(node) {
            const x = node.position?.x || 0;
            const y = node.position?.y || 0;
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
            const sourceNode = mapData.nodes.find(n => n.id === link.source);
            const targetNode = mapData.nodes.find(n => n.id === link.target);

            if (!sourceNode || !targetNode) return;

            const x1 = sourceNode.position?.x || 0;
            const y1 = sourceNode.position?.y || 0;
            const x2 = targetNode.position?.x || 0;
            const y2 = targetNode.position?.y || 0;

            // Draw link line
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.strokeStyle = link.color || '#28a745';
            ctx.lineWidth = Math.max(1, (link.width || 2));
            ctx.stroke();

            // Link utilization label
            if (link.utilization !== undefined) {
                const midX = (x1 + x2) / 2;
                const midY = (y1 + y2) / 2;
                ctx.fillStyle = '#666';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(Math.round(link.utilization * 100) + '%', midX, midY - 5);
            }
        }

        function getNodeColor(node) {
            const status = node.status || 'unknown';
            const colors = {
                'up': '#28a745',
                'down': '#dc3545',
                'warning': '#ffc107',
                'unknown': '#6c757d'
            };
            return colors[status] || colors.unknown;
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
            // Update every 5 minutes
            setInterval(() => {
                fetchMapData();
            }, 5 * 60 * 1000);
        }

        function fetchMapData() {
            fetch(`${baseUrl}/plugins/weathermapng/api/map/${mapId}`)
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
    </script>
</body>
</html>