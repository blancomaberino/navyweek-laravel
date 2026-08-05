<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentAirShowRepository implements AirShowRepositoryInterface
{
    public function findBySlug(string $slug): ?AirShow
    {
        return AirShow::query()->where('slug', $slug)->first();
    }

    public function directory(): Collection
    {
        return $this->inLegacyOrder()->get();
    }

    public function published(): Collection
    {
        return $this->inLegacyOrder()->where('published', true)->get();
    }

    /**
     * Legacy list order (airshows/index.ts): by start_date ascending, with
     * date-unconfirmed shows (empty start_date) forced last. This drives both the
     * hub directory rows and the ItemList positions, so it must match the legacy
     * byte-for-byte.
     *
     * @return Builder<AirShow>
     */
    private function inLegacyOrder(): Builder
    {
        return AirShow::query()
            ->orderByRaw("CASE WHEN start_date IS NULL OR start_date = '' THEN 1 ELSE 0 END")
            ->orderBy('start_date');
    }

    public function hub(): ?AirShowHubMeta
    {
        return AirShowHubMeta::query()->first();
    }
}
