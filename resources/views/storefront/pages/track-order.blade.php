{{-- Track an order by number + phone or email. FR-ORD-05, FR-ORD-07 --}}
@extends('storefront.layouts.store')
@section('title', ($sf->isAr() ? 'تتبع الطلب' : 'Track your order').' · '.$sf->store->name)
@section('noindex', '1')
@section('content')
    <section class="mv-section mv-w-narrow">
        <div class="mv-container">
            <h1 class="mv-h2">{{ $sf->isAr() ? 'تتبع طلبك' : 'Track your order' }}</h1>
            <p class="mv-muted">{{ $sf->isAr() ? 'أدخل رقم الطلب ورقم الموبايل أو البريد المستخدم عند الطلب.' : 'Enter your order number and the phone or email you ordered with.' }}</p>
            <form id="mv-track" action="{{ $sf->raw('/track-order') }}" method="post" style="display:grid;gap:12px;margin-top:24px">
                <label class="mv-label" for="t-number">{{ $sf->isAr() ? 'رقم الطلب' : 'Order number' }}</label>
                <input class="mv-input" id="t-number" name="order_number" required inputmode="numeric" value="{{ $number }}">
                <label class="mv-label" for="t-contact">{{ $sf->isAr() ? 'الموبايل أو البريد' : 'Phone or email' }}</label>
                <input class="mv-input" id="t-contact" name="contact" required autocomplete="tel">
                <button class="mv-btn" type="submit">{{ $sf->isAr() ? 'تتبع' : 'Track' }}</button>
            </form>
            <div id="mv-track-result" aria-live="polite" style="margin-top:32px"></div>
        </div>
    </section>
    <script>
        (function () {
            var form = document.getElementById('mv-track'), out = document.getElementById('mv-track-result');
            var ar = {{ $sf->isAr() ? 'true' : 'false' }};
            var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
            var money = function (p) { return (p / 100).toLocaleString('en-US', { minimumFractionDigits: 2 }) + (ar ? ' ج.م' : ' EGP'); };
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                out.textContent = ar ? 'جارٍ البحث…' : 'Looking it up…';
                fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ order_number: form.order_number.value, contact: form.contact.value }) })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok) { out.innerHTML = '<p class="mv-muted">' + (ar ? 'لم نجد طلباً بهذه البيانات.' : 'We could not find an order with those details.') + '</p>'; return; }
                        var o = res.j.order;
                        out.innerHTML = '<div class="mv-summary"><div class="mv-summary__row"><b>#' + esc(o.number) + '</b><span class="mv-pill">' + esc(o.status) + '</span></div>'
                            + o.items.map(function (i) { return '<div class="mv-summary__row"><span>' + esc(i.quantity) + ' × ' + esc(i.name) + '</span><span>' + money(i.line_total) + '</span></div>'; }).join('')
                            + '<div class="mv-summary__row mv-summary__total"><span>' + (ar ? 'الإجمالي' : 'Total') + '</span><span>' + money(o.total) + '</span></div>'
                            + (o.courier_name ? '<p class="mv-muted" style="margin:0">' + esc(o.courier_name) + '</p>' : '')
                            + (o.tracking_url && /^https?:/.test(o.tracking_url) ? '<a class="mv-btn mv-btn--outline" target="_blank" rel="noopener" href="' + esc(o.tracking_url) + '">' + (ar ? 'تتبع الشحنة' : 'Track shipment') + '</a>' : '')
                            + '</div>';
                    })
                    .catch(function () { out.textContent = ar ? 'حدث خطأ، حاول مرة أخرى.' : 'Something went wrong, please try again.'; });
            });
        })();
    </script>
@endsection
