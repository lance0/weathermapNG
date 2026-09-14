/**
 * WeathermapNG index view application logic.
 * Extracted from resources/views/index.blade.php with no behavior change.
 * The Blade template injects server-rendered URLs into window.WMNG.IndexUrls
 * before including this file.
 */
const WMNG_INDEX_URLS = window.WMNG && window.WMNG.IndexUrls ? window.WMNG.IndexUrls : {
  map: '', importApi: '', templates: '', editorBase: '',
};

if (window.WMNG && typeof WMNG.ensureUiHelpers === 'function') {
        WMNG.ensureUiHelpers();
    } else {
        const _c = (level) => (msg) => console[level === 'error' ? 'error' : 'log'](msg);
        window.WMNGToast = window.WMNGToast || {};
        ['success','error','warning','info'].forEach(m => {
            if (typeof window.WMNGToast[m] !== 'function') {
                window.WMNGToast[m] = _c(m === 'error' ? 'error' : 'log');
            }
        });
    }
    if (window.WMNG && typeof WMNG.observeTheme === 'function') {
        WMNG.observeTheme('.wmng-index');
    }
let pendingDeleteMapId = null;

function getCsrfToken() {
    if (window.WMNG && typeof WMNG.getCsrfToken === 'function') {
        return WMNG.getCsrfToken();
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// ===== Create Map Form =====
$('#createMapForm').on('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('createMapSubmitBtn');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Creating...';

    const formData = new FormData(this);

    fetch(`${WMNG_INDEX_URLS.map}`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + (response.statusText ? ' ' + response.statusText : ''));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            WMNGToast.success('Map created successfully!');
            $('#createMapModal').modal('hide');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                location.reload();
            }
        } else {
            WMNGToast.error('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        WMNGToast.error('Error: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});

// ===== File Input Label Update + Auto-fill from JSON =====
$('#import-file').on('change', function() {
    const fileName = this.files[0]?.name || 'Choose file...';
    $(this).next('.custom-file-label').text(fileName);

    const file = this.files[0];
    if (!file) {
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = JSON.parse(e.target.result);
            let filled = false;

            const nameInput = document.getElementById('import-name');
            if (nameInput && typeof data.name === 'string' && data.name.trim() !== '') {
                nameInput.value = data.name.trim();
                filled = true;
            }

            const titleInput = document.getElementById('import-title');
            if (titleInput && typeof data.title === 'string' && data.title.trim() !== '') {
                titleInput.value = data.title.trim();
                filled = true;
            }

            if (filled) {
                // Show a transient "Auto-filled from file" hint on the name field
                let hint = document.getElementById('import-autofill-hint');
                if (!hint) {
                    hint = document.createElement('small');
                    hint.id = 'import-autofill-hint';
                    hint.className = 'form-text text-info';
                    nameInput.parentNode.appendChild(hint);
                }
                hint.textContent = 'Auto-filled from file — edit if needed';
            }
        } catch (err) {
            // Not valid JSON or unexpected shape — leave fields empty for manual entry
            console.warn('Could not pre-fill import form from file:', err);
        }
    };
    reader.readAsText(file);
});

