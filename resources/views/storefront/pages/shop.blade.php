{{-- Shop, category and search results: filter, sort, paginate. FR-CAT-04, FR-CAT-05 --}}
@extends('storefront.layouts.store')
@php
    $title = $search ? ($sf->isAr() ? 'نتائج البحث' : 'Search') : ($category ? $sf->field($category, 'name') : ($sf->isAr() ? 'كل المنتجات' : 'All products'));
    $base = $category ? $sf->url('/categories/'.$category->slug) : ($search ? $sf->url('/search') : $sf->url('/shop'));
    $sorts = ['newest' => $sf->isAr() ? 'الأحدث' : 'Newest', 'best_selling' => $sf->isAr() ? 'الأكثر مبيعاً' : 'Best selling', 'price_asc' => $sf->isAr() ? 'السعر: الأقل' : 'Price: low to high', 'price_desc' => $sf->isAr() ? 'السعر: الأعلى' : 'Price: high to low'];
@endphp
@section('title', $title.' · '.$sf->store->name)
@section('content')
    <div class="mv-container">
        <div class="mv-page-head">
            <nav class="mv-breadcrumbs" aria-label="Breadcrumb"><a href="{{ $sf->url('/') }}">{{ $sf->isAr() ? 'الرئيسية' : 'Home' }}</a><span>/</span><span>{{ $title }}</span></nav>
            <h1 class="mv-h2" style="margin:0">{{ $title }}</h1>
            @if ($search)
                <form action="{{ $base }}" method="get" class="mv-inline-form" style="margin-top:16px" role="search">
                    <input class="mv-input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" aria-label="{{ $sf->isAr() ? 'بحث' : 'Search' }}" placeholder="{{ $sf->isAr() ? 'ابحث…' : 'Search…' }}">
                    <button class="mv-btn">{{ $sf->isAr() ? 'بحث' : 'Search' }}</button>
                </form>
            @endif
        </div>
        <div class="mv-shop">
            <form class="mv-filters" method="get" action="{{ $base }}">
                @if ($search)<input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">@endif
                @if ($categories->isNotEmpty())
                    <fieldset>
                        <legend>{{ $sf->isAr() ? 'الفئات' : 'Categories' }}</legend>
                        <a href="{{ $sf->url('/shop') }}" class="{{ ! $category ? 'is-active' : '' }}">{{ $sf->isAr() ? 'الكل' : 'All' }}</a>
                        @foreach ($categories as $c)
                            <a href="{{ $sf->url('/categories/'.$c->slug) }}" class="{{ $category?->id === $c->id ? 'is-active' : '' }}">{{ $sf->field($c, 'name') }} <span class="mv-muted">({{ $c->products_count }})</span></a>
                        @endforeach
                    </fieldset>
                @endif
                <fieldset>
                    <legend>{{ $sf->isAr() ? 'السعر (ج.م)' : 'Price (EGP)' }}</legend>
                    <div style="display:flex;gap:8px">
                        <input class="mv-input" type="number" min="0" name="min" value="{{ $filters['min'] ?? '' }}" placeholder="{{ $sf->isAr() ? 'من' : 'Min' }}" aria-label="Min">
                        <input class="mv-input" type="number" min="0" name="max" value="{{ $filters['max'] ?? '' }}" placeholder="{{ $sf->isAr() ? 'إلى' : 'Max' }}" aria-label="Max">
                    </div>
                </fieldset>
                <fieldset>
                    <legend>{{ $sf->isAr() ? 'التوفر' : 'Availability' }}</legend>
                    <label><input type="checkbox" name="availability" value="in_stock" @checked(($filters['availability'] ?? '') === 'in_stock')> {{ $sf->isAr() ? 'المتوفر فقط' : 'In stock only' }}</label>
                </fieldset>
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'newest' }}">
                <button class="mv-btn mv-btn--outline">{{ $sf->isAr() ? 'تطبيق' : 'Apply' }}</button>
            </form>
            <div>
                <div class="mv-toolbar">
                    <span>{{ $products->total() }} {{ $sf->isAr() ? 'منتج' : 'products' }}</span>
                    <form method="get" action="{{ $base }}">
                        @foreach (['min', 'max', 'availability', 'q'] as $k)@if (! empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif @endforeach
                        <label class="mv-skip" for="sort">{{ $sf->isAr() ? 'ترتيب' : 'Sort' }}</label>
                        <select id="sort" class="mv-select" name="sort" onchange="this.form.submit()">
                            @foreach ($sorts as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </form>
                </div>
                @if ($products->isEmpty())
                    <p class="mv-empty">{{ $sf->isAr() ? 'لا توجد منتجات مطابقة.' : 'No products match.' }}</p>
                @else
                    <div class="mv-grid" style="--cols:3;--cols-m:2">
                        @foreach ($products as $product)@include('storefront.partials.product-card', ['product' => $product])@endforeach
                    </div>
                    @if ($products->hasPages())
                        <nav class="mv-pagination" aria-label="Pages">
                            @foreach ($products->getUrlRange(1, $products->lastPage()) as $n => $url)
                                @if ($n === $products->currentPage())<span class="is-current" aria-current="page">{{ $n }}</span>@else<a href="{{ $url }}">{{ $n }}</a>@endif
                            @endforeach
                        </nav>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
