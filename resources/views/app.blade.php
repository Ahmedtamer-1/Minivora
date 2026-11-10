<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title inertia>{{ config('app.name', 'Minivora') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@300;400;500;600&display=swap" rel="stylesheet">
        <script>
            // Apply the saved theme and display preferences before paint to avoid a flash (lib/display-prefs.ts).
            try {
                const h = document.documentElement, t = localStorage.getItem('mv-theme'), p = JSON.parse(localStorage.getItem('mv-display') || '{}');
                if (t) h.dataset.theme = t;
                if (p.textSize && p.textSize !== 'default') h.dataset.textSize = p.textSize;
                if (p.motion === 'reduce') h.dataset.motion = 'reduce';
                if (p.transparency === 'reduce') h.dataset.transparency = 'reduce';
                if (p.contrast === 'more') h.dataset.contrast = 'more';
                if (p.underlineLinks) h.dataset.links = 'underline';
            } catch (e) {}
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
