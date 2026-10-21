<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitiser for CMS text fields. Only a small set of inline
 * (and, for rich text, block) tags survive; every attribute except a few safe
 * ones is removed, and links are restricted to safe schemes.
 */
class HtmlSanitizer
{
    private const INLINE = ['br', 'em', 'strong', 'b', 'i', 'span', 'small', 'sup', 'sub', 'u', 'a', 'abbr', 'mark'];

    private const BLOCK = ['p', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote'];

    private const ATTRS = ['a' => ['href', 'target', 'rel'], 'span' => ['class'], 'abbr' => ['title'], 'p' => ['class'], 'li' => ['class']];

    public static function inline(?string $html): string
    {
        return self::clean((string) $html, self::INLINE);
    }

    public static function rich(?string $html): string
    {
        return self::clean((string) $html, array_merge(self::INLINE, self::BLOCK));
    }

    public static function clean(string $html, array $allowed): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // Fast path: plain text without markup or entities that matter.
        if (! str_contains($html, '<') && ! str_contains($html, '>')) {
            return htmlspecialchars(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_NOQUOTES, 'UTF-8');
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root') ?? $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return e(strip_tags($html));
        }
        self::walk($root, $allowed);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node, array $allowed): void
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
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button'], true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child, $allowed);
            if (! in_array($tag, $allowed, true)) {
                // unwrap: keep text content, drop the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $keep = self::ATTRS[$tag] ?? [];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, $keep, true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $href = $child->getAttribute('href');
                if (! SiteUrl::isSafe($href)) {
                    $child->removeAttribute('href');
                }
                if ($child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('target');
                    $child->removeAttribute('rel');
                }
            }
            if ($child->hasAttribute('class')) {
                $cls = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $child->getAttribute('class'));
                $child->setAttribute('class', trim($cls));
            }
        }
    }
}
