{{-- Storefront order confirmation page: order number, status, EGP total, payment instructions, and tracking link. --}}
@extends('storefront.layouts.app')

@php
    $locale = app()->getLocale();
    $isAr = $locale === 'ar';
    $storeName = $store->name ?? config('app.name', 'Minivora');
    $currency = $isAr ? 'ج.م' : 'EGP';

    $orderNumber = data_get($order ?? null, 'number', '');
    $rawStatus = data_get($order ?? null, 'status');
    $statusValue = $rawStatus instanceof \BackedEnum ? $rawStatus->value : (string) ($rawStatus ?? 'pending');
    $statusKey = 'orders.status_'.$statusValue;
    $statusLabel = \Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : ucfirst($statusValue);

    $subtotal = (int) data_get($order ?? null, 'subtotal', 0);
    $shippingFee = (int) data_get($order ?? null, 'shipping_fee', 0);
    $discount = (int) data_get($order ?? null, 'discount', 0);
    $total = (int) data_get($order ?? null, 'total', 0);

    $paymentRecord = $payment ?? data_get($order ?? null, 'payment');
    $instructionsData = is_array($instructions ?? null) ? $instructions : [];
    $paymentMeta = data_get($paymentRecord, 'meta', data_get($paymentRecord, 'payload', []));
    $paymentMeta = is_array($paymentMeta) ? $paymentMeta : [];

    $paymentMethod = strtolower((string) (
        data_get($paymentRecord, 'gateway')
        ?? data_get($paymentRecord, 'provider')
        ?? data_get($order ?? null, 'payment_method')
        ?? 'cod'
    ));

    $paymentUrl = data_get($instructionsData, 'redirect_url')
        ?? data_get($instructionsData, 'payment_url')
        ?? data_get($instructionsData, 'checkout_url')
        ?? data_get($paymentRecord, 'redirect_url')
        ?? data_get($paymentRecord, 'checkout_url')
        ?? data_get($paymentMeta, 'redirect_url')
        ?? data_get($paymentMeta, 'checkout_url')
        ?? data_get($paymentMeta, 'payment_url');

    $paymentReference = data_get($instructionsData, 'reference')
        ?? data_get($instructionsData, 'fawry_reference')
        ?? data_get($paymentRecord, 'reference')
        ?? data_get($paymentRecord, 'external_id')
        ?? data_get($paymentMeta, 'reference')
        ?? data_get($paymentMeta, 'fawry_reference');

    $instapayHandle = data_get($instructionsData, 'instapay_handle')
        ?? data_get($paymentMeta, 'instapay_handle')
        ?? data_get($paymentMeta, 'ipa');

    $customMessage = is_string($instructions ?? null)
        ? $instructions
        : (data_get($instructionsData, 'message') ?? data_get($paymentMeta, 'message'));

    $orderItems = data_get($order ?? null, 'items', []);
    $orderItems = is_iterable($orderItems) ? $orderItems : [];

    $customerName = data_get($order ?? null, 'customer_name');
    $customerPhone = data_get($order ?? null, 'phone');
    $governorate = data_get($order ?? null, 'governorate');
    $address = data_get($order ?? null, 'address');

    $trackUrl = data_get($order ?? null, 'tracking_url')
        ?: ('/track-order'.($orderNumber !== '' ? '?order='.urlencode((string) $orderNumber) : ''));

    $statusBadgeClass = match ($statusValue) {
        'paid', 'delivered' => 'bg-success-subtle text-success',
        'cancelled', 'refunded', 'returned' => 'bg-danger-subtle text-danger',
        'processing', 'shipped' => 'bg-info-subtle text-info',
        default => 'bg-warning-subtle text-warning',
    };

    $paymentLabels = [
        'cod' => $isAr ? 'الدفع عند الاستلام' : 'Cash on Delivery (COD)',
        'kashier' => $isAr ? 'كاشير' : 'Kashier',
        'instapay' => $isAr ? 'إنستا باي' : 'InstaPay',
        'paymob' => $isAr ? 'باي موب' : 'Paymob',
        'fawry' => $isAr ? 'فوري' : 'Fawry',
    ];
    $paymentLabel = $paymentLabels[$paymentMethod] ?? strtoupper($paymentMethod);

    $formatEgp = static fn (int $piasters): string => number_format($piasters / 100, 2).' '.$currency;
@endphp

@section('title', ($isAr ? 'تأكيد الطلب' : 'Order Confirmed').($orderNumber !== '' ? ' #'.$orderNumber : '').' · '.$storeName)
@section('noindex', '1')

