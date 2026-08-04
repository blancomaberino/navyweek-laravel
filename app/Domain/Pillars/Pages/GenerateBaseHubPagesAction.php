<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Generates the Navy-bases hub pages the live site serves but the platform never
 * built: the directory root, the overseas hub, one hub per US state that has at
 * least one base, and one per host country.
 *
 * These hubs own no `pageable` — like the consolidated rank/rating lists they
 * aggregate the whole bases pillar at render, so the state/country they cover is
 * carried by the page's `slug`. Idempotent: keyed on stable generation keys
 * (`base-hub`, `base-hub:overseas`, `base-hub:state:{slug}`,
 * `base-hub:country:{slug}`) so editor renames of `url_path` survive a re-run.
 */
final class GenerateBaseHubPagesAction
{
    private const LAST_REVIEWED = '2026-07-23';

    private const SOURCE_PRIORITY = 'We cite navy.mil and installation public-affairs pages, CNIC, and DoD base guides first; the U.S. Code (Title 10) and host-nation SOFA texts where they apply. Non-government sources are not used as primary evidence on this page.';

    private const REVIEW_CADENCE = 'Installation lists, commands, and host-nation details are re-verified quarterly and at every page update.';

    public function __construct(
        private readonly BaseRepositoryInterface $bases,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of hub pages generated
     */
    public function __invoke(): int
    {
        $all = $this->bases->all();
        $count = 0;

        $stateCount = $this->states($all)->count();
        $countryCount = $this->countries($all)->count();

        $root = $this->upsert('base-hub', PagePaths::root('bases'), [
            'page_type' => PageType::BaseHub,
            'slug' => 'navy-bases',
            'title' => 'U.S. Navy Bases — Every Installation Listed by State & Country | NavyWeek.org',
            'h1' => 'NAVY BASES DIRECTORY',
            'meta_description' => "Every U.S. Navy installation on one page — {$all->count()} bases across {$stateCount} states and {$countryCount} countries, with commands, locations, and host-nation details.",
            'og_image_path' => '/og/bases/hub.png',
            'trust_page_label' => 'Navy Bases directory',
            'key_facts' => [
                'title' => 'U.S. Navy Bases — Key Facts',
                'facts' => [
                    ['label' => 'Installations listed', 'value' => (string) $all->count()],
                    ['label' => 'U.S. states with bases', 'value' => (string) $stateCount],
                    ['label' => 'Host countries', 'value' => (string) $countryCount],
                    ['label' => 'Shore support command', 'value' => 'Commander, Navy Installations Command (CNIC)'],
                ],
                'source' => ['label' => 'Commander, Navy Installations Command (cnic.navy.mil)', 'url' => 'https://www.cnic.navy.mil/'],
            ],
        ]);
        $root->replaceFaqs($this->numbered([
            ['How many U.S. Navy bases are there?', "This directory lists {$all->count()} U.S. Navy installations — {$stateCount} states and {$countryCount} host countries. The Navy also operates smaller reserve centres and detachments that are not listed separately."],
            ['What is the largest Navy base in the United States?', 'Naval Station Norfolk in Virginia is the largest naval installation in the world by personnel and by supported fleet, homeporting a large share of the Atlantic Fleet.'],
            ['What is the difference between a Naval Station, Naval Base, NAS, and SUBASE?', 'A Naval Station supports surface ships and shore commands; a Naval Base groups several installations under one command; a Naval Air Station (NAS) supports aviation squadrons; and a Submarine Base (SUBASE) supports submarines and their crews.'],
            ['Can civilians visit U.S. Navy bases?', 'Access is controlled. Most installations admit civilians only as sponsored visitors or for scheduled public events such as air shows and base open houses, and everyone 18 and over needs government-issued photo ID.'],
            ['Who runs U.S. Navy bases?', 'Commander, Navy Installations Command (CNIC) runs Navy shore installations, with each base under its own commanding officer and regional command.'],
        ]));
        $count++;

        $overseas = $all->filter(static fn (Base $b): bool => filled($b->country_slug));
        $overseasPage = $this->upsert('base-hub:overseas', PagePaths::child('bases', 'overseas'), [
            'page_type' => PageType::BaseOverseasHub,
            'slug' => 'overseas',
            'title' => 'U.S. Navy Bases Overseas — Every Installation by Country | NavyWeek.org',
            'h1' => 'NAVY BASES OVERSEAS',
            'meta_description' => "Every overseas U.S. Navy installation — {$overseas->count()} bases across {$countryCount} countries, with combatant command, SOFA status, and host-nation context.",
            'og_image_path' => '/og/bases/overseas.png',
            'trust_page_label' => 'Navy Bases overseas hub',
            // The live overseas hub carries no KeyFacts block (the root directory does).
        ]);
        $overseasPage->replaceFaqs($this->numbered([
            ['How many U.S. Navy bases are overseas?', "This directory lists {$overseas->count()} overseas U.S. Navy installations across {$countryCount} host countries."],
            ['What is the largest U.S. Navy base overseas?', 'Fleet Activities Yokosuka in Japan is the largest overseas U.S. Navy installation, homeporting the forward-deployed naval forces of the U.S. 7th Fleet.'],
            ['What is the difference between PACOM, EUCOM, CENTCOM, AFRICOM, and SOUTHCOM?', 'They are geographic combatant commands, each responsible for U.S. military activity in its own region — the Indo-Pacific, Europe, the Middle East and Central Asia, Africa, and Latin America respectively.'],
            ['What is a Status of Forces Agreement (SOFA)?', 'A SOFA is the treaty between the United States and a host nation setting the legal status of U.S. personnel there — jurisdiction, taxation, entry requirements, and the terms under which installations operate.'],
            ['Can U.S. tourists visit overseas Navy bases?', 'Generally no. Overseas installations are access-controlled and admit sponsored visitors and authorised personnel only; there are far fewer public-event exceptions than at U.S. bases.'],
            ['Is command sponsorship required for families overseas?', 'For most overseas assignments yes — command sponsorship is what authorises dependents to accompany a service member and grants access to base housing, schools, and medical care.'],
        ]));
        $count++;

        foreach ($this->states($all) as $slug => $stateBases) {
            /** @var Collection<int, Base> $stateBases */
            $name = (string) $stateBases->first()?->state_name;
            $this->upsert("base-hub:state:{$slug}", PagePaths::child('bases', (string) $slug), [
                'page_type' => PageType::BaseStateHub,
                'slug' => (string) $slug,
                'title' => "Navy Bases in {$name} — Every Installation Listed | NavyWeek.org",
                'h1' => mb_strtoupper("Navy Bases in {$name}"),
                'meta_description' => "All {$stateBases->count()} U.S. Navy installations in {$name}, grouped by type, with commands, locations, and key facts.",
                'og_image_path' => "/og/bases/state-{$slug}.png",
                'trust_page_label' => "Navy bases in {$name}",
            ]);
            $count++;
        }

        foreach ($this->countries($all) as $slug => $countryBases) {
            /** @var Collection<int, Base> $countryBases */
            $name = (string) $countryBases->first()?->country;
            $command = $countryBases->first()?->region?->label() ?? 'a geographic combatant command';
            $countryPage = $this->upsert("base-hub:country:{$slug}", PagePaths::child('bases', (string) $slug), [
                'page_type' => PageType::BaseCountryHub,
                'slug' => (string) $slug,
                'title' => "U.S. Navy Bases in {$name} — Every Installation Listed | NavyWeek.org",
                'h1' => mb_strtoupper("Navy Bases in {$name}"),
                'meta_description' => "All {$countryBases->count()} U.S. Navy installations in {$name}, with combatant command, SOFA status, and host-nation context.",
                'og_image_path' => "/og/bases/country-{$slug}.png",
                'trust_page_label' => "Navy bases in {$name}",
            ]);
            $countryPage->replaceFaqs($this->numbered([
                ["How many U.S. Navy bases are in {$name}?", "This directory lists {$countryBases->count()} U.S. Navy ".Str::plural('installation', $countryBases->count())." in {$name}."],
                ["What is the SOFA framework for U.S. forces in {$name}?", "U.S. personnel in {$name} serve under a Status of Forces Agreement that sets their legal status — jurisdiction, entry requirements, and the terms under which the installations operate. Check the installation's own guidance for current details."],
                ["What combatant command covers {$name}?", "U.S. Navy installations in {$name} fall under {$command}."],
                ["Can U.S. tourists visit U.S. Navy bases in {$name}?", 'Generally no. Overseas installations are access-controlled and admit sponsored visitors and authorised personnel only.'],
            ]));
            $count++;
        }

        return $count;
    }

    /**
     * Turn `[question, answer]` pairs into the sort-ordered shape `replaceFaqs()` wants.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<array{question: string, answer: string, sort_order: int}>
     */
    private function numbered(array $pairs): array
    {
        return array_map(
            static fn (array $pair, int $i): array => [
                'question' => $pair[0],
                'answer' => $pair[1],
                'sort_order' => $i + 1,
            ],
            $pairs,
            array_keys($pairs),
        );
    }

    /**
     * US states that have at least one base, keyed by state slug.
     *
     * @param  Collection<int, Base>  $all
     * @return Collection<string, Collection<int, Base>>
     */
    private function states(Collection $all): Collection
    {
        return $all->filter(static fn (Base $b): bool => filled($b->state))
            ->groupBy(static fn (Base $b): string => (string) $b->state)
            ->sortKeys();
    }

    /**
     * Host countries that have at least one base, keyed by country slug.
     *
     * @param  Collection<int, Base>  $all
     * @return Collection<string, Collection<int, Base>>
     */
    private function countries(Collection $all): Collection
    {
        return $all->filter(static fn (Base $b): bool => filled($b->country_slug))
            ->groupBy(static fn (Base $b): string => (string) $b->country_slug)
            ->sortKeys();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(string $generationKey, string $path, array $attributes): Page
    {
        return $this->pages->upsertPillarPage($generationKey, $path, $attributes + [
            'last_reviewed' => self::LAST_REVIEWED,
            'sources_checked' => self::LAST_REVIEWED,
            'shows_reference_backlink' => true,
            'editorial_source_priority' => self::SOURCE_PRIORITY,
            'editorial_review_cadence' => self::REVIEW_CADENCE,
            'is_published' => true,
        ]);
    }
}
