{{-- Base storefront layout: lang/dir per language (FR-SEO-22), tokens, header and footer. --}}
@php
    $locale = app()->getLocale();
    $other = $locale === 'ar' ? 'en' : 'ar';
    $path = request()->path();
    $switchPath = '/'.$other.(str_contains($path, '/') ? '/'.\Illuminate\Support\Str::after($path, '/') : '');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $store->name)</title>
    @hasSection('noindex')
        <meta name="robots" content="noindex">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&family=Noto+Sans+Arabic:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex flex-col bg-canvas text-ink">
    <header class="border-b border-line bg-elevated">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
            <a href="/{{ $locale }}" class="font-display text-xl font-semibold">{{ $store->name }}</a>
            <a href="{{ $switchPath }}" hreflang="{{ $other }}" class="text-sm text-ink-link hover:underline">{{ __('storefront.switch_language') }}</a>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-line py-6 text-center text-xs text-ink-muted">
        © {{ now()->year }} {{ $store->name }} · {{ __('storefront.powered_by') }}
    </footer>
</body>
</html>
