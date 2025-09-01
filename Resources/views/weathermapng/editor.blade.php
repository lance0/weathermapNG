@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-edit"></i> {{ $map ? 'Edit Map: ' . $map->name : 'Create New Map' }}</h1>
                <div>
                    <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Maps
                    </a>
                    <button class="btn btn-success" onclick="saveMap()">
                        <i class="fas fa-save"></i> Save Map
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Canvas Area -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Map Canvas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="canvas-container" style="overflow: auto; max-height: 600px;">
                        <canvas id="map-canvas"
                                width="{{ $map->width ?? config('weathermapng.default_width', 800) }}"
                                height="{{ $map->height ?? config('weathermapng.default_height', 600) }}"
                                style="border: 1px solid #dee2e6; display: block; margin: 0 auto;">
                        </canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools Panel -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tools</h5>
                </div>
                <div class="card-body">
                    <!-- Device Selection -->
                    <div class="mb-3">
                        <label for="device-select" class="form-label">Select Device:</label>
                        <select class="form-select" id="device-select">
                            <option value="">Choose a device...</option>
                        </select>
                    </div>

                    <!-- Interface Selection -->
                    <div class="mb-3" id="interface-container" style="display: none;">
                        <label for="interface-select" class="form-label">Select Interface:</label>
                        <select class="form-select" id="interface-select">
                            <option value="">Choose an interface...</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="addNode()">
                            <i class="fas fa-plus-circle"></i> Add Node
                        </button>
                        <button class="btn btn-outline-primary" id="link-mode-btn" onclick="toggleLinkMode()">
                            <i class="fas fa-link"></i> Link Mode: Off
                        </button>
                        <button class="btn btn-warning" onclick="clearCanvas()">
                            <i class="fas fa-trash"></i> Clear Canvas
                        </button>
                        <button class="btn btn-info" onclick="exportConfig()">
                            <i class="fas fa-download"></i> Export Config
                        </button>
                    </div>

                    <!-- Map Properties -->
                    <hr>
                    <h6>Map Properties</h6>
                    <div class="mb-3">
                        <label for="map-name" class="form-label">Map Name:</label>
                        <input type="text" class="form-control" id="map-name"
                               value="{{ $map->name ?? '' }}" placeholder="Enter map name">
                    </div>
                    <div class="mb-3">
                        <label for="map-title" class="form-label">Map Title:</label>
                        <input type="text" class="form-control" id="map-title"
                               value="{{ $map->title ?? '' }}" placeholder="Enter map title">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label for="map-width" class="form-label">Width:</label>
                            <input type="number" class="form-control" id="map-width"
                                   value="{{ $map->width ?? config('weathermapng.default_width', 800) }}"
                                   min="100" max="4096">
                        </div>
                        <div class="col-6">
                            <label for="map-height" class="form-label">Height:</label>
                            <input type="number" class="form-control" id="map-height"
                                   value="{{ $map->height ?? config('weathermapng.default_height', 600) }}"
                                   min="100" max="4096">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Instructions</h6>
                </div>
                <div class="card-body">
                    <ol class="small">
                        <li>Select a device from the dropdown</li>
                        <li>Choose an interface for that device</li>
                        <li>Click "Add Node" to place it on the canvas</li>
                        <li>Drag nodes to reposition them</li>
                        <li>Save your map when finished</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let nodes = [];
let selectedDevice = null;
let selectedInterface = null;
let nodeCounter = 1;
let mapId = {{ $map->id ?? 'null' }};
let linkMode = false;
let linkSource = null;

document.addEventListener('DOMContentLoaded', function() {
    initCanvas();
    if (mapId) loadMapData(mapId);
    loadDevices();

    // Device selection handler
    document.getElementById('device-select').addEventListener('change', function() {
        selectedDevice = this.value;
        if (selectedDevice) {
            loadInterfaces(selectedDevice);
        } else {
            document.getElementById('interface-container').style.display = 'none';
        }
    });
});

