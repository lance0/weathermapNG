/**
 * WeathermapNG editor — data layer and UI chrome.
 *
 * The editor "core": loading and saving the map, undo/redo, keyboard
 * shortcuts, the device autocomplete glue, toolbar / sidebar state
 * (status counts, unsaved indicator, toolbar buttons), default-style
 * preview, and canvas resize validation. It also registers the page-wide
 * DOMContentLoaded bootstrap that drives the canvas, node and link modules.
 *
 * Shared mutable state is read/written through the `S` alias for
 * `window.WMNG.EditorState` (see editor-state.js). Rendering / node / link
 * helpers are global function declarations in their own modules and resolve
 * at runtime.
 */
var S = window.WMNG.EditorState;
const MAX_UNDO = 50;

function parseMapTags(raw) {
    if (typeof raw !== 'string' || !raw.trim()) return [];
    const tags = raw.split(',')
        .map(t => t.trim().toLowerCase())
        .filter(t => /^[a-z0-9_-]+$/.test(t));
    return Array.from(new Set(tags));
}

function renderMapTagsPreview(tags) {
    const el = document.getElementById('map-tags-preview');
    if (!el) return;
    if (!Array.isArray(tags) || tags.length === 0) {
        el.innerHTML = '';
        return;
    }
    el.innerHTML = tags.map(t => `<span class="editor-tag">${escapeHtml(t)}</span>`).join(' ');
}

document.getElementById('map-tags')?.addEventListener('input', (e) => {
    renderMapTagsPreview(parseMapTags(e.target.value));
});

function getDefaultNodeStyle() {
    const colorInput = document.getElementById('default-node-color');
    const labelColorInput = document.getElementById('default-node-label-color');
    const style = {};
    if (colorInput && /^#[0-9a-fA-F]{6}$/.test(colorInput.value)) {
        style.color = colorInput.value.trim().toLowerCase();
    }
    if (labelColorInput && /^#[0-9a-fA-F]{6}$/.test(labelColorInput.value)) {
        style.label_color = labelColorInput.value.trim().toLowerCase();
    }
    return style;
}

function getDefaultLinkStyle() {
    const colorInput = document.getElementById('default-link-color');
    const widthInput = document.getElementById('default-link-width');
    const viaStyleSelect = document.getElementById('default-link-via-style');
    const style = {};
    if (colorInput && /^#[0-9a-fA-F]{6}$/.test(colorInput.value)) {
        style.color = colorInput.value.trim().toLowerCase();
    }
    if (widthInput && widthInput.value !== '') {
        const width = parseFloat(widthInput.value);
        if (!isNaN(width) && width >= 0.5 && width <= 20) {
            style.width = width;
        }
    }
    if (viaStyleSelect && viaStyleSelect.value) {
        style.via_style = viaStyleSelect.value;
    }
    return style;
}

function populateDefaultStyles(options = {}) {
    const dns = options.default_node_style || {};
    const dls = options.default_link_style || {};
    const nodeColor = document.getElementById('default-node-color');
    if (nodeColor) nodeColor.value = dns.color || '';
    const nodeLabelColor = document.getElementById('default-node-label-color');
    if (nodeLabelColor) nodeLabelColor.value = dns.label_color || '';
    const linkColor = document.getElementById('default-link-color');
    if (linkColor) linkColor.value = dls.color || '';
    const linkWidth = document.getElementById('default-link-width');
    if (linkWidth) linkWidth.value = (dls.width !== undefined && dls.width !== null) ? dls.width : '';
    const linkViaStyle = document.getElementById('default-link-via-style');
    if (linkViaStyle) linkViaStyle.value = dls.via_style || '';
}

