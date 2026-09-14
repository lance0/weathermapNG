@extends('layouts.librenmsv1')

@push('styles')
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/weathermapng.css') }}">
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/loading.css') }}">
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/toast.css') }}">
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/a11y.css') }}">
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/index.css') }}">
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
                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#importMapModal"
                            aria-label="Import map from file">
                        <i class="fas fa-file-import" aria-hidden="true"></i> Import
                    </button>
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#createMapModal"
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
                <input type="text" class="form-control" id="map-search" placeholder="Search maps..." aria-label="Search maps">
                <select class="form-control" id="map-filter" aria-label="Sort maps">
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="nodes-desc">Most nodes</option>
                    <option value="links-desc">Most links</option>
                    <option value="size-desc">Largest</option>
                </select>
                <select class="form-control" id="map-tag-filter" aria-label="Filter by tag">
                    <option value="">All tags</option>
                    @foreach(collect($maps)->flatMap(fn($m) => $m->tags)->unique()->sort()->values() as $tag)
                        @if($tag !== '')
                            <option value="{{ $tag }}">{{ $tag }}</option>
                        @endif
                    @endforeach
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
                     data-size="{{ ($map->width ?? 0) * ($map->height ?? 0) }}"
                     data-tags="{{ json_encode($map->tags) }}">
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
                            @if(count($map->tags) > 0)
                                <div class="map-tags" aria-label="Map tags">
                                    @foreach($map->tags as $tag)
                                        <span class="map-tag" data-tag="{{ $tag }}">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="map-card-meta">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                Updated {{ $map->updated_at ? $map->updated_at->diffForHumans() : 'recently' }}
                            </div>
                        </div>
                        <div class="map-card-actions">
                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}"
                               class="map-card-action" target="_blank" rel="noopener noreferrer" title="View map"
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
                            <button type="button" class="map-card-action danger" title="Delete map"
                                    data-map-id="{{ $map->id }}" data-map-name="{{ $map->name }}"
                                    data-action="delete-map"
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
                        <p>Get started by creating a map from a template, importing an existing map, or building a custom topology. If the plugin is fresh, run the diagnostics page first.</p>
                        <div class="wmng-onboarding-actions">
                            <a href="{{ url('plugin/WeathermapNG/templates') }}" class="btn btn-success btn-lg"
                               aria-label="Browse map templates">
                                <i class="fas fa-th-large mr-2" aria-hidden="true"></i>Browse Templates
                            </a>
                            <button type="button" class="btn btn-outline-secondary btn-lg" data-toggle="modal" data-target="#createMapModal"
                                    aria-label="Create your first map">
                                <i class="fas fa-plus mr-2" aria-hidden="true"></i>Create Custom Map
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg" data-toggle="modal" data-target="#importMapModal"
                                    aria-label="Import an existing map">
                                <i class="fas fa-file-import mr-2" aria-hidden="true"></i>Import Map
                            </button>
                            <a href="{{ url('plugin/WeathermapNG/diagnostics') }}" class="btn btn-outline-info btn-lg"
                               aria-label="Open diagnostics">
                                <i class="fas fa-stethoscope mr-2" aria-hidden="true"></i>Diagnostics
                            </a>
                            <a href="https://github.com/lance0/weathermapNG/blob/main/docs/EMBED.md" class="btn btn-outline-primary btn-lg" target="_blank" rel="noopener noreferrer"
                               aria-label="Read embed documentation">
                                <i class="fas fa-book mr-2" aria-hidden="true"></i>Docs
                            </a>
                        </div>
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
            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('map-search').value=''; document.getElementById('map-search').dispatchEvent(new Event('input'));" aria-label="Clear search filter">
                Clear Search
            </button>
        </div>
    </div>
</div>

<!-- Create Map Modal -->
<div class="modal fade" id="createMapModal" tabindex="-1" role="dialog" aria-labelledby="createMapModalTitle">
    <div class="modal-dialog modal-dialog-centered modal-lg" id="createMapDialog">
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
                <div class="tab-pane active" id="templatesPane" role="tabpanel">
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
                <div class="tab-pane" id="customPane" role="tabpanel">
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
                            <button type="submit" class="btn btn-success" id="createMapSubmitBtn">
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
        <form method="POST" action="{{ url('plugin/WeathermapNG/api/import') }}" class="modal-content" id="importMapForm" enctype="multipart/form-data">
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

<!-- Delete Map Confirmation Modal -->
<div class="modal fade" id="deleteMapModal" tabindex="-1" role="dialog" aria-labelledby="deleteMapModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMapModalTitle">
                    <i class="fas fa-trash-alt text-danger" aria-hidden="true"></i>
                    Delete Map
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="deleteMapModalBody" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteMapBtn">Delete Map</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form (hidden) -->
<form id="deleteMapForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('scripts')
<script src="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('js/wmng-common.js') }}"></script>
<script src="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('js/ui-helpers.js') }}"></script>
<link rel="stylesheet" href="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('css/index.css') }}">
<script>
    // Server-rendered bootstrap: URLs the index JS actions call.
    window.WMNG = window.WMNG || {};
    window.WMNG.IndexUrls = {
        map: '{{ url("plugin/WeathermapNG/map") }}',
        importApi: '{{ url("plugin/WeathermapNG/api/import") }}',
        templates: '{{ url("plugin/WeathermapNG/templates") }}',
        editorBase: '{{ url("plugin/WeathermapNG/editor") }}',
    };
    if (window.WMNG && typeof WMNG.ensureUiHelpers === 'function') {
        WMNG.ensureUiHelpers();
    }
    if (window.WMNG && typeof WMNG.observeTheme === 'function') {
        WMNG.observeTheme('.wmng-index');
    }
</script>
<script src="{{ \LibreNMS\Plugins\WeathermapNG\Asset::url('js/index-app.js') }}"></script>
@endsection
