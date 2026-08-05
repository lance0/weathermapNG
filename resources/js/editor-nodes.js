/**
 * WeathermapNG editor — node editing.
 *
 * Everything about creating, selecting, editing, duplicating and deleting
 * nodes: the add-node device autocomplete glue, the node properties panel in
 * the sidebar, the node list, and the per-node server calls (save/delete).
 *
 * Shared mutable state is read/written through the `S` alias for
 * `window.WMNG.EditorState` (see editor-state.js). Cross-module helpers and
 * render functions are global function declarations and resolve at runtime.
 */
var S = window.WMNG.EditorState;

function addNode() {
    if (!S.canvas) return;
    saveState(); // Save for undo

    const deviceSelect = document.getElementById('device-select');
    const interfaceSelect = document.getElementById('interface-select');
    const deviceId = deviceSelect?.value ? parseInt(deviceSelect.value, 10) : null;
    const interfaceId = interfaceSelect?.value ? parseInt(interfaceSelect.value, 10) : null;
    const device = S.acSelectedDevice || S.devicesCache.find(d => d.device_id === deviceId);
    const label = device?.hostname || device?.sysName || `Node ${S.nodes.length + 1}`;

    // Smart placement: spiral outward from center to avoid overlap
    const existingCount = S.nodes.length;
    const spacing = 60;
    const angle = existingCount * 0.8; // Golden angle approximation
    const radius = Math.sqrt(existingCount) * spacing;
    let x = S.canvas.width / 2 + Math.cos(angle) * radius;
    let y = S.canvas.height / 2 + Math.sin(angle) * radius;

    // Constrain to canvas bounds
    const nodeRadius = 12;
    x = Math.max(nodeRadius, Math.min(S.canvas.width - nodeRadius, x));
    y = Math.max(nodeRadius, Math.min(S.canvas.height - nodeRadius, y));

    const newNode = {
        id: `node-${Date.now()}`,
        dbId: null,
        label: label,
        x: x,
        y: y,
        deviceId: deviceId,
        deviceName: device ? deviceName(device) : null,
        status: device ? (device.status || null) : null,
        interfaceId: interfaceId,
    };

    S.nodes.push(newNode);
    S.selectedNode = newNode;
    renderEditor();
    renderLinksList();
    populateNodeProperties(newNode);
}

function populateNodeProperties(node) {
    const card = document.getElementById('node-properties-card');
    const label = document.getElementById('node-prop-label');

    if (!node) {
        if (card) card.style.display = 'none';
        return;
    }

    // Show card and enable inputs
    if (card) card.style.display = 'block';

    // Populate label
    if (label) {
        label.value = node.label || '';
        label.oninput = function () {
            node.label = this.value;
            renderEditor();
            renderNodesList();
            markUnsaved();
        };
    }

    // Populate device display + Change button
    const devName = document.getElementById('node-prop-device-name');
    const devHidden = document.getElementById('node-prop-device');
    const devWrap = document.getElementById('node-device-ac-wrap');
    if (devHidden) {
        devHidden.value = node.deviceId || '';
        if (devName) devName.textContent = node.deviceName || (node.deviceId ? `Device ${node.deviceId}` : 'No device');
        // Hide any open autocomplete from a previous node
        if (devWrap) devWrap.style.display = 'none';
    }

    // Show/hide View Device button based on current selection
    const viewBtn = document.getElementById('node-view-device-btn');
    if (viewBtn) viewBtn.style.display = node.deviceId ? 'inline-block' : 'none';
}

