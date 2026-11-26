@php
    $image = $sf->media($s['image'] ?? null);
    $title = $sf->t($s, 'headline');
    $eyebrow = $sf->t($s, 'eyebrow');
    $text = $sf->t($s, 'subheadline');
    $cta = $sf->t($s, 'cta_label');
    $cta2 = $sf->t($s, 'cta2_label');
@endphp
<div{!! $sf->ei('image') !!} class="mv-hero mv-h-{{ $s['height'] ?? 'large' }} {{ $image ? '' : 'is-empty' }}" style="{{ $sf->position((string) ($s['content_position'] ?? 'center')) }};{{ $sf->overlay($s['overlay_opacity'] ?? 30) }};--hs:{{ $sf->num($s['headline_size'] ?? 64, 24, 200) }}px">
    @include('storefront.partials.hero-media', ['image' => $image, 'mobile' => $sf->media($s['mobile_image'] ?? null), 'alt' => $title, 'eager' => $index < 2])
    <div class="mv-hero__content">
        <div class="mv-hero__inner" style="text-align:{{ in_array($s['alignment'] ?? '', ['start', 'center', 'end'], true) ? $s['alignment'] : 'center' }}">
            @if ($eyebrow !== '')<span class="mv-eyebrow"{!! $sf->e('eyebrow') !!}>{{ $eyebrow }}</span>@endif
            <h1 class="mv-hero__title"{!! $sf->e('headline') !!}>{{ $title !== '' ? $title : $sf->store->name }}</h1>
            @if ($text !== '')<p class="mv-hero__text"{!! $sf->e('subheadline') !!}>{{ $text }}</p>@endif
            @if ($cta !== '' || $cta2 !== '')
                <div class="mv-btn-row" style="justify-content:{{ ['start' => 'flex-start', 'end' => 'flex-end'][$s['alignment'] ?? ''] ?? 'center' }}">
                    @if ($cta !== '')<a class="mv-btn {{ $image ? 'mv-btn--light' : '' }}" href="{{ $sf->link($s['cta_url'] ?? '/shop') }}"{!! $sf->e('cta_label') !!}>{{ $cta }}</a>@endif
                    @if ($cta2 !== '')<a class="mv-btn {{ $image ? 'mv-btn--ghost-light' : 'mv-btn--outline' }}" href="{{ $sf->link($s['cta2_url'] ?? '/') }}"{!! $sf->e('cta2_label') !!}>{{ $cta2 }}</a>@endif
                </div>
            @endif
        </div>
    </div>
</div>
