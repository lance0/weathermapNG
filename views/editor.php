<?php
/**
 * WeathermapNG Map Editor - v1 Plugin
 */

// Get map details
$map = dbFetchRow("SELECT * FROM wmng_maps WHERE id = ?", [$mapId]);
if (!$map) {
    echo "<div class='alert alert-danger'>Map not found!</div>";
    exit;
}

// Get nodes for this map
$nodes = dbFetchRows("SELECT * FROM wmng_nodes WHERE map_id = ? ORDER BY id", [$mapId]);

// Get links for this map
$links = dbFetchRows("SELECT * FROM wmng_links WHERE map_id = ? ORDER BY id", [$mapId]);

// Get devices for dropdown
$devices = dbFetchRows("SELECT device_id, hostname, sysName FROM devices ORDER BY hostname");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fas fa-edit"></i> Edit Map: <?php echo htmlspecialchars($map['name']); ?>
                        <div class="pull-right">
                            <a href="/plugin/v1/WeathermapNG" class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button class="btn btn-success btn-sm" onclick="saveMap()">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <!-- Canvas -->
                        <div class="col-md-9">
                            <div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <canvas id="mapCanvas" 
                                        width="<?php echo $map['width']; ?>" 
                                        height="<?php echo $map['height']; ?>"
                                        style="border: 1px solid #333; background: white; cursor: crosshair;">
                                </canvas>
                            </div>
                        </div>
                        
                        <!-- Tools -->
                        <div class="col-md-3">
                            <div class="panel panel-info">
                                <div class="panel-heading">Tools</div>
                                <div class="panel-body">
                                    <button class="btn btn-primary btn-block" onclick="addNode()">
                                        <i class="fas fa-plus"></i> Add Node
                                    </button>
                                    <button class="btn btn-info btn-block" onclick="addLink()">
                                        <i class="fas fa-link"></i> Add Link
                                    </button>
                                    <button class="btn btn-warning btn-block" onclick="clearSelection()">
                                        <i class="fas fa-times"></i> Clear Selection
                                    </button>
                                    <hr>
                                    <h4>Devices</h4>
                                    <select id="deviceSelect" class="form-control">
                                        <option value="">-- Select Device --</option>
                                        <?php foreach ($devices as $device): ?>
                                            <option value="<?php echo $device['device_id']; ?>">
                                                <?php echo htmlspecialchars($device['hostname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-success btn-block" onclick="assignDevice()">
                                        Assign to Selected Node
                                    </button>
                                </div>
                            </div>
                            
                            <div class="panel panel-default">
                                <div class="panel-heading">Map Info</div>
                                <div class="panel-body">
                                    <p><strong>Size:</strong> <?php echo $map['width']; ?>x<?php echo $map['height']; ?>px</p>
                                    <p><strong>Nodes:</strong> <span id="nodeCount"><?php echo count($nodes); ?></span></p>
                                    <p><strong>Links:</strong> <span id="linkCount"><?php echo count($links); ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var mapId = <?php echo $mapId; ?>;
var canvas = document.getElementById('mapCanvas');
var ctx = canvas.getContext('2d');
var nodes = <?php echo json_encode($nodes); ?>;
var links = <?php echo json_encode($links); ?>;
var selectedNode = null;
var isAddingNode = false;
var isAddingLink = false;
var linkStart = null;

// Draw the map
function drawMap() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw links
    ctx.strokeStyle = '#666';
    ctx.lineWidth = 2;
    links.forEach(function(link) {
        var srcNode = nodes.find(n => n.id == link.src_node_id);
        var dstNode = nodes.find(n => n.id == link.dst_node_id);
        if (srcNode && dstNode) {
            ctx.beginPath();
            ctx.moveTo(srcNode.x, srcNode.y);
            ctx.lineTo(dstNode.x, dstNode.y);
            ctx.stroke();
        }
    });
    
    // Draw nodes
    nodes.forEach(function(node) {
        ctx.fillStyle = node == selectedNode ? '#ff0000' : '#0066cc';
        ctx.beginPath();
        ctx.arc(node.x, node.y, 10, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw label
        ctx.fillStyle = '#000';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(node.label, node.x, node.y - 15);
    });
}

// Canvas click handler
canvas.addEventListener('click', function(e) {
    var rect = canvas.getBoundingClientRect();
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;
    
    if (isAddingNode) {
        // Add new node
        var label = prompt('Node label:');
        if (label) {
            var newNode = {
                id: 'new_' + Date.now(),
                map_id: mapId,
                label: label,
                x: x,
                y: y,
                device_id: null
            };
            nodes.push(newNode);
            drawMap();
            document.getElementById('nodeCount').textContent = nodes.length;
        }
        isAddingNode = false;
        canvas.style.cursor = 'crosshair';
    } else if (isAddingLink) {
        // Find clicked node
        var clickedNode = null;
        nodes.forEach(function(node) {
            var dist = Math.sqrt(Math.pow(x - node.x, 2) + Math.pow(y - node.y, 2));
            if (dist <= 10) {
                clickedNode = node;
            }
        });
        
        if (clickedNode) {
            if (!linkStart) {
                linkStart = clickedNode;
                alert('Click on destination node');
            } else {
                // Create link
                var newLink = {
                    id: 'new_' + Date.now(),
                    map_id: mapId,
                    src_node_id: linkStart.id,
                    dst_node_id: clickedNode.id
                };
                links.push(newLink);
                drawMap();
                document.getElementById('linkCount').textContent = links.length;
                
                linkStart = null;
                isAddingLink = false;
                canvas.style.cursor = 'crosshair';
            }
        }
    } else {
        // Select node
        selectedNode = null;
        nodes.forEach(function(node) {
            var dist = Math.sqrt(Math.pow(x - node.x, 2) + Math.pow(y - node.y, 2));
            if (dist <= 10) {
                selectedNode = node;
            }
        });
        drawMap();
    }
});

function addNode() {
    isAddingNode = true;
    isAddingLink = false;
    canvas.style.cursor = 'copy';
    alert('Click on the canvas to add a node');
}

function addLink() {
    isAddingLink = true;
    isAddingNode = false;
    linkStart = null;
    canvas.style.cursor = 'pointer';
    alert('Click on source node, then destination node');
}

function clearSelection() {
    selectedNode = null;
    isAddingNode = false;
    isAddingLink = false;
    linkStart = null;
    canvas.style.cursor = 'crosshair';
    drawMap();
}

function assignDevice() {
    if (!selectedNode) {
        alert('Please select a node first');
        return;
    }
    var deviceId = document.getElementById('deviceSelect').value;
    if (deviceId) {
        selectedNode.device_id = deviceId;
        alert('Device assigned to node');
    }
}

function saveMap() {
    $.ajax({
        url: '/plugin/v1/WeathermapNG/ajax/save-map',
        method: 'POST',
        data: {
            id: mapId,
            nodes: JSON.stringify(nodes),
            links: JSON.stringify(links),
            _token: $('meta[name="csrf-token"]').attr('content') || ''
        },
        success: function(response) {
            if (response.success) {
                alert('Map saved successfully!');
            } else {
                alert('Error: ' + (response.message || 'Failed to save map'));
            }
        },
        error: function() {
            alert('Failed to save map');
        }
    });
}

// Initial draw
drawMap();
</script>