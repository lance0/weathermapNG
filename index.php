<?php
/**
 * WeathermapNG v2 Plugin Entry Point
 * 
 * This file serves as the main entry point for the v2 plugin system
 * Accessible at: /plugin/WeathermapNG
 */

// Get the LibreNMS app instance if available
$app = app();

// Check if we have database access
function weathermapng_db_ready() {
    try {
        return \Illuminate\Support\Facades\Schema::hasTable('wmng_maps');
    } catch (\Exception $e) {
        return false;
    }
}

// Get all maps
function weathermapng_get_maps() {
    if (!weathermapng_db_ready()) {
        return collect();
    }
    
    try {
        return \Illuminate\Support\Facades\DB::table('wmng_maps')
            ->select('id', 'name', 'description', 'width', 'height', 'updated_at')
            ->orderBy('name')
            ->get();
    } catch (\Exception $e) {
        return collect();
    }
}

// Main plugin data
$title = 'WeathermapNG - Network Weather Maps';
$maps = weathermapng_get_maps();
$plugin_version = '1.0.0';

// Include the main view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    
    <!-- Include WeathermapNG CSS -->
    <link href="/plugins/WeathermapNG/css/weathermapng.css" rel="stylesheet">
    
    <style>
        .weathermap-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        
        .map-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .map-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }
        
        .map-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .map-card-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .map-card-body {
            padding: 1rem;
        }
        
        .map-card-footer {
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Hero Section -->
        <div class="weathermap-hero">
            <div class="text-center">
                <h1 class="display-4">
                    <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($title); ?>
                </h1>
                <p class="lead">Real-time network topology visualization and monitoring</p>
                <div class="action-buttons justify-content-center">
                    <button class="btn btn-light btn-lg" onclick="createNewMap()">
                        <i class="fas fa-plus-circle"></i> Create New Map
                    </button>
                    <a href="/plugin/settings/WeathermapNG" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <button class="btn btn-outline-light btn-lg" onclick="showHelp()">
                        <i class="fas fa-question-circle"></i> Help
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $maps->count(); ?></div>
                <div class="stat-label">Total Maps</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="activeNodes">0</div>
                <div class="stat-label">Active Nodes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="activeLinks">0</div>
                <div class="stat-label">Active Links</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <span class="text-success">●</span> Online
                </div>
                <div class="stat-label">Plugin Status</div>
            </div>
        </div>

        <!-- Maps Grid -->
        <?php if ($maps->count() > 0): ?>
            <h2 class="mb-4">Your Network Maps</h2>
            <div class="map-grid">
                <?php foreach ($maps as $map): ?>
                    <div class="map-card">
                        <div class="map-card-header">
                            <h4>
                                <i class="fas fa-map"></i> 
                                <?php echo htmlspecialchars($map->name); ?>
                            </h4>
                        </div>
                        <div class="map-card-body">
                            <p class="text-muted">
                                <?php echo htmlspecialchars($map->description ?: 'No description'); ?>
                            </p>
                            <div class="d-flex justify-content-between text-sm text-muted">
                                <span><i class="fas fa-expand"></i> <?php echo $map->width; ?>x<?php echo $map->height; ?>px</span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M j, H:i', strtotime($map->updated_at)); ?></span>
                            </div>
                        </div>
                        <div class="map-card-footer">
                            <a href="/plugin/v1/WeathermapNG/view/<?php echo $map->id; ?>" class="btn btn-primary btn-sm flex-fill">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="/plugin/v1/WeathermapNG/editor/<?php echo $map->id; ?>" class="btn btn-warning btn-sm flex-fill">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button onclick="deleteMap(<?php echo $map->id; ?>)" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>No Weather Maps Yet</h3>
                <p class="text-muted">Create your first network weather map to visualize your infrastructure</p>
                <button class="btn btn-primary btn-lg" onclick="createNewMap()">
                    <i class="fas fa-plus-circle"></i> Create Your First Map
                </button>
            </div>
        <?php endif; ?>

        <!-- Database Setup Warning -->
        <?php if (!weathermapng_db_ready()): ?>
            <div class="alert alert-warning mt-4">
                <h4><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h4>
                <p>The WeathermapNG database tables need to be created.</p>
                <pre>cd /opt/librenms/html/plugins/WeathermapNG && php database/setup.php</pre>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Map Modal -->
    <div id="createMapModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Create New Weather Map</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="createMapForm">
                        <div class="form-group">
                            <label for="mapName">Map Name</label>
                            <input type="text" class="form-control" id="mapName" name="name" required 
                                   placeholder="e.g., Data Center Network">
                        </div>
                        <div class="form-group">
                            <label for="mapDescription">Description</label>
                            <textarea class="form-control" id="mapDescription" name="description" rows="3"
                                      placeholder="Describe what this map visualizes"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mapWidth">Width (pixels)</label>
                                    <input type="number" class="form-control" id="mapWidth" name="width" 
                                           value="1200" min="600" max="2000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mapHeight">Height (pixels)</label>
                                    <input type="number" class="form-control" id="mapHeight" name="height" 
                                           value="800" min="400" max="1500">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Template</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                <label class="btn btn-outline-primary active flex-fill">
                                    <input type="radio" name="template" value="blank" checked> Blank Canvas
                                </label>
                                <label class="btn btn-outline-primary flex-fill">
                                    <input type="radio" name="template" value="star"> Star Topology
                                </label>
                                <label class="btn btn-outline-primary flex-fill">
                                    <input type="radio" name="template" value="mesh"> Mesh Network
                                </label>
                                <label class="btn btn-outline-primary flex-fill">
                                    <input type="radio" name="template" value="tree"> Tree Structure
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitCreateMap()">
                        <i class="fas fa-plus-circle"></i> Create Map
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Count active nodes and links
    function updateStats() {
        fetch('/plugin/v1/WeathermapNG?ajax_action=get-stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('activeNodes').textContent = data.nodes || 0;
                    document.getElementById('activeLinks').textContent = data.links || 0;
                }
            })
            .catch(error => console.error('Failed to fetch stats:', error));
    }

    function createNewMap() {
        $('#createMapModal').modal('show');
    }

    function submitCreateMap() {
        const formData = new FormData(document.getElementById('createMapForm'));
        formData.append('ajax_action', 'create-map');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        fetch('/plugin/v1/WeathermapNG', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#createMapModal').modal('hide');
                // Redirect to editor
                window.location.href = '/plugin/v1/WeathermapNG/editor/' + data.id;
            } else {
                alert('Error: ' + (data.message || 'Failed to create map'));
            }
        })
        .catch(error => {
            alert('Error creating map: ' + error);
        });
    }

    function deleteMap(mapId) {
        if (!confirm('Are you sure you want to delete this map? This cannot be undone.')) {
            return;
        }

        const formData = new FormData();
        formData.append('id', mapId);
        formData.append('ajax_action', 'delete-map');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        fetch('/plugin/v1/WeathermapNG', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete map'));
            }
        })
        .catch(error => {
            alert('Error deleting map: ' + error);
        });
    }

    function showHelp() {
        alert(`WeathermapNG Help

1. Creating Maps:
   - Click "Create New Map" to start
   - Choose a template or start with blank canvas
   - Set dimensions based on your network size

2. Editing Maps:
   - Click "Edit" on any map card
   - Add nodes by clicking the canvas
   - Connect nodes to create links
   - Assign LibreNMS devices to nodes

3. Viewing Maps:
   - Click "View" to see live data
   - Links change color based on traffic
   - Auto-refreshes every 60 seconds

4. Best Practices:
   - Keep maps under 50 nodes for performance
   - Use descriptive names and labels
   - Group related devices together
   - Set appropriate refresh intervals

For more help, visit the documentation.`);
    }

    // Update stats on load
    document.addEventListener('DOMContentLoaded', updateStats);
    </script>
</body>
</html>