// ===== Import Map Form =====
$('#importMapForm').on('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('importMapSubmitBtn');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Importing...';

    const formData = new FormData(this);

    fetch(`${WMNG_INDEX_URLS.importApi}`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + (response.statusText ? ' ' + response.statusText : ''));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            WMNGToast.success('Map imported successfully!');
            $('#importMapModal').modal('hide');
            location.reload();
        } else {
            WMNGToast.error('Error: ' + (data.message || data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        WMNGToast.error('Error: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});

// ===== Delete Map =====
function deleteMap(mapId, mapName) {
    pendingDeleteMapId = mapId;
    const body = document.getElementById('deleteMapModalBody');
    if (body) {
        body.textContent = `Delete map "${mapName}"? This cannot be undone.`;
    }
    $('#deleteMapModal').modal('show');
}

// Delegated listener for delete buttons (replaces inline onclick)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="delete-map"]');
    if (!btn) return;
    deleteMap(btn.dataset.mapId, btn.dataset.mapName);
});

// Delegated listener for template cards (replaces inline onclick)
document.addEventListener('click', function(e) {
    const card = e.target.closest('[data-template-id]');
    if (!card) return;
    selectTemplate(card.dataset.templateId);
});

document.getElementById('confirmDeleteMapBtn')?.addEventListener('click', function() {
    if (!pendingDeleteMapId) return;

    const form = document.getElementById('deleteMapForm');
    form.action = WMNG_INDEX_URLS.map + '/' + pendingDeleteMapId;
    form.submit();
});

// ===== Search & Sort =====
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('map-search');
    const filterSelect = document.getElementById('map-filter');
    const tagFilter = document.getElementById('map-tag-filter');
    const container = document.getElementById('maps-container');
    if (!searchInput || !filterSelect || !container) return;

    const cards = Array.from(container.querySelectorAll('.map-card-col'));
    const total = cards.length;

    function getCardTags(card) {
        try {
            return JSON.parse(card.dataset.tags || '[]');
        } catch (e) {
            return [];
        }
    }

    function sortCards(mode) {
        const sorted = [...cards].sort((a, b) => {
            const nameA = a.dataset.title || a.dataset.name || '';
            const nameB = b.dataset.title || b.dataset.name || '';
            const nodesA = parseInt(a.dataset.nodes || '0', 10);
            const nodesB = parseInt(b.dataset.nodes || '0', 10);
            const linksA = parseInt(a.dataset.links || '0', 10);
            const linksB = parseInt(b.dataset.links || '0', 10);
            const sizeA = parseInt(a.dataset.size || '0', 10);
            const sizeB = parseInt(b.dataset.size || '0', 10);

            switch (mode) {
                case 'name-desc': return nameB.localeCompare(nameA);
                case 'nodes-desc': return nodesB - nodesA;
                case 'links-desc': return linksB - linksA;
                case 'size-desc': return sizeB - sizeA;
                case 'name-asc':
                default: return nameA.localeCompare(nameB);
            }
        });
        sorted.forEach(card => container.appendChild(card));
    }

    function applyFilter() {
        const query = searchInput.value.trim().toLowerCase();
        const selectedTag = tagFilter ? tagFilter.value.trim().toLowerCase() : '';
        let visible = 0;

        cards.forEach(card => {
            const text = `${card.dataset.name} ${card.dataset.title}`;
            const textMatch = text.includes(query);
            const tagMatch = !selectedTag || getCardTags(card).includes(selectedTag);
            const isMatch = textMatch && tagMatch;
            card.style.display = isMatch ? '' : 'none';
            if (isMatch) visible += 1;
        });

        const emptyState = document.getElementById('map-filter-empty');
        if (emptyState) {
            emptyState.style.display = total > 0 && visible === 0 ? '' : 'none';
        }
    }

    searchInput.addEventListener('input', applyFilter);
    filterSelect.addEventListener('change', function() {
        sortCards(this.value);
    });
    if (tagFilter) {
        tagFilter.addEventListener('change', applyFilter);
    }

    sortCards(filterSelect.value);
    applyFilter();
});

// ===== Templates Gallery =====
let templatesLoaded = false;
let templatesData = [];

