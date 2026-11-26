{{-- Builder storefront layout: theme tokens, fonts, header/footer groups, global custom CSS. FR-BLD-01, FR-BLD-03, FR-CAT-15 --}}
@php
    /** @var \App\Modules\Builder\Storefront\StorefrontContext $sf */
    $theme = $sf->theme;
    $ts = $theme->resolvedSettings();
    $radius = ['none' => '0px', 'sm' => '4px', 'md' => '8px', 'lg' => '16px', 'full' => '999px'][$theme->corner_radius] ?? '8px';
    $dark = $theme->color_mode === 'dark';
    $bg = $ts['background_color'] ?: ($dark ? '#0b0b0c' : '#ffffff');
    $fg = $ts['text_color'] ?: ($dark ? '#f4f4f5' : '#111111');
    $fontName = fn ($f) => preg_replace('/[^A-Za-z0-9 ]/', '', (string) $f) ?: 'Cairo';
    $fonts = array_unique([$fontName($theme->font_heading), $fontName($theme->font_body), 'Noto Sans Arabic']);
    $fontQuery = implode('&', array_map(fn ($f) => 'family='.str_replace(' ', '+', $f).':wght@300;400;500;600;700;800', $fonts));
@endphp
<!DOCTYPE html>
<html lang="{{ $sf->locale }}" dir="{{ $sf->dir() }}" data-mode="{{ $dark ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $sf->store->name)</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @if ($sf->preview)<meta name="robots" content="noindex, nofollow">@endif
    @hasSection('noindex')<meta name="robots" content="noindex">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $fontQuery }}&display=swap" rel="stylesheet">
    @vite(['resources/css/storefront.css', 'resources/js/storefront.ts'])
    <style>
        :root {
            --primary: {{ $theme->primary_color }};
            --accent: {{ $theme->accent_color }};
            --bg: {{ $bg }};
            --fg: {{ $fg }};
            --radius: {{ $radius }};
            --font-heading: '{{ $fontName($theme->font_heading) }}', 'Noto Sans Arabic', system-ui, sans-serif;
            --font-body: '{{ $fontName($theme->font_body) }}', 'Noto Sans Arabic', system-ui, sans-serif;
            --page-width: {{ (int) $ts['page_width'] }}px;
            --base-size: {{ (int) $ts['base_font_size'] }}px;
            --heading-weight: {{ (int) $ts['heading_weight'] }};
            --heading-transform: {{ $ts['heading_uppercase'] ? 'uppercase' : 'none' }};
            --heading-tracking: {{ ((int) $ts['heading_letter_spacing']) / 100 }}em;
            --button-transform: {{ $ts['button_uppercase'] ? 'uppercase' : 'none' }};
        }
    </style>
    @if (trim((string) $theme->custom_css) !== '')
        <style id="mv-theme-css">{!! \App\Modules\Builder\Services\CssSanitizer::clean((string) $theme->custom_css) !!}</style>
    @endif
    @stack('head')
</head>
<body class="mv-body mv-template-{{ $sf->template }} mv-buttons-{{ $ts['button_style'] }}{{ $sf->editing ? ' mv-preview' : '' }}" data-store="{{ $sf->store->subdomain }}" data-locale="{{ $sf->locale }}"
      data-cart-url="{{ $sf->raw('/cart/items') }}" data-cart-page="{{ $sf->url('/cart') }}">
    @if ($sf->preview && ! $sf->editing)
        <div class="mv-preview-bar" role="status">
            <span><b>{{ $sf->isAr() ? 'معاينة' : 'Preview' }}</b> · {{ $sf->isAr() ? 'هذه مسودة لم تُنشر بعد. لا يراها العملاء.' : 'This is your unpublished draft. Shoppers can\'t see it yet.' }}</span>
            <span class="mv-preview-bar__actions">
                <a href="{{ $sf->previewSwitchUrl }}">{{ $sf->isAr() ? 'English' : 'العربية' }}</a>
                <a href="{{ $sf->editorUrl }}">{{ $sf->isAr() ? 'العودة للمحرر' : 'Back to editor' }}</a>
            </span>
        </div>
    @endif
    @if (! $sf->preview && $sf->store->status() === \App\Modules\Platform\Enums\StoreStatus::OpeningSoon)
        <div class="mv-preview-bar" role="status">
            <span><b>{{ $sf->isAr() ? 'المتجر لم يُفتح بعد' : 'Not open yet' }}</b> · {{ $sf->isAr() ? 'فريقك فقط يرى هذه الصفحة. العملاء يرون صفحة "قريباً".' : 'Only your team can see this. Shoppers see the "opening soon" page.' }}</span>
            <span class="mv-preview-bar__actions"><a href="{{ rtrim((string) config('app.url'), '/') }}/dashboard">{{ $sf->isAr() ? 'لوحة التحكم' : 'Dashboard' }}</a></span>
        </div>
    @endif
    <a href="#main" class="mv-skip">{{ $sf->isAr() ? 'تخطَّ إلى المحتوى' : 'Skip to content' }}</a>
    <div class="mv-header-group" data-mv-group="header">{!! $headerHtml !!}</div>
    <main id="main" class="mv-main">
        @yield('content')
    </main>
    <div class="mv-footer-group" data-mv-group="footer">{!! $footerHtml !!}</div>
    <div class="mv-toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
