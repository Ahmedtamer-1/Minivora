<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-faq">
        @foreach ($blocks as $i => $b)
            <details @if ($i === 0) open @endif>
                <summary>{{ $sf->t($b['settings'], 'question') }}</summary>
                <div class="mv-rte">{!! $sf->rich($sf->t($b['settings'], 'answer')) !!}</div>
            </details>
        @endforeach
    </div>
</div>
