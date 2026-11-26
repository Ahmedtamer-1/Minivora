@php($btn = $sf->t($s, 'button_label'))
<div class="mv-container">
    <div class="mv-split {{ ($s['image_position'] ?? 'right') === 'right' ? 'is-reverse' : '' }}">
        @include('storefront.partials.media', ['src' => $sf->media($s['image'] ?? null), 'alt' => $sf->t($s, 'heading'), 'ratio' => 'landscape', 'edit' => $sf->ei('image')])
        <div>
            @if (($h = $sf->t($s, 'heading')) !== '')<h2 class="mv-h2"{!! $sf->e('heading') !!}>{{ $h }}</h2>@endif
            <div class="mv-rte"{!! $sf->e('body') !!}>{!! $sf->rich($sf->t($s, 'body')) !!}</div>
            @if ($btn !== '')<div class="mv-btn-row"><a class="mv-btn mv-btn--outline" href="{{ $sf->link($s['button_url'] ?? '/') }}"{!! $sf->e('button_label') !!}>{{ $btn }}</a></div>@endif
        </div>
    </div>
</div>
