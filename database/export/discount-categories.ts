// Stage-A exporter — discount_categories (the /discount/<slug> category hubs).
//
// Pure top-level camelCase→snake_case rename; the intro/pinned/excluded/order
// arrays pass through as JSON. No faqs/sources (a hub is a minimal directory, not
// a brand guide). The three ordering overrides are optional → null when absent
// (not []); `lastVerified` is a human label, kept verbatim in a string column.
// The mapping is explicit — one line per DB column — for a field-by-field audit.

import { discountCategories } from '../../../src/data/discounts/categories';
import type { DiscountCategory } from '../../../src/data/discounts/categories';
import { writeArtifact } from './lib/emit';

function toCategoryRow(c: DiscountCategory) {
  return {
    slug: c.slug,
    name: c.name,
    match_category: c.matchCategory,
    meta_title: c.metaTitle,
    meta_description: c.metaDescription,
    h1: c.h1,
    hero_tagline: c.heroTagline,
    intro: c.intro,
    og_image: c.ogImage,
    pinned: c.pinned ?? null,
    excluded: c.excluded ?? null,
    order: c.order ?? null,
    date_published: c.datePublished,
    date_modified: c.dateModified,
    last_verified: c.lastVerified,
  };
}

writeArtifact('discount-categories', discountCategories.map(toCategoryRow));
