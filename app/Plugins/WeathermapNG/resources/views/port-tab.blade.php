<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ $title }}</h3>
    </div>
    <div class="panel-body">
        <p>Port: {{ $port->getLabel() ?? ($port->ifName ?? $port->id) }}</p>
        <a href="{{ $plugin_url }}" class="btn btn-default btn-sm">
            <i class="fa fa-map"></i> View in WeathermapNG
        </a>
    </div>
</div>
