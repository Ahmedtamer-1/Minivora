<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-grid" style="--cols:{{ $sf->num($s['columns'] ?? 3, 1, 4) }};--cols-m:1">
        @forelse ($blocks as $b)
            @php
                $url = $sf->media($b['settings']['video_url'] ?? null);
                $embed = $url ? $sf->embedUrl($url) : null;
            @endphp
            <figure style="margin:0">
                <div class="mv-video-frame" style="aspect-ratio:16/9">
                    @if ($embed)<iframe src="{{ $embed }}" title="{{ $sf->t($b['settings'], 'title') }}" allowfullscreen loading="lazy"></iframe>
                    @elseif ($url)<video src="{{ $url }}" @if ($p = $sf->media($b['settings']['poster_image'] ?? null)) poster="{{ $p }}" @endif controls playsinline preload="metadata"></video>
                    @else<div class="mv-placeholder" style="position:absolute;inset:0"></div>@endif
                </div>
                @if (($t = $sf->t($b['settings'], 'title')) !== '')<figcaption style="margin-top:10px;font-weight:600">{{ $t }}</figcaption>@endif
            </figure>
        @empty
            @for ($i = 0; $i < 3; $i++)<div class="mv-video-frame" style="aspect-ratio:16/9"><div class="mv-placeholder" style="position:absolute;inset:0"></div></div>@endfor
        @endforelse
    </div>
</div>
