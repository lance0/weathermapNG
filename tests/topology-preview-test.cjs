const assert = require('node:assert/strict');
class Element {
    constructor(tag) { this.tag = tag; this.attrs = {}; this.children = []; }
    setAttribute(name, value) { this.attrs[name] = String(value); }
    appendChild(child) { this.children.push(child); }
    replaceChildren() { this.children = []; }
}
global.document = { createElementNS: (_, tag) => new Element(tag), createElement: tag => new Element(tag) };
const { render, templateGraph } = require('../resources/js/topology-preview.js');
const draw = graph => { const root = new Element('div'); render(root, graph); return root; };
const svg = root => root.children.find(child => child.tag === 'svg');
const nodes = root => svg(root)?.children.filter(child => child.tag === 'circle') || [];
const links = root => svg(root)?.children.filter(child => child.tag === 'line') || [];
const graph = coords => ({ nodes: coords.map(([x,y], id) => ({ id, x, y })), links: [{ src: 0, dst: 1 }, { src: 1, dst: 2 }] });
const chain = draw(graph([[0,0],[1,0],[2,0]]));
const triangle = draw(graph([[0,0],[1,2],[2,0]]));
assert.notDeepEqual(nodes(chain).map(node => node.attrs), nodes(triangle).map(node => node.attrs));
assert.equal(links(chain).length, 2);
assert.equal(nodes(chain)[1].attrs.cy, '70.00');
assert.equal(draw({ nodes: [], links: [] }).children[0].textContent, 'No topology');
for (const coords of [[[8,9]], [[8,9],[8,9]], [[-Number.MAX_VALUE, Number.MAX_VALUE],[Number.MAX_VALUE,-Number.MAX_VALUE]], [[0,0],[Number.MIN_VALUE,Number.MIN_VALUE]]]) {
    const root = draw(graph(coords));
    assert.equal(nodes(root).length, coords.length);
    for (const child of svg(root).children) {
        for (const value of Object.values(child.attrs)) assert.ok(Number.isFinite(Number(value)), `finite SVG attribute ${value}`);
        if (child.tag === 'circle') {
            assert.ok(Number(child.attrs.cx) >= 12 && Number(child.attrs.cx) <= 308);
            assert.ok(Number(child.attrs.cy) >= 12 && Number(child.attrs.cy) <= 128);
        }
    }
}
assert.deepEqual(nodes(draw(graph([[8,9]])))[0].attrs, { cx: '160.00', cy: '70.00', r: '3' });
const malformed = draw({ nodes: [null, { id: 0, x: null, y: 0 }, { id: 1, x: Infinity, y: 0 }, { id: 2, x: 'invalid', y: 0 }, { id: 3, x: 1, y: 2, label: '<svg onload=evil()>' }], links: [{ src: 2, dst: 3 }] });
assert.equal(nodes(malformed).length, 1);
assert.equal(links(malformed).length, 0);
assert.equal(JSON.stringify(malformed).includes('evil'), false);
const template = { config: { default_nodes: [{ x: null, y: 0 }, { x: 1, y: 2 }, { x: 2, y: 3 }], default_links: [{ src_node_idx: 1, dst_node_idx: 2 }, { src_node_idx: 0, dst_node_idx: 1 }] } };
assert.equal(links(draw(templateGraph(template))).length, 1, 'template indices survive skipped coordinates');
const large = { config: { default_nodes: Array.from({ length: 101 }, (_, x) => ({ x, y: x })), default_links: Array.from({ length: 151 }, () => ({ src_node_idx: 0, dst_node_idx: 99 })) } };
const capped = draw(templateGraph(large));
assert.equal(nodes(capped).length, 100);
assert.equal(links(capped).length, 150);
assert.equal(capped.children[1].textContent, 'Overview');
large.config.default_links = [{ src_node_idx: 0, dst_node_idx: 100 }];
assert.equal(links(draw(templateGraph(large))).length, 0, 'links to capped nodes are omitted');
render(capped, { nodes: [], links: [] });
assert.equal(capped.children.length, 1, 'rerender replaces previous graph');
console.log('PASS: topology geometry, safe extremes, malformed inputs, template indices, caps and empty state');
