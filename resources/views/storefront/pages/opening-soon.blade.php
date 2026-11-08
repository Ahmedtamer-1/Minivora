{{-- "Opening soon" gate for unpublished stores. FR-ONB-06 --}}
@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $store->name }} · {{ __('storefront.opening_soon.title') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&family=Noto+Sans+Arabic:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="aurora flex min-h-screen items-center justify-center p-4">
    <main class="glass w-full max-w-md rounded-xl p-8 text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-ink-muted">{{ __('storefront.opening_soon.title') }}</p>
        <h1 class="mt-2 text-3xl font-semibold">{{ $store->name }}</h1>
        <p class="mt-3 text-ink-muted">{{ __('storefront.opening_soon.body', ['store' => $store->name]) }}</p>

        @if ($store->storefront_password)
            <form method="POST" action="/__unlock" class="mt-8 space-y-3 text-start">
                @csrf
                <label for="password" class="block text-sm font-medium">{{ __('storefront.opening_soon.have_password') }}</label>
                <div class="flex gap-2">
                    <input id="password" name="password" type="password" required autocomplete="off"
                           class="w-full rounded-md border-line bg-elevated text-ink focus:border-line-focus focus:ring-line-focus"
                           placeholder="{{ __('storefront.opening_soon.password') }}">
                    <button type="submit" class="rounded-md bg-primary px-4 py-2 font-medium text-primary-fg hover:bg-primary-hover">
                        {{ __('storefront.opening_soon.enter') }}
                    </button>
                </div>
                @error('password')
                    <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror
            </form>
        @endif
    </main>
</body>
</html>
