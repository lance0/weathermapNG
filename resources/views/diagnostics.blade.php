@extends('layouts.librenmsv1')

@section('title', 'WeathermapNG Diagnostics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>WeathermapNG Diagnostics</h2>
            <p class="text-muted">Operational status for administrators.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-{{ $overallStatus === 'healthy' ? 'success' : ($overallStatus === 'warning' ? 'warning' : 'danger') }}" role="alert">
                <strong>Overall:</strong> {{ ucfirst($overallStatus) }}
                <span class="float-end text-muted">Plugin v{{ $version }}</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Counts</div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td>Maps</td><td class="text-end">{{ $stats['maps'] ?? 0 }}</td></tr>
                        <tr><td>Nodes</td><td class="text-end">{{ $stats['nodes'] ?? 0 }}</td></tr>
                        <tr><td>Links</td><td class="text-end">{{ $stats['links'] ?? 0 }}</td></tr>
                        <tr><td>DB size</td><td class="text-end">{{ $stats['database_size'] ?? 'Unknown' }}</td></tr>
                    </table>
                    @if(!empty($stats['error']))
                        <div class="text-danger small mt-2">{{ $stats['error'] }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Health Checks</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Check</th><th>Status</th><th>Message</th></tr>
                        </thead>
                        <tbody>
                            @foreach($checks as $name => $check)
                            <tr>
                                <td>{{ ucfirst($name) }}</td>
                                <td>
                                    <span class="badge bg-{{ ($check['status'] ?? 'unknown') === 'healthy' ? 'success' : (($check['status'] ?? 'unknown') === 'warning' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($check['status'] ?? 'unknown') }}
                                    </span>
                                </td>
                                <td>{{ $check['message'] ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Routes</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Method</th><th>Name</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach($routes as $route)
                            <tr>
                                <td>{{ $route['method'] }}</td>
                                <td>
                                    @if($route['url'] !== '#')
                                        <a href="{{ $route['url'] }}">{{ $route['name'] }}</a>
                                    @else
                                        <span class="text-muted">{{ $route['name'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $route['status'] === 'ok' ? 'success' : 'danger' }}">
                                        {{ $route['status'] === 'ok' ? 'OK' : 'Missing' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Writable Paths</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Path</th><th>Exists</th><th>Writable</th></tr>
                        </thead>
                        <tbody>
                            @foreach($paths as $label => $path)
                            <tr>
                                <td><code>{{ $path['path'] }}</code></td>
                                <td>
                                    <span class="badge bg-{{ $path['exists'] ? 'success' : 'danger' }}">
                                        {{ $path['exists'] ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $path['writable'] ? 'success' : 'warning' }}">
                                        {{ $path['writable'] ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Data Integrity</div>
                <div class="card-body">
                    @if(!empty($integrity['error']))
                        <div class="alert alert-danger mb-0">{{ $integrity['error'] }}</div>
                    @elseif(empty($integrity['maps']))
                        <p class="text-muted mb-0">No maps found.</p>
                    @else
                        @php
                            $summary = $integrity['summary'] ?? [];
                            $problemTotal = ($summary['broken_links'] ?? 0)
                                + ($summary['missing_rrd'] ?? 0)
                                + ($summary['orphan_nodes'] ?? 0)
                                + ($summary['orphan_links'] ?? 0)
                                + ($summary['dangling_nodes'] ?? 0)
                                + ($summary['dangling_links'] ?? 0);
                        @endphp
                        <div class="alert alert-{{ $problemTotal > 0 ? 'warning' : 'success' }} mb-3">
                            <strong>{{ $problemTotal > 0 ? $problemTotal . ' issue(s)' : 'All clean' }}</strong>
                            <span class="text-muted">
                                across {{ $summary['maps'] ?? 0 }} map(s) &mdash;
                                {{ $summary['broken_links'] ?? 0 }} broken ports,
                                {{ $summary['missing_rrd'] ?? 0 }} missing RRD,
                                {{ $summary['orphan_nodes'] ?? 0 }} orphan nodes,
                                {{ $summary['orphan_links'] ?? 0 }} orphan links.
                            </span>
                            @if(($summary['dangling_nodes'] ?? 0) > 0 || ($summary['dangling_links'] ?? 0) > 0)
                                <div class="small mt-1">
                                    Additionally {{ $summary['dangling_nodes'] ?? 0 }} node(s) and {{ $summary['dangling_links'] ?? 0 }} link(s) point at deleted maps.
                                </div>
                            @endif
                        </div>

                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Map</th>
                                    <th class="text-end">Nodes</th>
                                    <th class="text-end">Links</th>
                                    <th class="text-end">Broken</th>
                                    <th class="text-end">Missing RRD</th>
                                    <th class="text-end">Orphan Nodes</th>
                                    <th class="text-end">Orphan Links</th>
                                    <th>Findings</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($integrity['maps'] as $map)
                                    @php
                                        $issues = ($map['broken_links'] ?? 0)
                                            + ($map['missing_rrd'] ?? 0)
                                            + ($map['orphan_nodes'] ?? 0)
                                            + ($map['orphan_links'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $map['name'] }}</td>
                                        <td class="text-end">{{ $map['nodes'] }}</td>
                                        <td class="text-end">{{ $map['links'] }}</td>
                                        <td class="text-end">{{ $map['broken_links'] }}</td>
                                        <td class="text-end">{{ $map['missing_rrd'] }}</td>
                                        <td class="text-end">{{ $map['orphan_nodes'] }}</td>
                                        <td class="text-end">{{ $map['orphan_links'] }}</td>
                                        <td>
                                            @if($issues === 0)
                                                <span class="badge bg-success">No issues</span>
                                            @else
                                                <ul class="list-unstyled small mb-0">
                                                    @foreach($map['findings'] as $finding)
                                                        <li><span class="badge bg-{{ $finding['type'] === 'missing_rrd' ? 'warning' : 'danger' }}">{{ $finding['type'] }}</span> {{ $finding['message'] }}</li>
                                                    @endforeach
                                                    @if($map['total'] > count($map['findings']))
                                                        <li class="text-muted">+{{ $map['total'] - count($map['findings']) }} more&hellip;</li>
                                                    @endif
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
