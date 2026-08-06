<?php

declare(strict_types=1);

use App\Domain\Publishing\Content\BodyBlocks;

/**
 * The load-edit-save cycle must not change a single byte of a body an editor did not
 * touch. This asserts it against the REAL corpus — every block of every CMS-backed page,
 * captured verbatim into tests/Fixtures/body-blocks/.
 *
 * The old repeater modelled 4 block types and a flat `text` key against a renderer that
 * reads 19 types and nested `spans`, so opening a page in the CMS showed empty boxes.
 * This test is what makes that impossible to reintroduce.
 */
dataset('body block corpus', array_map(
    static fn (array $blocks): array => [$blocks],
    bodyBlockCorpus(),
));

it('round-trips every block of every live content page unchanged', function (array $blocks): void {
    $result = BodyBlocks::dehydrate(BodyBlocks::hydrate($blocks));

    // Compared as JSON so key ORDER and scalar TYPES are part of the assertion: a
    // silently reordered or stringified key would still be a diff on the live page.
    expect(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ->toBe(json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
})->with('body block corpus');

it('hydrates every block into a builder item carrying its type', function (array $blocks): void {
    $state = BodyBlocks::hydrate($blocks);

    expect($state)->toHaveCount(count($blocks));

    foreach ($state as $index => $item) {
        expect($item['type'])->toBe($blocks[$index]['type'])
            ->and($item['data'])->not->toHaveKey('type');
    }
})->with('body block corpus');

it('gives every rich block prose the editor can actually see', function (array $blocks): void {
    $state = BodyBlocks::hydrate($blocks);

    expect($state)->not->toBeEmpty();

    foreach ($state as $index => $item) {
        $source = $blocks[$index];

        // The exact defect that shipped: a stored `spans` paragraph rendered an empty
        // editor box. Every block that HAS prose must arrive with that prose in it.
        if (! isset($source['spans']) || $source['spans'] === []) {
            continue;
        }

        $plain = implode('', array_column($source['spans'], 'text'));

        $visible = html_entity_decode(strip_tags((string) $item['data']['content']), ENT_QUOTES | ENT_HTML5);

        expect($item['data']['content'])->toBeString()
            ->and($visible)->toContain(mb_substr($plain, 0, 40));
    }
})->with('body block corpus');

it('returns an empty list for an empty or absent builder state', function (): void {
    expect(BodyBlocks::dehydrate([]))->toBe([])
        ->and(BodyBlocks::dehydrate(null))->toBe([])
        ->and(BodyBlocks::hydrate(null))->toBe([]);
});

it('keeps a plain-text paragraph plain but promotes it when formatting is added', function (): void {
    $blocks = [['type' => 'paragraph', 'text' => 'Source: VA.gov']];

    $state = BodyBlocks::hydrate($blocks);
    expect($state[0]['data']['shape'])->toBe('text');

    // Untouched: still a `text` paragraph.
    expect(BodyBlocks::dehydrate($state))->toBe($blocks);

    // Bolded in the editor: `text` cannot carry that, so it graduates to `spans`.
    $state[0]['data']['content'] = '<p><strong>Source:</strong> VA.gov</p>';

    expect(BodyBlocks::dehydrate($state))->toBe([[
        'type' => 'paragraph',
        'spans' => [
            ['text' => 'Source:', 'bold' => true],
            ['text' => ' VA.gov'],
        ],
    ]]);
});

it('preserves the list item shape the renderer requires', function (): void {
    // A list entry renders via `$entry['spans']`; flattening it to a bare `text` key
    // would render an empty bullet, so the shape must survive the round-trip.
    $blocks = [[
        'type' => 'list',
        'ordered' => false,
        'items' => [['spans' => [['text' => 'Browser type and version']]]],
    ]];

    expect(BodyBlocks::dehydrate(BodyBlocks::hydrate($blocks)))->toBe($blocks);
});

it('preserves a table rows-of-cells matrix through the nested repeater', function (): void {
    $blocks = [[
        'type' => 'table',
        'columns' => [['label' => 'Rating'], ['label' => 'Monthly', 'align' => 'right']],
        'rows' => [
            [
                ['spans' => [['text' => '10%']]],
                ['spans' => [['text' => '$175.51']], 'align' => 'right', 'accent' => true],
            ],
        ],
    ]];

    expect(BodyBlocks::dehydrate(BodyBlocks::hydrate($blocks)))->toBe($blocks);
});
