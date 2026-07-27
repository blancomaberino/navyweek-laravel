<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use Illuminate\Support\Collection;

final class EloquentNavyWeekEventRepository implements NavyWeekEventRepositoryInterface
{
    public function findBySlug(string $slug): ?NavyWeekEvent
    {
        return NavyWeekEvent::query()->where('slug', $slug)->first();
    }

    public function all(): Collection
    {
        return NavyWeekEvent::query()->orderBy('sequence')->get();
    }

    public function byStatus(NavyWeekStatus $status): Collection
    {
        return NavyWeekEvent::query()
            ->where('status', $status->value)
            ->orderBy('sequence')
            ->get();
    }
}
