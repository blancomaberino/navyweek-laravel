<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Collection;

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
    /** NavyBasesHub.tsx / NavyBasesOverseas.tsx `LAST_REVIEWED_LABEL`. */
    private const LAST_REVIEWED = '2026-05-25';

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
            'trust_page_label' => 'Navy Bases reference hub',
            // Lead paragraph, KeyFacts card, FAQs and editorial-policy wording are
            // ported verbatim from NavyBasesHub.tsx (L38-59, L152-169, L357-361).
            'body_blocks' => [[
                'type' => 'paragraph',
                'text' => "A directory of United States Navy bases and major installations — from the world's largest naval base at Norfolk, Virginia, to the strategic Pacific Fleet headquarters at Pearl Harbor and the cradle of naval aviation at NAS Pensacola. Browse by state or by installation type to learn the history, mission, and major commands of each base.",
            ]],
            'editorial_source_priority' => 'We cite Commander, Navy Installations Command (CNIC) regional pages, individual installation public-affairs pages on navy.mil, and DoD installation listings first. Historical claims are sourced from the Naval History and Heritage Command. Non-government sources are not used as primary evidence on this page.',
            'editorial_review_cadence' => 'Base totals, state counts, and installation-type counts are re-verified whenever the underlying dataset is updated. Largest-base claims, CO/CNIC structure, and visitor-access framing are re-verified quarterly.',
            'key_facts' => [
                'title' => 'U.S. Navy Bases — Key Facts',
                'facts' => [
                    ['label' => 'Bases catalogued (this directory)', 'value' => (string) $all->count()],
                    ['label' => 'U.S. states represented', 'value' => (string) $stateCount],
                    ['label' => 'Overseas bases catalogued', 'value' => (string) $all->filter(static fn (Base $b): bool => filled($b->country_slug))->count()],
                    ['label' => 'Installation types covered', 'value' => $all->map(static fn (Base $b): string => $b->type->value)->unique()->count().' (Naval Stations, NAS, SUBASE, Joint Bases, specialty)'],
                    ['label' => 'Largest U.S. Navy base', 'value' => 'Naval Station Norfolk, Virginia — ~75,000 active-duty personnel'],
                    ['label' => 'Managed by', 'value' => 'Commander, Navy Installations Command (CNIC)'],
                ],
                'source' => ['label' => 'navy.mil installation pages and DoD installation listings', 'url' => 'https://www.cnic.navy.mil/'],
            ],
        ]);
        $root->replaceFaqs($this->numbered([
            ['How many U.S. Navy bases are there?', 'The U.S. Navy operates dozens of major shore installations across the United States and abroad, including Naval Stations, Naval Air Stations (NAS), Submarine Bases (SUBASE), Joint Bases, and specialty installations like the U.S. Naval Academy. This directory currently catalogues the largest and most historically significant U.S.-based installations, with more added on an ongoing basis.'],
            ['What is the largest Navy base in the United States?', "Naval Station Norfolk in Virginia is the world's largest naval base by population, footprint, and concentration of ships. It is home to U.S. Fleet Forces Command, supports approximately 75 ships and 134 aircraft, and houses around 75,000 active-duty personnel."],
            ['What is the difference between a Naval Station, Naval Base, NAS, and SUBASE?', 'A Naval Station is a general-purpose installation for surface ships. A Naval Base typically refers to a regional complex of multiple installations or a specific surface-ship homeport. A Naval Air Station (NAS) is centered on naval aviation operations and pilot training. A Submarine Base (SUBASE) is dedicated to homing and supporting submarines. A Joint Base is a Department of Defense-managed installation that combines two or more services (e.g., Navy + Air Force at Pearl Harbor-Hickam).'],
            ['Can civilians visit U.S. Navy bases?', "Most active U.S. Navy bases are restricted to authorized personnel and require a sponsor or pre-approved access for visitors. Some bases host on-base museums, memorials, or air shows that are open to the public — for example, the National Naval Aviation Museum at NAS Pensacola, the USS Arizona Memorial at Joint Base Pearl Harbor-Hickam, and U.S. Naval Academy tours in Annapolis. Always check the specific base's public-affairs page before planning a visit."],
            ['Who runs U.S. Navy bases?', 'Navy shore installations are managed by Commander, Navy Installations Command (CNIC), which oversees Navy regions worldwide. Each individual base is led by a Commanding Officer (typically a Navy Captain) who reports through the regional commander. Tenant commands — such as fleet headquarters, ships, air wings, and schools — operate on the base under their own chains of command.'],
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
            'trust_page_label' => 'Navy Bases Overseas reference hub',
            // The live overseas hub carries no KeyFacts block (the root directory does).
            // Lead paragraph, FAQs and editorial-policy wording ported verbatim from
            // NavyBasesOverseas.tsx (L33-58, L202-204, L361-365).
            'body_blocks' => [[
                'type' => 'paragraph',
                'text' => 'The forward-deployed U.S. Navy operates major shore installations across the Indo-Pacific, Europe, the Middle East, and beyond — anchoring American maritime presence from the Sea of Japan to the Mediterranean and the Persian Gulf. Browse by combatant command region or by host nation to learn the history, mission, and host-nation context of each installation.',
            ]],
            'editorial_source_priority' => 'We cite CNIC regional pages, individual installation public-affairs pages on navy.mil, State Department SOFA / treaty pages, and DoD overseas installation listings first. Host-nation context is sourced from official host-nation government pages where relevant. Non-government sources are not used as primary evidence on this page.',
            'editorial_review_cadence' => 'Overseas base totals, host-nation counts, and combatant-command alignment are re-verified whenever the underlying dataset is updated. SOFA references, command-sponsorship framing, and largest-overseas-base claims are re-verified quarterly.',
        ]);
        $overseasPage->replaceFaqs($this->numbered([
            ['How many U.S. Navy bases are overseas?', 'The United States Navy operates major shore installations in roughly a dozen countries outside the continental United States — most concentrated in the Indo-Pacific (PACOM), Europe (EUCOM), and the Middle East (CENTCOM). This directory catalogues the largest and most operationally significant overseas installations and is expanding on a rolling basis.'],
            ['What is the largest U.S. Navy base overseas?', 'Naval Station Yokosuka in Japan is the largest U.S. Navy installation overseas. It is the homeport of the U.S. 7th Fleet and the only forward-deployed U.S. aircraft carrier, supporting approximately 27,000 U.S. personnel and family members.'],
            ['What is the difference between PACOM, EUCOM, CENTCOM, AFRICOM, and SOUTHCOM?', 'These are the U.S. geographic combatant commands: PACOM (U.S. Indo-Pacific Command) covers the Pacific and Indian Ocean rim; EUCOM (U.S. European Command) covers Europe; CENTCOM (U.S. Central Command) covers the Middle East and Central Asia; AFRICOM (U.S. Africa Command) covers Africa; SOUTHCOM (U.S. Southern Command) covers Latin America and the Caribbean. Overseas Navy bases are aligned to the combatant command for their region.'],
            ['What is a Status of Forces Agreement (SOFA)?', 'A Status of Forces Agreement is a treaty or executive agreement between the United States and a host nation that defines the legal status, rights, and obligations of U.S. military personnel and dependents while in that country. SOFAs typically cover criminal jurisdiction, taxation, customs, labor relations, and base access. Each overseas installation operates under either a multilateral SOFA (such as the NATO SOFA) or a bilateral agreement.'],
            ['Can U.S. tourists visit overseas Navy bases?', 'Access to overseas U.S. Navy installations is generally restricted to authorized DoD personnel, family members, contractors, and pre-approved sponsored visitors. Some bases periodically host public open-house events; otherwise, civilian visits require advance coordination through a base sponsor and host-nation entry requirements (passport, visa, or visa-waiver eligibility).'],
            ['Is command sponsorship required for families overseas?', 'For most overseas installations, yes. Command sponsorship designates dependents as officially accompanying the service member on PCS orders, qualifies them for SOFA-status entry into the host nation, and provides access to base housing, DoDEA schools, and medical care. Non-command-sponsored dependents face significant restrictions and additional cost burdens.'],
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
            // FAQ bodies ported verbatim from NavyBasesCountry.tsx L115-138: derived
            // from the country's own installations, SOFA note and naval component.
            $ordered = $countryBases->sortBy('id');
            $names = $ordered->pluck('name')->implode(', ');
            $sofa = $ordered->firstWhere(static fn (Base $b): bool => filled($b->sofa_status))?->sofa_status;
            $component = match ($countryBases->first()?->region) {
                CombatantCommand::Pacom => 'U.S. Pacific Fleet and U.S. 7th Fleet',
                CombatantCommand::Eucom => 'U.S. Naval Forces Europe-Africa and U.S. 6th Fleet',
                CombatantCommand::Centcom => 'U.S. Naval Forces Central Command and U.S. 5th Fleet',
                CombatantCommand::Africom => 'U.S. Naval Forces Europe-Africa (NAVAF) and U.S. 6th Fleet',
                default => 'U.S. Naval Forces Southern Command and U.S. 4th Fleet',
            };
            $countryPage->replaceFaqs($this->numbered([
                ["How many U.S. Navy bases are in {$name}?", $countryBases->count() === 1
                    ? "One major U.S. Navy installation is catalogued in {$name}: {$names}."
                    : "{$countryBases->count()} major U.S. Navy installations are catalogued in {$name}: {$names}."],
                ["What is the SOFA framework for U.S. forces in {$name}?", $sofa !== null
                    ? "{$sofa} See each base's host-nation context for installation-specific arrangements."
                    : "Each U.S. installation in {$name} operates under either a multilateral Status of Forces Agreement or a bilateral arrangement. See each base page for installation-specific details."],
                ["What combatant command covers {$name}?", "{$name} falls under {$command}. The naval component for this region — {$component} — coordinates U.S. naval operations in this area."],
                ["Can U.S. tourists visit U.S. Navy bases in {$name}?", "Access to U.S. Navy installations in {$name} is restricted to authorized DoD personnel, dependents, contractors, and pre-approved sponsored visitors. Civilian visitors must coordinate base entry through their host-nation sponsor in advance and meet all host-nation entry requirements."],
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
