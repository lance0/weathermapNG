<div class="row">
    <div class="col-md-12">
        <h2>{{ $title }}</h2>
        
        <div class="row">
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_maps'] }}</h3>
                        <p>Total Maps</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_nodes'] }}</h3>
                        <p>Total Nodes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['total_links'] }}</h3>
                        <p>Total Links</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>{{ $stats['last_updated'] ? \Carbon\Carbon::parse($stats['last_updated'])->diffForHumans() : 'Never' }}</h3>
                        <p>Last Updated</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    Network Maps
                    @if($can_create)
                        <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-primary btn-sm pull-right">
                            <i class="fa fa-plus"></i> Create New Map
                        </a>
                    @endif
                </h3>
            </div>
            <div class="panel-body">
                @if(count($maps) > 0)
                    <div class="row">
                        @foreach($maps as $map)
                            <div class="col-md-4">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <h4>
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}">
                                                {{ $map->title ?? $map->name }}
                                            </a>
                                        </h4>
                                        <p class="text-muted">
                                            <i class="fa fa-server"></i> {{ $map->nodes->count() }} nodes |
                                            <i class="fa fa-link"></i> {{ $map->links->count() }} links
                                        </p>
                                        <p class="small">
                                            Dimensions: {{ $map->width }}x{{ $map->height }}px<br>
                                            Updated: {{ $map->updated_at->diffForHumans() }}
                                        </p>
                                        <div class="btn-group">
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" 
                                               class="btn btn-sm btn-default">
                                                <i class="fa fa-edit"></i> Edit
                                            </a>
                                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" 
                                               target="_blank" class="btn btn-sm btn-default">
                                                <i class="fa fa-external-link"></i> Embed
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
                        @if($can_create)
                            <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-primary btn-sm">
                                Create your first map
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<script>
// Auto-refresh stats from JSON endpoint every 30s
document.addEventListener('DOMContentLoaded', function() {
    const refresh = () => {
        fetch('{{ url('plugin/WeathermapNG/health/stats') }}')
            .then(r => r.json())
            .then(d => {
                const els = {
                    maps: document.querySelectorAll('h3')
                };
                const cards = document.querySelectorAll('.panel .panel-body h3');
                if (cards && cards.length >= 4) {
                    cards[0].textContent = d.maps ?? cards[0].textContent;
                    cards[1].textContent = d.nodes ?? cards[1].textContent;
                    cards[2].textContent = d.links ?? cards[2].textContent;
                    cards[3].textContent = d.last_updated ? new Date(d.last_updated).toLocaleString() : cards[3].textContent;
                }
            }).catch(() => {});
    };
    refresh();
    setInterval(refresh, 30000);
});
</script>
