<?php

declare(strict_types=1);

use App\Domain\Publishing\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Every public page is rendered by one catch-all keyed on pages.url_path.
// CanonicalUrlMiddleware (global, runs first) has already resolved redirects, so
// the router only sees live routes; anything the PageController can't find is a
// genuine 404. Route::fallback() catches all nested paths without Laravel's
// trailing-slash route-parameter quirks and keeps route:cache valid.
Route::get('/', [PageController::class, 'show']);
Route::fallback([PageController::class, 'show']);
