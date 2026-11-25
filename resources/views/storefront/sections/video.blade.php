@php
    $url = $sf->media($s['video_url'] ?? null);
    $embed = $url ? $sf->embedUrl($url, ! empty($s['autoplay'])) : null;
    $ratio = str_replace(':', ' / ', in_array($s['aspect_ratio'] ?? '', ['16:9', '4:3', '1:1', '9:16', '21:9'], true) ? $s['aspect_ratio'] : '16:9');
    $poster = $sf->media($s['poster_image'] ?? null);
@endphp
<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-video-frame" style="aspect-ratio:{{ $ratio }};{{ ($s['aspect_ratio'] ?? '') === '9:16' ? 'max-width:420px;margin-inline:auto' : '' }}">
        @if ($embed)
            <iframe src="{{ $embed }}" title="{{ $sf->t($s, 'heading') }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
        @elseif ($url)
            <video src="{{ $url }}" @if ($poster) poster="{{ $poster }}" @endif controls playsinline preload="metadata" @if (! empty($s['autoplay'])) autoplay muted loop @endif></video>
        @else
            <div class="mv-placeholder" style="position:absolute;inset:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="m8 5 11 7-11 7V5Z"/></svg></div>
        @endif
    </div>
</div>
