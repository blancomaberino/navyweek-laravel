<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\LocalDiscount;
use Illuminate\Support\Collection;

final class EloquentLocalDiscountRepository implements LocalDiscountRepositoryInterface
{
    public function find(string $state, string $city, string $businessSlug): ?LocalDiscount
    {
        return LocalDiscount::query()
            ->where('state', $state)
            ->where('city', $city)
            ->where('business_slug', $businessSlug)
            ->first();
    }

    public function all(): Collection
    {
        return LocalDiscount::query()
            ->with('stores.hours')
            ->orderBy('state')
            ->orderBy('city')
            ->orderBy('company')
            ->get();
    }

    public function states(): Collection
    {
        return LocalDiscount::query()
            ->selectRaw('state, state_name, COUNT(*) as aggregate_count')
            ->groupBy('state', 'state_name')
            ->orderBy('state_name')
            ->get()
            ->map(static function (LocalDiscount $row): array {
                $count = $row->getAttribute('aggregate_count');

                return [
                    'state' => $row->state,
                    'state_name' => $row->state_name,
                    'count' => is_numeric($count) ? (int) $count : 0,
                ];
            });
    }

    public function forState(string $stateSlug): Collection
    {
        return LocalDiscount::query()
            ->where('state', $stateSlug)
            ->orderBy('company')
            ->get();
    }

    public function forCity(string $stateSlug, string $citySlug): Collection
    {
        return LocalDiscount::query()
            ->where('state', $stateSlug)
            ->where('city', $citySlug)
            ->orderBy('company')
            ->get();
    }
}
