<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateVeteransDayFreeMealsPageAction;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // The default byline users, so upsertPillarPage applies the author + the Person node has data.
    User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford', 'credentials' => "USNA '02"]);
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera', 'credentials' => "USNA '04 · EOD"]);
});

function freeMealsFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('seeds the free-meals page as a Static, slug-dispatched page keyed on generation_key', function () {
    app(GenerateVeteransDayFreeMealsPageAction::class)();

    $page = Page::query()->where('url_path', '/veterans-day/free-meals/')->firstOrFail();
    expect($page->page_type)->toBe(PageType::Static)
        ->and($page->slug)->toBe('veterans-day-free-meals')
        ->and($page->generation_key)->toBe('content:veterans-day-free-meals')
        ->and($page->is_published)->toBeTrue()
        ->and($page->author_id)->not->toBeNull()              // default byline applied
        ->and($page->date_published->format('Y-m-d'))->toBe('2026-06-29');
});

it('is idempotent — a re-run keeps a single page row', function () {
    app(GenerateVeteransDayFreeMealsPageAction::class)();
    app(GenerateVeteransDayFreeMealsPageAction::class)();

    expect(Page::query()->where('slug', 'veterans-day-free-meals')->count())->toBe(1);
});

it('renders only verified meals, with the discount link, badge, and full JSON-LD graph', function () {
    // Two verified (one nationwide with a discount guide, one participating) + one pending (gated out).
    VeteransDayMeal::factory()->create([
        'brand' => 'Applebees', 'slug' => 'applebees', 'nationwide' => false,
        'source_url' => 'https://applebees.com/veterans-day', 'source_label' => 'Applebees official site',
    ]);
    VeteransDayMeal::factory()->create([
        'brand' => 'Texas Roadhouse', 'slug' => 'texas-roadhouse', 'nationwide' => true,
        'discount_slug' => 'texas-roadhouse-military-discount',
        'source_url' => 'https://texasroadhouse.com/veterans-day', 'source_label' => 'Texas Roadhouse official site',
    ]);
    VeteransDayMeal::factory()->pending()->create(['brand' => 'Friendlys', 'slug' => 'friendlys']);

    app(GenerateVeteransDayFreeMealsPageAction::class)();

    $res = freeMealsFetch('/veterans-day/free-meals/')->assertOk();

    $res->assertSee('Veterans Day Free Meals 2026')
        ->assertSee('Showing 2 of 2 verified offers')
        ->assertSee('Applebees')
        ->assertSee('Texas Roadhouse')
        ->assertDontSee('Friendlys')                                       // pending → gated out
        ->assertSee('/discount/texas-roadhouse-military-discount/', false) // internal discount link
        ->assertSee('Participating only')                                  // non-nationwide tag
        ->assertSee('Verified Jun 2026')                                   // verified badge
        // JSON-LD graph (Organization prepended by SeoHead)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"Veterans Day Free Meals 2026: Verified Restaurant Offers"', false)
        ->assertSee('"dateModified":"2026-06-29"', false)                  // freshest verification
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"numberOfItems":2', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('2 verified offers drawn from', false);                // FAQ stat interpolation
});

it('shows the empty state when no meals are verified', function () {
    VeteransDayMeal::factory()->pending()->create(['brand' => 'Pending Diner', 'slug' => 'pending-diner']);

    app(GenerateVeteransDayFreeMealsPageAction::class)();

    freeMealsFetch('/veterans-day/free-meals/')
        ->assertOk()
        ->assertSee('Showing 0 of 0 verified offers')
        ->assertSee('"numberOfItems":0', false);
});
