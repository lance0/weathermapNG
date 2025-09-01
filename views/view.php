<?php
/**
 * WeathermapNG Map View - v1 Plugin
 */

// Get map details
$map = dbFetchRow("SELECT * FROM wmng_maps WHERE id = ?", [$mapId]);
if (!$map) {
    echo "<div class='alert alert-danger'>Map not found!</div>";
    exit;
}

// Get nodes for this map
$nodes = dbFetchRows("SELECT n.*, d.hostname, d.status 
                      FROM wmng_nodes n 
                      LEFT JOIN devices d ON n.device_id = d.device_id 
                      WHERE n.map_id = ? 
                      ORDER BY n.id", [$mapId]);

// Get links with traffic data
$links = dbFetchRows("SELECT l.*, 
                             p1.ifInOctets_rate as in_rate_a, p1.ifOutOctets_rate as out_rate_a,
                             p2.ifInOctets_rate as in_rate_b, p2.ifOutOctets_rate as out_rate_b
                      FROM wmng_links l
                      LEFT JOIN ports p1 ON l.port_id_a = p1.port_id
                      LEFT JOIN ports p2 ON l.port_id_b = p2.port_id
                      WHERE l.map_id = ?
                      ORDER BY l.id", [$mapId]);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fas fa-map"></i> <?php echo htmlspecialchars($map['name']); ?>
                        <div class="pull-right">
                            <a href="/plugin/v1/WeathermapNG" class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <a href="/plugin/v1/WeathermapNG/editor/<?php echo $mapId; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-info btn-sm" onclick="refreshMap()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    <div style="text-align: center;">
                        <canvas id="mapCanvas" 
                                width="<?php echo $map['width']; ?>" 
                                height="<?php echo $map['height']; ?>"
                                style="border: 1px solid #333; background: white;">
                        </canvas>
                    </div>
                    
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            <div class="well well-sm">
                                <strong>Legend:</strong>
                                <span style="display: inline-block; width: 20px; height: 10px; background: #00ff00; margin: 0 5px;"></span> 0-30% utilization
                                <span style="display: inline-block; width: 20px; height: 10px; background: #ffff00; margin: 0 5px;"></span> 30-70% utilization
                                <span style="display: inline-block; width: 20px; height: 10px; background: #ff9900; margin: 0 5px;"></span> 70-90% utilization
                                <span style="display: inline-block; width: 20px; height: 10px; background: #ff0000; margin: 0 5px;"></span> 90%+ utilization
                                <span style="display: inline-block; width: 20px; height: 10px; background: #999999; margin: 0 5px;"></span> No data
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

// Get link color based on utilization
function getLinkColor(link) {
    // Calculate max utilization
    var maxRate = Math.max(
        link.in_rate_a || 0,
        link.out_rate_a || 0,
        link.in_rate_b || 0,
        link.out_rate_b || 0
    );
    
    if (maxRate == 0) return '#999999'; // No data
    
    // Assuming 1Gbps links for now (can be made dynamic)
    var utilization = (maxRate * 8) / 1000000000 * 100; // Convert to percentage
    
    if (utilization > 90) return '#ff0000';
    if (utilization > 70) return '#ff9900';
    if (utilization > 30) return '#ffff00';
    return '#00ff00';
}

// Draw the map
function drawMap() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw links with colors based on utilization
    ctx.lineWidth = 3;
    links.forEach(function(link) {
        var srcNode = nodes.find(n => n.id == link.src_node_id);
        var dstNode = nodes.find(n => n.id == link.dst_node_id);
        if (srcNode && dstNode) {
            ctx.strokeStyle = getLinkColor(link);
            ctx.beginPath();
            ctx.moveTo(srcNode.x, srcNode.y);
            ctx.lineTo(dstNode.x, dstNode.y);
            ctx.stroke();
            
            // Draw bandwidth label
            if (link.in_rate_a || link.out_rate_a) {
                var midX = (srcNode.x + dstNode.x) / 2;
                var midY = (srcNode.y + dstNode.y) / 2;
                ctx.fillStyle = '#000';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                var rate = Math.max(link.in_rate_a || 0, link.out_rate_a || 0);
                var rateStr = rate > 1000000 ? (rate/1000000).toFixed(1) + ' Mbps' : (rate/1000).toFixed(1) + ' Kbps';
                ctx.fillText(rateStr, midX, midY - 5);
            }
        }
    });
    
    // Draw nodes with status colors
    nodes.forEach(function(node) {
        // Node color based on device status
        if (node.status == 1) {
            ctx.fillStyle = '#00cc00'; // Up
        } else if (node.status === 0) {
            ctx.fillStyle = '#ff0000'; // Down
        } else {
            ctx.fillStyle = '#0066cc'; // No device assigned
        }
        
        ctx.beginPath();
        ctx.arc(node.x, node.y, 12, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw border
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 1;
        ctx.stroke();
        
        // Draw label
        ctx.fillStyle = '#000';
        ctx.font = 'bold 12px Arial';
        ctx.textAlign = 'center';
        var label = node.hostname || node.label;
        ctx.fillText(label, node.x, node.y - 18);
    });
    
    // Draw timestamp
    ctx.fillStyle = '#666';
    ctx.font = '10px Arial';
    ctx.textAlign = 'right';
    var now = new Date();
    ctx.fillText('Last updated: ' + now.toLocaleString(), canvas.width - 10, canvas.height - 10);
}

// Refresh map data
function refreshMap() {
    $.ajax({
        url: '/plugin/v1/WeathermapNG',
        method: 'GET',
        data: { 
            id: mapId,
            ajax_action: 'get-map-data'
        },
        success: function(response) {
            if (response.success) {
                nodes = response.nodes;
                links = response.links;
                drawMap();
            }
        },
        error: function() {
            console.error('Failed to refresh map data');
        }
    });
}

// Auto-refresh every 60 seconds
setInterval(refreshMap, 60000);

// Initial draw
drawMap();

// Show device info on hover
canvas.addEventListener('mousemove', function(e) {
    var rect = canvas.getBoundingClientRect();
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;
    
    var hoveredNode = null;
    nodes.forEach(function(node) {
        var dist = Math.sqrt(Math.pow(x - node.x, 2) + Math.pow(y - node.y, 2));
        if (dist <= 12) {
            hoveredNode = node;
        }
    });
    
    if (hoveredNode && hoveredNode.hostname) {
        canvas.title = hoveredNode.hostname + ' - ' + (hoveredNode.status == 1 ? 'Up' : 'Down');
    } else {
        canvas.title = '';
    }
});
</script>