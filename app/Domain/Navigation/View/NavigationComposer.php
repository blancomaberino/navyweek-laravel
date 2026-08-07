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
 * The header top bar renders from the `header` menu (labels, urls, order, slots,
 * active slugs); the CONTENTS of its two dynamic panels — the Deals mega-menu and the
 * Events dropdown — still come from the catalog, since those are brand guides and event
 * hubs rather than editable links. The footer link columns are editable menu data too.
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
                // The nav itself is now menu data: labels, urls, BOTH orderings, which
                // item is the Deals mega-menu / Events dropdown / NAVCO CTA, and the
                // slug that lights each tab. Editing the `header` menu changes the bar.
                'navItems' => $this->tree->header(),
                'mobileNavItems' => $this->tree->headerMobile(),
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
