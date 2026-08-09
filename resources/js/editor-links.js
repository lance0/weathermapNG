/**
 * WeathermapNG editor — link editing.
 *
 * Rendering the links list in the sidebar and the link edit modal (ports,
 * bandwidth, via-style), plus saving and deleting links and opening the
 * port graph.
 *
 * Shared mutable state is read/written through the `S` alias for
 * `window.WMNG.EditorState` (see editor-state.js). Bandwidth conversion and
 * the confirm dialog come from editor-state.js; rendering helpers are global
 * function declarations.
 */
var S = window.WMNG.EditorState;

function viewLinkPort() {
    if (S.currentLinkIndex === null) return;
    const link = S.links[S.currentLinkIndex];
    if (!link) return;
    const portId = document.getElementById('link-src-port').value || document.getElementById('link-dst-port').value || null;
    if (!portId) return;
    const now = Math.floor(Date.now() / 1000);
    const from = now - 86400;
    window.open(S.uris.graph + '?type=port_bits&id=' + portId + '&from=' + from + '&to=' + now, '_blank');
}

function renderLinksList() {
    const c = document.getElementById('links-list');
    if (!c) return;
    c.textContent = '';

    const filterEl = document.getElementById('links-filter');
    const term = filterEl ? filterEl.value.trim().toLowerCase() : '';

    if (!S.links.length) {
        const empty = document.createElement('small');
        empty.className = 'text-muted';
        empty.textContent = 'No links yet';
        c.appendChild(empty);
        return;
    }

    let shown = 0;
    S.links.forEach((l, idx) => {
        const a = findNodeById(l.srcId); const b = findNodeById(l.dstId);
        const aL = a ? a.label : String(l.srcId);
        const bL = b ? b.label : String(l.dstId);

        if (term && !aL.toLowerCase().includes(term) && !bL.toLowerCase().includes(term)) return;
        shown++;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between mb-2';

        const labelDiv = document.createElement('div');
        labelDiv.style.cssText = 'overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; font-size:12px;';
        const icon = document.createElement('i');
        icon.className = 'fas fa-link';
        labelDiv.appendChild(icon);
        labelDiv.appendChild(document.createTextNode(' ' + aL + ' \u2192 ' + bL));
        row.appendChild(labelDiv);

        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group btn-group-sm';
        btnGroup.style.flexShrink = '0';
        const editBtn = document.createElement('button');
        editBtn.className = 'btn btn-outline-secondary';
        editBtn.title = 'Edit link';
        editBtn.addEventListener('click', () => openLinkModal(idx));
        const editIcon = document.createElement('i');
        editIcon.className = 'fas fa-edit';
        editBtn.appendChild(editIcon);
        btnGroup.appendChild(editBtn);
        const delBtn = document.createElement('button');
        delBtn.className = 'btn btn-outline-danger';
        delBtn.title = 'Delete link';
        delBtn.addEventListener('click', () => deleteLink(idx));
        const delIcon = document.createElement('i');
        delIcon.className = 'fas fa-trash';
        delBtn.appendChild(delIcon);
        btnGroup.appendChild(delBtn);
        row.appendChild(btnGroup);

        c.appendChild(row);
    });

    if (shown === 0) {
        const empty = document.createElement('small');
        empty.className = 'text-muted';
        empty.textContent = term ? 'No matches' : 'No links yet';
        c.appendChild(empty);
    }
}

