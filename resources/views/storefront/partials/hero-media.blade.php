{{-- Background media for hero-like sections. Vars: $image, $mobile, $video, $poster, $alt, $eager --}}
<div class="mv-hero__media">
    @if (! empty($video) && ($embed = $sf->embedUrl($video, true)))
        <iframe src="{{ $embed }}" title="" allow="autoplay; encrypted-media" loading="lazy" tabindex="-1"></iframe>
    @elseif (! empty($video))
        <video src="{{ $video }}" @if(!empty($poster)) poster="{{ $poster }}" @endif autoplay muted loop playsinline preload="metadata"></video>
    @elseif (! empty($image))
        <picture>
            @if (! empty($mobile))<source media="(max-width: 749px)" srcset="{{ $mobile }}">@endif
            <img src="{{ $image }}" alt="{{ $alt ?? '' }}" @if (! empty($eager)) fetchpriority="high" @else loading="lazy" @endif>
        </picture>
    @endif
</div>
