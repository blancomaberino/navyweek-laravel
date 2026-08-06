<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Navigation\Support\ChromeCatalog;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

/**
 * `deals()` and `menuDeals()` are two DELIBERATELY different orderings of the same
 * rows — DealsSection.tsx sorts a copy by datePublished, Header.tsx maps the
 * registry as-is. The fixture below makes them provably different lists, so
 * collapsing one into the other (or dropping the stable tie-break) fails here
 * instead of silently reordering every page on the site.
 */
function brandPage(string $brand, string $slug, string $published, int $order): Page
{
    $connection = Connection::factory()->create(['brand' => $brand, 'slug' => $slug]);
    $offer = Offer::factory()->create(['connection_id' => $connection->id, 'sort_order' => $order]);

    $page = Page::factory()->create([
        'page_type' => PageType::DiscountBrand,
        'url_path' => "/discount/{$slug}/",
        'slug' => $slug,
        'is_published' => true,
        'date_published' => $published,
    ]);
    $page->pageable()->associate($offer)->save();

    return $page;
}

it('orders the Deals SECTION newest-published first, with registry order as the tie-break', function () {
    // Bravo and Charlie share a date: the tie-break must keep registry order.
    brandPage('Alpha', 'alpha', '2026-01-01', 2);
    brandPage('Bravo', 'bravo', '2026-06-01', 1);
    brandPage('Charlie', 'charlie', '2026-06-01', 0);

    $brands = array_column(app(ChromeCatalog::class)->deals(), 'brand');

    expect($brands)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('orders the header MENU by registry position only, ignoring publish date', function () {
    brandPage('Alpha', 'alpha', '2026-01-01', 2);
    brandPage('Bravo', 'bravo', '2026-06-01', 1);
    brandPage('Charlie', 'charlie', '2026-06-01', 0);

    $brands = array_column(app(ChromeCatalog::class)->menuDeals(), 'brand');

    expect($brands)->toBe(['Charlie', 'Bravo', 'Alpha']);
});

it('produces a genuinely different list from deals() when date and registry disagree', function () {
    // Alpha is newest but LAST in the registry — the two orderings must diverge.
    brandPage('Alpha', 'alpha', '2026-09-01', 9);
    brandPage('Bravo', 'bravo', '2026-01-01', 0);

    $catalog = app(ChromeCatalog::class);

    expect(array_column($catalog->deals(), 'brand'))->toBe(['Alpha', 'Bravo'])
        ->and(array_column($catalog->menuDeals(), 'brand'))->toBe(['Bravo', 'Alpha']);
});

/**
 * The legacy header matches a nav item's SLUG against an `activePage` each page
 * view passes in — never the current path. Matching on path equality left every
 * DETAIL page on the site with no active tab. These are the paths verified
 * against the live header.
 */
it('lights the family tab for a detail page, not just an exact path match', function (string $path, ?string $expected) {
    expect(app(ChromeCatalog::class)->activePage($path))->toBe($expected);
})->with([
    'city guide lights Schedule' => ['/city/honolulu-hilo/', 'schedule'],
    'schedule itself' => ['/schedule/', 'schedule'],
    'brand guide lights Deals' => ['/discount/yeti-military-veteran/', 'discount'],
    'discount hub' => ['/discount/', 'discount'],
    'local discount tree lights Deals' => ['/discounts/texas/houston/', 'discount'],
    'credit-cards guide lights Deals' => ['/best-credit-cards-for-military/', 'discount'],
    'reference pages light nothing' => ['/navy-ranks/', null],
    'home lights nothing' => ['/', null],
    'contact lights nothing (Contact.tsx passes no activePage)' => ['/contact/', null],
]);
