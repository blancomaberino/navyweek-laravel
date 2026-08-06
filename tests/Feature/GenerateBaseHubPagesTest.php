<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Pages\GenerateBaseHubPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

function seedHubBases(): void
{
    Base::factory()->create(['slug' => 'ns-norfolk', 'name' => 'Naval Station Norfolk', 'state' => 'virginia', 'state_name' => 'Virginia', 'region_type' => 'state', 'country' => null, 'country_slug' => null]);
    Base::factory()->create(['slug' => 'yokosuka', 'name' => 'Fleet Activities Yokosuka', 'state' => null, 'state_name' => null, 'region_type' => 'country', 'country' => 'Japan', 'country_slug' => 'japan']);
}

it('generates the root, overseas, state and country hubs', function () {
    seedHubBases();

    // 1 root + 1 overseas + 1 state (virginia) + 1 country (japan)
    expect(app(GenerateBaseHubPagesAction::class)())->toBe(4);

    $root = Page::query()->where('generation_key', 'base-hub')->firstOrFail();
    expect($root->page_type)->toBe(PageType::BaseHub)
        ->and($root->url_path)->toBe('/navy-bases/')
        ->and($root->h1)->toBe('NAVY BASES DIRECTORY')
        ->and($root->key_facts['title'] ?? null)->toBe('U.S. Navy Bases — Key Facts')
        ->and($root->faqs()->count())->toBe(5);

    expect(Page::query()->where('generation_key', 'base-hub:overseas')->value('url_path'))->toBe('/navy-bases/overseas/');
    expect(Page::query()->where('generation_key', 'base-hub:state:virginia')->value('url_path'))->toBe('/navy-bases/virginia/');

    $japan = Page::query()->where('generation_key', 'base-hub:country:japan')->firstOrFail();
    expect($japan->page_type)->toBe(PageType::BaseCountryHub)
        ->and($japan->url_path)->toBe('/navy-bases/japan/')
        ->and($japan->h1)->toBe('NAVY BASES IN JAPAN')
        ->and($japan->faqs()->count())->toBe(4);
});

it('only builds hubs for regions that actually have a base', function () {
    seedHubBases();
    app(GenerateBaseHubPagesAction::class)();

    // No base in California, so no California hub is generated.
    expect(Page::query()->where('generation_key', 'base-hub:state:california')->exists())->toBeFalse();
});

it('is idempotent and does not duplicate FAQs on a re-run', function () {
    seedHubBases();
    app(GenerateBaseHubPagesAction::class)();
    app(GenerateBaseHubPagesAction::class)();

    expect(Page::query()->whereIn('page_type', [
        PageType::BaseHub, PageType::BaseOverseasHub, PageType::BaseStateHub, PageType::BaseCountryHub,
    ])->count())->toBe(4)
        ->and(Page::query()->where('generation_key', 'base-hub')->firstOrFail()->faqs()->count())->toBe(5);
});
