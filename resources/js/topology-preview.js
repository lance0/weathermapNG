// Shared bounded renderer for saved maps (node IDs) and templates (original array indices).
(function(global) {
    const NODE_LIMIT = 100;
    const LINK_LIMIT = 150;
    const NS = 'http://www.w3.org/2000/svg';
    const number = value => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
        && Number.isFinite(Number(value)) ? Number(value) : null;

    function templateGraph(template) {
        const nodes = Array.isArray(template.config?.default_nodes) ? template.config.default_nodes : [];
        const links = Array.isArray(template.config?.default_links) ? template.config.default_links : [];
        return {
            nodes: nodes.slice(0, NODE_LIMIT).map((node, id) => ({ id, x: node?.x, y: node?.y })),
            links: links.slice(0, LINK_LIMIT).map(link => ({ src: link?.src_node_idx, dst: link?.dst_node_idx })),
            capped: nodes.length > NODE_LIMIT || links.length > LINK_LIMIT,
        };
    }

    function render(container, graph) {
        container.replaceChildren();
        const rawNodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
        const rawLinks = Array.isArray(graph?.links) ? graph.links : [];
        const points = new Map();
        rawNodes.slice(0, NODE_LIMIT).forEach(node => {
            const id = number(node?.id), x = number(node?.x), y = number(node?.y);
            if (id !== null && Number.isSafeInteger(id) && x !== null && y !== null && !points.has(id)) points.set(id, { x, y });
        });
        const capped = graph?.capped === true || rawNodes.length > NODE_LIMIT || rawLinks.length > LINK_LIMIT;
        if (points.size) {
            // Normalize first so finite extreme coordinates cannot overflow the fit calculations.
            const magnitude = Math.max(1, ...Array.from(points.values()).flatMap(point => [Math.abs(point.x), Math.abs(point.y)]));
            const normalized = Array.from(points.values(), point => ({ x: point.x / magnitude, y: point.y / magnitude }));
            const minX = Math.min(...normalized.map(point => point.x)), maxX = Math.max(...normalized.map(point => point.x));
            const minY = Math.min(...normalized.map(point => point.y)), maxY = Math.max(...normalized.map(point => point.y));
            const span = Math.max(maxX - minX, maxY - minY);
            const dx = span ? (maxX - minX) / span : 0, dy = span ? (maxY - minY) / span : 0;
            const fit = span ? Math.min(dx ? 296 / dx : Infinity, dy ? 116 / dy : Infinity) : 0;
            let index = 0;
            points.forEach(point => {
                const normalizedPoint = normalized[index++];
                point.x = 160 + (span ? ((normalizedPoint.x - minX) / span - dx / 2) * fit : 0);
                point.y = 70 + (span ? ((normalizedPoint.y - minY) / span - dy / 2) * fit : 0);
            });
            const svg = document.createElementNS(NS, 'svg');
            svg.setAttribute('viewBox', '0 0 320 140');
            svg.setAttribute('aria-hidden', 'true');
            rawLinks.slice(0, LINK_LIMIT).forEach(link => {
                const source = points.get(number(link?.src)), target = points.get(number(link?.dst));
                if (!source || !target) return;
                const line = document.createElementNS(NS, 'line');
                Object.entries({ x1: source.x, y1: source.y, x2: target.x, y2: target.y }).forEach(([name, value]) => line.setAttribute(name, value.toFixed(2)));
                svg.appendChild(line);
            });
            points.forEach(point => {
                const circle = document.createElementNS(NS, 'circle');
                circle.setAttribute('cx', point.x.toFixed(2));
                circle.setAttribute('cy', point.y.toFixed(2));
                circle.setAttribute('r', '3');
                svg.appendChild(circle);
            });
            container.appendChild(svg);
        }
        if (!points.size || capped) {
            const note = document.createElement('span');
            note.className = 'topology-preview-note';
            note.textContent = points.size ? 'Overview' : 'No topology';
            container.appendChild(note);
        }
    }
    const preview = { render, templateGraph };
    if (typeof module !== 'undefined' && module.exports) module.exports = preview;
    else {
        global.WMNG = global.WMNG || {};
        global.WMNG.TopologyPreview = preview;
    }
})(typeof window === 'undefined' ? globalThis : window);
