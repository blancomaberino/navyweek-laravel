<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateYmylGuidePagesAction;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford', 'credentials' => "USNA '02"]);
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera', 'credentials' => "USNA '04 · EOD"]);
});

function ymylFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('seeds both YMYL guide pages with an editable body', function () {
    $count = app(GenerateYmylGuidePagesAction::class)();

    expect($count)->toBe(2);
    foreach (['/va-disability/', '/veterans-home-care/'] as $path) {
        $page = Page::query()->where('url_path', $path)->firstOrFail();
        expect($page->page_type)->toBe(PageType::Static)
            ->and($page->is_published)->toBeTrue()
            ->and($page->body_blocks)->toBeArray()->not->toBe([])
            ->and($page->author_id)->not->toBeNull()
            ->and($page->reviewer_id)->not->toBeNull();
    }
});

it('renders va-disability with Article + author/reviewer Person + WebPage and NO FAQPage', function () {
    app(GenerateYmylGuidePagesAction::class)();

    $res = ymylFetch('/va-disability/')->assertOk();

    $res->assertSee('What VA disability compensation is')
        ->assertSee('monthly, tax-free benefit')
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@id":"https://www.navyweek.org/va-disability/#reviewer"', false)   // reviewer Person
        ->assertSee('"@id":"https://www.navyweek.org/va-disability/#webpage"', false)
        // YMYL guides intentionally emit NO FAQPage (validate-jsonld REQUIRED_TYPES).
        ->assertDontSee('"@type":"FAQPage"', false);
});

it('renders veterans-home-care with the guide graph', function () {
    app(GenerateYmylGuidePagesAction::class)();

    $res = ymylFetch('/veterans-home-care/')->assertOk();

    $res->assertSee('The one thing most families get wrong')
        ->assertSee('Veterans Health Administration')
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertDontSee('"@type":"FAQPage"', false);
});

it('does not clobber an editor body on re-run', function () {
    app(GenerateYmylGuidePagesAction::class)();
    Page::query()->where('url_path', '/va-disability/')->update([
        'body_blocks' => [['type' => 'paragraph', 'text' => 'Editor rewrote this.']],
    ]);

    app(GenerateYmylGuidePagesAction::class)();

    expect(Page::query()->where('url_path', '/va-disability/')->firstOrFail()->body_blocks)
        ->toBe([['type' => 'paragraph', 'text' => 'Editor rewrote this.']]);
});
