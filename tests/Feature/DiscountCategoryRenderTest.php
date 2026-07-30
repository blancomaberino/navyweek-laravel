<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function renderPath(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

/** A brand in a category with a published discount-brand page pointing at its offer. */
function liveBrand(string $brand, string $slug, string $category, ?string $audience = 'Military & Veteran'): Connection
{
    $connection = Connection::factory()->create(['brand' => $brand, 'slug' => $slug, 'category' => $category]);
    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'headline_discount' => '15% off',
        'audience_label' => $audience,
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

    return $connection;
}

function outdoorHub(): Page
{
    $category = DiscountCategory::create([
        'slug' => 'outdoor',
        'name' => 'Outdoor Gear',
        'match_category' => 'outdoor',
        'meta_title' => 'Outdoor Gear Military Discounts',
        'meta_description' => 'Military & veteran discounts on outdoor gear.',
        'h1' => 'Outdoor Gear Military Discounts',
        'hero_tagline' => 'Save on the trail.',
        'intro' => ['The outdoor brands that verify military status.', 'Updated for 2026.'],
        'og_image' => '/og/discount/outdoor.png',
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
        'last_verified' => '2026-07-18',
    ]);

    $page = Page::create([
        'page_type' => PageType::DiscountCategoryHub,
        'slug' => 'outdoor',
        'url_path' => '/discount/outdoor/',
        'title' => 'Outdoor Gear Military Discounts',
        'meta_description' => 'Military & veteran discounts on outdoor gear.',
        'og_image_path' => '/og/discount/outdoor.png',
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
        'is_published' => true,
    ]);
    $page->pageable()->associate($category)->save();

    return $page;
}

it('renders the category hub grid of live brands', function () {
    outdoorHub();
    liveBrand('YETI', 'yeti', 'outdoor');

    $res = renderPath('/discount/outdoor/')->assertOk();

    $res->assertSee('Outdoor Gear Military Discounts', false)   // h1
        ->assertSee('The outdoor brands that verify military status.') // intro
        ->assertSee('YETI')
        ->assertSee('/discount/yeti-military-veteran/', false)   // card links to the live page
        ->assertSee('All military discounts');                  // footer
});

it('emits the category JSON-LD graph: Organization + Breadcrumb + Article + ItemList', function () {
    outdoorHub();
    liveBrand('YETI', 'yeti', 'outdoor');

    $res = renderPath('/discount/outdoor/')->assertOk();

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"Outdoor Gear Military Discounts"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"numberOfItems":1', false)
        ->assertSee('"name":"YETI Military & Veteran Discount"', false)
        ->assertSee('"url":"https://www.navyweek.org/discount/yeti-military-veteran/"', false)
        ->assertSee('"datePublished":"2026-06-10"', false)
        // hubs are org-authored, no FAQPage/WebSite node
        ->assertDontSee('"@type":"FAQPage"', false);
});

it('excludes a same-category brand that has no published page', function () {
    outdoorHub();
    liveBrand('YETI', 'yeti', 'outdoor');
    // In the category but no discount-brand page → must not appear.
    Connection::factory()->create(['brand' => 'GhostBrand', 'slug' => 'ghostbrand', 'category' => 'outdoor']);

    renderPath('/discount/outdoor/')
        ->assertOk()
        ->assertSee('"numberOfItems":1', false)
        ->assertDontSee('GhostBrand');
});

it('renders the empty state when no brand in the category has a live page', function () {
    outdoorHub();
    // A same-category connection but with no published discount-brand page, so the
    // whole grid is empty.
    Connection::factory()->create(['brand' => 'GhostBrand', 'slug' => 'ghostbrand', 'category' => 'outdoor']);

    renderPath('/discount/outdoor/')
        ->assertOk()
        ->assertSee('No brands in this category yet')
        ->assertSee('"numberOfItems":0', false)
        ->assertDontSee('GhostBrand');
});

it('uses the audience-specific ItemList name when the offer has an audience label', function () {
    outdoorHub();
    liveBrand('Merrell', 'merrell', 'outdoor', 'First Responder');

    renderPath('/discount/outdoor/')
        ->assertOk()
        ->assertSee('"name":"Merrell First Responder Discount"', false);
});
