{{-- Single-page guest storefront checkout: <= 8 shopper inputs (NFR-USE-03), RTL/LTR & keyboard accessible (NFR-USE-04, FR-PAY-01). --}}
@extends('storefront.layouts.app')

@php
    $locale = app()->getLocale();
    $isAr = $locale === 'ar';
    $storeName = $store->name ?? config('app.name', 'Minivora');
    $currency = $isAr ? 'ج.م' : 'EGP';

    $summary = $summary ?? [];
    $items = is_iterable($summary['items'] ?? null) ? $summary['items'] : [];
    $subtotal = (int) ($summary['subtotal'] ?? 0);
    $shippingEstimate = (int) ($summary['shipping_estimate'] ?? 0);
    $discountAmount = (int) ($summary['discount'] ?? 0);
    $total = (int) ($summary['total'] ?? max(0, $subtotal + $shippingEstimate - $discountAmount));
    $freeShippingRemaining = $summary['free_shipping_remaining'] ?? null;
    $notices = is_iterable($summary['notices'] ?? null) ? $summary['notices'] : [];

    $selectedGovernorate = old('governorate', $selectedGovernorate ?? '');
    $selectedPaymentMethod = old('payment_method', 'cod');

    $paymentOptions = [
        'cod' => $isAr ? 'الدفع عند الاستلام (كاش)' : 'Cash on Delivery (COD)',
        'kashier' => $isAr ? 'كاشير (بطاقة بنكية / محفظة إلكترونية)' : 'Kashier (Card / Mobile Wallet)',
        'instapay' => $isAr ? 'إنستا باي (تحويل فوري)' : 'InstaPay (Instant Bank Transfer)',
        'paymob' => $isAr ? 'باي موب (بطاقة بنكية / محفظة)' : 'Paymob (Card / Wallet)',
        'fawry' => $isAr ? 'فوري (كود دفع)' : 'Fawry (Reference Code)',
    ];

    $formatEgp = static fn (int $piasters): string => number_format($piasters / 100, 2).' '.$currency;
@endphp

@section('title', ($isAr ? 'إتمام الطلب' : 'Checkout').' · '.$storeName)
@section('noindex', '1')

