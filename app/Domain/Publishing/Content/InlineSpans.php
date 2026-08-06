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
     * Marks a Filament RichEditor can carry. TipTap parses the stored HTML into its own
     * document model on the way IN and re-serialises on the way OUT, dropping every mark
     * it does not recognise — `<span>`, `<small>`, `<u>` and `<mark>` all vanish
     * (measured against the installed tiptap-php). So `emphasis`, which the renderer
     * emits as a bare `<span>`, CANNOT survive a round trip through the editor.
     *
     * A block carrying one is locked rather than silently flattened — see
     * {@see hasUneditableMark()} and BodyBlocks::hydrate().
     *
     * @var list<string>
     */
    public const EDITABLE_MARKS = ['bold', 'italic'];

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
     * Whether any run carries a mark the rich editor would destroy. Such a block is
     * preserved verbatim instead of being routed through the editor.
     *
     * @param  list<array<string, mixed>>|mixed  $spans
     */
    public static function hasUneditableMark(mixed $spans): bool
    {
        if (! is_array($spans)) {
            return false;
        }

        foreach ($spans as $span) {
            if (! is_array($span)) {
                continue;
            }

            foreach (array_keys(self::TAGS) as $mark) {
                if (($span[$mark] ?? false) && ! in_array($mark, self::EDITABLE_MARKS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Runs → PAGE HTML. Differs from {@see toHtml()} in exactly the ways the public
     * page must: every editor-supplied href goes through `LinkUrl::sanitize()` (repo
     * policy — editor values are untrusted output), off-site links carry the legacy
     * `target`/`rel` pair, and there is no wrapping `<p>` because the view supplies its
     * own `<p>`/`<li>`/`<td>`.
     *
     * @param  array<array-key, mixed>  $spans  untrusted stored JSON, not a typed list
     */
    public static function render(array $spans): string
    {
        $html = '';

        foreach ($spans as $span) {
            // A stored run that is not a map (bad import, hand-edited JSON) must not
            // take the public page down — this is a `{!! !!}` sink reached on every
            // content page.
            if (! is_array($span)) {
                continue;
            }

            $piece = self::marked($span, nl2br(e(self::textOf($span)), false));
            $url = $span['url'] ?? null;

            // `filled()`, not `!== ''`: a whitespace-only url is blank, and the view
            // this replaced rendered no anchor for it.
            if (is_scalar($url) && filled($url)) {
                $safe = LinkUrl::sanitize((string) $url);
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

        // Coerced, not narrowed: the closures this replaced did `(string)`, so a block
        // storing a numeric `text` still renders its digits. Non-scalars have no
        // sensible string form and would only produce a warning.
        return is_scalar($text) ? (string) $text : '';
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

        try {
            // The fragment is not a document: give it an explicit UTF-8 meta so DOMDocument
            // does not fall back to Latin-1 and mangle the em dashes these pages are full of.
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div>'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            // Restored under `finally` so internal-error mode cannot leak into the rest
            // of the request if the parse throws.
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if ($loaded === false || ! $root instanceof DOMNode) {
            // NEVER turn an unparseable body into an empty one — that would delete the
            // block's prose on save. Keep the words, lose only the markup.
            return self::merge([['text' => html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)]]);
        }

        $spans = [];
        self::walk($root, [], $spans);

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

            // A block boundary inside the fragment separates runs — without it, a
            // pasted table's cells glue into one word ("RatingMonthly"). Word
            // boundaries are content, so the list has to cover everything a paste can
            // bring in, not just what the editor itself emits.
            $isBlock = in_array($tag, [
                'p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'tr', 'td', 'th', 'blockquote', 'pre', 'dt', 'dd', 'figcaption',
            ], true);

            $last = $spans === [] ? null : $spans[array_key_last($spans)];

            // Nested blocks (TipTap wraps a list item's text in a <p>) would otherwise
            // each add their own break and render <br><br>.
            if ($isBlock && $last !== null && $last['text'] !== "\n") {
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

            // Compare the flag SET, not discovery order: `<strong><a>` and `<a><strong>`
            // yield the same run, and the canonical re-ordering below emits them
            // identically — so without this, a second save would re-split them.
            ksort($span);

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
