<?php

namespace Modules\Site\Domain\Page;

/**
 * Limited rich text, never HTML (site-builder.md, 1.5): paragraphs, h3 and
 * h4 (the block owns h2), bullet and numbered lists, quotes; inside them
 * plain text, bold, italic and links that follow the page's link rules. The
 * builder's editor saves TipTap's JSON: fromEditor() keeps only those parts
 * and drops everything else (marks, attributes, images, tables);
 * toEditor() gives it back to the editor. At most 60 nodes and 5,000
 * characters a language. Pure.
 *
 * Node:   {t: p|h3|h4|quote, c: [Inline]} | {t: ul|ol, items: [[Inline]]}
 * Inline: {text, b?: true, i?: true, href?: string}
 */
final class RichText
{
    public const MAX_NODES = 60;

    public const MAX_CHARS = 5000;

    public const MAX_ITEMS = 20;

    /**
     * A tree as the page keeps it, cleaned: unknown nodes and empty ones
     * dropped, links with a refused address lose it. Over the limits, a problem.
     *
     * @return list<array<string, mixed>>
     */
    public static function clean(mixed $nodes, string $path, Problems $problems): array
    {
        $out = [];
        foreach (is_array($nodes) ? $nodes : [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $t = $node['t'] ?? null;
            if (in_array($t, ['p', 'h3', 'h4', 'quote'], true)) {
                $c = self::inlines($node['c'] ?? []);
                if ($c !== []) {
                    $out[] = ['t' => $t, 'c' => $c];
                }
            } elseif (in_array($t, ['ul', 'ol'], true)) {
                $items = [];
                foreach (is_array($node['items'] ?? null) ? $node['items'] : [] as $item) {
                    $c = self::inlines($item);
                    if ($c !== []) {
                        $items[] = $c;
                    }
                }
                if (count($items) > self::MAX_ITEMS) {
                    $problems->add($path, 'rich_items', ['max' => self::MAX_ITEMS]);
                    $items = array_slice($items, 0, self::MAX_ITEMS);
                }
                if ($items !== []) {
                    $out[] = ['t' => $t, 'items' => $items];
                }
            }
        }
        if (self::nodes($out) > self::MAX_NODES) {
            $problems->add($path, 'rich_nodes', ['max' => self::MAX_NODES]);
        }
        if (self::chars($out) > self::MAX_CHARS) {
            $problems->add($path, 'rich_chars', ['max' => self::MAX_CHARS]);
        }

        return $out;
    }

    /**
     * TipTap's JSON from the builder's editor → the page's tree.
     *
     * @return list<array<string, mixed>>
     */
    public static function fromEditor(mixed $doc): array
    {
        if (is_string($doc)) {
            $decoded = json_decode($doc, true);
            $doc = is_array($decoded) ? $decoded : ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => strip_tags($doc)]]]]];
        }
        $out = [];
        foreach (is_array($doc) && is_array($doc['content'] ?? null) ? $doc['content'] : [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            switch ($node['type'] ?? null) {
                case 'paragraph':
                    $out[] = ['t' => 'p', 'c' => self::fromInline($node['content'] ?? [])];
                    break;
                case 'heading':
                    $level = (int) ($node['attrs']['level'] ?? 3);
                    $out[] = ['t' => $level >= 4 ? 'h4' : 'h3', 'c' => self::fromInline($node['content'] ?? [])];
                    break;
                case 'blockquote':
                    $c = [];
                    foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $inner) {
                        if ($c !== []) {
                            $c[] = ['text' => "\n"];
                        }
                        $c = [...$c, ...self::fromInline(is_array($inner) ? ($inner['content'] ?? []) : [])];
                    }
                    $out[] = ['t' => 'quote', 'c' => $c];
                    break;
                case 'bulletList':
                case 'orderedList':
                    $items = [];
                    foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $item) {
                        $c = [];
                        foreach (is_array($item) && is_array($item['content'] ?? null) ? $item['content'] : [] as $inner) {
                            if (is_array($inner) && ($inner['type'] ?? null) === 'paragraph') {
                                if ($c !== []) {
                                    $c[] = ['text' => "\n"];
                                }
                                $c = [...$c, ...self::fromInline($inner['content'] ?? [])];
                            }
                        }
                        $items[] = $c;
                    }
                    $out[] = ['t' => ($node['type'] === 'orderedList' ? 'ol' : 'ul'), 'items' => $items];
                    break;
            }
        }

        return self::clean($out, '', new Problems);
    }

    /**
     * The page's tree → TipTap's JSON, for the builder's editor.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public static function toEditor(array $nodes): array
    {
        $content = [];
        foreach ($nodes as $node) {
            $t = $node['t'] ?? null;
            if ($t === 'p') {
                $content[] = ['type' => 'paragraph', 'content' => self::toInline($node['c'] ?? [])];
            } elseif ($t === 'h3' || $t === 'h4') {
                $content[] = ['type' => 'heading', 'attrs' => ['level' => $t === 'h3' ? 3 : 4], 'content' => self::toInline($node['c'] ?? [])];
            } elseif ($t === 'quote') {
                $content[] = ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => self::toInline($node['c'] ?? [])]]];
            } elseif ($t === 'ul' || $t === 'ol') {
                $items = [];
                foreach ($node['items'] ?? [] as $item) {
                    $items[] = ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => self::toInline($item)]]];
                }
                $content[] = ['type' => $t === 'ul' ? 'bulletList' : 'orderedList', 'content' => $items];
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /** @param  list<array<string, mixed>>  $nodes */
    public static function isEmpty(array $nodes): bool
    {
        return $nodes === [];
    }

    /** @param  list<array<string, mixed>>  $nodes */
    public static function chars(array $nodes): int
    {
        $n = 0;
        foreach ($nodes as $node) {
            foreach ($node['c'] ?? [] as $inline) {
                $n += mb_strlen((string) ($inline['text'] ?? ''));
            }
            foreach ($node['items'] ?? [] as $item) {
                foreach ($item as $inline) {
                    $n += mb_strlen((string) ($inline['text'] ?? ''));
                }
            }
        }

        return $n;
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private static function nodes(array $nodes): int
    {
        $n = 0;
        foreach ($nodes as $node) {
            $n += 1 + count($node['items'] ?? []);
        }

        return $n;
    }

    /** @return list<array<string, mixed>> */
    private static function inlines(mixed $inlines): array
    {
        $out = [];
        foreach (is_array($inlines) ? $inlines : [] as $inline) {
            if (! is_array($inline)) {
                continue;
            }
            $text = (string) preg_replace('/[^\P{C}\n]/u', '', is_scalar($inline['text'] ?? null) ? (string) $inline['text'] : '');
            if ($text === '') {
                continue;
            }
            $clean = ['text' => $text];
            if (($inline['b'] ?? false) === true) {
                $clean['b'] = true;
            }
            if (($inline['i'] ?? false) === true) {
                $clean['i'] = true;
            }
            $href = is_string($inline['href'] ?? null) ? trim($inline['href']) : '';
            if ($href !== '' && Clean::isLinkUrl($href)) {
                $clean['href'] = $href;
            }
            $out[] = $clean;
        }
        // Nothing but spaces is nothing.
        $all = '';
        foreach ($out as $inline) {
            $all .= $inline['text'];
        }

        return trim($all) === '' ? [] : $out;
    }

    /** @return list<array<string, mixed>> */
    private static function fromInline(mixed $content): array
    {
        $out = [];
        foreach (is_array($content) ? $content : [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'hardBreak') {
                $out[] = ['text' => "\n"];

                continue;
            }
            if (($node['type'] ?? null) !== 'text') {
                continue;
            }
            $inline = ['text' => (string) ($node['text'] ?? '')];
            foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
                $type = is_array($mark) ? ($mark['type'] ?? null) : null;
                if ($type === 'bold') {
                    $inline['b'] = true;
                } elseif ($type === 'italic') {
                    $inline['i'] = true;
                } elseif ($type === 'link' && is_string($mark['attrs']['href'] ?? null)) {
                    $inline['href'] = $mark['attrs']['href'];
                }
            }
            $out[] = $inline;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $inlines
     * @return list<array<string, mixed>>
     */
    private static function toInline(array $inlines): array
    {
        $out = [];
        foreach ($inlines as $inline) {
            $parts = explode("\n", (string) ($inline['text'] ?? ''));
            foreach ($parts as $i => $part) {
                if ($i > 0) {
                    $out[] = ['type' => 'hardBreak'];
                }
                if ($part === '') {
                    continue;
                }
                $marks = [];
                if (($inline['b'] ?? false) === true) {
                    $marks[] = ['type' => 'bold'];
                }
                if (($inline['i'] ?? false) === true) {
                    $marks[] = ['type' => 'italic'];
                }
                if (isset($inline['href'])) {
                    $marks[] = ['type' => 'link', 'attrs' => ['href' => $inline['href'], 'target' => null]];
                }
                $out[] = ['type' => 'text', 'text' => $part] + ($marks !== [] ? ['marks' => $marks] : []);
            }
        }

        return $out;
    }
}
