@extends('layouts.librenmsv1')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/weathermapng.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/loading.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/toast.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/WeathermapNG/resources/css/a11y.css') }}">
@endpush

@section('title', 'WeathermapNG - Network Maps')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ url('plugin/WeathermapNG') }}">Plugins</a></li>
                            <li class="breadcrumb-item active" aria-current="page">WeathermapNG</li>
                        </ol>
                    </nav>
                    <h1 class="mb-1"><i class="fas fa-network-wired" aria-hidden="true"></i> WeathermapNG</h1>
                    <p class="text-muted mb-0">Topology maps with live device and link utilization.</p>
                </div>
                <div class="mt-2 mt-md-0">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#createMapModal"
                            aria-label="Create new map">
                        <i class="fas fa-plus" aria-hidden="true"></i> Create New Map
                    </button>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                <div class="text-muted small mb-2 mb-md-0">
                    <span id="map-count">{{ count($maps) }}</span> maps
                    <span class="mx-1">•</span>
                    <span id="map-filter-count">Showing {{ count($maps) }} of {{ count($maps) }}</span>
                </div>
                <div class="d-flex flex-column flex-md-row">
                    <div class="input-group input-group-sm mb-2 mb-md-0 mr-md-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="map-search" placeholder="Search maps" aria-label="Search maps">
                    </div>
                    <select class="form-control form-control-sm" id="map-filter" aria-label="Sort maps">
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="nodes-desc">Most nodes</option>
                        <option value="links-desc">Most links</option>
                        <option value="size-desc">Largest canvas</option>
                    </select>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert" aria-live="polite">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close success alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="assertive">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close error alert"></button>
                </div>
            @endif

            <div id="maps-container" class="row">
                @forelse($maps as $map)
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card map-card h-100 shadow-sm"
                             data-name="{{ strtolower($map->name) }}"
                             data-title="{{ strtolower($map->title ?? $map->name) }}"
                             data-nodes="{{ $map->nodes_count ?? $map->nodes()->count() }}"
                             data-links="{{ $map->links_count ?? $map->links()->count() }}"
                             data-size="{{ ($map->width ?? 0) * ($map->height ?? 0) }}">
                            <div class="card-header bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-map"></i> {{ $map->title ?? $map->name }}
                                    </h5>
                                    <span class="badge badge-light map-stat">
                                        <i class="fas fa-vector-square"></i> {{ $map->width }} x {{ $map->height }}
                                    </span>
                                </div>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex flex-wrap gap-2 text-muted small mb-4">
                                    <span class="map-stat"><i class="fas fa-id-badge"></i> {{ $map->name }}</span>
                                    <span class="map-stat"><i class="fas fa-project-diagram"></i> {{ $map->nodes_count ?? $map->nodes()->count() }} nodes</span>
                                    <span class="map-stat"><i class="fas fa-link"></i> {{ $map->links_count ?? $map->links()->count() }} links</span>
                                </div>
                                <div class="mt-auto">
                                    <div class="btn-group btn-group-sm w-100 map-card-actions" role="group" aria-label="Map actions">
                                        <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}"
                                           class="btn btn-outline-primary btn-sm" target="_blank"
                                           aria-label="View map {{ $map->name }}">
                                            <i class="fas fa-external-link-alt" aria-hidden="true"></i> View
                                        </a>
                                        <a href="{{ url('plugin/WeathermapNG/editor/' . $map->id) }}"
                                           class="btn btn-outline-secondary btn-sm"
                                           aria-label="Edit map {{ $map->name }}">
                                            <i class="fas fa-edit" aria-hidden="true"></i> Edit
                                        </a>
                                        <a href="{{ url('plugin/WeathermapNG/api/maps/' . $map->id . '/export?format=json') }}"
                                           class="btn btn-outline-info btn-sm"
                                           aria-label="Export map {{ $map->name }}">
                                            <i class="fas fa-download" aria-hidden="true"></i> Export
                                        </a>
                                        <form method="POST" action="{{ url('plugin/WeathermapNG/map/' . $map->id) }}" class="d-inline" onsubmit="return confirm('Delete this map?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm"
                                                    aria-label="Delete map {{ $map->name }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-map"></i></div>
                            <h3>No maps yet</h3>
                            <p>Create your first network map to start visualizing devices and links.</p>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#createMapModal"
                                    aria-label="Create first map">
                                <i class="fas fa-plus" aria-hidden="true"></i> Create Your First Map
                            </button>
                        </div>
                    </div>
                @endforelse
            </div>
            <div id="map-filter-empty" class="empty-state mt-3" style="display: none;">
                <div class="empty-state-icon"><i class="fas fa-filter"></i></div>
                <h3>No matching maps</h3>
                <p>Try a different search term or sorting option.</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Map Modal -->
