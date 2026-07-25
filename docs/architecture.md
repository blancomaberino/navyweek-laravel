# NavyWeek Platform — architecture

Living architecture diagram for the Laravel rebuild. **Keep it current:** any change
that adds/removes a table, model, repository, middleware, service, event/listener,
or request-flow step must update this file in the same PR (see `platform/CLAUDE.md`).

Scope reflects what is **built so far**. Planned-but-not-yet-built entities are drawn
with dashed borders and are not wired up until their slice lands.

Last updated: Phase 2 slice 5 — Pages SEO/JSON-LD extension (head meta, build-clock
dates, page-specific `json_ld`, polymorphic `pageable`).

## Domain modules & data access

Domain-first modular monolith under `app/Domain/*`. Callers depend on repository
**interfaces**; the Eloquent implementations are bound in `DomainServiceProvider`.

```mermaid
flowchart TB
    subgraph Crm["Crm module (brands / pipeline)"]
        Connection["Connection (model)"]
        ConnectionAlias["ConnectionAlias (model)"]
        ConnIface["ConnectionRepositoryInterface"]
        ConnRepo["EloquentConnectionRepository"]
        ConnStatus["enum ConnectionStatus"]
        Audience["enum Audience"]
        ConnRepo -. implements .-> ConnIface
        ConnRepo --> Connection
        ConnRepo --> ConnectionAlias
        ConnectionAlias -->|belongsTo| Connection
        Connection -->|"duplicate_of (self-ref)"| Connection
    end

    subgraph Catalog["Catalog module (offers + affiliate)"]
        Offer["Offer (model)"]
        OfferTier["OfferTier (model)"]
        RedemptionStep["RedemptionStep (model)"]
        OfferIface["OfferRepositoryInterface"]
        OfferRepo["EloquentOfferRepository"]
        OfferType["enum OfferType"]
        Verification["enum VerificationProvider"]
        Channel["enum RedemptionChannel"]
        AffNetwork["AffiliateNetwork (model)"]
        AffLink["AffiliateLink (model)"]
        AffNetIface["AffiliateNetworkRepositoryInterface"]
        AffLinkIface["AffiliateLinkRepositoryInterface"]
        Tagger["AffiliateLinkTagger (service)<br/>port of withPlacement"]
        PlacementEnum["enum Placement"]
        OfferRepo -. implements .-> OfferIface
        OfferRepo --> Offer
        Offer -->|hasMany| OfferTier
        Offer -->|hasMany| RedemptionStep
        Offer -->|hasMany| AffLink
        AffLink -->|belongsTo| AffNetwork
        Tagger -->|reads subid_param| AffNetwork
    end

    Connection -->|"hasMany (one brand, many offers)"| Offer
    Connection -->|"default_affiliate_network_id"| AffNetwork

    subgraph Publishing["Publishing module (pages / redirects / routing)"]
        Page["Page (model)"]
        Redirect["Redirect (model)"]
        PageIface["PageRepositoryInterface"]
        PageRepo["EloquentPageRepository"]
        RedirIface["RedirectRepositoryInterface"]
        RedirRepo["EloquentRedirectRepository"]
        PageType["enum PageType"]
        RedirType["enum RedirectMatchType"]
        LegacyResolver["LegacyPathResolver (service)"]
        PageRepo -. implements .-> PageIface
        RedirRepo -. implements .-> RedirIface
        PageRepo --> Page
        RedirRepo --> Redirect
    end

    subgraph Research["Research module (briefs / skill provenance)"]
        ResearchModel["Research (model)"]
        Skill["Skill (model)"]
        ResearchIface["ResearchRepositoryInterface"]
        ResearchRepo["EloquentResearchRepository"]
        ResearchStatus["enum ResearchStatus"]
        ResearchedBy["enum ResearchedBy"]
        Confidence["enum ConfidenceLevel"]
        ResearchRepo -. implements .-> ResearchIface
        ResearchRepo --> ResearchModel
        ResearchModel -->|"belongsToMany (research_skill: skill_version, used_for)"| Skill
    end

    Connection -->|"hasMany (versioned briefs)"| ResearchModel
    Offer -.->|"optional offer-scoped brief"| ResearchModel

    Page -.->|"pageable morphTo"| Offer
    Page -.->|"pageable morphTo"| Connection

    subgraph Shared["Shared module"]
        UrlPath["UrlPath value object<br/>(trailingSlash: always)"]
    end

    subgraph Planned["Planned (not yet built)"]
        Pillars["bases / ranks / events — Pillars"]:::planned
        SharedTaxonomy["sources / faqs / audience pivot — Shared"]:::planned
    end

    DSP["DomainServiceProvider<br/>(interface to implementation bindings)"]
    DSP -.binds.-> ConnIface
    DSP -.binds.-> OfferIface
    DSP -.binds.-> AffNetIface
    DSP -.binds.-> AffLinkIface
    DSP -.binds.-> ResearchIface
    DSP -.binds.-> PageIface
    DSP -.binds.-> RedirIface

    classDef planned stroke-dasharray: 5 5,stroke:#999,color:#999;
```

