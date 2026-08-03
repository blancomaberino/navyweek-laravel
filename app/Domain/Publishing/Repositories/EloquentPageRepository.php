<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

final class EloquentPageRepository implements PageRepositoryInterface
{
    public function upsertPillarPage(string $urlPath, array $attributes, ?Model $pageable = null): Page
    {
        $page = Page::query()->firstOrNew(['url_path' => $urlPath]);

        // Build clock: the first generation sets date_published; later runs preserve
        // it verbatim and only ever refresh date_modified (never re-stamp published).
        if ($page->exists) {
            unset($attributes['date_published']);
        }

        $page->fill($attributes);

        // A list/hub page owns no single aggregate — clear any pageable link.
        if ($pageable === null) {
            $page->pageable()->dissociate();
        } else {
            $page->pageable()->associate($pageable);
        }

        // Default editorial byline (author + reviewer), matching PageImporter — so the
        // E-E-A-T Person graph on pages that emit one (air shows, fleet weeks, …) is
        // populated. A page keeps any assignment it already has (an admin override).
        if ($page->author_id === null) {
            $page->author_id = self::userIdBySlug(Config::string('site.editorial.default_author_slug'));
        }
        if ($page->reviewer_id === null) {
            $page->reviewer_id = self::userIdBySlug(Config::string('site.editorial.default_reviewer_slug'));
        }

        $page->save();

        return $page;
    }

    /**
     * The id of the editorial user with this profile slug, or null when none exists.
     * Deliberately a plain per-call lookup (no static memo): pillar-page generation is
     * an occasional, off-hot-path command, and a static cache would leak resolved ids
     * across test cases (which reset the DB but not static state).
     */
    private static function userIdBySlug(string $slug): ?int
    {
        $id = User::query()->where('slug', $slug)->value('id');

        return is_int($id) ? $id : null;
    }

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
