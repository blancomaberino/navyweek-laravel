<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateDiscountIndexPageAction;

/** Create a published discount-brand page (Connection → primary Offer → Page). */
function publishedBrand(string $brand, string $slug): void
{
    $connection = Connection::factory()->create(['brand' => $brand, 'slug' => $slug]);
    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'audience_label' => 'Military & Veteran',
        'is_primary' => true,
        'is_published' => true,
    ]);
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => "{$slug}-military-veteran",
        'url_path' => "/discount/{$slug}-military-veteran/",
        'title' => "{$brand} Discount",
        'is_published' => true,
    ]);
    $page->pageable()->associate($offer)->save();
}

it('generates the /discount/ index with a live brand count in the title and seeded FAQs', function () {
    publishedBrand('YETI', 'yeti');
    publishedBrand('Nike', 'nike');

    $count = app(GenerateDiscountIndexPageAction::class)();

    expect($count)->toBe(1);

    $page = Page::query()->where('url_path', '/discount/')->firstOrFail();
    expect($page->page_type)->toBe(PageType::Static)
        ->and($page->slug)->toBe('discount')
        ->and($page->pageable)->toBeNull()
        ->and($page->og_image_path)->toBe('/og/discount/hub.png')
        ->and($page->title)->toBe('Military & Veteran Discounts Directory — 2+ Brands | NavyWeek.org')
        ->and($page->faqs()->count())->toBe(4);   // HUB_FAQS seeded
});

it('is idempotent — re-running keeps one page and does not duplicate FAQs', function () {
    publishedBrand('YETI', 'yeti');

    app(GenerateDiscountIndexPageAction::class)();
    app(GenerateDiscountIndexPageAction::class)();

    $page = Page::query()->where('url_path', '/discount/')->firstOrFail();
    expect(Page::query()->where('url_path', '/discount/')->count())->toBe(1)
        ->and($page->faqs()->count())->toBe(4)
        // A single brand → no "— N+ Brands" suffix (the count <= 1 branch).
        ->and($page->title)->toBe('Military & Veteran Discounts Directory | NavyWeek.org');
});
