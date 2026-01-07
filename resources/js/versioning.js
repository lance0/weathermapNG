/**
 * Map Versioning UI Components
 * Adds Save Version, Version History, and related functionality
 */

(function() {
    let currentMapId = null;

    function init(mapId) {
        currentMapId = mapId;
        setupEventListeners();
    checkAutoSave();
    loadVersionHistory();
    startAutoSaveTimer();
    }

    function setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            setupModalListeners();
        setupVersionButtons();
        setupAutoSaveToggle();
        setupKeyboardShortcuts();
        });
    }

    function setupModalListeners() {
        document.getElementById('versionModal').addEventListener('hidden.bs.modal', () => {
            loadVersionHistory();
        });
    }

    function setupVersionButtons() {
        const saveBtn = document.getElementById('save-version-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                WMNGLoading.show();
                saveVersion();
            });
        }
    }

    function setupAutoSaveToggle() {
        const checkbox = document.getElementById('auto-save');
        if (checkbox) {
            checkbox.addEventListener('change', (e) => {
                const enabled = e.target.checked;
                if (enabled) {
                    startAutoSaveTimer();
                    WMNGToast.success('Auto-save enabled - saving every 5 minutes');
                } else {
                    stopAutoSaveTimer();
                    WMNGToast.info('Auto-save disabled');
                }
            });
        }
    }

    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveVersion();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                e.stopPropagation();
                document.getElementById('versionModal').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }

    function checkAutoSave() {
        const checkbox = document.getElementById('auto-save');
        const enabled = checkbox.checked;
        const saveBtn = document.getElementById('save-version-btn');

        if (enabled) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Saving...';
            saveBtn.disabled = true;
        } else {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Version';
            saveBtn.disabled = false;
        }
    }

    function saveVersion() {
        const name = document.getElementById('version-name').value.trim();
        const desc = document.getElementById('version-desc').value.trim();
        const autoSave = document.getElementById('auto-save').checked;

        if (!name) {
            WMNGToast.error('Please enter a version name');
            return;
        }

        WMNGLoading.show('Saving version...');

        const formData = new FormData();
        formData.append('name', name);
        formData.append('description', desc);
        formData.append('auto_save', autoSave ? '1' : '0');

        fetch(`/plugin/WeathermapNG/maps/${currentMapId}/versions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            WMNGLoading.hide();
            if (data.success) {
                WMNGToast.success('Version saved successfully!', { duration: 2000 });
                document.getElementById('versionModal').classList.add('hide');
                loadVersionHistory();
            } else {
                WMNGToast.error('Failed to save version: ' + (data.message || 'Unknown error'), { duration: 4000 });
            }
        })
        .catch(error => {
            WMNGLoading.hide();
            WMNGToast.error('Error saving version: ' + error.message, { duration: 4000 });
        });
    }

    function loadVersionHistory() {
        WMNGLoading.show('Loading versions...');

        fetch(`/plugin/WeathermapNG/maps/${currentMapId}/versions`)
            .then(r => r.json())
            .then(data => {
                WMNGLoading.hide();
                renderVersionList(data.versions || []);
            })
            .catch(error => {
                WMNGLoading.hide();
                document.getElementById('version-list').innerHTML =
                    '<div class="text-center text-danger">Error loading versions: ' + error.message + '</div>';
            });
    }

    function renderVersionList(versions) {
        const container = document.getElementById('version-list');
        
        if (!versions || !versions.length) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border-custom text-primary" style="width: 2rem; height: 2rem;"></div>
                    <small class="text-muted">No versions saved yet. Create your first version to start tracking changes.</small>
                </div>
            `;
            return;
        }

        let html = '';
        versions.forEach((version, idx) => {
            html += renderVersionItem(version, versions.length - idx);
        });

        container.innerHTML = html;
    }

    function renderVersionItem(version, index) {
        const total = parseInt(document.getElementById('total-versions').textContent || '0');
        return `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>v${total - index}</strong>
                        <small class="text-muted">${version.created_at_human}</small>
                        <span class="badge badge-info">by ${version.created_by || 'Unknown'}</span>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="restoreVersion(${version.id})">
                            <i class="fas fa-undo"></i> Restore
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="compareWithPrevious(${version.id})">
                            <i class="fas fa-code-compare"></i> Compare
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteVersion(${version.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewVersionDetails(${version.id})">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
                <hr>
            </div>
        `;
    }

    function restoreVersion(versionId) {
        if (!confirm('Restore this version? Current changes will be lost.')) {
            return;
        }

        WMNGLoading.show('Restoring version...');

        fetch(`/plugin/WeathermapNG/maps/${currentMapId}/versions/${versionId}/restore`)
            .then(r => r.json())
            .then(data => {
                WMNGLoading.hide();
                if (data.success) {
                    WMNGToast.success('Version restored successfully!');
                    window.location.reload(); // Reload to see changes
                } else {
                    WMNGToast.error('Failed to restore: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                WMNGLoading.hide();
                WMNGToast.error('Error restoring: ' + error.message);
            });
    }

    function deleteVersion(versionId) {
        if (!confirm('Delete this version permanently? This cannot be undone.')) {
            return;
        }

        WMNGLoading.show('Deleting version...');

        fetch(`/plugin/WeathermapNG/maps/${currentMapId}/versions/${versionId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(r => r.json())
        .then(data => {
            WMNGLoading.hide();
            if (data.success) {
                WMNGToast.success('Version deleted successfully!');
                loadVersionHistory();
            } else {
                WMNGToast.error('Failed to delete: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            WMNGLoading.hide();
            WMNGToast.error('Error deleting: ' + error.message);
        });
    }

    function compareWithPrevious(versionId) {
        alert('Compare feature coming soon! Select two versions from history to compare side-by-side.');
    }

    function viewVersionDetails(versionId) {
        alert('Version details view coming soon! View full snapshot of version.');
    }

    let autoSaveTimer = null;

    function startAutoSaveTimer() {
        if (autoSaveTimer) clearInterval(autoSaveTimer);
        autoSaveTimer = setInterval(() => {
            const checkbox = document.getElementById('auto-save');
            if (checkbox.checked) {
                saveVersion();
            }
        }, 5 * 60 * 1000); // 5 minutes
    }

    function stopAutoSaveTimer() {
        if (autoSaveTimer) {
            clearInterval(autoSaveTimer);
            autoSaveTimer = null;
        }
    }

    function getTotalVersions() {
        return parseInt(document.getElementById('total-versions')?.textContent || '0');
    }

    function getCurrentMapId() {
        return currentMapId;
    }

    // Export for use in other scripts
    window.WeathermapVersioning = {
        init,
        saveVersion,
        loadVersionHistory,
        restoreVersion,
        deleteVersion,
        renderVersionList,
        getTotalVersions,
        getCurrentMapId,
    };

    // Make available globally for other scripts
    if (typeof window.WMNGToast === 'undefined') {
        console.warn('WMNGToast not loaded. Make sure ui-helpers.js is included before this script.');
    }
})();
