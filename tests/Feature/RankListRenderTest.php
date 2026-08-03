<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\RatingCommunity;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Pages\GenerateRankPagesAction;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/** Render a rank/rating list page through the full HTTP stack. */
function fetchRankList(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('renders /navy-ranks/ with paygrade-ordered sections and the ItemList JSON-LD', function () {
    // Commissioned ranks in scrambled DB order, with a pair whose numeric and lexical
    // orders DIVERGE (O-2 vs O-10: lexically "O-10" < "O-2", numerically 2 < 10) so the
    // test fails if the ordering ever regresses to a lexical `paygrade` sort.
    Rank::factory()->create(['slug' => 'admiral', 'name' => 'Admiral', 'abbreviation' => 'ADM', 'paygrade' => 'O-10']);
    Rank::factory()->create(['slug' => 'ensign', 'name' => 'Ensign', 'abbreviation' => 'ENS', 'paygrade' => 'O-1']);
    Rank::factory()->create(['slug' => 'lieutenant-jg', 'name' => 'Lieutenant Junior Grade', 'abbreviation' => 'LTJG', 'paygrade' => 'O-2']);
    Rank::factory()->warrant()->create(['slug' => 'cwo2', 'name' => 'Chief Warrant Officer 2', 'paygrade' => 'W-2']);
    Rank::factory()->enlisted()->create(['slug' => 'chief', 'name' => 'Chief Petty Officer', 'paygrade' => 'E-7']);
    app(GenerateRankPagesAction::class)();

    $res = fetchRankList('/navy-ranks/')->assertOk();

    // Body: hero + section headings + a couple of anchored rows.
    $res->assertSee('Navy Ranks')
        ->assertSee('Commissioned Officers')
        ->assertSee('Enlisted Paygrades')
        ->assertSee('id="ensign"', false)
        ->assertSee('Chief Petty Officer');

    // JSON-LD graph: Organization + Breadcrumb + Article + two ItemLists.
    // (SeoHead emits JSON with JSON_UNESCAPED_SLASHES, so slashes are literal.)
    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Navy Ranks"', false)
        ->assertSee('"headline":"U.S. Navy Ranks — Every Officer & Enlisted Rank Listed"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"name":"U.S. Navy Officer Ranks"', false)
        ->assertSee('"name":"U.S. Navy Enlisted Paygrades"', false)
        ->assertSee('"position":1,"url":"https://www.navyweek.org/navy-ranks/#ensign","name":"Ensign (O-1)"', false)
        ->assertSee('"url":"https://www.navyweek.org/navy-ranks/#admiral","name":"Admiral (O-10)"', false)
        // The full anchor URLs appear only in the JSON-LD (the body uses id="…"), so
        // this pins the ItemList to true numeric order: O-2 before O-10.
        ->assertSeeInOrder([
            '"url":"https://www.navyweek.org/navy-ranks/#lieutenant-jg"',
            '"url":"https://www.navyweek.org/navy-ranks/#admiral"',
        ], false)
        // The officer ItemList concatenates commissioned then warrant.
        ->assertSee('/navy-ranks/#cwo2', false)
        ->assertDontSee('"@type":"FAQPage"', false); // list pages emit no FAQPage
});

it('renders /navy-ratings/ grouped by community with a historic section and two ItemLists', function () {
    Rank::factory()->ratingActive()->create([
        'slug' => 'gunners-mate', 'name' => "Gunner's Mate", 'abbreviation' => 'GM',
        'rating_community' => RatingCommunity::General,
    ]);
    Rank::factory()->ratingActive()->create([
        'slug' => 'aviation-machinist', 'name' => "Aviation Machinist's Mate", 'abbreviation' => 'AD',
        'rating_community' => RatingCommunity::Aviation,
    ]);
    Rank::factory()->ratingHistorical()->create([
        'slug' => 'boilerman', 'name' => 'Boilerman', 'abbreviation' => 'BT', 'decommissioned_year' => 1996,
    ]);
    Rank::factory()->ratingHistorical()->create([
        'slug' => 'torpedoman', 'name' => 'Torpedoman', 'abbreviation' => 'TM', 'decommissioned_year' => 1990,
    ]);
    app(GenerateRankPagesAction::class)();

    $res = fetchRankList('/navy-ratings/')->assertOk();

    $res->assertSee('Navy Ratings')
        ->assertSee('id="community-general"', false)
        ->assertSee('id="community-aviation"', false)
        ->assertSee('id="historic"', false)
        ->assertSee("Gunner's Mate")
        ->assertSee('Ret. 1996')
        // Historic section ordered most-recently-decommissioned first (1996 before 1990).
        ->assertSeeInOrder(['id="boilerman"', 'id="torpedoman"'], false);

    $res->assertSee('"name":"U.S. Navy Active Enlisted Ratings"', false)
        ->assertSee('"name":"U.S. Navy Historic Enlisted Ratings"', false)
        ->assertSee('/navy-ratings/#gunners-mate', false)
        ->assertDontSee('"@type":"FAQPage"', false); // list pages emit no FAQPage
});

it('keeps a null-community active rating visible (HTML) and counted (JSON-LD) — no drift', function () {
    Rank::factory()->ratingActive()->create([
        'slug' => 'gunners-mate', 'name' => "Gunner's Mate", 'abbreviation' => 'GM',
        'rating_community' => RatingCommunity::General,
    ]);
    // An active rating with no community must not silently vanish from the body while
    // remaining in the ItemList + count (the three surfaces must agree).
    Rank::factory()->ratingActive()->create([
        'slug' => 'orphan-rating', 'name' => 'Orphan Rating', 'abbreviation' => 'OR',
        'rating_community' => null,
    ]);
    app(GenerateRankPagesAction::class)();

    fetchRankList('/navy-ratings/')
        ->assertOk()
        ->assertSee('Other')                              // fallback section heading
        ->assertSee('id="orphan-rating"', false)          // rendered in the body
        ->assertSee('/navy-ratings/#orphan-rating', false) // present in the JSON-LD ItemList
        ->assertSee('"numberOfItems":2', false);          // both active ratings counted
});
