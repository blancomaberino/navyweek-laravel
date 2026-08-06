<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Content;

/**
 * Translates `pages.body_blocks` between the shape the renderer reads and the shape a
 * Filament Builder edits, in both directions.
 *
 * Stored blocks are flat maps keyed by `type` (`{type: 'paragraph', spans: [...],
 * variant: 'lead'}`); a Builder's state is `{type, data: {...}}` and its rich fields
 * speak HTML, not inline runs. `hydrate()` goes stored → form, `dehydrate()` goes back.
 *
 * Two invariants make this safe to point at live YMYL content:
 *
 * - **Lossless.** `dehydrate(hydrate($blocks)) === $blocks` for every block in the
 *   corpus — asserted per page by `tests/Feature/Publishing/BodyBlocksRoundTripTest.php`.
 *   Any key the form does not model would break that test rather than be silently
 *   dropped on the editor's first save.
 * - **Shape-preserving.** A paragraph may store its prose as plain `text` or as rich
 *   `spans`, and the two are NOT interchangeable downstream (a `list` item without
 *   `spans` renders empty). The originating shape rides along in the form state and is
 *   restored on save, upgraded to `spans` only when the editor actually adds formatting
 *   that plain text cannot carry.
 */
class BodyBlocks
{
    /**
     * Stored blocks → Builder state.
     *
     * @param  list<array<string, mixed>>|null  $blocks
     * @return list<array{type: string, data: array<array-key, mixed>}>
     */
    public static function hydrate(?array $blocks): array
    {
        $state = [];

        foreach ($blocks ?? [] as $block) {
            $type = self::stringOr($block['type'] ?? null, 'paragraph');
            unset($block['type']);

            $data = self::encodeMap($block);

            // A `text`-shaped paragraph is edited in the same rich field as a
            // `spans`-shaped one; remember which it was so save can put it back.
            if ($type === 'paragraph' && array_key_exists('text', $data)) {
                $data = self::replaceKey($data, 'text', 'content', InlineSpans::toHtml([
                    ['text' => self::stringOr($data['text'], '')],
                ]));
                $data['shape'] = 'text';
            }

            $state[] = ['type' => $type, 'data' => $data];
        }

        return $state;
    }

    /**
     * Builder state → stored blocks. Filament keys repeater/builder items by UUID, so
     * the incoming array is re-indexed before it is written back.
     *
     * @param  array<array-key, array{type?: string, data?: array<string, mixed>}>|null  $state
     * @return list<array<array-key, mixed>>
     */
    public static function dehydrate(?array $state): array
    {
        $blocks = [];

        foreach ($state ?? [] as $item) {
            $type = self::stringOr($item['type'] ?? null, 'paragraph');
            $data = $item['data'] ?? [];

            $isPlainText = ($data['shape'] ?? null) === 'text';
            unset($data['shape']);

            $block = self::decodeMap($data);
            $spans = $block['spans'] ?? null;

            // Plain text can only carry a single unformatted run. The moment an editor
            // bolds a word or adds a link, the block graduates to `spans`.
            if ($type === 'paragraph' && $isPlainText && is_array($spans)
                && count($spans) === 1 && is_array($spans[0]) && array_keys($spans[0]) === ['text']
            ) {
                $block = self::replaceKey($block, 'spans', 'text', $spans[0]['text']);
            }

            $blocks[] = ['type' => $type, ...$block];
        }

        return $blocks;
    }

    /**
     * Recursively rename every `spans` run-list to the `content` HTML the rich editor
     * binds to, leaving every other key — and the key ORDER — untouched.
     *
     * @param  array<array-key, mixed>  $map
     * @return array<array-key, mixed>
     */
    private static function encodeMap(array $map): array
    {
        $out = [];

        foreach ($map as $key => $item) {
            if ($key === 'spans' && is_array($item)) {
                /** @var list<array<string, mixed>> $item */
                $out['content'] = InlineSpans::toHtml($item);

                continue;
            }

            $out[$key] = self::encodeValue($item);
        }

        return $out;
    }

    private static function encodeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return self::encodeMap($value);
        }

        // A list nested DIRECTLY inside a list (a table's rows-of-cells) has no field
        // name to bind to, so each row is wrapped in a single-key map the inner
        // repeater can own. `decodeValue()` unwraps it again.
        return array_map(
            static fn (mixed $item): mixed => is_array($item) && array_is_list($item)
                ? ['cells' => self::encodeValue($item)]
                : self::encodeValue($item),
            $value,
        );
    }

    /**
     * The inverse of {@see encodeMap()}.
     *
     * @param  array<array-key, mixed>  $map
     * @return array<array-key, mixed>
     */
    private static function decodeMap(array $map): array
    {
        $out = [];

        foreach ($map as $key => $item) {
            if ($key === 'content') {
                $out['spans'] = InlineSpans::fromHtml(is_string($item) ? $item : null);

                continue;
            }

            // Filament hands back every field in the block's schema, including the ones
            // the editor never filled. Writing those through as explicit nulls would
            // rewrite a body nobody touched (`"variant": null` on all 133 paragraphs),
            // so an absent value stays absent — which is what the renderer's
            // `$block['variant'] ?? null` reads anyway. ONLY null is dropped: `false`
            // and `''` are values a block may legitimately store (the importer writes
            // `ordered: false` explicitly).
            if ($item === null) {
                continue;
            }

            $out[$key] = self::decodeValue($item);
        }

        return $out;
    }

    private static function decodeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return self::decodeMap($value);
        }

        // NOTE: the unwrap is NAME-based, so `cells` is a reserved key for a list item —
        // a block that stored `rows: [{cells: […]}]` natively would be flattened here.
        // `ContentBlockCoverageTest` asserts the corpus never does that.
        return array_map(
            static fn (mixed $item): mixed => is_array($item) && array_keys($item) === ['cells'] && is_array($item['cells'])
                ? self::decodeValue(array_values($item['cells']))
                : self::decodeValue($item),
            $value,
        );
    }

    /**
     * Narrow a value the form layer types as `mixed` to the string this mapper needs,
     * without pretending a non-string was one.
     */
    private static function stringOr(mixed $value, string $fallback): string
    {
        return is_string($value) ? $value : $fallback;
    }

    /**
     * Swap one key for another IN PLACE, so a round-trip preserves the stored key order
     * and the diff of a saved page stays readable.
     *
     * @param  array<array-key, mixed>  $array
     * @return array<array-key, mixed>
     */
    private static function replaceKey(array $array, string $from, string $to, mixed $value): array
    {
        $out = [];

        foreach ($array as $key => $item) {
            if ($key === $from) {
                $out[$to] = $value;

                continue;
            }

            $out[$key] = $item;
        }

        return $out;
    }
}
