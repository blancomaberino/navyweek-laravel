<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/** Publish a page with the given attributes and return it. */
function renderPage(array $attributes = []): Page
{
    return Page::create(array_merge([
        'page_type' => PageType::Static,
        'slug' => 'yeti-military-veteran',
        'url_path' => '/discount/yeti-military-veteran/',
        'title' => 'YETI Military Discount 2026',
        'meta_description' => 'How service members save at YETI.',
        'canonical_path' => '/discount/yeti-military-veteran/',
        'og_type' => 'article',
        'og_image_path' => '/og/yeti.png',
        'noindex' => false,
        'is_published' => true,
    ], $attributes));
}

/**
 * Issue the request through the HTTP kernel with the URL verbatim. Laravel's
 * `$this->get()` normalizes away the trailing slash, which the (correct)
 * trailing-slash middleware would then 301 to re-add — a test artifact a real
 * browser never hits. Build the request directly so the exact canonical path is
 * what the middleware and controller see.
 */
function fetchPage(string $path): TestResponse
{
    $request = Request::create("http://localhost{$path}");
    $response = app(Kernel::class)->handle($request);

    return TestResponse::fromBaseResponse($response);
}

it('renders the legacy head furniture on a published page', function () {
    renderPage();

    $res = fetchPage('/discount/yeti-military-veteran/')->assertOk();

    $res->assertSee('<meta name="theme-color" content="#0A1628" />', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest" />', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('fonts.googleapis.com/css2?family=Bebas+Neue', false)
        ->assertSee('analytics.ahrefs.com/analytics.js', false)
        ->assertSee('posthog.init(', false);
});

it('emits the per-page SEO block: title, canonical, alternates, OG and Twitter', function () {
    renderPage();

    $res = fetchPage('/discount/yeti-military-veteran/')->assertOk();

    $res->assertSee('<title>YETI Military Discount 2026</title>', false)
        ->assertSee('<meta name="description" content="How service members save at YETI."/>', false)
        ->assertSee('<link rel="canonical" href="https://www.navyweek.org/discount/yeti-military-veteran/"/>', false)
        ->assertSee('href="https://www.navyweek.org/data/navy-week-2026.json" title="Navy Week 2026 JSON feed"', false)
        ->assertSee('href="https://www.navyweek.org/llms.txt" title="NavyWeek.org llms.txt"', false)
        ->assertSee('<meta property="og:type" content="article"/>', false)
        ->assertSee('<meta property="og:image" content="https://www.navyweek.org/og/yeti.png"/>', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image"/>', false)
        // indexable page gets the site-wide robots directive from the layout
        ->assertSee('name="robots" content="index, follow', false);
});

it('auto-prepends the Organization JSON-LD on an indexable page', function () {
    renderPage();

    $res = fetchPage('/discount/yeti-military-veteran/')->assertOk();

    $res->assertSee('<script type="application/ld+json" data-seo="1">', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@id":"https://www.navyweek.org/#organization"', false);
});

it('omits Organization and index-robots on a noindex page, emitting noindex instead', function () {
    renderPage(['noindex' => true, 'url_path' => '/hidden/', 'canonical_path' => '/hidden/', 'slug' => 'hidden']);

    $res = fetchPage('/hidden/')->assertOk();

    $res->assertSee('<meta name="robots" content="noindex, nofollow"/>', false)
        ->assertDontSee('content="index, follow', false)
        ->assertDontSee('"@type":"Organization"', false);
});

it('escapes special characters in the title and description like the legacy serializer', function () {
    renderPage([
        'title' => 'Fish & Chips <b> "Deals"',
        'meta_description' => "It's 5 > 3",
    ]);

    $res = fetchPage('/discount/yeti-military-veteran/')->assertOk();

    // & < > " ' → entities (note ' → &#x27;), matching escapeHtml.
    $res->assertSee('<title>Fish &amp; Chips &lt;b&gt; &quot;Deals&quot;</title>', false)
        ->assertSee('content="It&#x27;s 5 &gt; 3"', false);
});

it('falls back to the default OG image when the page has none', function () {
    renderPage(['og_image_path' => null]);

    fetchPage('/discount/yeti-military-veteran/')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="https://www.navyweek.org/og/home.png"/>', false);
});
