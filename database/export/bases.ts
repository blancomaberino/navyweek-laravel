// Stage-A exporter — bases pillar (+ us_states / overseas_countries lookups).
//
// Reads the legacy registries from the sibling Astro repo and emits three
// artifacts whose keys are the exact `bases` / `us_states` / `overseas_countries`
// DB columns. The mapping is explicit (one line per column) so it is auditable
// field-by-field against the migrations — the YMYL bar for the data migration.
//
// Renames the three camelCase legacy fields (stateName/stateAbbr/lastUpdated) and
// lifts the inline `faqs`/`sources` arrays out to nested objects that Stage B
// attaches to the shared polymorphic tables.

import { OVERSEAS_COUNTRIES } from '../../../src/data/bases/countries';
import { bases } from '../../../src/data/bases/index';
import { US_STATES } from '../../../src/data/bases/states';
import type { NavyBase } from '../../../src/data/bases/types';
import { writeArtifact } from './lib/emit';

writeArtifact(
  'us-states',
  US_STATES.map((s) => ({ slug: s.slug, name: s.name, abbr: s.abbr })),
);

writeArtifact(
  'overseas-countries',
  OVERSEAS_COUNTRIES.map((c) => ({
    slug: c.slug,
    name: c.name,
    iso2: c.iso2,
    region: c.region,
    is_us_territory: c.isUSTerritory ?? false,
  })),
);

function toBaseRow(b: NavyBase) {
  return {
    slug: b.slug,
    name: b.name,
    aka: b.aka ?? null,
    type: b.type,
    region_type: b.region_type ?? 'state',
    state: b.state ?? null,
    state_name: b.stateName ?? null,
    state_abbr: b.stateAbbr ?? null,
    country: b.country ?? null,
    country_slug: b.country_slug ?? null,
    country_iso2: b.country_iso2 ?? null,
    region: b.region ?? null,
    host_nation: b.host_nation ?? null,
    timezone: b.timezone ?? null,
    local_currency: b.local_currency ?? null,
    local_language: b.local_language ?? null,
    sofa_status: b.sofa_status ?? null,
    command_sponsorship_required: b.command_sponsorship_required ?? null,
    passport_required: b.passport_required ?? null,
    city: b.city,
    county: b.county ?? null,
    lat: b.lat,
    lng: b.lng,
    established: b.established,
    personnel_count: b.personnel_count ?? null,
    area_acres: b.area_acres ?? null,
    major_units: b.major_units,
    key_facts: b.key_facts,
    meta_title: b.meta_title,
    meta_description: b.meta_description,
    h1: b.h1,
    hero_tagline: b.hero_tagline,
    seo_keyword_primary: b.seo_keyword_primary,
    commanding_officer: b.commanding_officer ?? null,
    motto: b.motto ?? null,
    nickname: b.nickname ?? null,
    wikipedia_url: b.wikipedia_url ?? null,
    official_url: b.official_url ?? null,
    notable_events: b.notable_events ?? null,
    nearby_bases: b.nearby_bases ?? null,
    nearest_fleet_week_slug: b.nearest_fleet_week_slug ?? null,
    overview: b.overview,
    history: b.history,
    location_context: b.location_context ?? null,
    host_nation_context: b.host_nation_context ?? null,
    last_updated: b.lastUpdated,
    // Lifted to the shared polymorphic tables by Stage B; sort_order = array order.
    faqs: b.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: b.sources.map((s, i) => ({ label: s.label, url: s.url, sort_order: i })),
  };
}

writeArtifact('bases', bases.map(toBaseRow));
