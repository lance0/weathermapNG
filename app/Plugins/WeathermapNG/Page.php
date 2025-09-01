<?php

namespace App\Plugins\WeathermapNG;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Page
{
    // LibreNMS v2 plugins need to output HTML directly when views aren't registered
    
    public function __invoke(Request $request)
    {
        $maps = $this->getMaps();
        $title = 'WeathermapNG - Network Weather Maps';
        
        // Output HTML directly
        echo $this->renderHtml($maps, $title);
        return '';
    }
    
    public function data(Request $request): array
    {
        return [
            'title' => 'WeathermapNG - Network Weather Maps',
            'maps' => $this->getMaps(),
            'request' => $request,
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to access the page
        return $user !== null;
    }

    private function getMaps()
    {
        // Check if our tables exist
        if (!$this->tablesExist()) {
            return collect();
        }

        try {
            return DB::table('wmng_maps')
                ->select('id', 'name', 'description', 'width', 'height', 'updated_at')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function tablesExist()
    {
        try {
            return DB::getSchemaBuilder()->hasTable('wmng_maps');
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function renderHtml($maps, $title)
    {
        ob_start();
        ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fas fa-map"></i> <?php echo htmlspecialchars($title); ?>
                                <div class="pull-right">
                                    <button class="btn btn-primary btn-sm" onclick="createNewMap()">
                                        <i class="fas fa-plus"></i> Create New Map
                                    </button>
                                </div>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <?php if (!$this->tablesExist()): ?>
                                <div class="alert alert-warning">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h4>
                                    <p>The WeathermapNG database tables have not been created yet.</p>
                                    <p>Please run the following commands:</p>
                                    <pre>cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php</pre>
                                </div>
                            <?php elseif ($maps->count() > 0): ?>
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
                                                    <a href="/plugin/v1/WeathermapNG/map/<?php echo $map->id; ?>">
                                                        <?php echo htmlspecialchars($map->name); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($map->description ?? ''); ?></td>
                                                <td><?php echo $map->width; ?>x<?php echo $map->height; ?></td>
                                                <td><?php echo $map->updated_at; ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="/plugin/v1/WeathermapNG/map/<?php echo $map->id; ?>" 
                                                           class="btn btn-primary" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="/plugin/v1/WeathermapNG/map/<?php echo $map->id; ?>/edit" 
                                                           class="btn btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button onclick="deleteMap(<?php echo $map->id; ?>)" 
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
                            
                            <hr>
                            <div class="well well-sm">
                                <h4>Plugin Access</h4>
                                <ul>
                                    <li><strong>v1 Legacy:</strong> <a href="/plugin/v1/WeathermapNG">/plugin/v1/WeathermapNG</a></li>
                                    <li><strong>v2 Modern:</strong> /plugin/WeathermapNG (this page)</li>
                                    <li><strong>Settings:</strong> <a href="/plugin/settings/WeathermapNG">/plugin/settings/WeathermapNG</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function createNewMap() {
            alert('Map creation interface coming soon!');
        }
        
        function deleteMap(mapId) {
            if (confirm('Are you sure you want to delete this map?')) {
                alert('Map deletion will be implemented soon!');
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }
}