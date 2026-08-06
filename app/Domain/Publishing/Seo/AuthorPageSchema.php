<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Navigation\Support\LinkUrl;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * JSON-LD for an `/authors/{slug}/` author profile page. `SeoHead` prepends
 * Organization, so the emitted graph is `[Organization, Person, BreadcrumbList,
 * ProfilePage, (ItemList)]` — the legacy `AuthorTAlford`/`AuthorErikRivera`
 * ProfilePage/Person graph, now data-driven from the byline `users` row.
 *
 * The Person is the page's `mainEntity`; the optional ItemList enumerates the
 * articles the author has written (their published, indexable pages), so the
 * profile links its E-E-A-T signal to the work it backs. Every URL / `@id` is
 * derived from `$page->url_path` (never a hardcoded `/authors/` literal), so the
 * graph tracks the family prefix (`config('publishing.paths.authors')`).
 */
final class AuthorPageSchema
{
    use BuildsSeoSchema;

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @param  Collection<int, Page>  $authoredPages  Published, indexable pages this user authored.
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, User $author, array $crumbs, Collection $authoredPages): array
    {
        $site = SeoUrl::site();
        $profileUrl = SeoUrl::absolute($page->url_path);
        $personId = "{$profileUrl}#person";
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $person = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => $personId,
            'name' => $author->name,
            'url' => $profileUrl,
        ];
        if ($author->avatar_path !== null && $author->avatar_path !== '') {
            $person['image'] = self::absoluteImage($author->avatar_path);
        }
        if ($author->job_title !== null && $author->job_title !== '') {
            $person['jobTitle'] = $author->job_title;
        }
        // The long-form bio is the richest description; fall back to the byline line.
        $description = $author->bio !== null && $author->bio !== '' ? $author->bio : $author->credentials;
        if ($description !== null && $description !== '') {
            $person['description'] = $description;
        }
        if ($author->knows_about !== null && $author->knows_about !== []) {
            $person['knowsAbout'] = $author->knows_about;
        }
        // Editor-supplied, so it goes through the same scheme allowlist as every
        // rendered href — but OMITTED rather than replaced with the "#" placeholder:
        // publishing a bogus `sameAs` is worse structured data than publishing none.
        if ($author->linkedin_url !== null && LinkUrl::isAllowed($author->linkedin_url)) {
            $person['sameAs'] = [$author->linkedin_url];
        }
        // The publisher the profile writes for, mirroring the legacy `worksFor` entry.
        $person['worksFor'] = ['@id' => "{$site}/#organization"];

        $nodes = [$person, self::breadcrumb($crumbs)];

        $nodes[] = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            '@id' => "{$profileUrl}#profilepage",
            'url' => $profileUrl,
            'name' => "{$author->name} — Author Profile",
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'mainEntity' => ['@id' => $personId],
        ];

        // Link the profile to the work that backs its expertise (omit the node
        // entirely when the author has no live articles, matching the legacy graphs
        // that never emit an empty list).
        if ($authoredPages->isNotEmpty()) {
            $nodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => "Articles by {$author->name}",
                'itemListElement' => $authoredPages->values()->map(static fn (Page $p, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => SeoUrl::absolute($p->url_path),
                    'name' => (string) $p->title,
                ])->all(),
            ];
        }

        return $nodes;
    }
}
