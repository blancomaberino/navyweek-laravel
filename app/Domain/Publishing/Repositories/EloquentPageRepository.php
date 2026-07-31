<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

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

    public function findForUpdate(Page $page): ?Page
    {
        return Page::query()
            ->whereKey($page->getKey())
            ->lockForUpdate()
            ->first();
    }

    public function updateUrlPath(Page $page, string $newUrlPath): void
    {
        $page->url_path = $newUrlPath;
        $page->save();
    }
}
