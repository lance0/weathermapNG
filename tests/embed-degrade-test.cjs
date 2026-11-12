const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { particleBudget, showSecondaryLabels } = require('../resources/js/svg-export.js');

const root = path.join(__dirname, '..');
const app = fs.readFileSync(path.join(root, 'resources/js/embed-app.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'resources/views/embed.blade.php'), 'utf8');
const docs = fs.readFileSync(path.join(root, 'docs/EMBED.md'), 'utf8');

// Mirror tests/OverlayAlignmentTest.php's function extraction.
function functionBody(source, name) {
    const start = source.indexOf('function ' + name + '(');
    assert.notEqual(start, -1, `${name} exists`);
    const next = source.indexOf('\n    function ', start + 1);
    return next === -1 ? source.slice(start) : source.slice(start, next);
}

const count = (haystack, needle) => haystack.split(needle).length - 1;

// --- LAN-273: particle budget math ---

const tiers = [
    [0, 1], [100, 1], [150, 1],
    [151, 0.5], [300, 0.5],
    [301, 0.25], [1500, 0.25], [5000, 0.25],
];
for (const [links, multiplier] of tiers) {
    const budget = particleBudget(links);
    assert.equal(budget.multiplier, multiplier);
    assert.ok(budget.linkCount * budget.perLinkCap <= budget.cap, `<=1500 particles at ${links} links`);
}
assert.equal(particleBudget(1500).perLinkCap, 1, '1500 links ⇒ one particle per link');
assert.equal(particleBudget(1501).perLinkCap, 0, 'over-cap maps drop particles instead of exceeding the cap');

// embed-app applies the budget when building particles and keeps the controls.
const budgetHelper = functionBody(app, 'particleBudgetForMap');
assert.ok(budgetHelper.includes('svgExport.particleBudget('), 'map budget comes from the shared helper');
const particles = functionBody(app, 'drawFlowParticles');
assert.ok(particles.includes('particleBudgetForMap()'), 'particle count consults the map-wide budget');
assert.ok(particles.includes('Math.min(budget.perLinkCap'), 'per-link allowance is capped');
assert.ok(particles.includes('budget.multiplier'), 'link-count density multiplier applied');
assert.ok(particles.includes('particleDensity'), 'user density slider still scales the count');
assert.ok(particles.includes('particleSpeed'), 'user speed slider untouched');
assert.ok(particles.includes('particleCount <= 0'), 'a zero allowance skips the link');
assert.equal(count(particles, '.save()'), count(particles, '.restore()'), 'canvas state stays balanced');

// --- LAN-273: zoom label degradation ---

assert.equal(showSecondaryLabels(0.6), true);
assert.equal(showSecondaryLabels(0.59), false);
const node = functionBody(app, 'drawNode');
assert.ok(node.includes('svgExport.showSecondaryLabels(userScale)'), 'drawNode uses the shared threshold helper');
const guard = node.indexOf('if (secondaryLabels) {');
assert.notEqual(guard, -1, 'secondary text is guarded');
const labelAt = node.indexOf('ctx.fillText(node.label || node.id, x, labelY)');
assert.ok(labelAt !== -1 && labelAt < guard, 'primary node labels stay outside the guard');
assert.ok(node.indexOf("'Σ '") > guard, 'traffic aggregate hidden below the threshold');
assert.ok(node.indexOf('CPU ') > guard, 'CPU/MEM line hidden below the threshold');
assert.ok(node.includes('metaStyle.shape || getNodeType(node)'), 'meta.shape overrides the device shape');
assert.ok(node.includes('metaStyle.label_color'), 'meta.label_color overrides the label color');
assert.ok(node.includes('svgExport.sizeScale(metaStyle.size)'), 'meta.size scales the node');
assert.ok(app.includes('if (node?.meta?.color) return node.meta.color;'), 'meta.color still wins the fill');

// --- LAN-274: export button and download ---

assert.match(view, /<button[^>]*type="button"[^>]*id="export-svg"[^>]*aria-label="[^"]+"[^>]*>Export SVG<\/button>/, 'Export SVG is a named button');
assert.match(view, /<button[^>]*id="export-svg"[^>]*class="btn btn-light btn-sm"/, 'matches the PNG button style');
const svgModuleAt = view.indexOf("Asset::url('js/svg-export.js')");
const appModuleAt = view.indexOf("Asset::url('js/embed-app.js')");
assert.ok(svgModuleAt !== -1, 'svg-export.js is loaded');
assert.ok(svgModuleAt < appModuleAt, 'helpers load before embed-app.js');
assert.ok(app.includes('function exportSVG()'), 'export handler exists');
assert.ok(app.includes("new Blob([svg], { type: 'image/svg+xml"), 'downloads an SVG blob');
assert.ok(app.includes('a.download = `weathermap-${mapId}.svg`'), 'filename is weathermap-<mapId>.svg');
assert.ok(app.includes("document.getElementById('export-svg')"), 'button is wired');
assert.ok(app.includes("document.getElementById('export-svg')") && app.includes('addEventListener(\'click\', exportSVG)'), 'click handler attached');

// --- Docs ---

assert.ok(/PNG\/SVG export/.test(docs) && /weathermap-<mapId>\.svg/.test(docs), 'docs document the PNG/SVG export');
assert.ok(/1500/.test(docs), 'docs mention the particle cap');
assert.ok(/0\.6/.test(docs), 'docs mention the label threshold');
assert.ok(/metrics=0/.test(docs), 'docs mention metrics=0');

console.log('PASS: particle budget, zoom label degradation, SVG export button and docs');
