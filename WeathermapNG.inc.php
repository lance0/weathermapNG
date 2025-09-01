<?php
/**
 * WeathermapNG LibreNMS Plugin - v1 Legacy Support
 * 
 * This file provides v1 plugin compatibility for LibreNMS
 * Accessible at: /plugin/v1/WeathermapNG
 */

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
                                                <a href="plugin/v1/WeathermapNG/map/<?php echo $map['id']; ?>">
                                                    <?php echo htmlspecialchars($map['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($map['description'] ?? ''); ?></td>
                                            <td><?php echo $map['width']; ?>x<?php echo $map['height']; ?></td>
                                            <td><?php echo $map['updated_at']; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="plugin/v1/WeathermapNG/map/<?php echo $map['id']; ?>" 
                                                       class="btn btn-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="plugin/v1/WeathermapNG/map/<?php echo $map['id']; ?>/edit" 
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

<script>
function createNewMap() {
    // TODO: Implement map creation dialog
    alert('Map creation interface coming soon!');
}

function deleteMap(mapId) {
    if (confirm('Are you sure you want to delete this map?')) {
        // TODO: Implement map deletion
        alert('Map deletion will be implemented soon!');
    }
}
</script>