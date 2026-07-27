# NavyWeek Platform (Laravel 13 + Filament v4)

The database-backed rebuild of navyweek.org — a CRM + CMS that replaces the
Astro static site in the parent repo. See the approved plan and the root
`CLAUDE.md` for the full picture. This app lives in `platform/`; the legacy Astro
site remains at the repo root until cutover (Phase 7).

## Requirements

- **PHP 8.4** — the framework requires 8.3+. The machine default (`php`) is MAMP's
  **8.2**, which _cannot_ run this app. Use the Homebrew build explicitly:

  ```sh
  /usr/local/opt/php@8.4/bin/php
  ```

  Consider aliasing it for this project, e.g. `alias php84=/usr/local/opt/php@8.4/bin/php`.
- Composer 2.6+
- Node 20+ (Vite assets; the OG-image satori sidecar arrives in Phase 6)
- Redis (queues via Horizon, response cache — Phase 6)

## Common commands

Run everything under PHP 8.4:

```sh
php84=/usr/local/opt/php@8.4/bin/php

$php84 vendor/bin/pest          # test suite (Pest v4) — the SEO parity gates live here
$php84 -d memory_limit=512M vendor/bin/phpstan analyse   # Larastan (level max)
$php84 vendor/bin/pint          # format (Laravel Pint)
$php84 vendor/bin/pint --test   # format check (CI)
$php84 artisan serve            # dev server
```

Or via composer scripts: `composer pest`, `composer stan`, `composer lint`,
`composer lint:test`.

## Architecture

Domain-first (modular monolith) under `app/Domain/*`:

| Module | Responsibility |
|---|---|
| `Crm` | Connections (brands) + pipeline |
| `Catalog` | Offers, tiers, affiliate links, category hubs, local & seasonal directories |
| `Publishing` | Pages, redirects, SEO/JSON-LD, sitemap |
| `Research` | Briefs, cadence, skill provenance, automation |
| `Pillars` | Bases, ranks, events, air shows, etc. |
| `Shared` | Value objects, enums, cross-cutting DTOs |

Data access is behind **repository interfaces** bound in `DomainServiceProvider`;
business logic is **action classes**; side effects are **domain events +
listeners**; boundary crossings use **spatie/laravel-data** DTOs. The
`trailingSlash: 'always'` invariant is enforced in one place: the
`App\Domain\Shared\ValueObjects\UrlPath` value object.

The living **architecture diagram** (data model + request/redirect pipeline) is at
[`docs/architecture.md`](docs/architecture.md) — keep it current with every change
(see `CLAUDE.md`).

## Domain models & repositories

Every domain model and repository is documented here, one row each. Add a row in the
same PR that introduces the model/repo (enforced by `CLAUDE.md`).