function loadMapData(id) {
    fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${id}/json`)
        .then(r => r.json())
        .then(data => {
            if (data && Array.isArray(data.nodes)) {
                nodes = data.nodes.map(n => ({
                    dbId: n.id,
                    label: n.label,
                    x: n.x,
                    y: n.y,
                    deviceId: n.device_id || null,
                    interfaceId: n.meta?.interface_id || null,
                }));
                renderEditor();
            }
        }).catch(() => {});
}

function initCanvas() {
    const canvas = document.getElementById('map-canvas');
    const ctx = canvas.getContext('2d');

    // Set canvas background
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Add grid
    ctx.strokeStyle = '#dee2e6';
    ctx.lineWidth = 1;
    for (let x = 0; x <= canvas.width; x += 20) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, canvas.height);
        ctx.stroke();
    }
    for (let y = 0; y <= canvas.height; y += 20) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(canvas.width, y);
        ctx.stroke();
    }
    // Drag + drop support
    let dragging = null;
    let dragOffset = {x:0,y:0};
    canvas.addEventListener('mousedown', (e) => {
        const pos = getMousePos(canvas, e);
        const hit = hitTestNode(pos.x, pos.y);
        if (linkMode && hit) {
            if (!linkSource) {
                linkSource = hit;
            } else if (linkSource !== hit) {
                // create link
                createLink(linkSource, hit);
                linkSource = null;
            }
            return;
        }
        if (hit) {
            dragging = hit;
            dragOffset.x = hit.x - pos.x;
            dragOffset.y = hit.y - pos.y;
        }
    });
    canvas.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        const pos = getMousePos(canvas, e);
        dragging.x = pos.x + dragOffset.x;
        dragging.y = pos.y + dragOffset.y;
        renderEditor();
    });
    canvas.addEventListener('mouseup', () => {
        if (dragging && mapId && dragging.dbId) {
            // persist position
            patchNodePos(dragging);
        }
        dragging = null;
    });
}

function toggleLinkMode() {
    linkMode = !linkMode;
    linkSource = null;
    document.getElementById('link-mode-btn').textContent = `Link Mode: ${linkMode ? 'On' : 'Off'}`;
}

function createLink(srcNode, dstNode) {
    if (!mapId) {
        alert('Save map first to create links.');
        return;
    }
    const payload = {
        src_node_id: srcNode.dbId,
        dst_node_id: dstNode.dbId,
        port_id_a: srcNode.interfaceId || null,
        port_id_b: dstNode.interfaceId || null,
        bandwidth_bps: null,
        style: {}
    };
    fetch(`{{ url('plugin/WeathermapNG/map') }}/${mapId}/link`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (!d.success) {
            alert('Failed to create link: ' + (d.message || 'Unknown error'));
        } else {
            renderEditor();
        }
    }).catch(err => alert('Failed to create link: ' + err.message));
}

function getMousePos(canvas, evt) {
    const rect = canvas.getBoundingClientRect();
    return { x: evt.clientX - rect.left, y: evt.clientY - rect.top };
}

function hitTestNode(x, y) {
    for (let i = nodes.length - 1; i >= 0; i--) {
        const n = nodes[i];
        const dx = x - n.x, dy = y - n.y;
        if (Math.hypot(dx, dy) <= 12) return n;
    }
    return null;
}

function renderEditor() {
    const canvas = document.getElementById('map-canvas');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0,0,canvas.width, canvas.height);
    // background + grid
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#eee';
    for (let x = 0; x <= canvas.width; x += 20) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x, canvas.height); ctx.stroke(); }
    for (let y = 0; y <= canvas.height; y += 20) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(canvas.width, y); ctx.stroke(); }
    // draw nodes
    nodes.forEach(drawNode);
}

function patchNodePos(node) {
    fetch(`{{ url('plugin/WeathermapNG/map') }}/${mapId}/node/${node.dbId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ x: node.x, y: node.y })
    }).catch(() => {});
}

function loadDevices() {
    fetch('{{ url("plugin/WeathermapNG/api/devices") }}')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('device-select');
            select.innerHTML = '<option value="">Choose a device...</option>';

            data.devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.device_id;
                option.textContent = device.hostname;
                select.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading devices:', error);
        });
}

function loadInterfaces(deviceId) {
    fetch(`{{ url("plugin/WeathermapNG/api/device") }}/${deviceId}/ports`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('interface-select');
            select.innerHTML = '<option value="">Choose an interface...</option>';

            data.ports.forEach(port => {
                const option = document.createElement('option');
                option.value = port.port_id;
                option.textContent = port.ifName;
                select.appendChild(option);
            });

            document.getElementById('interface-container').style.display = 'block';

            select.addEventListener('change', function() {
                selectedInterface = this.value;
            });
        })
        .catch(error => {
            console.error('Error loading interfaces:', error);
        });
}

