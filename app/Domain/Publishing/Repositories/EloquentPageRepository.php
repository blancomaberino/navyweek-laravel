<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

final class EloquentPageRepository implements PageRepositoryInterface
{
    public function upsertPillarPage(string $generationKey, string $defaultUrlPath, array $attributes, ?Model $pageable = null): Page
    {
        // Identity is the stable generation_key, NOT the url_path — so a page is found
        // again across both a per-page rename and a family-wide prefix change.
        $page = Page::query()->firstOrNew(['generation_key' => $generationKey]);

        // Self-healing adoption: a row created before generation_key existed (or before
        // this page carried one) is keyless. Adopt it by its default url_path and stamp
        // the key, so regeneration never inserts a duplicate that collides on the unique
        // url_path. Legacy rows predate editor renames, so they sit at the default path.
        if (! $page->exists) {
            $legacy = Page::query()
                ->whereNull('generation_key')
                ->where('url_path', $defaultUrlPath)
                ->first();
            if ($legacy !== null) {
                $page = $legacy;
                $page->generation_key = $generationKey;
            }
        }

        $isNew = ! $page->exists;
        // The path the page currently lives at (empty string for a new page — never used,
        // since a move is only possible for an existing, non-custom page below).
        $oldUrlPath = $isNew ? '' : $page->url_path;

        // Build clock: the first generation sets date_published; later runs preserve
        // it verbatim and only ever refresh date_modified (never re-stamp published).
        if (! $isNew) {
            unset($attributes['date_published']);
        }

        $page->fill($attributes);

        // Location vs. identity. A new page lands at the family default. An editor
        // rename (url_path_is_custom) is preserved across regeneration. Otherwise the
        // page tracks the family default, so changing config('publishing.paths.*') and
        // re-running generation moves it — and records a 301 from its old path below.
        $moved = false;
        if ($isNew) {
            $page->url_path = $defaultUrlPath;
            $page->url_path_is_custom = false;
        } elseif (! $page->url_path_is_custom && $page->url_path !== $defaultUrlPath) {
            $page->url_path = $defaultUrlPath;
            $moved = true;
        }

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

        // A family-wide prefix change auto-creates the 301 from the old path, reusing
        // the exact redirect-graph rewrite an editor rename fires (CreateRedirectListener).
        if ($moved && $oldUrlPath !== $page->url_path) {
            PageUrlChanged::dispatch($page, $oldUrlPath, $page->url_path);
        }

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

    public function findByGenerationKey(string $generationKey): ?Page
    {
        return Page::query()
            ->where('generation_key', $generationKey)
            ->first();
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

    public function allPublishedDiscountBrandPages(): Collection
    {
        // Every published discount-brand page (with its Offer eager-loaded) for the
        // /discount/ directory ItemList. Ordered by url_path for a deterministic list.
        return Page::query()
            ->where('page_type', PageType::DiscountBrand)
            ->where('is_published', true)
            ->where('pageable_type', (new Offer)->getMorphClass())
            ->with('pageable.connection')
            ->orderBy('url_path')
            ->get();
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

    public function allPublishedIndexable(): Collection
    {
        return Page::query()
            ->where('is_published', true)
            ->where('noindex', false)
            ->orderBy('url_path')
            ->get();
    }

    public function publishedDiscountBrandPagesWithOffer(): Collection
    {
        return Page::query()
            ->where('pages.page_type', PageType::DiscountBrand)
            ->where('pages.is_published', true)
            ->where('pages.pageable_type', (new Offer)->getMorphClass())
            ->with([
                'pageable.connection',
                'pageable.tiers',
                'pageable.audiences',
                'pageable.faqs',
                'pageable.sources',
            ])
            ->join('offers', 'pages.pageable_id', '=', 'offers.id')
            ->join('connections', 'offers.connection_id', '=', 'connections.id')
            ->orderBy('connections.brand')
            ->select('pages.*')
            ->get();
    }

    public function publishedIndexableAuthoredBy(int $userId): Collection
    {
        return $this->publishedIndexableByByline('author_id', $userId);
    }

    public function publishedIndexableReviewedBy(int $userId): Collection
    {
        return $this->publishedIndexableByByline('reviewer_id', $userId);
    }

    /**
     * Shared query for the author profile's "writes for" / "reviews for" lists:
     * published + indexable pages whose given byline column points at the user,
     * minus the author-profile pages themselves, ordered by title.
     *
     * @return Collection<int, Page>
     */
    private function publishedIndexableByByline(string $column, int $userId): Collection
    {
        return Page::query()
            ->where($column, $userId)
            ->where('is_published', true)
            ->where('noindex', false)
            ->where('page_type', '!=', PageType::Author)
            ->orderBy('title')
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
        // An editor rename pins the path: `pages:generate-*` preserves it instead of
        // snapping the page back to its family default.
        $page->url_path_is_custom = true;
        $page->save();
    }
}
