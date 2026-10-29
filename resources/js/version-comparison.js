// Read-only, bounded version previews. Snapshot values are text, never SVG markup or styles.
(function (global) {
    const NODE_LIMIT = 100, LINK_LIMIT = 150, ROW_LIMIT = 200;
    const NS = 'http://www.w3.org/2000/svg';
    const own = (value, key) => Object.prototype.hasOwnProperty.call(value, key);
    const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
    const numeric = value => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
        && Number.isFinite(Number(value)) ? Number(value) : null;
    const identity = value => {
        if (typeof value === 'string' && !/^\d+$/.test(value)) return null;
        const number = numeric(value);
        return number !== null && Number.isSafeInteger(number) && number > 0 ? String(number) : null;
    };
    const absent = message => ({ message });
    const notRecorded = () => absent('Unavailable — not recorded in this version');
    const cell = (record, field, missing = notRecorded) => own(record, field) ? { value: record[field] } : missing();
    const stable = value => Array.isArray(value) ? value.map(stable)
        : object(value) ? Object.fromEntries(Object.keys(value).sort().map(key => [key, stable(value[key])])) : value;
    const equal = (a, b) => JSON.stringify(stable(a)) === JSON.stringify(stable(b));

    function collection(snapshot, type) {
        const value = snapshot[type];
        if (!Array.isArray(value) && !object(value)) throw new Error('Snapshot graph is unavailable.');
        return Object.values(value);
    }

    function graph(version) {
        const snapshot = version?.config_snapshot;
        if (!object(snapshot) || !object(snapshot.map)) throw new Error('Snapshot configuration is unavailable.');
        const result = { version, snapshot, nodes: new Map(), links: new Map(), coordinates: new Map() };
        for (const type of ['nodes', 'links']) {
            const records = collection(snapshot, type);
            result[type + 'Total'] = records.length;
            for (const record of records) {
                const id = object(record) ? identity(record.id) : null;
                if (id !== null && !result[type].has(id)) result[type].set(id, record);
            }
        }
        for (const [id, node] of result.nodes) {
            const x = numeric(node.x), y = numeric(node.y);
            if (x !== null && y !== null) result.coordinates.set(id, { x, y });
        }
        return result;
    }

    function preview(graph, changes) {
        const changed = id => changes.added.has(id) || changes.removed.has(id) || changes.modified.has(id);
        const ids = [...graph.coordinates.keys()].sort((a, b) => Number(changed(b)) - Number(changed(a)) || Number(a) - Number(b));
        const points = new Map(ids.slice(0, NODE_LIMIT).map(id => [id, graph.coordinates.get(id)]));
        const links = [];
        let validLinks = 0;
        for (const [id, link] of graph.links) {
            const src = identity(link.src_node_id), dst = identity(link.dst_node_id);
            if (!graph.coordinates.has(src) || !graph.coordinates.has(dst)) continue;
            validLinks++;
            if (links.length < LINK_LIMIT && points.has(src) && points.has(dst)) links.push({ id, src, dst });
        }
        return {
            points, links,
            invalidNodes: graph.nodesTotal - graph.coordinates.size,
            omittedNodes: graph.coordinates.size - points.size,
            invalidLinks: graph.linksTotal - validLinks,
            omittedLinks: validLinks - links.length,
        };
    }

    function fitTogether(before, after) {
        const points = [...before.points.values(), ...after.points.values()];
        // Normalize before taking spans so opposite finite extremes cannot overflow.
        const magnitude = Math.max(1, ...points.flatMap(point => [Math.abs(point.x), Math.abs(point.y)]));
        const xs = points.map(point => point.x / magnitude), ys = points.map(point => point.y / magnitude);
        const minX = points.length ? Math.min(...xs) : 0, maxX = points.length ? Math.max(...xs) : 0;
        const minY = points.length ? Math.min(...ys) : 0, maxY = points.length ? Math.max(...ys) : 0;
        const span = Math.max(maxX - minX, maxY - minY);
        const dx = span ? (maxX - minX) / span : 0, dy = span ? (maxY - minY) / span : 0;
        const scale = span ? Math.min(dx ? 360 / dx : Infinity, dy ? 180 / dy : Infinity) : 0;
        for (const view of [before, after]) {
            view.points = new Map([...view.points].map(([id, point]) => [id, {
                x: 220 + (span ? ((point.x / magnitude - minX) / span - dx / 2) * scale : 0),
                y: 120 + (span ? ((point.y / magnitude - minY) / span - dy / 2) * scale : 0),
            }]));
        }
    }

    function build(data) {
        const before = graph(data.version1), after = graph(data.version2);
        const changes = {};
        for (const type of ['nodes', 'links']) {
            changes[type] = {};
            for (const state of ['added', 'removed', 'modified']) {
                const ids = data.diff?.[type + '_' + state];
                if (!Array.isArray(ids)) throw new Error('Comparison details are unavailable.');
                changes[type][state] = new Set(ids.map(identity).filter(id => id !== null));
            }
        }
        before.preview = preview(before, changes.nodes);
        after.preview = preview(after, changes.nodes);
        fitTogether(before.preview, after.preview);

        const rows = [];
        let totalRows = 0;
        const add = (entity, state, field, left, right) => {
            if (equal(left, right)) return;
            totalRows++;
            if (rows.length < ROW_LIMIT) rows.push({ entity, state: left.message === notRecorded().message || right.message === notRecorded().message ? 'unavailable' : state, field, before: left, after: right });
        };
        const a = before.snapshot.map, b = after.snapshot.map;
        for (const field of ['name', 'title', 'description', 'parent_map_id', 'width', 'height', 'background', 'raw_width', 'raw_height']) {
            add('Map', 'modified', field, cell(a, field), cell(b, field));
        }
        const aOptions = object(a.options) ? a.options : {}, bOptions = object(b.options) ? b.options : {};
        const optionKeys = [...new Set([...Object.keys(aOptions), ...Object.keys(bOptions)])].sort();
        // A missing options snapshot is unknown; a recorded null is an actual value.
        if (!optionKeys.length || (own(a, 'options') && !object(a.options)) || (own(b, 'options') && !object(b.options))) {
            add('Map', 'modified', 'options', cell(a, 'options'), cell(b, 'options'));
        }
        for (const key of optionKeys) {
            add('Map option', 'modified', key,
                own(a, 'options') ? cell(aOptions, key, () => absent('Not set')) : notRecorded(),
                own(b, 'options') ? cell(bOptions, key, () => absent('Not set')) : notRecorded());
        }
        for (const [type, fields] of [
            ['nodes', ['label', 'x', 'y', 'device_id', 'meta']],
            ['links', ['src_node_id', 'dst_node_id', 'port_id_a', 'port_id_b', 'bandwidth_bps', 'style']],
        ]) {
            for (const state of ['added', 'removed', 'modified']) {
                for (const id of changes[type][state]) {
                    const left = before[type].get(id), right = after[type].get(id);
                    for (const field of fields) {
                        add(`${type === 'nodes' ? 'Node' : 'Link'} #${id}`, state, field,
                            left ? cell(left, field) : absent('Not present'),
                            right ? cell(right, field) : absent('Not present'));
                    }
                }
            }
        }
        return { before, after, changes, rows, totalRows };
    }

    function element(tag, text, className) {
        const result = document.createElement(tag);
        if (text !== undefined) result.textContent = text;
        if (className) result.className = className;
        return result;
    }

    function svgElement(tag, attributes) {
        const result = document.createElementNS(NS, tag);
        for (const [key, value] of Object.entries(attributes)) result.setAttribute(key, String(value));
        return result;
    }

    function stateFor(id, changes) {
        for (const state of ['added', 'removed', 'modified']) if (changes[state].has(id)) return state;
        return 'unchanged';
    }

    function draw(graph, changes, direction) {
        const figure = element('figure', undefined, 'version-diff-pane');
        const versionName = graph.version.name || `Version ${graph.version.id}`;
        figure.appendChild(element('figcaption', `${direction}: ${versionName}`, 'font-weight-bold'));
        const missing = [['options', 'options'], ['description', 'description'], ['parent_map_id', 'parent map'],
            ['raw_width', 'stored width'], ['raw_height', 'stored height']]
            .filter(([field]) => !own(graph.snapshot.map, field)).map(([, label]) => label);
        if (missing.length) {
            figure.appendChild(element('p', `Unavailable — not recorded in this version: ${missing.join(', ')}.`, 'small version-diff-availability'));
        }
        const view = graph.preview;
        const svg = svgElement('svg', { viewBox: '0 0 480 240', role: 'img', 'aria-label': `${direction} topology: ${versionName}` });
        for (const link of view.links) {
            const a = view.points.get(link.src), b = view.points.get(link.dst);
            const state = stateFor(link.id, changes.links);
            const line = svgElement('line', { x1: a.x, y1: a.y, x2: b.x, y2: b.y, class: `version-diff-link version-diff-${state}`, 'data-id': link.id });
            const title = svgElement('title', {});
            title.textContent = `Link #${link.id}: ${state}`;
            line.appendChild(title);
            svg.appendChild(line);
        }
        const marks = { added: '+ ', removed: '− ', modified: '~ ', unchanged: '' };
        for (const [id, point] of view.points) {
            const state = stateFor(id, changes.nodes);
            const node = graph.nodes.get(id);
            const label = typeof node.label === 'string' ? node.label : `Node #${id}`;
            const group = svgElement('g', { class: `version-diff-node version-diff-${state}`, 'data-id': id });
            const title = svgElement('title', {});
            title.textContent = `Node #${id}: ${label} (${state})`;
            group.appendChild(title);
            group.appendChild(svgElement('circle', { cx: point.x, cy: point.y, r: state === 'unchanged' ? 4 : 6 }));
            const text = svgElement('text', { x: point.x + (point.x > 240 ? -9 : 9), y: point.y + 4, 'text-anchor': point.x > 240 ? 'end' : 'start' });
            text.textContent = marks[state] + (label.length > 24 ? label.slice(0, 24) + '…' : label);
            group.appendChild(text);
            svg.appendChild(group);
        }
        figure.appendChild(svg);
        if (!graph.nodesTotal && !graph.linksTotal) figure.appendChild(element('p', 'Empty graph.', 'small text-muted'));
        figure.appendChild(element('p', `Nodes: ${view.points.size} of ${graph.nodesTotal} shown; ${view.invalidNodes} invalid, ${view.omittedNodes} outside preview. Links: ${view.links.length} of ${graph.linksTotal} shown; ${view.invalidLinks} invalid records/endpoints, ${view.omittedLinks} outside preview.`, 'small version-diff-counts'));
        return figure;
    }

    function formatCell(cell) {
        if (own(cell, 'message')) return cell.message;
        const text = JSON.stringify(cell.value, null, 2);
        return text.length > 2000 ? text.slice(0, 2000) + '\n… (value truncated)' : text;
    }

    function render(container, data) {
        const comparison = build(data);
        container.replaceChildren();
        container.appendChild(element('p', 'Compared by saved node/link ID; editor saves may replace IDs. Topology previews use straight links; saved link bends and styling are listed below.', 'small text-muted'));
        const legend = element('p', undefined, 'version-diff-legend small');
        for (const [state, label] of [['added', '+ Added'], ['removed', '− Removed (dashed)'], ['modified', '~ Modified'], ['unchanged', 'Unchanged']]) {
            legend.appendChild(element('span', label, `version-diff-${state}`));
        }
        container.appendChild(legend);
        const previews = element('div', undefined, 'version-diff-previews');
        previews.appendChild(draw(comparison.before, comparison.changes, 'Before'));
        previews.appendChild(draw(comparison.after, comparison.changes, 'After'));
        container.appendChild(previews);
        container.appendChild(element('p', `Showing ${comparison.rows.length} of ${comparison.totalRows} field differences. Preview limits: ${NODE_LIMIT} nodes and ${LINK_LIMIT} links per version, ${ROW_LIMIT} detail rows. Limits apply to display; the comparison response contains full snapshots.`, 'small version-diff-limit'));
        if (!comparison.totalRows) {
            const invalid = [comparison.before, comparison.after].some(graph => graph.preview.invalidNodes || graph.preview.invalidLinks);
            container.appendChild(element('p', invalid ? 'Field differences may be unavailable for omitted invalid records.' : 'No recorded field differences.', 'version-diff-empty'));
            return comparison;
        }
        const wrap = element('div', undefined, 'version-diff-table-wrap');
        wrap.setAttribute('tabindex', '0');
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', 'Comparison field values');
        const table = element('table', undefined, 'table table-sm version-diff-table');
        table.appendChild(element('caption', 'Saved field values before and after; unavailable values were not recorded.'));
        const head = element('thead'), header = element('tr');
        for (const label of ['Item / change', 'Field', 'Before', 'After']) {
            const th = element('th', label);
            th.setAttribute('scope', 'col');
            header.appendChild(th);
        }
        head.appendChild(header);
        table.appendChild(head);
        const body = element('tbody');
        for (const row of comparison.rows) {
            const tr = element('tr');
            const name = element('th', `${row.entity} (${row.state})`);
            name.setAttribute('scope', 'row');
            tr.appendChild(name);
            tr.appendChild(element('td', row.field));
            for (const value of [row.before, row.after]) {
                const td = element('td');
                td.appendChild(element('pre', formatCell(value)));
                tr.appendChild(td);
            }
            body.appendChild(tr);
        }
        table.appendChild(body);
        wrap.appendChild(table);
        container.appendChild(wrap);
        return comparison;
    }

    const comparison = { build, render };
    if (typeof module !== 'undefined' && module.exports) module.exports = comparison;
    else {
        global.WMNG = global.WMNG || {};
        global.WMNG.VersionComparison = comparison;
    }
})(typeof window === 'undefined' ? globalThis : window);
