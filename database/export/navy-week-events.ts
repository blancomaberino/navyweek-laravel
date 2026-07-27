// Stage-A exporter — navy_week_events (Navy Week stops).
//
// Folds the three legacy structures — the `events` array (NavyWeekEvent), the
// per-city `CityData` (getCityData), and the per-city `CityExtras`
// (getCityExtras) — into ONE row per city, joined on `event.slug`. The split
// across three files was a legacy file-organization artifact; every city has an
// entry in all three. The mapping is explicit — one line per DB column — for a
// field-by-field audit against the migration.
//
// Notes / faithful drops:
//  - lat/lng are JS numbers in the source; the column is decimal-as-string, so
//    they are stringified (nested Venue lat/lng stay numbers inside the JSON).
//  - `anchor_event_url` is declared on both CityData and CityExtras but only ever
//    populated on CityExtras — sourced from there.
//  - Optional detail (first_time_location/badge, daily_schedule) is null where the
//    source omits it; the runtime TBA-day synthesis is display-only and NOT stored.
//  - OfficialSource is {label,url} only — no `publisher` (matches the bases source
//    shape); the sources' publisher column stays null.
//  - The legacy getCityDescription() prose and hero-image data have NO target
//    column in the current schema and are intentionally not exported here — see
//    the PR description; they are a schema question for Phase-3 rendering, not a
//    silent drop into this exporter.

import { getCityExtras } from '../../../src/data/cityExtras';
import { events, getCityData } from '../../../src/data/data';
import type { NavyWeekEvent } from '../../../src/data/data';
import { writeArtifact } from './lib/emit';

function toNavyWeekRow(event: NavyWeekEvent) {
  const cityData = getCityData(event);
  const cityExtras = getCityExtras(event);

  if (!cityData || !cityExtras) {
    throw new Error(`navy-week: missing CityData/CityExtras for slug "${event.slug}"`);
  }

  return {
    // ---- NavyWeekEvent (the core stop) ----
    sequence: event.id,
    slug: event.slug,
    city: event.city,
    state: event.state,
    state_abbr: event.stateAbbr,
    start_date: event.startDate,
    end_date: event.endDate,
    anchor_event: event.anchorEvent,
    lat: String(event.lat),
    lng: String(event.lng),
    first_time: event.firstTime,
    first_time_location: event.firstTimeLocation ?? null,
    first_time_badge: event.firstTimeBadge ?? null,
    status: event.status,

    // ---- CityData (roundup detail) ----
    anchor_event_detail: cityData.anchorEventDetail,
    first_time_note: cityData.firstTimeNote,
    navy_assets: cityData.navyAssets,
    key_venues: cityData.keyVenues,
    military_context: cityData.militaryContext,
    navco_url: cityData.navcoUrl,
    highlights: cityData.highlights,

    // ---- CityExtras (rich city detail) ----
    anchor_event_url: cityExtras.anchorEventUrl ?? null,
    venues: cityExtras.venues,
    daily_schedule: cityExtras.dailySchedule ?? null,
    parking_notes: cityExtras.parkingNotes,
    cost_summary: cityExtras.costSummary,
    last_verified_at: cityExtras.lastVerifiedAt,

    // ---- Lifted to the shared polymorphic tables ----
    faqs: cityExtras.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: cityExtras.officialSources.map((s, i) => ({ label: s.label, url: s.url, sort_order: i })),
  };
}

writeArtifact('navy-week-events', events.map(toNavyWeekRow));
