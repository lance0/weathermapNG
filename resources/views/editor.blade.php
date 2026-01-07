@extends('layouts.librenmsv1')

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
                        <button class="btn btn-outline-primary" onclick="openVersionSaveModal()">
                            <i class="fas fa-code-branch"></i> Save Version
                        </button>
                        <button class="btn btn-info" onclick="openVersionHistory()">
                            <i class="fas fa-history"></i> Versions
                        </button>
                        <button class="btn btn-success" onclick="saveMap()">
                            <i class="fas fa-save"></i> Save Map
                        </button>
                    </div>
                </div>
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
                        <div id="link-tooltip" style="position:absolute; display:none; background: rgba(0,0,0,0.8); color:#fff; padding:6px 8px; border-radius:4px; font-size:12px;"></div>
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

<!-- Link Configuration Modal -->
<div class="modal fade" id="linkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Link</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Source Port</label>
                    <select id="link-src-port" class="form-select"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Destination Port</label>
                    <select id="link-dst-port" class="form-select"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bandwidth (bps)</label>
                    <input type="number" id="link-bandwidth" class="form-control" min="0" placeholder="e.g. 1000000000 for 1Gbps">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="delete-link-btn" style="display:none;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-link-btn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version Save Modal -->
        <div class="modal fade" id="versionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Version</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="version-name" class="form-label">Version Name</label>
                            <input type="text" class="form-control" id="version-name"
                                   placeholder="e.g. Experiment 1" maxlength="100">
                            <small class="form-text text-muted">Max 100 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="version-desc"
                                      placeholder="What did you change in this version?" rows="3" maxlength="1000"></textarea>
                            <small class="form-text text-muted">Max 1000 characters</small>
                        </div>
                        <div class="form-check mb-3">
                            <label>
                                <input type="checkbox" id="auto-save" checked>
                                <span class="ml-2">Auto-save every 5 minutes</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveVersion()">
                            <i class="fas fa-save"></i> Save Version
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version History Modal -->
        <div class="modal fade" id="versionHistoryModal" tabindex="-1">
            <div class="modal-dialog" style="max-width: 800px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Version History</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="version-list" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center text-muted py-3">
                                <div class="spinner-border-custom text-primary" style="width: 2rem; height: 2rem;"></div>
                                <small>Loading versions...</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearOldVersions()">
                            <i class="fas fa-trash-alt"></i> Clear Old
                        </button>
                        <button type="button" class="btn btn-info" onclick="exportVersions()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @endsection

        @section('scripts')
        <script src="{{ asset('plugins/WeathermapNG/resources/js/ui-helpers.js') }}"></script>
        <script>
            let mapId = {{ $map->id ?? 'null' }};
            let nodes = [];
            let links = [];
            let selectedNode = null;
            let devicesCache = [];
            
            document.addEventListener('DOMContentLoaded', function() {
                initCanvas();
                if (mapId) loadMapData(mapId);
                loadDevices();
            });

            function saveMap() {
                WMNGLoading.show('Saving map...');
                
                const mapName = document.getElementById('map-name').value.trim();
                const mapTitle = document.getElementById('map-title').value.trim();
                const mapWidth = parseInt(document.getElementById('map-width').value);
                const mapHeight = parseInt(document.getElementById('map-height').value);
                
                if (!mapName) {
                    WMNGLoading.hide();
                    WMNGToast.error('Please enter a map name', { duration: 3000 });
                    return;
                }
                
                const formData = new FormData();
                formData.append('name', mapName);
                if (mapTitle) formData.append('title', mapTitle);
                formData.append('width', mapWidth);
                formData.append('height', mapHeight);
                
                fetch('{{ url('plugin/WeathermapNG/map') }}' + '/' + mapId, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Map saved successfully!', { duration: 3000 });
                    } else {
                        WMNGToast.error('Failed to save map: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error saving map: ' + error.message, { duration: 3000 });
                });
            }

            function openVersionSaveModal() {
                $('#versionModal').modal('show');
            }

            function saveVersion() {
                const versionName = document.getElementById('version-name').value.trim();
                const versionDesc = document.getElementById('version-desc').value.trim();
                const autoSave = document.getElementById('auto-save').checked;
                
                WMNGLoading.show('Saving version...');
                
                const formData = new FormData();
                formData.append('name', versionName);
                formData.append('description', versionDesc);
                formData.append('auto_save', autoSave ? '1' : '0');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version saved successfully!', { duration: 3000 });
                        $('#versionModal').modal('hide');
                    } else {
                        WMNGToast.error('Failed to save version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error saving version: ' + error.message, { duration: 3000 });
                });
            }

            function openVersionHistory() {
                $('#versionHistoryModal').modal('show');
                loadVersions();
            }

            function loadVersions() {
                WMNGLoading.show('Loading versions...');
                
                const versionList = document.getElementById('version-list');
                versionList.innerHTML = '<div class="spinner-border-custom text-primary" style="width: 2rem; height: 2rem;"></div><small>Loading versions...</small>';
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions', {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.versions)) {
                        const versions = data.versions;
                        let html = '';
                        
                        versions.forEach((version, idx) => {
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>v${versions.length - idx}</strong>
                                            <small class="text-muted">
                                                ${version.created_at_human}
                                                by ${version.created_by || 'Unknown'}
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="restoreVersion(${version.id})">
                                            <i class="fas fa-undo"></i> Restore
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteVersion(${version.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <button class="btn btn-sm btn-outline-info" onclick="compareVersion(${version.id})">
                                        <i class="fas fa-code-compare"></i> Compare
                                    </button>
                                </div>
                                <hr>
                            `;
                        });
                        
                        versionList.innerHTML = html || '<div class="text-center text-muted py-3">No versions saved yet. <small>Save your first version to start tracking.</small></div>';
                    } else {
                        versionList.innerHTML = '<div class="text-center text-muted py-3">No versions found</div>';
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    versionList.innerHTML = '<div class="text-center text-danger py-3">Error loading versions: ' + error.message + '</div>';
                })
                .finally(() => {
                    WMNGLoading.hide();
                });
            }

            function restoreVersion(versionId) {
                if (!confirm('Restore to this version? Current changes will be lost.')) {
                    return;
                }
                
                WMNGLoading.show('Restoring version...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/' + versionId + '/restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version restored successfully!', { duration: 3000 });
                        $('#versionHistoryModal').modal('hide');
                        
                        if (data.snapshot) {
                            const snapshot = JSON.parse(data.snapshot);
                            if (snapshot && Array.isArray(snapshot.nodes)) {
                                nodes = snapshot.nodes;
                            }
                            if (snapshot && Array.isArray(snapshot.links)) {
                                links = snapshot.links;
                            }
                            if (snapshot && snapshot.map) {
                                const mapData = snapshot.map;
                                if (mapData.title) document.getElementById('map-title').value = mapData.title;
                                if (mapData.width) document.getElementById('map-width').value = mapData.width;
                                if (mapData.height) document.getElementById('map-height').value = mapData.height;
                            }
                        }
                        
                        renderEditor();
                    } else {
                        WMNGToast.error('Failed to restore version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error restoring version: ' + error.message, { duration: 3000 });
                });
            }

            function deleteVersion(versionId) {
                if (!confirm('Delete this version permanently? This cannot be undone.')) {
                    return;
                }
                
                WMNGLoading.show('Deleting version...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/' + versionId, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Version deleted successfully!', { duration: 3000 });
                        loadVersions();
                    } else {
                        WMNGToast.error('Failed to delete version: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error deleting version: ' + error.message, { duration: 3000 });
                });
            }

            function clearOldVersions() {
                if (!confirm('Delete all but the latest 20 versions?')) {
                    return;
                }
                
                WMNGLoading.show('Clearing old versions...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/cleanup', {
                    method: 'POST'
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        const count = data.deleted_count || 0;
                        WMNGToast.success('Cleared ' + count + ' old versions!', { duration: 3000 });
                        loadVersions();
                    } else {
                        WMNGToast.error('Failed to clear versions: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error clearing versions: ' + error.message, { duration: 3000 });
                });
            }

            function compareVersion(versionId) {
                const compareId = prompt('Enter version ID to compare against:', '0');
                if (!compareId) return;
                
                alert('Compare feature coming soon! Select two versions from the history to compare.');
            }

            function exportVersions() {
                WMNGLoading.show('Exporting versions...');
                
                fetch('{{ url('plugin/WeathermapNG/maps') }}' + '/' + mapId + '/versions/export', {
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    WMNGLoading.hide();
                    if (data.success) {
                        WMNGToast.success('Versions exported successfully!', { duration: 3000 });
                        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'weathermap-versions-' + mapId + '.json';
                        a.click();
                    } else {
                        WMNGToast.error('Failed to export versions: ' + (data.message || 'Unknown error'), { duration: 3000 });
                    }
                })
                .catch(error => {
                    WMNGLoading.hide();
                    WMNGToast.error('Error exporting versions: ' ' + error.message, { duration: 3000 });
                });
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
        links: links.map(l => ({
            src_node_id: l.srcId,
            dst_node_id: l.dstId,
            port_id_a: l.portA || null,
            port_id_b: l.portB || null,
            bandwidth_bps: l.bw || null,
            style: l.style || {}
        }))
    };

    fetch('{{ url('plugin/WeathermapNG/api/maps') }}' + '/' + mapId + '/save', {
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

function exportJson() {
    if (!mapId) { alert('Save the map first.'); return; }
    const url = '{{ url('plugin/WeathermapNG/api/maps') }}' + '/' + mapId + '/export?format=json';
    window.open(url, '_blank');
}

function populateNodeProperties(node) {
    const label = document.getElementById('node-prop-label');
    const devSel = document.getElementById('node-prop-device');
    const intSel = document.getElementById('node-prop-interface');
    const saveBtn = document.getElementById('node-prop-save');
    const delBtn = document.getElementById('node-prop-delete');
    [label, devSel, intSel, saveBtn, delBtn].forEach(el => el.disabled = false);
    label.value = node.label || '';
    devSel.value = node.deviceId || '';
    // load interfaces if device set
    intSel.innerHTML = '<option value="">No interface</option>';
    if (node.deviceId) {
        fetch('{{ url('plugin/WeathermapNG/api/device') }}' + '/' + node.deviceId + '/ports')
            .then(r => r.json()).then(d => {
                (d.ports || []).forEach(p => {
                    const o = document.createElement('option');
                    o.value = p.port_id; o.text = p.ifName;
                    if (node.interfaceId && node.interfaceId == p.port_id) o.selected = true;
                    intSel.appendChild(o);
                });
            });
    }
}

function saveSelectedNode() {
    if (!selectedNode || !mapId || !selectedNode.dbId) return;
    const label = document.getElementById('node-prop-label').value.trim();
    const deviceId = document.getElementById('node-prop-device').value || null;
    const ifaceId = document.getElementById('node-prop-interface').value || null;
    const payload = { label: label, device_id: deviceId ? parseInt(deviceId, 10) : null, meta: { interface_id: ifaceId ? parseInt(ifaceId, 10) : null } };
    fetch('{{ url('plugin/WeathermapNG/map') }}' + '/' + mapId + '/node/' + selectedNode.dbId, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            selectedNode.label = label;
            selectedNode.deviceId = payload.device_id;
            selectedNode.interfaceId = payload.meta.interface_id;
            renderEditor();
        } else {
            alert('Failed to save node: ' + (d.message || 'Unknown error'));
        }
    });
}

function deleteSelectedNode() {
    if (!selectedNode || !mapId || !selectedNode.dbId) return;
    if (!confirm('Delete this node and attached links?')) return;
    fetch('{{ url('plugin/WeathermapNG/map') }}' + '/' + mapId + '/node/' + selectedNode.dbId, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    }).then(() => {
        nodes = nodes.filter(n => n !== selectedNode);
        links = links.filter(l => l.srcId !== selectedNode.dbId && l.dstId !== selectedNode.dbId);
        selectedNode = null;
        ['node-prop-label','node-prop-device','node-prop-interface','node-prop-save','node-prop-delete'].forEach(id => document.getElementById(id).disabled = true);
        renderEditor();
    });
}

function renderLinksList() {
    const c = document.getElementById('links-list');
    if (!c) return;
    if (!links.length) { c.innerHTML = '<small class="text-muted">No links yet</small>'; return; }
    const item = (l, idx) => {
        const a = findNodeById(l.srcId); const b = findNodeById(l.dstId);
        const aL = a ? a.label : l.srcId; const bL = b ? b.label : l.dstId;
        return `<div class=\"d-flex align-items-center justify-content-between mb-2\">
            <div><i class=\"fas fa-link\"></i> ${aL} → ${bL}</div>
            <div class=\"btn-group btn-group-sm\">
                <button class=\"btn btn-outline-secondary\" onclick=\"openLinkModal(links[${idx}])\"><i class=\"fas fa-edit\"></i></button>
                <button class=\"btn btn-outline-danger\" onclick=\"deleteLink(links[${idx}])\"><i class=\"fas fa-trash\"></i></button>
            </div>
        </div>`
    };
    c.innerHTML = links.map((l,i) => item(l,i)).join('');
}
</script>
@endsection
