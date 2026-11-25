<?php

namespace App\Modules\Catalog\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Converts uploads to responsive WebP (and AVIF when supported) variants and renders
 * <picture> markup with srcset and lazy loading. FR-CAT-08
 */
class ImageService
{
    /** @var list<int> */
    public const RESPONSIVE_WIDTHS = [320, 640, 960, 1280];

    /**
     * Responsive image widths generated for storefront delivery.
     *
     * @return list<int>
     */
    public function responsiveWidths(): array
    {
        return self::RESPONSIVE_WIDTHS;
    }

    /**
     * Output formats supported by the current runtime (WebP always, AVIF when GD supports it).
     *
     * @return list<string>
     */
    public function supportedFormats(): array
    {
        $formats = ['webp'];

        if (function_exists('imageavif')) {
            $formats[] = 'avif';
        }

        return $formats;
    }

    /**
     * Build a responsive srcset attribute string for the given base path and format.
     */
    public function buildSrcset(string $basePath, string $format = 'webp'): string
    {
        $prefix = $this->stripExtension($basePath);
        $ext = strtolower(trim($format, '. '));

        return implode(', ', array_map(
            fn (int $width): string => "{$prefix}-{$width}w.{$ext} {$width}w",
            $this->responsiveWidths(),
        ));
    }

    /**
     * Render a responsive <picture> element with AVIF/WebP <source> elements and lazy-loaded <img>.
     */
    public function renderPictureHtml(
        string $basePath,
        string $alt,
        string $sizes = '(max-width: 768px) 100vw, 50vw',
    ): string {
        $prefix = $this->stripExtension($basePath);
        $escapedAlt = e($alt);
        $escapedSizes = e($sizes);
        $formats = $this->supportedFormats();

        $sources = [];

        if (in_array('avif', $formats, true)) {
            $avifSrcset = e($this->buildSrcset($prefix, 'avif'));
            $sources[] = "<source type=\"image/avif\" srcset=\"{$avifSrcset}\" sizes=\"{$escapedSizes}\">";
        }

        $webpSrcset = e($this->buildSrcset($prefix, 'webp'));
        $sources[] = "<source type=\"image/webp\" srcset=\"{$webpSrcset}\" sizes=\"{$escapedSizes}\">";

        $fallbackSrc = e("{$prefix}-960w.webp");
        $img = "<img src=\"{$fallbackSrc}\" alt=\"{$escapedAlt}\" loading=\"lazy\" decoding=\"async\">";

        return '<picture>'.implode('', $sources).$img.'</picture>';
    }

    /**
     * Generate responsive variants on the public disk, using GD when available.
     *
     * @return array{prefix: string, formats: list<string>, widths: list<int>, variants: array<string, array<int, string>>}
     */
    public function processUpload(string $sourcePath, string $outputPrefix): array
    {
        $disk = Storage::disk('public');
        $prefix = trim($this->stripExtension($outputPrefix), '/');
        $formats = $this->supportedFormats();
        $widths = $this->responsiveWidths();
        $rawBytes = is_file($sourcePath) && is_readable($sourcePath) ? (string) file_get_contents($sourcePath) : '';

        $sourceImage = ($rawBytes !== '' && function_exists('imagecreatefromstring'))
            ? @imagecreatefromstring($rawBytes)
            : false;

        $variants = [];

        foreach ($formats as $format) {
            $variants[$format] = [];

            foreach ($widths as $width) {
                $variantPath = "{$prefix}-{$width}w.{$format}";
                $encoded = $sourceImage !== false ? $this->encodeVariant($sourceImage, $width, $format) : null;

                $disk->put($variantPath, $encoded ?? $rawBytes);
                $variants[$format][$width] = $variantPath;
            }
        }

        if ($sourceImage !== false) {
            imagedestroy($sourceImage);
        }

        return [
            'prefix' => $prefix,
            'formats' => $formats,
            'widths' => $widths,
            'variants' => $variants,
        ];
    }

    private function encodeVariant(\GdImage $source, int $maxWidth, string $format): ?string
    {
        $origWidth = max(1, imagesx($source));
        $origHeight = max(1, imagesy($source));
        $targetWidth = min($maxWidth, $origWidth);
        $targetHeight = max(1, (int) round($origHeight * ($targetWidth / $origWidth)));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            return null;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

        ob_start();
        $ok = match ($format) {
            'avif' => function_exists('imageavif') ? @imageavif($canvas, null, 60) : false,
            default => function_exists('imagewebp') ? @imagewebp($canvas, null, 82) : false,
        };
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $ok && is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    private function stripExtension(string $path): string
    {
        return (string) preg_replace('/\.[a-zA-Z0-9]+$/', '', trim($path));
    }
}