// ========== Default Styles Live Preview ==========
function initDefaultStyleListeners() {
    const nodeColor = document.getElementById('default-node-color');
    const nodeLabelColor = document.getElementById('default-node-label-color');
    const linkColor = document.getElementById('default-link-color');
    const linkWidth = document.getElementById('default-link-width');
    const linkViaStyle = document.getElementById('default-link-via-style');
    const mapTitle = document.getElementById('map-title');
    const mapTags = document.getElementById('map-tags');

    const hexRegex = /^#[0-9a-fA-F]{6}$/;

    if (nodeColor) {
        nodeColor.addEventListener('input', function () {
            if (hexRegex.test(this.value)) { renderEditor(); renderNodesList(); }
            markUnsaved();
        });
    }
    if (nodeLabelColor) {
        nodeLabelColor.addEventListener('input', function () {
            if (hexRegex.test(this.value)) { renderEditor(); renderNodesList(); }
            markUnsaved();
        });
    }
    if (linkColor) {
        linkColor.addEventListener('input', function () {
            if (hexRegex.test(this.value)) { renderEditor(); renderLinksList(); }
            markUnsaved();
        });
    }
    if (linkWidth) {
        linkWidth.addEventListener('input', function () {
            const w = parseFloat(this.value);
            if (!isNaN(w) && w >= 0.5 && w <= 20) { renderEditor(); renderLinksList(); }
            markUnsaved();
        });
    }
    if (linkViaStyle) {
        linkViaStyle.addEventListener('change', function () {
            renderEditor();
            renderLinksList();
            markUnsaved();
        });
    }
    if (mapTitle) {
        mapTitle.addEventListener('input', function () {
            markUnsaved();
        });
    }
    const mapName = document.getElementById('map-name');
    if (mapName) {
        mapName.addEventListener('input', function () {
            markUnsaved();
        });
    }
    if (mapTags) {
        mapTags.addEventListener('input', function () {
            markUnsaved();
        });
    }
}

// ========== Canvas Resize Validation ==========
function initCanvasResizeValidation() {
    const widthInput = document.getElementById('map-width');
    const heightInput = document.getElementById('map-height');

    if (widthInput) {
        widthInput.addEventListener('change', function () {
            const newWidth = parseInt(this.value, 10);
            if (newWidth && newWidth >= 100) {
                validateAndApplyCanvasResize(newWidth, S.canvas.height);
            }
        });
        widthInput.addEventListener('input', function () {
            markUnsaved();
        });
    }

    if (heightInput) {
        heightInput.addEventListener('change', function () {
            const newHeight = parseInt(this.value, 10);
            if (newHeight && newHeight >= 100) {
                validateAndApplyCanvasResize(S.canvas.width, newHeight);
            }
        });
        heightInput.addEventListener('input', function () {
            markUnsaved();
        });
    }
}

function validateAndApplyCanvasResize(newWidth, newHeight) {
    const nodeRadius = 12;
    const outOfBounds = S.nodes.filter(n =>
        n.x > newWidth - nodeRadius || n.y > newHeight - nodeRadius
    );

    const applyCanvasResize = () => {
        S.canvas.width = newWidth;
        S.canvas.height = newHeight;
        renderEditor();
        WMNGToast.info(`Canvas resized to ${newWidth}x${newHeight}`, { duration: 2000 });
    };

    const revertCanvasResizeInputs = () => {
        document.getElementById('map-width').value = S.canvas.width;
        document.getElementById('map-height').value = S.canvas.height;
    };

    if (outOfBounds.length > 0) {
        showEditorConfirm(
            'Resize Canvas',
            `${outOfBounds.length} node(s) will be outside the new canvas bounds. Continue to move them inside the new bounds, or cancel to keep the current canvas size.`,
            'Resize Canvas',
            'btn-primary',
            function () {
                outOfBounds.forEach(node => {
                    node.x = Math.min(node.x, newWidth - nodeRadius);
                    node.y = Math.min(node.y, newHeight - nodeRadius);
                });
                applyCanvasResize();
            },
            revertCanvasResizeInputs
        );
        return;
    }

    applyCanvasResize();
}

// ========== Keyboard Shortcuts ==========
function initKeyboardShortcuts() {
    document.addEventListener('keydown', handleKeyDown);
}

