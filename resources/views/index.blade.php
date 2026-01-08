@extends('layouts.librenmsv1')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/weathermapng.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/loading.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/toast.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/a11y.css') }}">
<style>
/* ===== Light Mode (Default) ===== */
.wmng-index {
    --idx-bg: #f4f6f9;
    --idx-card-bg: #fff;
    --idx-card-border: #e9ecef;
    --idx-card-shadow: 0 2px 8px rgba(0,0,0,0.08);
    --idx-card-shadow-hover: 0 8px 24px rgba(0,0,0,0.12);
    --idx-text: #212529;
    --idx-text-muted: #6c757d;
    --idx-text-light: #adb5bd;
    --idx-preview-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --idx-stat-bg: #f8f9fa;
    --idx-stat-border: #e9ecef;
    --idx-action-bg: transparent;
    --idx-action-hover: #f8f9fa;
    --idx-action-text: #6c757d;
    --idx-action-text-hover: #212529;
    --idx-input-bg: #fff;
    --idx-input-border: #ced4da;
    --idx-badge-bg: rgba(255,255,255,0.9);
    --idx-badge-text: #495057;
    --idx-empty-icon: #dee2e6;
    --idx-header-border: #e9ecef;
}

/* ===== Dark Mode ===== */
.wmng-index.dark-theme {
    --idx-bg: #1a1d21;
    --idx-card-bg: #2c3136;
    --idx-card-border: #3d4349;
    --idx-card-shadow: 0 2px 8px rgba(0,0,0,0.3);
    --idx-card-shadow-hover: 0 8px 24px rgba(0,0,0,0.5);
    --idx-text: #e9ecef;
    --idx-text-muted: #adb5bd;
    --idx-text-light: #6c757d;
    --idx-preview-bg: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    --idx-stat-bg: #343a40;
    --idx-stat-border: #495057;
    --idx-action-bg: transparent;
    --idx-action-hover: #3d4349;
    --idx-action-text: #adb5bd;
    --idx-action-text-hover: #fff;
    --idx-input-bg: #212529;
    --idx-input-border: #495057;
    --idx-badge-bg: rgba(0,0,0,0.5);
    --idx-badge-text: #e9ecef;
    --idx-empty-icon: #495057;
    --idx-header-border: #3d4349;
}

/* ===== Base Layout ===== */
.wmng-index { min-height: 100%; }

