<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateDiscountIndexPageAction;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchDiscountIndex(): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create('http://localhost/discount/'))
    );
}

/** A published discount-brand page with a chosen audience label. */
function indexBrand(string $brand, string $slug, ?string $audience): void
{
    $connection = Connection::factory()->create(['brand' => $brand, 'slug' => $slug]);
    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'audience_label' => $audience,
        'is_primary' => true,
        'is_published' => true,
    ]);
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => $slug,
        'url_path' => "/discount/{$slug}/",
        'title' => "{$brand} Discount",
        'is_published' => true,
    ]);
    $page->pageable()->associate($offer)->save();
}

it('renders the /discount/ directory with the ItemList + FAQPage graph', function () {
    indexBrand('YETI', 'yeti', 'Military & Veteran');
    indexBrand('Merrell', 'merrell', 'First Responder');
    indexBrand('Costco', 'costco', null);   // null audience → default ItemList name
    app(GenerateDiscountIndexPageAction::class)();

    $res = fetchDiscountIndex()->assertOk();

    $res->assertSee('Military &amp; Veteran Discounts Directory', false)   // h1
        ->assertSee('YETI')
        ->assertSee('/discount/yeti/', false);

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Military Discounts"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"Military & Veteran Discounts Directory"', false)
        // The Article description is a distinct hardcoded string, not the meta desc.
        ->assertSee('"description":"A directory of verified military and veteran discounts', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"numberOfItems":3', false)
        ->assertSee('"name":"Merrell First Responder Discount"', false)
        // Costco has no audience label → the default name form.
        ->assertSee('"name":"Costco Military & Veteran Discount"', false)
        ->assertSee('"url":"https://www.navyweek.org/discount/yeti/"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('What military discounts can I get from major brands?', false); // seeded HUB_FAQS
});

it('renders the empty-state directory (no published brands) with numberOfItems 0', function () {
    app(GenerateDiscountIndexPageAction::class)();

    fetchDiscountIndex()
        ->assertOk()
        ->assertSee('Discount guides are coming soon.')   // empty-state copy
        ->assertSee('"numberOfItems":0', false);
});