function handleKeyDown(event) {
    // Ignore if user is typing in an input
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA' || event.target.tagName === 'SELECT') {
        return;
    }

    const key = event.key.toLowerCase();
    const ctrl = event.ctrlKey || event.metaKey;
    const shift = event.shiftKey;

    // Ctrl+S: Save map
    if (ctrl && key === 's') {
        event.preventDefault();
        saveMap();
        return;
    }

    // Ctrl+Z: Undo
    if (ctrl && key === 'z' && !shift) {
        event.preventDefault();
        undo();
        return;
    }

    // Ctrl+Y or Ctrl+Shift+Z: Redo
    if ((ctrl && key === 'y') || (ctrl && shift && key === 'z')) {
        event.preventDefault();
        redo();
        return;
    }

    // Delete/Backspace: Delete selected node
    if ((key === 'delete' || key === 'backspace') && S.selectedNode) {
        event.preventDefault();
        deleteSelectedNode();
        return;
    }

    // Escape: Deselect / Cancel link mode / exit multi-select
    if (key === 'escape') {
        if (S.linkMode) {
            toggleLinkMode();
        }
        if (S.selectionMode) {
            toggleSelectionMode();
        }
        if (S.selectedNode || S.selectedNodes.length) {
            S.selectedNodes = [];
            S.selectedNode = null;
            populateNodeProperties(null);
            renderEditor();
            renderNodesList();
            updateToolbarState();
        }
        return;
    }

    // Arrow keys: Nudge selected node
    if (S.selectedNode && ['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key)) {
        event.preventDefault();
        const amount = shift ? 10 : 1;
        const nodeRadius = 12;

        saveState(); // Save for undo before moving

        switch (key) {
            case 'arrowup':
                S.selectedNode.y = Math.max(nodeRadius, S.selectedNode.y - amount);
                break;
            case 'arrowdown':
                S.selectedNode.y = Math.min(S.canvas.height - nodeRadius, S.selectedNode.y + amount);
                break;
            case 'arrowleft':
                S.selectedNode.x = Math.max(nodeRadius, S.selectedNode.x - amount);
                break;
            case 'arrowright':
                S.selectedNode.x = Math.min(S.canvas.width - nodeRadius, S.selectedNode.x + amount);
                break;
        }
        renderEditor();
        return;
    }

    // + or =: Zoom in
    if (key === '+' || key === '=') {
        event.preventDefault();
        zoomIn();
        return;
    }

    // -: Zoom out
    if (key === '-') {
        event.preventDefault();
        zoomOut();
        return;
    }

    // 0: Reset zoom
    if (key === '0') {
        event.preventDefault();
        resetZoom();
        return;
    }
}

// ========== Undo/Redo System ==========
function saveState() {
    const state = {
        nodes: JSON.parse(JSON.stringify(S.nodes)),
        links: JSON.parse(JSON.stringify(S.links)),
    };
    S.undoStack.push(JSON.stringify(state));
    if (S.undoStack.length > MAX_UNDO) {
        S.undoStack.shift();
    }
    S.redoStack.length = 0; // Clear redo stack on new action
    markUnsaved();
}

function undo() {
    if (S.undoStack.length === 0) {
        WMNGToast.info('Nothing to undo', { duration: 1500 });
        return;
    }
    // Save current state to redo stack
    const currentState = {
        nodes: JSON.parse(JSON.stringify(S.nodes)),
        links: JSON.parse(JSON.stringify(S.links)),
    };
    S.redoStack.push(JSON.stringify(currentState));

    // Restore previous state
    const previousState = JSON.parse(S.undoStack.pop());
    S.nodes = previousState.nodes;
    S.links = previousState.links;
    S.selectedNodes = [];
    S.selectedNode = null;
    populateNodeProperties(null);
    renderEditor();
    renderLinksList();
    WMNGToast.info('Undone', { duration: 1000 });
}