/* ===== Page Header ===== */
.wmng-header {
    padding: 1.5rem 0 1rem;
    border-bottom: 1px solid var(--idx-header-border);
    margin-bottom: 1.5rem;
}
.wmng-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
}
.wmng-title {
    font-size: 2rem;
    font-weight: 600;
    color: var(--idx-text);
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.wmng-title i { color: #667eea; font-size: 1.75rem; }
.wmng-subtitle {
    color: var(--idx-text-muted);
    margin: 0;
    font-size: 1rem;
}
.wmng-header-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ===== Controls Bar ===== */
.wmng-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.wmng-stats {
    display: flex;
    gap: 2rem;
    color: var(--idx-text-muted);
    font-size: 1rem;
}
.wmng-stats-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.wmng-stats-item i { opacity: 0.7; font-size: 1.1rem; }
.wmng-stats-item strong {
    color: var(--idx-text);
    font-weight: 600;
    font-size: 1.1rem;
}
.wmng-filters {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.wmng-filters .form-control {
    background: var(--idx-input-bg);
    border-color: var(--idx-input-border);
    color: var(--idx-text);
    font-size: 1rem;
    padding: 0.5rem 0.75rem;
}
.wmng-filters .input-group-text {
    background: var(--idx-input-bg);
    border-color: var(--idx-input-border);
    color: var(--idx-text-muted);
}

/* ===== Map Cards ===== */
.map-card {
    background: var(--idx-card-bg);
    border: 1px solid var(--idx-card-border);
    border-radius: 12px;
    box-shadow: var(--idx-card-shadow);
    overflow: hidden;
    transition: all 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.map-card:hover {
    box-shadow: var(--idx-card-shadow-hover);
    transform: translateY(-2px);
}

/* Card Preview/Header */
.map-card-preview {
    background: var(--idx-preview-bg);
    height: 120px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.map-card-preview::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.map-card-dimensions {
    background: var(--idx-badge-bg);
    color: var(--idx-badge-text);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    backdrop-filter: blur(4px);
    z-index: 1;
}
.map-card-dimensions i { font-size: 0.9rem; opacity: 0.7; }

/* Card Body */
.map-card-body {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.map-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--idx-text);
    margin: 0 0 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.map-card-name {
    font-size: 0.9rem;
    color: var(--idx-text-light);
    margin-bottom: 1rem;
    font-family: monospace;
}

/* Stats Row */
.map-card-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.map-stat-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.95rem;
    color: var(--idx-text-muted);
    background: var(--idx-stat-bg);
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
}
.map-stat-badge i { font-size: 0.85rem; opacity: 0.7; }

/* Meta/Updated */
.map-card-meta {
    font-size: 0.9rem;
    color: var(--idx-text-light);
    margin-top: auto;
    padding-top: 0.75rem;
    border-top: 1px solid var(--idx-stat-border);
}

/* Action Bar */
.map-card-actions {
    display: flex;
    border-top: 1px solid var(--idx-card-border);
}
.map-card-action {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.85rem;
    color: var(--idx-action-text);
    text-decoration: none;
    transition: all 0.15s;
    border: none;
    background: var(--idx-action-bg);
    cursor: pointer;
    font-size: 1.1rem;
}
.map-card-action:hover {
    background: var(--idx-action-hover);
    color: var(--idx-action-text-hover);
    text-decoration: none;
}
.map-card-action.danger:hover { color: #dc3545; }
.map-card-action + .map-card-action {
    border-left: 1px solid var(--idx-card-border);
}

/* ===== Empty State ===== */
.wmng-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--idx-text-muted);
}
.wmng-empty-icon {
    font-size: 4rem;
    color: var(--idx-empty-icon);
    margin-bottom: 1.5rem;
}
.wmng-empty h3 {
    color: var(--idx-text);
    margin-bottom: 0.5rem;
}
.wmng-empty p {
    max-width: 400px;
    margin: 0 auto 1.5rem;
}

/* ===== Alerts ===== */
.wmng-index .alert {
    border-radius: 8px;
    margin-bottom: 1rem;
}

/* ===== Modal Title Icon ===== */
.modal-title i { color: #667eea; margin-right: 0.5rem; }

/* ===== Template Cards ===== */
.template-card {
    background: var(--idx-card-bg);
    border: 1px solid var(--idx-card-border);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
    cursor: pointer;
}
.template-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px var(--idx-card-shadow-hover);
    transform: translateY(-2px);
}
.template-card-icon {
    font-size: 2rem;
    color: #667eea;
    margin-bottom: 0.75rem;
}
.template-card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--idx-text);
    margin-bottom: 0.5rem;
}
.template-card-desc {
    font-size: 0.9rem;
    color: var(--idx-text-muted);
    margin-bottom: 0.75rem;
    line-height: 1.4;
}
.template-card-meta {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}
.template-card-meta .badge {
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0.3em 0.6em;
}
.template-card-btn {
    width: 100%;
}
.category-basic { background-color: #28a745; }
.category-advanced { background-color: #007bff; }
.category-custom { background-color: #6c757d; }

/* ===== Custom Tab Form ===== */
#customPane .form-control {
    background: var(--idx-input-bg);
    border-color: var(--idx-input-border);
    color: var(--idx-text);
}
#customPane label {
    color: var(--idx-text);
    font-weight: 500;
}
#customPane .form-text {
    color: var(--idx-text-muted);
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .wmng-header-top { flex-direction: column; align-items: stretch; }
    .wmng-header-actions { justify-content: flex-start; }
    .wmng-controls { flex-direction: column; align-items: stretch; }
    .wmng-stats { justify-content: center; }
    .wmng-filters { flex-direction: column; }
    .wmng-filters .form-control { width: 100%; }
}
</style>
@endpush

@section('title', 'WeathermapNG - Network Maps')

@section('content')
<div class="wmng-index">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="wmng-header">
            <div class="wmng-header-top">
                <div>
                    <h1 class="wmng-title">
                        <i class="fas fa-network-wired" aria-hidden="true"></i>
                        WeathermapNG
                    </h1>
                    <p class="wmng-subtitle">Real-time network topology visualization with live traffic data</p>
                </div>
                <div class="wmng-header-actions">
                    <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#importMapModal"
                            aria-label="Import map from file">
                        <i class="fas fa-file-import" aria-hidden="true"></i> Import
                    </button>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createMapModal"
                            aria-label="Create new map">
                        <i class="fas fa-plus" aria-hidden="true"></i> Create Map
                    </button>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="wmng-controls">
            <div class="wmng-stats">
                <div class="wmng-stats-item">
                    <i class="fas fa-map" aria-hidden="true"></i>
                    <strong id="map-count">{{ count($maps) }}</strong> maps
                </div>
                <div class="wmng-stats-item">
                    <i class="fas fa-project-diagram" aria-hidden="true"></i>
                    <strong>{{ $maps->sum(fn($m) => $m->nodes_count ?? $m->nodes()->count()) }}</strong> nodes
                </div>
                <div class="wmng-stats-item">
                    <i class="fas fa-link" aria-hidden="true"></i>
                    <strong>{{ $maps->sum(fn($m) => $m->links_count ?? $m->links()->count()) }}</strong> links
                </div>
            </div>
            <div class="wmng-filters">
                <div class="input-group" style="width: 280px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" class="form-control" id="map-search" placeholder="Search maps..." aria-label="Search maps">
                </div>
                <select class="form-control" id="map-filter" style="width: 180px;" aria-label="Sort maps">
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="nodes-desc">Most nodes</option>
                    <option value="links-desc">Most links</option>
                    <option value="size-desc">Largest</option>
                </select>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="assertive">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Maps Grid -->
        <div id="maps-container" class="row">
            @forelse($maps as $map)
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4 map-card-col"
                     data-name="{{ strtolower($map->name) }}"
                     data-title="{{ strtolower($map->title ?? $map->name) }}"
                     data-nodes="{{ $map->nodes_count ?? $map->nodes()->count() }}"
                     data-links="{{ $map->links_count ?? $map->links()->count() }}"
                     data-size="{{ ($map->width ?? 0) * ($map->height ?? 0) }}">
                    <div class="map-card">
                        <div class="map-card-preview">
                            <div class="map-card-dimensions">
                                <i class="fas fa-expand-arrows-alt" aria-hidden="true"></i>
                                {{ $map->width ?? 800 }} &times; {{ $map->height ?? 600 }}
                            </div>
                        </div>
                        <div class="map-card-body">
                            <h5 class="map-card-title" title="{{ $map->title ?? $map->name }}">
                                {{ $map->title ?? $map->name }}
                            </h5>
                            <div class="map-card-name">{{ $map->name }}</div>
                            <div class="map-card-stats">
                                <span class="map-stat-badge">
                                    <i class="fas fa-circle" aria-hidden="true"></i>
                                    {{ $map->nodes_count ?? $map->nodes()->count() }} nodes
                                </span>
                                <span class="map-stat-badge">
                                    <i class="fas fa-link" aria-hidden="true"></i>
                                    {{ $map->links_count ?? $map->links()->count() }} links
                                </span>
                            </div>
                            <div class="map-card-meta">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                Updated {{ $map->updated_at ? $map->updated_at->diffForHumans() : 'recently' }}
                            </div>
                        </div>
                        <div class="map-card-actions">
                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}"
                               class="map-card-action" target="_blank" title="View map"
                               aria-label="View map {{ $map->name }}">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </a>
                            <a href="{{ url('plugin/WeathermapNG/editor/' . $map->id) }}"
                               class="map-card-action" title="Edit map"
                               aria-label="Edit map {{ $map->name }}">
                                <i class="fas fa-edit" aria-hidden="true"></i>
                            </a>
                            <a href="{{ url('plugin/WeathermapNG/api/maps/' . $map->id . '/export?format=json') }}"
                               class="map-card-action" title="Export as JSON"
                               aria-label="Export map {{ $map->name }}">
                                <i class="fas fa-download" aria-hidden="true"></i>
                            </a>
                            <button class="map-card-action danger" title="Delete map"
                                    onclick="deleteMap({{ $map->id }}, '{{ addslashes($map->name) }}')"
                                    aria-label="Delete map {{ $map->name }}">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="wmng-empty">
                        <div class="wmng-empty-icon">
                            <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                        </div>
                        <h3>No maps yet</h3>
                        <p>Create your first network map to visualize your infrastructure with real-time traffic data.</p>
                        <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#createMapModal"
                                aria-label="Create your first map">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i>Create Your First Map
                        </button>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Filter Empty State -->
        <div id="map-filter-empty" class="wmng-empty" style="display: none;">
            <div class="wmng-empty-icon">
                <i class="fas fa-search" aria-hidden="true"></i>
            </div>
            <h3>No matching maps</h3>
            <p>Try a different search term or clear the filter.</p>
            <button class="btn btn-outline-secondary" onclick="document.getElementById('map-search').value=''; document.getElementById('map-search').dispatchEvent(new Event('input'));">
                Clear Search
            </button>
        </div>
    </div>
