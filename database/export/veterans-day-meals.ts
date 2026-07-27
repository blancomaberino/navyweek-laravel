// Stage-A exporter — veterans_day_meals (the Veterans Day free-meal roundup).
//
// A flat single-table rename. Exports the RAW `meals` array (all statuses,
// including `pending`) — NOT the `verifiedMeals` subset: the render gate
// (`VeteransDayMeal::isRenderable()` = status Verified && source_url !== '') is
// applied on the Laravel read side, so pending rows must be imported to preserve
// the audit trail exactly as the legacy `verifiedMeals` filter does.
//
// `eligibility` is an AsEnumCollection column → a JSON array of the backing
// strings (e.g. ['veteran','active']); redemption/status are scalar enums. No
// faqs/sources — each meal carries a single flat primary source as the
// source_url/source_label columns. `offer_date` is a free-text string column (not
// a date); `last_verified_at` is a real date. The mapping is explicit — one line
// per DB column.

import { meals } from '../../../src/data/veterans-day-meals/meals';
import type { VeteransDayMeal } from '../../../src/data/veterans-day-meals/types';
import { writeArtifact } from './lib/emit';

function toMealRow(m: VeteransDayMeal) {
  return {
    slug: m.slug,
    brand: m.brand,
    discount_slug: m.discountSlug ?? null,
    offer: m.offer,
    eligibility: m.eligibility,
    dependents_eligible: m.dependentsEligible,
    redemption: m.redemption,
    proof_required: m.proofRequired,
    offer_date: m.offerDate,
    nationwide: m.nationwide,
    source_url: m.sourceUrl,
    source_label: m.sourceLabel,
    last_verified_at: m.lastVerifiedAt,
    status: m.status,
    notes: m.notes ?? null,
  };
}

writeArtifact('veterans-day-meals', meals.map(toMealRow));
