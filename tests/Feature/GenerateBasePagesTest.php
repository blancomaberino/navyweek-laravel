<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Pages\GenerateBasePagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

it('generates one published page per base, pointed at the base', function () {
    $base = Base::factory()->create(['slug' => 'naval-station-norfolk']);

    $count = app(GenerateBasePagesAction::class)();

    expect($count)->toBe(1);

    $page = Page::query()->where('url_path', '/navy-bases/naval-station-norfolk/')->firstOrFail();

    expect($page->page_type)->toBe(PageType::Base)
        ->and($page->is_published)->toBeTrue()
        ->and($page->slug)->toBe('naval-station-norfolk')
        ->and($page->title)->toBe($base->meta_title)
        ->and($page->meta_description)->toBe($base->meta_description)
        ->and($page->og_image_path)->toBe('/og/bases/naval-station-norfolk.png')
        ->and($page->pageable->is($base))->toBeTrue()
        ->and($page->date_published->toDateString())->toBe('2026-07-01')
        ->and($page->date_modified->toDateString())->toBe('2026-07-01');
});

it('is idempotent and preserves the original publish date on regeneration (build clock)', function () {
    Base::factory()->create(['slug' => 'nas-oceana']);

    app(GenerateBasePagesAction::class)();

    // Simulate an earlier first-build date already recorded on the page.
    $page = Page::query()->where('url_path', '/navy-bases/nas-oceana/')->firstOrFail();
    $page->forceFill([
        'date_published' => '2025-01-01',
        'date_modified' => '2025-01-01',
    ])->save();

    $count = app(GenerateBasePagesAction::class)();

    expect($count)->toBe(1)
        ->and(Page::query()->where('url_path', '/navy-bases/nas-oceana/')->count())->toBe(1);

    $page->refresh();

    // date_published is preserved verbatim; date_modified is refreshed.
    expect($page->date_published->toDateString())->toBe('2025-01-01')
        ->and($page->date_modified->toDateString())->toBe('2026-07-01');
});
