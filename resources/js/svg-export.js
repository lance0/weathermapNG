// WeathermapNG embed: static SVG export plus the pure render helpers shared
// with embed-app.js.
//
// UMD, matching resources/js/topology-preview.js: browser code reads
// window.WMNG.SvgExport, Node tests require() the file. This module never
// touches the DOM and never reads the clock or Math.random(), so build() is
// deterministic for a given (mapData, liveData, options) input.
//
// Per-node style lives flat in wmng_nodes.meta:
//   meta.color        #RRGGBB node fill override
//   meta.label_color  #RRGGBB label text override
//   meta.shape        circle|square|diamond|triangle
//   meta.size         numeric 6..40 world-unit radius; natural embed shapes
//                     scale by size/10 (the embed's natural circle radius)
// Invalid values are dropped rather than emitted.
(function (global) {
    'use strict';

    const DEFAULT_WIDTH = 800;
    const DEFAULT_HEIGHT = 600;
    const DEFAULT_BACKGROUND = '#ffffff';
    const DEFAULT_LINK_COLOR = '#28a745';
    const DEFAULT_LINK_WIDTH = 2;
    const MAX_LINK_WIDTH = 40;
    const NODE_RADIUS = 10; // natural circle radius in the embed renderer
    const MIN_NODE_SIZE = 6;
    const MAX_NODE_SIZE = 40;
    const SHAPES = ['circle', 'square', 'diamond', 'triangle'];
    const VIA_STYLES = ['straight', 'angled', 'curved'];
    const SECONDARY_LABEL_MIN_SCALE = 0.6;
    const PARTICLE_CAP = 1500;
    const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

    function finiteNumber(value, fallback) {
        if (typeof value === 'number' && Number.isFinite(value)) return value;
        if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) return Number(value);
        return fallback;
    }

    function hexColor(value, fallback) {
        return typeof value === 'string' && HEX_COLOR.test(value.trim()) ? value.trim() : fallback;
    }

    // Same escaping rules as embed-app.js escapeHtml, plus apostrophes so text
    // nodes and attribute values can share one escape path.
    function escapeXml(value) {
        return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&apos;',
        }[character]));
    }

    function dimension(value, fallback) {
        const size = finiteNumber(value, fallback);
        return size > 0 && size <= 10000 ? Math.round(size * 100) / 100 : fallback;
    }

    function formatNumber(value) {
        const rounded = Math.round((Number(value) || 0) * 100) / 100;
        return String(rounded === 0 ? 0 : rounded);
    }

    // --- Shared pure helpers (LAN-273) ---

    // Below this zoom the embed hides node secondary text (Σ traffic and the
    // CPU/MEM line). Node labels stay visible; link labels are unaffected.
    function showSecondaryLabels(userScale) {
        const scale = finiteNumber(userScale, 1);
        return scale >= SECONDARY_LABEL_MIN_SCALE;
    }

    // Map-wide particle budget. The density multiplier shrinks as link count
    // grows, and perLinkCap keeps count * perLinkCap <= 1500 (the hard cap on
    // total particles across the map). Above 1500 links the per-link allowance
    // is 0: crossing the cap is worse than dropping the animation.
    function particleBudget(linkCount, cap) {
        const count = Math.max(0, Math.floor(finiteNumber(linkCount, 0)));
        const limit = Math.max(0, Math.floor(finiteNumber(cap, PARTICLE_CAP)));
        let multiplier = 0.25;
        if (count <= 150) multiplier = 1;
        else if (count <= 300) multiplier = 0.5;
        return { linkCount: count, multiplier, cap: limit, perLinkCap: Math.floor(limit / Math.max(1, count)) };
    }

    // --- Node style contract ---

    function nodeStyle(meta) {
        const style = {};
        if (!meta || typeof meta !== 'object') return style;
        const color = hexColor(meta.color, null);
        if (color) style.color = color;
        const labelColor = hexColor(meta.label_color, null);
        if (labelColor) style.label_color = labelColor;
        if (typeof meta.shape === 'string' && SHAPES.indexOf(meta.shape.trim().toLowerCase()) !== -1) {
            style.shape = meta.shape.trim().toLowerCase();
        }
        if (meta.size !== null && meta.size !== undefined && meta.size !== '' && typeof meta.size !== 'boolean') {
            const size = finiteNumber(meta.size, null);
            if (size !== null && size >= MIN_NODE_SIZE && size <= MAX_NODE_SIZE) style.size = size;
        }
        return style;
    }

    // Natural embed shapes for each device type, used when meta.shape is absent.
    function deviceShape(label) {
        const text = String(label === undefined || label === null ? '' : label).toLowerCase();
        if (text.includes('router') || text.includes('core')) return 'router';
        if (text.includes('switch')) return 'switch';
        if (text.includes('server') || text.includes('db') || text.includes('app') || text.includes('web') || text.includes('file')) return 'server';
        if (text.includes('firewall') || text.includes('fw')) return 'firewall';
        return 'circle';
    }

    function nodeShape(node) {
        const style = nodeStyle(node && node.meta);
        return style.shape || deviceShape(node && node.label);
    }

    function sizeScale(size) {
        const value = finiteNumber(size, 0);
        return value ? value / NODE_RADIUS : 1;
    }

    function shapeInsets(shape) {
        switch (shape) {
            case 'server': return { top: 12, bottom: 12 };
            case 'switch': return { top: 5, bottom: 5 };
            case 'square': return { top: 8, bottom: 8 };
            case 'triangle': return { top: 11, bottom: 8 };
            case 'firewall': return { top: 12, bottom: 14 };
            default: return { top: 10, bottom: 10 };
        }
    }

    // --- SVG building ---

    function shapeMarkup(shape, fill, scale) {
        // Compensate for the size scale so the outline stays ~1.5 world units
        // wide, matching the canvas renderer.
        const strokeWidth = formatNumber(1.5 / scale);
        const paint = ` fill="${fill}" stroke="#000000" stroke-width="${strokeWidth}"`;
        switch (shape) {
            case 'server':
                return `<rect x="-7" y="-12" width="14" height="24"${paint}/>`
                    + `<path d="M -5 -6 H 5 M -5 0 H 5 M -5 6 H 5" fill="none" stroke="#ffffff" stroke-width="${formatNumber(1 / scale)}"/>`;
            case 'square':
                return `<rect x="-8" y="-8" width="16" height="16" rx="2"${paint}/>`;
            case 'switch':
                return `<rect x="-9" y="-5" width="18" height="10" rx="3"${paint}/>`;
            case 'diamond':
            case 'router':
                return `<polygon points="0,-10 10,0 0,10 -10,0"${paint}/>`;
            case 'triangle':
                return `<polygon points="0,-11 10,8 -10,8"${paint}/>`;
            case 'firewall':
                return `<path d="M 0 -12 L 10 -6 L 10 4 Q 10 12 0 14 Q -10 12 -10 4 L -10 -6 Z"${paint}/>`;
            default:
                return `<circle cx="0" cy="0" r="10"${paint}/>`;
        }
    }

    function textElement(value, x, y, fontSize, fill, halo) {
        const stroke = halo ? ` stroke="${halo}" stroke-width="3" paint-order="stroke" stroke-linejoin="round"` : '';
        return `<text x="${formatNumber(x)}" y="${formatNumber(y)}" text-anchor="middle" font-family="Arial, sans-serif" font-size="${fontSize}" fill="${fill}"${stroke}>${escapeXml(value)}</text>`;
    }

    // Catmull-Rom sampling for curved links: labels must sit on the drawn
    // curve, not on the straight chord between via points (same approach as
    // the canvas renderer's densePathPoints()).
    function densePoints(points, viaStyle) {
        if (viaStyle !== 'curved' || points.length <= 2) return points;
        const steps = 24;
        const dense = [points[0]];
        for (let i = 0; i < points.length - 1; i++) {
            const p0 = points[Math.max(0, i - 1)];
            const p1 = points[i];
            const p2 = points[i + 1];
            const p3 = points[Math.min(points.length - 1, i + 2)];
            const cp1x = p1.x + (p2.x - p0.x) / 6;
            const cp1y = p1.y + (p2.y - p0.y) / 6;
            const cp2x = p2.x - (p3.x - p1.x) / 6;
            const cp2y = p2.y - (p3.y - p1.y) / 6;
            for (let step = 1; step <= steps; step++) {
                const t = step / steps;
                const t2 = t * t, t3 = t2 * t;
                const mt = 1 - t, mt2 = mt * mt, mt3 = mt2 * mt;
                dense.push({
                    x: mt3 * p1.x + 3 * mt2 * t * cp1x + 3 * mt * t2 * cp2x + t3 * p2.x,
                    y: mt3 * p1.y + 3 * mt2 * t * cp1y + 3 * mt * t2 * cp2y + t3 * p2.y,
                });
            }
        }
        return dense;
    }

    function humanRate(bits, units) {
        const v = finiteNumber(bits, null);
        if (v === null || v < 0) return '';
        if (units === 'bytes') {
            if (v >= 8e9) return (v / 8e9).toFixed(2) + ' GB/s';
            if (v >= 8e6) return (v / 8e6).toFixed(2) + ' MB/s';
            if (v >= 8e3) return (v / 8e3).toFixed(2) + ' KB/s';
            return (v / 8).toFixed(0) + ' B/s';
        }
        if (v >= 1e9) return (v / 1e9).toFixed(2) + ' Gb/s';
        if (v >= 1e6) return (v / 1e6).toFixed(2) + ' Mb/s';
        if (v >= 1e3) return (v / 1e3).toFixed(2) + ' Kb/s';
        return v + ' b/s';
    }

    function lookupLive(collection, id) {
        if (!collection || id === undefined || id === null) return null;
        if (Array.isArray(collection)) {
            return collection.find(item => item && (item.id === id || item.node_id === id || item.link_id === id)) || null;
        }
        if (typeof collection === 'object') {
            const entry = collection[id] !== undefined && collection[id] !== null ? collection[id] : collection[String(id)];
            if (entry && typeof entry === 'object') return entry;
        }
        return null;
    }

    function nodePoint(node) {
        const position = node && node.position && typeof node.position === 'object' ? node.position : {};
        return {
            x: finiteNumber(position.x !== undefined ? position.x : node && node.x, 0),
            y: finiteNumber(position.y !== undefined ? position.y : node && node.y, 0),
        };
    }

    function nodeLive(node, liveData) {
        return lookupLive(liveData && liveData.nodes, node.id !== undefined ? node.id : node.node_id) || {};
    }

    function linkLive(link, liveData) {
        const attached = link.live && typeof link.live === 'object' ? link.live : null;
        const entry = lookupLive(liveData && liveData.links, link.id !== undefined ? link.id : link.link_id) || {};
        return attached ? Object.assign({}, entry, attached) : Object.assign({}, entry);
    }

    // A live entry that is all zeros (no ports, no traffic) is treated as no
    // value so exported links only carry labels backed by real traffic data.
    function hasLiveValue(live) {
        return ['in_bps', 'out_bps', 'pct'].some(key => {
            const value = finiteNumber(live[key], null);
            return value !== null && value > 0;
        });
    }

    function trafficSum(node, liveData) {
        const direct = finiteNumber(node.current_value, null);
        if (direct !== null) return direct;
        const live = nodeLive(node, liveData);
        return finiteNumber(live.traffic && live.traffic.sum_bps, null);
    }

    function isWarning(metrics, thresholds) {
        const warnAt = finiteNumber(Array.isArray(thresholds) ? thresholds[1] : null, 80);
        return ['cpu', 'mem'].some(key => finiteNumber(metrics && metrics[key], null) !== null
            && finiteNumber(metrics[key], 0) >= warnAt);
    }

    function nodeColor(node, liveData, options, style) {
        if (style.color) return style.color;
        const colors = options.colors && typeof options.colors === 'object' ? options.colors : {};
        const live = nodeLive(node, liveData);
        const status = String(node.status || live.status || 'unknown');
        const metrics = node.metrics && typeof node.metrics === 'object' ? node.metrics : live.metrics;
        if (status === 'down') return hexColor(colors.node_down, '#dc3545');
        if (status === 'up' && isWarning(metrics, options.thresholds)) return hexColor(colors.node_warning, '#ffc107');
        const fallback = hexColor(options.default_node_color, null);
        if (status === 'up') return fallback || hexColor(colors.node_up, '#28a745');
        return fallback || hexColor(colors.node_unknown, '#6c757d');
    }

    function pathData(points, viaStyle) {
        let d = `M ${formatNumber(points[0].x)} ${formatNumber(points[0].y)}`;
        if (viaStyle === 'curved' && points.length > 2) {
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[Math.max(0, i - 1)];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[Math.min(points.length - 1, i + 2)];
                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = p1.y + (p2.y - p0.y) / 6;
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = p2.y - (p3.y - p1.y) / 6;
                d += ` C ${formatNumber(cp1x)} ${formatNumber(cp1y)} ${formatNumber(cp2x)} ${formatNumber(cp2y)} ${formatNumber(p2.x)} ${formatNumber(p2.y)}`;
            }
            return d;
        }
        for (let i = 1; i < points.length; i++) {
            d += ` L ${formatNumber(points[i].x)} ${formatNumber(points[i].y)}`;
        }
        return d;
    }

    function midpoint(points) {
        if (points.length < 2) return points[0] || { x: 0, y: 0 };
        let total = 0;
        const lengths = [];
        for (let i = 1; i < points.length; i++) {
            const length = Math.hypot(points[i].x - points[i - 1].x, points[i].y - points[i - 1].y);
            lengths.push(length);
            total += length;
        }
        let remaining = total / 2;
        for (let i = 0; i < lengths.length; i++) {
            if (remaining <= lengths[i] || i === lengths.length - 1) {
                const t = lengths[i] > 0 ? remaining / lengths[i] : 0;
                return {
                    x: points[i].x + (points[i + 1].x - points[i].x) * Math.min(1, t),
                    y: points[i].y + (points[i + 1].y - points[i].y) * Math.min(1, t),
                };
            }
            remaining -= lengths[i];
        }
        return points[points.length - 1];
    }

    function linkWidth(link, defaults, options) {
        const raw = link.style && link.style.width !== undefined && link.style.width !== null
            ? link.style.width
            : (link.width !== undefined && link.width !== null
                ? link.width
                : (defaults.width !== undefined && defaults.width !== null ? defaults.width : options.default_link_width));
        const width = finiteNumber(raw, DEFAULT_LINK_WIDTH);
        return Math.max(0.5, Math.min(MAX_LINK_WIDTH, width));
    }

    function linkPoints(link, source, target) {
        const points = [nodePoint(source)];
        const via = link.style && Array.isArray(link.style.via_points) ? link.style.via_points : [];
        via.forEach(point => {
            const x = finiteNumber(point && point.x, null);
            const y = finiteNumber(point && point.y, null);
            if (x !== null && y !== null) points.push({ x, y });
        });
        points.push(nodePoint(target));
        return points;
    }

    function linkViaStyle(link, defaults, options) {
        const raw = link.style && link.style.via_style
            ? link.style.via_style
            : (defaults.via_style || options.link_style || 'straight');
        const style = String(raw).toLowerCase();
        return VIA_STYLES.indexOf(style) === -1 ? 'straight' : style;
    }

    function renderLink(link, nodesById, liveData, options, parts) {
        const source = nodesById.get(String(link.source !== undefined && link.source !== null ? link.source : (link.src !== undefined && link.src !== null ? link.src : link.source_id)));
        const target = nodesById.get(String(link.target !== undefined && link.target !== null ? link.target : (link.dst !== undefined && link.dst !== null ? link.dst : link.destination_id)));
        if (!source || !target) return;

        const points = linkPoints(link, source, target);
        const defaults = options.default_link_style && typeof options.default_link_style === 'object' ? options.default_link_style : {};
        const viaStyle = linkViaStyle(link, defaults, options);
        const live = linkLive(link, liveData);
        const bandwidth = finiteNumber(
            link.bandwidth_bps !== undefined && link.bandwidth_bps !== null ? link.bandwidth_bps
                : (link.bandwidth !== undefined && link.bandwidth !== null ? link.bandwidth : live.bandwidth),
            null
        );
        const inBps = finiteNumber(live.in_bps, 0) || 0;
        const outBps = finiteNumber(live.out_bps, 0) || 0;
        const presetPct = finiteNumber(live.pct, null);
        const pct = presetPct !== null ? presetPct
            : ((Math.max(inBps, outBps) && bandwidth) ? (Math.max(inBps, outBps) / bandwidth) * 100 : null);
        const configured = hexColor(options.colors && options.colors.link_normal, DEFAULT_LINK_COLOR);
        const thresholds = Array.isArray(options.thresholds) ? options.thresholds : [50, 80, 95];
        const utilColor = (() => {
            if (pct === null) return hexColor(defaults.color, configured);
            if (pct >= (finiteNumber(thresholds[1], 80))) return hexColor(options.colors && options.colors.link_critical, '#dc3545');
            if (pct >= (finiteNumber(thresholds[0], 50))) return hexColor(options.colors && options.colors.link_warning, '#ffc107');
            return hexColor(options.colors && options.colors.link_normal, DEFAULT_LINK_COLOR);
        })();
        // Match the canvas: an explicit link color wins, otherwise utilization
        // colors the stroke instead of the configured default.
        const stroke = hexColor(link.style && link.style.color, null)
            || (live && Object.keys(live).length ? utilColor : hexColor(defaults.color, configured));
        const width = linkWidth(link, defaults, options);
        parts.push(`<path d="${pathData(points, viaStyle)}" fill="none" stroke="${stroke}" stroke-width="${formatNumber(width)}" stroke-linecap="round" stroke-linejoin="round"/>`);

        if (options.labels === false) return;
        if (!hasLiveValue(live)) return;

        // Mirror the on-canvas label: percent by default, otherwise the
        // selected metric (in/out/sum) in bits or bytes. The old version always
        // printed the configured bandwidth, so exports disagreed with the map.
        const metric = options.metric || 'percent';
        const metricBps = metric === 'in' ? inBps
            : metric === 'out' ? outBps
            : metric === 'sum' ? ((inBps + outBps) || null)
            : (Math.max(inBps, outBps) || null);

        let label = null;
        if (metric === 'percent') {
            if (options.show_percentages === false) return;
            if (pct === null) return;
            label = Math.round(Math.max(0, Math.min(100, pct))) + '%';
        } else {
            if (options.show_bandwidth === false) return;
            if (metricBps === null || metricBps <= 0) return;
            label = humanRate(metricBps, options.scale);
        }
        if (!label) return;

        // Label sits on the drawn curve; the white halo matches the canvas.
        const mid = midpoint(densePoints(points, viaStyle));
        parts.push(textElement(label, mid.x, mid.y - 5, 11, '#111111', '#ffffff'));
    }

    function renderNode(node, liveData, options, parts) {
        const style = nodeStyle(node.meta);
        const shape = style.shape || deviceShape(node.label);
        const scale = style.size ? sizeScale(style.size) : 1;
        const point = nodePoint(node);
        const insets = shapeInsets(shape);
        const fill = nodeColor(node, liveData, options, style);
        const labelColor = style.label_color || hexColor(options.default_node_label_color, '#000000');
        const label = node.label !== undefined && node.label !== null ? node.label : (node.id !== undefined && node.id !== null ? node.id : '');

        const transform = `translate(${formatNumber(point.x)} ${formatNumber(point.y)})` + (scale === 1 ? '' : ` scale(${formatNumber(scale)})`);
        parts.push(`<g transform="${transform}">${shapeMarkup(shape, fill, scale)}</g>`);
        if (String(label) !== '') {
            parts.push(textElement(label, point.x, point.y - insets.top * scale - 6, 12, labelColor));
        }

        if (options.labels === false) return;
        const sum = trafficSum(node, liveData);
        if (sum !== null && sum > 0) {
            parts.push(textElement('Σ ' + humanRate(sum, options.scale), point.x, point.y + insets.bottom * scale + 15, 10, '#666666'));
        }
    }

    function build(mapData, liveData, options) {
        const map = mapData && typeof mapData === 'object' ? mapData : {};
        const opts = options && typeof options === 'object' ? Object.assign({}, options) : {};
        const defaults = map.options && typeof map.options === 'object' ? map.options : {};
        if (!opts.default_link_style && defaults.default_link_style && typeof defaults.default_link_style === 'object') {
            opts.default_link_style = defaults.default_link_style;
        }
        if (opts.default_node_color === undefined && defaults.default_node_style && defaults.default_node_style.color) {
            opts.default_node_color = defaults.default_node_style.color;
        }
        if (opts.default_node_label_color === undefined && defaults.default_node_style && defaults.default_node_style.label_color) {
            opts.default_node_label_color = defaults.default_node_style.label_color;
        }

        const safeWidth = dimension(map.width !== undefined ? map.width : map.metadata && map.metadata.width, DEFAULT_WIDTH);
        const safeHeight = dimension(map.height !== undefined ? map.height : map.metadata && map.metadata.height, DEFAULT_HEIGHT);
        const background = hexColor(map.background !== undefined ? map.background : defaults.background, DEFAULT_BACKGROUND);
        const title = map.title || map.name || 'Weathermap';

        const nodes = Array.isArray(map.nodes) ? map.nodes.filter(node => node && typeof node === 'object') : [];
        const links = Array.isArray(map.links) ? map.links.filter(link => link && typeof link === 'object') : [];
        const nodesById = new Map();
        nodes.forEach(node => {
            if (node.id !== undefined && node.id !== null) nodesById.set(String(node.id), node);
        });

        const parts = [];
        parts.push('<?xml version="1.0" encoding="UTF-8"?>');
        parts.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${safeWidth}" height="${safeHeight}" viewBox="0 0 ${safeWidth} ${safeHeight}" role="img" aria-label="${escapeXml(title)}">`);
        parts.push(`<title>${escapeXml(title)}</title>`);
        parts.push(`<rect x="0" y="0" width="${safeWidth}" height="${safeHeight}" fill="${background}"/>`);
        parts.push('<g id="links">');
        links.forEach(link => renderLink(link, nodesById, liveData, opts, parts));
        parts.push('</g>');
        parts.push('<g id="nodes">');
        nodes.forEach(node => renderNode(node, liveData, opts, parts));
        parts.push('</g>');
        parts.push('</svg>');
        return parts.join('\n');
    }

    const api = {
        build,
        escapeXml,
        nodeStyle,
        nodeShape,
        deviceShape,
        sizeScale,
        shapeInsets,
        showSecondaryLabels,
        particleBudget,
        densePoints,
    };

    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else {
        global.WMNG = global.WMNG || {};
        global.WMNG.SvgExport = api;
    }
})(typeof window === 'undefined' ? globalThis : window);