| Type | Class (`App\Domain\…`) | Module | Purpose |
|---|---|---|---|
| Model | `Crm\Models\Connection` | Crm | A brand in the CRM — identity, pipeline status, search-volume metrics, research cadence. First of the four lifecycles split out of the legacy flat `Discount`. |
| Model | `Crm\Models\ConnectionAlias` | Crm | Keyword-variant slug that resolves to a canonical `Connection` (replaces `aliases.json`). |
| Repository | `Crm\Repositories\ConnectionRepositoryInterface` → `EloquentConnectionRepository` | Crm | Connection data access: `findBySlug`, `findByAliasSlug` (alias → canonical), `upsertBySlug` (idempotent import), `dueForReview` (cadence/staleness query). |
| Model | `Catalog\Models\Offer` | Catalog | A discount offer a `Connection` carries — headline facts, verification, and the page-scoped display-JSON units. Many per connection (everyday, promo, membership, advisory). |
| Model | `Catalog\Models\OfferTier` | Catalog | A per-audience savings row on an offer (was the legacy `tiers[]`), individually orderable. |
| Model | `Catalog\Models\RedemptionStep` | Catalog | A numbered redemption step on an offer, discriminated by `channel` (online/in-store); merges the legacy `redeemOnline[]`/`redeemInStore[]`. |
| Repository | `Catalog\Repositories\OfferRepositoryInterface` → `EloquentOfferRepository` | Catalog | Offer reads for a connection: `forConnection` (primary first), `primaryForConnection`. |
| Model | `Catalog\Models\AffiliateNetwork` | Catalog | An affiliate network in the sub-ID registry (port of `NETWORK_SUBID_REGISTRY`); `subid_param` carries the placement token on outbound links. |
| Model | `Catalog\Models\AffiliateLink` | Catalog | An outbound offer link (belongs to a connection and/or offer + a network), tagged with a placement sub-ID at render time. |
| Repository | `Catalog\Repositories\AffiliateNetworkRepositoryInterface` → `EloquentAffiliateNetworkRepository` | Catalog | Network lookup by registry key (`findByKey`), used by the tagger. |
| Repository | `Catalog\Repositories\AffiliateLinkRepositoryInterface` → `EloquentAffiliateLinkRepository` | Catalog | Offer link reads with the network eager-loaded (`forOffer`). |
| Model | `Publishing\Models\Page` | Publishing | Central routing/SEO row keyed on the canonical `url_path`; the DB successor to the build-time route manifest. Carries the head-meta/OG/robots SEO layer, build-clock Article dates, a `json_ld` slot for page-specific extra schema, and the polymorphic `pageable` owner (Offer/Connection/pillar). |
| Model | `Publishing\Models\Redirect` | Publishing | A 301/redirect rule (`from`→`to`, `exact`/`prefix`); subsumes the hand-coded `middleware.ts` rules and is the sink for editor URL changes. |
| Repository | `Publishing\Repositories\PageRepositoryInterface` → `EloquentPageRepository` | Publishing | Page lookups on `url_path`: `publishedPathExists` (middleware route gate) and `findPublishedByPath` (render read, `pageable` eager-loaded). |
| Repository | `Publishing\Repositories\RedirectRepositoryInterface` → `EloquentRedirectRepository` | Publishing | Redirect resolution (exact, then longest-prefix strict-descendant match). |
| Model | `Research\Models\Research` | Research | A sourced, versioned research brief for a connection (fourth lifecycle). Stores the brief `raw_markdown` verbatim plus parsed facts/copy; only `last_verified` traces to research (build-clock rule). |
| Model | `Research\Models\Skill` | Research | A research/QA skill in the provenance registry (`military-discount-research`, `seo-geo`); `content_hash`/`current_version` drive skill-upgrade re-research triggers. |
| Repository | `Research\Repositories\ResearchRepositoryInterface` → `EloquentResearchRepository` | Research | Brief reads for a connection: `latestForConnection` (highest version), `historyForConnection`. |
| Model | `Crm\Models\Audience` | Crm | A first-class eligible cohort (military, veteran, student, …) an Offer targets via the `offer_audience` pivot — the joinable form of the `Crm\Enums\Audience` vocabulary; replaces the legacy 9 `DiscountAudience` booleans (consolidated to 7 cases). |
| Model | `Shared\Models\Source` | Shared | A primary-source citation attached polymorphically (`sourceable`) to an Offer / Research / Page. The shared backbone of the YMYL "every claim traces to a verified source" invariant. |
| Model | `Shared\Models\Faq` | Shared | A question/answer pair attached polymorphically (`faqable`) to a Page / Offer / pillar. Single source for both the rendered FAQ and its FAQPage JSON-LD (parity gate). |
| Model | `Pillars\Models\Base` | Pillars | A naval base / installation (first reference pillar). `region_type` discriminates state-based (CONUS) from overseas (country/territory); cohesive lists are JSON, while FAQs/sources attach via the shared polymorphic tables. |
| Model | `Pillars\Models\UsState` | Pillars | U.S. state/DC lookup (port of `bases/states.ts`) the state-based base hubs group on; `bases.state` is a soft slug FK. |
| Model | `Pillars\Models\OverseasCountry` | Pillars | Overseas host-country lookup (port of `bases/countries.ts`, incl. country-equivalent U.S. territories) the OCONUS base hubs group on; `bases.country_slug` is a soft slug FK. |
| Repository | `Pillars\Repositories\BaseRepositoryInterface` → `EloquentBaseRepository` | Pillars | Base pillar reads mirroring the legacy hubs: `findBySlug`, `forState`, `forCountry`, `forType`, `forRegion`. |
| Model | `Pillars\Models\Rank` | Pillars | A rank / paygrade / designator / rating (2nd reference pillar), single-table inheritance over the `category` discriminator. Common columns + nullable per-category variant groups; self-ref `next`/`previous`/`merged_into`/`related_*` links by slug; FAQs/sources via the shared polymorphic tables. |
| Repository | `Pillars\Repositories\RankRepositoryInterface` → `EloquentRankRepository` | Pillars | Rank pillar reads: `findBySlug` and `forCategory` (the list-page grouping, ordered by paygrade then name). |
| Model | `Catalog\Models\DiscountCategory` | Catalog | A discount category hub (`/discount/<slug>/`) listing every Connection whose `category` matches `match_category`; the ordering overrides (`pinned`/`excluded`/`order`) are soft slug lists resolved at read time. |
| Repository | `Catalog\Repositories\DiscountCategoryRepositoryInterface` → `EloquentDiscountCategoryRepository` | Catalog | Category-hub reads: `findBySlug`, `all`, and `orderedConnections` (the port of `orderCategoryDiscounts` — pinned/excluded/explicit-order). |
| Model | `Catalog\Models\VeteransDayMeal` | Catalog | One brand's Veterans Day free-meal offer (seasonal roundup under `/veterans-day/`). YMYL render gate: shown only when `status = verified` AND a primary `source_url` is present; `discount_slug` soft-links to the brand's `/discount/` guide. |
| Repository | `Catalog\Repositories\VeteransDayMealRepositoryInterface` → `EloquentVeteransDayMealRepository` | Catalog | Meal-roundup reads: `findBySlug` and `verified` (the render-gated, brand-ordered list). |
| Model | `Catalog\Models\LocalDiscount` | Catalog | A local-business discount page (`/discounts/<state>/<city>/<business>/`) — geographic identity, the fixed 5-flag military audience, JSON display lists; `state` soft-FKs the shared `us_states` lookup, FAQs/sources via the shared polymorphic tables. |
| Model | `Catalog\Models\LocalStore` | Catalog | A physical storefront for a `LocalDiscount` (the legacy `locations[]`); first (`sort_order` 0) is the primary NAP + LocalBusiness schema source. |
| Model | `Catalog\Models\LocalStoreHours` | Catalog | One opening-hours span for a `LocalStore` (the legacy `hours[]`), mapped to schema.org `openingHoursSpecification`. |
| Repository | `Catalog\Repositories\LocalDiscountRepositoryInterface` → `EloquentLocalDiscountRepository` | Catalog | Local-page reads: `find` (state/city/business triple) and the `forState`/`forCity` rollups. |
| Model | `Pillars\Models\NavyWeekEvent` | Pillars | A Navy Week stop (folds the legacy `NavyWeekEvent` + `CityData` + `CityExtras`, one row per city). `sequence` is the canonical 1..N order; the rich city-detail block (venues, daily schedule, context) is optional JSON; FAQs/official sources via the shared polymorphic tables. |
| Repository | `Pillars\Repositories\NavyWeekEventRepositoryInterface` → `EloquentNavyWeekEventRepository` | Pillars | Navy Week reads: `findBySlug`, `all` (canonical `sequence` order), `byStatus`. |
| Model | `Pillars\Models\FleetWeek` | Pillars | A Fleet Week city guide (`/fleetweek/<slug>/`) driven by a flexible block template; `has_official_fleet_week`/`has_air_show` + `status` gate which blocks render (Tier-3 cities omit the festival/air-show payloads). Block payloads are JSON; FAQs/sources via the shared polymorphic tables. |
| Repository | `Pillars\Repositories\FleetWeekRepositoryInterface` → `EloquentFleetWeekRepository` | Pillars | Fleet Week reads: `findBySlug`, `all`, `forSeason` (the hub season grouping). |
| Model | `Pillars\Models\AirShow` | Pillars | An air-show event guide (`/air-show/<slug>/`). `published` gates the page; `date_unconfirmed` suppresses Event JSON-LD; `canonical_override` marks a disambiguation page. Block body + schema inputs are JSON; FAQs/sources via the shared polymorphic tables. |
| Model | `Pillars\Models\AirShowHubMeta` | Pillars | The air-show directory hub (`/air-show/`) content — a single editable row keyed on `base_path`; hub FAQs via the shared polymorphic table. |
| Repository | `Pillars\Repositories\AirShowRepositoryInterface` → `EloquentAirShowRepository` | Pillars | Air-show reads: `findBySlug`, `published` (the render gate for the hub listing), and `hub` (the single hub-meta record). |
| Model | `Pillars\Models\JetTeam` | Pillars | A flight-demonstration squadron hub (`/{team}/`, Blue Angels / Thunderbirds; port of `TeamMeta`). `team` is the natural key (enum `TeamId`); the season schedule + city guides are children; hub FAQs via the shared polymorphic table. |
| Model | `Pillars\Models\JetTeamScheduleRow` | Pillars | One stop on a team's season tour (port of `JetTeamScheduleRow`) — factual hub-table data; `slug` links only when a published guide exists, and is non-unique (a city can appear twice a season). |
| Model | `Pillars\Models\JetTeamCity` | Pillars | A published jet-team city guide (`/{team}/{slug}/`; port of `JetTeamCity`). `published` gates the route; the optional `second_*` window handles a twice-a-season city; FAQs/sources via the shared polymorphic tables. |
| Repository | `Pillars\Repositories\JetTeamRepositoryInterface` → `EloquentJetTeamRepository` | Pillars | Jet-team reads: `findTeam`/`findByBasePath`, `allTeams`, `schedule` (authored order), `publishedCities` (the render gate), `findCity` (team + slug). |

