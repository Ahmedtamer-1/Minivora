{{-- Store home: the published builder homepage (FR-BLD-01), or a friendly default until one is published. --}}
@extends('storefront.layouts.store')

@section('title', $page && ($t = $sf->field($page, 'title')) && ! in_array($t, ['Home', 'Untitled Page'], true) ? $sf->store->name.' · '.$t : $sf->store->name)

@section('content')
    @if (! empty($sections))
        {!! $renderer->render($sections, $sf) !!}
    @else
        <section class="mv-section" style="--pt:0;--pb:0">
            <div class="mv-hero mv-h-medium is-empty" style="--ai:center;--jc:center">
                <div class="mv-hero__media"></div>
                <div class="mv-hero__content"><div class="mv-hero__inner">
                    <h1 class="mv-hero__title">{{ __('storefront.home.welcome', ['store' => $sf->store->name]) }}</h1>
                    @if ($sf->store->profileData()->sells)<p class="mv-hero__text">{{ $sf->store->profileData()->sells }}</p>@endif
                    <p class="mv-hero__text mv-muted">{{ __('storefront.home.soon') }}</p>
                    <div class="mv-btn-row"><a class="mv-btn" href="{{ $sf->url('/shop') }}">{{ $sf->isAr() ? 'تسوق الآن' : 'Shop now' }}</a></div>
                </div></div>
            </div>
        </section>
    @endif
@endsection
