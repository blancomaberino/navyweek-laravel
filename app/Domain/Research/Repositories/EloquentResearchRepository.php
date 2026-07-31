<?php

declare(strict_types=1);

namespace App\Domain\Research\Repositories;

use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use DateTimeInterface;
use Illuminate\Support\Carbon;
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

    public function latestForConnections(array $connectionIds): Collection
    {
        if ($connectionIds === []) {
            return new Collection();
        }

        // One query for the whole set, then keep the highest-version row per
        // connection in PHP (briefs per connection are few — no per-connection query).
        return Research::query()
            ->whereIn('connection_id', $connectionIds)
            ->get()
            ->sortByDesc('version')
            ->unique('connection_id')
            ->keyBy('connection_id');
    }

    public function historyForConnection(int $connectionId): Collection
    {
        return Research::query()
            ->where('connection_id', $connectionId)
            ->orderByDesc('version')
            ->get();
    }

    public function markStale(Research $research): void
    {
        $locked = Research::query()->whereKey($research->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->status === ResearchStatus::Complete) {
            $locked->status = ResearchStatus::Stale;
            $locked->save();
        }
    }

    public function markVerified(Research $research, DateTimeInterface $verifiedAt): Research
    {
        $locked = Research::query()->whereKey($research->getKey())->lockForUpdate()->firstOrFail();
        $locked->status = ResearchStatus::Complete;
        $locked->last_verified = Carbon::instance($verifiedAt);
        $locked->save();

        return $locked;
    }

    public function connectionIdsWithBriefs(): array
    {
        /** @var array<int, int> $ids */
        $ids = Research::query()->distinct()->pluck('connection_id')->all();

        return $ids;
    }
}
