<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Pages\GenerateBasePagesAction;
use App\Domain\Publishing\Actions\ChangeUrlPathAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Models\Redirect;
use App\Domain\Publishing\Pages\GenerateContentPagesAction;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * The path-flexibility contract: a generated page is identified by its stable
 * generation_key, so its url_path is free to change two ways —
 *   (1) a family-wide prefix change (config('publishing.paths.*')) moves every
 *       non-custom page and 301s the old paths;
 *   (2) a per-page editor rename (ChangeUrlPathAction) survives regeneration.
 * In both cases the served JSON-LD reflects the page's actual url_path.
 */
function flexFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('builds family roots and child paths from config, and tracks the knob', function () {
    config()->set('publishing.paths.bases', '/navy-bases/');

    expect(PagePaths::root('bases'))->toBe('/navy-bases/')
        ->and(PagePaths::child('bases', 'norfolk'))->toBe('/navy-bases/norfolk/')
        ->and(PagePaths::child('local_discounts', 'ca', 'san-diego', 'yeti'))
        ->toBe('/discounts/ca/san-diego/yeti/');

    // Change the knob → every derived path follows.
    config()->set('publishing.paths.bases', '/navy-bases-2/');
    expect(PagePaths::root('bases'))->toBe('/navy-bases-2/')
        ->and(PagePaths::child('bases', 'norfolk'))->toBe('/navy-bases-2/norfolk/');
});

it('normalizes a root configured without surrounding slashes', function () {
    config()->set('publishing.paths.bases', 'navy-bases');

    expect(PagePaths::root('bases'))->toBe('/navy-bases/')
        ->and(PagePaths::child('bases', 'x'))->toBe('/navy-bases/x/');
});

it('moves every non-custom page and 301s the old path when a family prefix changes', function () {
    Base::factory()->create(['slug' => 'nas-oceana']);
    app(GenerateBasePagesAction::class)();

    $page = Page::query()->where('generation_key', 'base:nas-oceana')->firstOrFail();
    expect($page->url_path)->toBe('/navy-bases/nas-oceana/')
        ->and($page->url_path_is_custom)->toBeFalse();

    // Flip the knob and regenerate — the page tracks the new default.
    config()->set('publishing.paths.bases', '/navy-bases-2/');
    app(GenerateBasePagesAction::class)();

    $page->refresh();
    expect($page->url_path)->toBe('/navy-bases-2/nas-oceana/')
        // Moved, NOT duplicated — identity is the generation_key, not the path.
        ->and(Page::query()->where('generation_key', 'base:nas-oceana')->count())->toBe(1);

    // A 301 from the old path was created automatically, and the new path serves.
    $redirect = Redirect::query()->where('from_path', '/navy-bases/nas-oceana/')->sole();
    expect($redirect->to_path)->toBe('/navy-bases-2/nas-oceana/')
        ->and($redirect->status)->toBe(301);
    flexFetch('/navy-bases/nas-oceana/')
        ->assertRedirect('http://localhost/navy-bases-2/nas-oceana/')
        ->assertStatus(301);
    flexFetch('/navy-bases-2/nas-oceana/')->assertOk();
});

it('preserves an editor rename across regeneration (never clobbered or duplicated)', function () {
    Base::factory()->create(['slug' => 'nas-lemoore']);
    app(GenerateBasePagesAction::class)();
    $page = Page::query()->where('generation_key', 'base:nas-lemoore')->firstOrFail();

    // Editor renames the page in the admin panel.
    app(ChangeUrlPathAction::class)($page, '/bases/lemoore/');
    expect($page->fresh()?->url_path_is_custom)->toBeTrue();

    // Regeneration must NOT snap it back to the family default, nor duplicate it.
    app(GenerateBasePagesAction::class)();

    $page->refresh();
    expect($page->url_path)->toBe('/bases/lemoore/')
        ->and(Page::query()->where('generation_key', 'base:nas-lemoore')->count())->toBe(1);
});

it('serves JSON-LD keyed on the current url_path after an editor rename', function () {
    Base::factory()->create(['slug' => 'nas-fallon']);
    app(GenerateBasePagesAction::class)();
    $page = Page::query()->where('generation_key', 'base:nas-fallon')->firstOrFail();
    app(ChangeUrlPathAction::class)($page, '/bases/fallon/');

    $res = flexFetch('/bases/fallon/')->assertOk();

    // The page's own JSON-LD nodes (Article/Place @id/url) use the new canonical path…
    $res->assertSee('https://www.navyweek.org/bases/fallon/', false)
        // …and never the stale generated detail path.
        ->assertDontSee('/navy-bases/nas-fallon/', false);
});

it('adopts a pre-generation_key legacy row instead of duplicating it (in-place upgrade)', function () {
    Base::factory()->create(['slug' => 'nas-whidbey']);
    // A row created before generation_key existed: keyless, at the family-default path.
    $legacy = Page::create([
        'page_type' => PageType::Base,
        'slug' => 'nas-whidbey',
        'url_path' => '/navy-bases/nas-whidbey/',
        'title' => 'Legacy title',
        'date_published' => '2025-01-01',
        'date_modified' => '2025-01-01',
        'is_published' => true,
    ]);
    expect($legacy->generation_key)->toBeNull();

    app(GenerateBasePagesAction::class)();

    // The legacy row is adopted (stamped with the key), never duplicated, and its
    // original publish date is preserved by the build clock.
    expect(Page::query()->where('url_path', '/navy-bases/nas-whidbey/')->count())->toBe(1);
    $legacy->refresh();
    expect($legacy->generation_key)->toBe('base:nas-whidbey')
        ->and($legacy->date_published->toDateString())->toBe('2025-01-01');
});

it('recognizes a renamed content page by generation_key and never re-seeds its body', function () {
    app(GenerateContentPagesAction::class)();
    $privacy = Page::query()->where('generation_key', 'content:privacy')->firstOrFail();

    // Editor renames it AND rewrites the body.
    app(ChangeUrlPathAction::class)($privacy, '/legal/privacy/');
    $privacy->refresh();
    $privacy->update(['body_blocks' => [['type' => 'paragraph', 'text' => 'Editor rewrote this.']]]);

    // Re-run: the page is found by generation_key (not the old path), so its body and
    // its rename both survive; no duplicate row is created.
    app(GenerateContentPagesAction::class)();

    expect(Page::query()->where('generation_key', 'content:privacy')->count())->toBe(1);
    $privacy->refresh();
    expect($privacy->url_path)->toBe('/legal/privacy/')
        ->and($privacy->body_blocks)->toBe([['type' => 'paragraph', 'text' => 'Editor rewrote this.']]);
});