function redo() {
    if (S.redoStack.length === 0) {
        WMNGToast.info('Nothing to redo', { duration: 1500 });
        return;
    }
    // Save current state to undo stack
    const currentState = {
        nodes: JSON.parse(JSON.stringify(S.nodes)),
        links: JSON.parse(JSON.stringify(S.links)),
    };
    S.undoStack.push(JSON.stringify(currentState));

    // Restore redo state
    const redoState = JSON.parse(S.redoStack.pop());
    S.nodes = redoState.nodes;
    S.links = redoState.links;
    S.selectedNodes = [];
    S.selectedNode = null;
    populateNodeProperties(null);
    renderEditor();
    renderLinksList();
    WMNGToast.info('Redone', { duration: 1000 });
}

function loadMapData(id) {
    fetch(S.uris.maps + '/' + id + '/json', {
        headers: { 'Accept': 'application/json' }
    })
        .then(r => {
            if (!r.ok) {
                S.mapDataLoadFailed = true;
                throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
            }
            return r.json();
        })
        .then(data => {
            if (!data) {
                S.mapDataLoadFailed = true;
                return;
            }
            S.nodes = (data.nodes || []).map(node => ({
                id: node.id,
                dbId: node.id,
                label: node.label,
                x: node.x,
                y: node.y,
                deviceId: node.device_id,
                deviceName: node.device_name || null,
                status: node.status || null,
                interfaceId: node.meta?.interface_id || null,
            }));

            S.links = (data.links || []).map(link => ({
                id: link.id,
                dbId: link.id,
                srcId: link.src,
                dstId: link.dst,
                portA: link.port_id_a || null,
                portB: link.port_id_b || null,
                bw: link.bandwidth_bps || null,
                style: link.style || {},
            }));

            const mapTitle = document.getElementById('map-title');
            const mapWidthEl = document.getElementById('map-width');
            const mapHeightEl = document.getElementById('map-height');
            if (mapTitle && data.title) mapTitle.value = data.title;
            if (mapWidthEl && data.width) mapWidthEl.value = data.width;
            if (mapHeightEl && data.height) mapHeightEl.value = data.height;

            const mapTags = document.getElementById('map-tags');
            if (mapTags && Array.isArray(data.options?.tags)) {
                mapTags.value = data.options.tags.join(', ');
                renderMapTagsPreview(data.options.tags);
            }

            populateDefaultStyles(data.options);

            if (data.width && S.canvas) S.canvas.width = data.width;
            if (data.height && S.canvas) S.canvas.height = data.height;

            S.mapDataLoaded = true;
            renderEditor();
            renderLinksList();
            renderNodesList();
            updateStatusCounts();
            markSaved();
        })
        .catch(error => {
            S.mapDataLoadFailed = true;
            console.error('Failed to load map data', error);
            WMNGToast.error('Failed to load map data: ' + error.message + '. Saving is disabled until the map loads.', { duration: 5000 });
        });
}

// --- Device autocomplete ---
function deviceName(device) {
    return device.hostname || device.sysName || `Device ${device.device_id}`;
}

function deviceStatusColor(device) {
    const st = device.status || 'unknown';
    if (st === 'down') return '#dc3545';
    if (st === 'up') return '#28a745';
    return '#6c757d';
}

function loadDevices() {
    initDeviceAutocomplete('device-search', 'device-ac-results', function (device) {
        S.acSelectedDevice = device;
        S.devicesCache = [device];
        document.getElementById('device-select').value = device.device_id;
        loadDeviceInterfaces(device.device_id);
    });
}

