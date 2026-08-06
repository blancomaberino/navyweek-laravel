<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A family root that owns no page must 301 to "/" like every other unknown path —
 * the live site does exactly this (/authors/ -> 301 -> /).
 *
 * Asserted through the HTTP kernel on purpose. Under `artisan serve`, PHP's
 * built-in server short-circuits any request whose path maps to a real directory
 * under public/ and returns its OWN 404 without ever invoking Laravel — so
 * /authors/ looks broken locally purely because public/authors/ exists to hold the
 * two byline portraits. Production serves through the front controller and is
 * unaffected; these assertions prove the routing itself is correct.
 *
 * The request is built by hand rather than with `$this->get()`, because Laravel's
 * test client runs the URI through `prepareUrlForRequest()`, which TRIMS the
 * trailing slash — so `$this->get('/authors/')` actually asks for `/authors` and
 * only ever exercises the slash-normalising redirect, never the catch-all.
 */
function handlePath(string $path): Response
{
    return app()->handle(Request::create('http://localhost'.$path, 'GET'));
}

it('301s an unmatched family root to the site root', function (string $path) {
    $response = handlePath($path);

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('http://localhost/');
})->with([
    '/authors/',
    '/authors/nobody/',
    '/navy-week/',
]);

it('still serves an author profile that does exist', function () {
    Page::factory()->create([
        'page_type' => PageType::Author,
        'url_path' => '/authors/jane-doe/',
        'slug' => 'jane-doe',
        'is_published' => true,
    ]);

    expect(handlePath('/authors/jane-doe/')->getStatusCode())->toBe(200);
});
