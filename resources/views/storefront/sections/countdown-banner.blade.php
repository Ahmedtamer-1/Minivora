@php
    $image = $sf->media($s['image'] ?? null);
    $layout = $s['layout'] ?? 'split';
    $btn = $sf->t($s, 'button_label');
    $ends = (string) ($s['ends_at'] ?? '');
@endphp
@php(ob_start())
    @if (($e = $sf->t($s, 'eyebrow')) !== '')<span class="mv-eyebrow"{!! $sf->e('eyebrow') !!}>{{ $e }}</span>@endif
    @if (($h = $sf->t($s, 'heading')) !== '')<h2 class="mv-h1" style="margin:0"{!! $sf->e('heading') !!}>{{ $h }}</h2>@endif
    @if (($t = $sf->t($s, 'text')) !== '')<p class="mv-hero__text"{!! $sf->e('text') !!}>{{ $t }}</p>@endif
    @if ($ends !== '')<div class="mv-countdown" data-countdown="{{ $ends }}" data-expired="{{ $sf->t($s, 'expired_text') }}"></div>@endif
    @if ($btn !== '')<div class="mv-btn-row"><a class="mv-btn {{ $layout === 'background' && $image ? 'mv-btn--light' : '' }}" href="{{ $sf->link($s['button_url'] ?? '/shop') }}"{!! $sf->e('button_label') !!}>{{ $btn }}</a></div>@endif
@php($content = ob_get_clean())
@if ($layout === 'background')
    <div class="mv-hero mv-h-medium {{ $image ? '' : 'is-empty' }}" style="--ai:center;--jc:center;--overlay:.4">
        @include('storefront.partials.hero-media', ['image' => $image])
        <div class="mv-hero__content"><div class="mv-hero__inner">{!! $content !!}</div></div>
    </div>
@elseif ($layout === 'text' || ! $image)
    <div class="mv-container" style="text-align:center">{!! $content !!}</div>
@else
    <div class="mv-container"><div class="mv-split">@include('storefront.partials.media', ['src' => $image, 'ratio' => 'landscape', 'edit' => $sf->ei('image')])<div>{!! $content !!}</div></div></div>
@endif
