## Map Versioning System

The map versioning system provides version control for network maps, allowing users to save, restore, and compare different versions of their maps.

### Features

#### Core Functionality
- **Save Versions**: Create named versions with descriptions
- **Restore Versions**: Rollback to any previous version
- **Compare Versions**: Visual diff between two versions
- **Auto-Save**: Automatically save versions at intervals
- **Export Versions**: Export all versions for backup
- **Version History**: View all saved versions with metadata

#### Version Metadata
- Created timestamp
- Created by user
- Version name
- Version description
- Full snapshot of map state (nodes, links, settings)

### Configuration

#### Version Settings
- **Auto-save interval**: How often to auto-save (default: 5 minutes)
- **Max versions**: Maximum versions to keep (default: 20)
- **Retention policy**: Which versions to keep (oldest 20, newest 20, all)
- **Backup enabled**: Enable automatic backups
- **Export format**: JSON, XML, YAML
- **Compression**: Enable version data compression
- **Conflict detection**: Auto-detect version conflicts
- **Merge strategy**: How to handle merge conflicts (replace, merge)

### Version Comparison
- **Nodes**: Show added, removed, and modified nodes
- **Links**: Show added, removed, and modified links
- **Settings**: Show setting differences
- **Visual Diff**: Side-by-side comparison

### Auto-Save
- **Trigger**: Save version automatically on timer or action
- **Naming**: Auto-generated names based on timestamp
- **Description**: User-defined or auto-generated
- **Conflict handling**: Resolve naming conflicts

### API Endpoints

#### List Versions
```
GET /plugin/WeathermapNG/maps/{id}/versions
```
Returns all versions for a map.

#### Create Version
```
POST /plugin/WeathermapNG/maps/{id}/versions
Content-Type: application/json

{
  "name": "v1.0",
  "description": "Initial version"
}
```
Creates a new version with optional auto-save flag.

#### Get Version
```
GET /plugin/WeathermapNG/versions/{id}
```
Returns details of a specific version including snapshot.

#### Restore Version
```
POST /plugin/WeathermapNG/maps/{id}/versions/{versionId}/restore
```
Restores the map to a specific version.

#### Compare Versions
```
GET /plugin/WeathermapNG/maps/{id}/versions/{id}/compare/{compareId}
```
Compares two versions and returns diff.

#### Delete Version
```
DELETE /plugin/WeathermapNG/maps/{id}/versions/{versionId}
```
Deletes a specific version.

#### Export All Versions
```
GET /plugin/WeathermapNG/maps/{id}/versions/export
```
Exports all versions in specified format.

#### Auto-Save
```
POST /plugin/WeathermapNG/maps/{id}/versions/auto-save
Content-Type: application/json

{
  "name": "v1.0"
}
```
Creates a version automatically.

#### Version Settings
```
GET /plugin/WeathermapNG/maps/{id}/versions/settings
```
Returns versioning configuration options.

#### Update Settings
```
PUT /plugin/WEB/plugins/WeathermapNG/maps/{id}/versions/settings
Content-Type: application/json

{
  "auto_save_enabled": true,
  "auto_save_interval": 5,
  "max_versions": 20
}
```
Updates versioning settings.

### Use Cases

#### Scenario 1: Safe Experimentation
1. User is developing a complex map layout
2. Creates version "Experiment 1"
3. Tests the layout
4. Doesn't like it - creates "Experiment 2"
5. Restores to "Experiment 1"
6. **No data lost!**

#### Scenario 2: Audit Trail
1. User needs to see when changes were made
2. Version history shows: "Updated on 2026-01-07"
3. Can see who made changes
4. **Compliance and traceability**

#### Scenario 3: Team Collaboration
1. Multiple team members working on same map
2. Each saves versions with their name
3. "Created by: John", "Created by: Sarah"
4. Can merge changes and track contributions

#### Scenario 4: Rollback After Error
1. User accidentally deletes critical nodes
2. Previous version still exists
3. One-click restore
4. **Immediate recovery**

### Implementation Details

#### Database Schema
```sql
CREATE TABLE wmng_map_versions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    map_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    config_snapshot LONGTEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP NOT NULL,
    FOREIGN KEY (map_id) REFERENCES wmng_maps(id) ON DELETE CASCADE
);
```

#### Model
```php
class MapVersion extends Model
{
    protected $table = 'wmng_map_versions';
    protected $fillable = ['map_id', 'name', 'description', 'config_snapshot', 'created_by'];
    protected $casts = ['config_snapshot' => 'array', 'created_at' => 'datetime'];
}
```

#### Service
```php
class MapVersionService
{
    public function createVersion(Map $map, $name, $description, $autoSave = false, $userId)
    public function restoreVersion(MapVersion $version): Map
    public function getVersions(Map $map, $limit = 10)
    public function compareVersions($version1, $version2): array
    public function deleteVersionsOlderThan(MapVersion $version)
}
```

### User Interface
- **Version dropdown** in editor
- **"Save Version"** button with name input
- **"History"** modal/table view
- **"Compare"** side-by-side view
- **"Rollback"** confirmation dialog
- **Auto-save indicator** in UI

### Settings UI
- **Auto-save toggle**
- **Auto-save interval** (1, 5, 10, 30 minutes)
- **Max versions** slider (5, 10, 20, 50)
- **Retention policy** dropdown (oldest, newest, all)
- **Export format** selection (JSON, XML, YAML)
- **Compression** toggle
- **Manual cleanup** button

### Storage Options

#### Database Storage (Default)
- Fast and reliable
- Easy to backup and export
- Low overhead for small/medium maps

#### File-Based Storage (Optional)
- Good for very large maps
- Can use external backup systems
- Allows version control with Git

#### S3/Cloud Storage (Optional)
- Reliable offsite backups
- Geographic distribution
- Disaster recovery

### Security
- **Authorization**: Users can only manage their own versions
- **Audit Trail**: Created by tracking
- **Version Access**: Restore protected by ownership
- **Backup Security**: Exported versions can't be modified by others

### Performance
- **Snapshot Storage**: JSON in LONGTEXT (scalable)
- **Lazy Loading**: Version list pagination
- **Indexing**: Indexed on map_id and created_at
- **Query Optimization**: Eager loading of relationships

### Best Practices
- **Version Naming**: Use clear, descriptive names
- **Version Descriptions**: Document significant changes
- **Clean History**: Delete old versions automatically
- **Test Restores**: Verify restorable before deletion
- **Conflict Resolution**: Allow users to resolve conflicts

### Configuration

#### .env Variables
```
WEATHERMAP_AUTO_SAVE_ENABLED=true
WEATHERMAP_AUTO_SAVE_INTERVAL=5
WEATHERMAP_MAX_VERSIONS=20
WEATHERMAP_RETENTION_POLICY=oldest_20
WEATHERMAP_VERSION_STORAGE=database
WEATHERMAP_EXPORT_FORMAT=json
```

#### Config File
```php
return [
    'versioning' => [
        'enabled' => true,
        'auto_save' => [
            'enabled' => true,
            'interval' => 5,
            'max_versions' => 20,
        ],
        'retention' => 'oldest_20',
        'export' => [
            'enabled' => true,
            'format' => 'json',
            'formats' => ['json', 'xml', 'yaml'],
        ],
    ],
];
```

### Benefits
- **Zero Data Loss**: Users can always rollback
- **Audit Trail**: Full history of all changes
- **Experimentation**: Safe try without consequences
- **Collaboration**: Multiple users working together
- **Recovery**: Quick recovery from errors
- **Compliance**: Tracking for audit purposes
- **Backup**: Automatic versioning = backup
