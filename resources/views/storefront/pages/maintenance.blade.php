{{-- Shown to guests when a store owner enables maintenance mode. FR-CAT-14 --}}
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $store->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas p-4 text-ink">
    <main class="max-w-md text-center">
        <h1 class="text-2xl font-semibold">{{ $store->name }}</h1>
        <p class="mt-2 text-ink-muted">{{ $message }}</p>
    </main>
</body>
</html>
