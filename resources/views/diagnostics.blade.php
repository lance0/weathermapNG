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
</div>
@endsection
