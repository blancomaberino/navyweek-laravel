<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Models\Redirect;
use Database\Seeders\RedirectSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * Laravel's `$this->get()` helper trims trailing slashes off the URI before the
 * request is built (`prepareUrlForRequest`), which would defeat every test of the
 * trailing-slash-sensitive middleware. So we build the Request directly (which
 * preserves the path verbatim, exactly like a real HTTP request) and push it
 * through the HTTP kernel.
 */
function getRaw(string $uri, string $method = 'GET'): TestResponse
{
    $request = Request::create($uri, $method);
    $response = app(Kernel::class)->handle($request);

    return TestResponse::fromBaseResponse($response);
}

function publishPage(string $urlPath, bool $published = true): Page
{
    return Page::create([
        'page_type' => PageType::Static,
        'slug' => trim($urlPath, '/') ?: 'home',
        'url_path' => $urlPath,
        'is_published' => $published,
    ]);
}

// --- Step 1: apex → www ---------------------------------------------------

it('301s the apex host to www', function () {
    getRaw('http://navyweek.org/anything/')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://www.navyweek.org/anything/');
});

// --- Step 2: trailing slash ----------------------------------------------

it('301s a slashless path to its trailing-slash form', function () {
    getRaw('http://localhost/discount/nike')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/nike/');
});

it('preserves the query string on a trailing-slash redirect', function () {
    getRaw('http://localhost/discount/nike?ref=abc')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/nike/?ref=abc');
});

// --- Step 3: extension gate ----------------------------------------------

it('passes non-html assets straight through (no redirect)', function () {
    // robots.txt has an extension and is not html → middleware calls next();
    // no page owns it, so the catch-all controller 404s (the web server serves
    // the real file in production).
    getRaw('http://localhost/robots.txt')->assertNotFound();
});

// --- Step 5a: algorithmic category-hub rule ------------------------------

it('rewrites the retired /discount/category/<slug>/ shape', function () {
    getRaw('http://localhost/discount/category/pet-food/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/pet-food-military-veteran/');
});

it('does not double-suffix an already-military-veteran category slug', function () {
    getRaw('http://localhost/discount/category/pet-food-military-veteran/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/pet-food-military-veteran/');
});

// --- Step 5b: redirect table (exact + prefix) ----------------------------

it('applies a seeded exact redirect', function () {
    $this->seed(RedirectSeeder::class);

    getRaw('http://localhost/promo-code/chewy/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/chewy-military-discount/');
});

it('applies the retired autozone flat url', function () {
    $this->seed(RedirectSeeder::class);

    getRaw('http://localhost/discount/autozone-military-discount/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/autozone/military-veteran/');
});

it('collapses a /navy-ranks/ subpath to the list page', function () {
    $this->seed(RedirectSeeder::class);

    getRaw('http://localhost/navy-ranks/enlisted/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/navy-ranks/');
});

it('sends the ratings mirror to /navy-ratings/ via the longest prefix', function () {
    $this->seed(RedirectSeeder::class);

    getRaw('http://localhost/navy-ranks/enlisted/ratings/aviation/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/navy-ratings/');
});

it('does not self-redirect the /navy-ranks/ list page itself', function () {
    $this->seed(RedirectSeeder::class);
    publishPage('/navy-ranks/');

    getRaw('http://localhost/navy-ranks/')
        ->assertOk()
        ->assertSee('OK: /navy-ranks/');
});

// --- Step 6: legacy fuzzy resolve ----------------------------------------

it('resolves a historic city html url to /schedule/ with a cache header', function () {
    getRaw('http://localhost/boston.html')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/schedule/')
        // The resolver 301 is the only one that carries a cache header (ported
        // verbatim; Symfony normalizes the directive order).
        ->assertHeader('Cache-Control', 'max-age=86400, public');
});

it('skips the fuzzy resolver for a path containing ".." (traversal guard)', function () {
    // Without the guard, normalizeCitySegment would strip the trailing dots and
    // resolve "boston" → /schedule/; the guard sends it to the catch-all instead.
    getRaw('http://localhost/boston../')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/');
});

it('remaps a historic city to its /city/<slug>/ page', function () {
    getRaw('http://localhost/charlotte.html')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/city/charlotte/');
});

// --- Step 7: catch-all ----------------------------------------------------

it('renders a published page', function () {
    publishPage('/discount/nike/');

    getRaw('http://localhost/discount/nike/')
        ->assertOk()
        ->assertSee('OK: /discount/nike/');
});

it('301s an unpublished page path to the homepage', function () {
    publishPage('/hidden/', published: false);

    getRaw('http://localhost/hidden/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/');
});

it('301s an unknown path to the homepage and drops the query', function () {
    getRaw('http://localhost/totally-unknown/?x=1')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/');
});

it('301s a non-canonical mixed-case path to the homepage', function () {
    // The page exists at the canonical lowercase path; a mixed-case request must
    // not be served (duplicate content) — it 301s to "/" like the legacy manifest.
    publishPage('/discount/nike/');

    getRaw('http://localhost/Discount/Nike/')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/');
});

// --- Query-string fidelity + side effects --------------------------------

it('preserves query-string order and bytes verbatim (no re-encode/reorder)', function () {
    getRaw('http://localhost/discount/nike?b=2&a=1&utm_source=Foo%2CBar')
        ->assertStatus(301)
        ->assertHeader('Location', 'http://localhost/discount/nike/?b=2&a=1&utm_source=Foo%2CBar');
});

it('appends the query string on the apex → www redirect', function () {
    getRaw('http://navyweek.org/anything/?ref=1')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://www.navyweek.org/anything/?ref=1');
});

it('increments the hit counter on a matched redirect', function () {
    $this->seed(RedirectSeeder::class);

    getRaw('http://localhost/promo-code/chewy/')->assertStatus(301);

    expect(Redirect::where('from_path', '/promo-code/chewy/')->value('hits'))
        ->toBe(1);
});

// --- Method gate ----------------------------------------------------------

it('does not trailing-slash-redirect a non-GET/HEAD request', function () {
    // POST skips the redirect pipeline (method gate) → no 301; the route layer
    // handles it (405, since only GET/HEAD are routed).
    getRaw('http://localhost/discount/nike', 'POST')
        ->assertStatus(405);
});

it('still 301s the apex host before the method gate', function () {
    // The apex → www check precedes the GET/HEAD gate, so even a POST is redirected.
    getRaw('http://navyweek.org/anything/', 'POST')
        ->assertStatus(301)
        ->assertHeader('Location', 'https://www.navyweek.org/anything/');
});
