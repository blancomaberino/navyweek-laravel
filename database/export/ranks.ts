// Stage-A exporter — ranks pillar (single-table inheritance over `category`).
//
// The legacy `NavyRankEntry` is a 6-variant discriminated union; the `ranks`
// table folds it into one row per rank with nullable per-category variant groups.
// This exporter reproduces the three slice-8 consolidations, explicitly:
//   • next_rank_slug (officers) / next_paygrade_slug (enlisted) → `next_slug`
//     (and the same for previous_*).
//   • `community` names two disjoint vocabularies → split into
//     `designator_community` (designators) / `rating_community` (ratings).
//   • typical_career_path (designators) / career_path (ratings) → `career_path`.
// Common fields are read off the typed union; variant-specific fields via a loose
// view (they exist only on some members). faqs/sources are lifted to the shared
// polymorphic tables. Enum values are emitted verbatim and validated on import.

import { allRanks } from '../../../src/data/ranks/index';
import type { NavyRankEntry } from '../../../src/data/ranks/types';
import { writeArtifact } from './lib/emit';

/** Loose view for reading fields that exist on only some union members. */
type LooseRank = Record<string, unknown>;

function toRankRow(rank: NavyRankEntry) {
  const r = rank as LooseRank;
  const isDesignator = rank.category === 'officer-designator';
  const isRating = rank.category === 'rating-active' || rank.category === 'rating-historical';

  return {
    // ---- common (all categories) ----
    slug: rank.slug,
    category: rank.category,
    name: rank.name,
    abbreviation: rank.abbreviation,
    paygrade: rank.paygrade,
    insignia_path: rank.insignia_path,
    insignia_alt: rank.insignia_alt,
    meta_title: rank.meta_title,
    meta_description: rank.meta_description,
    h1: rank.h1,
    hero_tagline: rank.hero_tagline,
    overview: rank.overview,
    history: rank.history,
    responsibilities: rank.responsibilities,
    addressing: rank.addressing,
    prerequisites: rank.prerequisites,
    common_assignments: rank.common_assignments,
    pay_range: rank.pay_range ?? null,
    related_base_slug: rank.related_base_slug ?? null,
    related_base_note: rank.related_base_note ?? null,
    last_updated: rank.lastUpdated,

    // ---- consolidations ----
    next_slug: (r.next_rank_slug as string) ?? (r.next_paygrade_slug as string) ?? null,
    previous_slug: (r.previous_rank_slug as string) ?? (r.previous_paygrade_slug as string) ?? null,
    designator_community: isDesignator ? ((r.community as string) ?? null) : null,
    rating_community: isRating ? ((r.community as string) ?? null) : null,
    career_path: (r.typical_career_path as unknown[]) ?? (r.career_path as unknown[]) ?? null,

    // ---- officer / enlisted variant ----
    nato_code: (r.nato_code as string) ?? null,
    is_flag_officer: (r.is_flag_officer as boolean) ?? null,
    is_chief: (r.is_chief as boolean) ?? null,
    community_variants: (r.community_variants as unknown[]) ?? null,
    special_billets: (r.special_billets as unknown[]) ?? null,

    // ---- designator variant ----
    designator_code: (r.designator_code as string) ?? null,
    commissioning_sources: (r.commissioning_sources as unknown[]) ?? null,
    related_designators: (r.related_designators as unknown[]) ?? null,
    device_description: (r.device_description as string) ?? null,

    // ---- rating variant (active + historical) ----
    what_they_do: (r.what_they_do as string) ?? null,
    asvab_requirement: (r.asvab_requirement as string) ?? null,
    asvab_score_min: (r.asvab_score_min as number) ?? null,
    a_school_location: (r.a_school_location as string) ?? null,
    a_school_location_slug: (r.a_school_location_slug as string) ?? null,
    a_school_duration: (r.a_school_duration as string) ?? null,
    clearance_required: (r.clearance_required as string) ?? null,
    enlistment_obligation_years: (r.enlistment_obligation_years as number) ?? null,
    typical_platforms: (r.typical_platforms as unknown[]) ?? null,
    related_ratings: (r.related_ratings as unknown[]) ?? null,
    nec_examples: (r.nec_examples as unknown[]) ?? null,
    badge_description: (r.badge_description as string) ?? null,

    // ---- shared across designator + rating ----
    predecessor_ratings: (r.predecessor_ratings as unknown[]) ?? null,
    related_base_slugs: (r.related_base_slugs as unknown[]) ?? null,
    training_pipeline: (r.training_pipeline as unknown[]) ?? null,

    // ---- rating-historical variant ----
    active_period: (r.active_period as string) ?? null,
    years_active: (r.years_active as string) ?? null,
    decommissioned_year: (r.decommissioned_year as number) ?? null,
    decommission_reason: (r.decommission_reason as string) ?? null,
    successor_ratings: (r.successor_ratings as unknown[]) ?? null,
    notable_for: (r.notable_for as unknown[]) ?? null,
    era_tags: (r.era_tags as unknown[]) ?? null,
    merged_into_slug: (r.merged_into_slug as string) ?? null,

    // ---- lifted to shared polymorphic tables ----
    faqs: rank.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: rank.sources.map((s, i) => ({ label: s.label, url: s.url, sort_order: i })),
  };
}

writeArtifact('ranks', allRanks.map(toRankRow));
