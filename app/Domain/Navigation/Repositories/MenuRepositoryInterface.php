<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Repositories;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use Illuminate\Support\Collection;

interface MenuRepositoryInterface
{
    /**
     * Active menus for a chrome region, ordered by the menu's `sort_order`, with
     * their active top-level items (and each item's active children) eager-loaded
     * and ordered — the single read that backs the header, footer and legal rows.
     *
     * @return Collection<int, Menu>
     */
    public function activeMenusForLocation(MenuLocation $location): Collection;
}
