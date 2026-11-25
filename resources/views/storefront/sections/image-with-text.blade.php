@php
    $w = ['33' => '1fr 2fr', '50' => '1fr 1fr', '66' => '2fr 1fr'][(string) ($s['image_width'] ?? '50')] ?? '1fr 1fr';
    $reverse = ($s['image_position'] ?? 'start') === 'end';
    if ($reverse) { $w = implode(' ', array_reverse(explode(' ', $w))); }
    $btn = $sf->t($s, 'button_label');
@endphp
<div class="mv-container">
    <div class="mv-split {{ $reverse ? 'is-reverse' : '' }}" style="--split:{{ $w }};--va:{{ ['start' => 'start', 'end' => 'end'][$s['vertical'] ?? ''] ?? 'center' }}">
        @include('storefront.partials.media', ['src' => $sf->media($s['image'] ?? null), 'alt' => $sf->t($s, 'heading'), 'ratio' => $s['aspect'] ?? 'portrait'])
        <div>
            @if (($big = $sf->t($s, 'big_text')) !== '')<p class="mv-big-text">{{ $big }}</p>@endif
            @if (($e = $sf->t($s, 'eyebrow')) !== '')<span class="mv-eyebrow">{{ $e }}</span>@endif
            @if (($h = $sf->t($s, 'heading')) !== '')<h2 class="mv-h2">{{ $h }}</h2>@endif
            <div class="mv-rte">{!! $sf->rich($sf->t($s, 'body')) !!}</div>
            @if ($btn !== '')<div class="mv-btn-row"><a class="mv-btn mv-btn--outline" href="{{ $sf->link($s['button_url'] ?? '/') }}">{{ $btn }}</a></div>@endif
        </div>
    </div>
</div>
