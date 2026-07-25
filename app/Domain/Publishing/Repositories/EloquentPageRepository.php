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
}