## Data model (built so far)

```mermaid
erDiagram
    CONNECTIONS ||--o{ CONNECTION_ALIASES : "has keyword-variant aliases"
    CONNECTIONS ||--o| CONNECTIONS : "duplicate_of (canonical)"
    CONNECTIONS ||--o{ OFFERS : "carries offers"
    OFFERS ||--o{ OFFER_TIERS : "per-audience savings rows"
    OFFERS ||--o{ REDEMPTION_STEPS : "numbered steps (online/in-store)"
    CONNECTIONS ||--o{ RESEARCH : "versioned briefs"
    OFFERS ||--o{ RESEARCH : "optional offer-scoped brief"
    RESEARCH ||--o{ RESEARCH_SKILL : "skill provenance"
    SKILLS ||--o{ RESEARCH_SKILL : "contributed to"
    CONNECTIONS ||--o{ AFFILIATE_LINKS : "brand-level links"
    OFFERS ||--o{ AFFILIATE_LINKS : "offer links"
    AFFILIATE_NETWORKS ||--o{ AFFILIATE_LINKS : "tags via subid_param"
    AFFILIATE_NETWORKS ||--o| CONNECTIONS : "default network"
    OFFERS ||--o{ PAGES : "presented by (pageable morph)"
    CONNECTIONS ||--o{ PAGES : "presented by (pageable morph)"

    CONNECTIONS {
        bigint id PK
        string slug UK
        string brand
        string key
        string category "freeform industry label (lossless)"
        string status "enum ConnectionStatus"
        int priority_tier
        bool is_backlog "active queue vs backlog"
        int max_volume
        int total_volume
        int keyword_count
        int min_difficulty
        decimal cpc
        string top_keyword
        json audiences "enum Audience array"
        int research_cadence_days "default 45"
        date last_verified_at
        date next_review_due
        bigint duplicate_of FK
        string brand_home_url
        string official_url
        string logo_url
        bigint default_affiliate_network_id FK "nullable"
        string brief_path
        datetime deleted_at "soft deletes"
    }

    CONNECTION_ALIASES {
        bigint id PK
        string alias_slug UK
        bigint connection_id FK
    }

    OFFERS {
        bigint id PK
        bigint connection_id FK
        string internal_label
        string offer_type "enum OfferType"
        string headline_discount
        text discount_summary
        string verification "enum VerificationProvider"
        string verification_url
        string official_url "falls back to connection"
        string audience_label
        json display_units "eligibility, exclusions, key_facts, promo, savings tools, share_cta"
        string cta_overrides "cta_label, cta_subnote, sticky_cta_label, source_priority_note"
        bool is_primary
        int sort_order
        bool is_published
    }

    OFFER_TIERS {
        bigint id PK
        bigint offer_id FK
        string audience
        string amount
        string note
        int sort_order
    }

    REDEMPTION_STEPS {
        bigint id PK
        bigint offer_id FK
        string channel "enum RedemptionChannel: online/in_store"
        string title
        text detail
        int sort_order
    }

    AFFILIATE_NETWORKS {
        bigint id PK
        string key UK "direct | impact | cj | awin | rakuten | avantlink | amazon"
        string name
        string subid_param "query key carrying the placement token"
        json extra_params "e.g. direct utm_source/medium"
    }

    AFFILIATE_LINKS {
        bigint id PK
        bigint connection_id FK "nullable"
        bigint offer_id FK "nullable"
        bigint affiliate_network_id FK
        string base_url
        string placement "enum Placement: hero-cta/sticky-footer/keyfacts-source"
        string rel "default sponsored noopener noreferrer"
    }

    RESEARCH {
        bigint id PK
        bigint connection_id FK
        bigint offer_id FK "nullable"
        string brief_path
        longtext raw_markdown "verbatim, zero-loss"
        text executive_summary
        json parsed "verified_facts, decision_table, maintenance, recommended_copy"
        string confidence_overall "enum ConfidenceLevel"
        date last_verified
        string researched_by "enum ResearchedBy"
        string skill_key "primary skill provenance"
        string skill_version
        string status "enum ResearchStatus"
        int version
    }

    SKILLS {
        bigint id PK
        string key UK
        string name
        string current_version
        string content_hash "bump triggers re-research"
        string source_ref
    }

    RESEARCH_SKILL {
        bigint id PK
        bigint research_id FK
        bigint skill_id FK
        string skill_version
        string used_for "facts | citability"
    }

    PAGES {
        bigint id PK
        string page_type "enum PageType"
        string slug
        string url_path UK "canonical routing key"
        string title "head/og/twitter title"
        text meta_description
        string canonical_path "canonical override, nullable"
        string og_type "default website"
        string og_image_path "site-relative, nullable"
        bool noindex "robots + suppresses org node"
        datetimetz date_published "build-clock, first build"
        datetimetz date_modified "build-clock, every build"
        json json_ld "page-specific EXTRA schema nodes"
        string pageable_type "polymorphic owner, nullable"
        bigint pageable_id "Offer / Connection / pillar, nullable"
        bool is_published
    }

    REDIRECTS {
        bigint id PK
        string from_path UK
        string to_path
        int status "default 301"
        string reason
        string match_type "exact or prefix"
        bool is_active
        int hits
    }
```

