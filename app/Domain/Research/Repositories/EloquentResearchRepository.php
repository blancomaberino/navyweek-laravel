<?php

declare(strict_types=1);

namespace App\Domain\Research\Repositories;

use App\Domain\Research\Models\Research;
use Illuminate\Support\Collection;

final class EloquentResearchRepository implements ResearchRepositoryInterface
{
    public function latestForConnection(int $connectionId): ?Research
    {
        return Research::query()
            ->where('connection_id', $connectionId)
            ->orderByDesc('version')
            ->first();
    }

    public function historyForConnection(int $connectionId): Collection
    {
        return Research::query()
            ->where('connection_id', $connectionId)
            ->orderByDesc('version')
            ->get();
    }

    public function connectionIdsWithBriefs(): array
    {
        /** @var array<int, int> $ids */
        $ids = Research::query()->distinct()->pluck('connection_id')->all();

        return $ids;
    }
}
