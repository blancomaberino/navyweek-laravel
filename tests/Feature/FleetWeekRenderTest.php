<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Pages\GenerateFleetWeekPagesAction;
use App\Domain\Shared\Models\Faq;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchFleetWeek(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

function fleetWeekRenderSetup(): void
{
    User::factory()->create([
        'slug' => 't-alford', 'name' => 'T Madden Alford',
        'job_title' => 'Editor, NavyWeek.org', 'credentials' => 'USNA 02',
    ]);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera', 'credentials' => 'USNA 04']);
}

it('renders a fleet-week guide with the full JSON-LD graph incl. Festival', function () {
    fleetWeekRenderSetup();
    $week = FleetWeek::factory()->create([
        'slug' => 'san-francisco', 'city' => 'San Francisco', 'branding_name' => 'San Francisco Fleet Week',
        'year' => 2026, 'h1' => 'San Francisco Fleet Week 2026',
        'meta_title' => 'San Francisco Fleet Week 2026 | NavyWeek.org',
    ]);
    Faq::factory()->for($week, 'faqable')->create(['question' => 'When is it?', 'answer' => 'October.']);
    app(GenerateFleetWeekPagesAction::class)();

    $res = fetchFleetWeek('/fleetweek/san-francisco/')->assertOk();

    $res->assertSee('San Francisco Fleet Week 2026')
        ->assertSee('When is it?');

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Fleet Week"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"San Francisco Fleet Week 2026"', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        ->assertSee('"San Francisco Fleet Week"', false)   // author knowsAbout entry
        ->assertSee('"@id":"https://www.navyweek.org/fleetweek/san-francisco/#reviewer"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Festival"', false)
        ->assertSee('"@type":"PerformingGroup","name":"Blue Angels"', false)
        ->assertSee('"@type":"Organization","name":"Fleet Week Association"', false);
});

it('omits the Festival node for a Tier-3 city without an official event', function () {
    fleetWeekRenderSetup();
    FleetWeek::factory()->tierThree()->create(['slug' => 'chicago', 'city' => 'Chicago']);
    app(GenerateFleetWeekPagesAction::class)();

    fetchFleetWeek('/fleetweek/chicago/')
        ->assertOk()
        ->assertSee('"@type":"Article"', false)
        ->assertDontSee('"@type":"Festival"', false);
});

it('renders the fleet-week hub with an ItemList and the hub FAQPage', function () {
    fleetWeekRenderSetup();
    FleetWeek::factory()->create(['slug' => 'san-francisco', 'branding_name' => 'San Francisco Fleet Week', 'year' => 2026]);
    app(GenerateFleetWeekPagesAction::class)();

    $res = fetchFleetWeek('/fleetweek/')->assertOk();

    $res->assertSee('U.S. Fleet Week Guide, City by City')
        ->assertSee('/fleetweek/san-francisco/', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"name":"San Francisco Fleet Week 2026"', false)
        // The hub Article description is a distinct hardcoded string, NOT the meta desc.
        ->assertSee('"description":"A directory of U.S. fleet weeks', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('What is Fleet Week?', false);   // seeded HUB_FAQS
});

it('emits the Festival Place geo + full PostalAddress when the location has them', function () {
    fleetWeekRenderSetup();
    $week = FleetWeek::factory()->create(['slug' => 'san-diego', 'city' => 'San Diego']);
    // Override the festival JSON with a fully-populated location.
    $week->festival = array_merge((array) $week->festival, [
        'location' => [
            'name' => 'Broadway Pier',
            'streetAddress' => '1000 N Harbor Dr',
            'locality' => 'San Diego',
            'region' => 'CA',
            'postalCode' => '92101',
            'lat' => 32.7157,
            'lng' => -117.1730,
        ],
    ]);
    $week->save();
    app(GenerateFleetWeekPagesAction::class)();

    fetchFleetWeek('/fleetweek/san-diego/')
        ->assertOk()
        ->assertSee('"@type":"GeoCoordinates"', false)
        ->assertSee('"latitude":32.7157', false)
        ->assertSee('"streetAddress":"1000 N Harbor Dr"', false)
        ->assertSee('"postalCode":"92101"', false)
        ->assertSee('"addressCountry":"US"', false);
});
