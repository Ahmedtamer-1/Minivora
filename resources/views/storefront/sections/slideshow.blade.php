<div class="mv-slideshow" data-rotator data-interval="{{ (int) ($s['interval_ms'] ?? 5000) }}" data-autoplay="{{ ! empty($s['autoplay']) ? 'true' : 'false' }}">
    <div class="mv-slides">
        @forelse ($blocks as $i => $b)
            @php
                $bs = $b['settings'];
                $image = $sf->media($bs['image'] ?? null);
                $heading = $sf->t($bs, 'heading');
                $btn = $sf->t($bs, 'button_label');
            @endphp
            <div{!! $sf->ei('image', $b['id']) !!} class="mv-hero mv-h-{{ $s['height'] ?? 'large' }} {{ $image ? '' : 'is-empty' }} {{ $i === 0 ? 'is-active' : '' }}" data-rotator-item style="{{ $sf->position((string) ($bs['content_position'] ?? 'bottom-start')) }};{{ $sf->overlay($s['overlay_opacity'] ?? 30) }}">
                @include('storefront.partials.hero-media', ['image' => $image, 'mobile' => $sf->media($bs['mobile_image'] ?? null), 'alt' => $heading, 'eager' => $i === 0 && $index < 2])
                <div class="mv-hero__content">
                    <div class="mv-hero__inner">
                        @if (($e = $sf->t($bs, 'eyebrow')) !== '')<span class="mv-eyebrow"{!! $sf->e('eyebrow', $b['id']) !!}>{{ $e }}</span>@endif
                        @if ($heading !== '')<h2 class="mv-hero__title"{!! $sf->e('heading', $b['id']) !!}>{{ $heading }}</h2>@endif
                        @if (($t = $sf->t($bs, 'text')) !== '')<p class="mv-hero__text"{!! $sf->e('text', $b['id']) !!}>{{ $t }}</p>@endif
                        @if (! empty($bs['countdown_to']))<div class="mv-countdown" data-countdown="{{ $bs['countdown_to'] }}"></div>@endif
                        @if ($btn !== '')<div class="mv-btn-row"><a class="mv-btn {{ $image ? 'mv-btn--light' : '' }}" href="{{ $sf->link($bs['button_url'] ?? '/shop') }}"{!! $sf->e('button_label', $b['id']) !!}>{{ $btn }}</a></div>@endif
                    </div>
                </div>
            </div>
        @empty
            <div class="mv-hero mv-h-medium is-empty is-active"><div class="mv-hero__media"></div><div class="mv-hero__content"><p>{{ $sf->isAr() ? 'أضف شرائح' : 'Add slides' }}</p></div></div>
        @endforelse
    </div>
    @if (count($blocks) > 1)
        <div class="mv-slideshow__controls">
            @if (! empty($s['show_dots']))
                <div class="mv-dots">@foreach ($blocks as $i => $b)<button type="button" class="mv-dot" data-rotator-dot aria-label="{{ $i + 1 }}"></button>@endforeach</div>
            @endif
            @if (! empty($s['show_arrows']))
                <button type="button" class="mv-icon-btn" data-rotator-prev aria-label="{{ $sf->isAr() ? 'السابق' : 'Previous' }}"><svg class="mv-flip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg></button>
                <button type="button" class="mv-icon-btn" data-rotator-next aria-label="{{ $sf->isAr() ? 'التالي' : 'Next' }}"><svg class="mv-flip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg></button>
            @endif
        </div>
    @endif
</div>
