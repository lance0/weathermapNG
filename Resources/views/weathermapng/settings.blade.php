@extends('layouts.app')

@section('title', 'WeathermapNG Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-cog"></i> WeathermapNG Settings</h1>
            <p class="text-muted">Configure global settings for network weather maps</p>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <!-- Settings Navigation -->
            <div class="card">
                <div class="card-body">
                    <div class="nav flex-column nav-pills" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#general-settings" type="button">
                            <i class="fas fa-sliders-h"></i> General
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#display-settings" type="button">
                            <i class="fas fa-palette"></i> Display
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#snmp-settings" type="button">
                            <i class="fas fa-network-wired"></i> SNMP
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#performance-settings" type="button">
                            <i class="fas fa-tachometer-alt"></i> Performance
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#advanced-settings" type="button">
                            <i class="fas fa-tools"></i> Advanced
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#backup-settings" type="button">
                            <i class="fas fa-database"></i> Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <form id="settings-form" method="POST" action="{{ url('plugin/WeathermapNG/api/settings') }}">
                @csrf
                
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">General Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Default Map Width</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="default_width" 
                                                   value="{{ config('weathermapng.default_width', 800) }}" 
                                                   min="400" max="4096">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Default Map Height</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="default_height" 
                                                   value="{{ config('weathermapng.default_height', 600) }}" 
                                                   min="300" max="2048">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Auto-Refresh Interval</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="refresh_interval" 
                                                   value="{{ config('weathermapng.refresh_interval', 60) }}" 
                                                   min="5" max="600">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                        <small class="text-muted">How often to refresh map data (5-600 seconds)</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Data Retention</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="data_retention" 
                                                   value="{{ config('weathermapng.data_retention', 30) }}" 
                                                   min="1" max="365">
                                            <span class="input-group-text">days</span>
                                        </div>
                                        <small class="text-muted">How long to keep historical data</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Time Zone</label>
                                    <select class="form-select" name="timezone">
                                        @foreach(timezone_identifiers_list() as $tz)
                                            <option value="{{ $tz }}" {{ config('weathermapng.timezone') == $tz ? 'selected' : '' }}>
                                                {{ $tz }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_animations" 
                                               {{ config('weathermapng.enable_animations', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Animations</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_tooltips" 
                                               {{ config('weathermapng.enable_tooltips', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Tooltips</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_minimap" 
                                               {{ config('weathermapng.enable_minimap', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Show Minimap</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display Settings -->
                    <div class="tab-pane fade" id="display-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Display Settings</h5>
                            </div>
                            <div class="card-body">
                                <h6>Traffic Thresholds</h6>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Warning Threshold</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="threshold_warning" 
                                                   value="{{ config('weathermapng.thresholds.warning', 50) }}" 
                                                   min="1" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Critical Threshold</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="threshold_critical" 
                                                   value="{{ config('weathermapng.thresholds.critical', 80) }}" 
                                                   min="1" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Severe Threshold</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="threshold_severe" 
                                                   value="{{ config('weathermapng.thresholds.severe', 95) }}" 
                                                   min="1" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <h6>Color Scheme</h6>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Normal</label>
                                        <input type="color" class="form-control form-control-color" name="color_normal" 
                                               value="{{ config('weathermapng.colors.normal', '#28a745') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Warning</label>
                                        <input type="color" class="form-control form-control-color" name="color_warning" 
                                               value="{{ config('weathermapng.colors.warning', '#ffc107') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Critical</label>
                                        <input type="color" class="form-control form-control-color" name="color_critical" 
                                               value="{{ config('weathermapng.colors.critical', '#fd7e14') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Severe</label>
                                        <input type="color" class="form-control form-control-color" name="color_severe" 
                                               value="{{ config('weathermapng.colors.severe', '#dc3545') }}">
                                    </div>
                                </div>
                                
                                <h6>Node Display</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Default Node Size</label>
                                        <input type="range" class="form-range" name="node_size" 
                                               value="{{ config('weathermapng.node_size', 40) }}" 
                                               min="20" max="80" id="node-size-slider">
                                        <small class="text-muted">Size: <span id="node-size-value">40</span>px</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Label Font Size</label>
                                        <input type="range" class="form-range" name="label_size" 
                                               value="{{ config('weathermapng.label_size', 12) }}" 
                                               min="8" max="20" id="label-size-slider">
                                        <small class="text-muted">Size: <span id="label-size-value">12</span>px</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Link Style</label>
                                    <select class="form-select" name="link_style">
                                        <option value="straight" {{ config('weathermapng.link_style') == 'straight' ? 'selected' : '' }}>Straight</option>
                                        <option value="curved" {{ config('weathermapng.link_style') == 'curved' ? 'selected' : '' }}>Curved</option>
                                        <option value="orthogonal" {{ config('weathermapng.link_style') == 'orthogonal' ? 'selected' : '' }}>Orthogonal</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="show_bandwidth" 
                                               {{ config('weathermapng.show_bandwidth', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Show Bandwidth Labels</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="show_percentages" 
                                               {{ config('weathermapng.show_percentages', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Show Usage Percentages</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SNMP Settings -->
                    <div class="tab-pane fade" id="snmp-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">SNMP Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> SNMP is used as a fallback when LibreNMS API data is unavailable
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="snmp_enabled" id="snmp-enabled"
                                               {{ config('weathermapng.snmp.enabled', false) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable SNMP Fallback</label>
                                    </div>
                                </div>
                                
                                <div id="snmp-options" style="{{ !config('weathermapng.snmp.enabled') ? 'display: none;' : '' }}">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">SNMP Version</label>
                                            <select class="form-select" name="snmp_version">
                                                <option value="2c" {{ config('weathermapng.snmp.version') == '2c' ? 'selected' : '' }}>v2c</option>
                                                <option value="3" {{ config('weathermapng.snmp.version') == '3' ? 'selected' : '' }}>v3</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Community String</label>
                                            <input type="text" class="form-control" name="snmp_community" 
                                                   value="{{ config('weathermapng.snmp.community', 'public') }}">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Timeout</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="snmp_timeout" 
                                                       value="{{ config('weathermapng.snmp.timeout', 1000000) }}" 
                                                       min="100000" max="10000000">
                                                <span class="input-group-text">μs</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Retries</label>
                                            <input type="number" class="form-control" name="snmp_retries" 
                                                   value="{{ config('weathermapng.snmp.retries', 3) }}" 
                                                   min="1" max="10">
                                        </div>
                                    </div>
                                    
                                    <h6>SNMPv3 Settings</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Security Name</label>
                                            <input type="text" class="form-control" name="snmpv3_sec_name" 
                                                   value="{{ config('weathermapng.snmp.v3.sec_name', '') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Security Level</label>
                                            <select class="form-select" name="snmpv3_sec_level">
                                                <option value="noAuthNoPriv">noAuthNoPriv</option>
                                                <option value="authNoPriv">authNoPriv</option>
                                                <option value="authPriv">authPriv</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Settings -->
                    <div class="tab-pane fade" id="performance-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Performance Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Polling Interval</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="polling_interval" 
                                                   value="{{ config('weathermapng.polling_interval', 300) }}" 
                                                   min="60" max="3600">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                        <small class="text-muted">How often to collect data from devices</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cache TTL</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="cache_ttl" 
                                                   value="{{ config('weathermapng.cache_ttl', 60) }}" 
                                                   min="10" max="600">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                        <small class="text-muted">How long to cache API responses</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Max Concurrent Polls</label>
                                        <input type="number" class="form-control" name="max_concurrent_polls" 
                                               value="{{ config('weathermapng.max_concurrent_polls', 10) }}" 
                                               min="1" max="50">
                                        <small class="text-muted">Number of devices to poll simultaneously</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">RRD Step</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="rrd_step" 
                                                   value="{{ config('weathermapng.rrd_step', 300) }}" 
                                                   min="60" max="3600">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                        <small class="text-muted">RRD database step interval</small>
                                    </div>
                                </div>
                                
                                <h6>Resource Limits</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Memory Limit</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="memory_limit" 
                                                   value="{{ config('weathermapng.memory_limit', 256) }}" 
                                                   min="128" max="2048">
                                            <span class="input-group-text">MB</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Execution Time Limit</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="time_limit" 
                                                   value="{{ config('weathermapng.time_limit', 300) }}" 
                                                   min="30" max="3600">
                                            <span class="input-group-text">seconds</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_compression" 
                                               {{ config('weathermapng.enable_compression', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Data Compression</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_lazy_loading" 
                                               {{ config('weathermapng.enable_lazy_loading', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Lazy Loading</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="tab-pane fade" id="advanced-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Advanced Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="debug_mode" 
                                               {{ config('weathermapng.debug', false) ? 'checked' : '' }}>
                                        <label class="form-check-label">Debug Mode</label>
                                        <small class="text-muted d-block">Enable detailed logging and debugging information</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Log Level</label>
                                    <select class="form-select" name="log_level">
                                        <option value="error" {{ config('weathermapng.log_level') == 'error' ? 'selected' : '' }}>Error</option>
                                        <option value="warning" {{ config('weathermapng.log_level') == 'warning' ? 'selected' : '' }}>Warning</option>
                                        <option value="info" {{ config('weathermapng.log_level') == 'info' ? 'selected' : '' }}>Info</option>
                                        <option value="debug" {{ config('weathermapng.log_level') == 'debug' ? 'selected' : '' }}>Debug</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">API Rate Limit</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="api_rate_limit" 
                                               value="{{ config('weathermapng.api_rate_limit', 60) }}" 
                                               min="10" max="1000">
                                        <span class="input-group-text">requests/minute</span>
                                    </div>
                                </div>
                                
                                <h6>Export Settings</h6>
                                <div class="mb-3">
                                    <label class="form-label">Default Export Format</label>
                                    <select class="form-select" name="export_format">
                                        <option value="json" {{ config('weathermapng.export_format') == 'json' ? 'selected' : '' }}>JSON</option>
                                        <option value="xml" {{ config('weathermapng.export_format') == 'xml' ? 'selected' : '' }}>XML</option>
                                        <option value="csv" {{ config('weathermapng.export_format') == 'csv' ? 'selected' : '' }}>CSV</option>
                                        <option value="png" {{ config('weathermapng.export_format') == 'png' ? 'selected' : '' }}>PNG Image</option>
                                        <option value="pdf" {{ config('weathermapng.export_format') == 'pdf' ? 'selected' : '' }}>PDF</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Custom CSS</label>
                                    <textarea class="form-control" name="custom_css" rows="5" 
                                              placeholder="/* Add custom CSS here */">{{ config('weathermapng.custom_css', '') }}</textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Webhook URL</label>
                                    <input type="url" class="form-control" name="webhook_url" 
                                           value="{{ config('weathermapng.webhook_url', '') }}" 
                                           placeholder="https://example.com/webhook">
                                    <small class="text-muted">Send notifications to this URL when maps are updated</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup Settings -->
                    <div class="tab-pane fade" id="backup-settings">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Backup & Recovery</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="auto_backup" 
                                               {{ config('weathermapng.auto_backup', true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Automatic Backups</label>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Backup Schedule</label>
                                        <select class="form-select" name="backup_schedule">
                                            <option value="daily" {{ config('weathermapng.backup_schedule') == 'daily' ? 'selected' : '' }}>Daily</option>
                                            <option value="weekly" {{ config('weathermapng.backup_schedule') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                            <option value="monthly" {{ config('weathermapng.backup_schedule') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Backup Retention</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="backup_retention" 
                                                   value="{{ config('weathermapng.backup_retention', 30) }}" 
                                                   min="7" max="365">
                                            <span class="input-group-text">days</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Backup Location</label>
                                    <input type="text" class="form-control" name="backup_path" 
                                           value="{{ config('weathermapng.backup_path', storage_path('backups/weathermapng')) }}">
                                </div>
                                
                                <hr>
                                
                                <h6>Manual Actions</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary" onclick="createBackup()">
                                        <i class="fas fa-download"></i> Create Backup Now
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="restoreBackup()">
                                        <i class="fas fa-upload"></i> Restore from Backup
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="resetToDefaults()">
                                        <i class="fas fa-undo"></i> Reset to Defaults
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span id="save-status" class="text-muted"></span>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="previewSettings()">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Settings Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="preview-content"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Enable/disable SNMP options
document.getElementById('snmp-enabled')?.addEventListener('change', function() {
    document.getElementById('snmp-options').style.display = this.checked ? 'block' : 'none';
});

// Update slider values
document.getElementById('node-size-slider')?.addEventListener('input', function() {
    document.getElementById('node-size-value').textContent = this.value;
});

document.getElementById('label-size-slider')?.addEventListener('input', function() {
    document.getElementById('label-size-value').textContent = this.value;
});

// Save settings
document.getElementById('settings-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('save-status').innerHTML = 
                '<i class="fas fa-check-circle text-success"></i> Settings saved successfully';
            setTimeout(() => {
                document.getElementById('save-status').innerHTML = '';
            }, 3000);
        } else {
            alert('Error saving settings: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error saving settings: ' + error);
    });
});

// Preview settings
function previewSettings() {
    const formData = new FormData(document.getElementById('settings-form'));
    const settings = {};
    
    for (let [key, value] of formData.entries()) {
        settings[key] = value;
    }
    
    document.getElementById('preview-content').innerHTML = 
        '<pre>' + JSON.stringify(settings, null, 2) + '</pre>';
    
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

// Create backup
function createBackup() {
    if (!confirm('Create a backup of all WeathermapNG data?')) return;
    
    fetch('{{ url("plugin/WeathermapNG/api/backup/create") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Backup created successfully: ' + data.filename);
        } else {
            alert('Error creating backup: ' + data.message);
        }
    });
}

// Restore backup
function restoreBackup() {
    if (!confirm('This will restore WeathermapNG data from a backup. Continue?')) return;
    
    // In production, this would show a file picker or list of available backups
    alert('Restore functionality would be implemented here');
}

// Reset to defaults
function resetToDefaults() {
    if (!confirm('This will reset all settings to their default values. Are you sure?')) return;
    
    fetch('{{ url("plugin/WeathermapNG/api/settings/reset") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Settings reset to defaults');
            location.reload();
        } else {
            alert('Error resetting settings: ' + data.message);
        }
    });
}
</script>
@endsection

@section('styles')
<style>
.nav-pills .nav-link {
    color: #6c757d;
    border-radius: 0.25rem;
    margin-bottom: 0.25rem;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.nav-pills .nav-link i {
    width: 20px;
    margin-right: 0.5rem;
}

.form-control-color {
    width: 100%;
    height: 40px;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
</style>
@endsection