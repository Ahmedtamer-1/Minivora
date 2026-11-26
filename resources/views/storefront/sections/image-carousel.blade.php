<div class="mv-container" data-carousel>
    @include('storefront.partials.section-head', ['arrows' => count($blocks) > 1])
    <div class="mv-carousel" style="--cols:{{ $sf->num($s['per_view'] ?? 3, 1, 6) }};--cols-m:1">
        @forelse ($blocks as $b)
            @php($href = ! empty($b['settings']['link_url']) ? $sf->link($b['settings']['link_url']) : null)
            <figure style="margin:0"{!! $sf->eb($b['id']) !!}>
                @if ($href)<a href="{{ $href }}">@endif
                @include('storefront.partials.media', ['src' => $sf->media($b['settings']['image'] ?? null), 'alt' => $sf->t($b['settings'], 'caption'), 'ratio' => $s['aspect'] ?? 'portrait', 'edit' => $sf->ei('image', $b['id'])])
                @if ($href)</a>@endif
                @if (($c = $sf->t($b['settings'], 'caption')) !== '')<figcaption class="mv-muted" style="font-size:13px;margin-top:8px"{!! $sf->e('caption', $b['id']) !!}>{{ $c }}</figcaption>@endif
            </figure>
        @empty
            @for ($i = 0; $i < 3; $i++)@include('storefront.partials.media', ['ratio' => $s['aspect'] ?? 'portrait'])@endfor
        @endforelse
    </div>
</div>
