// Stage-A exporter — local_discounts (+ local_stores + local_store_hours).
//
// A nested aggregate. Each `LocalDiscount` becomes one parent row that NESTS its
// `stores` (from the legacy `locations[]` — note the key rename) and each store's
// `hours` (from `location.hours[]`), plus the lifted polymorphic `faqs`/`sources`.
// Stage B walks the nesting and synthesizes the `local_discount_id` /
// `local_store_id` FKs + `sort_order` (the array index) at insert time — the
// legacy data carries no flat FK fields.
//
// Mapping notes: the 5 military-audience flags come off the nested `audience`
// object; lat/lng are JS numbers → stringified for the decimal columns; the
// display lists (eligibility, tiers, redeemInStore→redeem_in_store, exclusions,
// nearbyBases→nearby_bases, intro, details, keyFacts→key_facts) pass through as
// JSON; `state` is a soft slug FK to us_states; `lastVerified` is a human string;
// source `publisher` provenance is preserved. Explicit one line per DB column.

import { localDiscounts } from '../../../src/data/localDiscounts/index';
import type { LocalDiscount, LocalStore, OpeningHours } from '../../../src/data/localDiscounts/types';
import { writeArtifact } from './lib/emit';

function toHoursRow(h: OpeningHours, i: number) {
  return {
    days: h.days,
    day_of_week: h.dayOfWeek,
    opens: h.opens,
    closes: h.closes,
    note: h.note ?? null,
    sort_order: i,
  };
}

function toStoreRow(s: LocalStore, i: number) {
  return {
    name: s.name,
    street: s.street,
    city: s.city,
    state_abbr: s.stateAbbr,
    zip: s.zip,
    phone: s.phone ?? null,
    lat: String(s.lat),
    lng: String(s.lng),
    directions_url: s.directionsUrl ?? null,
    map_embed_url: s.mapEmbedUrl ?? null,
    distance_label: s.distanceLabel ?? null,
    sort_order: i,
    hours: s.hours.map(toHoursRow),
  };
}

function toLocalDiscountRow(d: LocalDiscount) {
  return {
    state: d.state,
    state_name: d.stateName,
    state_abbr: d.stateAbbr,
    city: d.city,
    city_name: d.cityName,
    business_slug: d.businessSlug,
    company: d.company,
    business_type: d.businessType,
    category: d.category,
    logo: d.logo ?? null,
    logo_alt: d.logoAlt ?? null,
    logo_background: d.logoBackground ?? null,
    official_url: d.officialUrl,
    brand_home_url: d.brandHomeUrl,
    headline_discount: d.headlineDiscount,
    discount_summary: d.discountSummary,
    verification: d.verification,
    verification_detail: d.verificationDetail ?? null,
    active_duty: d.audience.activeDuty,
    veterans: d.audience.veterans,
    retirees: d.audience.retirees,
    reserve_guard: d.audience.reserveGuard,
    military_family: d.audience.militaryFamily,
    eligibility: d.eligibility,
    tiers: d.tiers,
    redeem_in_store: d.redeemInStore,
    exclusions: d.exclusions,
    nearby_bases: d.nearbyBases,
    service_area: d.serviceArea ?? null,
    price_range: d.priceRange ?? null,
    intro: d.intro,
    details: d.details,
    key_facts: d.keyFacts,
    meta_title: d.metaTitle,
    meta_description: d.metaDescription,
    h1: d.h1,
    hero_tagline: d.heroTagline,
    primary_keyword: d.primaryKeyword,
    og_image: d.ogImage ?? null,
    date_published: d.datePublished,
    date_modified: d.dateModified,
    last_verified: d.lastVerified,
    stores: d.locations.map(toStoreRow),
    faqs: d.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: d.sources.map((s, i) => ({ label: s.label, url: s.url, publisher: s.publisher ?? null, sort_order: i })),
  };
}

writeArtifact('local-discounts', localDiscounts.map(toLocalDiscountRow));
