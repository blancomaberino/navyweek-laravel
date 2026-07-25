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
| Model | `Publishing\Models\Page` | Publishing | Central routing/SEO row keyed on the canonical `url_path`; the DB successor to the build-time route manifest. |
| Model | `Publishing\Models\Redirect` | Publishing | A 301/redirect rule (`from`→`to`, `exact`/`prefix`); subsumes the hand-coded `middleware.ts` rules and is the sink for editor URL changes. |
| Repository | `Publishing\Repositories\PageRepositoryInterface` → `EloquentPageRepository` | Publishing | Page routing lookups (e.g. `publishedPathExists` on `url_path`). |
| Repository | `Publishing\Repositories\RedirectRepositoryInterface` → `EloquentRedirectRepository` | Publishing | Redirect resolution (exact, then longest-prefix strict-descendant match). |
| Model | `Research\Models\Research` | Research | A sourced, versioned research brief for a connection (fourth lifecycle). Stores the brief `raw_markdown` verbatim plus parsed facts/copy; only `last_verified` traces to research (build-clock rule). |
| Model | `Research\Models\Skill` | Research | A research/QA skill in the provenance registry (`military-discount-research`, `seo-geo`); `content_hash`/`current_version` drive skill-upgrade re-research triggers. |
| Repository | `Research\Repositories\ResearchRepositoryInterface` → `EloquentResearchRepository` | Research | Brief reads for a connection: `latestForConnection` (highest version), `historyForConnection`. |

Supporting types: value object `Shared\ValueObjects\UrlPath`; services `Publishing\Services\LegacyPathResolver`, `Catalog\Services\AffiliateLinkTagger` (port of `withPlacement` — the outbound sub-ID tagging choke point); enums `Crm\Enums\{ConnectionStatus,Audience}`, `Catalog\Enums\{OfferType,VerificationProvider,RedemptionChannel,Placement}`, `Research\Enums\{ResearchStatus,ResearchedBy,ConfidenceLevel}`, `Publishing\Enums\{PageType,RedirectMatchType}`. Seeder: `AffiliateNetworkSeeder` (the 7 networks).

## Quality gates (per the rebuild workflow)

Every task runs `/frontend-design` + `/seo-geo` (inform UI/page work) → implement
→ `/simplify` → `/security-review` → commit. Pest (parity gates), Larastan (max),
and Pint must be green before a phase is committed. **Every task ships as a PR.**
