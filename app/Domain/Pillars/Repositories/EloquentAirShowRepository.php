<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use Illuminate\Support\Collection;

final class EloquentAirShowRepository implements AirShowRepositoryInterface
{
    public function findBySlug(string $slug): ?AirShow
    {
        return AirShow::query()->where('slug', $slug)->first();
    }

    public function published(): Collection
    {
        // Legacy list order (airshows/index.ts): by start_date ascending, with
        // date-unconfirmed shows (empty start_date) forced last. This drives the hub
        // ItemList positions, so it must match the legacy byte-for-byte.
        return AirShow::query()
            ->where('published', true)
            ->orderByRaw("CASE WHEN start_date IS NULL OR start_date = '' THEN 1 ELSE 0 END")
            ->orderBy('start_date')
            ->get();
    }

    public function hub(): ?AirShowHubMeta
    {
        return AirShowHubMeta::query()->first();
    }
}
