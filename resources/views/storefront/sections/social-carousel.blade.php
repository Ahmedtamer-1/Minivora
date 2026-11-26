@php
    $carousel = ($s['layout'] ?? 'grid') === 'carousel';
    $handle = trim((string) ($s['handle'] ?? ''));
    $action = $handle !== '' ? '<a class="mv-btn mv-btn--link" href="'.e($sf->link($s['profile_url'] ?? '#')).'" target="_blank" rel="noopener">'.e($handle).'</a>' : '';
@endphp
<div class="mv-container" @if ($carousel) data-carousel @endif>
    @include('storefront.partials.section-head', ['action' => $action, 'arrows' => $carousel])
    <div class="{{ $carousel ? 'mv-carousel' : 'mv-grid' }}" style="--cols:{{ $sf->num($s['columns'] ?? 4, 1, 6) }};--cols-m:2">
        @forelse ($blocks as $b)
            @php($bs = $b['settings'])
            <a class="mv-post" href="{{ $sf->link($bs['link_url'] ?? ($s['profile_url'] ?? '#')) }}" @if (str_starts_with((string) ($bs['link_url'] ?? ''), 'http')) target="_blank" rel="noopener" @endif>
                @include('storefront.partials.media', ['src' => $sf->media($bs['image'] ?? null), 'alt' => $sf->t($bs, 'caption'), 'ratio' => $s['aspect'] ?? 'square', 'edit' => $sf->ei('image', $b['id'])])
                <span class="mv-post__cap">@if (! empty($bs['handle']))<b>{{ $bs['handle'] }}</b><br>@endif{{ $sf->t($bs, 'caption') }}</span>
            </a>
        @empty
            @for ($i = 0; $i < 4; $i++)@include('storefront.partials.media', ['ratio' => $s['aspect'] ?? 'square'])@endfor
        @endforelse
    </div>
</div>
