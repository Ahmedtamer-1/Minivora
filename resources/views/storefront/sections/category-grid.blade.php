@php
    $categories = $catalog->categories((array) ($s['category_ids'] ?? []));
    $overlay = ($s['card_style'] ?? 'overlay') === 'overlay';
@endphp
<div class="mv-container">
    @include('storefront.partials.section-head')
    <div class="mv-grid" style="--cols:{{ $sf->num($s['columns'] ?? 4, 1, 6) }};--cols-m:{{ $sf->num($s['mobile_columns'] ?? 2, 1, 2) }}">
        @forelse ($categories as $i => $category)
            @php($img = \App\Modules\Builder\Storefront\StorefrontCatalog::categoryImage($category))
            <a class="{{ $overlay ? 'mv-card-overlay' : 'mv-card-plain' }}" href="{{ $sf->url('/categories/'.$category->slug) }}">
                @include('storefront.partials.media', ['src' => $img, 'alt' => $sf->field($category, 'name'), 'ratio' => $s['aspect'] ?? 'portrait'])
                <div class="{{ $overlay ? 'mv-card-overlay__text' : '' }}">
                    @if (! empty($s['numbered']))<span class="mv-num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>@endif
                    <h3 class="mv-h3">{{ $sf->field($category, 'name') }}</h3>
                    <span class="mv-muted" style="font-size:13px;{{ $overlay ? 'color:rgba(255,255,255,.8)' : '' }}">{{ $category->products_count }} {{ $sf->isAr() ? 'منتج' : 'products' }}</span>
                </div>
            </a>
        @empty
            @for ($i = 0; $i < 4; $i++)
                <div class="mv-card-plain">@include('storefront.partials.media', ['ratio' => $s['aspect'] ?? 'portrait'])<h3 class="mv-h3">{{ $sf->isAr() ? 'فئة' : 'Category' }} {{ $i + 1 }}</h3></div>
            @endfor
        @endforelse
    </div>
</div>