function loadDeviceInterfaces(deviceId) {
    const interfaceSelect = document.getElementById('interface-select');
    const interfaceContainer = document.getElementById('interface-container');
    interfaceSelect.innerHTML = '<option value="">Select interface...</option>';
    if (!deviceId) {
        if (interfaceContainer) interfaceContainer.style.display = 'none';
        return;
    }
    if (interfaceContainer) interfaceContainer.style.display = 'block';
    fetch(S.uris.device + '/' + deviceId + '/ports')
        .then(r => {
            if (!r.ok) { console.warn('Failed to load ports: HTTP ' + r.status); return { ports: [] }; }
            return r.json();
        })
        .then(data => {
            (data.ports || []).forEach(port => {
                const option = document.createElement('option');
                option.value = port.port_id;
                option.textContent = port.ifName || port.ifIndex || `Port ${port.port_id}`;
                interfaceSelect.appendChild(option);
            });
        });
}

function initDeviceAutocomplete(searchId, resultsId, onSelect) {
    const search = document.getElementById(searchId);
    const dropdown = document.getElementById(resultsId);
    if (!search || !dropdown) return;

    let localResults = [];
    let localActive = -1;
    let localTimer = null;

    function setItems(devices) {
        localResults = devices;
        S.devicesCache = devices;
        localActive = -1;
        dropdown.innerHTML = '';
        if (!devices.length) {
            const empty = document.createElement('div');
            empty.className = 'ac-empty';
            empty.textContent = 'No devices found';
            dropdown.appendChild(empty);
        } else {
            devices.forEach((dev, i) => {
                const item = document.createElement('div');
                item.className = 'ac-item' + (i === localActive ? ' active' : '');
                item.setAttribute('role', 'option');
                const dot = document.createElement('span');
                dot.className = 'ac-status-dot';
                dot.style.background = deviceStatusColor(dev);
                item.appendChild(dot);
                item.appendChild(document.createTextNode(deviceName(dev)));
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectItem(i);
                });
                dropdown.appendChild(item);
            });
        }
        dropdown.classList.add('show');
        search.setAttribute('aria-expanded', 'true');
    }

    function hide() {
        dropdown.classList.remove('show');
        search.setAttribute('aria-expanded', 'false');
    }

    function updateActive() {
        const items = dropdown.querySelectorAll('.ac-item');
        items.forEach((el, i) => el.classList.toggle('active', i === localActive));
        if (localActive >= 0 && items[localActive]) {
            items[localActive].scrollIntoView({ block: 'nearest' });
        }
    }

    function selectItem(idx) {
        const dev = localResults[idx];
        if (!dev) return;
        search.value = deviceName(dev);
        hide();
        onSelect(dev);
    }

    search.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(localTimer);
        if (q.length < 2) {
            hide();
            return;
        }
        localTimer = setTimeout(function () {
            dropdown.innerHTML = '<div class="ac-loading">Searching...</div>';
            dropdown.classList.add('show');
            fetch(S.uris.devices + '?q=' + encodeURIComponent(q))
                .then(r => {
                    if (!r.ok) return [];
                    return r.json();
                })
                .then(data => {
                    const devices = Array.isArray(data) ? data : (data.devices || []);
                    setItems(devices);
                })
                .catch(function () { hide(); });
        }, 200);
    });

    search.addEventListener('keydown', function (e) {
        if (!dropdown.classList.contains('show')) {
            if (e.key === 'ArrowDown' && this.value.trim().length >= 2) {
                e.preventDefault();
                this.dispatchEvent(new Event('input'));
            }
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            localActive = Math.min(localActive + 1, localResults.length - 1);
            updateActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            localActive = Math.max(localActive - 1, 0);
            updateActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (localActive >= 0) {
                selectItem(localActive);
            } else if (localResults.length === 1) {
                selectItem(0);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            hide();
        }
    });

    search.addEventListener('blur', function () {
        setTimeout(hide, 150);
    });
}

function clearCanvas() {
    showEditorConfirm(
        'Clear Canvas',
        'Clear all nodes and links from this map? This can be undone with the editor undo history.',
        'Clear Canvas',
        'btn-danger',
        function () {
            S.nodes = [];
            S.links = [];
            S.selectedNode = null;
            renderEditor();
            renderLinksList();
        }
    );
}

