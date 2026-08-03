<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Pages\GenerateAirShowPagesAction;
use App\Domain\Shared\Models\Faq;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchAirShow(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

function airShowRenderSetup(): void
{
    User::factory()->create([
        'slug' => 't-alford', 'name' => 'T Madden Alford',
        'job_title' => 'Editor, NavyWeek.org', 'credentials' => 'USNA 02',
        'avatar_path' => '/authors/t-alford.jpg',
    ]);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera', 'credentials' => 'USNA 04']);
}

it('renders an air-show guide with the full JSON-LD graph incl. Event', function () {
    airShowRenderSetup();
    $show = AirShow::factory()->create([
        'slug' => 'miramar', 'short_name' => 'MCAS Miramar', 'name' => 'MCAS Miramar',
        'city' => 'San Diego', 'state_name' => 'California', 'headliner' => 'Blue Angels',
        'h1' => 'MCAS Miramar Air Show 2026', 'meta_title' => 'MCAS Miramar Air Show 2026 | NavyWeek.org',
        'schema_name' => 'MCAS Miramar Air Show 2026',
    ]);
    Faq::factory()->for($show, 'faqable')->create(['question' => 'Is parking free?', 'answer' => 'Yes.']);
    AirShowHubMeta::factory()->create();
    app(GenerateAirShowPagesAction::class)();

    $res = fetchAirShow('/air-show/miramar/')->assertOk();

    $res->assertSee('MCAS Miramar Air Show 2026')
        ->assertSee('Is parking free?');

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Air Shows"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@id":"https://www.navyweek.org/air-show/miramar/#webpage"', false)
        ->assertSee('"@type":"Person"', false)
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        ->assertSee('"military air shows"', false)          // author knowsAbout
        ->assertSee('"San Diego air show"', false)
        ->assertSee('"@id":"https://www.navyweek.org/air-show/miramar/#reviewer"', false)
        ->assertSee('"@type":"FAQPage"', false)
        // Event node (published, confirmed, no override)
        ->assertSee('"@type":"Event"', false)
        ->assertSee('"name":"MCAS Miramar Air Show 2026"', false)
        ->assertSee('"@type":"PerformingGroup","name":"Blue Angels"', false)
        ->assertSee('"@type":"Organization","name":"The base"', false)
        ->assertSee('"isAccessibleForFree":true', false);
});

it('suppresses the Event node for a canonical-router show', function () {
    airShowRenderSetup();
    AirShow::factory()->create(['slug' => 'miramar', 'short_name' => 'Miramar']);
    AirShow::factory()->router('/air-show/miramar/')->create(['slug' => 'san-diego', 'short_name' => 'San Diego']);
    AirShowHubMeta::factory()->create();
    app(GenerateAirShowPagesAction::class)();

    fetchAirShow('/air-show/san-diego/')
        ->assertOk()
        ->assertSee('"@type":"Article"', false)   // still a guide
        ->assertDontSee('"@type":"Event"', false); // but no Event
});

it('suppresses the Event node for a date-unconfirmed show', function () {
    airShowRenderSetup();
    AirShow::factory()->unconfirmed()->create(['slug' => 'tbd-show', 'short_name' => 'TBD']);
    AirShowHubMeta::factory()->create();
    app(GenerateAirShowPagesAction::class)();

    fetchAirShow('/air-show/tbd-show/')
        ->assertOk()
        ->assertSee('"@type":"Article"', false)    // still a guide
        ->assertDontSee('"@type":"Event"', false); // date unconfirmed → no Event
});

it('renders the air-show hub with an ItemList of published shows', function () {
    airShowRenderSetup();
    AirShow::factory()->create(['slug' => 'miramar', 'name' => 'MCAS Miramar', 'year' => 2026]);
    AirShow::factory()->unpublished()->create(['slug' => 'draft']);
    AirShowHubMeta::factory()->create(['seo_headline' => '2026 U.S. Military Air Shows']);
    app(GenerateAirShowPagesAction::class)();

    $res = fetchAirShow('/air-show/')->assertOk();

    $res->assertSee('Air Shows')
        ->assertSee('/air-show/miramar/', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"name":"MCAS Miramar 2026"', false)
        ->assertSee('"numberOfItems":1', false)   // only the published show
        ->assertDontSee('/air-show/draft/', false);
});
