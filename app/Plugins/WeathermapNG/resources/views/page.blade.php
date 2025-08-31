@extends('layouts.librenmsv1')

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fas fa-map"></i> {{ $title }}
                        <div class="pull-right">
                            <button class="btn btn-primary btn-sm" onclick="createNewMap()">
                                <i class="fas fa-plus"></i> Create New Map
                            </button>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">
                    @if(count($maps) > 0)
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
                                    @foreach($maps as $map)
                                    <tr>
                                        <td>
                                            <a href="{{ url('/plugin/weathermapng/map/' . $map->id) }}">
                                                {{ $map->name }}
                                            </a>
                                        </td>
                                        <td>{{ $map->description }}</td>
                                        <td>{{ $map->width }}x{{ $map->height }}</td>
                                        <td>{{ $map->updated_at }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ url('/plugin/weathermapng/map/' . $map->id) }}" 
                                                   class="btn btn-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ url('/plugin/weathermapng/map/' . $map->id . '/edit') }}" 
                                                   class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="deleteMap({{ $map->id }})" 
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
                            <a href="#" onclick="createNewMap()" class="alert-link">Create your first map</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function createNewMap() {
    // Placeholder for map creation logic
    alert('Map creation interface will be implemented here');
}

function deleteMap(mapId) {
    if (confirm('Are you sure you want to delete this map?')) {
        // Placeholder for map deletion logic
        alert('Map deletion will be implemented here');
    }
}
</script>
@endsection