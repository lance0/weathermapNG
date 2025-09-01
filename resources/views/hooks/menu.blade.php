<li>
    <a href="{{ $url }}">
        <i class="fa {{ $icon ?? 'fa-map' }}"></i>
        <span>{{ $title }}</span>
        @if($map_count > 0)
            <span class="badge bg-primary float-end">{{ $map_count }}</span>
        @endif
    </a>
</li>