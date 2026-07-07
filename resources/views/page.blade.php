@extends('layouts.librenmsv1')

@section('title', $title)

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-map"></i> {{ $title }}
                        <div class="float-right">
                            <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#createMapModal">
                                <i class="fas fa-plus" aria-hidden="true"></i> Create New Map
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="card-body">
                    <div id="legacyPageAlerts" aria-live="polite"></div>
                    @if(count($maps) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Size</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($maps as $map)
                                    <tr>
                                        <td>
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank" rel="noopener noreferrer" aria-label="Open map {{ $map->name }}">
                                                {{ $map->name }}
                                            </a>
                                        </td>
                                        <td>{{ $map->title ?? '' }}</td>
                                        <td>{{ Str::limit($map->description ?? '', 50) }}</td>
                                        <td>{{ $map->width ?? 800 }}x{{ $map->height ?? 600 }}</td>
                                        <td>{{ $map->updated_at ? \Carbon\Carbon::parse($map->updated_at)->format('M j, Y H:i') : 'Never' }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}"
                                                   class="btn btn-default" title="View" target="_blank" rel="noopener noreferrer" aria-label="View map {{ $map->name }}">
                                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                                </a>
                                                <button type="button"
                                                        data-map-id="{{ $map->id }}" data-map-name="{{ $map->name }}"
                                                        data-action="delete-map"
                                                        class="btn btn-danger" title="Delete" aria-label="Delete map {{ $map->name }}">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No weather maps have been created yet.
                            <button type="button" class="btn btn-success btn-sm ml-2" data-toggle="modal" data-target="#createMapModal">
                                <i class="fas fa-plus" aria-hidden="true"></i> Create Your First Map
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Map Modal -->
<div class="modal fade" id="createMapModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="createMapForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Map</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="mapName" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="mapName" name="name" required maxlength="255">
                        <div class="form-text">Unique identifier for the map</div>
                    </div>
                    <div class="form-group">
                        <label for="mapTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="mapTitle" name="title" maxlength="255">
                        <div class="form-text">Display title (optional)</div>
                    </div>
                    <div class="form-group">
                        <label for="mapDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="mapDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label for="mapWidth" class="form-label">Width</label>
                            <input type="number" class="form-control" id="mapWidth" name="width"
                                   value="800" min="400" max="2000">
                        </div>
                        <div class="col-6">
                            <label for="mapHeight" class="form-label">Height</label>
                            <input type="number" class="form-control" id="mapHeight" name="height"
                                   value="600" min="300" max="1500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Map</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="legacyDeleteMapModal" tabindex="-1" role="dialog" aria-labelledby="legacyDeleteMapTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="legacyDeleteMapTitle">Delete Map</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p id="legacyDeleteMapMessage" class="mb-0">Delete this map? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmLegacyDeleteMapBtn">Delete Map</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingLegacyDeleteMapId = null;

function createNewMap() {
    $('#createMapModal').modal('show');
}

function showPageAlert(message, type = 'danger') {
    const container = document.getElementById('legacyPageAlerts');
    if (!container) return;

    container.innerHTML = '';

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible`;
    alert.setAttribute('role', 'alert');

    const text = document.createTextNode(message);
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'close';
    close.setAttribute('data-dismiss', 'alert');
    close.setAttribute('aria-label', 'Close');
    close.innerHTML = '&times;';

    alert.appendChild(text);
    alert.appendChild(close);
    container.appendChild(alert);
}

function deleteMap(mapId, mapName) {
    pendingLegacyDeleteMapId = mapId;

    const message = document.getElementById('legacyDeleteMapMessage');
    if (message) {
        message.textContent = `Delete the map "${mapName}"? This action cannot be undone.`;
    }

    $('#legacyDeleteMapModal').modal('show');
}

// Delegated listener for delete buttons (replaces inline onclick)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="delete-map"]');
    if (!btn) return;
    deleteMap(btn.dataset.mapId, btn.dataset.mapName);
});

document.getElementById('confirmLegacyDeleteMapBtn')?.addEventListener('click', function() {
    if (!pendingLegacyDeleteMapId) return;

    const mapId = pendingLegacyDeleteMapId;
    pendingLegacyDeleteMapId = null;
    $('#legacyDeleteMapModal').modal('hide');

    fetch(`{{ url('plugin/WeathermapNG/map') }}/${mapId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + (response.statusText ? ' ' + response.statusText : ''));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showPageAlert('Error deleting map: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        showPageAlert('Error deleting map: ' + error.message);
    });
});

// Handle create map form submission
$('#createMapForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('{{ url("plugin/WeathermapNG/map") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + (response.statusText ? ' ' + response.statusText : ''));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            $('#createMapModal').modal('hide');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                location.reload();
            }
        } else {
            showPageAlert('Error creating map: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        showPageAlert('Error creating map: ' + error.message);
    });
});
</script>
@endsection
