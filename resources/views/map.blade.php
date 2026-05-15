@extends('layouts.librenmsv1')

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-map"></i> {{ $map->title ?? $map->name }}</h1>
                <div>
                    <a href="{{ url('plugin/WeathermapNG') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Maps
                    </a>
                    <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" class="btn btn-default" target="_blank" rel="noopener noreferrer" aria-label="Open live map {{ $map->title ?? $map->name }}">
                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> Open Live Map
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Map Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Name:</dt>
                                <dd class="col-sm-8">{{ $map->name }}</dd>

                                <dt class="col-sm-4">Title:</dt>
                                <dd class="col-sm-8">{{ $map->title ?? 'N/A' }}</dd>

                                <dt class="col-sm-4">Size:</dt>
                                <dd class="col-sm-8">{{ $map->width }} x {{ $map->height }} pixels</dd>

                                <dt class="col-sm-4">Created:</dt>
                                <dd class="col-sm-8">{{ $map->created_at->format('M j, Y H:i') }}</dd>

                                <dt class="col-sm-4">Updated:</dt>
                                <dd class="col-sm-8">{{ $map->updated_at->format('M j, Y H:i') }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Map Status</h6>
                                <p>This compatibility page summarizes the map. Use the live map view for current rendering and traffic data.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Preview -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Map Visualization</h5>
                </div>
                <div class="card-body text-center">
                    <div style="width: {{ $map->width }}px; height: {{ $map->height }}px; border: 2px solid #dee2e6; margin: 0 auto; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa;">
                        <div class="text-muted">
                            <i class="fas fa-map fa-3x mb-3"></i>
                            <h4>Map Preview</h4>
                            <p>{{ $map->width }} x {{ $map->height }} pixels</p>
                            <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" class="btn btn-default btn-sm" target="_blank" rel="noopener noreferrer" aria-label="Open live map preview for {{ $map->title ?? $map->name }}">
                                <i class="fas fa-external-link-alt" aria-hidden="true"></i> Open Live Map
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
