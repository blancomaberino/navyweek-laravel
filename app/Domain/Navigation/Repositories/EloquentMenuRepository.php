<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Repositories;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use Illuminate\Support\Collection;

final class EloquentMenuRepository implements MenuRepositoryInterface
{
    public function activeMenusForLocation(MenuLocation $location): Collection
    {
        return Menu::query()
            ->where('location', $location->value)
            ->where('is_active', true)
            // Only active top-level items, each with its active children — the two
            // constrained relations do the filtering/ordering (see the models).
            ->with(['activeItems', 'activeItems.activeChildren'])
            ->orderBy('sort_order')
            ->get();
    }
}