> `pages` now carries the full SEO/JSON-LD head-meta layer (title, description,
> canonical override, OG, robots), the build-clock Article dates, a `json_ld` slot
> for page-specific EXTRA schema nodes, and the polymorphic `pageable` owner
> (Offer/Connection today; pillars when those land). Derived schema — the auto
> `Organization` node and the aggregate-driven `Article`/`LocalBusiness` — is
> composed at render time from `pageable`, never stored, so the graph can't drift.
> The page **body** columns land with the Phase 3 rendering slice (aggregate-backed
> pages derive their body from the Offer/Connection; only static pages need stored
> body). `connections.category` is kept as the raw industry string; the category-hub
> FK lands with the rendering slice.

## Request / redirect pipeline

`CanonicalUrlMiddleware` is registered **global + first**, before the (Phase 6)
response cache, so 301s are never mis-cached. It ports the legacy Vercel
`middleware.ts` order 1:1.

```mermaid
flowchart TD
    Req["HTTP request"] --> Apex{"apex host?"}
    Apex -->|yes| ApexR["301 to www"]
    Apex -->|no| Method{"method / extension gate"}
    Method -->|asset / disallowed| Pass["pass through"]
    Method -->|html-ish| Slash{"missing trailing slash?"}
    Slash -->|yes| SlashR["301 add slash"]
    Slash -->|no| RedirLook["Redirect table lookup<br/>RedirectRepository:<br/>exact, then longest-prefix"]
    RedirLook -->|hit| RedirR["redirect to to_path"]
    RedirLook -->|miss| Legacy["LegacyPathResolver<br/>historic Navy Week city URLs<br/>(MODERN_ROUTE_PREFIXES gate)"]
    Legacy -->|resolved| LegacyR["301 to resolved path"]
    Legacy -->|no match| LiveLook["Published page lookup<br/>PageRepository.publishedPathExists<br/>on url_path"]
    LiveLook -->|exists| Ctrl["PageController (200)"]
    LiveLook -->|missing| Fallback["Route::fallback 301 to /  (never 404)"]
```

## References

- Module responsibilities + commands: `platform/README.md`
- Full rebuild plan and SEO invariants: root `CLAUDE.md`
