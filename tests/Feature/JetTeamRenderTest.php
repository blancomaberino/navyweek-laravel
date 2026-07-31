<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Pillars\Pages\GenerateJetTeamPagesAction;
use App\Domain\Shared\Models\Faq;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function fetchJetTeam(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

function jetTeamRenderSetup(): JetTeam
{
    User::factory()->create([
        'slug' => 't-alford', 'name' => 'T Madden Alford',
        'job_title' => 'Editor, NavyWeek.org', 'credentials' => 'USNA 02',
        'avatar_path' => '/authors/t-alford.jpg',
    ]);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera', 'credentials' => 'USNA 04']);

    return JetTeam::factory()->create([
        'name' => 'Blue Angels', 'full_name' => 'U.S. Navy Blue Angels',
        'branch' => 'U.S. Navy', 'seo_headline' => 'Blue Angels 2026 Schedule',
    ]);
}

it('renders the jet-team hub with a name-only schedule ItemList', function () {
    $team = jetTeamRenderSetup();
    JetTeamScheduleRow::factory()->for($team, 'team')->create([
        'sort_order' => 0, 'slug' => 'anchorage', 'show' => 'Arctic Thunder', 'city' => 'Anchorage',
        'state' => 'Alaska', 'dates_label' => 'Jul 25–26',
    ]);
    // A published city guide for that stop, so the hub links it (vs "Guide coming soon").
    JetTeamCity::factory()->for($team, 'team')->create(['slug' => 'anchorage', 'city' => 'Anchorage']);
    Faq::factory()->for($team, 'faqable')->create(['question' => 'When do they fly?', 'answer' => 'Summer.']);
    app(GenerateJetTeamPagesAction::class)();

    $res = fetchJetTeam('/blue-angels/')->assertOk();

    $res->assertSee('Blue Angels')
        ->assertSee('Arctic Thunder')
        ->assertSee('/blue-angels/anchorage/', false); // published-guide link in the schedule

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"ItemList"', false)
        // ItemList entries are name-only (no url), unlike the other hubs.
        ->assertSee('"name":"Arctic Thunder — Anchorage, Alaska (Jul 25–26)"', false)
        ->assertSee('"@type":"FAQPage"', false);
});

it('renders a jet-team city guide with the full graph incl. a plain Event', function () {
    $team = jetTeamRenderSetup();
    $city = JetTeamCity::factory()->for($team, 'team')->create([
        'slug' => 'anchorage', 'city' => 'Anchorage', 'state' => 'Alaska', 'year' => 2026,
        'show' => 'Arctic Thunder', 'venue' => 'JBER', 'admission' => Admission::Free,
        'h1' => 'Blue Angels Anchorage 2026', 'meta_title' => 'Blue Angels Anchorage 2026 | NavyWeek.org',
    ]);
    Faq::factory()->for($city, 'faqable')->create(['question' => 'Is it free?', 'answer' => 'Yes.']);
    app(GenerateJetTeamPagesAction::class)();

    $res = fetchJetTeam('/blue-angels/anchorage/')->assertOk();

    $res->assertSee('Blue Angels Anchorage 2026')
        ->assertSee('Is it free?');

    $res->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"Blue Angels Anchorage 2026"', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        ->assertSee('"image":"https://www.navyweek.org/authors/t-alford.jpg"', false) // author image
        ->assertSee('"Anchorage air show"', false)          // author knowsAbout
        ->assertSee('"@id":"https://www.navyweek.org/blue-angels/anchorage/#reviewer"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Event"', false)
        ->assertSee('"name":"Blue Angels — Arctic Thunder 2026"', false)
        ->assertSee('"@type":"PerformingGroup","name":"U.S. Navy Blue Angels"', false)
        ->assertSee('"isAccessibleForFree":true', false)
        // plain Event: no offers/organizer/subEvent
        ->assertDontSee('"@type":"Offer"', false);
});

it('falls through to the shell for a city unpublished after generation', function () {
    $team = jetTeamRenderSetup();
    $city = JetTeamCity::factory()->for($team, 'team')->create(['slug' => 'anchorage', 'city' => 'Anchorage']);
    app(GenerateJetTeamPagesAction::class)();

    // Simulate the city being unpublished after its page was generated.
    $city->update(['published' => false]);

    fetchJetTeam('/blue-angels/anchorage/')
        ->assertOk()
        ->assertDontSee('"@type":"Event"', false);   // guide not rendered → shell
});

it('maps a stop status onto the schema.org Event status (and isAccessibleForFree=false for ticketed)', function (JetTeamStatus $status, string $expected) {
    $team = jetTeamRenderSetup();
    JetTeamCity::factory()->for($team, 'team')->create([
        'slug' => 'anchorage', 'city' => 'Anchorage', 'status' => $status, 'admission' => Admission::Ticketed,
    ]);
    app(GenerateJetTeamPagesAction::class)();

    fetchJetTeam('/blue-angels/anchorage/')
        ->assertOk()
        ->assertSee('"eventStatus":"'.$expected.'"', false)
        ->assertSee('"isAccessibleForFree":false', false); // ticketed
})->with([
    [JetTeamStatus::Cancelled, 'https://schema.org/EventCancelled'],
    [JetTeamStatus::Postponed, 'https://schema.org/EventPostponed'],
    [JetTeamStatus::Completed, 'https://schema.org/EventScheduled'],
]);
