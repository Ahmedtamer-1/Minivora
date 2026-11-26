<figure class="mv-quote" style="margin:0"{!! $sf->eb($bid ?? null) !!}>
    @if (($stars = (int) ($q['rating'] ?? 0)) > 0)<div class="mv-stars" aria-label="{{ $stars }}/5">{{ str_repeat('★', min(5, $stars)) }}</div>@endif
    <blockquote{!! isset($bid) ? $sf->e('quote', $bid) : '' !!}>{{ $sf->t($q, 'quote') }}</blockquote>
    <figcaption class="mv-quote__who">
        @if ($img = $sf->media($q['image'] ?? null))<img src="{{ $img }}" alt="" loading="lazy">@endif
        <span><b{!! isset($bid) ? $sf->e('author', $bid) : '' !!}>{{ $q['author'] ?? '' }}</b>@if (($r = $sf->t($q, 'role')) !== '')<br><span class="mv-muted"{!! isset($bid) ? $sf->e('role', $bid) : '' !!}>{{ $r }}</span>@endif</span>
    </figcaption>
</figure>
