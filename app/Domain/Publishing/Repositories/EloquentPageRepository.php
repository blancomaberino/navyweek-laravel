<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;

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

    public function connectionIdsWithPublishedDiscountBrandPage(): array
    {
        /** @var array<int, int> $ids */
        $ids = Offer::query()
            ->whereIn('id', Page::query()
                ->where('page_type', PageType::DiscountBrand)
                ->where('is_published', true)
                ->where('pageable_type', (new Offer)->getMorphClass())
                ->select('pageable_id'))
            ->pluck('connection_id')
            ->all();

        return $ids;
    }
}
