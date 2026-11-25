<div class="mv-container" data-rotator data-interval="{{ (int) ($s['interval_ms'] ?? 5000) }}" data-autoplay="{{ ! empty($s['autoplay']) ? 'true' : 'false' }}">
    <div class="mv-announce">
        @foreach ($blocks as $i => $b)
            @php($text = $sf->t($b['settings'], 'text'))
            <p data-rotator-item class="{{ $i === 0 ? 'is-active' : '' }}" style="margin:0">
                @if (! empty($b['settings']['link_url']))<a href="{{ $sf->link($b['settings']['link_url']) }}">{{ $text }}</a>@else{{ $text }}@endif
            </p>
        @endforeach
    </div>
</div>
