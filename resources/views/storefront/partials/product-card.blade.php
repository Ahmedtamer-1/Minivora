{{-- Product card. Vars: $product, $sf, $ratio, $hoverSwap --}}
@php
    $images = \App\Modules\Builder\Storefront\StorefrontCatalog::images($product);
    $onSale = $product->compare_at_price !== null && $product->compare_at_price > $product->price;
@endphp
<a class="mv-card" href="{{ $sf->url('/products/'.\App\Modules\Builder\Storefront\StorefrontCatalog::handle($product)) }}">
    <div class="mv-card__media">
        @if ($product->stock === 0 && ! $product->has_options)
            <span class="mv-tag">{{ $sf->isAr() ? 'نفد' : 'Sold out' }}</span>
        @elseif ($onSale)
            <span class="mv-tag mv-tag--sale">−{{ (int) round(100 - $product->price * 100 / $product->compare_at_price) }}%</span>
        @endif
        @include('storefront.partials.media', ['src' => $images[0] ?? null, 'src2' => ($hoverSwap ?? true) ? ($images[1] ?? null) : null, 'alt' => $sf->field($product, 'name'), 'ratio' => $ratio ?? 'portrait'])
    </div>
    <div>
        <h3 class="mv-card__title">{{ $sf->field($product, 'name') }}</h3>
        <div class="mv-card__price">
            <span>{{ $sf->money($product->price) }}</span>
            @if ($onSale)<s>{{ $sf->money($product->compare_at_price) }}</s>@endif
        </div>
    </div>
</a>
