<?php

namespace App\Modules\Builder\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Cleans owner-supplied HTML for the storefront (custom HTML sections, rich text).
 *
 * Stores can be served from the dashboard's own host (path mode), so storefront HTML must never run
 * script: <script>, <object>, <embed>, <base>, <meta>, <link> and forms posting elsewhere are dropped,
 * every on* attribute and javascript:/vbscript: URL is removed, and <iframe> is kept only for
 * well-known video, map and social embeds.
 */
final class HtmlSanitizer
{
    private const DROP_TAGS = ['script', 'object', 'embed', 'base', 'meta', 'link', 'applet', 'frame', 'frameset', 'noscript', 'template', 'portal', 'set'];

    private const RICH_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'span', 'small', 'mark', 'hr'];

    private const IFRAME_HOSTS = [
        'www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com',
        'www.google.com', 'maps.google.com', 'www.instagram.com', 'www.tiktok.com', 'open.spotify.com', 'w.soundcloud.com',
    ];

    /** Full custom HTML: any markup except the dangerous parts above. */
    public static function clean(?string $html): string
    {
        return self::run((string) $html, null);
    }

    /** Rich text: a small set of formatting tags; plain text gets line breaks. */
    public static function rich(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        if (strip_tags($html) === $html) {
            return nl2br(e($html), false);
        }
        if (! preg_match('/<(p|br|ul|ol|h[2-4]|blockquote|div)\b/i', $html)) {
            $html = nl2br($html, false);
        }

        return self::run($html, self::RICH_TAGS);
    }

    /** @param  list<string>|null  $allowedTags */
    private static function run(string $html, ?array $allowedTags): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__mv_root">'.mb_substr($html, 0, 100_000).'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($doc))->query('//*[@id="__mv_root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root, $allowedTags);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** @param  list<string>|null  $allowedTags */
    private static function walk(DOMNode $node, ?array $allowedTags): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_TAGS, true) || ($tag === 'iframe' && ! self::allowedIframe($child))) {
                $node->removeChild($child);

                continue;
            }
            if ($allowedTags !== null && ! in_array($tag, $allowedTags, true)) {
                // Keep the text, drop the tag.
                self::walk($child, $allowedTags);
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $allowedTags !== null);
            self::walk($child, $allowedTags);
        }
    }

    private static function cleanAttributes(DOMElement $el, bool $strict): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim(html_entity_decode($attr->value, ENT_QUOTES | ENT_HTML5));

            $remove = str_starts_with($name, 'on')
                || $name === 'formaction' || $name === 'srcdoc' || $name === 'xmlns'
                || ($strict && ! in_array($name, ['href', 'title', 'target', 'rel', 'class'], true));

            if (! $remove && in_array($name, ['href', 'src', 'action', 'xlink:href', 'poster', 'data', 'background', 'to', 'from', 'values', 'by'], true)) {
                $remove = ! self::safeUrl($value, $name === 'src');
            }
            if (! $remove && $name === 'style') {
                $remove = (bool) preg_match('/expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', $value);
            }

            if ($remove) {
                $el->removeAttribute($attr->name);
            }
        }

        if (strtolower($el->tagName) === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if (strtolower($el->tagName) === 'form') {
            $el->removeAttribute('action');
            $el->setAttribute('onsubmit', 'return false');
        }
    }

    public static function safeUrl(string $url, bool $allowDataImage = false): bool
    {
        $compact = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $url));
        if ($compact === '' || str_starts_with($compact, '#') || str_starts_with($compact, '/') || str_starts_with($compact, '?')) {
            return true;
        }
        if ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#', $compact)) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:|whatsapp:)#', $compact) || ! str_contains(explode('/', $compact)[0], ':');
    }

    private static function allowedIframe(DOMElement $iframe): bool
    {
        $host = strtolower((string) parse_url($iframe->getAttribute('src'), PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($iframe->getAttribute('src'), PHP_URL_SCHEME));

        return $scheme === 'https' && in_array($host, self::IFRAME_HOSTS, true);
    }
}
