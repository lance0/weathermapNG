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
const NODE_STYLE_META_KEYS = ['color', 'label_color', 'shape', 'size'];

/**
 * Nodes loaded from older/imported rows can carry `meta: []` (PHP encodes an
 * empty PHP array as a JSON list). Writing named style keys onto that array
 * would serialize as "[]" and silently drop them, so every read/write path
 * coerces non-object meta to a plain object first.
 */
function nodeMetaObject(node) {
    if (!node) return {};
    if (!node.meta || typeof node.meta !== 'object' || Array.isArray(node.meta)) {
        node.meta = {};
    }
    return node.meta;
}

const NODE_STYLE_HEX_PATTERN = /^#[0-9a-fA-F]{6}$/;
// One undo snapshot per continuous style gesture (e.g. dragging the color
// picker fires many `input` events); reset on change/selection switch.
let activeNodeStyleEdit = null;

/** Effective default colors used when a node has no per-node override. */
function getEffectiveNodeStyleDefaults() {
    const defaultNodeStyle = getDefaultNodeStyle();
    const isDarkTheme = document.querySelector('.editor-container.dark-theme') !== null;
    return {
        color: defaultNodeStyle.color || '#28a745',
        label_color: defaultNodeStyle.label_color || (isDarkTheme ? '#f8f9fa' : '#212529'),
    };
}

/**
 * Apply a single style meta edit to the anchor node: snapshot for undo,
 * update the node, mark the map dirty and re-render. An empty value removes
 * the override so the node falls back to its device/default style.
 */
function updateNodeStyle(node, key, value) {
    if (!node) return;
    const editToken = node.id + ':' + key;
    if (activeNodeStyleEdit !== editToken) {
        saveState();
        activeNodeStyleEdit = editToken;
    }
    const meta = nodeMetaObject(node);
    if (value === null || value === undefined || value === '') {
        delete meta[key];
    } else {
        meta[key] = value;
    }
    markUnsaved();
    renderEditor();
    renderNodesList();
    updateApplyStyleButton();
}

function updateNodeSize(node, rawValue) {
    if (!node) return null;
    if (rawValue === '') {
        updateNodeStyle(node, 'size', null);
        return null;
    }
    const size = parseFloat(rawValue);
    if (!isFinite(size)) return null;
    const clamped = Math.max(6, Math.min(40, size));
    updateNodeStyle(node, 'size', clamped);
    return clamped;
}

/** Populate the Style group from the selected node (and rebind handlers). */
function populateNodeStyleControls(node) {
    const color = document.getElementById('node-prop-color');
    const labelColor = document.getElementById('node-prop-label-color');
    const shape = document.getElementById('node-prop-shape');
    const size = document.getElementById('node-prop-size');
    const resetBtn = document.getElementById('node-prop-style-reset-btn');

    if (!node) {
        // No selection: the controls must not accept edits that would be
        // silently discarded (they used to stay enabled and show the change).
        [color, labelColor, shape, size, resetBtn].forEach(el => {
            if (!el) return;
            el.disabled = true;
            el.oninput = null;
            el.onchange = null;
        });
        updateApplyStyleButton();
        return;
    }

    [color, labelColor, shape, size, resetBtn].forEach(el => { if (el) el.disabled = false; });

    const meta = nodeMetaObject(node);
    const defaults = getEffectiveNodeStyleDefaults();
    activeNodeStyleEdit = null;

    if (color) {
        color.value = (typeof meta.color === 'string' && NODE_STYLE_HEX_PATTERN.test(meta.color))
            ? meta.color : defaults.color;
        color.oninput = function () { updateNodeStyle(node, 'color', this.value); };
        color.onchange = function () { activeNodeStyleEdit = null; };
    }
    if (labelColor) {
        labelColor.value = (typeof meta.label_color === 'string' && NODE_STYLE_HEX_PATTERN.test(meta.label_color))
            ? meta.label_color : defaults.label_color;
        labelColor.oninput = function () { updateNodeStyle(node, 'label_color', this.value); };
        labelColor.onchange = function () { activeNodeStyleEdit = null; };
    }
    if (shape) {
        shape.value = NODE_STYLE_SHAPES.indexOf(meta.shape) >= 0 ? meta.shape : '';
        shape.onchange = function () {
            updateNodeStyle(node, 'shape', this.value || null);
            activeNodeStyleEdit = null;
        };
    }
    if (size) {
        const storedSize = Number(meta.size);
        // Only show a value the renderer actually honours; stale out-of-range
        // values (0, 45) render at the default radius and must not look active.
        size.value = (meta.size !== undefined && meta.size !== null && isFinite(storedSize)
            && storedSize >= 6 && storedSize <= 40) ? storedSize : '';
        size.oninput = function () { updateNodeSize(node, this.value); };
        size.onchange = function () {
            // Commit the clamped value so the box never disagrees with the model.
            const clamped = updateNodeSize(node, this.value);
            if (clamped !== null && Number(this.value) !== clamped) this.value = String(clamped);
            activeNodeStyleEdit = null;
        };
    }

    updateApplyStyleButton();
}

/** Drop every per-node style override, restoring device/default rendering. */
function resetSelectedNodeStyle() {
    const node = S.selectedNode;
    if (!node) return;
    const meta = nodeMetaObject(node);
    const hadStyle = NODE_STYLE_META_KEYS.some(key => Object.prototype.hasOwnProperty.call(meta, key));
    if (hadStyle) {
        saveState();
        NODE_STYLE_META_KEYS.forEach(key => { delete meta[key]; });
        markUnsaved();
        populateNodeProperties(node);
        renderEditor();
        renderNodesList();
    }
}

/**
 * Copy the anchor node's full style (color/label_color/shape/size) onto every
 * other selected node in one undo step. Missing anchor keys clear the target
 * override so all selected nodes match the anchor.
 */
