<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\Audience as AudienceEnum;
use App\Domain\Crm\Models\Audience;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Feed\FeedGenerator;
use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Carbon::setTestNow('2026-07-31T12:00:00Z');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A published discount-brand page over a primary offer with full relations. */
function feedDiscountPage(string $slug, string $brand, array $offerAttrs = [], array $audiences = [AudienceEnum::Military]): Page
{
    $connection = Connection::factory()->create([
        'slug' => $slug,
        'brand' => $brand,
        'category' => 'Outdoors',
        'official_url' => "https://{$slug}.example/mil",
        'brand_home_url' => "https://{$slug}.example",
        'last_verified_at' => '2026-07-01',
    ]);
    $offer = Offer::factory()->create(array_merge([
        'connection_id' => $connection->id,
        'is_primary' => true,
        'is_published' => true,
        'offer_type' => OfferType::Everyday,
        'headline_discount' => '20% off',
        'discount_summary' => "How the military saves at {$brand}.",
    ], $offerAttrs));
    $offer->tiers()->create(['audience' => 'Active duty', 'amount' => '20% off', 'sort_order' => 0]);
    $offer->faqs()->create(['question' => 'Who qualifies?', 'answer' => 'Active duty.', 'sort_order' => 0]);
    $offer->sources()->create(['label' => 'Brand page', 'url' => "https://{$slug}.example/mil", 'publisher' => $brand, 'sort_order' => 0]);
    foreach ($audiences as $a) {
        $offer->audiences()->attach(Audience::factory()->ofKey($a)->create()->id);
    }

    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => $slug,
        'url_path' => "/discount/{$slug}/",
        'title' => "{$brand} Discount",
        'meta_description' => "{$brand} savings.",
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
        'is_published' => true,
        'noindex' => false,
    ]);
    $page->pageable()->associate($offer)->save();

    return $page;
}

it('builds a valid JSON feed with the full envelope and correct totals', function () {
    NavyWeekEvent::factory()->count(2)->create();
    feedDiscountPage('yeti', 'YETI');
    FleetWeek::factory()->create();
    AirShow::factory()->create(['published' => true]);

    $json = json_decode(app(FeedGenerator::class)->build()->json, true);

    expect($json)->toBeArray()
        ->and($json['$schema'])->toBe('https://www.navyweek.org/schemas/navy-week-feed.v1.json')
        ->and($json['feedUrl'])->toBe('https://www.navyweek.org/data/navy-week-2026.json')
        ->and($json)->toHaveKeys(['program', 'methodology', 'totals', 'events', 'faqs', 'discounts', 'fleetWeek', 'jetTeams', 'jetTeamCities', 'airShows']);

    expect($json['totals']['cities'])->toBe(2)
        ->and($json['totals']['discounts'])->toBe(1)
        ->and($json['totals']['fleetWeekCities'])->toBe(1)
        ->and($json['totals']['airShows'])->toBe(1);

    // Static envelope copied verbatim.
    expect($json['program']['operator'])->toBe('Navy Office of Community Outreach (NAVCO)')
        ->and($json['license']['name'])->toBe('CC BY 4.0')
        ->and($json['methodology']['reviewers'])->toHaveCount(2);
});

it('maps a Navy Week event record from the model', function () {
    NavyWeekEvent::factory()->create([
        'slug' => 'san-diego', 'city' => 'San Diego', 'state' => 'California', 'state_abbr' => 'CA',
        'anchor_event' => 'Fleet Week', 'first_time' => true,
    ]);

    $event = json_decode(app(FeedGenerator::class)->build()->json, true)['events'][0];

    expect($event)
        ->name->toBe('Navy Week San Diego 2026')
        ->slug->toBe('san-diego')
        ->stateAbbr->toBe('CA')
        ->firstTimeLocation->toBeTrue()
        // Feed URLs omit the trailing slash, matching the legacy generator verbatim.
        ->url->toBe('https://www.navyweek.org/city/san-diego');
});

