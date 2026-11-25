@php
    $align = in_array($s['alignment'] ?? '', ['start', 'center', 'end'], true) ? $s['alignment'] : 'start';
    $btn = $sf->t($s, 'button_label');
@endphp
<div class="mv-container" style="text-align:{{ $align }}">
    @if (($h = $sf->t($s, 'heading')) !== '')<h2 style="font-size:clamp(24px, 4vw, {{ $sf->num($s['heading_size'] ?? 36, 16, 160) }}px)">{{ $h }}</h2>@endif
    <div class="mv-rte {{ $align === 'center' ? 'is-center' : '' }}" @if ($align === 'end') style="margin-inline-start:auto" @endif>{!! $sf->rich($sf->t($s, 'body')) !!}</div>
    @if ($btn !== '')<div class="mv-btn-row {{ $align === 'center' ? 'is-center' : '' }}"><a class="mv-btn" href="{{ $sf->link($s['button_url'] ?? '/') }}">{{ $btn }}</a></div>@endif
</div>
