<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for the limited rich text of places, content
 * items and pages (docs/places_content_contract.md §2): paragraphs, line
 * breaks, bold / italic / underline, lists, small headings, quotes and
 * links (http, https, mailto, tel). Everything else is unwrapped (its text
 * kept) and script-like elements are dropped with their content. Every
 * attribute except a link's href is removed; links get rel="noopener
 * nofollow" and target="_blank".
 *
 * Plain text without tags is turned into paragraphs (blank line = new
 * paragraph, single newline = <br>). Bodies are sanitized when saved and
 * again when served, so rows written outside the admin are safe too.
 */
class HtmlSanitizer
{
    public const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h3', 'h4', 'blockquote'];

    /** Removed together with their content. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form',
        'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'head', 'title', 'frame', 'frameset', 'applet'];

    private const BLOCKS = ['p', 'ul', 'ol', 'h3', 'h4', 'blockquote'];

    private const RENAME = ['h1' => 'h3', 'h2' => 'h3', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p'];

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $html = trim(str_replace("\r\n", "\n", $html));
        if ($html === '') {
            return null;
        }
        if (! preg_match('/<[a-z!\/][^>]*>/i', $html)) {
            return self::fromPlainText($html);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__root">'.$html.'</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return self::fromPlainText(strip_tags($html));
        }
        self::walk($root, $doc);

        // Top-level text / inline nodes are grouped into paragraphs.
        $out = '';
        $inline = '';
        $flush = function () use (&$out, &$inline) {
            if (trim(strip_tags($inline, '<br>')) !== '') {
                $out .= '<p>'.trim($inline).'</p>';
            }
            $inline = '';
        };
        foreach ($root->childNodes as $child) {
            $html = $doc->saveHTML($child);
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::BLOCKS, true)) {
                $flush();
                $out .= $html;
            } else {
                $inline .= $html;
            }
        }
        $flush();
        $out = trim(preg_replace('/(<p>(\s|&nbsp;|<br>)*<\/p>\s*)+/u', '', $out) ?? '');

        return $out === '' || trim(strip_tags($out)) === '' ? null : $out;
    }

    /** Text without tags (for search snippets, AI tools, previews). */
    public static function toText(?string $html, int $limit = 0): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/li|\/h[34])\s*\/?>/i', "$0 ", (string) $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $limit > 0 ? \Illuminate\Support\Str::limit($text, $limit) : $text;
    }

    public static function fromPlainText(string $text): ?string
    {
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/u', trim($text)) ?: []), fn ($p) => $p !== '');
        if (! $paragraphs) {
            return null;
        }

        return implode('', array_map(fn ($p) => '<p>'.str_replace("\n", '<br>', e($p)).'</p>', $paragraphs));
    }

    private static function walk(DOMNode $node, DOMDocument $doc): void
    {
        // Iterate over a static copy: children are replaced while walking.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child, $doc);

            if (isset(self::RENAME[$tag])) {
                $child = self::rename($child, self::RENAME[$tag], $doc);
                $tag = self::RENAME[$tag];
            }

            if (! in_array($tag, self::ALLOWED, true)) {
                // Unwrap: keep the (already cleaned) children.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $href = $tag === 'a' ? self::safeHref($child->getAttribute('href')) : null;
            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttribute($attr->nodeName);
            }
            if ($tag === 'a') {
                if ($href === null) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }
                $child->setAttribute('href', $href);
                $child->setAttribute('rel', 'noopener nofollow');
                $child->setAttribute('target', '_blank');
            }
        }
    }

    private static function rename(DOMElement $el, string $tag, DOMDocument $doc): DOMElement
    {
        $new = $doc->createElement($tag);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }

    private static function safeHref(string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Control characters / whitespace inside the scheme are a classic bypass.
        $compact = preg_replace('/[\x00-\x20]+/', '', $href) ?? '';
        if ($href === '' || ! preg_match('#^(https?://|mailto:|tel:)#i', $compact)) {
            return null;
        }

        return mb_substr($compact, 0, 500);
    }
}
