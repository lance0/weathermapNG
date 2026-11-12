const assert = require('node:assert/strict');
const {
    build,
    escapeXml,
    nodeStyle,
    nodeShape,
    sizeScale,
    showSecondaryLabels,
    particleBudget,
} = require('../resources/js/svg-export.js');

const map = {
    width: 1024,
    height: 768,
    background: '#101010',
    title: 'Core & Edge <main>',
    options: {
        default_link_style: { color: '#2468ac' },
        default_node_style: { label_color: '#333333' },
    },
    nodes: [
        { id: 1, label: 'core-router', x: 10, y: 20, status: 'up' },
        { id: 2, label: 'edge-switch', x: 300, y: 400, status: 'down' },
        { id: 3, label: 'web & <db>', x: 500, y: 100, status: 'unknown', meta: { color: '#123456', label_color: '#abcdef', shape: 'triangle', size: 20 } },
    ],
    links: [
        { id: 10, src: 1, dst: 2, bandwidth_bps: 1e9, style: { color: '#ff0000', width: 4, via_points: [{ x: 100, y: 200 }], via_style: 'angled' } },
        { id: 11, src: 2, dst: 3, bandwidth_bps: 5e8, style: { color: 'not-a-color', via_points: [{ x: 350, y: 200 }], via_style: 'curved' } },
    ],
};

const live = {
    links: {
        10: { in_bps: 5e8, out_bps: 4e8, pct: 50 },
        11: { in_bps: 0, out_bps: 0 },
    },
    nodes: {
        1: { status: 'up', metrics: { cpu: 10, mem: 20 }, traffic: { sum_bps: 2e9 } },
    },
};

const svg = build(map, live);

// Document envelope, viewBox and background.
assert.ok(svg.startsWith('<?xml version="1.0" encoding="UTF-8"?>'));
assert.ok(svg.includes('viewBox="0 0 1024 768"'), 'viewBox comes from map dimensions');
assert.ok(svg.includes('<rect x="0" y="0" width="1024" height="768" fill="#101010"/>'), 'background rect');
assert.ok(svg.includes('<title>Core &amp; Edge &lt;main&gt;</title>'), 'title is XML-escaped');
assert.ok(svg.trimEnd().endsWith('</svg>'));

// Labels are escaped and the document contains nothing executable.
assert.ok(svg.includes('web &amp; &lt;db&gt;'), 'node label escaped');
assert.equal(svg.includes('<db>'), false, 'raw label markup never reaches output');
for (const forbidden of ['<script', '<foreignObject', 'href=', 'javascript:', '<image', '<iframe', '<use']) {
    assert.equal(svg.includes(forbidden), false, `forbidden SVG content: ${forbidden}`);
}
assert.equal(/\son[a-z]+\s*=/i.test(svg), false, 'no inline event handler attributes');

// Link geometry follows via_points (angled ⇒ explicit L segments).
const linkPath = svg.match(/<path d="M 10 20 L 100 200 L 300 400"[^>]*\/>/);
assert.ok(linkPath, 'angled via path includes start, via point and end');
assert.ok(linkPath[0].includes('stroke="#ff0000"'), 'link style color wins');
assert.ok(linkPath[0].includes('stroke-width="4"'), 'link style width wins');
assert.ok(svg.includes('>50%</text>'), 'link label matches the canvas default (percent)');
assert.equal(svg.includes('1.00 Gb/s'), false, 'export no longer prints configured bandwidth instead of live metric');
assert.equal(svg.includes('500.00 Mb/s'), false, 'link without live traffic gets no label');