</div>

<!-- Create Map Modal -->
<div class="modal fade" id="createMapModal" tabindex="-1" role="dialog" aria-labelledby="createMapModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createMapModalTitle">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                    Create New Map
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs px-3 pt-2" id="createMapTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="templates-tab" data-toggle="tab" href="#templatesPane" role="tab">
                        <i class="fas fa-th-large mr-1"></i>From Template
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tab" data-toggle="tab" href="#customPane" role="tab">
                        <i class="fas fa-edit mr-1"></i>Custom
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Templates Tab -->
                <div class="tab-pane fade show active" id="templatesPane" role="tabpanel">
                    <div class="modal-body">
                        <div id="templatesLoading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="mt-2 mb-0 text-muted">Loading templates...</p>
                        </div>
                        <div id="templatesGrid" class="row" style="display: none;"></div>
                        <div id="templatesError" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>Failed to load templates. <a href="#" onclick="loadTemplates(); return false;">Retry</a></span>
                        </div>
                    </div>
                </div>

                <!-- Custom Tab -->
                <div class="tab-pane fade" id="customPane" role="tabpanel">
                    <form method="POST" action="{{ url('plugin/WeathermapNG/map') }}" id="createMapForm" novalidate>
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="map-name">Map Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="map-name" name="name" required maxlength="255"
                                       placeholder="e.g., datacenter-core" aria-required="true">
                                <small class="form-text text-muted">Unique identifier used in URLs</small>
                            </div>
                            <div class="form-group">
                                <label for="map-title">Display Title</label>
                                <input type="text" class="form-control" id="map-title" name="title" maxlength="255"
                                       placeholder="e.g., Datacenter Core Network">
                                <small class="form-text text-muted">Human-readable title shown in the UI</small>
                            </div>
                            <div class="form-row">
                                <div class="col-6">
                                    <label for="map-width">Width (px)</label>
                                    <input type="number" class="form-control" id="map-width" name="width"
                                           value="800" min="100" max="4096">
                                </div>
                                <div class="col-6">
                                    <label for="map-height">Height (px)</label>
                                    <input type="number" class="form-control" id="map-height" name="height"
                                           value="600" min="100" max="4096">
                                </div>
                            </div>
                            <small class="form-text text-muted">Canvas dimensions in pixels (100-4096)</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="createMapSubmitBtn">
                                <i class="fas fa-plus mr-1" aria-hidden="true"></i>Create Map
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Map Modal -->
<div class="modal fade" id="importMapModal" tabindex="-1" role="dialog" aria-labelledby="importMapModalTitle">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ url('plugin/WeathermapNG/api/maps/import') }}" class="modal-content" id="importMapForm" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="importMapModalTitle">
                    <i class="fas fa-file-import" aria-hidden="true"></i>
                    Import Map
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="import-file">JSON File <span class="text-danger">*</span></label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="import-file" name="file" accept=".json" required>
                        <label class="custom-file-label" for="import-file">Choose file...</label>
                    </div>
                    <small class="form-text">Select a previously exported map JSON file</small>
                </div>
                <div class="form-group">
                    <label for="import-name">Map Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="import-name" name="name" required maxlength="255"
                           placeholder="e.g., imported-map">
                    <small class="form-text">Unique identifier for the imported map</small>
                </div>
                <div class="form-group">
                    <label for="import-title">Display Title</label>
                    <input type="text" class="form-control" id="import-title" name="title" maxlength="255"
                           placeholder="e.g., Imported Network Map">
                    <small class="form-text">Optional human-readable title</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="importMapSubmitBtn">
                    <i class="fas fa-upload mr-1" aria-hidden="true"></i>Import
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (hidden) -->
<form id="deleteMapForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('scripts')
<script src="{{ asset('plugins/WeathermapNG/resources/js/ui-helpers.js') }}"></script>
<script>
// ===== Theme Detection =====
function detectTheme() {
    const container = document.querySelector('.wmng-index');
    if (!container) return;

    let isDark = null;

    // Check actual rendered background color
    const navbar = document.querySelector('.navbar, .navbar-default, .navbar-static-top, nav');
    const elementsToCheck = [navbar, document.body].filter(Boolean);

    for (const element of elementsToCheck) {
        const bg = window.getComputedStyle(element).backgroundColor;
        const rgb = bg.match(/\d+/g);
        if (rgb && rgb.length >= 3) {
            if (rgb.length === 4 && parseInt(rgb[3]) === 0) continue;
            if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;

            const brightness = (parseInt(rgb[0]) * 299 + parseInt(rgb[1]) * 587 + parseInt(rgb[2]) * 114) / 1000;
            isDark = brightness < 128;
            break;
        }
    }

    // Fallback: check for dark theme class names
    if (isDark === null) {
        const allClasses = document.body.className + ' ' + document.documentElement.className;
        if (/\bdark\b|\bnight\b|\bdark-mode\b/i.test(allClasses)) {
            isDark = true;
        }
    }

    if (isDark === null) isDark = false;

    container.classList.toggle('dark-theme', isDark);
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(detectTheme, 100);
    const observer = new MutationObserver(() => setTimeout(detectTheme, 50));
    observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] });
    observer.observe(document.head, { childList: true, subtree: true });
});

