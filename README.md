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

## Front-end & design system

The visual language ("Road Trip to 250" — Fleet Navy `#0A1628` background, Service Gold
`#C9A84C` accents, Bebas Neue display / IBM Plex Sans + Mono) is ported from the legacy
`src/styles/global.css` into **`resources/css/app.css`** (design tokens + base typography +
component vocabulary + the header/footer chrome). The base layout **must** `@vite` this
stylesheet — a page with no linked CSS renders as raw HTML (see the "Visual verification"
rule in `CLAUDE.md`). Site chrome lives in `resources/views/partials/{header,footer}.blade.php`
(CSS-only, no JS). Build assets with `npm run build` (Vite 8; on Apple Silicon the
`@rolldown/binding-darwin-arm64` native binding may need a one-off install — see the local-run notes).

## Browser (E2E) tests — Laravel Dusk

Every user-facing page ships a **Dusk** end-to-end test (`tests/Browser/*`) that loads the
real page in headless Chrome and asserts what Pest feature tests cannot — that the design
system is applied (stylesheet linked, Fleet-Navy body, Bebas-Neue headings) and interactive
controls work. Dusk tests are excluded from the default `pest` run; run them separately:

```sh
php84 artisan serve --host=127.0.0.1 --port=8000 &          # serve the app + real DB
vendor/bin/../laravel/dusk/bin/chromedriver-mac-intel --port=9515 &  # arm64: start driver manually
APP_URL=http://127.0.0.1:8000 php84 artisan dusk            # run the browser suite
```