<div class="modal fade" id="createMapModal" tabindex="-1" role="dialog" aria-labelledby="createMapModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ url('plugin/WeathermapNG/map') }}" class="modal-content" id="createMapForm" novalidate>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="createMapModalTitle">Create New Map</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="map-name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="map-name" name="name" required maxlength="255"
                           aria-required="true" aria-describedby="name-help"
                           placeholder="Enter unique map identifier">
                    <small id="name-help" class="form-text text-muted">Unique identifier for the map (URL-safe)</small>
                </div>
                <div class="mb-3">
                    <label for="map-title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="map-title" name="title" maxlength="255"
                           aria-describedby="title-help"
                           placeholder="Enter display title">
                    <small id="title-help" class="form-text text-muted">Display title shown to users</small>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label for="map-width" class="form-label">Width</label>
                        <input type="number" class="form-control" id="map-width" name="width" value="800" min="100" max="4096"
                               aria-describedby="width-help"
                               placeholder="800">
                        <small id="width-help" class="form-text text-muted">Canvas width in pixels (100-4096)</small>
                    </div>
                    <div class="col-6">
                        <label for="map-height" class="form-label">Height</label>
                        <input type="number" class="form-control" id="map-height" name="height" value="600" min="100" max="4096"
                               aria-describedby="height-help"
                               placeholder="600">
                        <small id="height-help" class="form-text text-muted">Canvas height in pixels (100-4096)</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"
                        aria-label="Cancel map creation">Cancel</button>
                <button type="submit" class="btn btn-primary" id="createMapSubmitBtn"
                        aria-label="Create new map">
                    Create
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Embed Code Modal -->
<div class="modal fade" id="embedModal" tabindex="-1" role="dialog" aria-labelledby="embedModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="embedModalTitle">Embed Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <p>Copy and paste this code to embed map:</p>
                <div class="form-group">
                    <label for="embedCode">HTML Code:</label>
                    <textarea class="form-control" id="embedCode" rows="4" readonly
                              aria-describedby="embedCode-help"
                              placeholder="HTML embed code will appear here"></textarea>
                    <small id="embedCode-help" class="form-text text-muted">Copy this code to embed map in other sites</small>
                </div>
                <div class="form-group mt-3">
                    <label for="iframeCode">Iframe Code:</label>
                    <textarea class="form-control" id="iframeCode" rows="2" readonly
                              aria-describedby="iframeCode-help"
                              placeholder="Iframe code will appear here"></textarea>
                    <small id="iframeCode-help" class="form-text text-muted">Simple iframe embed</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"
                        aria-label="Close embed code modal">Close</button>
                <button type="button" class="btn btn-primary" id="copyEmbedCodeBtn"
                        aria-label="Copy HTML code to clipboard">
                    Copy HTML
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/WeathermapNG/resources/js/ui-helpers.js') }}"></script>
<script>
// Handle create map form submission
$('#createMapForm').on('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('createMapSubmitBtn');
    const originalBtnText = submitBtn.textContent;

    // Show loading state
    submitBtn.classList.add('btn-loading');
    submitBtn.innerHTML = '<span class="spinner-border-sm"></span> Creating...';
    WMNGLoading.show('Creating map...');

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
            WMNGA11y.announce('Map created successfully', 'polite');

            $('#createMapModal').modal('hide');

            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                location.reload();
            }
        } else {
            WMNGToast.error('Error creating map: ' + (data.message || 'Unknown error'));
            WMNGA11y.announce('Failed to create map', 'assertive');
        }
    })
    .catch(error => {
        WMNGToast.error('Error creating map: ' + error.message);
        WMNGA11y.announce('Network error creating map', 'assertive');
    })
    .finally(() => {
        // Remove loading state
        submitBtn.classList.remove('btn-loading');
        submitBtn.innerHTML = originalBtnText;
        WMNGLoading.hide();
    });
});

