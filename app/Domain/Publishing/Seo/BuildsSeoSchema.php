<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Shared JSON-LD building blocks used by more than one page-schema serializer
 * (discount brand guide + category hub). Keeps the date format, og:image fallback,
 * and BreadcrumbList shape in one place so the serializers can't silently diverge.
 */
trait BuildsSeoSchema
{
    /** ISO-8601 date (`Y-m-d`) for a nullable/mixed date attribute, or '' when absent. */
    private static function isoDate(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : '';
    }

    /**
     * The absolute og:image URL for a page's JSON-LD `image` — the page's own image,
     * falling back to the site default, via the shared resolver.
     */
    private static function ogImage(Page $page): string
    {
        return self::absoluteImage($page->og_image_path);
    }

    /**
     * Absolute URL for a JSON-LD `image` path: an `http(s)` path is kept as-is, a
     * site-relative path is prefixed with the canonical origin, and a null/empty path
     * falls back to the site default. Matches the legacy `buildArticleSchema` image rule
     * (src/lib/seo.ts).
     *
     * NOTE: this is intentionally http-aware, whereas the head `og:image` in
     * {@see SeoHead} is NOT (it mirrors legacy `buildSEOData`). For a site-relative or
     * null path the two agree; do not "unify" them to remove the asymmetry, or the
     * og:image byte-parity with the legacy head breaks.
     */
    private static function absoluteImage(?string $path): string
    {
        $site = SeoUrl::site();

        if ($path === null || $path === '') {
            return $site.Config::string('site.default_og_image');
        }

        return str_starts_with($path, 'http') ? $path : $site.$path;
    }

    /**
     * The generic schema.org Article node, ported 1:1 from the legacy
     * `buildArticleSchema` (`src/lib/seo.ts`): org-authored by default, no WebPage /
     * WebSite / Person graph (that richer graph is the discount guide's, in
     * {@see DiscountGuideSchema}). Pillar pages (bases, ranks, events) emit this
     * lighter Article. The image path is absolutized the same way as the head
     * og:image; a null/empty image falls back to the site default.
     *
     * @param  array<string, mixed>|null  $author  Author node/ref; defaults to the Organization @id.
     * @return array<string, mixed>
     */
    private static function article(
        string $headline,
        string $description,
        string $path,
        ?string $imagePath,
        string $datePublished,
        string $dateModified,
        ?array $author = null,
    ): array {
        $site = SeoUrl::site();
        $url = SeoUrl::absolute($path);
        $image = self::absoluteImage($imagePath);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $headline,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'isAccessibleForFree' => true,
            'inLanguage' => 'en-US',
            'author' => $author ?? ['@id' => "{$site}/#organization"],
            'publisher' => ['@id' => "{$site}/#organization"],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];
    }

    /**
     * A schema.org FAQPage node from ordered `{question, answer}` FAQ models —
     * ported from the legacy `buildFAQSchema`. The caller decides whether to emit
     * it at all (the legacy graphs omit the node entirely when there are no FAQs).
     *
     * @param  iterable<object{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    private static function faqPageFrom(iterable $faqs): array
    {
        $questions = [];
        foreach ($faqs as $faq) {
            $questions[] = [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        ];
    }

    /**
     * The author Person node, built from a byline user's public editorial profile.
     * Optional fields (image, jobTitle, description, knowsAbout) are emitted only when
     * populated. Shared by the discount-guide + local-business detail graphs.
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
     * The reviewer Person — a lighter node (name + credentials + profile link), keyed
     * per-page as in the legacy graph.
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
     * The `/authors/{slug}/` profile URL for a byline user, or null when the user has
     * no profile slug. Routes through {@see SeoUrl::absolute} so the trailing slash
     * matches every other canonical/@id/breadcrumb URL.
     */
    private static function authorProfileUrl(?User $user): ?string
    {
        if ($user === null || $user->slug === null || $user->slug === '') {
            return null;
        }

        return SeoUrl::absolute("/authors/{$user->slug}");
    }

    /**
     * The site-wide WebSite node (port of legacy `buildWebSiteSchema`): id `#website`,
     * publisher → the Organization. Emitted by the home landing graph.
     *
     * @return array<string, mixed>
     */
    private static function webSite(): array
    {
        $site = SeoUrl::site();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => "{$site}/#website",
            'name' => Config::string('site.name'),
            'url' => $site,
            'publisher' => ['@id' => "{$site}/#organization"],
        ];
    }

    /**
     * The United States Navy GovernmentOrganization node (port of legacy
     * `buildUsNavyOrganizationSchema`), id `#us-navy`. Shared by the Navy Week city
     * graph and the home landing graph.
     *
     * @return array<string, mixed>
     */
    private static function usNavyOrganization(): array
    {
        $site = SeoUrl::site();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            '@id' => "{$site}/#us-navy",
            'name' => 'United States Navy',
            'alternateName' => 'U.S. Navy',
            'url' => 'https://www.navy.mil/',
            'sameAs' => [
                'https://www.navy.mil/',
                'https://en.wikipedia.org/wiki/United_States_Navy',
            ],
        ];
    }

    /**
     * The Navy Office of Community Outreach GovernmentOrganization node (port of legacy
     * `buildNavcoOrganizationSchema`), id `#navco`, parented to `#us-navy`. Shared by the
     * Navy Week city graph and the home landing graph.
     *
     * @return array<string, mixed>
     */
    private static function navcoOrganization(): array
    {
        $site = SeoUrl::site();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            '@id' => "{$site}/#navco",
            'name' => 'Navy Office of Community Outreach',
            'alternateName' => 'NAVCO',
            'url' => 'https://outreach.navy.mil/Navy-Weeks/',
            'description' => "The Navy Office of Community Outreach (NAVCO), based in Millington, TN, manages the U.S. Navy Week program — the Navy's flagship community outreach effort in cities without a significant Navy presence.",
            'parentOrganization' => ['@id' => "{$site}/#us-navy"],
            'sameAs' => [
                'https://outreach.navy.mil/Navy-Weeks/',
                'https://outreach.navy.mil/',
            ],
        ];
    }

    /**
     * A schema.org BreadcrumbList node from ordered `{name, url}` crumbs. Each crumb
     * URL runs through {@see SeoUrl::absolute} so the trailing slash matches every
     * other canonical/@id URL.
     *
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private static function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => SeoUrl::absolute($item['url']),
                ],
                $items,
                array_keys($items),
            ),
        ];
    }
}
