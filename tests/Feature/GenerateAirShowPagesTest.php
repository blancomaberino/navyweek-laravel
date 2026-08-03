<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Pages\GenerateAirShowPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;

/** Seed the default editorial byline users so page generation can resolve them. */
function airShowEditorial(): void
{
    User::factory()->create(['slug' => 't-alford', 'name' => 'T Madden Alford']);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera']);
}

it('generates a page per published show (incl. canonical routers) + the hub, skipping unpublished', function () {
    airShowEditorial();
    AirShow::factory()->create(['slug' => 'miramar', 'short_name' => 'Miramar']);
    AirShow::factory()->router('/air-show/miramar/')->create(['slug' => 'san-diego', 'short_name' => 'San Diego']);
    AirShow::factory()->unpublished()->create(['slug' => 'draft-show']);
    AirShowHubMeta::factory()->create();

    $count = app(GenerateAirShowPagesAction::class)();

    expect($count)->toBe(3); // 2 published shows + hub

    $miramar = Page::query()->where('url_path', '/air-show/miramar/')->firstOrFail();
    expect($miramar->page_type)->toBe(PageType::AirShow)
        ->and($miramar->pageable)->toBeInstanceOf(AirShow::class)
        ->and($miramar->is_published)->toBeTrue()
        ->and($miramar->author_id)->not->toBeNull()   // default byline applied
        ->and($miramar->reviewer_id)->not->toBeNull();

    // The disambiguation page exists but canonicalizes to its primary show.
    $sanDiego = Page::query()->where('url_path', '/air-show/san-diego/')->firstOrFail();
    expect($sanDiego->canonical_path)->toBe('/air-show/miramar/');

    // Unpublished show gets no page.
    expect(Page::query()->where('url_path', '/air-show/draft-show/')->exists())->toBeFalse();

    // The hub page points at the hub meta.
    $hub = Page::query()->where('url_path', '/air-show/')->firstOrFail();
    expect($hub->page_type)->toBe(PageType::AirShow)
        ->and($hub->pageable)->toBeInstanceOf(AirShowHubMeta::class);
});
