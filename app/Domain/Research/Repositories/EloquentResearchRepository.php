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
            return new Collection;
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
        $locked = $this->lockForUpdate($research);
        if ($locked->status === ResearchStatus::Complete) {
            $locked->status = ResearchStatus::Stale;
            $locked->save();
        }
    }

    public function markVerified(Research $research, DateTimeInterface $verifiedAt): Research
    {
        $locked = $this->lockForUpdate($research);
        $locked->status = ResearchStatus::Complete;
        $locked->last_verified = Carbon::instance($verifiedAt);
        $locked->save();

        return $locked;
    }

    /**
     * Re-read the brief's row under a `FOR UPDATE` lock so a caller can serialize a
     * read-then-write. Must run inside the caller's transaction. Shared by the
     * mark* mutators so the lock semantics live in one place.
     */
    private function lockForUpdate(Research $research): Research
    {
        return Research::query()->whereKey($research->getKey())->lockForUpdate()->firstOrFail();
    }

    public function connectionIdsWithBriefs(): array
    {
        /** @var array<int, int> $ids */
        $ids = Research::query()->distinct()->pluck('connection_id')->all();

        return $ids;
    }
}
