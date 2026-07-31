<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Pages\GenerateFleetWeekPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;

function fleetWeekEditorial(): void
{
    User::factory()->create(['slug' => 't-alford', 'name' => 'T Madden Alford']);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera']);
}

it('generates a page per city + the hub (with seeded FAQs)', function () {
    fleetWeekEditorial();
    FleetWeek::factory()->create(['slug' => 'san-francisco', 'city' => 'San Francisco']);
    FleetWeek::factory()->tierThree()->create(['slug' => 'chicago', 'city' => 'Chicago']);

    $count = app(GenerateFleetWeekPagesAction::class)();

    expect($count)->toBe(3); // 2 cities + hub

    $sf = Page::query()->where('url_path', '/fleetweek/san-francisco/')->firstOrFail();
    expect($sf->page_type)->toBe(PageType::FleetWeek)
        ->and($sf->pageable)->toBeInstanceOf(FleetWeek::class)
        ->and($sf->author_id)->not->toBeNull();

    $hub = Page::query()->where('url_path', '/fleetweek/')->firstOrFail();
    expect($hub->page_type)->toBe(PageType::FleetWeek)
        ->and($hub->pageable)->toBeNull()
        ->and($hub->faqs()->count())->toBe(4);   // HUB_FAQS seeded
});

it('is idempotent — re-running keeps 3 pages and does not duplicate hub FAQs', function () {
    fleetWeekEditorial();
    FleetWeek::factory()->create(['slug' => 'san-francisco']);
    FleetWeek::factory()->create(['slug' => 'new-york']);

    app(GenerateFleetWeekPagesAction::class)();
    app(GenerateFleetWeekPagesAction::class)();

    expect(Page::query()->where('page_type', PageType::FleetWeek)->count())->toBe(3);

    $hub = Page::query()->where('url_path', '/fleetweek/')->firstOrFail();
    expect($hub->faqs()->count())->toBe(4); // replaceFaqs, not append
});
