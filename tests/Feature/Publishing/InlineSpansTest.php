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

it('maps each supported mark to the element the EDITOR gets', function (): void {
    expect(InlineSpans::toHtml([
        ['text' => 'plain '],
        ['text' => 'bold', 'bold' => true],
        ['text' => 'italic', 'italic' => true],
        ['text' => 'accent', 'emphasis' => true],
        ['text' => 'link', 'url' => '/va-disability/'],
    ]))->toBe('<p>plain <strong>bold</strong><em>italic</em><span>accent</span><a href="/va-disability/">link</a></p>');
});

it('renders the page HTML the content view emits, byte for byte', function (): void {
    // This is the method the public pages call. It differs from toHtml() in exactly
    // three ways, all asserted here: no wrapping <p> (the view supplies it), every href
    // through LinkUrl::sanitize(), and the legacy target/rel pair on off-site links.
    expect(InlineSpans::render([
        ['text' => 'plain '],
        ['text' => 'all three', 'bold' => true, 'italic' => true, 'emphasis' => true],
        ['text' => 'onsite', 'url' => '/va-disability/'],
        ['text' => 'offsite', 'url' => 'https://www.va.gov/'],
        ['text' => 'blocked', 'url' => 'javascript:alert(1)'],
    ]))->toBe(
        'plain '
        .'<span><em><strong>all three</strong></em></span>'
        .'<a href="/va-disability/">onsite</a>'
        .'<a href="https://www.va.gov/" target="_blank" rel="noopener noreferrer">offsite</a>'
        .'<a href="#">blocked</a>'
    );
});

it('escapes the page HTML it renders', function (): void {
    expect(InlineSpans::render([['text' => '<script>alert(1)</script> & "quotes"']]))
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('keeps a soft wrap as a break on the page', function (): void {
    // nl2br, not str_replace: the page keeps the source newline beside the <br>.
    expect(InlineSpans::render([['text' => "First\nSecond"]]))->toBe("First<br>\nSecond");
});

it('renders nothing for a blank or malformed run rather than failing', function (): void {
    expect(InlineSpans::render([]))->toBe('')
        // A whitespace-only url is blank — the view this replaced emitted no anchor.
        ->and(InlineSpans::render([['text' => 'x', 'url' => '   ']]))->toBe('x')
        // A run that is not a map must not take the page down.
        ->and(InlineSpans::render([['text' => 'kept'], 'not-a-run']))->toBe('kept');
});

it('reads a block\'s words from either stored shape', function (): void {
    expect(InlineSpans::plainText(['text' => 'flat']))->toBe('flat')
        ->and(InlineSpans::plainText(['spans' => [['text' => 'a '], ['text' => 'b', 'bold' => true]]]))->toBe('a b')
        ->and(InlineSpans::plainText([]))->toBe('')
        ->and(InlineSpans::plainText(['spans' => 'nonsense']))->toBe('');
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

it('keeps word boundaries when a table is pasted in', function (): void {
    // Cells are block boundaries; without that, "Rating" and "Monthly" glue together.
    expect(InlineSpans::fromHtml('<table><tr><td>Rating</td><td>Monthly</td></tr></table>'))
        ->toBe([['text' => "Rating\nMonthly"]]);
});

it('does not double a break when blocks nest', function (): void {
    // TipTap wraps a list item's text in a <p>; one boundary, one break.
    expect(InlineSpans::fromHtml('<ul><li><p>a</p></li><li><p>b</p></li></ul>'))
        ->toBe([['text' => "a\nb"]]);
});

it('merges runs whose marks were discovered in a different order', function (): void {
    // `<strong><a>` and `<a><strong>` are the same run; canonical key order makes them
    // identical on output, so they must not survive as two spans that re-split on the
    // next save.
    expect(InlineSpans::fromHtml(
        '<p><em><strong>a</strong></em><strong><em>b</em></strong></p>'
    ))->toBe([['text' => 'ab', 'bold' => true, 'italic' => true]]);
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