function openNodeDeviceAutocomplete() {
    if (!S.selectedNode) return;
    const wrap = document.getElementById('node-device-ac-wrap');
    const search = document.getElementById('node-device-search');
    if (!wrap || !search) return;
    wrap.style.display = 'block';
    search.value = '';
    search.focus();
    // Lazy-init the autocomplete once
    if (!search.dataset.acReady) {
        search.dataset.acReady = '1';
        initDeviceAutocomplete('node-device-search', 'node-device-ac-results', function (device) {
            S.selectedNode.deviceId = device.device_id;
            S.selectedNode.deviceName = deviceName(device);
            S.selectedNode.status = device.status || null;
            S.selectedNode.interfaceId = null;
            const devHidden = document.getElementById('node-prop-device');
            const devName = document.getElementById('node-prop-device-name');
            if (devHidden) devHidden.value = device.device_id;
            if (devName) devName.textContent = deviceName(device);
            renderEditor();
            renderNodesList();
            markUnsaved();
            loadInterfacesForNode(S.selectedNode);
            const _vbtn = document.getElementById('node-view-device-btn');
            if (_vbtn) _vbtn.style.display = 'inline-block';
        });
    }
}

function loadInterfacesForNode(node) {
    const intSel = document.getElementById('node-prop-interface');
    if (!intSel) return;

    intSel.innerHTML = '<option value="">No interface</option>';
    intSel.onchange = function () {
        node.interfaceId = this.value ? parseInt(this.value, 10) : null;
        renderEditor();
        renderNodesList();
        markUnsaved();
    };
    if (!node.deviceId) return;

    fetch(S.uris.device + '/' + node.deviceId + '/ports')
        .then(r => {
            if (!r.ok) { console.warn('Failed to load interfaces: HTTP ' + r.status); return { ports: [] }; }
            return r.json();
        })
        .then(data => {
            (data.ports || []).forEach(port => {
                const opt = document.createElement('option');
                opt.value = port.port_id;
                opt.textContent = port.ifName || `Port ${port.port_id}`;
                if (node.interfaceId == port.port_id) opt.selected = true;
                intSel.appendChild(opt);
            });
        });
}

function saveSelectedNode() {
    if (!S.selectedNode || !S.mapId || !S.selectedNode.dbId) return;
    const label = document.getElementById('node-prop-label').value.trim();
    const deviceId = document.getElementById('node-prop-device').value || null;
    const ifaceId = document.getElementById('node-prop-interface').value || null;
    const payload = { label: label, device_id: deviceId ? parseInt(deviceId, 10) : null, meta: { interface_id: ifaceId ? parseInt(ifaceId, 10) : null } };
    fetch(S.uris.map + '/' + S.mapId + '/node/' + S.selectedNode.dbId, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }, body: JSON.stringify(payload)
    }).then(r => {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
        }
        return r.json();
    }).then(d => {
        if (d.success) {
            S.selectedNode.label = label;
            S.selectedNode.deviceId = payload.device_id;
            S.selectedNode.interfaceId = payload.meta.interface_id;
            renderEditor();
        } else {
            WMNGToast.error('Failed to save node: ' + (d.message || 'Unknown error'), { duration: 3000 });
        }
    });
}

function viewSelectedNodeDevice() {
    if (!S.selectedNode || !S.selectedNode.deviceId) return;
    window.open(S.uris.devicePage + '/' + S.selectedNode.deviceId, '_blank');
}

function deleteSelectedNode() {
    if (!S.selectedNode) return;
    const nodeToDelete = S.selectedNode;
    const nodeId = nodeToDelete.id || nodeToDelete.dbId;

    showEditorConfirm(
        'Delete Node',
        'Delete this node and attached links? This can be undone with the editor undo history.',
        'Delete Node',
        'btn-danger',
        function () {
            saveState(); // Save for undo

            // Helper to clean up after deletion
            function finishDelete() {
                S.nodes = S.nodes.filter(n => n !== nodeToDelete);
                S.links = S.links.filter(l => l.srcId !== nodeId && l.dstId !== nodeId && l.srcId !== nodeToDelete.dbId && l.dstId !== nodeToDelete.dbId);
                S.selectedNode = null;
                populateNodeProperties(null);
                renderEditor();
                renderLinksList();
            }

            // If node is saved in DB, delete from server
            if (S.mapId && nodeToDelete.dbId) {
                fetch(S.uris.map + '/' + S.mapId + '/node/' + nodeToDelete.dbId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                }).then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                    }
                    return r.json().catch(() => ({}));
                }).then(data => {
                    if (data.success === false) {
                        throw new Error(data.message || 'Server refused to delete node');
                    }
                    finishDelete();
                }).catch(err => {
                    WMNGToast.error('Failed to delete node: ' + err.message, { duration: 3000 });
                });
            } else {
                // Node only exists locally
                finishDelete();
            }
        }
    );
}