@section('content')
    <section class="aurora py-10 sm:py-14">
        <div class="mx-auto max-w-3xl px-4">
            <div class="card p-6 sm:p-8">
                {{-- Confirmation header --}}
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-success">
                            {{ $isAr ? 'تم استلام طلبك بنجاح' : 'Order Confirmed' }}
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold text-ink sm:text-3xl">
                            {{ $isAr ? 'شكرًا لطلبك' : 'Thank you for your order' }}@if ($customerName){{ $isAr ? '، ' : ', ' }}{{ $customerName }}@endif
                        </h1>
                        <p class="mt-2 text-sm text-ink-muted">
                            {{ $isAr ? 'رقم الطلب:' : 'Order number:' }}
                            <span class="font-mono font-semibold text-ink">#{{ $orderNumber }}</span>
                        </p>
                    </div>

                    <div class="text-start sm:text-end">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                            {{ $statusLabel }}
                        </span>
                        <p class="mt-2 text-xs text-ink-muted">{{ $isAr ? 'الإجمالي' : 'Total' }}</p>
                        <p class="font-display text-2xl font-semibold text-ink tabular">
                            {{ $formatEgp($total) }}
                        </p>
                    </div>
                </div>

                {{-- Payment instructions --}}
                <section aria-labelledby="payment-instructions-heading" class="mt-6 rounded-xl border border-line bg-surface p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="payment-instructions-heading" class="font-display text-base font-semibold text-ink">
                            {{ $isAr ? 'تعليمات الدفع' : 'Payment instructions' }}
                        </h2>
                        <span class="text-xs font-medium text-ink-muted">{{ $paymentLabel }}</span>
                    </div>

                    @if ($paymentMethod === 'cod')
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'سيتم تحصيل مبلغ '.$formatEgp($total).' نقدًا عند استلام الطلب من مندوب الشحن.'
                                : 'Please prepare '.$formatEgp($total).' in cash to pay the courier upon delivery.' }}
                        </p>
                    @elseif ($paymentMethod === 'instapay')
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'يرجى تحويل مبلغ '.$formatEgp($total).' عبر إنستا باي وإرسال إيصال التحويل للمتجر. حالة الدفع حاليًا بانتظار تأكيد الإيصال.'
                                : 'Please transfer '.$formatEgp($total).' via InstaPay and share your receipt with the store. Your payment is currently pending receipt verification.' }}
                        </p>
                        @if ($instapayHandle)
                            <p class="mt-2 text-sm text-ink">
                                <span class="text-ink-muted">{{ $isAr ? 'حساب إنستا باي:' : 'InstaPay address:' }}</span>
                                <span class="font-mono font-semibold" dir="ltr">{{ $instapayHandle }}</span>
                            </p>
                        @endif
                        @if ($paymentReference)
                            <p class="mt-1 text-sm text-ink">
                                <span class="text-ink-muted">{{ $isAr ? 'مرجع التحويل:' : 'Reference:' }}</span>
                                <span class="font-mono font-semibold" dir="ltr">{{ $paymentReference }}</span>
                            </p>
                        @endif
                    @elseif ($paymentMethod === 'kashier')
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'لإتمام الدفع الإلكتروني عبر كاشير، استخدم رابط الدفع أدناه.'
                                : 'Use the secure Kashier payment link below to complete your online payment.' }}
                        </p>
                        @if ($paymentReference)
                            <p class="mt-2 text-xs text-ink-muted">
                                {{ $isAr ? 'رقم العملية:' : 'Transaction reference:' }}
                                <span class="font-mono font-semibold text-ink" dir="ltr">{{ $paymentReference }}</span>
                            </p>
                        @endif
                        @if ($paymentUrl)
                            <div class="mt-4">
                                <a
                                    href="{{ $paymentUrl }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg shadow-sm transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-focus"
                                >
                                    {{ $isAr ? 'الدفع الآن عبر كاشير' : 'Pay now with Kashier' }}
                                </a>
                            </div>
                        @endif
                    @elseif ($paymentMethod === 'paymob')
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'لإتمام الدفع عبر باي موب، استخدم رابط الدفع أدناه.'
                                : 'Use the Paymob link below to complete your payment.' }}
                        </p>
                        @if ($paymentReference)
                            <p class="mt-2 text-xs text-ink-muted">
                                {{ $isAr ? 'رقم العملية:' : 'Reference:' }}
                                <span class="font-mono font-semibold text-ink" dir="ltr">{{ $paymentReference }}</span>
                            </p>
                        @endif
                        @if ($paymentUrl)
                            <div class="mt-4">
                                <a
                                    href="{{ $paymentUrl }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg shadow-sm transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-focus"
                                >
                                    {{ $isAr ? 'الدفع الآن عبر باي موب' : 'Pay now with Paymob' }}
                                </a>
                            </div>
                        @endif
                    @elseif ($paymentMethod === 'fawry')
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'استخدم كود فوري أدناه لدفع مبلغ '.$formatEgp($total).' من أقرب منفذ فوري أو تطبيق ماي فوري.'
                                : 'Use the Fawry reference code below to pay '.$formatEgp($total).' at any Fawry outlet or via myFawry.' }}
                        </p>
                        <div class="mt-3 rounded-lg border border-line bg-elevated px-4 py-3">
                            <p class="text-xs text-ink-muted">{{ $isAr ? 'كود الدفع (فوري)' : 'Fawry Reference Number' }}</p>
                            <p class="mt-0.5 font-mono text-lg font-semibold text-ink tabular" dir="ltr">
                                {{ $paymentReference ?: ('FWR-'.$orderNumber) }}
                            </p>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-ink">
                            {{ $isAr
                                ? 'تم تسجيل طريقة الدفع المختارة لطلبك.'
                                : 'Your selected payment method has been recorded for this order.' }}
                        </p>
                    @endif

                    @if ($customMessage)
                        <p class="mt-3 text-xs text-ink-muted">{{ $customMessage }}</p>
                    @endif
                </section>

                {{-- Order items & totals --}}
                @if (! empty($orderItems) && count($orderItems) > 0)
                    <section aria-labelledby="confirmed-items-heading" class="mt-6">
                        <h2 id="confirmed-items-heading" class="font-display text-base font-semibold text-ink">
                            {{ $isAr ? 'تفاصيل الطلب' : 'Order details' }}
                        </h2>
                        <ul class="mt-3 divide-y divide-line border-t border-b border-line">
                            @foreach ($orderItems as $item)
                                @php
                                    $itemName = (string) data_get($item, 'name', '');
                                    $itemVariant = data_get($item, 'variant');
                                    $itemQty = (int) data_get($item, 'quantity', 1);
                                    $itemLineTotal = (int) data_get($item, 'line_total', (int) data_get($item, 'unit_price', 0) * $itemQty);
                                @endphp
                                <li class="flex items-start justify-between gap-4 py-3 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-ink">{{ $itemName }}</p>
                                        <p class="text-xs text-ink-muted">
                                            {{ $isAr ? 'الكمية:' : 'Qty:' }} <span class="tabular">{{ $itemQty }}</span>
                                            @if ($itemVariant)
                                                · {{ $itemVariant }}
                                            @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-medium text-ink tabular">{{ $formatEgp($itemLineTotal) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <dl class="mt-5 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-muted">{{ $isAr ? 'المجموع الفرعي' : 'Subtotal' }}</dt>
                        <dd class="font-medium text-ink tabular">{{ $formatEgp($subtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-muted">{{ $isAr ? 'الشحن' : 'Shipping' }}</dt>
                        <dd class="font-medium text-ink tabular">{{ $formatEgp($shippingFee) }}</dd>
                    </div>
                    @if ($discount > 0)
                        <div class="flex items-center justify-between gap-4 text-success">
                            <dt>{{ $isAr ? 'الخصم' : 'Discount' }}</dt>
                            <dd class="font-medium tabular">-{{ $formatEgp($discount) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3 text-base font-semibold text-ink">
                        <dt>{{ $isAr ? 'الإجمالي' : 'Total' }}</dt>
                        <dd class="tabular">{{ $formatEgp($total) }}</dd>
                    </div>
                </dl>

                @if ($governorate || $address || $customerPhone)
                    <div class="mt-6 border-t border-line pt-5 text-sm">
                        <h2 class="font-display text-base font-semibold text-ink">
                            {{ $isAr ? 'بيانات التوصيل' : 'Delivery details' }}
                        </h2>
                        <div class="mt-2 space-y-1 text-ink-muted">
                            @if ($customerName)
                                <p class="text-ink">{{ $customerName }}</p>
                            @endif
                            @if ($governorate || $address)
                                <p>{{ trim(implode(' — ', array_filter([$governorate, $address]))) }}</p>
                            @endif
                            @if ($customerPhone)
                                <p dir="ltr" class="inline-block">{{ $customerPhone }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Actions: track order + continue shopping --}}
                <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-line pt-6">
                    <a
                        href="{{ $trackUrl }}"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-fg shadow-sm transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-focus"
                    >
                        {{ $isAr ? 'تتبع طلبك' : 'Track your order' }}
                    </a>
                    <a
                        href="/{{ $locale }}"
                        class="inline-flex items-center justify-center rounded-lg border border-line bg-elevated px-5 py-2.5 text-sm font-medium text-ink transition hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line-focus"
                    >
                        {{ $isAr ? 'العودة إلى المتجر' : 'Back to store' }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