function applyStyleToSelected() {
    const anchor = S.selectedNode;
    if (!anchor || S.selectedNodes.length <= 1) return;

    const anchorMeta = nodeMetaObject(anchor);
    const style = {};
    NODE_STYLE_META_KEYS.forEach(key => {
        if (anchorMeta[key] !== undefined && anchorMeta[key] !== null && anchorMeta[key] !== '') {
            style[key] = anchorMeta[key];
        }
    });

    saveState();
    S.selectedNodes.forEach(node => {
        const meta = nodeMetaObject(node);
        NODE_STYLE_META_KEYS.forEach(key => {
            if (Object.prototype.hasOwnProperty.call(style, key)) {
                meta[key] = style[key];
            } else {
                delete meta[key];
            }
        });
    });

    markUnsaved();
    renderEditor();
    renderNodesList();
    updateApplyStyleButton();
    activeNodeStyleEdit = null;
    WMNGToast.success('Style applied to ' + S.selectedNodes.length + ' nodes', { duration: 1500 });
}

function addNode() {
    if (!S.canvas) return;
    saveState(); // Save for undo

    const deviceSelect = document.getElementById('device-select');
    const interfaceSelect = document.getElementById('interface-select');
    const deviceId = deviceSelect?.value ? parseInt(deviceSelect.value, 10) : null;
    const interfaceId = interfaceSelect?.value ? parseInt(interfaceSelect.value, 10) : null;
    const device = S.acSelectedDevice || S.devicesCache.find(d => d.device_id === deviceId);
    const label = device?.hostname || device?.sysName || `Node ${S.nodes.length + 1}`;

    // Smart placement: spiral outward from viewport center to avoid overlap
    // and ensure the new node is visible (not culled off-screen after pan).
    const vcx = (S.mapWidth / 2 - S.viewOffsetX) / S.viewScale;
    const vcy = (S.mapHeight / 2 - S.viewOffsetY) / S.viewScale;
    const existingCount = S.nodes.length;
    const spacing = 60 / S.viewScale; // keep visual spacing consistent
    const angle = existingCount * 0.8; // Golden angle approximation
    const radius = Math.sqrt(existingCount) * spacing;
    let x = vcx + Math.cos(angle) * radius;
    let y = vcy + Math.sin(angle) * radius;

    // Constrain to map bounds
    const nodeRadius = 12;
    x = Math.max(nodeRadius, Math.min(S.mapWidth - nodeRadius, x));
    y = Math.max(nodeRadius, Math.min(S.mapHeight - nodeRadius, y));

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
    S.selectedNodes = [newNode];
    renderEditor();
    renderLinksList();
    populateNodeProperties(newNode);
}

function populateNodeProperties(node) {
    const card = document.getElementById('node-properties-card');
    const label = document.getElementById('node-prop-label');

    if (!node) {
        if (card) card.style.display = 'none';
        populateNodeStyleControls(null);
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

    populateNodeStyleControls(node);
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
    // Capture the node being saved: the user can select another node before the
    // PATCH resolves, and the response must not be applied to the new selection
    // (that used to overwrite its meta and label).
    const node = S.selectedNode;
    const label = document.getElementById('node-prop-label').value.trim();
    const deviceId = document.getElementById('node-prop-device').value || null;
    const ifaceId = document.getElementById('node-prop-interface').value || null;
    const payload = {
        label: label,
        device_id: deviceId ? parseInt(deviceId, 10) : null,
        // Merge the loaded meta so unknown keys (style overrides, sub_map_id,
        // tooltip, ...) survive the PATCH — the server replaces meta wholesale.
        meta: { ...nodeMetaObject(node), interface_id: ifaceId ? parseInt(ifaceId, 10) : null },
    };
    fetch(S.uris.map + '/' + S.mapId + '/node/' + node.dbId, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }, body: JSON.stringify(payload)
    }).then(r => {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
        }
        return r.json();
    }).then(d => {
        if (!d.success) {
            WMNGToast.error('Failed to save node: ' + (d.message || 'Unknown error'), { duration: 3000 });
            return;
        }
        // The node may have been deleted or undone in the meantime.
        if (S.nodes.indexOf(node) < 0) return;
        node.label = label;
        node.deviceId = payload.device_id;
        node.interfaceId = payload.meta.interface_id;
        node.meta = payload.meta;
        if (S.selectedNode === node) populateNodeProperties(node);
        renderEditor();
        renderNodesList();
    }).catch(err => {
        WMNGToast.error('Failed to save node: ' + err.message, { duration: 3000 });
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
                S.selectedNodes = [];
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

function selectAllNodes() {
    if (!S.nodes.length) return;
    S.selectedNodes = S.nodes.slice();
    S.selectedNode = S.nodes[0];
    populateNodeProperties(S.selectedNode);
    updateToolbarState();
    renderNodesList();
    renderEditor();
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
                S.selectedNodes = S.selectedNodes.filter(n => n !== node);
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
        x: Math.min(S.mapWidth - getNodeRadius(S.selectedNode), S.selectedNode.x + 30),
        y: Math.min(S.mapHeight - getNodeRadius(S.selectedNode), S.selectedNode.y + 30),
        deviceId: S.selectedNode.deviceId,
        interfaceId: S.selectedNode.interfaceId,
        meta: JSON.parse(JSON.stringify(nodeMetaObject(S.selectedNode))),
    };

    S.nodes.push(newNode);
    S.selectedNode = newNode;
    S.selectedNodes = [newNode];

    markUnsaved();
    renderEditor();
    renderNodesList();
    populateNodeProperties(newNode);
    updateStatusCounts();
    updateToolbarState();
    WMNGToast.success('Node duplicated', { duration: 1500 });
}