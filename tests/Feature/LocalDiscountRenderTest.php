<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\LocalStore;
use App\Domain\Catalog\Models\LocalStoreHours;
use App\Domain\Catalog\Pages\GenerateLocalDiscountPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\LocalDiscountSchema;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/** The default editorial byline users (slugs match config('site.editorial.*')). */
function localEditorialTeam(): void
{
    User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford', 'credentials' => "USNA '02"]);
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera', 'credentials' => "USNA '04 · EOD"]);
}

/** A local discount with one store (sort_order 0) + hours. */
function localWithStore(array $attrs = []): LocalDiscount
{
    $ld = LocalDiscount::factory()->create(array_merge([
        'state' => 'texas', 'state_name' => 'Texas', 'state_abbr' => 'TX',
        'city' => 'houston', 'city_name' => 'Houston',
        'business_slug' => 'houston-zoo', 'company' => 'Houston Zoo',
        'business_type' => 'Zoo', 'price_range' => '$$', 'service_area' => 'Greater Houston',
        'official_url' => 'https://houstonzoo.example/military',
    ], $attrs));
    $store = LocalStore::factory()->create([
        'local_discount_id' => $ld->id, 'sort_order' => 0,
        'street' => '6200 Hermann Park Dr', 'city' => 'Houston', 'state_abbr' => 'TX', 'zip' => '77030',
        'phone' => '713-533-6500', 'lat' => '29.7150', 'lng' => '-95.3900',
    ]);
    LocalStoreHours::factory()->create([
        'local_store_id' => $store->id, 'days' => 'Mon–Fri',
        'day_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'opens' => '09:00', 'closes' => '17:00',
    ]);
    $ld->faqs()->create(['question' => 'Who qualifies?', 'answer' => 'Active duty and veterans.', 'sort_order' => 0]);

    return $ld->fresh();
}

function localFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('builds the local-discount JSON-LD graph with a LocalBusiness node', function () {
    localEditorialTeam();
    $ld = localWithStore();
    $page = Page::create([
        'page_type' => PageType::LocalDiscount, 'slug' => 'houston-zoo',
        'url_path' => '/discounts/texas/houston/houston-zoo/',
        'title' => 'Houston Zoo Military Discount', 'meta_description' => 'Houston Zoo military savings.',
        'date_published' => '2026-06-10', 'date_modified' => '2026-07-20',
        'is_published' => true, 'noindex' => false,
        'author_id' => User::query()->where('slug', 't-alford')->value('id'),
        'reviewer_id' => User::query()->where('slug', 'erik-rivera')->value('id'),
    ]);
    $page->pageable()->associate($ld)->save();

    $types = array_map(fn (array $n): string => $n['@type'], LocalDiscountSchema::build($page->fresh(), $ld));

    expect($types)->toBe(['BreadcrumbList', 'Article', 'WebSite', 'WebPage', 'LocalBusiness', 'Person', 'Person', 'FAQPage']);

    $business = collect(LocalDiscountSchema::build($page->fresh(), $ld))->firstWhere('@type', 'LocalBusiness');
    expect($business['@id'])->toBe('https://www.navyweek.org/discounts/texas/houston/houston-zoo/#localbusiness')
        ->and($business['additionalType'])->toBe('https://schema.org/Zoo')
        ->and($business['priceRange'])->toBe('$$')
        ->and($business['areaServed'])->toBe('Greater Houston')
        ->and($business['telephone'])->toBe('713-533-6500')
        ->and($business['address']['postalCode'])->toBe('77030')
        ->and($business['geo']['latitude'])->toBe(29.715)
        ->and($business['openingHoursSpecification'][0]['dayOfWeek'])->toContain('Monday')
        ->and($business['openingHoursSpecification'][0]['opens'])->toBe('09:00');
});

it('generates a page per local discount, honoring the build clock', function () {
    localEditorialTeam();
    localWithStore(['business_slug' => 'houston-zoo', 'date_published' => '2026-05-01', 'date_modified' => '2026-05-01']);
    localWithStore(['city' => 'austin', 'city_name' => 'Austin', 'business_slug' => 'austin-cafe', 'company' => 'Austin Cafe']);

    $count = app(GenerateLocalDiscountPagesAction::class)();

    expect($count)->toBe(2);
    $page = Page::query()->where('url_path', '/discounts/texas/houston/houston-zoo/')->firstOrFail();
    expect($page->page_type)->toBe(PageType::LocalDiscount)
        ->and($page->pageable)->toBeInstanceOf(LocalDiscount::class)
        ->and($page->is_published)->toBeTrue()
        ->and($page->author_id)->not->toBeNull()      // default byline applied
        ->and($page->date_published->format('Y-m-d'))->toBe('2026-05-01');

    // Re-run preserves date_published (build clock).
    Page::query()->where('url_path', '/discounts/texas/houston/houston-zoo/')->update(['date_published' => '2020-01-01']);
    app(GenerateLocalDiscountPagesAction::class)();
    expect(Page::query()->where('url_path', '/discounts/texas/houston/houston-zoo/')->firstOrFail()->date_published->format('Y-m-d'))
        ->toBe('2020-01-01');
});

it('renders the detail page with the LocalBusiness + FAQ JSON-LD', function () {
    localEditorialTeam();
    localWithStore();
    app(GenerateLocalDiscountPagesAction::class)();

    $res = localFetch('/discounts/texas/houston/houston-zoo/')->assertOk();

    $res->assertSee('Houston Zoo')
        ->assertSee('6200 Hermann Park Dr')
        ->assertSee('Who qualifies?')
        ->assertSee('not affiliated', false)
        ->assertSee('"@type":"LocalBusiness"', false)
        ->assertSee('"@type":"OpeningHoursSpecification"', false)
        ->assertSee('"@type":"Organization"', false)   // prepended by SeoHead
        ->assertSee('"@type":"FAQPage"', false);
});