function addNode() {
    if (!selectedDevice || !selectedInterface) {
        alert('Please select both a device and an interface first.');
        return;
    }

    const deviceSelect = document.getElementById('device-select');
    const interfaceSelect = document.getElementById('interface-select');

    const deviceText = deviceSelect.options[deviceSelect.selectedIndex].text;
    const interfaceText = interfaceSelect.options[interfaceSelect.selectedIndex].text;

    const node = {
        id: 'node' + nodeCounter++,
        deviceId: selectedDevice,
        interfaceId: selectedInterface,
        label: `${deviceText} (${interfaceText})`,
        x: Math.random() * 400 + 100,
        y: Math.random() * 300 + 100
    };

    if (mapId) {
        // Create immediately
        fetch(`{{ url('plugin/WeathermapNG/map') }}/${mapId}/node`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                label: node.label,
                x: node.x,
                y: node.y,
                device_id: node.deviceId || null,
                meta: { interface_id: node.interfaceId || null }
            })
        }).then(r => r.json()).then(d => {
            if (d && d.success && d.node) {
                node.dbId = d.node.id;
            }
            nodes.push(node);
            renderEditor();
        }).catch(() => { nodes.push(node); renderEditor(); });
    } else {
        nodes.push(node);
        renderEditor();
    }

    // Reset selections
    document.getElementById('device-select').value = '';
    document.getElementById('interface-select').value = '';
    document.getElementById('interface-container').style.display = 'none';
    selectedDevice = null;
    selectedInterface = null;
}

function drawNode(node) {
    const canvas = document.getElementById('map-canvas');
    const ctx = canvas.getContext('2d');

    // Draw node circle
    ctx.fillStyle = '#007bff';
    ctx.beginPath();
    ctx.arc(node.x, node.y, 10, 0, 2 * Math.PI);
    ctx.fill();

    // Draw label
    ctx.fillStyle = '#000';
    ctx.font = '12px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(node.label, node.x, node.y - 15);
}

function clearCanvas() {
    if (confirm('Are you sure you want to clear the canvas? This will remove all nodes.')) {
        nodes = [];
        nodeCounter = 1;
        initCanvas();
    }
}

function saveMap() {
    const mapName = document.getElementById('map-name').value.trim();
    const mapTitle = document.getElementById('map-title').value.trim();
    const mapWidth = parseInt(document.getElementById('map-width').value, 10);
    const mapHeight = parseInt(document.getElementById('map-height').value, 10);

    if (!mapName) {
        alert('Please enter a map name.');
        return;
    }

    if (!mapId) {
        // Create then redirect (legacy flow)
        const formData = new FormData();
        formData.append('name', mapName);
        formData.append('title', mapTitle);
        formData.append('width', mapWidth);
        formData.append('height', mapHeight);
        fetch('{{ url("plugin/WeathermapNG/map") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        }).then(() => window.location.href = '{{ url("plugin/WeathermapNG") }}');
        return;
    }

    // Update options + nodes/links via combined save endpoint
    const payload = {
        title: mapTitle,
        options: {
            width: mapWidth,
            height: mapHeight,
        },
        nodes: nodes.map(n => ({
            label: n.label,
            x: n.x,
            y: n.y,
            device_id: n.deviceId || null,
            meta: { interface_id: n.interfaceId || null }
        })),
        links: [] // TODO: populate when link UI is added
    };

    fetch(`{{ url('plugin/WeathermapNG/api/maps') }}/${mapId}/save`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Map saved successfully!');
        } else {
            alert('Error saving map: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => alert('Error saving map: ' + err.message));
}

function exportConfig() {
    const mapTitle = document.getElementById('map-title').value.trim() || 'Network Map';
    const mapWidth = document.getElementById('map-width').value;
    const mapHeight = document.getElementById('map-height').value;

    let config = `[global]\n`;
    config += `width ${mapWidth}\n`;
    config += `height ${mapHeight}\n`;
    config += `title "${mapTitle}"\n\n`;

    nodes.forEach(node => {
        config += `[node:${node.id}]\n`;
        config += `label "${node.label}"\n`;
        config += `x ${Math.round(node.x)}\n`;
        config += `y ${Math.round(node.y)}\n`;
        config += `device_id ${node.deviceId}\n`;
        config += `interface_id ${node.interfaceId}\n`;
        config += `metric traffic_in\n\n`;
    });

    const blob = new Blob([config], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'weathermap.conf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
@endsection
