const assert = require('node:assert/strict');
class Element {
    constructor(tag) { this.tag = tag; this.attrs = {}; this.children = []; }
    setAttribute(name, value) { this.attrs[name] = String(value); }
    appendChild(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
    set innerHTML(_) { throw new Error('Historical values must not become HTML'); }
}
global.document = { createElement: tag => new Element(tag), createElementNS: (_, tag) => new Element(tag) };
const { build, render } = require('../resources/js/version-comparison.js');
const node = (id, x = id * 10, y = id * 20) => ({ id, label: `Node ${id}`, x, y, device_id: null, meta: null });
const link = (id, src, dst) => ({ id, src_node_id: src, dst_node_id: dst, port_id_a: null, port_id_b: null, bandwidth_bps: null, style: null });
const snapshot = (nodes = [], links = [], options = {}) => ({ map: { id: 1, name: 'Map', options }, nodes, links });
const blankDiff = () => ({ nodes_added: [], nodes_removed: [], nodes_modified: [], links_added: [], links_removed: [], links_modified: [] });
const response = (before, after, diff = blankDiff()) => ({ version1: { id: 1, name: 'Before', config_snapshot: before }, version2: { id: 2, name: 'After', config_snapshot: after }, diff });
const descendants = root => [root, ...root.children.flatMap(descendants)];
const text = root => descendants(root).map(el => el.textContent || '').join('\n');
const draw = data => { const root = new Element('div'); const result = render(root, data); return { root, result }; };

const before = snapshot([node(1), node(2), node(3)], [link(11, 1, 2), link(12, 2, 3)], { tags: ['old'], threshold: null });
const after = snapshot([node(1, 90, 50), node(2), node(4)], [{ ...link(11, 1, 2), bandwidth_bps: 500, style: { via_points: [{ x: 0, y: 0 }] } }, link(13, 2, 4)], { tags: ['new'], background: '#eee' });
after.nodes[0].label = '<svg onload="globalThis.evil=true">';
after.nodes[0].meta = { sub_map_id: 5, tooltip: '<img src=x onerror=evil()>' };
const data = response(before, after, { nodes_added: [4], nodes_removed: [3], nodes_modified: [1], links_added: [13], links_removed: [12], links_modified: [11] });
const original = JSON.stringify(data);
const { root, result } = draw(data);
assert.equal(JSON.stringify(data), original, 'comparison cannot mutate snapshots');
assert.deepEqual(result.before.preview.points.get('2'), result.after.preview.points.get('2'), 'unchanged node uses the same shared frame');
assert.notDeepEqual(result.before.preview.points.get('1'), result.after.preview.points.get('1'), 'movement remains visible in the shared frame');
assert.ok(result.rows.some(row => row.entity === 'Node #1' && row.field === 'x' && row.before.value === 10 && row.after.value === 90));
assert.ok(result.rows.some(row => row.entity === 'Link #11' && row.field === 'bandwidth_bps' && row.after.value === 500));
assert.ok(result.rows.some(row => row.entity === 'Link #11' && row.field === 'style'));
assert.ok(result.rows.some(row => row.entity === 'Map option' && row.field === 'tags'));
assert.ok(text(root).includes('<svg onload='), 'unsafe labels remain literal text');
assert.ok(text(root).includes('<img src=x onerror=evil()>'), 'unsafe metadata remains literal text');
assert.ok(descendants(root).filter(el => el.tag === 'svg').length === 2);
assert.ok(descendants(root).some(el => el.attrs.class === 'version-diff-node version-diff-added' && el.attrs['data-id'] === '4'));
assert.ok(descendants(root).some(el => el.attrs.class === 'version-diff-link version-diff-removed' && el.attrs['data-id'] === '12'));
assert.ok(!descendants(root).some(el => ['img', 'script', 'foreignObject'].includes(el.tag)));
for (const el of descendants(root)) {
    for (const [key, value] of Object.entries(el.attrs)) {
        if (['x', 'y', 'cx', 'cy', 'x1', 'x2', 'y1', 'y2', 'r'].includes(key)) assert.ok(Number.isFinite(Number(value)));
        assert.ok(!key.startsWith('on'));
    }
}

const legacy = snapshot();
delete legacy.map.options;
let rows = build(response(legacy, snapshot([], [], { tags: null }))).rows;
const bothLegacy = draw(response(legacy, structuredClone(legacy)));
assert.equal(bothLegacy.result.totalRows, 0, 'identical unavailable fields are not fake changes');
const availability = descendants(bothLegacy.root).filter(el => el.className?.includes('version-diff-availability'));
assert.equal(availability.length, 2, 'both historical panes disclose unavailable fields');
for (const note of availability) {
    for (const label of ['options', 'description', 'parent map', 'stored width', 'stored height']) assert.ok(note.textContent.includes(label));
}
const completeNull = snapshot([], [], null);
Object.assign(completeNull.map, { description: null, parent_map_id: null, raw_width: 800, raw_height: 600 });
assert.equal(descendants(draw(response(completeNull, completeNull)).root).filter(el => el.className?.includes('version-diff-availability')).length, 0, 'recorded null values are available');
const tag = rows.find(row => row.field === 'tags');
assert.equal(tag.before.message, 'Unavailable — not recorded in this version');
assert.equal(tag.after.value, null);
assert.equal(tag.state, 'unavailable');
rows = build(response(snapshot([], [], {}), snapshot([], [], { tags: null }))).rows;
assert.equal(rows[0].before.message, 'Not set');
assert.equal(rows[0].after.value, null);
rows = build(response(snapshot([], [], null), snapshot())).rows;
assert.equal(rows[0].field, 'options');
assert.equal(rows[0].before.value, null);
assert.deepEqual(rows[0].after.value, {});
assert.equal(build(response(legacy, snapshot())).rows[0].before.message, 'Unavailable — not recorded in this version');
const reordered = JSON.parse(JSON.stringify(before));
reordered.nodes[0].meta = { b: 2, a: 1 };
const ordered = JSON.parse(JSON.stringify(before));
ordered.nodes[0].meta = { a: 1, b: 2 };
assert.equal(build(response(ordered, reordered, { ...blankDiff(), nodes_modified: [1] })).totalRows, 0, 'object key order is not a configuration change');

const keyed = JSON.parse(JSON.stringify(data));
for (const version of [keyed.version1, keyed.version2]) {
    for (const type of ['nodes', 'links']) version.config_snapshot[type] = Object.fromEntries(version.config_snapshot[type].map(item => [item.id, item]));
}
assert.deepEqual(build(keyed).rows, result.rows, 'list and keyed graph snapshots yield identical details');

const large = snapshot(Array.from({ length: 105 }, (_, i) => node(i + 1)), Array.from({ length: 160 }, (_, i) => link(i + 1, 1, 2)));
large.nodes.push({ ...node(106), x: null });
large.links.push(link(161, 1, 999));
const largeData = response(snapshot(), large, { ...blankDiff(), nodes_added: large.nodes.map(n => n.id), links_added: large.links.map(l => l.id) });
const largeView = draw(largeData);
assert.equal(largeView.result.after.preview.points.size, 100);
assert.equal(largeView.result.after.preview.links.length, 150);
assert.equal(largeView.result.after.preview.invalidNodes, 1);
assert.equal(largeView.result.after.preview.omittedNodes, 5);
assert.equal(largeView.result.after.preview.invalidLinks, 1);
assert.equal(largeView.result.after.preview.omittedLinks, 10);
assert.equal(largeView.result.rows.length, 200);
assert.ok(largeView.result.totalRows > 200);
assert.equal(descendants(largeView.root).find(el => el.tag === 'tbody').children.length, 200);
assert.ok(text(largeView.root).includes('100 of 106 shown; 1 invalid, 5 outside preview'));
assert.ok(text(largeView.root).includes('150 of 161 shown; 1 invalid records/endpoints, 10 outside preview'));
assert.ok(text(largeView.root).includes('response contains full snapshots'));
for (const coordinates of [[[-Number.MAX_VALUE, Number.MAX_VALUE], [Number.MAX_VALUE, -Number.MAX_VALUE]], [[0, 0], [Number.MIN_VALUE, Number.MIN_VALUE]], [[5, 5], [5, 5]]]) {
    const a = snapshot([node(1, ...coordinates[0])]);
    const b = snapshot([node(1, ...coordinates[1])]);
    const view = build(response(a, b, { ...blankDiff(), nodes_modified: [1] }));
    for (const graph of [view.before, view.after]) {
        const point = graph.preview.points.get('1');
        assert.ok(Number.isFinite(point.x) && Number.isFinite(point.y));
        assert.ok(point.x >= 40 && point.x <= 400 && point.y >= 30 && point.y <= 210);
    }
}
assert.ok(text(draw(response(snapshot(), snapshot())).root).includes('Empty graph.'));
assert.ok(text(draw(response(snapshot(), snapshot())).root).includes('No recorded field differences.'));
assert.throws(() => build(response({ map: {} }, snapshot())), /unavailable/);
assert.throws(() => build(response([], snapshot())), /unavailable/);
render(root, response(snapshot(), snapshot()));
assert.equal(descendants(root).filter(el => el.tag === 'svg').length, 2, 'rerender replaces previous preview');
console.log('PASS: shared coordinates, graph/config differences, legacy availability, literal unsafe text, finite extremes, display limits and read-only snapshots');