function loadTemplates() {
    const loading = document.getElementById('templatesLoading');
    const grid = document.getElementById('templatesGrid');
    const error = document.getElementById('templatesError');

    loading.style.display = '';
    grid.style.display = 'none';
    error.style.display = 'none';

    fetch(`${WMNG_INDEX_URLS.templates}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to load templates');
        return response.json();
    })
    .then(data => {
        // API returns { success: true, templates: [...] }
        templatesData = Array.isArray(data) ? data : (data.templates || data.data || []);
        renderTemplates(templatesData);
        templatesLoaded = true;
        loading.style.display = 'none';
        grid.style.display = 'flex';
    })
    .catch(err => {
        console.error('Templates load error:', err);
        loading.style.display = 'none';
        error.style.display = '';
    });
}

function renderTemplates(templates) {
    const grid = document.getElementById('templatesGrid');
    grid.innerHTML = '';

    if (templates.length === 0) {
        grid.innerHTML = '<div class="col-12 text-center py-4 text-muted">No templates available.</div>';
        return;
    }

    templates.forEach(template => {
        const categorySlug = String(template.category || 'custom').replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'custom';
        const categoryClass = 'category-' + categorySlug;
        const nodeCount = template.config?.default_nodes?.length || 0;
        const linkCount = template.config?.default_links?.length || 0;

        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4';
        card.innerHTML = `
            <button type="button" class="template-card" data-template-id="${escapeHtml(String(template.id))}" aria-label="Use template ${escapeHtml(template.title || template.name)}">
                <span class="template-card-icon">
                    <i class="${escapeHtml(template.icon || 'fas fa-map')}"></i>
                </span>
                <span class="template-card-title">${escapeHtml(template.title || template.name)}</span>
                <span class="template-card-desc">${escapeHtml(template.description || '')}</span>
                <span class="template-card-meta">
                    <span class="badge badge-secondary">${escapeHtml(String(template.width))}x${escapeHtml(String(template.height))}</span>
                    <span class="badge ${categoryClass}">${escapeHtml(template.category || 'custom')}</span>
                    ${nodeCount > 0 ? `<span class="badge badge-light">${nodeCount} nodes</span>` : ''}
                    ${linkCount > 0 ? `<span class="badge badge-light">${linkCount} links</span>` : ''}
                </span>
                <span class="btn btn-success btn-sm template-card-btn">
                    <i class="fas fa-plus mr-1"></i>Use Template
                </span>
            </button>
        `;
        grid.appendChild(card);
    });
}

function selectTemplate(templateId) {
    const template = templatesData.find(t => String(t.id) === String(templateId));
    if (!template) return;

    const mapName = prompt(`Create map from "${template.title}".\n\nEnter a unique map name (no spaces):`, '');
    if (!mapName || !mapName.trim()) return;

    const cleanName = mapName.trim().toLowerCase().replace(/[^a-z0-9\-_]/g, '-');

    createMapFromTemplate(templateId, cleanName);
}

function createMapFromTemplate(templateId, mapName) {
    const grid = document.getElementById('templatesGrid');
    grid.style.opacity = '0.5';
    grid.style.pointerEvents = 'none';

    fetch(`${WMNG_INDEX_URLS.templates}/${templateId}/create-map`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ name: mapName })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || data.error || 'Failed to create map');
            });
        }
        return response.json();
    })
    .then(data => {
        // Redirect first — non-critical UI cleanup must not block navigation.
        const mapId = data.id || data.map?.id || data.map_id || data.data?.id;
        if (mapId) {
            window.location.href = WMNG_INDEX_URLS.editorBase + '/' + mapId;
            return;
        }
        // No map ID in response — show toast and reload to see the new map.
        try { WMNGToast.success('Map created from template!'); } catch(e) { console.log('Map created from template!'); }
        try { $('#createMapModal').modal('hide'); } catch(e) {}
        location.reload();
    })
    .catch(err => {
        WMNGToast.error('Error: ' + err.message);
        grid.style.opacity = '1';
        grid.style.pointerEvents = '';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load templates when modal opens
$('#createMapModal').on('shown.bs.modal', function() {
    if (!templatesLoaded) {
        loadTemplates();
    }
});

// Toggle modal size based on active tab
$('#createMapTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
    const dialog = document.getElementById('createMapDialog');
    if (e.target.id === 'templates-tab') {
        dialog.classList.add('modal-lg');
    } else {
        dialog.classList.remove('modal-lg');
    }
});
