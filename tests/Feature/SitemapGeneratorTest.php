<?php

declare(strict_types=1);

use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Sitemap\SitemapGenerator;
use Illuminate\Support\Facades\File;

/** Publish a page at an exact canonical path (slug kept unique, extra attrs merged). */
function pageAt(string $urlPath, array $attributes = []): Page
{
    return Page::factory()->create(array_merge([
        'slug' => trim(str_replace('/', '-', $urlPath), '-') ?: 'home',
        'url_path' => $urlPath,
    ], $attributes));
}

it('buckets pages by url_path prefix into the right child sitemap', function () {
    pageAt('/city/san-diego/');                 // events
    pageAt('/veterans-day/');                    // guides
    pageAt('/navy-bases/norfolk/');              // reference
    pageAt('/navy-ratings/');                    // reference
    pageAt('/discount/chewy/');                  // discounts
    pageAt('/discounts/ca/san-diego/acme/');     // local-discounts
    pageAt('/fleetweek/new-york/');              // fleetweek
    pageAt('/blue-angels/el-centro/');           // jetteams
    pageAt('/air-show/miramar/');                // air-show

    $result = app(SitemapGenerator::class)->build();

    expect($result->counts)->toMatchArray([
        'events' => 1, 'guides' => 1, 'reference' => 2, 'discounts' => 1,
        'local-discounts' => 1, 'fleetweek' => 1, 'jetteams' => 1, 'air-show' => 1,
    ]);
    expect($result->uncovered)->toBe([]);

    expect($result->files['sitemap-air-show.xml'])->toContain('https://www.navyweek.org/air-show/miramar/');
    expect($result->files['sitemap-jetteams.xml'])->toContain('https://www.navyweek.org/blue-angels/el-centro/');
    // /discounts/ (local) must NOT leak into the national /discount/ bucket.
    expect($result->files['sitemap-discounts.xml'])->not->toContain('/discounts/ca/');
    expect($result->files['sitemap-local-discounts.xml'])->toContain('https://www.navyweek.org/discounts/ca/san-diego/acme/');
});

it('always emits events/guides/reference but omits other empty buckets', function () {
    $result = app(SitemapGenerator::class)->build();

    expect($result->files)->toHaveKeys(['sitemap.xml', 'sitemap-events.xml', 'sitemap-guides.xml', 'sitemap-reference.xml'])
        ->and($result->files)->not->toHaveKey('sitemap-discounts.xml')
        ->and($result->files)->not->toHaveKey('sitemap-air-show.xml');
    // An always-emitted empty bucket is a valid, url-less urlset.
    expect($result->files['sitemap-events.xml'])
        ->toContain('<urlset')
        ->not->toContain('<url>');
});

it('excludes unpublished and noindex pages', function () {
    pageAt('/air-show/published/');
    pageAt('/air-show/draft/', ['is_published' => false]);
    pageAt('/air-show/hidden/', ['noindex' => true]);

    $result = app(SitemapGenerator::class)->build();

    expect($result->counts['air-show'])->toBe(1);
    expect($result->files['sitemap-air-show.xml'])
        ->toContain('/air-show/published/')
        ->not->toContain('/air-show/draft/')
        ->not->toContain('/air-show/hidden/');
});

it('takes lastmod from the build-clock date_modified, falling back to date_published then the fixed date', function () {
    pageAt('/air-show/modified/', ['date_modified' => '2026-07-20 10:00:00', 'date_published' => '2026-01-01 00:00:00']);
    pageAt('/air-show/published-only/', ['date_modified' => null, 'date_published' => '2026-03-15 00:00:00']);
    pageAt('/air-show/neither/', ['date_modified' => null, 'date_published' => null]);

    $xml = app(SitemapGenerator::class)->build()->files['sitemap-air-show.xml'];

    expect($xml)
        ->toContain('<lastmod>2026-07-20</lastmod>')
        ->toContain('<lastmod>2026-03-15</lastmod>')
        ->toContain('<lastmod>2026-06-21</lastmod>'); // FALLBACK_DATE
});

it('reports pages in no bucket as uncovered and excludes them from output', function () {
    pageAt('/air-show/kept/');
    pageAt('/some-brand-new-family/x/');

    $result = app(SitemapGenerator::class)->build();

    expect($result->uncovered)->toBe(['/some-brand-new-family/x/']);
    foreach ($result->files as $xml) {
        expect($xml)->not->toContain('/some-brand-new-family/');
    }
});

it('adds supplied data resources to the data bucket without a trailing slash', function () {
    pageAt('/air-show/x/');

    $result = app(SitemapGenerator::class)->build([
        ['path' => '/llms.txt', 'lastmod' => '2026-07-10'],
        ['path' => '/data/navy-week-2026.json', 'lastmod' => '2026-07-11'],
    ]);

    expect($result->counts['data'])->toBe(2);
    expect($result->files['sitemap-data.xml'])
        ->toContain('<loc>https://www.navyweek.org/llms.txt</loc>')          // no trailing slash
        ->toContain('<loc>https://www.navyweek.org/data/navy-week-2026.json</loc>')
        ->toContain('<lastmod>2026-07-10</lastmod>');
});

it('builds a well-formed index that references every emitted child with its freshest date', function () {
    pageAt('/air-show/a/', ['date_modified' => '2026-07-01 00:00:00']);
    pageAt('/air-show/b/', ['date_modified' => '2026-07-25 00:00:00']);

    $result = app(SitemapGenerator::class)->build();
    $index = $result->files['sitemap.xml'];

    // Parses as XML and lists each emitted child.
    $parsed = simplexml_load_string($index);
    expect($parsed)->not->toBeFalse();
    foreach (array_keys($result->files) as $file) {
        if ($file === 'sitemap.xml') {
            continue;
        }
        expect($index)->toContain("https://www.navyweek.org/{$file}");
    }
    // The air-show child's index lastmod is the freshest entry date.
    expect($index)->toContain('https://www.navyweek.org/sitemap-air-show.xml')
        ->and($index)->toContain('<lastmod>2026-07-25</lastmod>');
});

it('every child urlset parses as valid XML', function () {
    pageAt('/discount/brand/');
    pageAt('/navy-bases/norfolk/');

    foreach (app(SitemapGenerator::class)->build()->files as $xml) {
        expect(simplexml_load_string($xml))->not->toBeFalse();
    }
});

it('writes the sitemap files to public/ via the command', function () {
    $tmp = sys_get_temp_dir().'/nw-sitemap-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($tmp);
    app()->usePublicPath($tmp);

    pageAt('/air-show/miramar/');

    $this->artisan('sitemap:generate')
        ->expectsOutputToContain('sitemap file(s)')
        ->assertSuccessful();

    expect(File::exists("{$tmp}/sitemap.xml"))->toBeTrue()
        ->and(File::exists("{$tmp}/sitemap-air-show.xml"))->toBeTrue();
    expect(File::get("{$tmp}/sitemap-air-show.xml"))->toContain('https://www.navyweek.org/air-show/miramar/');

    File::deleteDirectory($tmp);
});
