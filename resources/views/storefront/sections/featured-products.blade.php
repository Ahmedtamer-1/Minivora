@php
    $products = $catalog->forSection($s);
    $carousel = ($s['layout'] ?? 'grid') === 'carousel';
    $viewAll = ! empty($s['show_view_all']) ? $sf->t($s, 'view_all_label', 'View all') : '';
    $viewAllUrl = ($s['source'] ?? '') === 'category' && ! empty($s['category_id']) && ($cat = \App\Modules\Catalog\Models\Category::query()->find((int) $s['category_id']))
        ? $sf->url('/categories/'.$cat->slug) : $sf->url('/shop');
    $action = $viewAll !== '' ? '<a class="mv-btn mv-btn--link" href="'.e($viewAllUrl).'">'.e($viewAll).'</a>' : '';
@endphp
<div class="mv-container" @if ($carousel) data-carousel @endif>
    @include('storefront.partials.section-head', ['action' => $action, 'arrows' => $carousel])
    @if ($products->isEmpty())
        <div class="mv-grid" style="--cols:{{ $sf->num($s['columns'] ?? 4, 1, 6) }};--cols-m:{{ $sf->num($s['mobile_columns'] ?? 2, 1, 2) }}">
            @for ($i = 0; $i < min(4, (int) ($s['limit'] ?? 4)); $i++)
                <div class="mv-card">@include('storefront.partials.media', ['ratio' => $s['aspect'] ?? 'portrait'])<div><h3 class="mv-card__title">{{ $sf->isAr() ? 'اسم المنتج' : 'Product name' }}</h3><div class="mv-card__price mv-muted">—</div></div></div>
            @endfor
        </div>
        @if ($sf->preview)<p class="mv-muted" style="margin-top:12px;font-size:13px">{{ $sf->isAr() ? 'تظهر المنتجات المنشورة هنا.' : 'Your live products will appear here.' }}</p>@endif
    @else
        <div class="{{ $carousel ? 'mv-carousel' : 'mv-grid' }}" style="--cols:{{ $sf->num($s['columns'] ?? 4, 1, 6) }};--cols-m:{{ $sf->num($s['mobile_columns'] ?? 2, 1, 2) }}">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product, 'ratio' => $s['aspect'] ?? 'portrait', 'hoverSwap' => ! empty($s['hover_swap'])])
            @endforeach
        </div>
    @endif
</div>
