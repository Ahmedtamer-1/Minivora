{{-- Image (or empty placeholder) in a ratio frame. Vars: $src, $alt, $ratio, $class, $eager, $src2 --}}
@php($ratio = $ratio ?? 'portrait')
<div class="mv-media mv-ar-{{ $ratio }} {{ $class ?? '' }}"{!! $edit ?? '' !!}>
    @if (! empty($src))
        <img src="{{ $src }}" alt="{{ $alt ?? '' }}" @if (empty($eager)) loading="lazy" @else fetchpriority="high" @endif decoding="async">
        @if (! empty($src2))<img class="mv-card__img2" src="{{ $src2 }}" alt="" loading="lazy" decoding="async">@endif
    @else
        <div class="mv-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>
    @endif
</div>
