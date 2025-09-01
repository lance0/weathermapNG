let canvas, ctx;
let nodes = [];
let selectedNode = null;
let isDragging = false;
let dragOffset = { x: 0, y: 0 };
let nodeCounter = 1;

function initCanvas() {
    canvas = document.getElementById('map-canvas');
    if (!canvas) return;

    ctx = canvas.getContext('2d');

    // Set up event listeners
    canvas.addEventListener('mousedown', handleMouseDown);
    canvas.addEventListener('mousemove', handleMouseMove);
    canvas.addEventListener('mouseup', handleMouseUp);
    canvas.addEventListener('dblclick', handleDoubleClick);

    // Initial render
    render();
}

function handleMouseDown(event) {
    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;

    // Check if clicking on a node
    for (let node of nodes) {
        const distance = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
        if (distance <= 15) { // Node radius + some tolerance
            selectedNode = node;
            isDragging = true;
            dragOffset.x = x - node.x;
            dragOffset.y = y - node.y;
            canvas.style.cursor = 'grabbing';
            return;
        }
    }

    selectedNode = null;
}

function handleMouseMove(event) {
    if (!isDragging || !selectedNode) return;

    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;

    selectedNode.x = x - dragOffset.x;
    selectedNode.y = y - dragOffset.y;

    render();
}

function handleMouseUp(event) {
    isDragging = false;
    selectedNode = null;
    canvas.style.cursor = 'default';
}

function handleDoubleClick(event) {
    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;

    // Remove node if double-clicked
    for (let i = nodes.length - 1; i >= 0; i--) {
        const node = nodes[i];
        const distance = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
        if (distance <= 15) {
            nodes.splice(i, 1);
            render();
            return;
        }
    }
}

function drawNode(node) {
    // Node shadow
    ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
    ctx.shadowBlur = 4;
    ctx.shadowOffsetX = 2;
    ctx.shadowOffsetY = 2;

    // Node body
    ctx.beginPath();
    ctx.arc(node.x, node.y, 12, 0, 2 * Math.PI);
    ctx.fillStyle = selectedNode === node ? '#007bff' : '#28a745';
    ctx.fill();

    // Node border
    ctx.shadowColor = 'transparent';
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Node label
    ctx.fillStyle = '#000';
    ctx.font = '12px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(node.label, node.x, node.y - 20);

    // Device info
    ctx.fillStyle = '#666';
    ctx.font = '10px Arial';
    ctx.fillText(`ID: ${node.id}`, node.x, node.y + 25);
}

function render() {
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw background grid
    drawGrid();

    // Draw nodes
    nodes.forEach(node => {
        drawNode(node);
    });

    // Draw connections between nodes (simple auto-connect for demo)
    if (nodes.length > 1) {
        for (let i = 0; i < nodes.length - 1; i++) {
            const node1 = nodes[i];
            const node2 = nodes[i + 1];

            ctx.beginPath();
            ctx.moveTo(node1.x, node1.y);
            ctx.lineTo(node2.x, node2.y);
            ctx.strokeStyle = '#6c757d';
            ctx.lineWidth = 1;
            ctx.setLineDash([5, 5]);
            ctx.stroke();
            ctx.setLineDash([]);
        }
    }
}

function drawGrid() {
    const gridSize = 20;
    ctx.strokeStyle = '#f0f0f0';
    ctx.lineWidth = 1;

    // Vertical lines
    for (let x = 0; x <= canvas.width; x += gridSize) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, canvas.height);
        ctx.stroke();
    }

    // Horizontal lines
    for (let y = 0; y <= canvas.height; y += gridSize) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(canvas.width, y);
        ctx.stroke();
    }
}

function clearCanvas() {
    if (confirm('Are you sure you want to clear all nodes from the canvas?')) {
        nodes = [];
        render();
    }
}

// Make functions globally available
window.initCanvas = initCanvas;
window.clearCanvas = clearCanvas;
window.addNode = function() {
    // This will be overridden by the editor template
    console.log('Add node function should be overridden');
};

// Auto-initialize if canvas exists
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('map-canvas')) {
        initCanvas();
    }
});