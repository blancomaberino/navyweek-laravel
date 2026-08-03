<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;

/**
 * JSON-LD for a DB-driven content page (body in `pages.body_blocks`). `SeoHead` prepends
 * Organization, so the emitted graph is `[Organization, …]`.
 *
 * Three shapes, selected by the caller:
 *  - **Breadcrumb-only** (`/privacy/`, `/terms/`, `/contact/`): pass just crumbs.
 *  - **Article + author Person + FAQPage** (`/veterans-day/`): pass `$headline` +
 *    `$emitFaqPage` (the page's `faqs` become the FAQPage).
 *  - **Article + author/reviewer Person + WebPage, NO FAQPage** (`/va-disability/`,
 *    `/veterans-home-care/`): pass `$headline` + `$emitWebPage` (follow-up slice).
 *
 * The author/reviewer Person nodes come from the page's byline `users` via the shared
 * `BuildsSeoSchema` helpers, so the E-E-A-T graph is data-driven.
 */
final class ContentPageSchema
{
    use BuildsSeoSchema;

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return list<array<string, mixed>>
     */
    public static function build(
        Page $page,
        array $crumbs,
        ?string $headline = null,
        string $articleDescription = '',
        bool $emitFaqPage = false,
        bool $emitWebPage = false,
    ): array {
        $nodes = [self::breadcrumb($crumbs)];

        // Breadcrumb-only pages stop here.
        if ($headline === null) {
            return $nodes;
        }

        $site = SeoUrl::site();
        $pageUrl = SeoUrl::absolute($page->url_path);
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $author = $page->author;
        $reviewer = $page->reviewer;
        $authorProfileUrl = self::authorProfileUrl($author);
        $reviewerPersonId = $reviewer !== null ? "{$pageUrl}#reviewer" : null;

        $nodes[] = self::article(
            headline: $headline,
            description: $articleDescription !== '' ? $articleDescription : (string) $page->meta_description,
            path: $page->url_path,
            imagePath: $page->og_image_path,
            datePublished: $datePublished,
            dateModified: $dateModified,
            author: $authorProfileUrl !== null ? ['@id' => $authorProfileUrl.'#person'] : null,
        );

        if ($author !== null && $authorProfileUrl !== null) {
            $nodes[] = self::authorPerson($site, $author, $authorProfileUrl);
        }

        // The YMYL guide variant (va-disability / veterans-home-care): reviewer Person +
        // WebPage, and deliberately NO FAQPage (validate-jsonld REQUIRED_TYPES).
        if ($emitWebPage) {
            if ($reviewer !== null && $reviewerPersonId !== null) {
                $nodes[] = self::reviewerPerson($reviewer, $reviewerPersonId);
            }
            $webPage = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                '@id' => "{$pageUrl}#webpage",
                'url' => $pageUrl,
                'name' => (string) $page->title,
                'isPartOf' => ['@id' => "{$site}/#website"],
                'datePublished' => $datePublished,
                'dateModified' => $dateModified,
            ];
            if ($reviewerPersonId !== null) {
                $webPage['lastReviewed'] = $dateModified;
                $webPage['reviewedBy'] = ['@id' => $reviewerPersonId];
            }
            $nodes[] = $webPage;
        }

        // The reference-article variant (veterans-day): FAQPage from the page's FAQs.
        if ($emitFaqPage) {
            $nodes[] = self::faqPageFrom($page->faqs);
        }

        return $nodes;
    }
}
