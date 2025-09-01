@if($has_links)
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ $title }}</h3>
    </div>
    <div class="panel-body">
        @if($utilization)
            <div class="alert alert-info">
                <strong>Current Utilization:</strong>
                <div class="progress">
                    <div class="progress-bar 
                        @if($utilization['percentage'] > 80) progress-bar-danger
                        @elseif($utilization['percentage'] > 50) progress-bar-warning
                        @else progress-bar-success
                        @endif" 
                        role="progressbar" 
                        style="width: {{ $utilization['percentage'] }}%">
                        {{ $utilization['percentage'] }}%
                    </div>
                </div>
                <small>
                    In: {{ $utilization['in_bps'] }} bps | 
                    Out: {{ $utilization['out_bps'] }} bps
                </small>
            </div>
        @endif
        
        <h4>Weathermap Links</h4>
        <table class="table table-condensed">
            <thead>
                <tr>
                    <th>Map</th>
                    <th>Link</th>
                    <th>Bandwidth</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($links as $link)
                    <tr>
                        <td>
                            <a href="{{ url('plugin/WeathermapNG/embed/' . $link->map_id) }}" target="_blank">
                                {{ $link->map->title ?? $link->map->name }}
                            </a>
                        </td>
                        <td>
                            @if($link->port_id_a == $port->port_id)
                                To: Node {{ $link->dst_node_id }}
                            @else
                                From: Node {{ $link->src_node_id }}
                            @endif
                        </td>
                        <td>
                            {{ number_format($link->bandwidth_bps / 1000000) }} Mbps
                        </td>
                        <td>
                            <a href="{{ url('plugin/WeathermapNG/embed/' . $link->map_id) }}" 
                               class="btn btn-xs btn-default" target="_blank">
                                <i class="fa fa-external-link"></i> View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-info">
    <i class="fa fa-info-circle"></i> This port is not used in any weathermaps.
</div>
@endif
