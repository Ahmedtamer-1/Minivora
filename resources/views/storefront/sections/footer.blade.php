@php
    $logo = $sf->media($s['logo'] ?? null);
    $copyright = $sf->t($s, 'copyright');
    $icons = [
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".5" fill="currentColor"/>',
        'tiktok' => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.5 2.5 2.5 4.5 5 5"/>',
        'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8Z"/>',
        'x' => '<path d="M4 4l16 16M20 4 4 20"/>',
        'youtube' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
        'whatsapp' => '<path d="M4 20l1.5-4A8 8 0 1 1 8 18.5L4 20Z"/>',
    ];
@endphp
<div class="mv-container">
    <div class="mv-footer" style="--cols:{{ max(1, min(4, count($blocks))) }}">
        <div>
            <a class="mv-logo" href="{{ $sf->url('/') }}" style="margin-bottom:12px">@if ($logo)<img src="{{ $logo }}" alt="{{ $sf->store->name }}" style="width:120px">@else{{ $sf->store->name }}@endif</a>
            @if (($t = $sf->t($s, 'tagline')) !== '')<p class="mv-muted" style="max-width:36ch"{!! $sf->e('tagline') !!}>{{ $t }}</p>@endif
        </div>
        @foreach ($blocks as $b)
            @php($bs = $b['settings'])
            <div{!! $sf->eb($b['id']) !!}>
                @if (($h = $sf->t($bs, 'heading')) !== '')<h2{!! $sf->e('heading', $b['id']) !!}>{{ $h }}</h2>@endif
                @switch($b['type'])
                    @case('link_list')
                        <ul>@foreach ($sf->links($sf->t($bs, 'links')) as [$label, $url])<li><a href="{{ $url }}">{{ $label }}</a></li>@endforeach</ul>
                        @break
                    @case('text')
                        <div class="mv-rte mv-muted">{!! $sf->rich($sf->t($bs, 'text')) !!}</div>
                        @break
                    @case('newsletter')
                        @if (($t = $sf->t($bs, 'text')) !== '')<p class="mv-muted">{{ $t }}</p>@endif
                        <form class="mv-inline-form" action="{{ $sf->raw('/newsletter/subscribe') }}" method="post" data-newsletter>
                            <input class="mv-input" type="email" name="email" required aria-label="Email" placeholder="{{ $sf->isAr() ? 'بريدك الإلكتروني' : 'Email' }}">
                            <button class="mv-btn" type="submit" aria-label="{{ $sf->isAr() ? 'اشترك' : 'Subscribe' }}">→</button>
                        </form>
                        @break
                    @case('social')
                        <div class="mv-social">
                            @foreach ($icons as $key => $svg)
                                @if (! empty($bs[$key]))<a href="{{ $sf->link($bs[$key]) }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($key) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">{!! $svg !!}</svg></a>@endif
                            @endforeach
                        </div>
                        @break
                @endswitch
            </div>
        @endforeach
    </div>
    <div class="mv-footer__bottom">
        <span>© {{ now()->year }} {{ $copyright !== '' ? $copyright : $sf->store->name }} · {{ $sf->isAr() ? 'مدعوم من Minivora' : 'Powered by Minivora' }}</span>
        @if (! empty($s['show_payment_icons']))<div class="mv-pay" aria-label="{{ $sf->isAr() ? 'طرق الدفع' : 'Payment methods' }}"><span>Visa</span><span>Mastercard</span><span>Meeza</span><span>InstaPay</span><span>Vodafone Cash</span><span>{{ $sf->isAr() ? 'الدفع عند الاستلام' : 'Cash on delivery' }}</span></div>@endif
        <span style="display:flex;gap:16px">
            @if (! empty($s['show_language']))<a href="{{ $sf->switchUrl() }}" style="color:inherit">{{ $sf->isAr() ? 'English' : 'العربية' }}</a>@endif
            @if (! empty($s['show_back_to_top']))<a href="#main" style="color:inherit">{{ $sf->isAr() ? 'للأعلى ↑' : 'Back to top ↑' }}</a>@endif
        </span>
    </div>
</div>
