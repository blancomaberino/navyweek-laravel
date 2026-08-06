<?php

declare(strict_types=1);

use App\Domain\Publishing\Content\InlineSpans;

it('round-trips every span run in the live corpus', function (): void {
    $checked = 0;

    $walk = function (mixed $node) use (&$walk, &$checked): void {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key === 'spans' && is_array($value) && $value !== []) {
                expect(InlineSpans::fromHtml(InlineSpans::toHtml($value)))->toBe($value);
                $checked++;
            }

            $walk($value);
        }
    };

    foreach (bodyBlockCorpus() as $blocks) {
        $walk($blocks);
    }

    // Guard the guard: an empty walk would pass vacuously.
    expect($checked)->toBeGreaterThan(300);
});

it('maps each supported mark to the element the renderer emits', function (): void {
    expect(InlineSpans::toHtml([
        ['text' => 'plain '],
        ['text' => 'bold', 'bold' => true],
        ['text' => 'italic', 'italic' => true],
        ['text' => 'accent', 'emphasis' => true],
        ['text' => 'link', 'url' => '/va-disability/'],
    ]))->toBe('<p>plain <strong>bold</strong><em>italic</em><span>accent</span><a href="/va-disability/">link</a></p>');
});

it('escapes markup an editor pastes rather than executing it', function (): void {
    $spans = [['text' => 'Tom & Jerry <script>alert("x")</script> "quoted"']];

    expect(InlineSpans::toHtml($spans))->not->toContain('<script>')
        ->and(InlineSpans::fromHtml(InlineSpans::toHtml($spans)))->toBe($spans);
});

it('survives the punctuation these pages are full of', function (): void {
    $spans = [
        ['text' => "Aid & Attendance — 90 days' service · 24 months"],
        ['text' => 'VA.gov — Pension', 'url' => 'https://www.va.gov/pension/eligibility/?a=1&b=2'],
    ];

    expect(InlineSpans::fromHtml(InlineSpans::toHtml($spans)))->toBe($spans);
});

it('keeps a soft line break inside a run', function (): void {
    $spans = [['text' => "First line\nSecond line"]];

    expect(InlineSpans::toHtml($spans))->toContain('<br>')
        ->and(InlineSpans::fromHtml(InlineSpans::toHtml($spans)))->toBe($spans);
});

it('round-trips combined marks', function (): void {
    $spans = [['text' => 'strong link', 'bold' => true, 'url' => 'https://example.com/']];

    expect(InlineSpans::fromHtml(InlineSpans::toHtml($spans)))->toBe($spans);
});

it('merges the fragments a rich editor splits a run into', function (): void {
    expect(InlineSpans::fromHtml('<p>one <strong>two</strong><strong> three</strong> four</p>'))->toBe([
        ['text' => 'one '],
        ['text' => 'two three', 'bold' => true],
        ['text' => ' four'],
    ]);
});

it('reads the tags a paste from a word processor brings in', function (): void {
    expect(InlineSpans::fromHtml('<p><b>bold</b> and <i>italic</i></p>'))->toBe([
        ['text' => 'bold', 'bold' => true],
        ['text' => ' and '],
        ['text' => 'italic', 'italic' => true],
    ]);
});

it('flattens unsupported formatting to text instead of dropping the words', function (): void {
    expect(InlineSpans::fromHtml('<p><s><u>struck and underlined</u></s></p>'))
        ->toBe([['text' => 'struck and underlined']]);
});

it('treats an empty editor as no spans at all', function (string $html): void {
    expect(InlineSpans::fromHtml($html))->toBe([]);
})->with(['', '   ', '<p></p>']);

it('drops an anchor with no href rather than inventing one', function (): void {
    expect(InlineSpans::fromHtml('<p><a>no href</a></p>'))->toBe([['text' => 'no href']]);
});
