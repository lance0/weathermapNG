@if($has_maps)
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading">
                <strong>{{ $title }}</strong>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-12">
                        <p>This device appears in {{ count($maps) }} network map(s):</p>
                        <ul class="list-group">
                            @foreach($maps as $map)
                                <li class="list-group-item">
                                    <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank" rel="noopener noreferrer" aria-label="Open map {{ $map->title ?? $map->name }}">
                                        <i class="fa fa-map" aria-hidden="true"></i> {{ $map->title ?? $map->name }}
                                    </a>
                                    <span class="badge">{{ $map->device_nodes->count() }} node(s)</span>
                                    <div class="small text-muted">
                                        Last updated: {{ $map->updated_at->diffForHumans() }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-default btn-sm">
                            <i class="fa fa-map" aria-hidden="true"></i> View All Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