// The exported label follows the selected metric, exactly like the canvas.
assert.ok(build(map, live, { metric: 'in' }).includes('>500.00 Mb/s</text>'), 'metric=in exports inbound');
assert.ok(build(map, live, { metric: 'out' }).includes('>400.00 Mb/s</text>'), 'metric=out exports outbound');
assert.ok(build(map, live, { metric: 'sum' }).includes('>900.00 Mb/s</text>'), 'metric=sum exports both directions');
assert.equal(build(map, live, { metric: 'in', show_bandwidth: false }).includes('500.00 Mb/s'), false, 'show_bandwidth=false hides rate labels');
assert.equal(build(map, live, { show_percentages: false }).includes('>50%</text>'), false, 'show_percentages=false hides percent labels');
// A label can be exported when the live payload carries the percentage and
// bandwidth even though the stored link has no bandwidth_bps (demo maps).
const demoLink = {
    width: 800, height: 600,
    nodes: [{ id: 1, label: 'a', x: 0, y: 0 }, { id: 2, label: 'b', x: 400, y: 0 }],
    links: [{ id: 7, src: 1, dst: 2 }],
};
const demoLive = { links: { 7: { in_bps: 4e8, out_bps: 3e8, pct: 70 } } };
assert.ok(build(demoLink, demoLive).includes('>70%</text>'), 'percent label needs no stored bandwidth');
const demoRate = build(demoLink, demoLive, { metric: 'in' });
assert.ok(demoRate.includes('>400.00 Mb/s</text>'), 'rate label comes from live payload');

// Utilization colours the stroke when the link has no explicit style colour.
const utilSvg = build(demoLink, { links: { 7: { in_bps: 9e8, out_bps: 1e8, pct: 90 } } });
assert.ok(/stroke="#dc3545"/.test(utilSvg), 'pct above the critical threshold paints the critical colour');

// Curved labels anchor on the sampled curve, not the chord between via points.
const curveMap = {
    width: 800, height: 600,
    nodes: [{ id: 1, label: 'a', x: 66, y: 753 }, { id: 2, label: 'b', x: 645, y: 30 }],
    links: [{ id: 9, src: 1, dst: 2, bandwidth_bps: 1e9, style: { via_points: [{ x: 655, y: 31 }], via_style: 'curved' } }],
};
const curveSvg = build(curveMap, { links: { 9: { in_bps: 5e8, out_bps: 4e8, pct: 50 } } });
const curveText = curveSvg.match(/<text x="([-0-9.]+)" y="([-0-9.]+)"[^>]*>50%<\/text>/);
assert.ok(curveText, 'curved link exports its label');
const chordMid = { x: (66 + 645) / 2, y: (753 + 30) / 2 };
assert.ok(Math.hypot(Number(curveText[1]) - chordMid.x, Number(curveText[2]) - chordMid.y) > 20,
    `label sits on the curve, not the chord (got ${curveText[1]},${curveText[2]})`);

// Curved links render bezier commands and fall back to the configured default color.
const curved = svg.match(/<path d="M 300 400 [^"]*C [^"]*"[^>]*\/>/);
assert.ok(curved, 'curved via_style samples into C commands');
assert.ok(curved[0].includes('stroke="#2468ac"'), 'invalid style color falls back to configured default');

// Node shapes and colors: device rules, status colors, meta overrides.
assert.ok(svg.includes('<g transform="translate(10 20)"><polygon points="0,-10 10,0 0,10 -10,0" fill="#28a745"'), 'router diamond uses node_up');
assert.ok(svg.includes('<polygon points="0,-10 10,0 0,10 -10,0" fill="#28a745"'), 'router renders as a diamond');
assert.ok(svg.includes('<rect x="-9" y="-5" width="18" height="10" rx="3" fill="#dc3545"'), 'switch renders rounded rect in node_down');
assert.ok(svg.includes('<polygon points="0,-11 10,8 -10,8" fill="#123456"'), 'meta.color and meta.shape win');
assert.ok(svg.includes('transform="translate(500 100) scale(2)"'), 'meta.size scales the natural shape');
assert.ok(svg.includes('fill="#abcdef">web'), 'meta.label_color wins for the label');
assert.ok(svg.includes('font-size="12" fill="#333333">core-router'), 'default node label color from map options');
assert.ok(svg.includes('Σ 2.00 Gb/s'), 'node traffic aggregate label is escaped text');

// Warning status color.
const warning = build(map, { nodes: { 1: { status: 'up', metrics: { cpu: 95 } } } });
assert.ok(warning.includes('<polygon points="0,-10 10,0 0,10 -10,0" fill="#ffc107"'), 'high CPU uses node_warning');

