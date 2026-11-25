<?php

namespace App\Modules\Builder\Services;

/**
 * Makes owner-written CSS safe to print inside a <style> tag, and scopes section CSS.
 *
 * CSS cannot run scripts in current browsers, so the only risk is breaking out of the <style>
 * element; every "<" is removed. Section CSS may use "&" (or ":scope") for "this section".
 */
final class CssSanitizer
{
    public const MAX_LENGTH = 60_000;

    public static function clean(string $css): string
    {
        $css = mb_substr($css, 0, self::MAX_LENGTH);
        $css = str_replace(['<', '\\3c', '\\3C', '\\00003c'], '', $css);
        // Legacy IE script hooks.
        $css = (string) preg_replace('/expression\s*\(|behavior\s*:|-moz-binding/i', '', $css);

        return trim($css);
    }

    /** Replaces "&" / ":scope" with the section's selector. Rules without either are left as written. */
    public static function scope(string $css, string $selector): string
    {
        $css = self::clean($css);

        return str_replace(['&', ':scope'], [$selector, $selector], $css);
    }
}