function saveMap() {
    const mapName = document.getElementById('map-name').value.trim();
    const mapTitle = document.getElementById('map-title').value.trim();
    const mapWidth = parseInt(document.getElementById('map-width').value, 10);
    const mapHeight = parseInt(document.getElementById('map-height').value, 10);

    if (!mapName) {
        WMNGToast.error('Please enter a map name.', { duration: 3000 });
        return;
    }

    // Destructive-save guard: for an existing map, refuse to save
    // until the /json load has completed successfully. The backend
    // replaceMapContent() deletes all nodes/links then recreates
    // from the client arrays — saving before the load resolves (or
    // after it failed) would POST empty arrays and wipe the map.
    if (S.mapId && !S.mapDataLoaded) {
        if (S.mapDataLoadFailed) {
            WMNGToast.error('Cannot save: map data failed to load. Reload the page and try again.', { duration: 5000 });
        } else {
            WMNGToast.warning('Map is still loading, please wait a moment and try again.', { duration: 3000 });
        }
        return;
    }
    const baseHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
    };

    if (!S.mapId) {
        WMNGLoading.show('Creating map...');
        const createOptions = {
            tags: parseMapTags(document.getElementById('map-tags')?.value),
            default_node_style: getDefaultNodeStyle(),
            default_link_style: getDefaultLinkStyle(),
        };
        const payload = { name: mapName, title: mapTitle, width: mapWidth, height: mapHeight, options: createOptions };
        fetch(S.uris.map, {
            method: 'POST',
            headers: baseHeaders,
            body: JSON.stringify(payload),
        })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                }
                return r.json();
            })
            .then(data => {
                WMNGLoading.hide();
                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (data.map?.id) {
                        window.location.href = S.uris.editor + '/' + data.map.id;
                        return;
                    }
                    WMNGToast.success('Map created successfully!', { duration: 3000 });
                } else {
                    WMNGToast.error('Failed to create map: ' + (data.message || 'Unknown error'), { duration: 3000 });
                }
            })
            .catch(error => {
                WMNGLoading.hide();
                WMNGToast.error('Error creating map: ' + error.message, { duration: 3000 });
            });
        return;
    }

    WMNGLoading.show('Saving map...');
    const defaultNodeStyle = getDefaultNodeStyle();
    const defaultLinkStyle = getDefaultLinkStyle();
    const options = {
        width: mapWidth,
        height: mapHeight,
        tags: parseMapTags(document.getElementById('map-tags')?.value),
        default_node_style: defaultNodeStyle,
        default_link_style: defaultLinkStyle,
    };
    const payload = {
        name: mapName,
        title: mapTitle,
        options,
        nodes: S.nodes.map(n => ({
            id: n.id,
            label: n.label,
            x: n.x,
            y: n.y,
            device_id: n.deviceId || null,
            meta: { interface_id: n.interfaceId || null },
        })),
        links: S.links.map(l => ({
            src_node_id: l.srcId,
            dst_node_id: l.dstId,
            port_id_a: l.portA || null,
            port_id_b: l.portB || null,
            bandwidth_bps: l.bw || null,
            style: l.style || {},
        })),
    };

    fetch(S.uris.maps + '/' + S.mapId + '/save', {
        method: 'POST',
        headers: baseHeaders,
        body: JSON.stringify(payload),
    })
        .then(r => {
            // Surface non-2xx (403 admin gate, 419 CSRF, 500 server)
            // before trying to parse JSON — otherwise a HTML error page
            // throws an opaque SyntaxError and the real cause is lost.
            if (!r.ok) {
                throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
            }
            return r.json();
        })
        .then(data => {
            WMNGLoading.hide();
            if (data.success) {
                WMNGToast.success('Map saved successfully!', { duration: 3000 });
                markSaved();
            } else {
                WMNGToast.error('Error saving map: ' + (data.message || 'Unknown error'), { duration: 3000 });
            }
        })
        .catch(err => {
            WMNGLoading.hide();
            WMNGToast.error('Error saving map: ' + err.message, { duration: 3000 });
        });
}

