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
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createMapModal">
                                <i class="fas fa-plus"></i> Create New Map
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="card-body">
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
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank">
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
                                                   class="btn btn-primary" title="View" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button onclick="deleteMap({{ $map->id }}, '{{ $map->name }}')"
                                                        class="btn btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
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
                            <button class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#createMapModal">
                                <i class="fas fa-plus"></i> Create Your First Map
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
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
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
                    <button type="submit" class="btn btn-primary">Create Map</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function createNewMap() {
    $('#createMapModal').modal('show');
}

function deleteMap(mapId, mapName) {
    if (confirm(`Are you sure you want to delete the map "${mapName}"? This action cannot be undone.`)) {
        fetch(`{{ url('plugin/WeathermapNG/map') }}/${mapId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting map: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Error deleting map: ' + error.message);
        });
    }
}

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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#createMapModal').modal('hide');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                location.reload();
            }
        } else {
            alert('Error creating map: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error creating map: ' + error.message);
    });
});
</script>
@endsection
