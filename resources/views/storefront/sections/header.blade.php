@php
    $logo = $sf->media($s['logo'] ?? null);
    $tagline = $sf->t($s, 'tagline');
    $layout = in_array($s['layout'] ?? '', ['logo-start', 'logo-center', 'logo-start-menu-start'], true) ? $s['layout'] : 'logo-start';
    $cartCount = (int) ($cartCount ?? 0);
@endphp
<div class="mv-container" @if (! empty($s['sticky'])) data-sticky @endif @if (! empty($s['transparent_on_home'])) data-transparent @endif>
    <div class="mv-header mv-header--{{ $layout }}">
        <button type="button" class="mv-tool mv-menu-toggle" data-drawer-open aria-expanded="false" aria-label="{{ $sf->isAr() ? 'القائمة' : 'Menu' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a class="mv-logo" href="{{ $sf->url('/') }}">
            @if ($logo)<img src="{{ $logo }}" alt="{{ $sf->store->name }}" style="width:{{ $sf->num($s['logo_width'] ?? 120, 40, 320) }}px">@else{{ $sf->store->name }}@endif
            @if ($tagline !== '')<span class="mv-logo__tagline">{{ $tagline }}</span>@endif
        </a>
        <nav class="mv-nav {{ ! empty($s['uppercase_menu']) ? 'is-upper' : '' }}" aria-label="{{ $sf->isAr() ? 'القائمة الرئيسية' : 'Main' }}">
            @foreach ($blocks as $b)
                @php($children = $sf->links($sf->t($b['settings'], 'children')))
                <div class="mv-nav__item">
                    <a class="mv-nav__link {{ ! empty($b['settings']['highlight']) ? 'is-highlight' : '' }}" href="{{ $sf->link($b['settings']['url'] ?? '/') }}">
                        {{ $sf->t($b['settings'], 'label') }}
                        @if ($children)<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>@endif
                    </a>
                    @if ($children)
                        <div class="mv-nav__menu">@foreach ($children as [$label, $url])<a href="{{ $url }}">{{ $label }}</a>@endforeach</div>
                    @endif
                </div>
            @endforeach
        </nav>
        <div class="mv-header__tools">
            @if (! empty($s['show_language']))
                <a class="mv-tool" href="{{ $sf->switchUrl() }}" hreflang="{{ $sf->isAr() ? 'en' : 'ar' }}" lang="{{ $sf->isAr() ? 'en' : 'ar' }}">{{ $sf->isAr() ? 'EN' : 'ع' }}</a>
            @endif
            @if (! empty($s['show_search']))
                <button type="button" class="mv-tool" data-search-toggle aria-expanded="false" aria-label="{{ $sf->isAr() ? 'بحث' : 'Search' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
            @endif
            @if (! empty($s['show_cart']))
                <a class="mv-tool" href="{{ $sf->url('/cart') }}" aria-label="{{ $sf->isAr() ? 'السلة' : 'Cart' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                    <span class="mv-tool__count" data-cart-count @if ($cartCount === 0) hidden @endif>{{ $cartCount }}</span>
                </a>
            @endif
        </div>
    </div>
    @if (! empty($s['show_search']))
        <div class="mv-search-bar">
            <form action="{{ $sf->url('/search') }}" method="get" role="search">
                <input class="mv-input" type="search" name="q" placeholder="{{ $sf->isAr() ? 'ابحث عن منتج…' : 'Search products…' }}" aria-label="{{ $sf->isAr() ? 'بحث' : 'Search' }}">
                <button class="mv-btn" type="submit">{{ $sf->isAr() ? 'بحث' : 'Search' }}</button>
            </form>
        </div>
    @endif
</div>
<div class="mv-drawer" role="dialog" aria-modal="true" aria-label="{{ $sf->isAr() ? 'القائمة' : 'Menu' }}">
    <div class="mv-drawer__scrim" data-drawer-close></div>
    <div class="mv-drawer__panel">
        <button type="button" class="mv-tool" data-drawer-close aria-label="{{ $sf->isAr() ? 'إغلاق' : 'Close' }}" style="align-self:flex-end"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        @foreach ($blocks as $i => $b)
            <a href="{{ $sf->link($b['settings']['url'] ?? '/') }}"><span>{{ $sf->t($b['settings'], 'label') }}</span><span class="mv-num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span></a>
            @php($children = $sf->links($sf->t($b['settings'], 'children')))
            @if ($children)<div class="mv-drawer__sub">@foreach ($children as [$label, $url])<a href="{{ $url }}">{{ $label }}</a>@endforeach</div>@endif
        @endforeach
        <a href="{{ $sf->switchUrl() }}">{{ $sf->isAr() ? 'English' : 'العربية' }}</a>
    </div>
</div>