// Options: colors override, labels off, bytes units, default envelope.
const custom = build({ ...map, width: 0, height: undefined, background: 'blue' }, live, {
    colors: { node_up: '#00ff00' },
    scale: 'bytes',
    labels: false,
});
assert.ok(custom.includes('viewBox="0 0 800 600"'), 'invalid dimensions fall back to 800x600');
assert.ok(custom.includes('fill="#ffffff"/>'), 'invalid background falls back to #ffffff');
assert.ok(custom.includes('fill="#00ff00"'), 'options.colors override status colors');
assert.equal(custom.includes('>50%</text>'), false, 'options.labels=false drops link labels');
assert.equal(custom.includes('Σ '), false, 'options.labels=false drops traffic labels');
assert.ok(custom.includes('>core-router</text>'), 'node labels stay');
const bytes = build(map, live, { scale: 'bytes', metric: 'sum' });
assert.ok(bytes.includes('112.50 MB/s'), 'bytes scale honoured for rate labels');

// Configured colors.link_normal fills in when neither the link nor the map
// has a default color; a two-point straight link has no via_points.
const plain = build({
    width: 400,
    height: 300,
    nodes: [{ id: 1, label: 'a', x: 0, y: 0 }, { id: 2, label: 'b', x: 100, y: 100 }],
    links: [{ id: 7, src: 1, dst: 2 }],
}, null, { colors: { link_normal: '#0a0b0c' } });
assert.ok(plain.includes('<path d="M 0 0 L 100 100" fill="none" stroke="#0a0b0c" stroke-width="2"'), 'default color/width used');

// Deterministic output (no clock, no randomness).
assert.equal(build(map, live), build(map, live));

// escapeXml covers all five XML specials.
assert.equal(escapeXml(`<&>"'`), '&lt;&amp;&gt;&quot;&apos;');
assert.equal(escapeXml(null), '');

// Node style contract sanitisation.
assert.deepEqual(nodeStyle({ color: '#AaBbCc', label_color: '#112233', shape: ' Square ', size: '12' }), {
    color: '#AaBbCc',
    label_color: '#112233',
    shape: 'square',
    size: 12,
});
assert.deepEqual(nodeStyle({ color: 'red', label_color: '#abc', shape: 'hexagon', size: 100 }), {});
assert.deepEqual(nodeStyle({ size: 5 }), {}, 'size below 6 dropped');
assert.deepEqual(nodeStyle({ size: 41 }), {}, 'size above 40 dropped');
assert.deepEqual(nodeStyle(null), {});
assert.equal(sizeScale(12), 1.2);
assert.equal(sizeScale(undefined), 1);

// Device-type shape rules used when meta.shape is absent.
assert.equal(nodeShape({ label: 'core-router' }), 'router');
assert.equal(nodeShape({ label: 'Edge SWITCH' }), 'switch');
assert.equal(nodeShape({ label: 'web server' }), 'server');
assert.equal(nodeShape({ label: 'fw-01' }), 'firewall');
assert.equal(nodeShape({ label: 'printer' }), 'circle');
assert.equal(nodeShape({ label: 'printer', meta: { shape: 'diamond' } }), 'diamond');

// Particle budget: density multiplier tiers plus the 1500-particle hard cap.
const tiers = [
    [0, 1], [100, 1], [150, 1],
    [151, 0.5], [300, 0.5],
    [301, 0.25], [600, 0.25], [1500, 0.25], [2000, 0.25],
];
for (const [count, multiplier] of tiers) {
    const budget = particleBudget(count);
    assert.equal(budget.multiplier, multiplier, `${count} links ⇒ multiplier ${multiplier}`);
    assert.equal(budget.cap, 1500);
    assert.ok(budget.linkCount * budget.perLinkCap <= 1500, `hard cap respected at ${count} links`);
    assert.ok(Number.isFinite(budget.perLinkCap));
}
assert.equal(particleBudget(100).perLinkCap, 15);
assert.equal(particleBudget(2000).perLinkCap, 0);
assert.equal(particleBudget('200').multiplier, 0.5, 'numeric strings accepted');
assert.equal(particleBudget(-5).multiplier, 1);
assert.equal(particleBudget(100, 900).perLinkCap, 9, 'custom cap honoured');

// Zoom label threshold.
assert.equal(showSecondaryLabels(0.6), true, '0.6 is the inclusive boundary');
assert.equal(showSecondaryLabels(0.59), false);
assert.equal(showSecondaryLabels(1), true);
assert.equal(showSecondaryLabels(0.5), false);
assert.equal(showSecondaryLabels(undefined), true);

console.log('PASS: SVG export envelope, escaping, link geometry, node style overrides, particle budget and label threshold');
