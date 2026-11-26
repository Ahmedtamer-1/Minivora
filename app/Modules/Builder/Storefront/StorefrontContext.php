<?php

namespace App\Modules\Builder\Storefront;

use App\Core\Money\MoneyFormatter;
use App\Core\Tenancy\StoreUrl;
use App\Modules\Builder\Models\Theme;
use App\Modules\Builder\Services\HtmlSanitizer;
use App\Modules\Platform\Models\Store;

/**
 * Everything a storefront view needs to know about the request: store, language, theme, whether this
 * is the editor's draft preview, and helpers for links, translated settings, images and money.
 * Shared with every storefront view as `$sf`.
 */
final class StorefrontContext
{
    public function __construct(
        public readonly Store $store,
        public readonly string $locale,
        public readonly Theme $theme,
        public readonly bool $preview = false,
        public readonly string $template = 'page',
    ) {}

    public function isAr(): bool
    {
        return $this->locale === 'ar';
    }

    public function dir(): string
    {
        return $this->isAr() ? 'rtl' : 'ltr';
    }

    /** Store-relative page path ("/shop", "/products/x") → URL with the store prefix and language. */
    public function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $first = explode('/', ltrim($path, '/'))[0];

        if (! in_array($first, config('minivora.locales'), true)) {
            $path = '/'.$this->locale.($path === '/' ? '' : $path);
        }

        return StoreUrl::prefix($this->store->subdomain).$path;
    }

    /** Store-relative endpoint without a language ("/cart/items", "/checkout", "/newsletter/subscribe"). */
    public function raw(string $path): string
    {
        return StoreUrl::prefix($this->store->subdomain).'/'.ltrim($path, '/');
    }

    /** Owner-entered link: internal paths go through url(); unsafe schemes become "#". */
    public function link(mixed $value, string $fallback = '#'): string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return $fallback;
        }
        if (str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $this->url($url);
        }

        return HtmlSanitizer::safeUrl($url) ? $url : '#';
    }

    /** Same page in the other language. */
    public function switchUrl(): string
    {
        $other = $this->isAr() ? 'en' : 'ar';
        $prefix = StoreUrl::prefix($this->store->subdomain);
        $path = '/'.ltrim(request()->path(), '/');
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix)) ?: '/';
        }
        $parts = explode('/', ltrim($path, '/'));
        if (in_array($parts[0] ?? '', config('minivora.locales'), true)) {
            array_shift($parts);
        }

        return $prefix.'/'.$other.(count($parts) && $parts[0] !== '' ? '/'.implode('/', $parts) : '');
    }

    /**
     * Translated setting: `{key}_ar` in Arabic when filled, otherwise `{key}`.
     *
     * @param  array<string, mixed>  $settings
     */
    public function t(array $settings, string $key, string $fallback = ''): string
    {
        if ($this->isAr()) {
            $ar = trim((string) ($settings[$key.'_ar'] ?? ''));
            if ($ar !== '') {
                return $ar;
            }
        }

        $value = $settings[$key] ?? $fallback;

        return is_scalar($value) ? (string) $value : $fallback;
    }

    /** Bilingual model field, e.g. name / name_ar. */
    public function field(object $model, string $key): string
    {
        if ($this->isAr()) {
            $ar = (string) ($model->{$key.'_ar'} ?? '');
            if ($ar !== '') {
                return $ar;
            }
        }

        return (string) ($model->{$key} ?? '');
    }

    /** Image or video URL from a settings value; null when empty or unsafe. */
    public function media(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? null;
        }
        $url = trim((string) $value);
        if ($url === '' || ! HtmlSanitizer::safeUrl($url, true)) {
            return null;
        }

        return $url;
    }

    public function money(int $piasters): string
    {
        return MoneyFormatter::format($piasters, $this->locale);
    }

    /** "Label | /url" per line → list of [label, url]. */
    public function links(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $out[] = [$label, $this->link($url !== '' ? $url : '#')];
        }

        return $out;
    }

    /** YouTube / Vimeo page URL → embed URL; null for direct video files. */
    public function embedUrl(?string $url, bool $autoplay = false): ?string
    {
        $url = (string) $url;
        $auto = $autoplay ? '1' : '0';
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay='.$auto.'&mute='.$auto.'&loop='.$auto.'&playlist='.$m[1].'&controls='.($autoplay ? '0' : '1').'&playsinline=1&rel=0';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1].'?autoplay='.$auto.'&muted='.$auto.'&loop='.$auto.'&background='.$auto;
        }

        return null;
    }

    public function rich(mixed $html): string
    {
        return HtmlSanitizer::rich(is_scalar($html) ? (string) $html : '');
    }

    /** Content position name ("bottom-start") → flex alignment CSS variables. */
    public function position(string $position): string
    {
        [$v, $h] = match ($position) {
            'top-start' => ['flex-start', 'flex-start'],
            'top' => ['flex-start', 'center'],
            'start' => ['center', 'flex-start'],
            'end' => ['center', 'flex-end'],
            'bottom-start' => ['flex-end', 'flex-start'],
            'bottom' => ['flex-end', 'center'],
            'bottom-end' => ['flex-end', 'flex-end'],
            default => ['center', 'center'],
        };

        return '--ai:'.$v.';--jc:'.$h;
    }

    public function overlay(mixed $percent): string
    {
        return '--overlay:'.(max(0, min(95, (int) $percent)) / 100);
    }

    /** Safe CSS length/number for inline style values. */
    public function num(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    /**
     * Editor preview only: marks an element as the inline-editable text of setting $key (on the
     * section, or on block $blockId). Prints nothing for shoppers.
     */
    public function e(string $key, ?string $blockId = null): string
    {
        if (! $this->preview) {
            return '';
        }

        return ' data-mv-field="'.e($key).'"'.($blockId !== null && $blockId !== '' ? ' data-mv-block="'.e($blockId).'"' : '');
    }

    /** Editor preview only: marks an element whose image (setting $key) can be swapped by double-clicking. */
    public function ei(string $key, ?string $blockId = null): string
    {
        if (! $this->preview) {
            return '';
        }

        return ' data-mv-image="'.e($key).'"'.($blockId !== null && $blockId !== '' ? ' data-mv-block="'.e($blockId).'"' : '');
    }

    /** Editor preview only: marks a block's wrapper so clicking it selects that block. */
    public function eb(?string $blockId): string
    {
        return $this->preview && $blockId !== null && $blockId !== '' ? ' data-mv-block="'.e($blockId).'"' : '';
    }
}
