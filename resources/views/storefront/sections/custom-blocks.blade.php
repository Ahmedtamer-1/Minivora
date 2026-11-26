@php
    $layout = in_array($s['layout'] ?? '', ['stack', 'row', 'grid', 'split'], true) ? $s['layout'] : 'stack';
    $align = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch'][$s['align'] ?? 'start'] ?? 'flex-start';
@endphp
<div class="mv-container">
    <div class="mv-flex is-{{ $layout }}" style="--g:{{ $sf->num($s['gap'] ?? 16, 0, 160) }}px;--al:{{ $align }};--cols:{{ $sf->num($s['columns'] ?? 3, 1, 8) }};--mh:{{ $sf->num($s['min_height'] ?? 0, 0, 100) }}vh">
        @foreach ($blocks as $b)
            @php($bs = $b['settings'])
            @switch($b['type'])
                @case('heading')
                    @php($tag = in_array($bs['tag'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'p'], true) ? $bs['tag'] : 'h2')
                    <{{ $tag }}{!! $sf->e('text', $b['id']) !!} class="mv-block-heading" style="margin:0;font-size:clamp({{ min(28, $sf->num($bs['size'] ?? 40, 12, 160)) }}px, {{ round($sf->num($bs['size'] ?? 40, 12, 160) / 14, 2) }}vw, {{ $sf->num($bs['size'] ?? 40, 12, 160) }}px);{{ ! empty($bs['uppercase']) ? 'text-transform:uppercase;' : '' }}letter-spacing:{{ $sf->num($bs['letter_spacing'] ?? 0, -5, 40) / 100 }}em;font-family:var(--font-heading)">{{ $sf->t($bs, 'text') }}</{{ $tag }}>
                    @break
                @case('text')
                    <div class="mv-rte mv-block-text"{!! $sf->e('text', $b['id']) !!}>{!! $sf->rich($sf->t($bs, 'text')) !!}</div>
                    @break
                @case('button')
                    @php($style = in_array($bs['style'] ?? '', ['primary', 'outline', 'link'], true) ? $bs['style'] : 'primary')
                    <a class="mv-btn {{ $style !== 'primary' ? 'mv-btn--'.$style : '' }}" href="{{ $sf->link($bs['url'] ?? '/shop') }}"{!! $sf->e('label', $b['id']) !!}>{{ $sf->t($bs, 'label') }}</a>
                    @break
                @case('image')
                    @php($href = ! empty($bs['link_url']) ? $sf->link($bs['link_url']) : null)
                    <div class="mv-block-image"{!! $sf->ei('image', $b['id']) !!}>@if ($href)<a href="{{ $href }}">@endif @include('storefront.partials.media', ['src' => $sf->media($bs['image'] ?? null), 'alt' => $bs['alt'] ?? '', 'ratio' => $bs['aspect'] ?? 'auto']) @if ($href)</a>@endif</div>
                    @break
                @case('video')
                    @php($url = $sf->media($bs['video_url'] ?? null))
                    <div class="mv-video-frame" style="aspect-ratio:16/9;width:100%">
                        @if ($url && ($embed = $sf->embedUrl($url)))<iframe src="{{ $embed }}" title="" allowfullscreen loading="lazy"></iframe>
                        @elseif ($url)<video src="{{ $url }}" controls playsinline preload="metadata" @if ($p = $sf->media($bs['poster_image'] ?? null)) poster="{{ $p }}" @endif></video>
                        @else<div class="mv-placeholder" style="position:absolute;inset:0"></div>@endif
                    </div>
                    @break
                @case('product')
                    @php($product = ! empty($bs['product_id']) ? $catalog->live()->find((int) $bs['product_id']) : null)
                    @if ($product)<div style="width:100%;max-width:360px">@include('storefront.partials.product-card', ['product' => $product])</div>
                    @else<div class="mv-card" style="width:100%;max-width:360px">@include('storefront.partials.media')<span class="mv-muted">{{ $sf->isAr() ? 'اختر منتجاً' : 'Choose a product' }}</span></div>@endif
                    @break
                @case('countdown')
                    <div class="mv-countdown" data-countdown="{{ $bs['ends_at'] ?? '' }}"></div>
                    @break
                @case('badge')
                    <span class="mv-pill"{!! $sf->e('text', $b['id']) !!}>{{ $sf->t($bs, 'text') }}</span>
                    @break
                @case('spacer')
                    <div style="height:{{ $sf->num($bs['height'] ?? 24, 0, 200) }}px;width:100%" aria-hidden="true"></div>
                    @break
                @case('divider')
                    <hr class="mv-divider">
                    @break
                @case('html')
                    <div class="mv-block-html" style="width:100%">{!! \App\Modules\Builder\Services\HtmlSanitizer::clean((string) ($bs['code'] ?? '')) !!}</div>
                    @break
            @endswitch
        @endforeach
    </div>
</div>
