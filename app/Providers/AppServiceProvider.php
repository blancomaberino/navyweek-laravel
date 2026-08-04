<?php

namespace App\Providers;

use App\Domain\Navigation\Support\NavigationTree;
use App\Domain\Navigation\View\NavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped so the header and footer composers share a single
        // NavigationTree instance (and its per-request memo of the menu reads),
        // rebuilt fresh on the next request.
        $this->app->scoped(NavigationTree::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Render the site chrome from the editable menu data. Bound to the partials
        // themselves (not just the layout) so they stay data-driven wherever they
        // are included, with a hardcoded fallback inside NavigationTree.
        View::composer(['partials.header', 'partials.footer'], NavigationComposer::class);
    }
}
