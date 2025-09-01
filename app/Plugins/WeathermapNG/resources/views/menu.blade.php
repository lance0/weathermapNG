<li>
    <a href="{{ $url }}">
        <i class="fa {{ $icon ?? 'fa-map' }}"></i>
        <span>{{ $title }}</span>
        @if(!empty($map_count) && $map_count > 0)
            <span class="badge badge-primary pull-right">{{ $map_count }}</span>
        @endif
    </a>
</li>
