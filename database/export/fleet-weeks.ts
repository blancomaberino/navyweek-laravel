// Stage-A exporter — fleet_weeks (Fleet Week city guides).
//
// A pure top-level camelCase → snake_case column rename (no consolidations); the
// nested block payloads (schedule, airshow, parade_of_ships, ship_tours,
// viewing_spots, festival, past_years, quick_facts) pass through as JSON with
// their original keys. faqs/sources are lifted to the shared polymorphic tables.
// The mapping is explicit — one line per DB column — for field-by-field audit.

import { fleetWeekCities } from '../../../src/data/fleetweek/index';
import type { FleetWeek } from '../../../src/data/fleetweek/types';
import { writeArtifact } from './lib/emit';

function toFleetWeekRow(fw: FleetWeek) {
  return {
    slug: fw.slug,
    city: fw.city,
    state: fw.state,
    state_abbr: fw.stateAbbr,
    year: fw.year,
    branding_name: fw.brandingName,
    season: fw.season,
    month_label: fw.monthLabel,
    has_official_fleet_week: fw.hasOfficialFleetWeek,
    has_air_show: fw.hasAirShow,
    status: fw.status,
    status_label: fw.statusLabel,
    status_note: fw.statusNote ?? null,
    festival_dates_label: fw.festivalDatesLabel ?? null,
    airshow_dates_label: fw.airshowDatesLabel ?? null,
    dek: fw.dek,
    intro: fw.intro,
    quick_facts: fw.quickFacts,
    official_url: fw.officialUrl ?? null,
    official_site_label: fw.officialSiteLabel ?? null,
    schedule: fw.schedule,
    schedule_note: fw.scheduleNote ?? null,
    airshow: fw.airshow ?? null,
    parade_of_ships: fw.paradeOfShips ?? null,
    ship_tours: fw.shipTours ?? null,
    viewing_intro: fw.viewingIntro ?? null,
    viewing_spots: fw.viewingSpots,
    getting_there: fw.gettingThere,
    history: fw.history,
    past_years: fw.pastYears ?? null,
    festival: fw.festival ?? null,
    card_summary: fw.cardSummary,
    related_slugs: fw.relatedSlugs ?? null,
    meta_title: fw.metaTitle,
    meta_description: fw.metaDescription,
    h1: fw.h1,
    primary_keyword: fw.primaryKeyword,
    og_image: fw.ogImage,
    date_published: fw.datePublished,
    date_modified: fw.dateModified,
    last_verified: fw.lastVerified,
    faqs: fw.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: fw.sources.map((s, i) => ({ label: s.label, url: s.url, publisher: s.publisher ?? null, sort_order: i })),
  };
}

writeArtifact('fleet-weeks', fleetWeekCities.map(toFleetWeekRow));
