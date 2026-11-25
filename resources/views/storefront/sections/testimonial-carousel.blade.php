<div class="mv-container mv-quote-carousel" data-rotator data-interval="{{ (int) ($s['interval_ms'] ?? 6000) }}" data-autoplay="{{ ! empty($s['autoplay']) ? 'true' : 'false' }}">
    @if (($h = $sf->t($s, 'heading')) !== '')<span class="mv-eyebrow">{{ $h }}</span>@endif
    <div class="mv-rotator">
        @forelse ($blocks as $i => $b)
            <figure data-rotator-item class="{{ $i === 0 ? 'is-active' : '' }}" style="margin:0">
                @if (($stars = (int) ($b['settings']['rating'] ?? 0)) > 0)<div class="mv-stars">{{ str_repeat('★', min(5, $stars)) }}</div>@endif
                <blockquote>“{{ $sf->t($b['settings'], 'quote') }}”</blockquote>
                <figcaption><b>{{ $b['settings']['author'] ?? '' }}</b> @if (($r = $sf->t($b['settings'], 'role')) !== '')<span class="mv-muted">· {{ $r }}</span>@endif</figcaption>
            </figure>
        @empty
            <figure data-rotator-item class="is-active" style="margin:0"><blockquote>“{{ $sf->isAr() ? 'أضف آراء عملائك من المحرر.' : 'Add customer quotes in the editor.' }}”</blockquote></figure>
        @endforelse
    </div>
    @if (count($blocks) > 1)
        <div class="mv-dots" style="justify-content:center;margin-top:24px">@foreach ($blocks as $b)<button type="button" class="mv-dot" style="background:var(--line)" data-rotator-dot aria-label="{{ $loop->iteration }}"></button>@endforeach</div>
        <style>#{{ $sid }} .mv-dot.is-active{background:currentColor!important}</style>
    @endif
</div>
