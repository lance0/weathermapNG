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

// Get links with traffic data and bandwidth
$links = dbFetchRows("SELECT l.*, 
                             p1.ifInOctets_rate as in_rate_a, p1.ifOutOctets_rate as out_rate_a,
                             p2.ifInOctets_rate as in_rate_b, p2.ifOutOctets_rate as out_rate_b
                      FROM wmng_links l
                      LEFT JOIN ports p1 ON l.port_id_a = p1.port_id
                      LEFT JOIN ports p2 ON l.port_id_b = p2.port_id
                      WHERE l.map_id = ?
                      ORDER BY l.id", [$mapId]);

// Ensure bandwidth_bps is included in the links data
foreach ($links as &$link) {
    if (!isset($link['bandwidth_bps'])) {
        $link['bandwidth_bps'] = null;
    }
}
unset($link);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fas fa-map"></i> <?php echo htmlspecialchars($map['name']); ?>
                        <div class="pull-right btn-group btn-group-sm" role="group" aria-label="Map actions">
                            <a href="/plugin/v1/WeathermapNG" class="btn btn-default">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <a href="/plugin/v1/WeathermapNG/editor/<?php echo $mapId; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-info" onclick="refreshMap()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    <div style="text-align: center;">
                        <canvas id="mapCanvas"
                                class="map-canvas"
                                width="<?php echo $map['width']; ?>" 
                                height="<?php echo $map['height']; ?>">
                        </canvas>
                    </div>
                    
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12">
                            <div class="well well-sm">
                                <strong>Legend:</strong>
                                <div class="map-legend">
                                    <span><span class="status-indicator status-traffic-low"></span> 0-30% utilization</span>
                                    <span><span class="status-indicator status-traffic-mid"></span> 30-70% utilization</span>
                                    <span><span class="status-indicator status-traffic-high"></span> 70-90% utilization</span>
                                    <span><span class="status-indicator status-traffic-critical"></span> 90%+ utilization</span>
                                    <span><span class="status-indicator status-traffic-none"></span> No data</span>
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

// Get link color based on utilization
function getLinkColor(link) {
    // Calculate max utilization
    var maxRate = Math.max(
        link.in_rate_a || 0,
        link.out_rate_a || 0,
        link.in_rate_b || 0,
        link.out_rate_b || 0
    );
    
    if (maxRate == 0) return '#6c757d'; // No data
    
    // Use configured bandwidth or default to 1Gbps
    var bandwidth = link.bandwidth_bps || 1000000000;
    var utilization = (maxRate * 8) / bandwidth * 100; // Convert to percentage
    
    if (utilization > 90) return '#dc3545';
    if (utilization > 70) return '#fd7e14';
    if (utilization > 30) return '#ffc107';
    return '#28a745';
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
                ctx.fillStyle = '#212529';
                ctx.font = '10px "Segoe UI", "Helvetica Neue", Arial, sans-serif';
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
            ctx.fillStyle = '#28a745'; // Up
        } else if (node.status === 0) {
            ctx.fillStyle = '#dc3545'; // Down
        } else {
            ctx.fillStyle = '#6c757d'; // No device assigned
        }
        
        ctx.beginPath();
        ctx.arc(node.x, node.y, 12, 0, 2 * Math.PI);
        ctx.fill();
        
        // Draw border
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 1;
        ctx.stroke();
        
        // Draw label
        ctx.fillStyle = '#212529';
        ctx.font = '600 12px "Segoe UI", "Helvetica Neue", Arial, sans-serif';
        ctx.textAlign = 'center';
        var label = node.hostname || node.label;
        ctx.fillText(label, node.x, node.y - 18);
    });
    
    // Draw timestamp
    ctx.fillStyle = '#6c757d';
    ctx.font = '10px "Segoe UI", "Helvetica Neue", Arial, sans-serif';
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