Reads for the shared/lookup tables (`audiences`, `sources`, `faqs`, `us_states`,
`overseas_countries`) route through the aggregate they hang off (the Offer
repository eager-loads `audiences` + `sources` on the `/discount/` read path;
sources/faqs are reached via their parent's morph relation; the state/country
lookups via `Base`, and `us_states` also via `LocalDiscount`), so they add no
repository of their own — the pillar and catalog-directory aggregates carry
theirs. The `local_stores` / `local_store_hours` children are likewise reached
through their `LocalDiscount` parent, not a repository of their own.

Supporting types: value object `Shared\ValueObjects\UrlPath`; services `Publishing\Services\LegacyPathResolver`, `Catalog\Services\AffiliateLinkTagger` (port of `withPlacement` — the outbound sub-ID tagging choke point); enums `Crm\Enums\{ConnectionStatus,Audience}`, `Catalog\Enums\{OfferType,VerificationProvider,RedemptionChannel,Placement,MealEligibility,MealRedemption,MealStatus,LocalVerification}`, `Research\Enums\{ResearchStatus,ResearchedBy}`, `Shared\Enums\{ConfidenceLevel,SourceType}` (confidence is shared by briefs + citations), `Pillars\Enums\{BaseType,CombatantCommand,RegionType,RankCategory,DesignatorCommunity,RatingCommunity,HistoricRatingEra,NavyWeekStatus,NavyWeekSourceLevel,FleetWeekSeason,FleetWeekStatus,AirShowStatus,Admission,TeamId,JetTeamStatus}`, `Publishing\Enums\{PageType,RedirectMatchType}`. Seeders: `AffiliateNetworkSeeder` (the 7 networks), `AudienceSeeder` (audience vocabulary from the enum).

