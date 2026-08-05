/**
 * WeathermapNG editor — shared state and cross-cutting helpers.
 *
 * Must be loaded BEFORE every other editor module. Defines the single mutable
 * `EditorState` object that the canvas / node / link / ui / versions modules
 * read and write, plus the small helpers (CSRF token, HTML escaping,
 * bandwidth-unit conversion, and the shared confirm dialog) used across them.
 *
 * This file is plain-browser JS (no modules, no build step). Function
 * declarations become global; the mutable state lives on
 * `window.WMNG.EditorState` so the `let`/`const` script-scoping rules of
 * classic <script> tags cannot silently fork state between files.
 *
 * Page-specific values (map id, editor link style, and the LibreNMS route
 * URLs) are injected by a tiny inline <script> in editor.blade.php and must
 * never be hard-coded here.
 */
window.WMNG = window.WMNG || {};

window.WMNG.EditorState = {
    // ---- Confirm dialog ----
    pendingEditorConfirmAction: null,
    pendingEditorCancelAction: null,
    editorConfirmAccepted: false,

    // ---- Core map data ----
    // mapId / editorConfig / uris are filled by the template's inline init.
    mapId: null,
    editorConfig: { link_style: 'straight' },
    uris: {},
    nodes: [],
    links: [],
    selectedNode: null,
    // Multi-select set (additive via shift/ctrl-click or rubber-band marquee).
    // `selectedNode` stays the drag/anchor reference; this set drives bulk ops
    // and render highlighting.
    selectedNodes: [],
    mapDataLoaded: false,
    mapDataLoadFailed: false,
    devicesCache: [],

    // ---- Canvas ----
    canvas: null,
    ctx: null,
    isDragging: false,
    dragOffset: { x: 0, y: 0 },
    linkMode: false,
    linkStart: null,
    // Rubber-band marquee selection (selectionMode, active marquee rect).
    selectionMode: false,
    marquee: null,

    // ---- Zoom / pan ----
    viewScale: 1,
    viewOffsetX: 0,
    viewOffsetY: 0,
    isPanning: false,
    panStart: { x: 0, y: 0 },

    // ---- Undo / redo ----
    undoStack: [],
    redoStack: [],

    // ---- Grid snapping ----
    snapToGrid: false,
    gridSize: 20,

    // ---- Minimap ----
    minimapCanvas: null,
    minimapCtx: null,

    // ---- Autocomplete / selection ----
    acSelectedDevice: null,

    // ---- Link modal ----
    currentLinkIndex: null,

    // ---- Unsaved indicator ----
    hasUnsavedChanges: false,
};

var S = window.WMNG.EditorState;

/**
 * Read the CSRF token from the shared helper when available, falling back to
 * the page <meta> tag.
 */
function getCsrfToken() {
    if (window.WMNG && typeof WMNG.getCsrfToken === 'function') {
        return WMNG.getCsrfToken();
    }
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/** Escape user-controlled strings before interpolating into innerHTML (XSS hardening). */
function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

const BANDWIDTH_UNITS = {
    bps: 1,
    Kbps: 1000,
    Mbps: 1000 * 1000,
    Gbps: 1000 * 1000 * 1000,
    KBps: 8 * 1000,
    MBps: 8 * 1000 * 1000,
    GBps: 8 * 1000 * 1000 * 1000,
};

function bandwidthInputsToBps(value, unit) {
    const num = parseFloat(value);
    if (isNaN(num) || num < 0) return null;
    const factor = BANDWIDTH_UNITS[unit] || 1;
    return Math.round(num * factor);
}

function setBandwidthInputsFromBps(bps, valueInput, unitSelect) {
    if (!valueInput || !unitSelect) return;
    if (!bps || isNaN(bps) || bps <= 0) {
        valueInput.value = '';
        unitSelect.value = 'bps';
        return;
    }
    const unitsBySize = ['GBps', 'Gbps', 'MBps', 'Mbps', 'KBps', 'Kbps', 'bps'];
    for (const unit of unitsBySize) {
        const factor = BANDWIDTH_UNITS[unit];
        const val = bps / factor;
        if (Math.abs(val - Math.round(val)) < 0.0001 && val >= 1) {
            valueInput.value = Number.isInteger(val) ? val : parseFloat(val.toFixed(3));
            unitSelect.value = unit;
            return;
        }
    }
    valueInput.value = bps;
    unitSelect.value = 'bps';
}

/**
 * Shared confirmation dialog. Exactly two hooks fire: onConfirm when the
 * action button is clicked, or onCancel when the modal is dismissed any other
 * way (backdrop, close, Escape).
 */
function showEditorConfirm(title, message, confirmText, confirmClass, onConfirm, onCancel = null) {
    S.pendingEditorConfirmAction = onConfirm;
    S.pendingEditorCancelAction = onCancel;
    S.editorConfirmAccepted = false;

    document.getElementById('editorConfirmTitle').textContent = title;
    document.getElementById('editorConfirmBody').textContent = message;

    const actionButton = document.getElementById('editorConfirmAction');
    actionButton.textContent = confirmText;
    actionButton.className = `btn ${confirmClass}`;

    $('#editorConfirmModal').modal('show');
    // Bump the confirm modal's backdrop above the version modal after it's inserted
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 1) {
            backdrops[backdrops.length - 1].style.zIndex = 1055;
        }
    }, 0);
}

document.getElementById('editorConfirmAction')?.addEventListener('click', function () {
    const action = S.pendingEditorConfirmAction;
    S.pendingEditorConfirmAction = null;
    S.pendingEditorCancelAction = null;
    S.editorConfirmAccepted = true;
    $('#editorConfirmModal').modal('hide');

    if (typeof action === 'function') {
        action();
    }
});

$('#editorConfirmModal').on('hidden.bs.modal', function () {
    if (!S.editorConfirmAccepted && typeof S.pendingEditorCancelAction === 'function') {
        S.pendingEditorCancelAction();
    }
    S.pendingEditorConfirmAction = null;
    S.pendingEditorCancelAction = null;
    S.editorConfirmAccepted = false;
});