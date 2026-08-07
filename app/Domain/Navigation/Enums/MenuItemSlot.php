<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * What a header item RENDERS AS, when it is not a plain link.
 *
 * The header is not a flat link list: two of its entries are panels whose contents
 * come from the catalog rather than from the menu (the Deals mega-menu of every brand
 * guide, and the Events dropdown of the four hubs), and one is the off-site NAVCO
 * call-to-action styled apart from the nav. Without this, a menu could carry the
 * labels but not the ORDER — the panels would stay pinned in the markup and moving
 * "Deals" in the CMS would do nothing.
 *
 * A `null` slot is the default: an ordinary link.
 */
enum MenuItemSlot: string implements HasLabel
{
    /** Full-width mega-menu of every discount-brand guide (contents from ChromeCatalog). */
    case Deals = 'deals';

    /** Dropdown of the four event hubs, with air-show guides indented under the first. */
    case Events = 'events';

    /** The off-site NAVCO button that sits outside the nav list. */
    case Cta = 'cta';

    public function label(): string
    {
        return match ($this) {
            self::Deals => 'Deals mega-menu',
            self::Events => 'Events dropdown',
            self::Cta => 'Call-to-action button',
        };
    }
}
