{{-- Store home. Builder sections (FR-BLD-01) render here once the Builder module ships. --}}
@extends('storefront.layouts.app')

@section('content')
    <section class="aurora">
        <div class="mx-auto flex min-h-[60vh] max-w-6xl flex-col items-center justify-center px-4 py-16 text-center">
            <div class="glass max-w-xl rounded-xl px-8 py-10">
                <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('storefront.home.welcome', ['store' => $store->name]) }}</h1>
                @if ($store->profileData()->sells)
                    <p class="mt-3 text-ink-muted">{{ $store->profileData()->sells }}</p>
                @endif
                <p class="mt-6 text-sm text-ink-muted">{{ __('storefront.home.soon') }}</p>
            </div>
        </div>
    </section>
@endsection
