<?php

declare(strict_types=1);

namespace App\Domain\Crm\Repositories;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Models\ConnectionAlias;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class EloquentConnectionRepository implements ConnectionRepositoryInterface
{
    public function findBySlug(string $slug): ?Connection
    {
        return Connection::query()->where('slug', $slug)->first();
    }

    public function findByAliasSlug(string $aliasSlug): ?Connection
    {
        // Single query — resolve alias → canonical connection with a subquery
        // rather than loading the alias row then lazy-loading its connection.
        return Connection::query()
            ->whereIn('id', ConnectionAlias::query()
                ->where('alias_slug', $aliasSlug)
                ->select('connection_id'))
            ->first();
    }

    public function upsertBySlug(string $slug, array $attributes): Connection
    {
        return Connection::updateOrCreate(['slug' => $slug], $attributes);
    }

    public function dueForReview(DateTimeInterface $asOf): Collection
    {
        // Compare the raw `date` column (no DATE() wrap) so the `next_review_due`
        // index is usable; the `<=` predicate already excludes NULLs.
        return Connection::query()
            ->where('next_review_due', '<=', $asOf->format('Y-m-d'))
            ->orderBy('next_review_due')
            ->get();
    }

    public function publishedPagesMissingResearch(array $publishedIds, array $researchedIds): Collection
    {
        return Connection::query()
            ->whereIn('id', $publishedIds)
            ->whereNotIn('id', $researchedIds)
            ->orderBy('slug')
            ->get();
    }

    public function liveNotMarkedPublished(array $publishedIds): Collection
    {
        return Connection::query()
            ->whereIn('id', $publishedIds)
            ->whereNull('duplicate_of')
            ->where('status', '!=', ConnectionStatus::Published->value)
            ->orderBy('slug')
            ->get();
    }

    public function duplicatesNotMarkedDuplicate(): Collection
    {
        return Connection::query()
            ->whereNotNull('duplicate_of')
            ->where('status', '!=', ConnectionStatus::Duplicate->value)
            ->orderBy('slug')
            ->get();
    }
}
