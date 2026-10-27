<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Saneador de HTML enriquecido (Tiptap) por lista blanca. Todo contenido
 * editable que se muestre con v-html debe pasar por aquí al guardarse.
 */
final class Html
{
    /** @var array<string, list<string>> etiqueta → atributos permitidos */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'hr' => [], 'code' => [], 'pre' => [], 'span' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /** Texto plano (para meta descriptions, resúmenes). */
    public static function text(?string $html, int $limit = 0): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))) ?? '');

        return $limit > 0 ? mb_strimwidth($text, 0, $limit, '…') : $text;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! array_key_exists($tag, self::ALLOWED)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'svg', 'math', 'link', 'meta'], true)) {
                        $node->removeChild($child);

                        continue;
                    }
                    // Etiqueta desconocida: se conserva solo su contenido.
                    self::walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                    $name = strtolower($attr->name);
                    if (! in_array($name, self::ALLOWED[$tag], true)) {
                        $child->removeAttribute($attr->name);

                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attr->value, $name === 'src')) {
                        $child->removeAttribute($attr->name);
                    }
                }

                if ($tag === 'a') {
                    if ($child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    } else {
                        $child->removeAttribute('target');
                    }
                }

                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function safeUrl(string $url, bool $isImage): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $isImage ? $scheme === 'https' : in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
