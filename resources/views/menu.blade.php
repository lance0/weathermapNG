<li class="nav-item">
    <a href="{{ $url }}" class="nav-link">
        <i class="fas {{ $icon ?? 'fa-map' }}"></i>
        <span>{{ $title }}</span>
        @if(($map_count ?? 0) > 0)
            <span class="badge bg-primary float-end">{{ $map_count }}</span>
        @endif
    </a>
    @if($is_admin ?? false)
        <a href="{{ $diagnostics_url }}" class="nav-link">
            <i class="fas fa-stethoscope"></i>
            <span>Diagnostics</span>
        </a>
    @endif
</li>