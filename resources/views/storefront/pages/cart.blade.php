{{-- Cart page. FR-CRT-01..05 --}}
@extends('storefront.layouts.store')
@section('title', ($sf->isAr() ? 'السلة' : 'Cart').' · '.$sf->store->name)
@section('noindex', '1')
@section('content')
    <div class="mv-container">
        <div class="mv-page-head"><h1 class="mv-h2" style="margin:0">{{ $sf->isAr() ? 'سلة التسوق' : 'Your cart' }}</h1></div>
        @if (empty($summary['items']))
            <div class="mv-empty">
                <p>{{ $sf->isAr() ? 'سلتك فارغة.' : 'Your cart is empty.' }}</p>
                <a class="mv-btn" href="{{ $sf->url('/shop') }}">{{ $sf->isAr() ? 'تسوق الآن' : 'Start shopping' }}</a>
            </div>
        @else
            <div class="mv-cart">
                <div>
                    @foreach ($summary['items'] as $item)
                        <div class="mv-line" data-cart-line data-url="{{ $sf->raw('/cart/items/'.$item['id']) }}" data-qty="{{ $item['quantity'] }}">
                            @include('storefront.partials.media', ['src' => $item['cover'] ?? null, 'ratio' => 'portrait'])
                            <div>
                                <p style="margin:0;font-weight:600">{{ $sf->isAr() && ! empty($item['name_ar']) ? $item['name_ar'] : $item['name'] }}</p>
                                @if (! empty($item['variant']))<p class="mv-muted" style="margin:2px 0 0;font-size:13px">{{ $item['variant'] }}</p>@endif
                                <p class="mv-muted" style="margin:4px 0 10px;font-size:14px">{{ $sf->money((int) $item['unit_price']) }}</p>
                                <div class="mv-qty">
                                    <button type="button" data-line-qty="-1" aria-label="{{ $sf->isAr() ? 'تقليل' : 'Decrease' }}">−</button>
                                    <input type="number" value="{{ $item['quantity'] }}" readonly aria-label="{{ $sf->isAr() ? 'الكمية' : 'Quantity' }}">
                                    <button type="button" data-line-qty="1" aria-label="{{ $sf->isAr() ? 'زيادة' : 'Increase' }}">+</button>
                                </div>
                            </div>
                            <div style="text-align:end">
                                <b>{{ $sf->money((int) $item['line_total']) }}</b><br>
                                <button type="button" class="mv-btn mv-btn--link" data-line-remove style="font-size:13px;margin-top:8px">{{ $sf->isAr() ? 'إزالة' : 'Remove' }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <aside class="mv-summary">
                    <div class="mv-summary__row"><span>{{ $sf->isAr() ? 'المجموع الفرعي' : 'Subtotal' }}</span><b>{{ $sf->money((int) $summary['subtotal']) }}</b></div>
                    @if (! empty($summary['free_shipping_remaining']))<p class="mv-muted" style="margin:0;font-size:13px">{{ $sf->isAr() ? 'أضف '.$sf->money((int) $summary['free_shipping_remaining']).' للشحن المجاني' : 'Add '.$sf->money((int) $summary['free_shipping_remaining']).' for free delivery' }}</p>@endif
                    <div class="mv-summary__row mv-summary__total"><span>{{ $sf->isAr() ? 'الإجمالي' : 'Total' }}</span><span>{{ $sf->money((int) $summary['total']) }}</span></div>
                    <a class="mv-btn mv-btn--block" href="{{ $sf->url('/checkout') }}">{{ $sf->isAr() ? 'إتمام الطلب' : 'Checkout' }}</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
