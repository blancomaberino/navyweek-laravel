<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Pages\GenerateDiscountCategoryPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

function seedCategory(string $slug = 'hotels-military-veteran'): DiscountCategory
{
    return DiscountCategory::query()->create([
        'slug' => $slug,
        'name' => 'Hotels',
        'match_category' => 'hotels',
        'meta_title' => 'Hotel Military Discounts | NavyWeek.org',
        'meta_description' => 'Military hotel discounts.',
        'h1' => 'HOTEL MILITARY DISCOUNTS',
        'hero_tagline' => 'Verified hotel discounts for the military community.',
        'intro' => 'How the military community saves on hotels.',
        'pinned' => [],
        'excluded' => [],
        'order' => [],
        'og_image' => '/og/discount/hotels.png',
        'last_verified' => '2026-07-20',
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
    ]);
}

it('generates a hub page per category, keyed on a stable generation key', function () {
    seedCategory();

    expect(app(GenerateDiscountCategoryPagesAction::class)())->toBe(1);

    $page = Page::query()->where('generation_key', 'discount-category:hotels-military-veteran')->firstOrFail();
    expect($page->page_type)->toBe(PageType::DiscountCategoryHub)
        ->and($page->url_path)->toBe('/discount/hotels-military-veteran/')
        ->and($page->h1)->toBe('HOTEL MILITARY DISCOUNTS')
        ->and($page->title)->toBe('Hotel Military Discounts | NavyWeek.org')
        ->and($page->is_published)->toBeTrue()
        // the trust chrome columns are populated so the editorial policy renders
        ->and($page->editorial_source_priority)->not->toBeNull()
        ->and($page->pageable)->toBeInstanceOf(DiscountCategory::class);
});

it('is idempotent and preserves an editor-renamed url_path', function () {
    seedCategory();
    app(GenerateDiscountCategoryPagesAction::class)();

    Page::query()->where('generation_key', 'discount-category:hotels-military-veteran')
        ->update(['url_path' => '/discount/hoteis/', 'url_path_is_custom' => true]);

    app(GenerateDiscountCategoryPagesAction::class)();

    expect(Page::query()->where('page_type', PageType::DiscountCategoryHub)->count())->toBe(1)
        ->and(Page::query()->where('generation_key', 'discount-category:hotels-military-veteran')->value('url_path'))
        ->toBe('/discount/hoteis/');
});