## Data migration (Stage A → Stage B)

The legacy TypeScript data in the sibling Astro repo (`../src/data`) is migrated
into these tables in two decoupled stages, handed off through committed JSON
artifacts so Stage B is reproducible without the Astro source present.

- **Stage A — exporter (`database/export/*.ts`, run with `npm run export:legacy`).**
  A `tsx` script imports each legacy registry, maps it **explicitly, one line per
  DB column** (auditable field-by-field against the migrations), lifts inline
  `faqs`/`sources` arrays out for the shared polymorphic tables, and writes
  `database/seed-data/<name>.json`. The artifacts are committed — that JSON is the
  handoff contract.
- **Stage B — importers (`app/Domain/*/Import/*Importer.php` + `app/Console/Commands/Import*Command.php`).**
  `Shared\Import\SeedArtifact::read()` loads an artifact; each domain importer
  upserts it **by slug inside one transaction** and replaces the polymorphic
  children, so it is **idempotent** (re-running updates in place, never
  duplicates). Enum columns are validated on cast — a value the enum doesn't know
  fails the import rather than landing bad data.

Domains migrated so far, each with its own exporter + importer + `import:<domain>`
command and a `*ImportTest` that runs against the real committed artifacts
(asserting counts, enum/JSON/soft-FK/child integrity, and idempotency):

- **bases** (`import:bases`) — `us_states` + `overseas_countries` lookups + `bases`
  with FAQs/sources.
- **ranks** (`import:ranks`) — the single-table-inheritance `ranks` (all 6
  categories), reproducing the slice-8 consolidations (`next_slug`/`previous_slug`,
  the `designator_community`/`rating_community` split, unified `career_path`, the
  `era_tags` enum collection) plus self-ref slug links and FAQs/sources.

Later slices add the remaining domains (catalog/CRM, the events silo) through the
same framework.

## Quality gates (per the rebuild workflow)

Every task runs `/frontend-design` + `/seo-geo` (inform UI/page work) → implement
→ `/simplify` → `/security-review` → commit. Pest (parity gates), Larastan (max),
and Pint must be green before a phase is committed. **Every task ships as a PR.**
