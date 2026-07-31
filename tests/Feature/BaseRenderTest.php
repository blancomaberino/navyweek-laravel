<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Pages\GenerateBasePagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Shared\Models\Faq;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/** Render a base page through the full HTTP stack (middleware + catch-all controller). */
function fetchBase(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

/** A CONUS base with FAQs + its generated page. */
function conusBase(): Base
{
    $base = Base::factory()->create([
        'slug' => 'naval-station-norfolk',
        'name' => 'Naval Station Norfolk',
        'city' => 'Norfolk',
        'state' => 'virginia',
        'state_name' => 'Virginia',
        'state_abbr' => 'VA',
        'meta_title' => 'Naval Station Norfolk Guide | NavyWeek.org',
        'meta_description' => 'The world’s largest naval station.',
        'h1' => 'Naval Station Norfolk',
        'overview' => 'The largest naval base in the world.',
        'established' => 1917,
        'key_facts' => [['label' => 'Homeport of', 'value' => 'the U.S. Atlantic Fleet']],
        'wikipedia_url' => 'https://en.wikipedia.org/wiki/Naval_Station_Norfolk',
        'official_url' => null,
    ]);
    Faq::factory()->for($base, 'faqable')->create([
        'question' => 'Is Naval Station Norfolk open to the public?',
        'answer' => 'Access is restricted; guided tours are available.',
        'sort_order' => 1,
    ]);
    app(GenerateBasePagesAction::class)();

    return $base;
}

it('renders the base body: h1, overview, key facts, and FAQs', function () {
    conusBase();

    fetchBase('/navy-bases/naval-station-norfolk/')
        ->assertOk()
        ->assertSee('Naval Station Norfolk')
        ->assertSee('The largest naval base in the world.')
        ->assertSee('Homeport of')                        // key fact label
        ->assertSee('the U.S. Atlantic Fleet')            // key fact value
        ->assertSee('Is Naval Station Norfolk open to the public?')
        ->assertSee('Navy Bases'); // breadcrumb
});

it('falls through to the minimal shell for a Base page whose pageable is not a Base', function () {
    // A page typed Base but with no Base pageable must hit the shell, not pages.base —
    // this locks in the renderBody() null-arm (type-mismatch) behavior.
    Page::create([
        'page_type' => PageType::Base,
        'slug' => 'orphan-base',
        'url_path' => '/navy-bases/orphan-base/',
        'title' => 'Orphan Base',
        'is_published' => true,
    ]);

    fetchBase('/navy-bases/orphan-base/')
        ->assertOk()
        ->assertDontSee('Navy Bases'); // the base view breadcrumb — absent on the shell
});

it('emits the base JSON-LD graph: Organization + Breadcrumb + Article + Place + GovernmentOrganization + FAQPage', function () {
    conusBase();

    $res = fetchBase('/navy-bases/naval-station-norfolk/')->assertOk();

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Navy Bases"', false)
        ->assertSee('"name":"Virginia"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"Naval Station Norfolk Guide | NavyWeek.org"', false)
        ->assertSee('"datePublished":"2026-07-01"', false)
        ->assertSee('"@type":"Place"', false)
        ->assertSee('"@id":"https://www.navyweek.org/navy-bases/naval-station-norfolk/#place"', false)
        ->assertSee('"additionalType":"https://schema.org/GovernmentBuilding"', false)
        ->assertSee('"addressCountry":"US"', false)
        ->assertSee('"addressRegion":"VA"', false)
        ->assertSee('"@type":"GovernmentOrganization"', false)
        ->assertSee('"@id":"https://www.navyweek.org/navy-bases/naval-station-norfolk/#org"', false)
        ->assertSee('"name":"United States Navy"', false)
        ->assertSee('"foundingDate":"1917"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('Is Naval Station Norfolk open to the public?', false);
});

it('uses overseas breadcrumbs and host-country address for an OCONUS base', function () {
    $base = Base::factory()->overseas()->create([
        'slug' => 'fleet-activities-sasebo',
        'name' => 'Fleet Activities Sasebo',
        'city' => 'Sasebo',
        'county' => 'Nagasaki',
    ]);
    app(GenerateBasePagesAction::class)();

    $res = fetchBase('/navy-bases/fleet-activities-sasebo/')->assertOk();

    $res->assertSee('"name":"Overseas"', false)
        ->assertSee('"item":"https://www.navyweek.org/navy-bases/overseas/"', false)
        ->assertSee('"name":"Japan"', false)
        ->assertSee('"addressCountry":"JP"', false)
        ->assertSee('"addressRegion":"Nagasaki"', false)
        // No FAQs on this base → the FAQPage node is omitted entirely.
        ->assertDontSee('"@type":"FAQPage"', false);
});
