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
                        <i class="fas fa-arrow-left"></i> Back to Maps
                    </a>
                    <a href="{{ url('plugin/WeathermapNG/embed/' . $map->id) }}" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Open Embed
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
                                <p>This map is configured but the live rendering functionality is not yet implemented.</p>
                                <p>Nodes and links data will be displayed here once the rendering engine is complete.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Canvas Placeholder -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Map Visualization</h5>
                </div>
                <div class="card-body text-center">
                    <div style="width: {{ $map->width }}px; height: {{ $map->height }}px; border: 2px dashed #dee2e6; margin: 0 auto; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa;">
                        <div class="text-muted">
                            <i class="fas fa-map fa-3x mb-3"></i>
                            <h4>Map Canvas</h4>
                            <p>{{ $map->width }} x {{ $map->height }} pixels</p>
                            <small>Rendering engine coming soon...</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
