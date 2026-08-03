<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Pages\GenerateNavyWeekPagesAction;
use App\Domain\Shared\Models\Faq;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchNavyWeek(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('renders a Navy Week city page with the full Event JSON-LD graph', function () {
    $event = NavyWeekEvent::factory()->withCityDetail()->create([
        'slug' => 'houston', 'city' => 'Houston', 'state' => 'Texas', 'state_abbr' => 'TX',
        'anchor_event' => 'Houston Livestock Show',
        'navy_assets' => ['Blue Angels', 'Navy Band Southeast'],
        'lat' => 29.7604, 'lng' => -95.3698,
    ]);
    Faq::factory()->for($event, 'faqable')->create(['question' => 'Is it free?', 'answer' => 'Yes.']);
    app(GenerateNavyWeekPagesAction::class)();

    $res = fetchNavyWeek('/city/houston/')->assertOk();

    $res->assertSee('Houston Navy Week 2026')
        ->assertSee('Opening ceremony')   // daily schedule item
        ->assertSee('Is it free?');

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Schedule"', false)
        ->assertSee('"@type":"GovernmentOrganization"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#us-navy"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#navco"', false)
        ->assertSee('"@type":"Event"', false)
        ->assertSee('"name":"Navy Week Houston 2026"', false)
        // performers regex-derived from navy_assets
        ->assertSee('"name":"U.S. Navy Blue Angels Flight Demonstration Squadron"', false)
        ->assertSee('"name":"U.S. Navy Band Southeast"', false)
        // Offer + subEvent with the matched City Hall venue geo
        ->assertSee('"@type":"Offer"', false)
        ->assertSee('"subEvent"', false)
        ->assertSee('"streetAddress":"1 Main St"', false)
        ->assertSee('"latitude":29.76', false)
        // Parent geo is a JSON number (float cast), not a decimal string.
        ->assertSee('"latitude":29.7604', false)
        ->assertDontSee('"latitude":"29.7604"', false)
        // subEvent organizer is #organization (distinct from the parent Event's #navco).
        ->assertSee('"organizer":{"@id":"https://www.navyweek.org/#organization"}', false)
        ->assertSee('"organizer":{"@id":"https://www.navyweek.org/#navco"}', false)
        ->assertSee('"@type":"FAQPage"', false);
});

it('emits no subEvent for a city with detail but no stored daily schedule (accepted deviation)', function () {
    // The production-majority shape: rich detail (highlights) but daily_schedule = null.
    // The legacy TBA-day synthesis was intentionally not migrated, so no subEvent.
    NavyWeekEvent::factory()->withCityDetail()->create([
        'slug' => 'norfolk', 'city' => 'Norfolk', 'daily_schedule' => null,
    ]);
    app(GenerateNavyWeekPagesAction::class)();

    fetchNavyWeek('/city/norfolk/')
        ->assertOk()
        ->assertSee('"@type":"Event"', false)   // parent Event still emitted
        ->assertDontSee('"subEvent"', false);   // no synthesized TBA subEvents
});

it('falls back to Navy Band + Leap Frogs performers and omits FAQPage when there are no matches/FAQs', function () {
    NavyWeekEvent::factory()->create([  // plain event: navy_assets null, no faqs
        'slug' => 'mcallen', 'city' => 'McAllen',
    ]);
    app(GenerateNavyWeekPagesAction::class)();

    fetchNavyWeek('/city/mcallen/')
        ->assertOk()
        ->assertSee('"name":"U.S. Navy Band"', false)
        ->assertSee('"name":"U.S. Navy Leap Frogs Parachute Team"', false)
        ->assertDontSee('"@type":"FAQPage"', false)   // no FAQs
        ->assertDontSee('"subEvent"', false);         // no daily schedule
});
