@extends('layouts.app')

@section('title', 'WeathermapNG - Network Maps')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-network-wired"></i> WeathermapNG</h1>
                <div>
                    <a href="{{ url('plugins/weathermapng/editor') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Map
                    </a>
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
                                    <i class="fas fa-map"></i> {{ $map['name'] }}
                                </h5>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="map-preview mb-3" style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                    @if(file_exists(config('weathermapng.output_dir', __DIR__ . '/../output/maps/') . $map['file']))
                                        <img src="{{ asset('plugins/WeathermapNG/output/maps/' . $map['file']) }}"
                                             alt="{{ $map['name'] }}"
                                             class="img-fluid rounded"
                                             style="max-height: 180px; max-width: 100%;">
                                    @else
                                        <div class="text-muted">
                                            <i class="fas fa-image fa-3x"></i>
                                            <p class="mt-2">No preview available</p>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-auto">
                                    <div class="btn-group w-100" role="group">
                                        <a href="{{ url('plugins/weathermapng/embed/' . $map['id']) }}"
                                           class="btn btn-outline-primary btn-sm"
                                           target="_blank">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                        <button class="btn btn-outline-info btn-sm"
                                                onclick="showEmbedCode('{{ $map['id'] }}')">
                                            <i class="fas fa-code"></i> Embed
                                        </button>
                                        <a href="{{ url('plugins/weathermapng/editor?id=' . $map['id']) }}"
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm"
                                                onclick="deleteMap('{{ $map['id'] }}')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>
                                    <i class="fas fa-info-circle"></i>
                                    Config: {{ $map['config_path'] }}
                                </small>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-map fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">No Maps Found</h3>
                            <p class="text-muted">Create your first network map to get started.</p>
                            <a href="{{ url('plugins/weathermapng/editor') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Your First Map
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
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

    const htmlCode = `<div style="width: 100%; height: 400px; border: 1px solid #ccc;">
    <iframe src="${embedUrl}" width="100%" height="100%" frameborder="0"></iframe>
</div>`;

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
    if (confirm('Are you sure you want to delete this map? This action cannot be undone.')) {
        fetch(`{{ url('plugins/weathermapng/api/map') }}/${mapId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error deleting map: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error deleting map: ' + error.message);
        });
    }
}
</script>
@endsection