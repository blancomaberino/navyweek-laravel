<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Pages\GenerateRankPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Carbon;

it('generates the two consolidated list pages with live counts and no pageable', function () {
    Carbon::setTestNow('2026-07-31');

    // 2 commissioned, 1 warrant, 1 enlisted; 3 active + 2 historic ratings.
    Rank::factory()->count(2)->create();                 // officer-commissioned (default)
    Rank::factory()->warrant()->create();
    Rank::factory()->enlisted()->create();
    Rank::factory()->count(3)->ratingActive()->create();
    Rank::factory()->count(2)->ratingHistorical()->create();

    $count = app(GenerateRankPagesAction::class)();

    expect($count)->toBe(2);

    $ranks = Page::query()->where('url_path', '/navy-ranks/')->firstOrFail();
    expect($ranks->page_type)->toBe(PageType::Rank)
        ->and($ranks->is_published)->toBeTrue()
        ->and($ranks->pageable)->toBeNull()
        ->and($ranks->og_image_path)->toBe('/og/ranks/hub.png')
        ->and($ranks->title)->toBe('U.S. Navy Ranks — Every Officer & Enlisted Rank Listed | NavyWeek.org')
        ->and($ranks->meta_description)->toContain('2 commissioned officer')
        ->and($ranks->meta_description)->toContain('1 warrant officer')
        ->and($ranks->meta_description)->toContain('1 enlisted')
        ->and($ranks->date_published->toDateString())->toBe('2026-07-31');

    $ratings = Page::query()->where('url_path', '/navy-ratings/')->firstOrFail();
    expect($ratings->page_type)->toBe(PageType::Rating)
        ->and($ratings->pageable)->toBeNull()
        ->and($ratings->og_image_path)->toBe('/og/default.png')
        ->and($ratings->title)->toBe('U.S. Navy Ratings — All 3 Active Enlisted Ratings Listed | NavyWeek.org')
        ->and($ratings->meta_description)->toContain('all 3 active ratings')
        ->and($ratings->meta_description)->toContain('2 historic');
});

it('is idempotent and honors the build clock on regeneration', function () {
    Rank::factory()->create();

    Carbon::setTestNow('2026-01-01');
    app(GenerateRankPagesAction::class)();

    // A later rebuild must preserve date_published and advance date_modified.
    Carbon::setTestNow('2026-07-31');
    app(GenerateRankPagesAction::class)();

    expect(Page::query()->whereIn('url_path', ['/navy-ranks/', '/navy-ratings/'])->count())->toBe(2);

    $ranks = Page::query()->where('url_path', '/navy-ranks/')->firstOrFail();
    expect($ranks->date_published->toDateString())->toBe('2026-01-01')  // preserved
        ->and($ranks->date_modified->toDateString())->toBe('2026-07-31'); // refreshed
});
