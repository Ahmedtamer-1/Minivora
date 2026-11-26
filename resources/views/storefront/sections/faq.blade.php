<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-faq">
        @foreach ($blocks as $i => $b)
            <details @if ($i === 0 || $sf->preview) open @endif{!! $sf->eb($b['id']) !!}>
                <summary><span{!! $sf->e('question', $b['id']) !!}>{{ $sf->t($b['settings'], 'question') }}</span></summary>
                <div class="mv-rte"{!! $sf->e('answer', $b['id']) !!}>{!! $sf->rich($sf->t($b['settings'], 'answer')) !!}</div>
            </details>
        @endforeach
    </div>
</div>