it('maps a discount record with provenance, audiences, and build-clock dates', function () {
    feedDiscountPage('advisory-brand', 'AdvisoryBrand', [
        'offer_type' => OfferType::AdvisoryNoDiscount,
        'headline_discount' => 'No military discount',
        'savings_table' => ['rows' => [['path' => 'GovX', 'net' => '10% off']]],
    ], [AudienceEnum::Military, AudienceEnum::Veteran]);

    $feed = json_decode(app(FeedGenerator::class)->build()->json, true);
    $d = $feed['discounts'][0];

    expect($d)
        ->slug->toBe('advisory-brand')
        ->company->toBe('AdvisoryBrand')
        ->documentedNoDiscount->toBeTrue()
        ->hasSavingsAnalysis->toBeTrue()
        ->sourceCount->toBe(1)
        ->datePublished->toBe('2026-06-10')
        ->dateModified->toBe('2026-07-20')
        ->lastVerified->toBe('2026-07-01')
        ->recheckCadenceDays->toBe(45);
    expect($d['audience'])->toBe(['military', 'veteran'])
        ->and($d['tiers'])->toBe([['audience' => 'Active duty', 'amount' => '20% off', 'note' => null]]);

    // The methodology stats aggregate over the discounts.
    expect($feed['methodology']['statistics'])
        ->documentedNoDiscountFindings->toBe(1)
        ->savingsDecisionTables->toBe(1)
        ->primarySourcesCited->toBe(1);
});

it('includes fleet week, jet team, and air show sections', function () {
    FleetWeek::factory()->create(['slug' => 'new-york', 'branding_name' => 'Fleet Week New York', 'year' => 2026]);
    $team = JetTeam::factory()->create(['name' => 'Blue Angels']);
    JetTeamScheduleRow::factory()->create(['jet_team_id' => $team->id, 'slug' => 'el-centro', 'city' => 'El Centro']);
    JetTeamCity::factory()->create(['jet_team_id' => $team->id, 'slug' => 'el-centro', 'city' => 'El Centro', 'published' => true]);
    AirShow::factory()->create(['slug' => 'miramar', 'published' => true]);

    $feed = json_decode(app(FeedGenerator::class)->build()->json, true);

    expect($feed['fleetWeek'][0]['slug'])->toBe('new-york')
        ->and($feed['jetTeams'][0]['name'])->toBe('Blue Angels')
        ->and(collect($feed['jetTeamCities'])->pluck('slug'))->toContain('el-centro')
        ->and($feed['airShows'][0]['slug'])->toBe('miramar');

    // The schedule row for a published city carries the guide URL.
    $row = collect($feed['jetTeams'][0]['schedule'])->firstWhere('city', 'El Centro');
    expect($row['published'])->toBeTrue()
        // No trailing slash on feed URLs, matching the legacy generator.
        ->and($row['url'])->toEndWith('/el-centro');
});

it('renders llms.txt with the host-city count, discount lines, and citation footer', function () {
    NavyWeekEvent::factory()->count(3)->create();
    feedDiscountPage('yeti', 'YETI');
    DiscountCategory::factory()->create(['slug' => 'outdoors', 'name' => 'Outdoor gear']);

    $txt = app(FeedGenerator::class)->build()->llmsTxt;

    expect($txt)
        ->toContain('# NavyWeek.org')
        ->toContain('about 3 host cities')
        ->toContain('the tour visits 3 cities')
        ->toContain('[YETI military & veteran discount')
        ->toContain('[Outdoor gear with military discounts]')
        ->toContain('Source: https://outreach.navy.mil/Navy-Weeks/')
        ->toContain('Last updated: 2026-07-31');
});

it('writes both feed files to public/ via the command', function () {
    $tmp = sys_get_temp_dir().'/nw-feed-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($tmp);
    app()->usePublicPath($tmp);

    NavyWeekEvent::factory()->create();

    $this->artisan('feed:generate')->expectsOutputToContain('llms.txt')->assertSuccessful();

    expect(File::exists("{$tmp}/data/navy-week-2026.json"))->toBeTrue()
        ->and(File::exists("{$tmp}/llms.txt"))->toBeTrue();
    expect(json_decode(File::get("{$tmp}/data/navy-week-2026.json"), true))->toBeArray();

    File::deleteDirectory($tmp);
});
