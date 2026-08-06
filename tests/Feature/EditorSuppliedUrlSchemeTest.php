<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\Base;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Every editor-supplied URL that reaches an `href` must pass the LinkUrl scheme
 * allowlist (http/https/mailto/tel). A stored `javascript:` value is a
 * stored-XSS payload the moment a template interpolates it raw, and these fields
 * are all editable from the admin panel.
 *
 * One case per payload SHAPE, because each reaches the template differently:
 * a related model (`sources[].url`), a JSON column (`users.featured_works[].url`)
 * and a plain column (`users.linkedin_url`).
 */
const EVIL = 'javascript:alert(document.cookie)';

/**
 * Built by hand, not via `$this->get()`: Laravel's test client runs the URI
 * through `prepareUrlForRequest()`, which TRIMS the trailing slash — so the
 * request would only ever hit the slash-normalising 301, never the page.
 */
function renderSanitizedPath(string $path): string
{
    $response = app()->handle(Request::create('http://localhost'.$path, 'GET'));

    expect($response->getStatusCode())->toBe(200);

    return (string) $response->getContent();
}

it('neutralizes a dangerous scheme in a source url on a base guide', function () {
    $base = Base::factory()->create(['slug' => 'naval-station-norfolk']);
    $base->sources()->create(['label' => 'Evil', 'url' => EVIL, 'sort_order' => 0]);

    $page = Page::factory()->create([
        'page_type' => PageType::Base,
        'url_path' => '/navy-bases/naval-station-norfolk/',
        'slug' => 'naval-station-norfolk',
        'is_published' => true,
    ]);
    $page->pageable()->associate($base)->save();

    $html = renderSanitizedPath('/navy-bases/naval-station-norfolk/');

    expect($html)->not->toContain('javascript:')
        ->and($html)->toContain('href="#"');
});

it('neutralizes a dangerous scheme in an author profile url column and json column', function () {
    $author = User::factory()->create([
        'slug' => 'jane-doe',
        'name' => 'Jane Doe',
        'linkedin_url' => EVIL,
        'featured_works' => [['label' => 'Evil work', 'url' => EVIL, 'note' => null]],
    ]);

    $page = Page::factory()->create([
        'page_type' => PageType::Author,
        'url_path' => '/authors/jane-doe/',
        'slug' => 'jane-doe',
        'is_published' => true,
    ]);
    $page->pageable()->associate($author)->save();

    $html = renderSanitizedPath('/authors/jane-doe/');

    expect($html)->not->toContain('javascript:');
});
