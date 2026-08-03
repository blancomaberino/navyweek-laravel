<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Publishing\Pages\GenerateHomePageAction;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchHome(string $path = '/'): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('renders the home landing at / with the schedule, key facts, and FAQ', function () {
    NavyWeekEvent::factory()->create([
        'sequence' => 1, 'slug' => 'mcallen', 'city' => 'McAllen', 'state_abbr' => 'TX',
        'anchor_event' => 'Citrus Fiesta', 'status' => NavyWeekStatus::Upcoming, 'first_time' => true,
    ]);
    app(GenerateHomePageAction::class)();

    $res = fetchHome()->assertOk();

    $res->assertSee('Navy Week 2026')
        ->assertSee('Key Facts')
        ->assertSee('McAllen, TX')            // schedule card
        ->assertSee('Citrus Fiesta')          // anchor event
        ->assertSee('What is Navy Week?');    // FAQ
});

it('emits the full home JSON-LD graph', function () {
    NavyWeekEvent::factory()->create([
        'sequence' => 1, 'slug' => 'mcallen', 'city' => 'McAllen', 'status' => NavyWeekStatus::Upcoming,
    ]);
    app(GenerateHomePageAction::class)();

    $res = fetchHome()->assertOk();

    $res->assertSee('"@type":"Organization"', false)          // prepended by SeoHead
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#website"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"GovernmentOrganization"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#us-navy"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#navco"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"name":"Navy Week 2026 Schedule"', false)
        // City ItemList URL built via PagePaths (default /city/ prefix).
        ->assertSee('"url":"https://www.navyweek.org/city/mcallen/"', false)
        ->assertSee('"name":"Navy Week McAllen 2026"', false)
        ->assertSee('"@type":"FAQPage"', false);
});

it('shows "Happening Now" for an active stop and "Next Stop" otherwise', function () {
    NavyWeekEvent::factory()->create([
        'sequence' => 1, 'slug' => 'billings', 'city' => 'Billings', 'status' => NavyWeekStatus::Active,
    ]);
    app(GenerateHomePageAction::class)();

    fetchHome()->assertOk()
        ->assertSee('Happening Now')
        ->assertSee('Live This Week');
});

it('shows "Next Stop" when no stop is active', function () {
    NavyWeekEvent::factory()->create([
        'sequence' => 1, 'slug' => 'flagstaff', 'city' => 'Flagstaff', 'status' => NavyWeekStatus::Upcoming,
    ]);
    app(GenerateHomePageAction::class)();

    fetchHome()->assertOk()
        ->assertSee('Next Stop')
        ->assertDontSee('Happening Now');
});

it('counts first-time locations in the key facts', function () {
    NavyWeekEvent::factory()->create(['sequence' => 1, 'slug' => 'a', 'first_time' => true]);
    NavyWeekEvent::factory()->create(['sequence' => 2, 'slug' => 'b', 'first_time' => false, 'first_time_location' => true]);
    NavyWeekEvent::factory()->create(['sequence' => 3, 'slug' => 'c', 'first_time' => false, 'first_time_location' => false]);
    app(GenerateHomePageAction::class)();

    // 3 host cities, 2 first-time (full host + first-time-location).
    fetchHome()->assertOk()->assertSee('3 cities (2 first-time locations)');
});