// ========== Auto-Discovery ==========
// Runs LLDP/CDP auto-discovery on the backend for an existing map and
// reloads the map data so newly discovered nodes/links appear.
function autoDiscoverMap() {
    if (!S.mapId) {
        WMNGToast.warning('Save the map first, then run auto-discovery.', { duration: 4000 });
        return;
    }
    if (!S.mapDataLoaded) {
        if (S.mapDataLoadFailed) {
            WMNGToast.error('Cannot run auto-discovery: map data failed to load. Reload the page and try again.', { duration: 5000 });
        } else {
            WMNGToast.warning('Map is still loading, please wait a moment and try again.', { duration: 3000 });
        }
        return;
    }

    WMNGLoading.show('Running auto-discovery...');
    fetch(S.uris.map + '/' + S.mapId + '/autodiscover', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    })
        .then(r => {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
            }
            return r.json();
        })
        .then(data => {
            WMNGLoading.hide();
            if (data.success) {
                WMNGToast.success(data.message, { duration: 5000 });
                // Reload so the discovered nodes/links render on the canvas.
                S.mapDataLoaded = false;
                S.mapDataLoadFailed = false;
                loadMapData(S.mapId);
            } else {
                WMNGToast.error(data.message || 'Auto-discovery failed.', { duration: 5000 });
            }
        })
        .catch(err => {
            WMNGLoading.hide();
            WMNGToast.error('Error running auto-discovery: ' + err.message, { duration: 5000 });
        });
}

