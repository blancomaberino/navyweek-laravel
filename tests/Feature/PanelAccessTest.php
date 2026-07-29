<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * Hit /admin through the HTTP kernel directly. The global CanonicalUrlMiddleware
 * runs first (and must pass /admin through), and Laravel's `$this->get()`
 * normalizes the path — building the request verbatim exercises the real stack.
 */
function adminGet(string $path = '/admin'): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('lets an admin user reach the Filament panel', function () {
    $this->be(User::factory()->admin()->create());

    adminGet()->assertOk();
});

it('forbids a non-admin authenticated user from the panel', function () {
    $this->be(User::factory()->create());

    adminGet()->assertForbidden();
});

it('redirects a guest to the panel login', function () {
    adminGet()->assertRedirect('http://localhost/admin/login');
});

it('gates canAccessPanel on the is_admin flag', function () {
    $panel = Filament::getPanel('admin');

    expect(User::factory()->admin()->create()->canAccessPanel($panel))->toBeTrue()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeFalse();
});

it('leaves the public catch-all untouched (a non-admin path still 301s to /)', function () {
    adminGet('/totally-unknown/')->assertRedirect('http://localhost/');
});
