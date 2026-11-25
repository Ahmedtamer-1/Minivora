@php
    $video = $sf->media($s['video_url'] ?? null);
    $poster = $sf->media($s['poster_image'] ?? null);
    $title = $sf->t($s, 'headline');
    $text = $sf->t($s, 'subheadline');
    $cta = $sf->t($s, 'cta_label');
    $embed = $video ? $sf->embedUrl($video, true) : null;
@endphp
<div class="mv-hero mv-h-{{ $s['height'] ?? 'large' }} {{ $video || $poster ? '' : 'is-empty' }}" style="{{ $sf->position((string) ($s['content_position'] ?? 'center')) }};{{ $sf->overlay($s['overlay_opacity'] ?? 30) }}">
    <div class="mv-hero__media">
        @if ($embed)
            <iframe src="{{ $embed }}" title="" allow="autoplay; encrypted-media" tabindex="-1"></iframe>
        @elseif ($video)
            <video src="{{ $video }}" @if($poster) poster="{{ $poster }}" @endif {{ ! empty($s['autoplay']) ? 'autoplay' : '' }} {{ ! empty($s['muted']) ? 'muted' : '' }} {{ ! empty($s['loop']) ? 'loop' : '' }} playsinline preload="metadata"></video>
        @elseif ($poster)
            <img src="{{ $poster }}" alt="">
        @endif
    </div>
    <div class="mv-hero__content">
        <div class="mv-hero__inner">
            <h1 class="mv-hero__title">{{ $title !== '' ? $title : $sf->store->name }}</h1>
            @if ($text !== '')<p class="mv-hero__text">{{ $text }}</p>@endif
            @if ($cta !== '')<div class="mv-btn-row"><a class="mv-btn mv-btn--light" href="{{ $sf->link($s['cta_url'] ?? '/shop') }}">{{ $cta }}</a></div>@endif
        </div>
    </div>
</div>
