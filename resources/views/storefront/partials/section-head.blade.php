{{-- Heading row: title, subtitle and an optional action (link or carousel arrows). --}}
@php($title = $sf->t($s, 'heading'))
@php($sub = $sf->t($s, 'subheading'))
@if ($title !== '' || $sub !== '' || ! empty($action) || ! empty($arrows))
    <div class="mv-section-head">
        <div>
            @if ($title !== '')<h2 class="mv-h2"{!! $sf->e('heading') !!}>{{ $title }}</h2>@endif
            @if ($sub !== '')<p{!! $sf->e('subheading') !!}>{{ $sub }}</p>@endif
        </div>
        @if (! empty($action) || ! empty($arrows))
            <div class="mv-carousel-nav">
                {!! $action ?? '' !!}
                @if (! empty($arrows))
                    <button type="button" class="mv-icon-btn" data-carousel-prev aria-label="{{ $sf->isAr() ? 'السابق' : 'Previous' }}"><svg class="mv-flip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg></button>
                    <button type="button" class="mv-icon-btn" data-carousel-next aria-label="{{ $sf->isAr() ? 'التالي' : 'Next' }}"><svg class="mv-flip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg></button>
                @endif
            </div>
        @endif
    </div>
@endif
