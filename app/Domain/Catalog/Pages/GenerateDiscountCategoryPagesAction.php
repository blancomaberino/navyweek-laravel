<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Pages;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Generates one `/discount/{category}/` hub page per DiscountCategory — the
 * category landing pages the live site serves (Hotels, Flights, Car Rental,
 * Insurance, Moving Companies).
 *
 * The render (discount-category.blade.php + DiscountCategorySchema) and the
 * PageController arm already existed; only the page rows were missing, so these
 * URLs fell through the catch-all to `/`. Idempotent — keyed on the stable
 * `discount-category:{slug}` generation key, so an editor rename of the
 * `url_path` survives a re-run.
 */
final class GenerateDiscountCategoryPagesAction
{
    public function __construct(
        private readonly DiscountCategoryRepositoryInterface $categories,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of category hub pages generated
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->categories->all() as $category) {
            $page = $this->pages->upsertPillarPage(
                "discount-category:{$category->slug}",
                PagePaths::child('discounts', $category->slug),
                $this->attributesFor($category),
            );
            $page->pageable()->associate($category)->save();
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(DiscountCategory $category): array
    {
        return [
            'page_type' => PageType::DiscountCategoryHub,
            'slug' => $category->slug,
            'title' => $category->meta_title ?? "{$category->name} Military Discounts | NavyWeek.org",
            'h1' => $category->h1,
            'meta_description' => $category->meta_description,
            'og_image_path' => $category->og_image,
            'date_published' => $category->date_published,
            'date_modified' => $category->date_modified,
            'last_reviewed' => $category->last_verified,
            'sources_checked' => $category->last_verified,
            'trust_page_label' => "{$category->name} military discounts hub",
            'editorial_source_priority' => "We cite each brand's own official discount page and its verification partner (ID.me, GovX, SheerID) first. Third-party coupon aggregators are never used as primary evidence.",
            'editorial_review_cadence' => 'Category listings are re-verified whenever a member brand’s offer changes and at every page update.',
            'is_published' => true,
        ];
    }
}