(On Linux CI the matching chromedriver auto-starts; the manual step is an Apple-Silicon quirk.)

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
| Repository | `Crm\Repositories\ConnectionRepositoryInterface` → `EloquentConnectionRepository` | Crm | Connection data access: `findById` (plain read for the research job), `findBySlug`, `findByAliasSlug` (alias → canonical), `upsertBySlug` (idempotent import), `dueForReview` (cadence/staleness query), reconcile drift reads (`publishedPagesMissingResearch`, `liveNotMarkedPublished`, `duplicatesNotMarkedDuplicate`), pipeline aggregates (`total`, `countByStatus`, `dueForReviewCount`, `backlogCount`) for the dashboard, `forStatus` (top-N by volume in one status — the pipeline board's columns), and bulk mutations (`updateStatusForIds`, `clearBacklogForIds`) for the CRM bulk actions and the board's card moves. |
| Model | `Catalog\Models\Offer` | Catalog | A discount offer a `Connection` carries — headline facts, verification, and the page-scoped display-JSON units. Many per connection (everyday, promo, membership, advisory). |
| Model | `Catalog\Models\OfferTier` | Catalog | A per-audience savings row on an offer (was the legacy `tiers[]`), individually orderable. |
| Model | `Catalog\Models\RedemptionStep` | Catalog | A numbered redemption step on an offer, discriminated by `channel` (online/in-store); merges the legacy `redeemOnline[]`/`redeemInStore[]`. |
| Repository | `Catalog\Repositories\OfferRepositoryInterface` → `EloquentOfferRepository` | Catalog | Offer reads for a connection: `forConnection` (primary first), `primaryForConnection`. |
| Model | `Catalog\Models\AffiliateNetwork` | Catalog | An affiliate network in the sub-ID registry (port of `NETWORK_SUBID_REGISTRY`); `subid_param` carries the placement token on outbound links. |
| Model | `Catalog\Models\AffiliateLink` | Catalog | An outbound offer link (belongs to a connection and/or offer + a network), tagged with a placement sub-ID at render time. |
| Repository | `Catalog\Repositories\AffiliateNetworkRepositoryInterface` → `EloquentAffiliateNetworkRepository` | Catalog | Network lookup by registry key (`findByKey`), used by the tagger. |
| Repository | `Catalog\Repositories\AffiliateLinkRepositoryInterface` → `EloquentAffiliateLinkRepository` | Catalog | Offer link reads with the network eager-loaded (`forOffer`). |
| Model | `Publishing\Models\Page` | Publishing | Central routing/SEO row served at the canonical `url_path`; the DB successor to the build-time route manifest. Identity vs. location: a generated page is identified by its stable `generation_key` (assigned by `pages:generate-*`) while `url_path` is mutable — an editor rename sets `url_path_is_custom` (preserved on regeneration) and a family-prefix change moves every non-custom page. Carries the head-meta/OG/robots SEO layer, build-clock Article dates, a `json_ld` slot for page-specific extra schema, a CMS-editable `body_blocks` JSON slot (ordered typed blocks — the DB-driven content pages' body, managed in Filament), the polymorphic `pageable` owner (Offer/Connection/pillar), and the editorial byline (`author_id`/`reviewer_id` → `users`, nullable) that drives the guide's Article `author` + WebPage `reviewedBy` Person JSON-LD. |
| Model | `App\Models\User` | (root) | A login account that doubles as the editorial byline. Beyond auth, carries the PUBLIC author profile the discount-guide `Person` JSON-LD + the `/authors/{slug}/` profile page read (`slug` → `/authors/{slug}/`, `job_title`, `credentials`, `avatar_path`, `knows_about`, long-form `bio`, `linkedin_url`); `authoredPages`/`reviewedPages` are the inverse of `Page.author`/`reviewer`. Replaces the legacy hardcoded author/reviewer persons — now assignable per-page from the admin panel. |
| Model | `Publishing\Models\Redirect` | Publishing | A 301/redirect rule (`from`→`to`, `exact`/`prefix`); subsumes the hand-coded `middleware.ts` rules and is the sink for editor URL changes. |
| Repository | `Publishing\Repositories\PageRepositoryInterface` → `EloquentPageRepository` | Publishing | Page lookups on `url_path`: `publishedPathExists` (middleware route gate), `findPublishedByPath` (render read, `pageable` eager-loaded), `connectionIdsWithPublishedDiscountBrandPage` (reconcile's live-brand set), `liveDiscountBrandPagesForConnections` (the category hub's live-brand grid), `allPublishedDiscountBrandPages` (the /discount/ directory ItemList), `allPublishedIndexable` (the sitemap route universe — published + not `noindex`, url-ordered), `publishedDiscountBrandPagesWithOffer` (the LLM feed's discount section — offer + connection/tiers/audiences/faqs/sources eager-loaded, brand-ordered), the editable-URL rename pair `findForUpdate`/`updateUrlPath` (the latter pins `url_path_is_custom`), `findByGenerationKey` (identity lookup for the content-page body-seed guard), `publishedIndexableAuthoredBy`/`publishedIndexableReviewedBy` (the author profile's "writes for" / "reviews for" lists + the authored-articles ItemList — published + indexable, author-profile pages excluded, title-ordered), and `upsertPillarPage(generationKey, defaultUrlPath, …)` (idempotent pillar-page generation keyed on the stable `generation_key`, not the path — so it moves a non-custom page and emits a 301 when the family prefix changes, preserves an editor rename, honors the build clock, accepts a null pageable for list/hub pages, and applies the default editorial byline). |
| Repository | `Publishing\Repositories\RedirectRepositoryInterface` → `EloquentRedirectRepository` | Publishing | Redirect resolution (exact, then longest-prefix strict-descendant match). |
| Repository | `Publishing\Repositories\AuthorRepositoryInterface` → `EloquentAuthorRepository` | Publishing | Editorial-user reads for the author-page family: `publicProfiles` (every `users` row with a public profile `slug` — the accounts `pages:generate-authors` builds an `/authors/{slug}/` page for), name-ordered. Keeps the generation action off `User::query()`. |
| Model | `Research\Models\Research` | Research | A sourced, versioned research brief for a connection (fourth lifecycle). Stores the brief `raw_markdown` verbatim plus parsed facts/copy; only `last_verified` traces to research (build-clock rule). |
| Model | `Research\Models\Skill` | Research | A research/QA skill in the provenance registry (`military-discount-research`, `seo-geo`); `content_hash`/`current_version` drive skill-upgrade re-research triggers. |
| Repository | `Research\Repositories\ResearchRepositoryInterface` → `EloquentResearchRepository` | Research | Brief reads/writes for a connection: `latestForConnection` (highest version), `historyForConnection`, `connectionIdsWithBriefs` (reconcile), `connectionIdsWithStaleSkillProvenance` (latest brief cited a superseded skill version — drives `skills:detect-updates`), the headless-run writes `createDraftRun` (next-version Draft + skill-provenance pivot) / `storeRawOutput` (verbatim brief), plus the locked cadence writes `markVerified` / `markStale`. |
| Repository | `Research\Repositories\SkillRepositoryInterface` → `EloquentSkillRepository` | Research | Skill-registry access: `all` (ordered by key, drives `skills:check-hashes`); `recordContentHash` (locks the row, stores the new hash + bumps `current_version` on a real change) for the `skills:detect-updates` write-detector; `findByKey` (stamp a skill's current version onto a research run). |
| Model | `Crm\Models\Audience` | Crm | A first-class eligible cohort (military, veteran, student, …) an Offer targets via the `offer_audience` pivot — the joinable form of the `Crm\Enums\Audience` vocabulary; replaces the legacy 9 `DiscountAudience` booleans (consolidated to 7 cases). |
| Model | `Shared\Models\Source` | Shared | A primary-source citation attached polymorphically (`sourceable`) to an Offer / Research / Page. The shared backbone of the YMYL "every claim traces to a verified source" invariant. |
| Model | `Shared\Models\Faq` | Shared | A question/answer pair attached polymorphically (`faqable`) to a Page / Offer / pillar. Single source for both the rendered FAQ and its FAQPage JSON-LD (parity gate). |
| Model | `Pillars\Models\Base` | Pillars | A naval base / installation (first reference pillar). `region_type` discriminates state-based (CONUS) from overseas (country/territory); cohesive lists are JSON, while FAQs/sources attach via the shared polymorphic tables. |
| Model | `Pillars\Models\UsState` | Pillars | U.S. state/DC lookup (port of `bases/states.ts`) the state-based base hubs group on; `bases.state` is a soft slug FK. |
| Model | `Pillars\Models\OverseasCountry` | Pillars | Overseas host-country lookup (port of `bases/countries.ts`, incl. country-equivalent U.S. territories) the OCONUS base hubs group on; `bases.country_slug` is a soft slug FK. |
| Repository | `Pillars\Repositories\BaseRepositoryInterface` → `EloquentBaseRepository` | Pillars | Base pillar reads mirroring the legacy hubs: `findBySlug`, `all` (page generation), `forState`, `forCountry`, `forType`, `forRegion`. |
| Model | `Pillars\Models\Rank` | Pillars | A rank / paygrade / designator / rating (2nd reference pillar), single-table inheritance over the `category` discriminator. Common columns + nullable per-category variant groups; self-ref `next`/`previous`/`merged_into`/`related_*` links by slug; FAQs/sources via the shared polymorphic tables. |
| Repository | `Pillars\Repositories\RankRepositoryInterface` → `EloquentRankRepository` | Pillars | Rank pillar reads: `findBySlug`, `forCategory` (lexical grouping), `forCategoryByPaygrade` (true numeric paygrade order for the `/navy-ranks/` sections), and `activeRatings`/`historicRatings` (the `/navy-ratings/` groups). |
| Model | `Catalog\Models\DiscountCategory` | Catalog | A discount category hub (`/discount/<slug>/`) listing every Connection whose `category` matches `match_category`; the ordering overrides (`pinned`/`excluded`/`order`) are soft slug lists resolved at read time. |
| Repository | `Catalog\Repositories\DiscountCategoryRepositoryInterface` → `EloquentDiscountCategoryRepository` | Catalog | Category-hub reads: `findBySlug`, `all`, and `orderedConnections` (the port of `orderCategoryDiscounts` — pinned/excluded/explicit-order). |
| Model | `Catalog\Models\VeteransDayMeal` | Catalog | One brand's Veterans Day free-meal offer (seasonal roundup under `/veterans-day/`). YMYL render gate: shown only when `status = verified` AND a primary `source_url` is present; `discount_slug` soft-links to the brand's `/discount/` guide. |
| Repository | `Catalog\Repositories\VeteransDayMealRepositoryInterface` → `EloquentVeteransDayMealRepository` | Catalog | Meal-roundup reads: `findBySlug` and `verified` (the render-gated, brand-ordered list). |
| Model | `Catalog\Models\LocalDiscount` | Catalog | A local-business discount page (`/discounts/<state>/<city>/<business>/`) — geographic identity, the fixed 5-flag military audience, JSON display lists; `state` soft-FKs the shared `us_states` lookup, FAQs/sources via the shared polymorphic tables. |
| Model | `Catalog\Models\LocalStore` | Catalog | A physical storefront for a `LocalDiscount` (the legacy `locations[]`); first (`sort_order` 0) is the primary NAP + LocalBusiness schema source. |
| Model | `Catalog\Models\LocalStoreHours` | Catalog | One opening-hours span for a `LocalStore` (the legacy `hours[]`), mapped to schema.org `openingHoursSpecification`. |
| Repository | `Catalog\Repositories\LocalDiscountRepositoryInterface` → `EloquentLocalDiscountRepository` | Catalog | Local-page reads: `find` (state/city/business triple), the `forState`/`forCity` rollups, `states` (distinct states + counts for the `/discounts/` root hub), and `all` (stores+hours eager-loaded — the generation sweep). |
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

Supporting types: value objects `Shared\ValueObjects\UrlPath` and `Publishing\Support\FaqItem` (a computed, non-persisted FAQ pair for pages whose FAQs derive from live data, e.g. the free-meals roundup); the page-path knob `Publishing\Support\PagePaths` (reads `config('publishing.paths.*')` — the single place a family prefix like `/navy-bases/` is defined; both the generators and the SEO schemas build family/ancestor/child links through it, and every schema derives its own `@id`/canonical from `$page->url_path`); services `Publishing\Services\LegacyPathResolver`, `Catalog\Services\AffiliateLinkTagger` (port of `withPlacement` — the outbound sub-ID tagging choke point), `Publishing\Sitemap\SitemapGenerator` (hand-port of `generate-sitemap.mjs` — buckets `allPublishedIndexable` pages by url-path into the 9-file split + index, pure/returns `SitemapResult` for `sitemap:generate` to write), `Publishing\Feed\FeedGenerator` (hand-port of `generate-llm-feed.mjs` — assembles `llms.txt` + the `navy-week-2026.json` feed from every pillar/catalog aggregate, pure/returns `FeedResult` for `feed:generate` to write); page-schema serializers `Publishing\Seo\{DiscountGuideSchema, DiscountCategorySchema, DiscountIndexSchema, LocalDiscountSchema, VeteransDayFreeMealsSchema}` + pillar `*PageSchema` builders, all on the shared `BuildsSeoSchema` trait (which also holds the author/reviewer `Person` builders reused by the guide + local-business detail graphs); the DB-driven-content-page serializer `Publishing\Seo\ContentPageSchema` (Breadcrumb-only, Article+Person+FAQPage (veterans-day), or Article+Person×2+WebPage (va-disability/veterans-home-care, follow-up) by flags) + view `pages.content` (renders `body_blocks`, editable via the PageForm block Repeater); page generation `Catalog\Pages\GenerateLocalDiscountPagesAction` (+ `pages:generate-local-discounts`), `Publishing\Pages\GenerateContentPagesAction` (+ `pages:generate-content` — seeds privacy/terms/contact) and `Publishing\Pages\GenerateVeteransDayPageAction` (+ `pages:generate-veterans-day` — migrates the prose + 9 FAQs), both never clobbering an editor's body; the **author-profile page family** — one `/authors/{slug}/` page per byline user (the `authors` path family in `config('publishing.paths')`) — `Publishing\Seo\AuthorPageSchema` (Person + Breadcrumb + ProfilePage + an authored-articles ItemList, derived from `$page->url_path`) + view `pages.author`, generated by `Publishing\Pages\GenerateAuthorPagesAction` (+ `pages:generate-authors`, keyed on `author:{slug}`, pageable = the `User`) over `AuthorRepositoryInterface::publicProfiles`, dispatched via `PageType::Author` → `PageController::renderAuthor`; the data-driven `/veterans-day/free-meals/` roundup — `Publishing\Seo\VeteransDayFreeMealsSchema` + `Catalog\Support\VeteransDayFreeMealsPresenter` (live stats/ItemList/FAQ computed from the `verified()` meals) + view `pages.veterans-day-free-meals`, seeded as a `Static` slug page by `Publishing\Pages\GenerateVeteransDayFreeMealsPageAction` (+ `pages:generate-veterans-day-free-meals`) and dispatched via `renderStatic` slug `veterans-day-free-meals`; and the pillar `Generate*PagesAction`s; the editable-URL loop `Publishing\Actions\ChangeUrlPathAction` → `Publishing\Events\PageUrlChanged` → `Publishing\Listeners\CreateRedirectListener` (renaming a page auto-writes its 301 + collapses chains, no deploy — wired in `DomainServiceProvider::boot`); enums `Crm\Enums\{ConnectionStatus,Audience}`, `Catalog\Enums\{OfferType,VerificationProvider,RedemptionChannel,Placement,MealEligibility,MealRedemption,MealStatus,LocalVerification}`, `Research\Enums\{ResearchStatus,ResearchedBy}`, `Shared\Enums\{ConfidenceLevel,SourceType}` (confidence is shared by briefs + citations), `Pillars\Enums\{BaseType,CombatantCommand,RegionType,RankCategory,DesignatorCommunity,RatingCommunity,HistoricRatingEra,NavyWeekStatus,NavyWeekSourceLevel,FleetWeekSeason,FleetWeekStatus,AirShowStatus,Admission,TeamId,JetTeamStatus}`, `Publishing\Enums\{PageType,RedirectMatchType}`. Seeders: `AffiliateNetworkSeeder` (the 7 networks), `AudienceSeeder` (audience vocabulary from the enum), `EditorialTeamSeeder` (the two default byline `users` — author + reviewer — matching `config('site.editorial.*')`; back-fills the default byline onto any page missing one).

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
- **event guides** (`import:event-guides`) — `fleet_weeks` + `air_shows` guides and
  the single-row `air_show_hub`; a pure top-level camelCase→snake_case rename with
  the block payloads (schedule, festival, sections, …) passing through as JSON.
- **navy week** (`import:navy-week-events`) — the `navy_week_events` stops, folding
  the legacy `events` + `CityData` + `CityExtras` (three files, one row per city,
  joined on slug) into one record with the display lists (navy_assets, venues,
  daily_schedule, …) as JSON plus FAQs/sources.
- **jet teams** (`import:jet-teams`) — the `jet_teams` hubs + their `jet_team_schedule`
  stops + published `jet_team_cities` guides. The schedule FK comes from the
  exporter's `team` natural key (the legacy row has no team field); a stop has no
  natural unique key (a city slug recurs in a season), so each team's stops are
  replaced wholesale on re-import, while hubs/cities upsert by their unique key.
- **discount categories** (`import:discount-categories`) — the `/discount/<slug>`
  category hubs (`discount_categories`); a flat camelCase→snake_case rename with the
  intro/pinned/excluded/order arrays as JSON and no polymorphic children.
- **veterans day meals** (`import:veterans-day-meals`) — the Veterans Day free-meal
  roundup (`veterans_day_meals`); a flat rename with `eligibility` as an enum-string
  array. All statuses import (pending included) — the render gate filters on read.
- **local discounts** (`import:local-discounts`) — the geographic guides
  (`local_discounts` → `local_stores` → `local_store_hours`). A nested aggregate:
  the parent upserts on its `state`+`city`+`business_slug` composite key and
  replaces FAQs/sources; stores/hours (no natural key) are replaced wholesale per
  parent, their FKs + `sort_order` synthesized from the nesting.
- **discount core** (`import:discount-core`) — the whole brand universe normalized:
  `connections` (the ~15.3k reconciled queue brands, overlaid with the 981 published
  brands' editorial/asset fields) + `connection_aliases`, then `offers` (one primary
  offer per connection) with `offer_tiers`, `redemption_steps`, the `offer_audience`
  pivot, FAQs/sources, and `affiliate_links`, then the discount-brand `pages`
  (pageable → the primary offer) and the `research` briefs (a manifest plus each
  brief's verbatim Markdown, copied to `database/seed-data/research-briefs/<slug>.md`
  at export so Stage B reads `raw_markdown` from a committed artifact). The join key is the
  discount **file basename** (= queue slug = brief stem = `connection.slug`), not the
  record's own long slug (that stays the offer/page slug). Two resolution passes wire
  the self-referential `duplicate_of` and each alias's `connection_id`. The legacy
  9-boolean audience collapses to the 5 relevant `Audience` enum cases
  (`firstResponders` has no enum case and is intentionally dropped); the per-brand
  default affiliate network is unset in legacy data, so links fall back to the
  seeded `direct` network. The importers read scalar artifact fields through the new
  fail-loud `Shared\Import\Row` helper. Runs `import:discount-core` (Audience +
  AffiliateNetwork lookups seeded first).

This completes the discount data migration; the flat legacy `Discount` (+ the brand
queue) is now fully normalized across the catalog/CRM/publishing/research aggregates.

## Ops commands

- `php artisan skills:check-hashes` — reports skills whose on-disk content
  (`SKILL.md` + `references/*.md` under `config('research.skills_path')`) no longer
  matches their stored `content_hash` (changed / missing / never-hashed). **Read-only**;
  `--check` exits non-zero on drift so CI/scheduling can gate on it.
- `php artisan skills:detect-updates` — the **write** counterpart of the above (thin
  wrapper over `Research\Actions\DetectSkillUpdatesAction`). On a real content change it
  bumps the skill's `current_version` + stores the new hash, then flags every connection
  whose **latest** brief cited that skill at the superseded version as `needs-reverify`.
  A first-time hash only records the baseline (no bump/flag); a skill missing on disk is
  reported and skipped. Scheduled daily at 06:15, after `research:flag-stale`.
- `php artisan sitemap:generate` — regenerates the custom **9-file sitemap split +
  index** (`public/sitemap.xml` + `public/sitemap-*.xml`) from the live `pages`
  registry (hand-port of `scripts/generate-sitemap.mjs` — NOT a generic package;
  parity is load-bearing for search). Reads `allPublishedIndexable` pages, buckets by
  url-path prefix (events / guides / reference / discounts / local-discounts /
  fleetweek / jetteams / air-show / data), `lastmod` = build-clock `date_modified`. The
  non-HTML `data` bucket (`/llms.txt`, `/data/navy-week-2026.json`) is included only for
  resources present in `public/`. A published, indexable page in **no** bucket is
  reported as a warning (and omitted) so a new family gets its own prefix on purpose.
- `php artisan feed:generate` — regenerates the machine-readable LLM/citability
  resources `public/llms.txt` + `public/data/navy-week-2026.json` from the live
  aggregates (hand-port of `scripts/generate-llm-feed.mjs`). The JSON carries the
  program/methodology envelope + per-record provenance; the sitemap's `data` bucket
  lists both files once they exist. (`feed.faqs` — the legacy site-wide FAQ block —
  has no platform source yet and is emitted empty.)
- `php artisan connections:reconcile` — reports pipeline-state drift between a
  connection's `status` and the DB facts: published pages with no research brief
  (the YMYL/R6 invariant), live pages not marked `published`, and duplicates not
  marked `duplicate`. **Read-only** (never writes); `--check` exits non-zero on drift
  so CI/scheduling can gate on it. The Laravel successor to `tools/reconcile-state.py`.

## Quality gates (per the rebuild workflow)

Every task runs `/frontend-design` + `/seo-geo` (inform UI/page work) → implement
→ `/simplify` → `/security-review` → commit. Pest (parity gates), Larastan (max),
and Pint must be green before a phase is committed. **Every task ships as a PR.**