// ========== Link Modal Functions ==========
function openLinkModal(linkIndex) {
    S.currentLinkIndex = linkIndex;
    const link = S.links[linkIndex];
    if (!link) return;

    const srcNode = findNodeById(link.srcId);
    const dstNode = findNodeById(link.dstId);

    const titleEl = document.getElementById('linkModalTitle');
    if (titleEl) {
        const aL = srcNode ? srcNode.label : 'Node';
        const bL = dstNode ? dstNode.label : 'Node';
        titleEl.textContent = aL + ' → ' + bL;
    }

    const srcPortSelect = document.getElementById('link-src-port');
    const dstPortSelect = document.getElementById('link-dst-port');
    const bandwidthValue = document.getElementById('link-bandwidth-value');
    const bandwidthUnit = document.getElementById('link-bandwidth-unit');
    const deleteBtn = document.getElementById('delete-link-btn');
    const viaStyleSelect = document.getElementById('link-via-style');

    // Reset dropdowns
    srcPortSelect.innerHTML = '<option value="">Select port...</option>';
    dstPortSelect.innerHTML = '<option value="">Select port...</option>';
    setBandwidthInputsFromBps(link.bw, bandwidthValue, bandwidthUnit);
    viaStyleSelect.value = (link.style && link.style.via_style) || 'straight';
    deleteBtn.style.display = 'inline-block';

    // Update View Port button when port selects change
    const updateViewPortBtn = () => {
        const vpBtn = document.getElementById('view-port-btn');
        if (vpBtn) vpBtn.style.display = (srcPortSelect.value || dstPortSelect.value) ? 'inline-block' : 'none';
    };
    srcPortSelect.onchange = updateViewPortBtn;
    dstPortSelect.onchange = updateViewPortBtn;

    // Load source node ports
    if (srcNode && srcNode.deviceId) {
        fetch(S.uris.device + '/' + srcNode.deviceId + '/ports')
            .then(r => {
                if (!r.ok) { console.warn('Failed to load source ports: HTTP ' + r.status); return { ports: [] }; }
                return r.json();
            })
            .then(data => {
                (data.ports || []).forEach(port => {
                    const opt = document.createElement('option');
                    opt.value = port.port_id;
                    const descA = port.ifAlias && port.ifAlias !== port.ifName ? ` — ${port.ifAlias}` : '';
                    opt.textContent = (port.ifName || `Port ${port.port_id}`) + descA;
                    if (link.portA == port.port_id) opt.selected = true;
                    srcPortSelect.appendChild(opt);
                });
            });
    }

    // Load destination node ports
    if (dstNode && dstNode.deviceId) {
        fetch(S.uris.device + '/' + dstNode.deviceId + '/ports')
            .then(r => {
                if (!r.ok) { console.warn('Failed to load destination ports: HTTP ' + r.status); return { ports: [] }; }
                return r.json();
            })
            .then(data => {
                (data.ports || []).forEach(port => {
                    const opt = document.createElement('option');
                    opt.value = port.port_id;
                    const descB = port.ifAlias && port.ifAlias !== port.ifName ? ` — ${port.ifAlias}` : '';
                    opt.textContent = (port.ifName || `Port ${port.port_id}`) + descB;
                    if (link.portB == port.port_id) opt.selected = true;
                    dstPortSelect.appendChild(opt);
                });
            });
    }

    // Show View Port button if either port is already selected
    const viewPortBtn = document.getElementById('view-port-btn');
    if (viewPortBtn) viewPortBtn.style.display = (link.portA || link.portB) ? 'inline-block' : 'none';

    $('#linkModal').modal('show');
}

function saveLink() {
    if (S.currentLinkIndex === null) return;
    const link = S.links[S.currentLinkIndex];
    if (!link) return;

    link.portA = document.getElementById('link-src-port').value || null;
    link.portB = document.getElementById('link-dst-port').value || null;
    link.bw = bandwidthInputsToBps(
        document.getElementById('link-bandwidth-value').value,
        document.getElementById('link-bandwidth-unit').value
    );
    const viaStyle = document.getElementById('link-via-style').value || 'straight';
    if (!link.style) link.style = {};
    link.style.via_style = viaStyle;

    $('#linkModal').modal('hide');
    S.currentLinkIndex = null;
    markUnsaved();
    renderEditor();
    renderLinksList();
    WMNGToast.success('Link updated!', { duration: 2000 });
}

function deleteLink(linkIndex) {
    showEditorConfirm(
        'Delete Link',
        'Delete this link? This can be undone with the editor undo history.',
        'Delete Link',
        'btn-danger',
        function () {
            saveState(); // Save for undo
            S.links.splice(linkIndex, 1);
            renderEditor();
            renderLinksList();
            $('#linkModal').modal('hide');
            S.currentLinkIndex = null;
        }
    );
}

// Wire up modal buttons and filter input
document.addEventListener('DOMContentLoaded', function () {
    const saveLinkBtn = document.getElementById('save-link-btn');
    const deleteLinkBtn = document.getElementById('delete-link-btn');
    if (saveLinkBtn) saveLinkBtn.addEventListener('click', saveLink);
    if (deleteLinkBtn) deleteLinkBtn.addEventListener('click', () => {
        if (S.currentLinkIndex !== null) deleteLink(S.currentLinkIndex);
    });

    const filterEl = document.getElementById('links-filter');
    if (filterEl) filterEl.addEventListener('input', renderLinksList);
});