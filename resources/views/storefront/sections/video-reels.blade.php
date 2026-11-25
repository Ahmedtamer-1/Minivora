<div class="mv-container" data-carousel>
    @include('storefront.partials.section-head', ['arrows' => count($blocks) > 4])
    <div class="mv-carousel" style="--cols:5;--cols-m:2">
        @forelse ($blocks as $b)
            @php
                $bs = $b['settings'];
                $url = $sf->media($bs['video_url'] ?? null);
                $href = ! empty($bs['product_url']) ? $sf->link($bs['product_url']) : null;
                $tag = $href ? 'a' : 'div';
            @endphp
            <{{ $tag }} class="mv-reel" @if ($href) href="{{ $href }}" @endif>
                <div class="mv-media">
                    @if ($url && ! $sf->embedUrl($url))<video src="{{ $url }}" @if ($p = $sf->media($bs['poster_image'] ?? null)) poster="{{ $p }}" @endif muted loop playsinline preload="metadata" data-hover-play></video>
                    @elseif ($p = $sf->media($bs['poster_image'] ?? null))<img src="{{ $p }}" alt="" loading="lazy">
                    @else<div class="mv-placeholder"></div>@endif
                </div>
                @if (($c = $sf->t($bs, 'caption')) !== '')<span style="font-size:14px">{{ $c }}</span>@endif
            </{{ $tag }}>
        @empty
            @for ($i = 0; $i < 5; $i++)<div class="mv-reel"><div class="mv-media"><div class="mv-placeholder"></div></div></div>@endfor
        @endforelse
    </div>
</div>
