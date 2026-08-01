<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;

/**
 * JSON-LD for a DB-driven content page (the editorial pages whose body lives in
 * `pages.body_blocks`). This foundation slice covers the **Breadcrumb-only** pages
 * (`/privacy/`, `/terms/`, `/contact/`) — `SeoHead` prepends Organization, so the graph
 * is `[Organization, BreadcrumbList]`.
 *
 * The richer content-page graphs layer on in later slices: `/veterans-day/` adds an
 * Article + author Person + FAQPage; `/va-disability/` and `/veterans-home-care/` add an
 * Article + author/reviewer Person + WebPage (and, per the validate-jsonld REQUIRED_TYPES
 * rule, deliberately NO FAQPage). Those reuse the same `BuildsSeoSchema` helpers.
 */
final class ContentPageSchema
{
    use BuildsSeoSchema;

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, array $crumbs): array
    {
        return [
            self::breadcrumb($crumbs),
        ];
    }
}
