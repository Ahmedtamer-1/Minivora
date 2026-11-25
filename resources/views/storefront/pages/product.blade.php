{{-- Product page: gallery, variants, stock, delivery estimate, reviews, related. FR-CAT-07, FR-CAT-10 --}}
@extends('storefront.layouts.store')
@php
    use App\Modules\Builder\Storefront\StorefrontCatalog;
    $images = StorefrontCatalog::images($product);
    $name = $sf->field($product, 'name');
    $onSale = $product->compare_at_price !== null && $product->compare_at_price > $product->price;
    $variants = $product->variants->map(fn ($v) => ['id' => $v->id, 'options' => array_map('strval', (array) $v->options), 'price' => $sf->money($v->price ?: $product->price), 'stock' => $v->stock])->values();
    $optionValues = [];
    foreach ($product->variants as $v) { foreach ((array) $v->options as $k => $val) { $optionValues[$k][(string) $val] = true; } }
    $swatches = collect($product->colors ?? [])->keyBy('name');
    $from = now()->addDays(2); $to = now()->addDays(5);
    $soldOut = $variants->isEmpty() ? $product->stock <= 0 : $variants->every(fn ($v) => $v['stock'] <= 0);
@endphp
@section('title', ($product->seo_title ?: $name).' · '.$sf->store->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) ($product->seo_description ?: $product->description)), 155))
@section('content')
    <div class="mv-container">
        <nav class="mv-breadcrumbs" aria-label="Breadcrumb" style="margin-top:24px">
            <a href="{{ $sf->url('/') }}">{{ $sf->isAr() ? 'الرئيسية' : 'Home' }}</a><span>/</span>
            @if ($product->categoryRecord)<a href="{{ $sf->url('/categories/'.$product->categoryRecord->slug) }}">{{ $sf->field($product->categoryRecord, 'name') }}</a><span>/</span>@endif
            <span>{{ $name }}</span>
        </nav>
        <div class="mv-product">
            <div class="mv-gallery">
                @forelse ($images as $i => $img)
                    @include('storefront.partials.media', ['src' => $img, 'alt' => $name.' '.($i + 1), 'ratio' => 'portrait', 'eager' => $i === 0])
                @empty
                    @include('storefront.partials.media', ['ratio' => 'portrait'])
                @endforelse
            </div>
            <div class="mv-product__info">
                <h1 class="mv-h2" style="margin:0">{{ $name }}</h1>
                <div class="mv-price"><span data-price>{{ $sf->money($product->price) }}</span>@if ($onSale)<s>{{ $sf->money($product->compare_at_price) }}</s>@endif</div>
                <form data-product-form data-product-id="{{ $product->id }}" data-variants='@json($variants)'>
                    <input type="hidden" name="variant_id" value="">
                    @if ($optionValues)
                        <div class="mv-options">
                            @foreach ($optionValues as $option => $values)
                                <fieldset style="border:0;padding:0;margin:0">
                                    <legend class="mv-label">{{ $option }}</legend>
                                    <div class="mv-swatches">
                                        @foreach (array_keys($values) as $j => $value)
                                            <label class="mv-swatch">
                                                <input type="radio" name="opt_{{ $loop->parent->index }}" value="{{ $value }}" data-option="{{ $option }}" @checked($j === 0)>
                                                <span>@if ($swatches->has($value))<i style="background:{{ preg_match('/^#[0-9a-f]{3,8}$/i', (string) $swatches[$value]['hex']) ? $swatches[$value]['hex'] : 'transparent' }}"></i>@endif{{ $value }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    @endif
                    <div class="mv-buy">
                        <div class="mv-qty">
                            <button type="button" data-qty="-1" aria-label="{{ $sf->isAr() ? 'تقليل' : 'Decrease' }}">−</button>
                            <input type="number" name="quantity" value="1" min="1" max="99" aria-label="{{ $sf->isAr() ? 'الكمية' : 'Quantity' }}">
                            <button type="button" data-qty="1" aria-label="{{ $sf->isAr() ? 'زيادة' : 'Increase' }}">+</button>
                        </div>
                        <button type="submit" class="mv-btn mv-btn--block" @disabled($soldOut)
                                data-add="{{ $sf->isAr() ? 'أضف إلى السلة' : 'Add to cart' }}" data-sold-out="{{ $sf->isAr() ? 'نفدت الكمية' : 'Sold out' }}" data-unavailable="{{ $sf->isAr() ? 'غير متاح' : 'Unavailable' }}">
                            {{ $soldOut ? ($sf->isAr() ? 'نفدت الكمية' : 'Sold out') : ($sf->isAr() ? 'أضف إلى السلة' : 'Add to cart') }}
                        </button>
                    </div>
                </form>
                @if ($product->isRunningLow())<p class="mv-stock-low">{{ $sf->isAr() ? 'متبقٍ '.$product->stock.' فقط' : 'Only '.$product->stock.' left' }}</p>@endif
                <div class="mv-meta-list">
                    <span>🚚 {{ $sf->isAr() ? 'يصل بين' : 'Arrives' }} {{ $from->translatedFormat('j M') }} – {{ $to->translatedFormat('j M') }}</span>
                    <span>↩︎ {{ $sf->isAr() ? 'استرجاع خلال ١٤ يوماً' : '14-day returns' }}</span>
                    @if ($product->sku)<span>SKU: {{ $product->sku }}</span>@endif
                </div>
                @if ($product->description)<div class="mv-rte" style="margin-top:16px">{!! $sf->rich($sf->field($product, 'description')) !!}</div>@endif
            </div>
        </div>

        @if ($product->approvedReviews->isNotEmpty())
            <section style="padding-bottom:48px">
                <h2 class="mv-h2">{{ $sf->isAr() ? 'التقييمات' : 'Reviews' }} <span class="mv-muted" style="font-size:.6em">★ {{ number_format($product->approvedReviews->avg('rating'), 1) }}</span></h2>
                <div class="mv-grid" style="--cols:3;--cols-m:1">
                    @foreach ($product->approvedReviews->take(6) as $review)
                        @include('storefront.partials.quote', ['q' => ['quote' => $review->body ?? '', 'author' => $review->author_name ?? '', 'rating' => $review->rating]])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section style="padding-bottom:64px">
                <h2 class="mv-h2">{{ $sf->isAr() ? 'قد يعجبك أيضاً' : 'You may also like' }}</h2>
                <div class="mv-grid" style="--cols:4;--cols-m:2">
                    @foreach ($related as $r)@include('storefront.partials.product-card', ['product' => $r])@endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
