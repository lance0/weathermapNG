@extends('layouts.app')

@section('title', 'WeathermapNG - Network Maps')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-network-wired"></i> WeathermapNG</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMapModal">
                        <i class="fas fa-plus"></i> Create New Map
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div id="maps-container" class="row">
                @forelse($maps as $map)
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map"></i> {{ $map->title ?? $map->name }}
                                </h5>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <ul class="list-unstyled mb-4 small text-muted">
                                    <li><i class="fas fa-id-badge"></i> Name: {{ $map->name }}</li>
                                    <li><i class="fas fa-vector-square"></i> Size: {{ $map->width }} x {{ $map->height }}</li>
                                    <li><i class="fas fa-project-diagram"></i> Nodes: {{ $map->nodes_count ?? $map->nodes()->count() }}, Links: {{ $map->links_count ?? $map->links()->count() }}</li>
                                </ul>
                                <div class="mt-auto">
                                    <div class="btn-group w-100" role="group">
                                        <a href="{{ url('plugins/weathermapng/embed/' . $map->id) }}"
                                           class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                        <a href="{{ url('plugins/weathermapng/maps/' . $map->id . '/editor') }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="{{ url('plugins/weathermapng/api/maps/' . $map->id . '/export?format=json') }}"
                                           class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-download"></i> Export
                                        </a>
                                        <form method="POST" action="{{ url('plugins/weathermapng/maps/' . $map->id) }}" onsubmit="return confirm('Delete this map?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-map fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">No Maps Found</h3>
                            <p class="text-muted">Create your first network map to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMapModal">
                                <i class="fas fa-plus"></i> Create Your First Map
                            </button>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Create Map Modal -->
<div class="modal fade" id="createMapModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ url('plugins/weathermapng/maps') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Create New Map</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" maxlength="255">
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label">Width</label>
                        <input type="number" class="form-control" name="width" value="800" min="100" max="4096">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Height</label>
                        <input type="number" class="form-control" name="height" value="600" min="100" max="4096">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    </div>

<!-- Embed Code Modal -->
<div class="modal fade" id="embedModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Embed Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Copy and paste this code to embed the map:</p>
                <div class="form-group">
                    <label for="embedCode">HTML Code:</label>
                    <textarea class="form-control" id="embedCode" rows="4" readonly></textarea>
                </div>
                <div class="form-group mt-3">
                    <label for="iframeCode">Iframe Code:</label>
                    <textarea class="form-control" id="iframeCode" rows="2" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="copyEmbedCode()">Copy HTML</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function showEmbedCode(mapId) {
    const baseUrl = '{{ url("/") }}';
    const embedUrl = `${baseUrl}/plugins/weathermapng/embed/${mapId}`;

    const htmlCode = `<div style="width: 100%; height: 400px; border: 1px solid #ccc;">\n    <iframe src="${embedUrl}" width="100%" height="100%" frameborder="0"></iframe>\n</div>`;

    const iframeCode = `<iframe src="${embedUrl}" width="800" height="600" frameborder="0"></iframe>`;

    document.getElementById('embedCode').value = htmlCode;
    document.getElementById('iframeCode').value = iframeCode;

    new bootstrap.Modal(document.getElementById('embedModal')).show();
}

function copyEmbedCode() {
    const textarea = document.getElementById('embedCode');
    textarea.select();
    document.execCommand('copy');

    // Show feedback
    const btn = event.target;
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
}
</script>
@endsection
