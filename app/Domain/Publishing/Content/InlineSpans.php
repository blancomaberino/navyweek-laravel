<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Content;

use App\Domain\Navigation\Support\LinkUrl;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Bridges the stored inline-run shape (`spans`) and the HTML a Filament RichEditor
 * speaks, in both directions.
 *
 * A block's prose is stored as an ordered list of runs — `{text, bold?, italic?,
 * emphasis?, url?}` — which `resources/views/pages/content.blade.php` turns into
 * `<strong>` / `<em>` / `<span>` / `<a>`. The CMS needs the same content as HTML so an
 * editor can work on it in a rich editor, and needs it back as runs on save.
 *
 * It also owns the RENDER-side mapping ({@see render()}), so the site and the CMS read
 * one vocabulary instead of two implementations that must be kept in lockstep by hand.
 *
 * The editor mapping is deliberately TOTAL and LOSSLESS over the supported flag set: every
 * span array survives `fromHtml(toHtml($spans))` unchanged, which
 * `tests/Feature/Publishing/InlineSpansTest.php` asserts over the whole live corpus
 * plus adversarial cases. Anything the editor produces that this vocabulary cannot
 * express is flattened to plain text rather than silently dropped — a paragraph never
 * loses its words, only (at worst) a formatting flag.
 */
class InlineSpans
{
    /**
     * The mark → element vocabulary, innermost-first. `<a>` wraps these because that
     * is the nesting the renderer emits.
     *
     * PUBLIC because `resources/views/pages/content.blade.php` renders spans from the
     * same list: the CMS must be able to read back everything the site can display, so
     * a new mark has to appear in both at once or in neither.
     *
     * @var array<string, string>
     */
    public const TAGS = [
        'bold' => 'strong',
        'italic' => 'em',
        'emphasis' => 'span',
    ];

    /**
     * The inverse lookup, including the legacy `<b>`/`<i>` a paste from a word processor
     * brings in. Kept as data so reading a mark back is not a scan.
     *
     * @var array<string, string>
     */
    private const FLAG_BY_TAG = [
        'b' => 'bold',
        'em' => 'italic',
        'i' => 'italic',
        'span' => 'emphasis',
        'strong' => 'bold',
    ];

    /**
     * Runs → PAGE HTML. Differs from {@see toHtml()} in exactly the ways the public
     * page must: every editor-supplied href goes through `LinkUrl::sanitize()` (repo
     * policy — editor values are untrusted output), off-site links carry the legacy
     * `target`/`rel` pair, and there is no wrapping `<p>` because the view supplies its
     * own `<p>`/`<li>`/`<td>`.
     *
     * @param  list<array<string, mixed>>  $spans
     */
    public static function render(array $spans): string
    {
        $html = '';

        foreach ($spans as $span) {
            $piece = self::marked($span, nl2br(e(self::textOf($span)), false));
            $url = $span['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $safe = LinkUrl::sanitize($url);
                $offsite = str_starts_with($safe, 'http');

                $piece = '<a href="'.e($safe).'"'
                    .($offsite ? ' target="_blank" rel="noopener noreferrer"' : '')
                    .'>'.$piece.'</a>';
            }

            $html .= $piece;
        }

        return $html;
    }

    /**
     * A block's plain text, whether it stores `text` or `spans`. The two shapes are
     * interchangeable to a reader but NOT in the stored JSON, so every consumer that
     * wants "the words" must come through here — a view that reads `['text']` directly
     * silently loses its content the first time an editor bolds a word.
     *
     * @param  array<array-key, mixed>  $block
     */
    public static function plainText(array $block): string
    {
        if (isset($block['text'])) {
            return self::textOf($block);
        }

        $spans = $block['spans'] ?? [];

        if (! is_array($spans)) {
            return '';
        }

        return implode('', array_map(
            static fn (mixed $span): string => is_array($span) ? self::textOf($span) : '',
            $spans,
        ));
    }

    /**
     * Runs → editor HTML. Newlines inside a run become `<br>` so a soft-wrapped legacy
     * paragraph looks in the editor the way it looks on the page.
     *
     * @param  list<array<string, mixed>>  $spans
     */
    public static function toHtml(array $spans): string
    {
        $html = '';

        foreach ($spans as $span) {
            // REPLACES the newline rather than `nl2br`-ing it (which keeps both the
            // `<br>` AND the `\n`): the editor's HTML has to round-trip, and a surviving
            // newline beside its `<br>` reads back as two line breaks. The page renderer
            // deliberately differs here — see render().
            $piece = self::marked(
                $span,
                str_replace(["\r\n", "\r", "\n"], '<br>', e(self::textOf($span))),
            );
            $url = $span['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $piece = '<a href="'.e($url).'">'.$piece.'</a>';
            }

            $html .= $piece;
        }

        return $html === '' ? '' : '<p>'.$html.'</p>';
    }

    /**
     * Wrap a piece in the elements its marks call for, innermost-first.
     *
     * @param  array<array-key, mixed>  $span
     */
    private static function marked(array $span, string $piece): string
    {
        foreach (self::TAGS as $flag => $tag) {
            if ($span[$flag] ?? false) {
                $piece = '<'.$tag.'>'.$piece.'</'.$tag.'>';
            }
        }

        return $piece;
    }

    /**
     * @param  array<array-key, mixed>  $span
     */
    private static function textOf(array $span): string
    {
        $text = $span['text'] ?? '';

        return is_string($text) ? $text : '';
    }

    /**
     * Editor HTML → runs. Unknown elements contribute their text but none of their
     * formatting; adjacent runs carrying identical flags are merged so a round-trip
     * cannot fragment a paragraph into one span per keystroke.
     *
     * @return list<array{text: string, bold?: bool, italic?: bool, emphasis?: bool, url?: string}>
     */
    public static function fromHtml(?string $html): array
    {
        $html = trim((string) $html);

        if ($html === '') {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The fragment is not a document: give it an explicit UTF-8 meta so DOMDocument
        // does not fall back to Latin-1 and mangle the em dashes these pages are full of.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $spans = [];
        $root = $document->documentElement;

        if ($root instanceof DOMNode) {
            self::walk($root, [], $spans);
        }

        return self::merge($spans);
    }

    /**
     * Depth-first walk accumulating the formatting flags in scope. `<br>` and block
     * boundaries become newlines inside the current run, which `toHtml` turns back
     * into `<br>`.
     *
     * @param  array{bold?: bool, italic?: bool, emphasis?: bool, url?: string}  $flags
     * @param  list<array{text: string, bold?: bool, italic?: bool, emphasis?: bool, url?: string}>  $spans
     */
    private static function walk(DOMNode $node, array $flags, array &$spans): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = $child->textContent;

                if ($text !== '') {
                    $spans[] = [...$flags, 'text' => $text];
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            // Executable subtrees are dropped whole, contents included. Their text is
            // code, not prose, so carrying it through as words would only put an
            // `alert(1)` in the middle of a policy page. Everything else that this
            // vocabulary cannot express keeps its words and loses its formatting.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                continue;
            }

            if ($tag === 'br') {
                $spans[] = [...$flags, 'text' => "\n"];

                continue;
            }

            $childFlags = $flags;

            if (isset(self::FLAG_BY_TAG[$tag])) {
                $childFlags[self::FLAG_BY_TAG[$tag]] = true;
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));

                if ($href !== '') {
                    $childFlags['url'] = $href;
                }
            }

            // A paragraph/list boundary inside the fragment separates runs; the editor
            // emits one <p> per block, and a stray second one must not glue words together.
            $isBlock = in_array($tag, ['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4'], true);

            if ($isBlock && $spans !== []) {
                $spans[] = [...$flags, 'text' => "\n"];
            }

            self::walk($child, $childFlags, $spans);
        }
    }

    /**
     * Drop empty runs, fold neighbours that carry identical formatting, and put the
     * keys in the stored order (`text` first, then flags) so a round-trip is
     * byte-identical to what the importer wrote.
     *
     * @param  list<array{text: string, bold?: bool, italic?: bool, emphasis?: bool, url?: string}>  $spans
     * @return list<array{text: string, bold?: bool, italic?: bool, emphasis?: bool, url?: string}>
     */
    private static function merge(array $spans): array
    {
        $merged = [];

        foreach ($spans as $span) {
            $text = $span['text'];
            unset($span['text']);

            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['flags'] === $span) {
                $merged[$last]['text'] .= $text;

                continue;
            }

            $merged[] = ['text' => $text, 'flags' => $span];
        }

        $out = [];

        foreach ($merged as $entry) {
            // A run that is only whitespace between two formatted runs is meaningful
            // (the space between two links); one that is entirely empty is not.
            if ($entry['text'] === '') {
                continue;
            }

            $span = ['text' => $entry['text']];

            // Canonical key order — `text`, then the marks in TAGS order, then `url`.
            // The walk discovers flags outside-in (a bolded link yields `url` first),
            // so without this a round-trip would reorder keys the importer wrote and
            // show a diff on a page nobody edited.
            foreach (array_keys(self::TAGS) as $flag) {
                if ($entry['flags'][$flag] ?? false) {
                    $span[$flag] = true;
                }
            }

            if (isset($entry['flags']['url'])) {
                $span['url'] = $entry['flags']['url'];
            }

            $out[] = $span;
        }

        return $out;
    }
}
