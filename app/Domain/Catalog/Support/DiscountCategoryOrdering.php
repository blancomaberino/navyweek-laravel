<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Models\DiscountCategory;

/**
 * Applies a category's curated ordering — port of the legacy `orderCategoryDiscounts`.
 *
 * The curated lists (`pinned`, `order`, `excluded`) hold **page** slugs
 * (`marriott-military-discount`), not connection slugs (`marriott`), because the
 * legacy registry they came from was keyed by discount page. That is why this
 * lives here rather than in the repository: a Catalog repository returns
 * Connections and cannot see a page's slug, so matching there silently never fired
 * — the curated order was never applied and `excluded` never excluded.
 *
 * The rule: named entries first in their given order, everyone else after them in
 * the order they arrived (callers pass brand A–Z). An explicit full `order` wins
 * over `pinned`, matching the legacy `if (order) … else (pinned) …`.
 */
final class DiscountCategoryOrdering
{
    /**
     * @template TItem
     *
     * @param  array<string, TItem>  $bySlug  page slug => item, already in the fallback order
     * @return array<string, TItem>
     */
    public static function apply(DiscountCategory $category, array $bySlug): array
    {
        $excluded = array_flip($category->excluded ?? []);
        $priority = array_flip($category->order ?: ($category->pinned ?? []));

        $kept = array_filter(
            $bySlug,
            static fn (string $slug): bool => ! isset($excluded[$slug]),
            ARRAY_FILTER_USE_KEY,
        );

        // PHP's sort is stable (guaranteed since 8.0), so comparing on curated
        // position alone leaves everything unnamed in the caller's A–Z order.
        uksort(
            $kept,
            static fn (string $a, string $b): int => ($priority[$a] ?? PHP_INT_MAX) <=> ($priority[$b] ?? PHP_INT_MAX),
        );

        return $kept;
    }
}
