@php
    $scroll = ! empty($s['scrolling']) && count($blocks) > 3;
    $logos = '';
    foreach ($blocks as $b) {
        $img = $sf->media($b['settings']['image'] ?? null);
        $alt = (string) ($b['settings']['alt'] ?? '');
        $inner = $img ? '<img src="'.e($img).'" alt="'.e($alt).'" loading="lazy">' : '<span class="mv-logo-name">'.e($alt !== '' ? $alt : 'Logo').'</span>';
        $logos .= ! empty($b['settings']['link_url']) ? '<a href="'.e($sf->link($b['settings']['link_url'])).'">'.$inner.'</a>' : '<span>'.$inner.'</span>';
    }
@endphp
<div class="mv-container">
    @include('storefront.partials.section-head')
    @if ($scroll)
        <div class="mv-marquee is-pausable" style="--speed:40s">
            <div class="mv-marquee__track">@for ($r = 0; $r < 2; $r++)<div class="mv-marquee__group mv-logos {{ ! empty($s['grayscale']) ? 'is-grey' : '' }}" style="--logo-h:{{ $sf->num($s['logo_height'] ?? 40, 16, 120) }}px;padding-inline:32px;flex-wrap:nowrap" @if ($r) aria-hidden="true" @endif>{!! $logos !!}</div>@endfor</div>
        </div>
    @else
        <div class="mv-logos {{ ! empty($s['grayscale']) ? 'is-grey' : '' }}" style="--logo-h:{{ $sf->num($s['logo_height'] ?? 40, 16, 120) }}px">{!! $logos !== '' ? $logos : '<span class="mv-logo-name">Brand</span><span class="mv-logo-name">Brand</span><span class="mv-logo-name">Brand</span>' !!}</div>
    @endif
</div>
