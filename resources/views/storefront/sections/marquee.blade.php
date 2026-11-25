@php
    $sep = trim((string) ($s['separator'] ?? ''));
    $items = $blocks ?: [['settings' => ['text' => $sf->store->name]]];
    $group = '';
    foreach ($items as $b) {
        $img = $sf->media($b['settings']['image'] ?? null);
        $group .= '<span class="mv-marquee__item">'.($img ? '<img src="'.e($img).'" alt="">' : '').e($sf->t($b['settings'], 'text')).'</span>';
        if ($sep !== '') { $group .= '<span class="mv-marquee__sep" aria-hidden="true">'.e($sep).'</span>'; }
    }
    $label = implode(' · ', array_map(fn ($b) => $sf->t($b['settings'], 'text'), $items));
@endphp
<div class="mv-marquee {{ ! empty($s['uppercase']) ? 'is-upper' : '' }} {{ ! empty($s['outline']) ? 'is-outline' : '' }} {{ ($s['direction'] ?? '') === 'reverse' ? 'is-reverse' : '' }} {{ ! empty($s['pause_on_hover']) ? 'is-pausable' : '' }}"
     style="--speed:{{ $sf->num($s['speed'] ?? 30, 5, 120) }}s;--size:{{ $sf->num($s['font_size'] ?? 32, 12, 200) }}px" role="marquee" aria-label="{{ $label }}">
    <div class="mv-marquee__track" aria-hidden="true">
        @for ($r = 0; $r < 2; $r++)
            <div class="mv-marquee__group">{!! str_repeat($group, 4) !!}</div>
        @endfor
    </div>
</div>
