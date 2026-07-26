<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\DiscountCategory;

it('casts intro, ordering overrides and dates', function () {
    $category = DiscountCategory::factory()->create([
        'intro' => ['One.', 'Two.'],
        'pinned' => ['a-slug', 'b-slug'],
        'excluded' => ['x-slug'],
        'order' => null,
        'date_published' => '2026-07-10',
    ]);

    $fresh = $category->fresh();

    expect($fresh->intro)->toBe(['One.', 'Two.'])
        ->and($fresh->pinned)->toBe(['a-slug', 'b-slug'])
        ->and($fresh->excluded)->toBe(['x-slug'])
        ->and($fresh->order)->toBeNull()
        ->and($fresh->date_published->toDateString())->toBe('2026-07-10');
});