@section('content')
    <section class="aurora py-8 sm:py-12">
        <div class="mx-auto max-w-6xl px-4">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-ink-muted">
                        {{ $isAr ? 'طلب سريع بدون حساب' : 'Guest Checkout' }}
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">
                        {{ $isAr ? 'إتمام الطلب' : 'Complete your order' }}
                    </h1>
                </div>
                <a href="{{ \App\Core\Tenancy\StoreUrl::prefix($store->subdomain) }}/{{ $locale }}" class="text-sm font-medium text-ink-link hover:underline">
                    {{ $isAr ? 'العودة للتسوق' : 'Continue shopping' }}
                </a>
            </div>

            @if (session('error'))
                <div role="alert" class="mb-6 rounded-xl border border-danger bg-danger-subtle px-4 py-3 text-sm text-danger">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-danger bg-danger-subtle px-4 py-3 text-sm text-danger">
                    <p class="font-semibold">
                        {{ $isAr ? 'يرجى مراجعة الحقول التالية:' : 'Please check the highlighted fields:' }}
                    </p>
                    <ul class="mt-1 list-disc space-y-0.5 ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($notices))
                <div role="status" class="mb-6 rounded-xl border border-warning bg-warning-subtle px-4 py-3 text-sm text-ink">
                    <p class="font-semibold">
                        {{ $isAr ? 'تم تحديث سلة التسوق:' : 'Cart updates:' }}
                    </p>
                    <ul class="mt-1 list-disc space-y-0.5 ps-5">
                        @foreach ($notices as $notice)
                            <li>{{ is_array($notice) ? ($notice['message'] ?? '') : $notice }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                {{-- Checkout form (7 visible controls, strictly <= 8 per NFR-USE-03) --}}
                <div class="card p-6 sm:p-8 lg:col-span-7">
                    <form method="POST" action="{{ \App\Core\Tenancy\StoreUrl::prefix($store->subdomain) }}/checkout" class="space-y-6" novalidate>
                        @csrf
                        <input type="hidden" name="locale" value="{{ $locale }}">

                        <fieldset class="space-y-4">
                            <legend class="font-display text-lg font-semibold text-ink">
                                {{ $isAr ? 'بيانات التواصل والتوصيل' : 'Contact & Delivery' }}
                            </legend>

                            {{-- 1. Full name --}}
                            <div>
                                <label for="checkout-name" class="block text-sm font-medium text-ink">
                                    {{ $isAr ? 'الاسم بالكامل' : 'Full name' }}
                                    <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout-name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    autocomplete="name"
                                    placeholder="{{ $isAr ? 'مثال: أحمد محمد' : 'e.g. Ahmed Mohamed' }}"
                                    aria-required="true"
                                    @error('name') aria-invalid="true" aria-describedby="checkout-name-error" @enderror
                                    class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink placeholder:text-ink-muted focus:border-line-focus focus:ring-line-focus @error('name') border-danger @enderror"
                                >
                                @error('name')
                                    <p id="checkout-name-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- 2. Phone --}}
                                <div>
                                    <label for="checkout-phone" class="block text-sm font-medium text-ink">
                                        {{ $isAr ? 'رقم الموبايل' : 'Mobile phone' }}
                                        <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        id="checkout-phone"
                                        name="phone"
                                        value="{{ old('phone') }}"
                                        required
                                        placeholder="01012345678"
                                        autocomplete="tel"
                                        dir="ltr"
                                        aria-required="true"
                                        aria-describedby="checkout-phone-hint @error('phone') checkout-phone-error @enderror"
                                        @error('phone') aria-invalid="true" @enderror
                                        class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink text-start placeholder:text-ink-muted focus:border-line-focus focus:ring-line-focus @error('phone') border-danger @enderror"
                                    >
                                    <p id="checkout-phone-hint" class="mt-1 text-xs text-ink-muted">
                                        {{ $isAr ? 'للتأكيد ومتابعة الشحن (مثال: 01012345678)' : 'Used for delivery updates (e.g. 01012345678)' }}
                                    </p>
                                    @error('phone')
                                        <p id="checkout-phone-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- 3. Email (optional) --}}
                                <div>
                                    <label for="checkout-email" class="block text-sm font-medium text-ink">
                                        {{ $isAr ? 'البريد الإلكتروني (اختياري)' : 'Email (optional)' }}
                                    </label>
                                    <input
                                        type="email"
                                        id="checkout-email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        autocomplete="email"
                                        dir="ltr"
                                        placeholder="you@example.com"
                                        aria-describedby="checkout-email-hint @error('email') checkout-email-error @enderror"
                                        @error('email') aria-invalid="true" @enderror
                                        class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink text-start placeholder:text-ink-muted focus:border-line-focus focus:ring-line-focus @error('email') border-danger @enderror"
                                    >
                                    <p id="checkout-email-hint" class="mt-1 text-xs text-ink-muted">
                                        {{ $isAr ? 'لإرسال نسخة من تأكيد الطلب' : 'For your order receipt' }}
                                    </p>
                                    @error('email')
                                        <p id="checkout-email-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- 4. Governorate --}}
                            <div>
                                <label for="checkout-governorate" class="block text-sm font-medium text-ink">
                                    {{ $isAr ? 'المحافظة' : 'Governorate' }}
                                    <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <select
                                    id="checkout-governorate"
                                    name="governorate"
                                    required
                                    aria-required="true"
                                    @error('governorate') aria-invalid="true" aria-describedby="checkout-governorate-error" @enderror
                                    class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink focus:border-line-focus focus:ring-line-focus @error('governorate') border-danger @enderror"
                                >
                                    <option value="">{{ $isAr ? 'اختر المحافظة' : 'Select governorate' }}</option>
                                    {{-- Every governorate: the store's own zone rate where set, otherwise the default rate. --}}
                                    @foreach ((is_array($shippingRates ?? null) ? $shippingRates : []) as $gov => $rate)
                                        <option value="{{ $gov }}" @selected($selectedGovernorate === $gov)>
                                            {{ \Illuminate\Support\Facades\Lang::has('gov.'.$gov) ? __('gov.'.$gov) : ucwords(str_replace('_', ' ', $gov)) }} — {{ $formatEgp((int) ($rate['rate'] ?? 0)) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('governorate')
                                    <p id="checkout-governorate-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- 5. Street address --}}
                            <div>
                                <label for="checkout-address" class="block text-sm font-medium text-ink">
                                    {{ $isAr ? 'العنوان بالتفصيل' : 'Delivery address' }}
                                    <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="checkout-address"
                                    name="address"
                                    required
                                    rows="2"
                                    autocomplete="street-address"
                                    placeholder="{{ $isAr ? 'اسم الشارع، رقم العمارة، الدور، الشقة، وأقرب علامة مميزة' : 'Street, building, floor, apartment, landmark' }}"
                                    aria-required="true"
                                    @error('address') aria-invalid="true" aria-describedby="checkout-address-error" @enderror
                                    class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink placeholder:text-ink-muted focus:border-line-focus focus:ring-line-focus @error('address') border-danger @enderror"
                                >{{ old('address') }}</textarea>
                                @error('address')
                                    <p id="checkout-address-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </fieldset>

                        <hr class="border-line">

                        <fieldset class="space-y-4">
                            <legend class="font-display text-lg font-semibold text-ink">
                                {{ $isAr ? 'الدفع وكود الخصم' : 'Payment & Discount' }}
                            </legend>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- 6. Payment method --}}
                                <div>
                                    <label for="checkout-payment-method" class="block text-sm font-medium text-ink">
                                        {{ $isAr ? 'طريقة الدفع' : 'Payment method' }}
                                        <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <select
                                        id="checkout-payment-method"
                                        name="payment_method"
                                        required
                                        aria-required="true"
                                        @error('payment_method') aria-invalid="true" aria-describedby="checkout-payment-method-error" @enderror
                                        class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink focus:border-line-focus focus:ring-line-focus @error('payment_method') border-danger @enderror"
                                    >
                                        @foreach ($paymentOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($selectedPaymentMethod === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_method')
                                        <p id="checkout-payment-method-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- 7. Discount code (optional) --}}
                                <div>
                                    <label for="checkout-discount" class="block text-sm font-medium text-ink">
                                        {{ $isAr ? 'كود الخصم (اختياري)' : 'Discount code (optional)' }}
                                    </label>
                                    <input
                                        type="text"
                                        id="checkout-discount"
                                        name="discount_code"
                                        value="{{ old('discount_code') }}"
                                        autocomplete="off"
                                        placeholder="{{ $isAr ? 'أدخل كود الخصم' : 'Enter promo code' }}"
                                        aria-describedby="checkout-discount-hint @error('discount_code') checkout-discount-error @enderror"
                                        @error('discount_code') aria-invalid="true" @enderror
                                        class="mt-1.5 block w-full rounded-md border-line bg-elevated text-ink uppercase placeholder:normal-case placeholder:text-ink-muted focus:border-line-focus focus:ring-line-focus @error('discount_code') border-danger @enderror"
                                    >
                                    <p id="checkout-discount-hint" class="mt-1 text-xs text-ink-muted">
                                        {{ $isAr ? 'يُطبق تلقائيًا عند تأكيد الطلب' : 'Applied automatically when you place your order' }}
                                    </p>
                                    @error('discount_code')
                                        <p id="checkout-discount-error" role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </fieldset>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full rounded-lg bg-primary px-6 py-3 text-base font-semibold text-primary-fg shadow-sm transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-focus focus-visible:ring-offset-2"
                            >
                                {{ $isAr ? 'تأكيد الطلب' : 'Place order' }}
                                @if ($total > 0)
                                    · <span class="tabular">{{ $formatEgp($total) }}</span>
                                @endif
                            </button>
                            <p class="mt-2 text-center text-xs text-ink-muted">
                                {{ $isAr ? 'لا يتطلب إنشاء حساب. سيتم تزويدك برقم الطلب لمتابعته فورًا.' : 'No account required. You will receive an order number to track your delivery.' }}
                            </p>
                        </div>
                    </form>
                </div>

                {{-- Order summary sidebar --}}
                <aside aria-labelledby="order-summary-heading" class="card p-6 sm:p-8 lg:col-span-5 lg:sticky lg:top-6">
                    <h2 id="order-summary-heading" class="font-display text-lg font-semibold text-ink">
                        {{ $isAr ? 'ملخص الطلب' : 'Order summary' }}
                    </h2>

                    @if (empty($items))
                        <div class="mt-4 rounded-lg border border-line bg-surface p-4 text-sm text-ink-muted">
                            <p>{{ $isAr ? 'سلة التسوق فارغة حاليًا.' : 'Your cart is currently empty.' }}</p>
                            <a href="{{ \App\Core\Tenancy\StoreUrl::prefix($store->subdomain) }}/{{ $locale }}" class="mt-2 inline-block font-medium text-ink-link hover:underline">
                                {{ $isAr ? 'تصفح المنتجات' : 'Browse products' }}
                            </a>
                        </div>
                    @else
                        <ul class="mt-4 divide-y divide-line">
                            @foreach ($items as $item)
                                @php
                                    $itemName = ($isAr && ! empty($item['name_ar']))
                                        ? $item['name_ar']
                                        : ($item['name'] ?? '');
                                    $itemQty = (int) ($item['quantity'] ?? 1);
                                    $itemLineTotal = (int) ($item['line_total'] ?? ((int) ($item['unit_price'] ?? 0) * $itemQty));
                                @endphp
                                <li class="flex items-start justify-between gap-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $itemName }}</p>
                                        <p class="mt-0.5 text-xs text-ink-muted">
                                            {{ $isAr ? 'الكمية:' : 'Qty:' }} <span class="tabular">{{ $itemQty }}</span>
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-sm font-medium text-ink tabular">
                                        {{ $formatEgp($itemLineTotal) }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($freeShippingRemaining !== null && (int) $freeShippingRemaining > 0)
                        <p class="mt-4 rounded-lg bg-accent-subtle px-3 py-2 text-xs text-ink">
                            {{ $isAr ? 'أضف منتجات بقيمة' : 'Add' }}
                            <span class="font-semibold tabular">{{ $formatEgp((int) $freeShippingRemaining) }}</span>
                            {{ $isAr ? 'للحصول على شحن مجاني.' : 'more for free shipping.' }}
                        </p>
                    @endif

                    <dl class="mt-5 space-y-2.5 border-t border-line pt-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-muted">{{ $isAr ? 'المجموع الفرعي' : 'Subtotal' }}</dt>
                            <dd class="font-medium text-ink tabular">{{ $formatEgp($subtotal) }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-muted">{{ $isAr ? 'تكلفة الشحن التقديرية' : 'Estimated shipping' }}</dt>
                            <dd class="font-medium text-ink tabular">
                                @if ($shippingEstimate > 0)
                                    {{ $formatEgp($shippingEstimate) }}
                                @else
                                    {{ $isAr ? 'يُحدد حسب المحافظة' : 'Calculated by governorate' }}
                                @endif
                            </dd>
                        </div>

                        @if ($discountAmount > 0)
                            <div class="flex items-center justify-between gap-4 text-success">
                                <dt>{{ $isAr ? 'الخصم' : 'Discount' }}</dt>
                                <dd class="font-medium tabular">-{{ $formatEgp($discountAmount) }}</dd>
                            </div>
                        @endif

                        <div class="flex items-center justify-between gap-4 border-t border-line pt-3 text-base font-semibold text-ink">
                            <dt>{{ $isAr ? 'الإجمالي' : 'Total' }}</dt>
                            <dd class="tabular">{{ $formatEgp($total) }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </div>
    </section>
@endsection
