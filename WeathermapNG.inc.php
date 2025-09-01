<?php
/**
 * WeathermapNG LibreNMS Plugin - v1 Legacy Support
 * 
 * This file provides v1 plugin compatibility for LibreNMS
 * Accessible at: /plugin/v1/WeathermapNG
 */

// Handle different page views
if (isset($_GET['other'])) {
    // Editor view
    if (preg_match('/^editor\/(\d+)$/', $_GET['other'], $matches)) {
        $mapId = intval($matches[1]);
        include __DIR__ . '/views/editor.php';
        exit;
    }
    
    // View map
    if (preg_match('/^view\/(\d+)$/', $_GET['other'], $matches)) {
        $mapId = intval($matches[1]);
        include __DIR__ . '/views/view.php';
        exit;
    }
}

// Handle AJAX requests
if (isset($_GET['other']) && strpos($_GET['other'], 'ajax/') === 0) {
    header('Content-Type: application/json');
    
    // Debug logging
    error_log("WeathermapNG AJAX request: " . $_GET['other']);
    error_log("POST data: " . json_encode($_POST));
    
    $action = str_replace('ajax/', '', $_GET['other']);
    
    switch ($action) {
        case 'create-map':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $width = intval($_POST['width'] ?? 800);
                $height = intval($_POST['height'] ?? 600);
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Map name is required', 'debug' => 'Name was empty']);
                    exit;
                }
                
                try {
                    // Check if name already exists
                    $existing = dbFetchCell("SELECT COUNT(*) FROM wmng_maps WHERE name = ?", [$name]);
                    if ($existing > 0) {
                        echo json_encode(['success' => false, 'message' => 'A map with this name already exists']);
                        exit;
                    }
                    
                    // Insert the new map
                    $result = dbInsert([
                        'name' => $name,
                        'description' => $description,
                        'width' => $width,
                        'height' => $height,
                        'options' => json_encode(['background' => '#ffffff']),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'wmng_maps');
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'id' => $result]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create map']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            exit;
            
        case 'delete-map':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid map ID']);
                    exit;
                }
                
                try {
                    $result = dbDelete('wmng_maps', 'id = ?', [$id]);
                    echo json_encode(['success' => $result > 0]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            exit;
            
        case 'save-map':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = intval($_POST['id'] ?? 0);
                $nodes = json_decode($_POST['nodes'] ?? '[]', true);
                $links = json_decode($_POST['links'] ?? '[]', true);
                
                try {
                    // Delete existing nodes and links
                    dbDelete('wmng_nodes', 'map_id = ?', [$id]);
                    dbDelete('wmng_links', 'map_id = ?', [$id]);
                    
                    // Insert new nodes
                    $nodeIdMap = [];
                    foreach ($nodes as $node) {
                        $oldId = $node['id'];
                        $newId = dbInsert([
                            'map_id' => $id,
                            'label' => $node['label'],
                            'x' => $node['x'],
                            'y' => $node['y'],
                            'device_id' => $node['device_id'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'wmng_nodes');
                        $nodeIdMap[$oldId] = $newId;
                    }
                    
                    // Insert new links with mapped node IDs
                    foreach ($links as $link) {
                        dbInsert([
                            'map_id' => $id,
                            'src_node_id' => $nodeIdMap[$link['src_node_id']] ?? $link['src_node_id'],
                            'dst_node_id' => $nodeIdMap[$link['dst_node_id']] ?? $link['dst_node_id'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'wmng_links');
                    }
                    
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            exit;
            
        case 'get-map-data':
            $id = intval($_GET['id'] ?? 0);
            try {
                $nodes = dbFetchRows("SELECT n.*, d.hostname, d.status 
                                     FROM wmng_nodes n 
                                     LEFT JOIN devices d ON n.device_id = d.device_id 
                                     WHERE n.map_id = ?", [$id]);
                                     
                $links = dbFetchRows("SELECT l.*, 
                                     p1.ifInOctets_rate as in_rate_a, p1.ifOutOctets_rate as out_rate_a,
                                     p2.ifInOctets_rate as in_rate_b, p2.ifOutOctets_rate as out_rate_b
                                     FROM wmng_links l
                                     LEFT JOIN ports p1 ON l.port_id_a = p1.port_id
                                     LEFT JOIN ports p2 ON l.port_id_b = p2.port_id
                                     WHERE l.map_id = ?", [$id]);
                                     
                echo json_encode(['success' => true, 'nodes' => $nodes, 'links' => $links]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

// Check if tables exist
function weathermapng_tables_exist() {
    try {
        $result = dbFetchCell("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'wmng_maps'");
        return $result > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Get all maps
function weathermapng_get_maps() {
    if (!weathermapng_tables_exist()) {
        return [];
    }
    
    try {
        return dbFetchRows("SELECT id, name, description, width, height, updated_at FROM wmng_maps ORDER BY name");
    } catch (Exception $e) {
        return [];
    }
}

// Main plugin output
$maps = weathermapng_get_maps();
$plugin_dir = '/plugins/WeathermapNG';

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fas fa-map"></i> WeathermapNG - Network Weather Maps
                        <div class="pull-right">
                            <button class="btn btn-primary btn-sm" onclick="createNewMap()">
                                <i class="fas fa-plus"></i> Create New Map
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if (weathermapng_tables_exist()): ?>
                        <?php if (count($maps) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Size</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($maps as $map): ?>
                                        <tr>
                                            <td>
                                                <a href="/plugin/v1/WeathermapNG/view/<?php echo $map['id']; ?>">
                                                    <?php echo htmlspecialchars($map['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($map['description'] ?? ''); ?></td>
                                            <td><?php echo $map['width']; ?>x<?php echo $map['height']; ?></td>
                                            <td><?php echo $map['updated_at']; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/plugin/v1/WeathermapNG/view/<?php echo $map['id']; ?>" 
                                                       class="btn btn-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="/plugin/v1/WeathermapNG/editor/<?php echo $map['id']; ?>" 
                                                       class="btn btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="deleteMap(<?php echo $map['id']; ?>)" 
                                                            class="btn btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                No weather maps have been created yet. 
                                <a href="#" onclick="createNewMap()" class="alert-link">Create your first map</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h4><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h4>
                            <p>The WeathermapNG database tables have not been created yet.</p>
                            <p>Please run the following commands:</p>
                            <pre>cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php</pre>
                            <p>Or manually import the SQL:</p>
                            <pre>mysql -u librenms -p librenms < database/schema.sql</pre>
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="well well-sm">
                        <h4>Plugin Information</h4>
                        <ul>
                            <li><strong>Version:</strong> 1.0.0</li>
                            <li><strong>Status:</strong> <span class="label label-success">Active</span></li>
                            <li><strong>Documentation:</strong> <a href="https://github.com/lance0/weathermapNG" target="_blank">GitHub Repository</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="createMapModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Create New Map</h4>
            </div>
            <div class="modal-body">
                <form id="createMapForm">
                    <div class="form-group">
                        <label for="mapName">Map Name</label>
                        <input type="text" class="form-control" id="mapName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="mapDescription">Description</label>
                        <textarea class="form-control" id="mapDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mapWidth">Width (px)</label>
                                <input type="number" class="form-control" id="mapWidth" name="width" value="800" min="400" max="2000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mapHeight">Height (px)</label>
                                <input type="number" class="form-control" id="mapHeight" name="height" value="600" min="300" max="1500">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateMap()">Create Map</button>
            </div>
        </div>
    </div>
</div>

<script>
// Ensure we use the correct base URL
var baseUrl = window.location.protocol + '//' + window.location.host;

function createNewMap() {
    $('#createMapModal').modal('show');
}

function submitCreateMap() {
    var formData = {
        name: $('#mapName').val(),
        description: $('#mapDescription').val(),
        width: $('#mapWidth').val(),
        height: $('#mapHeight').val(),
        _token: $('meta[name="csrf-token"]').attr('content') || ''
    };
    
    if (!formData.name) {
        alert('Please enter a map name');
        return;
    }
    
    // Create the map directly in the database
    $.ajax({
        url: baseUrl + '/plugin/v1/WeathermapNG/ajax/create-map',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('Success response:', response);
            if (response.success) {
                alert('Map created successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.message || 'Failed to create map'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            alert('Failed to create map. Error: ' + error + '\nCheck console for details.');
        }
    });
}

function deleteMap(mapId) {
    if (confirm('Are you sure you want to delete this map?')) {
        $.ajax({
            url: baseUrl + '/plugin/v1/WeathermapNG/ajax/delete-map',
            method: 'POST',
            data: { 
                id: mapId,
                _token: $('meta[name="csrf-token"]').attr('content') || ''
            },
            success: function(response) {
                if (response.success) {
                    alert('Map deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to delete map'));
                }
            },
            error: function() {
                alert('Failed to delete map. Please check the console for errors.');
            }
        });
    }
}
</script>