// ========== Nodes List ==========
function renderNodesList() {
    const container = document.getElementById('nodes-list');
    if (!container) return;

    container.textContent = '';

    if (!S.nodes.length) {
        const empty = document.createElement('small');
        empty.className = 'text-muted';
        empty.textContent = 'No nodes yet';
        container.appendChild(empty);
        return;
    }

    S.nodes.forEach((node, idx) => {
        const isSelected = S.selectedNodes.indexOf(node) >= 0;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between py-1 node-list-item' + (isSelected ? ' selected' : '');
        row.addEventListener('click', () => selectNodeByIndex(idx));

        const label = document.createElement('small');
        if (isSelected) label.classList.add('font-weight-bold');

        const dot = document.createElement('i');
        dot.className = 'fas fa-circle node-dot ' + (isSelected ? 'text-primary' : 'text-success');
        label.appendChild(dot);
        label.appendChild(document.createTextNode(' ' + (node.label || 'Node ' + (idx + 1))));
        row.appendChild(label);

        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-outline-danger btn-sm py-0 px-1';
        delBtn.title = 'Delete';
        delBtn.addEventListener('click', (e) => { e.stopPropagation(); deleteNodeByIndex(idx); });
        const delIcon = document.createElement('i');
        delIcon.className = 'fas fa-times node-delete-icon';
        delBtn.appendChild(delIcon);
        row.appendChild(delBtn);

        container.appendChild(row);
    });
}

function selectNodeByIndex(idx, additive) {
    if (idx >= 0 && idx < S.nodes.length) {
        const node = S.nodes[idx];
        if (additive) {
            const i = S.selectedNodes.indexOf(node);
            if (i >= 0) {
                S.selectedNodes.splice(i, 1);
            } else {
                S.selectedNodes.push(node);
            }
            S.selectedNode = S.selectedNodes[S.selectedNodes.length - 1] || null;
        } else {
            S.selectedNodes = [node];
            S.selectedNode = node;
        }
        populateNodeProperties(S.selectedNode);
        updateToolbarState();
        renderEditor();
        renderNodesList();
    }
}

function deleteNodeByIndex(idx) {
    if (idx >= 0 && idx < S.nodes.length) {
        const node = S.nodes[idx];
        showEditorConfirm(
            'Delete Node',
            `Delete node "${node.label || 'Node ' + (idx + 1)}"? This can be undone with the editor undo history.`,
            'Delete Node',
            'btn-danger',
            function () {
                saveState();

                const nodeId = node.id || node.dbId;
                S.nodes.splice(idx, 1);
                S.links = S.links.filter(l => l.srcId !== nodeId && l.dstId !== nodeId && l.srcId !== node.dbId && l.dstId !== node.dbId);

                if (S.selectedNode === node) {
                    S.selectedNode = null;
                    populateNodeProperties(null);
                }

                markUnsaved();
                renderEditor();
                renderNodesList();
                renderLinksList();
                updateStatusCounts();
                updateToolbarState();
            }
        );
    }
}

// ========== Duplicate Node ==========
function duplicateSelectedNode() {
    if (!S.selectedNode) return;
    saveState();

    const newNode = {
        id: `node-${Date.now()}`,
        dbId: null,
        label: S.selectedNode.label + ' (copy)',
        x: Math.min(S.canvas.width - 12, S.selectedNode.x + 30),
        y: Math.min(S.canvas.height - 12, S.selectedNode.y + 30),
        deviceId: S.selectedNode.deviceId,
        interfaceId: S.selectedNode.interfaceId,
    };

    S.nodes.push(newNode);
    S.selectedNode = newNode;

    markUnsaved();
    renderEditor();
    renderNodesList();
    populateNodeProperties(newNode);
    updateStatusCounts();
    updateToolbarState();
    WMNGToast.success('Node duplicated', { duration: 1500 });
}