// Auto-refresh simple stats if present on page (optional)
document.addEventListener('DOMContentLoaded', function() {
    const refresh = () => {
        fetch('{{ url('plugin/WeathermapNG/health/stats') }}')
            .then(r => r.json())
            .then(d => {
                const mapCount = document.getElementById('map-count');
                if (mapCount && d && typeof d.maps !== 'undefined') {
                    mapCount.textContent = d.maps;
                }
            }).catch(() => {});
    };
    refresh();
    setInterval(refresh, 30000);
});

function showEmbedCode(mapId) {
    const baseUrl = '{{ url("/") }}';
    const embedUrl = `${baseUrl}/plugin/WeathermapNG/embed/${mapId}`;

    const htmlCode = `<div style="width: 100%; height: 400px; border: 1px solid #ccc;">\n    <iframe src="${embedUrl}" width="100%" height="100%" frameborder="0" aria-label="Network map"></iframe>\n</div>`;

    const iframeCode = `<iframe src="${embedUrl}" width="800" height="600" frameborder="0" aria-label="Network map"></iframe>`;

    document.getElementById('embedCode').value = htmlCode;
    document.getElementById('iframeCode').value = iframeCode;

    $('#embedModal').modal('show');
}

function copyEmbedCode() {
    const textarea = document.getElementById('embedCode');

    try {
        navigator.clipboard.writeText(textarea.value);
        WMNGToast.success('Code copied to clipboard!');
        WMNGA11y.announce('Code copied to clipboard', 'polite');
    } catch (err) {
        textarea.select();
        document.execCommand('copy');
        WMNGToast.success('Code copied to clipboard!');
    }

    // Show feedback
    const btn = document.getElementById('copyEmbedCodeBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Copied!';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-success');

    setTimeout(() => {
        btn.textContent = originalText;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-primary');
    }, 2000);
}

function deleteMap(mapId) {
    // Handled via form submit for proper CSRF and method spoofing
    // Added aria-label in HTML
}

function updateMapFilterCount(visible, total) {
    const count = document.getElementById('map-filter-count');
    if (count) {
        count.textContent = `Showing ${visible} of ${total}`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('map-search');
    const filterSelect = document.getElementById('map-filter');
    const container = document.getElementById('maps-container');
    if (!searchInput || !filterSelect || !container) return;

    const cards = Array.from(container.querySelectorAll('.map-card'))
        .map(card => card.closest('.col-lg-4'));

    const total = cards.length;

    function sortCards(mode) {
        const sorted = [...cards].sort((a, b) => {
            const cardA = a.querySelector('.map-card');
            const cardB = b.querySelector('.map-card');
            if (!cardA || !cardB) return 0;

            const nameA = cardA.dataset.title || cardA.dataset.name || '';
            const nameB = cardB.dataset.title || cardB.dataset.name || '';
            const nodesA = parseInt(cardA.dataset.nodes || '0', 10);
            const nodesB = parseInt(cardB.dataset.nodes || '0', 10);
            const linksA = parseInt(cardA.dataset.links || '0', 10);
            const linksB = parseInt(cardB.dataset.links || '0', 10);
            const sizeA = parseInt(cardA.dataset.size || '0', 10);
            const sizeB = parseInt(cardB.dataset.size || '0', 10);

            switch (mode) {
                case 'name-desc':
                    return nameB.localeCompare(nameA);
                case 'nodes-desc':
                    return nodesB - nodesA;
                case 'links-desc':
                    return linksB - linksA;
                case 'size-desc':
                    return sizeB - sizeA;
                case 'name-asc':
                default:
                    return nameA.localeCompare(nameB);
            }
        });

        sorted.forEach(card => container.appendChild(card));
    }

    function applyFilter() {
        const query = searchInput.value.trim().toLowerCase();
        let visible = 0;

        cards.forEach(card => {
            const mapCard = card.querySelector('.map-card');
            const text = `${mapCard.dataset.name} ${mapCard.dataset.title}`;
            const isMatch = text.includes(query);
            card.style.display = isMatch ? '' : 'none';
            if (isMatch) visible += 1;
        });

        updateMapFilterCount(visible, total);
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
</script>
@endsection
