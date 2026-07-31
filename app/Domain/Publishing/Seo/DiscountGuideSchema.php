<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Builds the discount-brand guide's JSON-LD node list, ported 1:1 from the legacy
 * `DiscountDetail.getSeoData()` (`src/page-views/DiscountDetail.tsx`): a Breadcrumb,
 * an Article, a WebSite, the WebPage node, the author + reviewer `Person` nodes, and
 * the FAQPage. `SeoHead` prepends the site Organization, so the emitted list matches
 * the legacy `[Organization, …]` order.
 *
 * The author + reviewer are the page's assigned `users` (byline set from the admin
 * panel), NOT hardcoded persons — so the E-E-A-T Person graph is data-driven. When a
 * page has no author/reviewer assigned, those nodes (and the Article `author` /
 * WebPage `reviewedBy` links) are simply omitted.
 *
 * @phpstan-type Schema array<string, mixed>
 */
final class DiscountGuideSchema
{
    use BuildsSeoSchema;

    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, Offer $offer): array
    {
        $site = SeoUrl::site();
        $slug = $page->slug;
        $pageUrl = "{$site}/discount/{$slug}/";
        $brand = $offer->connection->brand;
        $audienceLabel = $offer->audience_label;
        $title = (string) $page->title;
        $description = (string) $page->meta_description;
        $ogImage = self::ogImage($page);
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $author = $page->author;
        $reviewer = $page->reviewer;
        $authorProfileUrl = self::authorProfileUrl($author);
        $reviewerPersonId = $reviewer !== null ? "{$pageUrl}#reviewer" : null;

        $headline = $audienceLabel !== null
            ? "{$brand} {$audienceLabel} Discount (2026)"
            : "{$brand} Military & Veteran Discount (2026)";
        $crumbLabel = $brand.' '.($audienceLabel ?? 'Military').' Discount';

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'url' => $pageUrl,
            'image' => $ogImage,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'isAccessibleForFree' => true,
            'inLanguage' => 'en-US',
            'publisher' => ['@id' => "{$site}/#organization"],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
        ];
        if ($authorProfileUrl !== null) {
            $article['author'] = ['@id' => $authorProfileUrl.'#person'];
        }

        $webPage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $pageUrl,
            'url' => $pageUrl,
            'name' => $title,
            'isPartOf' => ['@id' => "{$site}/#website"],
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'about' => [
                '@type' => 'Thing',
                'name' => $audienceLabel !== null
                    ? "{$brand} ".mb_strtolower($audienceLabel).' discount'
                    : "{$brand} military and veteran discount",
            ],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $ogImage],
        ];
        if ($reviewerPersonId !== null) {
            $webPage['lastReviewed'] = $dateModified;
            $webPage['reviewedBy'] = ['@id' => $reviewerPersonId];
        }

        // Order matches the legacy graph: Breadcrumb, Article, WebSite, WebPage,
        // author Person, reviewer Person, FAQPage. The two Person nodes are appended
        // only when the page has that byline assigned.
        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Military Discounts', 'url' => '/discount/'],
                ['name' => $crumbLabel, 'url' => "/discount/{$slug}/"],
            ]),
            $article,
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => "{$site}/#website",
                'name' => Config::string('site.name'),
                'url' => $site,
                'publisher' => ['@id' => "{$site}/#organization"],
            ],
            $webPage,
        ];
        if ($author !== null && $authorProfileUrl !== null) {
            $nodes[] = self::authorPerson($site, $author, $authorProfileUrl);
        }
        if ($reviewer !== null) {
            $nodes[] = self::reviewerPerson($reviewer, $reviewerPersonId);
        }
        $nodes[] = self::faqPageFrom($offer->faqs);

        return $nodes;
    }

    /**
     * The author Person, built from the assigned user's public editorial profile.
     * Optional fields (image, jobTitle, description, knowsAbout) are emitted only
     * when the user has them populated.
     *
     * @return array<string, mixed>
     */
    private static function authorPerson(string $site, User $author, string $profileUrl): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => $profileUrl.'#person',
            'name' => $author->name,
            'url' => $profileUrl,
        ];
        if ($author->avatar_path !== null && $author->avatar_path !== '') {
            $node['image'] = $site.$author->avatar_path;
        }
        if ($author->job_title !== null && $author->job_title !== '') {
            $node['jobTitle'] = $author->job_title;
        }
        if ($author->credentials !== null && $author->credentials !== '') {
            $node['description'] = $author->credentials;
        }
        if ($author->knows_about !== null && $author->knows_about !== []) {
            $node['knowsAbout'] = $author->knows_about;
        }

        return $node;
    }

    /**
     * The reviewer Person — a lighter node (name + credentials + profile link),
     * keyed per-page as in the legacy graph.
     *
     * @return array<string, mixed>
     */
    private static function reviewerPerson(User $reviewer, string $personId): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => $personId,
            'name' => $reviewer->name,
        ];
        if ($reviewer->credentials !== null && $reviewer->credentials !== '') {
            $node['description'] = $reviewer->credentials;
        }
        $url = self::authorProfileUrl($reviewer);
        if ($url !== null) {
            $node['url'] = $url;
        }

        return $node;
    }

    /**
     * The `/authors/{slug}/` profile URL for a byline user, or null when the user
     * has no profile slug. Routes through the shared SeoUrl builder so the trailing
     * slash matches every other canonical/@id/breadcrumb URL.
     */
    private static function authorProfileUrl(?User $user): ?string
    {
        if ($user === null || $user->slug === null || $user->slug === '') {
            return null;
        }

        return SeoUrl::absolute("/authors/{$user->slug}");
    }
}
