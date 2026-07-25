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
| `Catalog` | Offers, tiers, affiliate links |
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

Reads for the shared/lookup tables (`audiences`, `sources`, `faqs`, `us_states`,
`overseas_countries`) route through the aggregate they hang off (the Offer
repository eager-loads `audiences` + `sources` on the `/discount/` read path;
sources/faqs are reached via their parent's morph relation; the state/country
lookups via `Base`), so they add no repository of their own — only the `Base`
aggregate carries one.

Supporting types: value object `Shared\ValueObjects\UrlPath`; services `Publishing\Services\LegacyPathResolver`, `Catalog\Services\AffiliateLinkTagger` (port of `withPlacement` — the outbound sub-ID tagging choke point); enums `Crm\Enums\{ConnectionStatus,Audience}`, `Catalog\Enums\{OfferType,VerificationProvider,RedemptionChannel,Placement}`, `Research\Enums\{ResearchStatus,ResearchedBy}`, `Shared\Enums\{ConfidenceLevel,SourceType}` (confidence is shared by briefs + citations), `Pillars\Enums\{BaseType,CombatantCommand,RegionType}`, `Publishing\Enums\{PageType,RedirectMatchType}`. Seeders: `AffiliateNetworkSeeder` (the 7 networks), `AudienceSeeder` (audience vocabulary from the enum).

## Quality gates (per the rebuild workflow)

Every task runs `/frontend-design` + `/seo-geo` (inform UI/page work) → implement
→ `/simplify` → `/security-review` → commit. Pest (parity gates), Larastan (max),
and Pint must be green before a phase is committed. **Every task ships as a PR.**
