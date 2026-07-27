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
        return AirShow::query()
            ->where('published', true)
            ->orderBy('short_name')
            ->get();
    }

    public function hub(): ?AirShowHubMeta
    {
        return AirShowHubMeta::query()->first();
    }
}
