<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * JSON-LD for the jet-team pages (Blue Angels / Thunderbirds), a 1:1 port of
 * `JetTeamHub`/`JetTeamDetail` getSeoData + `src/data/jetteams/seo.ts`.
 *
 * Team hub (`/{team}/`): BreadcrumbList → Article → ItemList → FAQPage. The ItemList
 * has one **name-only** entry per season schedule stop (no url), unlike the other hubs.
 *
 * City guide (`/{team}/{slug}/`), after Organization prepend:
 *   BreadcrumbList → Article → WebPage → Person(author) → Person(reviewer)
 *   → FAQPage → Event
 * The Event is a plain `Event` (not SportsEvent) with performer = the team, a Place
 * location, and NO offers/organizer. Author/reviewer Person nodes are driven by the
 * page byline (seeded t-alford/erik-rivera); the author `knowsAbout` is the
 * jet-team-specific list. City guides are published-only (the caller re-checks).
 *
 * NOTE: the guide-graph helpers (webPage/authorPerson/reviewerPerson) mirror
 * AirShow/FleetWeek — a shared "guide graph" trait across those three schemas is a
 * worthwhile follow-up (this is the 3rd consumer).
 */
final class JetTeamPageSchema
{
    use BuildsSeoSchema;

    /**
     * @param  Collection<int, JetTeamScheduleRow>  $schedule
     * @return list<array<string, mixed>>
     */
    public static function buildHub(Page $page, JetTeam $team, Collection $schedule): array
    {
        $hubPath = "{$team->base_path}/";

        return [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $team->name, 'url' => $hubPath],
            ]),
            self::article(
                headline: $team->seo_headline,
                description: $team->meta_description,
                path: $hubPath,
                imagePath: $team->og_image,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $team->seo_headline,
                'url' => SeoUrl::absolute($hubPath),
                'numberOfItems' => $schedule->count(),
                'itemListElement' => $schedule->values()->map(static fn ($row, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => "{$row->show} — {$row->city}, {$row->state} ({$row->dates_label})",
                ])->all(),
            ],
            self::faqPageFrom($team->faqs),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildCity(Page $page, JetTeamCity $city, JetTeam $team): array
    {
        $site = SeoUrl::site();
        $path = "{$team->base_path}/{$city->slug}/";
        $url = "{$site}{$path}";
        $author = $page->author;
        $reviewer = $page->reviewer;

        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $team->name, 'url' => "{$team->base_path}/"],
                ['name' => $city->city, 'url' => $path],
            ]),
            self::article(
                headline: $city->h1,
                description: $city->meta_description,
                path: $path,
                imagePath: $city->og_image,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
                author: $author !== null ? ['@id' => "{$site}/authors/{$author->slug}/#person"] : null,
            ),
            self::webPage($site, $url, $city, $team, $page, $reviewer !== null),
        ];

        if ($author !== null) {
            $nodes[] = self::authorPerson($site, $author, $city, $team);
        }
        if ($reviewer !== null) {
            $nodes[] = self::reviewerPerson($site, $url, $reviewer);
        }

        $nodes[] = self::faqPageFrom($city->faqs);
        $nodes[] = self::eventNode($site, $url, $city, $team);

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private static function webPage(string $site, string $url, JetTeamCity $city, JetTeam $team, Page $page, bool $hasReviewer): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => "{$url}#webpage",
            'url' => $url,
            'name' => $city->meta_title,
            'isPartOf' => ['@id' => "{$site}/#website"],
            'datePublished' => self::isoDate($page->date_published),
            'dateModified' => self::isoDate($page->date_modified),
        ];
        if ($hasReviewer) {
            $node['lastReviewed'] = self::isoDate($page->date_modified);
            $node['reviewedBy'] = ['@id' => "{$url}#reviewer"];
        }
        $node['about'] = ['@type' => 'Thing', 'name' => "{$team->name} {$city->city} {$city->year}"];
        $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $site.$city->og_image];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private static function authorPerson(string $site, User $author, JetTeamCity $city, JetTeam $team): array
    {
        $profileUrl = "{$site}/authors/{$author->slug}/";

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => "{$profileUrl}#person",
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
        $node['knowsAbout'] = [$team->name, $team->branch, 'air shows', "{$city->city} air show", $city->show];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewerPerson(string $site, string $url, User $reviewer): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => "{$url}#reviewer",
            'name' => $reviewer->name,
            'description' => (string) $reviewer->credentials,
            'url' => "{$site}/authors/{$reviewer->slug}/",
        ];
    }

    /**
     * The show-stop Event (`buildJetTeamEventSchema`) — a plain Event with the team as
     * performer, a Place location, and no offers/organizer.
     *
     * @return array<string, mixed>
     */
    private static function eventNode(string $site, string $url, JetTeamCity $city, JetTeam $team): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => "{$team->name} — {$city->show} {$city->year}",
            'startDate' => $city->start_date->format('Y-m-d'),
            'endDate' => $city->end_date->format('Y-m-d'),
            'eventStatus' => self::eventStatusUrl($city->status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
            'image' => $site.$city->og_image,
            'description' => $city->meta_description,
            'location' => [
                '@type' => 'Place',
                'name' => $city->venue,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $city->city,
                    'addressRegion' => $city->state,
                    'addressCountry' => 'US',
                ],
            ],
            'performer' => [
                '@type' => 'PerformingGroup',
                'name' => $team->full_name,
            ],
            'isAccessibleForFree' => $city->admission === Admission::Free,
        ];
    }

    private static function eventStatusUrl(JetTeamStatus $status): string
    {
        return match ($status) {
            JetTeamStatus::Scheduled, JetTeamStatus::Completed => 'https://schema.org/EventScheduled',
            JetTeamStatus::Cancelled => 'https://schema.org/EventCancelled',
            JetTeamStatus::Postponed => 'https://schema.org/EventPostponed',
        };
    }
}
