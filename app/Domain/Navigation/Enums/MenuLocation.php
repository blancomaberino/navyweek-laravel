<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Shared\Enums\HasLabel;

/**
 * Where a {@see Menu} renders in the site chrome.
 *
 * The site chrome has three navigation regions, each backed by one or more menus:
 * - `Header` — the single primary top nav (one menu).
 * - `Footer` — the footer's link columns (one menu per column, ordered by the
 *   menu's own `sort_order`; the menu `name` is the column heading).
 * - `Legal` — the slim legal row beneath the footer disclosure (one menu).
 *
 * The region is a first-class axis (not merely inferred from a menu key) so the
 * repository can fetch "every active menu for the footer, in order" in one query.
 */
enum MenuLocation: string implements HasLabel
{
    case Header = 'header';
    case Footer = 'footer';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Header',
            self::Footer => 'Footer',
            self::Legal => 'Legal',
        };
    }
}
