<?php

declare(strict_types=1);

use App\Domain\Publishing\Actions\ChangeUrlPathAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Models\Redirect;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * Drive the request through the real HTTP kernel so CanonicalUrlMiddleware runs —
 * that's what turns a `redirects` row into a live 301. `$this->get()` normalizes the
 * path, so build the request verbatim.
 */
function hitPath(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

function staticPage(string $urlPath): Page
{
    return Page::create([
        'page_type' => PageType::Static,
        'slug' => trim($urlPath, '/'),
        'url_path' => $urlPath,
        'title' => 'Guide',
        'is_published' => true,
        'noindex' => false,
    ]);
}

function movePage(Page $page, string $to): void
{
    app(ChangeUrlPathAction::class)($page, $to);
}

it('renames a page and 301s the old path to the new one, with no deploy', function () {
    $page = staticPage('/guides/old/');

    movePage($page, '/guides/new/');

    expect($page->fresh()?->url_path)->toBe('/guides/new/');

    $redirect = Redirect::query()->where('from_path', '/guides/old/')->sole();
    expect($redirect->to_path)->toBe('/guides/new/')
        ->and($redirect->status)->toBe(301)
        ->and($redirect->reason)->toBe('slug-change')
        ->and($redirect->match_type)->toBe(RedirectMatchType::Exact)
        ->and($redirect->is_active)->toBeTrue();

    // The old URL now 301s to the new one; the new URL serves the page.
    hitPath('/guides/old/')->assertRedirect('http://localhost/guides/new/')->assertStatus(301);
    hitPath('/guides/new/')->assertOk();
});

it('collapses an inbound chain to a single hop', function () {
    // An older URL already 301s to /guides/old/.
    Redirect::create([
        'from_path' => '/guides/older/',
        'to_path' => '/guides/old/',
        'status' => 301,
        'reason' => 'slug-change',
        'match_type' => RedirectMatchType::Exact,
        'is_active' => true,
    ]);
    $page = staticPage('/guides/old/');

    movePage($page, '/guides/new/');

    // The older rule is repointed straight at /guides/new/ — no /older/→/old/→/new/.
    expect(Redirect::query()->where('from_path', '/guides/older/')->sole()->to_path)->toBe('/guides/new/');
    hitPath('/guides/older/')->assertRedirect('http://localhost/guides/new/')->assertStatus(301);
});

it('drops a stale rule that pointed away from the now-live new path', function () {
    // /guides/new/ used to 301 elsewhere (an earlier retirement).
    Redirect::create([
        'from_path' => '/guides/new/',
        'to_path' => '/somewhere-else/',
        'status' => 301,
        'reason' => 'retirement',
        'match_type' => RedirectMatchType::Exact,
        'is_active' => true,
    ]);
    $page = staticPage('/guides/old/');

    movePage($page, '/guides/new/');

    expect(Redirect::query()->where('from_path', '/guides/new/')->exists())->toBeFalse();
    hitPath('/guides/new/')->assertOk();
});

it('is a no-op when the path is unchanged', function () {
    $page = staticPage('/guides/same/');

    movePage($page, '/guides/same/');

    expect(Redirect::query()->count())->toBe(0);
});

it('never leaves a self-redirect after a rename back and forth', function () {
    $page = staticPage('/guides/a/');
    movePage($page, '/guides/b/');   // a → b
    $page->refresh();
    movePage($page, '/guides/a/');   // b → a; the a→b rule must not become a→a

    expect(Redirect::query()->whereColumn('from_path', 'to_path')->exists())->toBeFalse();
    // /guides/a/ is live again; /guides/b/ 301s to it.
    hitPath('/guides/a/')->assertOk();
    hitPath('/guides/b/')->assertRedirect('http://localhost/guides/a/')->assertStatus(301);
});
