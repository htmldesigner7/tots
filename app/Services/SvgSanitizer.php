<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * Conservative SVG sanitiser: only presentational shape elements and
 * attributes survive. Scripts, event handlers, foreignObject, external
 * references and CSS (style elements/attributes) are removed.
 */
class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan',
        'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'title', 'desc', 'symbol', 'use',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit', 'opacity',
        'transform', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'clip-path',
        'mask', 'preserveaspectratio', 'xmlns', 'version', 'font-family', 'font-size', 'font-weight',
        'text-anchor', 'dominant-baseline', 'href', 'xlink:href', 'fx', 'fy', 'spreadmethod', 'role', 'aria-hidden',
        'aria-label', 'focusable',
    ];

    public function sanitize(string $svg): ?string
    {
        if (strlen($svg) > 2_000_000 || preg_match('/<!ENTITY|<!DOCTYPE/i', $svg)) {
            return null;
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok || ! $doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
            return null;
        }
        $this->clean($doc->documentElement);

        return $doc->saveXML($doc->documentElement);
    }

    private function clean(DOMElement $el): void
    {
        foreach (iterator_to_array($el->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $el->removeChild($child);
                    continue;
                }
                $this->clean($child);
            } elseif (in_array($child->nodeType, [XML_PI_NODE, XML_COMMENT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $el->removeChild($child);
            }
        }
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = trim($attr->nodeValue);
            $bad = ! in_array($name, self::ATTRIBUTES, true)
                || str_starts_with($name, 'on')
                || preg_match('/(javascript|data|vbscript):/i', $value)
                || (in_array($name, ['href', 'xlink:href'], true) && ! str_starts_with($value, '#'))
                || preg_match('/url\(\s*[\'"]?\s*(?!#)/i', $value);
            if ($bad) {
                $el->removeAttributeNode($attr);
            }
        }
    }
}
