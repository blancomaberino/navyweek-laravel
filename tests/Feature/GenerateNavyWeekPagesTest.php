<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Pages\GenerateNavyWeekPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

it('generates a page per city event, with derived title + meta description', function () {
    // Short city + anchor so the highlights fit under the 160-char meta-description cap.
    NavyWeekEvent::factory()->withCityDetail()->create([
        'slug' => 'mesa', 'city' => 'Mesa', 'anchor_event' => 'Expo',
    ]);
    NavyWeekEvent::factory()->create(['slug' => 'mcallen', 'city' => 'McAllen']);

    $count = app(GenerateNavyWeekPagesAction::class)();

    expect($count)->toBe(2);

    $mesa = Page::query()->where('url_path', '/city/mesa/')->firstOrFail();
    expect($mesa->page_type)->toBe(PageType::NavyWeekCity)
        ->and($mesa->pageable)->toBeInstanceOf(NavyWeekEvent::class)
        ->and($mesa->is_published)->toBeTrue()
        ->and($mesa->og_image_path)->toBe('/og/mesa.png')
        ->and($mesa->title)->toBe('Mesa Navy Week 2026: Dates, Schedule, Events & Expo | NavyWeek.org')
        ->and($mesa->meta_description)->toContain('Mesa Navy Week 2026 runs')
        ->and($mesa->meta_description)->toContain('Free events: Parade, Ship tours'); // highlights fit
});

it('is idempotent — re-running keeps one page per city', function () {
    NavyWeekEvent::factory()->create(['slug' => 'houston']);

    app(GenerateNavyWeekPagesAction::class)();
    app(GenerateNavyWeekPagesAction::class)();

    expect(Page::query()->where('page_type', PageType::NavyWeekCity)->count())->toBe(1);
});
