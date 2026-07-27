<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;

it('replaceFaqs and replaceSources replace the set rather than appending', function () {
    $base = Base::factory()->create();

    $base->replaceFaqs([
        ['question' => 'Q1', 'answer' => 'A1', 'sort_order' => 0],
        ['question' => 'Q2', 'answer' => 'A2', 'sort_order' => 1],
    ]);
    $base->replaceSources([
        ['label' => 'S1', 'url' => 'https://example.com/1', 'sort_order' => 0],
    ]);

    expect($base->faqs()->count())->toBe(2)
        ->and($base->sources()->count())->toBe(1);

    // Re-running with a different set replaces (deletes the old), never appends.
    $base->replaceFaqs([
        ['question' => 'Only', 'answer' => 'One', 'sort_order' => 0],
    ]);
    $base->replaceSources([]);

    expect($base->faqs()->count())->toBe(1)
        ->and($base->faqs->first()->question)->toBe('Only')
        ->and($base->sources()->count())->toBe(0);
});