// ===== Create Map Form =====
$('#createMapForm').on('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('createMapSubmitBtn');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Creating...';

    const formData = new FormData(this);

    fetch('{{ url("plugin/WeathermapNG/map") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            WMNGToast.success('Map created successfully!');
            $('#createMapModal').modal('hide');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                location.reload();
            }
        } else {
            WMNGToast.error('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        WMNGToast.error('Error: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});

// ===== File Input Label Update =====
$('#import-file').on('change', function() {
    const fileName = this.files[0]?.name || 'Choose file...';
    $(this).next('.custom-file-label').text(fileName);
});

// ===== Import Map Form =====
$('#importMapForm').on('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('importMapSubmitBtn');
    const originalHTML = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Importing...';

    const formData = new FormData(this);

    fetch('{{ url("plugin/WeathermapNG/api/maps/import") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            WMNGToast.success('Map imported successfully!');
            $('#importMapModal').modal('hide');
            location.reload();
        } else {
            WMNGToast.error('Error: ' + (data.message || data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        WMNGToast.error('Error: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});

// ===== Delete Map =====
function deleteMap(mapId, mapName) {
    if (!confirm(`Delete map "${mapName}"? This cannot be undone.`)) return;

    const form = document.getElementById('deleteMapForm');
    form.action = '{{ url("plugin/WeathermapNG/map") }}/' + mapId;
    form.submit();
}

// ===== Search & Sort =====
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('map-search');
    const filterSelect = document.getElementById('map-filter');
    const container = document.getElementById('maps-container');
    if (!searchInput || !filterSelect || !container) return;

    const cards = Array.from(container.querySelectorAll('.map-card-col'));
    const total = cards.length;

    function sortCards(mode) {
        const sorted = [...cards].sort((a, b) => {
            const nameA = a.dataset.title || a.dataset.name || '';
            const nameB = b.dataset.title || b.dataset.name || '';
            const nodesA = parseInt(a.dataset.nodes || '0', 10);
            const nodesB = parseInt(b.dataset.nodes || '0', 10);
            const linksA = parseInt(a.dataset.links || '0', 10);
            const linksB = parseInt(b.dataset.links || '0', 10);
            const sizeA = parseInt(a.dataset.size || '0', 10);
            const sizeB = parseInt(b.dataset.size || '0', 10);

            switch (mode) {
                case 'name-desc': return nameB.localeCompare(nameA);
                case 'nodes-desc': return nodesB - nodesA;
                case 'links-desc': return linksB - linksA;
                case 'size-desc': return sizeB - sizeA;
                case 'name-asc':
                default: return nameA.localeCompare(nameB);
            }
        });
        sorted.forEach(card => container.appendChild(card));
    }

    function applyFilter() {
        const query = searchInput.value.trim().toLowerCase();
        let visible = 0;

        cards.forEach(card => {
            const text = `${card.dataset.name} ${card.dataset.title}`;
            const isMatch = text.includes(query);
            card.style.display = isMatch ? '' : 'none';
            if (isMatch) visible += 1;
        });

        const emptyState = document.getElementById('map-filter-empty');
        if (emptyState) {
            emptyState.style.display = total > 0 && visible === 0 ? '' : 'none';
        }
    }

    searchInput.addEventListener('input', applyFilter);
    filterSelect.addEventListener('change', function() {
        sortCards(this.value);
    });

    sortCards(filterSelect.value);
    applyFilter();
});

// ===== Templates Gallery =====
let templatesLoaded = false;
let templatesData = [];

function loadTemplates() {
    const loading = document.getElementById('templatesLoading');
    const grid = document.getElementById('templatesGrid');
    const error = document.getElementById('templatesError');

    loading.style.display = '';
    grid.style.display = 'none';
    error.style.display = 'none';

    fetch('{{ url("plugin/WeathermapNG/templates") }}', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to load templates');
        return response.json();
    })
    .then(data => {
        // API returns { success: true, templates: [...] }
        templatesData = Array.isArray(data) ? data : (data.templates || data.data || []);
        renderTemplates(templatesData);
        templatesLoaded = true;
        loading.style.display = 'none';
        grid.style.display = 'flex';
    })
    .catch(err => {
        console.error('Templates load error:', err);
        loading.style.display = 'none';
        error.style.display = '';
    });
}

function renderTemplates(templates) {
    const grid = document.getElementById('templatesGrid');
    grid.innerHTML = '';

    if (templates.length === 0) {
        grid.innerHTML = '<div class="col-12 text-center py-4 text-muted">No templates available.</div>';
        return;
    }

    templates.forEach(template => {
        const categoryClass = 'category-' + (template.category || 'custom');
        const nodeCount = template.config?.default_nodes?.length || 0;
        const linkCount = template.config?.default_links?.length || 0;

        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4';
        card.innerHTML = `
            <div class="template-card" onclick="selectTemplate(${template.id})">
                <div class="template-card-icon">
                    <i class="${template.icon || 'fas fa-map'}"></i>
                </div>
                <div class="template-card-title">${escapeHtml(template.title || template.name)}</div>
                <div class="template-card-desc">${escapeHtml(template.description || '')}</div>
                <div class="template-card-meta">
                    <span class="badge badge-secondary">${template.width}x${template.height}</span>
                    <span class="badge ${categoryClass}">${template.category || 'custom'}</span>
                    ${nodeCount > 0 ? `<span class="badge badge-light">${nodeCount} nodes</span>` : ''}
                    ${linkCount > 0 ? `<span class="badge badge-light">${linkCount} links</span>` : ''}
                </div>
                <button type="button" class="btn btn-primary btn-sm template-card-btn" onclick="event.stopPropagation(); selectTemplate(${template.id})">
                    <i class="fas fa-plus mr-1"></i>Use Template
                </button>
            </div>
        `;
        grid.appendChild(card);
    });
}

function selectTemplate(templateId) {
    const template = templatesData.find(t => t.id === templateId);
    if (!template) return;

    const mapName = prompt(`Create map from "${template.title}".\n\nEnter a unique map name (no spaces):`, '');
    if (!mapName || !mapName.trim()) return;

    const cleanName = mapName.trim().toLowerCase().replace(/[^a-z0-9\-_]/g, '-');

    createMapFromTemplate(templateId, cleanName);
}

function createMapFromTemplate(templateId, mapName) {
    const grid = document.getElementById('templatesGrid');
    grid.style.opacity = '0.5';
    grid.style.pointerEvents = 'none';

    fetch(`{{ url("plugin/WeathermapNG/templates") }}/${templateId}/create-map`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ name: mapName })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || data.error || 'Failed to create map');
            });
        }
        return response.json();
    })
    .then(data => {
        WMNGToast.success('Map created from template!');
        $('#createMapModal').modal('hide');
        // Redirect to editor
        const mapId = data.id || data.map_id || data.data?.id;
        if (mapId) {
            window.location.href = '{{ url("plugin/WeathermapNG/editor") }}/' + mapId;
        } else {
            location.reload();
        }
    })
    .catch(err => {
        WMNGToast.error('Error: ' + err.message);
        grid.style.opacity = '1';
        grid.style.pointerEvents = '';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load templates when modal opens
$('#createMapModal').on('shown.bs.modal', function() {
    if (!templatesLoaded) {
        loadTemplates();
    }
});
</script>
@endsection
