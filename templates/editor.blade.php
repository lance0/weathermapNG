@extends('layouts.app')

@section('title', 'WeathermapNG - Map Editor')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-edit"></i> Map Editor</h1>
                <div>
                    <a href="{{ url('plugins/weathermapng') }}" class="btn btn-secondary">
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
                                width="{{ config('weathermapng.default_width', 800) }}"
                                height="{{ config('weathermapng.default_height', 600) }}"
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
                            @foreach($devices as $device)
                                <option value="{{ $device->device_id ?? $device['device_id'] }}">
                                    {{ $device->hostname ?? $device['hostname'] }}
                                </option>
                            @endforeach
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
                        <input type="text" class="form-control" id="map-name" placeholder="Enter map name">
                    </div>
                    <div class="mb-3">
                        <label for="map-title" class="form-label">Map Title:</label>
                        <input type="text" class="form-control" id="map-title" placeholder="Enter map title">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label for="map-width" class="form-label">Width:</label>
                            <input type="number" class="form-control" id="map-width"
                                   value="{{ config('weathermapng.default_width', 800) }}" min="100" max="4096">
                        </div>
                        <div class="col-6">
                            <label for="map-height" class="form-label">Height:</label>
                            <input type="number" class="form-control" id="map-height"
                                   value="{{ config('weathermapng.default_height', 600) }}" min="100" max="4096">
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
<script src="{{ asset('plugins/WeathermapNG/js/weathermapng.js') }}"></script>
<script>
let nodes = [];
let selectedDevice = null;
let selectedInterface = null;
let nodeCounter = 1;

document.addEventListener('DOMContentLoaded', function() {
    initCanvas();

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

function loadInterfaces(deviceId) {
    fetch(`{{ url('plugins/weathermapng/api/devices') }}/${deviceId}/interfaces`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('interface-select');
            select.innerHTML = '<option value="">Choose an interface...</option>';

            data.interfaces.forEach(interface => {
                const option = document.createElement('option');
                option.value = interface.id;
                option.textContent = interface.name;
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

    nodes.push(node);
    drawNode(node);

    // Reset selections
    document.getElementById('device-select').value = '';
    document.getElementById('interface-select').value = '';
    document.getElementById('interface-container').style.display = 'none';
    selectedDevice = null;
    selectedInterface = null;
}

function saveMap() {
    const mapName = document.getElementById('map-name').value.trim();
    const mapTitle = document.getElementById('map-title').value.trim();
    const mapWidth = document.getElementById('map-width').value;
    const mapHeight = document.getElementById('map-height').value;

    if (!mapName) {
        alert('Please enter a map name.');
        return;
    }

    if (nodes.length === 0) {
        alert('Please add at least one node to the map.');
        return;
    }

    // Generate config
    let config = `[global]\n`;
    config += `width ${mapWidth}\n`;
    config += `height ${mapHeight}\n`;
    config += `title "${mapTitle || mapName}"\n\n`;

    nodes.forEach(node => {
        config += `[node:${node.id}]\n`;
        config += `label "${node.label}"\n`;
        config += `x ${Math.round(node.x)}\n`;
        config += `y ${Math.round(node.y)}\n`;
        config += `device_id ${node.deviceId}\n`;
        config += `interface_id ${node.interfaceId}\n`;
        config += `metric traffic_in\n\n`;
    });

    // Save via API
    fetch('{{ url("plugins/weathermapng/api/map") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            name: mapName,
            title: mapTitle,
            width: mapWidth,
            height: mapHeight,
            config: config
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Map saved successfully!');
            window.location.href = '{{ url("plugins/weathermapng") }}';
        } else {
            alert('Error saving map: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error saving map: ' + error.message);
    });
}

function exportConfig() {
    const config = generateConfig();
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

function generateConfig() {
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

    return config;
}
</script>
@endsection