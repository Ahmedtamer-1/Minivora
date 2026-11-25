<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-grid" style="--cols:{{ $sf->num($s['columns'] ?? 3, 1, 4) }};--cols-m:1">
        @forelse ($blocks as $b)
            @include('storefront.partials.quote', ['q' => $b['settings']])
        @empty
            @for ($i = 0; $i < 3; $i++)@include('storefront.partials.quote', ['q' => ['quote' => $sf->isAr() ? 'أضف آراء عملائك من المحرر.' : 'Add customer quotes in the editor.', 'author' => $sf->isAr() ? 'عميل' : 'Customer', 'rating' => 5]])@endfor
        @endforelse
    </div>
</div>
