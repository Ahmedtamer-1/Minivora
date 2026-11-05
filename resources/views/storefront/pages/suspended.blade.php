{{-- Shown when a platform admin suspends a store. FR-ACC-08 --}}
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('storefront.suspended.title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas p-4 text-ink">
    <main class="max-w-md text-center">
        <h1 class="text-2xl font-semibold">{{ __('storefront.suspended.title') }}</h1>
        <p class="mt-2 text-ink-muted">{{ __('storefront.suspended.body') }}</p>
    </main>
</body>
</html>
