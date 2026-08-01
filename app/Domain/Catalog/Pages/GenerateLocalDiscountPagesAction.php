<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Pages;

use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Derives the `pages` routing/SEO rows for every local-business discount detail page
 * (`/discounts/{state}/{city}/{business}/`), `pageable` → the LocalDiscount. Each record
 * carries its own meta_title/meta_description + build-clock dates. Idempotent: upserts by
 * url_path and preserves each page's original `date_published` (the build clock lives in
 * the repository). The `/discounts/` rollup hubs are a follow-up.
 */
final class GenerateLocalDiscountPagesAction
{
    public function __construct(
        private readonly LocalDiscountRepositoryInterface $locals,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of local-discount detail pages generated/refreshed
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->locals->all() as $local) {
            $this->pages->upsertPillarPage(
                "/discounts/{$local->state}/{$local->city}/{$local->business_slug}/",
                [
                    'page_type' => PageType::LocalDiscount,
                    'slug' => $local->business_slug,
                    'title' => $local->meta_title,
                    'meta_description' => $local->meta_description,
                    'og_image_path' => $local->og_image,
                    'date_published' => $local->date_published,
                    'date_modified' => $local->date_modified,
                    'is_published' => true,
                ],
                $local,
            );
            $count++;
        }

        return $count;
    }
}
