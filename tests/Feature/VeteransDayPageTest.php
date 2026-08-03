<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateVeteransDayPageAction;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // The default byline users, so the Article author Person node has data.
    User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford', 'credentials' => "USNA '02"]);
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera', 'credentials' => "USNA '04 · EOD"]);
});

function vetDayFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('seeds the veterans-day page with body blocks and 9 FAQs', function () {
    app(GenerateVeteransDayPageAction::class)();

    $page = Page::query()->where('url_path', '/veterans-day/')->firstOrFail();
    expect($page->page_type)->toBe(PageType::VeteransDayHub)
        ->and($page->is_published)->toBeTrue()
        ->and($page->body_blocks)->toBeArray()->not->toBe([])
        ->and($page->faqs()->count())->toBe(9)
        ->and($page->author_id)->not->toBeNull()          // default byline applied
        ->and($page->date_published->format('Y-m-d'))->toBe('2026-06-02');
});

it('does not clobber an editor-edited body/FAQs on re-run', function () {
    app(GenerateVeteransDayPageAction::class)();
    Page::query()->where('url_path', '/veterans-day/')->update([
        'body_blocks' => [['type' => 'paragraph', 'text' => 'Editor rewrote this.']],
    ]);

    app(GenerateVeteransDayPageAction::class)();

    expect(Page::query()->where('url_path', '/veterans-day/')->firstOrFail()->body_blocks)
        ->toBe([['type' => 'paragraph', 'text' => 'Editor rewrote this.']]);
});

it('renders the veterans-day page with the Article + Person + FAQPage graph', function () {
    app(GenerateVeteransDayPageAction::class)();

    $res = vetDayFetch('/veterans-day/')->assertOk();

    $res->assertSee('Veterans Day 2026: History, Meaning &amp; How the Navy Observes It', false) // clean h1
        ->assertSee('What Veterans Day is')
        ->assertSee('the armistice that ended the fighting of World War I')
        ->assertSee('When is Veterans Day 2026?')                    // an FAQ (visible + schema)
        ->assertSee('"@type":"Organization"', false)                 // prepended by SeoHead
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"Person"', false)                       // author byline
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false);
});
