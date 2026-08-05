/**
 * WeathermapNG editor — version history UI.
 *
 * Self-contained module for saving, restoring, comparing and deleting map
 * versions. Only applies to existing, saved maps (when `S.mapId` is set).
 * Loaded last: it depends on `window.WMNG.EditorState` (editor-state.js) and
 * the global helpers `loadMapData` and `showEditorConfirm`.
 */
var S = window.WMNG.EditorState;

(function () {
    // Version history UI — only applies to existing, saved maps.
    const API_MAPS = S.uris.maps;
    const API_VERSIONS = S.uris.versions;

    let versionsCache = [];

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WMNG.getCsrfToken() };
    }

    function toast(method, msg, opts) {
        try {
            if (window.WMNGToast && typeof window.WMNGToast[method] === 'function') {
                window.WMNGToast[method](msg, opts);
            }
        } catch (e) { /* toast failure must never break the UI */ }
    }

    function fetchVersions() {
        return WMNG.fetchJson(`${API_MAPS}/${S.mapId}/versions?_=${Date.now()}`, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(data => {
                versionsCache = Array.isArray(data) ? data : (data.versions || []);
                renderVersionList(versionsCache);
            })
            .catch(err => {
                renderVersionList([]);
                const list = document.getElementById('versionList');
                if (list) {
                    const p = document.createElement('p');
                    p.className = 'text-danger text-center';
                    p.textContent = 'Failed to load versions';
                    list.innerHTML = '';
                    list.appendChild(p);
                }
                toast('error', 'Failed to load versions', { duration: 3000 });
            });
    }

    function renderVersionList(versions) {
        const list = document.getElementById('versionList');
        if (!list) return;
        list.innerHTML = '';

        if (!versions.length) {
            const p = document.createElement('p');
            p.className = 'text-muted text-center';
            p.textContent = 'No saved versions yet.';
            list.appendChild(p);
            return;
        }

        versions.forEach(v => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';
            item.setAttribute('data-version-id', v.id);

            const info = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = v.name || `Version ${v.id}`;
            info.appendChild(name);

            const meta = document.createElement('div');
            meta.className = 'small text-muted';
            meta.textContent = [
                v.created_at_human || v.created_at || '',
                v.creator ? (v.creator.realname || v.creator.username || 'unknown') : 'unknown'
            ].filter(Boolean).join(' \u00b7 ');
            info.appendChild(document.createElement('br'));
            info.appendChild(meta);

            if (v.description) {
                const desc = document.createElement('div');
                desc.className = 'small';
                desc.textContent = v.description;
                info.appendChild(document.createElement('br'));
                info.appendChild(desc);
            }

            const actions = document.createElement('div');
            actions.className = 'btn-group btn-group-sm';

            const restoreBtn = document.createElement('button');
            restoreBtn.type = 'button';
            restoreBtn.className = 'btn btn-sm btn-outline-primary';
            restoreBtn.setAttribute('data-action', 'restore');
            restoreBtn.setAttribute('data-version-id', v.id);
            restoreBtn.setAttribute('aria-label', `Restore version ${v.id}`);
            restoreBtn.textContent = 'Restore';

            const compareBtn = document.createElement('button');
            compareBtn.type = 'button';
            compareBtn.className = 'btn btn-sm btn-outline-info';
            compareBtn.setAttribute('data-action', 'compare');
            compareBtn.setAttribute('data-version-id', v.id);
            compareBtn.setAttribute('aria-label', `Compare version ${v.id}`);
            compareBtn.textContent = 'Compare';

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-sm btn-outline-danger';
            deleteBtn.setAttribute('data-action', 'delete');
            deleteBtn.setAttribute('data-version-id', v.id);
            deleteBtn.setAttribute('aria-label', `Delete version ${v.id}`);
            deleteBtn.textContent = 'Delete';

            actions.appendChild(restoreBtn);
            actions.appendChild(compareBtn);
            actions.appendChild(deleteBtn);

            item.appendChild(info);
            item.appendChild(actions);
            list.appendChild(item);
        });
    }

    function clearDiff() {
        const area = document.getElementById('versionDiffArea');
        if (area) area.style.display = 'none';
        const summary = document.getElementById('diffSummary');
        if (summary) summary.innerHTML = '';
        const n1 = document.getElementById('diffVersion1Name');
        const n2 = document.getElementById('diffVersion2Name');
        if (n1) n1.textContent = '';
        if (n2) n2.textContent = '';
    }

    function showCompareSelect(versionId, btn) {
        const others = versionsCache.filter(v => v.id !== versionId);
        if (!others.length) {
            toast('info', 'No other versions to compare with.', { duration: 3000 });
            return;
        }

        const item = btn.closest('[data-version-id]');
        let selectWrap = item.querySelector('.compare-select-wrap');
        if (selectWrap) {
            selectWrap.remove();
            return; // toggle off
        }

        selectWrap = document.createElement('span');
        selectWrap.className = 'compare-select-wrap ml-1';

        const select = document.createElement('select');
        select.className = 'form-control form-control-sm d-inline-block';
        select.style.maxWidth = '180px';
        select.setAttribute('aria-label', 'Compare with version');
        others.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.name || `Version ${v.id}`;
            select.appendChild(opt);
        });
        selectWrap.appendChild(select);

        const go = document.createElement('button');
        go.type = 'button';
        go.className = 'btn btn-sm btn-info ml-1';
        go.textContent = 'Go';
        go.setAttribute('data-action', 'compare-go');
        go.setAttribute('data-v1', String(versionId));
        selectWrap.appendChild(go);

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-sm btn-secondary ml-1';
        cancel.textContent = 'Cancel';
        cancel.setAttribute('data-action', 'compare-cancel');
        selectWrap.appendChild(cancel);

        item.appendChild(selectWrap);
    }

    function loadDiff(v1Id, v2Id) {
        WMNG.fetchJson(`${API_VERSIONS}/${v1Id}/compare/${v2Id}`)
            .then(data => {
                renderDiff(data, v1Id, v2Id);
            })
            .catch(() => {
                toast('error', 'Failed to load comparison', { duration: 3000 });
            });
    }

    function renderDiff(diff, v1Id, v2Id) {
        const area = document.getElementById('versionDiffArea');
        if (!area) return;
        area.style.display = '';

        const v1 = versionsCache.find(x => x.id === v1Id);
        const v2 = versionsCache.find(x => x.id === v2Id);
        const n1 = document.getElementById('diffVersion1Name');
        const n2 = document.getElementById('diffVersion2Name');
        if (n1) n1.textContent = v1 ? (v1.name || `Version ${v1.id}`) : `Version ${v1Id}`;
        if (n2) n2.textContent = v2 ? (v2.name || `Version ${v2.id}`) : `Version ${v2Id}`;

        const summary = document.getElementById('diffSummary');
        summary.innerHTML = '';

        const d = diff && diff.diff ? diff.diff : diff;
        const counts = [
            ['Nodes added', d.nodes_added],
            ['Nodes removed', d.nodes_removed],
            ['Nodes modified', d.nodes_modified],
            ['Links added', d.links_added],
            ['Links removed', d.links_removed],
            ['Links modified', d.links_modified]
        ];

        counts.forEach(([label, val]) => {
            const li = document.createElement('li');
            li.className = 'small';
            const labelSpan = document.createElement('strong');
            labelSpan.textContent = label + ': ';
            li.appendChild(labelSpan);
            const valSpan = document.createElement('span');
            valSpan.textContent = Array.isArray(val) ? val.length : (val || 0);
            li.appendChild(valSpan);
            summary.appendChild(li);
        });
    }

    function saveVersion() {
        const input = document.getElementById('versionNameInput');
        if (!input) return;
        const name = input.value.trim();
        if (!name) {
            toast('info', 'Please enter a version name.', { duration: 3000 });
            return;
        }
        WMNG.fetchJson(`${API_MAPS}/${S.mapId}/versions`, {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({ name: name })
        })
            .then(data => {
                input.value = '';
                toast('success', 'Version saved', { duration: 3000 });
                clearDiff();
                // Optimistically prepend the new version from the POST response
                const newVersion = data && data.version ? data.version : null;
                if (newVersion) {
                    versionsCache.unshift(newVersion);
                    renderVersionList(versionsCache);
                }
                // Re-fetch authoritative list, but preserve the new version if the GET is stale
                return fetchVersions().then(() => {
                    if (newVersion && !versionsCache.some(v => v.id === newVersion.id)) {
                        versionsCache.unshift(newVersion);
                        renderVersionList(versionsCache);
                    }
                });
            })
            .catch(() => {
                toast('error', 'Failed to save version', { duration: 3000 });
            });
    }

    function restoreVersion(versionId) {
        const v = versionsCache.find(x => x.id === versionId);
        const label = v ? (v.name || `Version ${versionId}`) : `Version ${versionId}`;
        showEditorConfirm(
            'Restore Version',
            `Restore the map to "${label}"? Current unsaved changes will be lost.`,
            'Restore',
            'btn-primary',
            () => {
                WMNG.fetchJson(`${API_VERSIONS}/${versionId}/restore`, {
                    method: 'POST',
                    headers: csrfHeaders()
                })
                    .then(() => {
                        toast('success', 'Version restored. Reloading map data...', { duration: 3000 });
                        $('#versionHistoryModal').modal('hide');
                        // Reset load gate then pull fresh map data.
                        S.mapDataLoaded = false;
                        S.mapDataLoadFailed = false;
                        loadMapData(S.mapId);
                    })
                    .catch(() => {
                        toast('error', 'Failed to restore version', { duration: 3000 });
                    });
            }
        );
    }

    function deleteVersion(versionId) {
        const v = versionsCache.find(x => x.id === versionId);
        const label = v ? (v.name || `Version ${versionId}`) : `Version ${versionId}`;
        showEditorConfirm(
            'Delete Version',
            `Delete version "${label}"? This cannot be undone.`,
            'Delete',
            'btn-danger',
            () => {
                WMNG.fetchJson(`${API_VERSIONS}/${versionId}`, {
                    method: 'DELETE',
                    headers: csrfHeaders()
                })
                    .then(() => {
                        toast('success', 'Version deleted', { duration: 3000 });
                        clearDiff();
                        return fetchVersions();
                    })
                    .catch(() => {
                        toast('error', 'Failed to delete version', { duration: 3000 });
                    });
            }
        );
    }

    // Open modal + load versions
    const versionHistoryBtn = document.getElementById('versionHistoryBtn');
    if (versionHistoryBtn) {
        versionHistoryBtn.addEventListener('click', () => {
            if (!S.mapId) return; // new unsaved maps have no version history
            clearDiff();
            $('#versionHistoryModal').modal('show');
            fetchVersions();
        });
    }

    // Save version
    const saveVersionBtn = document.getElementById('saveVersionBtn');
    if (saveVersionBtn) {
        saveVersionBtn.addEventListener('click', saveVersion);
    }

    // Delegated listeners on version list
    const versionList = document.getElementById('versionList');
    if (versionList) {
        versionList.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const versionId = Number(btn.getAttribute('data-version-id'));

            if (action === 'restore') {
                restoreVersion(versionId);
            } else if (action === 'compare') {
                showCompareSelect(versionId, btn);
            } else if (action === 'compare-go') {
                const v1 = Number(btn.getAttribute('data-v1'));
                const select = btn.previousElementSibling;
                const v2 = Number(select.value);
                const wrap = btn.closest('.compare-select-wrap');
                if (wrap) wrap.remove();
                loadDiff(v1, v2);
            } else if (action === 'compare-cancel') {
                const wrap = btn.closest('.compare-select-wrap');
                if (wrap) wrap.remove();
            } else if (action === 'delete') {
                deleteVersion(versionId);
            }
        });
    }
})();