<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Database\Eloquent\Collection;

final class EloquentPageRepository implements PageRepositoryInterface
{
    public function publishedPathExists(string $urlPath): bool
    {
        return Page::query()
            ->where('is_published', true)
            ->where('url_path', $urlPath)
            ->exists();
    }

    public function findPublishedByPath(string $urlPath): ?Page
    {
        return Page::query()
            ->with('pageable')
            ->where('is_published', true)
            ->where('url_path', $urlPath)
            ->first();
    }

    public function liveDiscountBrandPagesForConnections(array $connectionIds): Collection
    {
        return Page::query()
            ->where('page_type', PageType::DiscountBrand)
            ->where('is_published', true)
            ->where('pageable_type', (new Offer)->getMorphClass())
            ->whereIn('pageable_id', Offer::query()->whereIn('connection_id', $connectionIds)->select('id'))
            ->with('pageable')
            // Stable order so a connection with more than one published brand page
            // resolves to the same card deterministically (lowest page id wins).
            ->orderBy('id')
            ->get();
    }
}