function exportJson() {
    const mapName = document.getElementById('map-name').value.trim() || 'untitled';
    const mapTitle = document.getElementById('map-title').value.trim() || 'Network Map';
    const mapWidth = parseInt(document.getElementById('map-width').value, 10) || 800;
    const mapHeight = parseInt(document.getElementById('map-height').value, 10) || 600;

    const defaultNodeStyle = getDefaultNodeStyle();
    const defaultLinkStyle = getDefaultLinkStyle();
    const exportData = {
        name: mapName,
        title: mapTitle,
        width: mapWidth,
        height: mapHeight,
        options: {
            width: mapWidth,
            height: mapHeight,
            default_node_style: defaultNodeStyle,
            default_link_style: defaultLinkStyle,
        },
        nodes: S.nodes.map(n => ({
            id: n.id,
            label: n.label,
            x: n.x,
            y: n.y,
            device_id: n.deviceId || null,
            meta: { interface_id: n.interfaceId || null }
        })),
        links: S.links.map(l => ({
            src: l.srcId,
            dst: l.dstId,
            port_id_a: l.portA || null,
            port_id_b: l.portB || null,
            bandwidth_bps: l.bw || null,
            style: l.style || {}
        }))
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = mapName + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    WMNGToast.success('Map exported as JSON', { duration: 2000 });
}

// ========== Status Updates ==========
function updateStatusCounts() {
    const nodeCount = document.getElementById('node-count');
    const linkCount = document.getElementById('link-count');
    const nodesBadge = document.getElementById('nodes-badge');
    const linksBadge = document.getElementById('links-badge');

    if (nodeCount) nodeCount.textContent = S.nodes.length;
    if (linkCount) linkCount.textContent = S.links.length;
    if (nodesBadge) nodesBadge.textContent = S.nodes.length;
    if (linksBadge) linksBadge.textContent = S.links.length;
}

function markUnsaved() {
    S.hasUnsavedChanges = true;
    const indicator = document.getElementById('unsaved-indicator');
    if (indicator) indicator.style.display = 'inline';
}

function markSaved() {
    S.hasUnsavedChanges = false;
    const indicator = document.getElementById('unsaved-indicator');
    if (indicator) indicator.style.display = 'none';
}

// ========== Toolbar State ==========
function updateToolbarState() {
    const duplicateBtn = document.getElementById('duplicate-btn');
    const deleteBtn = document.getElementById('delete-node-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const hasSelection = S.selectedNode !== null;

    if (duplicateBtn) duplicateBtn.disabled = !hasSelection;
    if (deleteBtn) deleteBtn.disabled = !hasSelection;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = S.selectedNodes.length === 0;
}

// Toggle rubber-band / multi-select mode. In this mode plain canvas clicks
// add to the selection, empty-click drag draws a marquee, and
// shift/ctrl-click toggles membership.
function toggleSelectionMode() {
    S.selectionMode = !S.selectionMode;
    if (!S.selectionMode) {
        S.marquee = null;
    }
    const btn = document.getElementById('select-mode-btn');
    if (btn) {
        btn.classList.toggle('active', S.selectionMode);
        btn.title = S.selectionMode ? 'Multi-select (ON) — drag to marquee' : 'Multi-select';
    }
    const canvasEl = document.getElementById('map-canvas');
    if (canvasEl) {
        canvasEl.style.cursor = S.selectionMode ? 'crosshair' : 'default';
    }
    renderEditor();
}

// Bulk-delete the current multi-selection. Mirrors deleteSelectedNode:
// undo snapshot, then per-node DELETE when saved (DB cascades attached links)
// or local splice otherwise. Deletes happen in parallel; failures surface
// individually and final cleanup runs once all resolve.
function bulkDeleteSelected() {
    const toDelete = S.selectedNodes.slice();
    if (toDelete.length === 0) {
        WMNGToast.info('Nothing selected to delete', { duration: 2000 });
        return;
    }

    showEditorConfirm(
        'Delete Selected',
        `Delete ${toDelete.length} node(s) and their attached links? This can be undone with the editor undo history.`,
        'Delete Selected',
        'btn-danger',
        function () {
            saveState(); // Save for undo

            const confirmCleanup = function () {
                const ids = new Set(toDelete.map(n => n.id).concat(toDelete.map(n => n.dbId).filter(Boolean)));
                S.selectedNodes = [];
                S.selectedNode = null;
                S.nodes = S.nodes.filter(n => toDelete.indexOf(n) < 0);
                S.links = S.links.filter(l => !ids.has(l.srcId) && !ids.has(l.dstId));
                populateNodeProperties(null);
                renderEditor();
                renderLinksList();
                updateToolbarState();
            };

            const pending = toDelete.filter(n => S.mapId && n.dbId);
            if (pending.length === 0) {
                confirmCleanup();
                return;
            }

            let remaining = pending.length;
            let failed = false;
            pending.forEach(n => {
                fetch(S.uris.map + '/' + S.mapId + '/node/' + n.dbId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                })
                    .then(r => {
                        if (!r.ok) {
                            throw new Error('HTTP ' + r.status + (r.statusText ? ' ' + r.statusText : ''));
                        }
                        return r.json().catch(() => ({}));
                    })
                    .then(data => {
                        if (data.success === false) {
                            throw new Error(data.message || 'Server refused to delete node');
                        }
                    })
                    .catch(err => {
                        failed = true;
                        WMNGToast.error('Failed to delete node: ' + err.message, { duration: 3000 });
                    })
                    .finally(() => {
                        remaining -= 1;
                        if (remaining === 0) {
                            confirmCleanup();
                            if (failed) {
                                WMNGToast.warning('Some nodes could not be deleted. Reload the map to refresh.', { duration: 5000 });
                            }
                        }
                    });
            });
        }
    );
}

// --- Page bootstrap: init the canvas, load the map and device list ---
document.addEventListener('DOMContentLoaded', function () {
    initCanvas();
    if (S.mapId) {
        loadMapData(S.mapId);
    }
    loadDevices();
});

document.addEventListener('DOMContentLoaded', initCanvasResizeValidation);
document.addEventListener('DOMContentLoaded', initDefaultStyleListeners);
document.addEventListener('DOMContentLoaded', initKeyboardShortcuts);

// Initial state updates
document.addEventListener('DOMContentLoaded', function () {
    updateStatusCounts();
    renderNodesList();
    updateToolbarState();
});