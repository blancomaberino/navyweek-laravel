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
