<?php

declare(strict_types=1);

namespace App\Domain\Navigation\View;

use App\Domain\Navigation\Support\ChromeCatalog;
use App\Domain\Navigation\Support\NavigationTree;
use App\Providers\AppServiceProvider;
use Illuminate\View\View;

/**
 * Shares the site chrome's data into the header/footer partials. Bound to both
 * partials (in {@see AppServiceProvider}) so each renders whether or not it was
 * reached through the base layout; the request-scoped {@see NavigationTree} and
 * {@see ChromeCatalog} mean the shared reads run once per request regardless.
 *
 * The header top bar is fixed chrome (Deals mega-menu / Schedule / Events / …),
 * ported 1:1 from the legacy Header.tsx — only the Deals list and the Events
 * dropdown are data-driven. The footer link columns remain editable menu data.
 */
final class NavigationComposer
{
    public function __construct(
        private readonly NavigationTree $tree,
        private readonly ChromeCatalog $chrome,
    ) {}

    public function compose(View $view): void
    {
        match ($view->name()) {
            'partials.footer' => $view->with([
                'footerGroups' => $this->tree->footerGroups(),
                'legalNav' => $this->tree->legal(),
                'deals' => $this->chrome->deals(),
                'lastUpdated' => config('site.last_updated'),
            ]),
            default => $view->with([
                // Header: curated registry order, not the section's date sort.
                'deals' => $this->chrome->menuDeals(),
                'eventLinks' => $this->chrome->eventLinks(),
                'lastUpdated' => config('site.last_updated'),
                // The nav slug to highlight. The legacy passes this explicitly from
                // each page view, so a detail page lights its FAMILY's tab rather
                // than only an exact path match.
                'activePage' => $this->chrome->activePage(request()->getPathInfo()),
            ]),
        };
    }
}
