<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeathermapNG - {{ $mapId }}</title>
    <link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/embed.css') }}">
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
        <canvas id="overlay-canvas"></canvas>
        <canvas id="minimap" width="160" height="120" class="embed-minimap"></canvas>
        <div id="status-bar" class="status-bar" style="display: none;">
            <i class="fas fa-clock"></i> Updated: <span id="last-updated">Never</span>
        </div>
        <div id="tooltip" class="embed-tooltip"></div>
        <div id="graph-popup" class="embed-graph-popup"></div>
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
        <button type="button" id="kiosk-exit" class="kiosk-exit" aria-label="Exit kiosk mode">Exit Kiosk</button>
    </div>

    <script>
        // Server-rendered bootstrap: everything the JS modules need from Blade.
        window.WMNG = window.WMNG || {};
        window.WMNG.EmbedConfig = {
            mapId: '{{ $mapId }}',
            baseUrl: '{{ url("/") }}',
            deviceBaseUrl: '{{ url("device") }}',
            graphBaseUrl: '{{ url("graph") }}',
            kioskEnabled: @json($kiosk),
            cycleSeconds: @json($cycleSeconds),
            linkTarget: @json($target),
            mapList: @json($mapList ?? []),
            thresholds: @json(config('weathermapng.thresholds') ?? [50, 80, 95]),
            colors: {
                link_normal: '{{ config('weathermapng.colors.link_normal', '#28a745') }}',
                link_warning: '{{ config('weathermapng.colors.link_warning', '#ffc107') }}',
                link_critical: '{{ config('weathermapng.colors.link_critical', '#dc3545') }}',
                node_up: '{{ config('weathermapng.colors.node_up', '#28a745') }}',
                node_down: '{{ config('weathermapng.colors.node_down', '#dc3545') }}',
                node_warning: '{{ config('weathermapng.colors.node_warning', '#ffc107') }}',
                node_unknown: '{{ config('weathermapng.colors.node_unknown', '#6c757d') }}'
            },
            enable_sse: @json(config('weathermapng.enable_sse') ?? true),
            client_refresh: @json(config('weathermapng.client_refresh') ?? 60),
            scale: @json(config('weathermapng.scale') ?? 'bits'),
            link_style: '{{ config('weathermapng.link_style', 'straight') }}',
            show_bandwidth: @json(config('weathermapng.show_bandwidth', true)),
            show_percentages: @json(config('weathermapng.show_percentages', true)),
            show_node_metrics: @json(config('weathermapng.show_node_metrics', true)),
        };
        window.WMNG.EmbedData = {
            mapData: @json($mapData ?? []),
            liveData: @json($liveData ?? []),
        };
    </script>
    <script src="{{ asset('plugins/WeathermapNG/resources/js/embed-app.js') }}"></script>
</body>
</html>
