<?php

declare(strict_types=1);

namespace App\Domain\Navigation\View;

use App\Domain\Navigation\Support\NavigationTree;
use App\Providers\AppServiceProvider;
use Illuminate\View\View;

/**
 * Shares the editable navigation into the header/footer partials. Bound to both
 * partials (in {@see AppServiceProvider}) so each renders from menu
 * data whether or not it was reached through the base layout; the request-scoped
 * {@see NavigationTree} means the shared reads run once per request regardless.
 */
final class NavigationComposer
{
    public function __construct(private readonly NavigationTree $tree) {}

    public function compose(View $view): void
    {
        match ($view->name()) {
            'partials.footer' => $view->with([
                'footerGroups' => $this->tree->footerGroups(),
                'legalNav' => $this->tree->legal(),
            ]),
            default => $view->with('primaryNav', $this->tree->header()),
        };
    }
}
