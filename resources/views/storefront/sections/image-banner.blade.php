@php
    $image = $sf->media($s['image'] ?? null);
    $alt = $sf->isAr() ? ($s['alt_ar'] ?: $s['alt_en'] ?? '') : ($s['alt_en'] ?? '');
    $heading = $sf->t($s, 'heading');
    $text = $sf->t($s, 'text');
    $badge = $sf->t($s, 'badge');
    $btn = $sf->t($s, 'button_label');
    $caption = $sf->t($s, 'caption');
    $wholeLink = ! empty($s['link_url']) ? $sf->link($s['link_url']) : null;
@endphp
<div class="mv-hero mv-h-{{ $s['height'] ?? 'large' }} {{ $image ? '' : 'is-empty' }}" style="{{ $sf->position((string) ($s['content_position'] ?? 'center')) }};{{ ($heading !== '' || $text !== '' || $btn !== '') ? $sf->overlay($s['overlay_opacity'] ?? 30) : '--overlay:0' }}">
    @include('storefront.partials.hero-media', ['image' => $image, 'mobile' => $sf->media($s['mobile_image'] ?? null), 'alt' => $alt, 'eager' => $index < 2])
    @if ($wholeLink)<a href="{{ $wholeLink }}" style="position:absolute;inset:0;z-index:1" aria-label="{{ $heading ?: $alt }}"></a>@endif
    @if ($badge !== '')<span class="mv-badge-xl">{{ $badge }}</span>@endif
    @if ($heading !== '' || $text !== '' || $btn !== '')
        <div class="mv-hero__content" style="z-index:2">
            <div class="mv-hero__inner">
                @if ($heading !== '')<h2 class="mv-hero__title">{{ $heading }}</h2>@endif
                @if ($text !== '')<p class="mv-hero__text">{{ $text }}</p>@endif
                @if ($btn !== '')<div class="mv-btn-row"><a class="mv-btn {{ $image ? 'mv-btn--light' : '' }}" href="{{ $sf->link($s['button_url'] ?? '/shop') }}">{{ $btn }}</a></div>@endif
            </div>
        </div>
    @endif
</div>
@if ($caption !== '')<p class="mv-container mv-muted" style="font-size:13px;margin-top:8px">{{ $caption }}</p>@endif
