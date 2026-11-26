@php
    $style = $s['card_style'] ?? 'below';
    $carousel = ($s['layout'] ?? 'grid') === 'carousel';
@endphp
<div class="mv-container" @if ($carousel) data-carousel @endif>
    @include('storefront.partials.section-head', ['arrows' => $carousel])
    <div class="{{ $carousel ? 'mv-carousel' : 'mv-grid' }}" style="--cols:{{ $sf->num($s['columns'] ?? 3, 1, 6) }};--cols-m:{{ $sf->num($s['mobile_columns'] ?? 2, 1, 2) }}">
        @foreach ($blocks as $b)
            @php
                $bs = $b['settings'];
                $img = $sf->media($bs['image'] ?? null);
                $href = ! empty($bs['link_url']) ? $sf->link($bs['link_url']) : null;
                $tag = $href ? 'a' : 'div';
            @endphp
            <{{ $tag }}{!! $sf->eb($b['id']) !!} @if ($href) href="{{ $href }}" @endif class="{{ $style === 'overlay' ? 'mv-card-overlay' : 'mv-card-plain' }}">
                @if ($style !== 'plain' || $img)
                    @include('storefront.partials.media', ['src' => $img, 'alt' => $sf->t($bs, 'title'), 'ratio' => $s['aspect'] ?? 'portrait', 'edit' => $sf->ei('image', $b['id'])])
                @endif
                <div class="{{ $style === 'overlay' ? 'mv-card-overlay__text' : '' }}">
                    @if (($l = $sf->t($bs, 'label')) !== '')<span class="mv-num"{!! $sf->e('label', $b['id']) !!}>{{ $l }}</span>@endif
                    @if (($t = $sf->t($bs, 'title')) !== '')<h3 class="mv-h3"{!! $sf->e('title', $b['id']) !!}>{{ $t }}</h3>@endif
                    @if (($x = $sf->t($bs, 'text')) !== '')<p class="{{ $style === 'overlay' ? '' : 'mv-muted' }}" style="font-size:14px;margin-top:6px"{!! $sf->e('text', $b['id']) !!}>{{ $x }}</p>@endif
                </div>
            </{{ $tag }}>
        @endforeach
    </div>
</div>
