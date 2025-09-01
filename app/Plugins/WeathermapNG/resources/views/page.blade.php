<div class="row">
    <div class="col-md-12">
        <h2>{{ $title }}</h2>
        
        <div class="row">
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_maps'] ?? 0 }}</h3>
                        <p>Total Maps</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_nodes'] ?? 0 }}</h3>
                        <p>Total Nodes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_links'] ?? 0 }}</h3>
                        <p>Total Links</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ !empty($stats['last_updated']) ? \Carbon\Carbon::parse($stats['last_updated'])->diffForHumans() : 'Never' }}</h3>
                        <p>Last Updated</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    Network Maps
                    @if(!empty($can_create))
                        <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-primary btn-sm pull-right">
                            <i class="fa fa-plus"></i> Create New Map
                        </a>
                    @endif
                </h3>
            </div>
            <div class="panel-body">
                @if(!empty($maps) && count($maps) > 0)
                    <div class="row">
                        @foreach($maps as $map)
                            <div class="col-md-4">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <h4>
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" target="_blank">
                                                {{ $map->title ?? $map->name }}
                                            </a>
                                        </h4>
                                        <p class="text-muted">
                                            <i class="fa fa-server"></i> {{ $map->nodes->count() }} nodes |
                                            <i class="fa fa-link"></i> {{ $map->links->count() }} links
                                        </p>
                                        <p class="small">
                                            Dimensions: {{ $map->width ?? 800 }}x{{ $map->height ?? 600 }}px<br>
                                            Updated: {{ $map->updated_at ? $map->updated_at->diffForHumans() : 'Never' }}
                                        </p>
                                        <div class="btn-group">
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" 
                                               class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No maps have been created yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
