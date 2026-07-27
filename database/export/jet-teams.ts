// Stage-A exporter — jet-teams silo (jet_teams hub + jet_team_schedule + jet_team_cities).
//
// Three tables from the jetteams registry:
//  - jet_teams      ← teamMetas (TeamMeta): the two hub records.
//  - jet_team_schedule ← teamSchedules (a Record<TeamId, JetTeamScheduleRow[]>):
//        every season stop. The row itself has NO team field — the parent is the
//        map key, so each row carries a `team` natural key for Stage B to resolve
//        the jet_team_id FK. `sort_order` is the array index (the season order).
//        `slug` is NOT unique (a city can recur in a season / across teams).
//  - jet_team_cities ← jetTeamCities (all authored; each carries `team`): the
//        published-or-not city guides.
//
// Pure top-level camelCase→snake_case; nested blocks (key_facts, sections,
// cross_team, related_paragraph, …) pass through as JSON. FAQs/sources lift to
// the shared polymorphic tables — the hub has FAQs only; cities carry sources
// with an optional `publisher`. `status` mirrors the legacy `?? 'scheduled'`
// default; sparse venue/admission/guide_label/second_*/dek default to null.

import { jetTeamCities, teamMetas, teamSchedules } from '../../../src/data/jetteams/index';
import type { JetTeamCity, JetTeamScheduleRow, TeamId, TeamMeta } from '../../../src/data/jetteams/types';
import { writeArtifact } from './lib/emit';

function toTeamRow(meta: TeamMeta) {
  return {
    team: meta.id,
    name: meta.name,
    full_name: meta.fullName,
    branch: meta.branch,
    aircraft: meta.aircraft,
    home_base: meta.homeBase,
    base_path: meta.basePath,
    year: meta.year,
    eyebrow: meta.eyebrow,
    hub_title: meta.hubTitle,
    hub_subtitle: meta.hubSubtitle,
    seo_headline: meta.seoHeadline,
    intro: meta.intro,
    key_facts: meta.keyFacts,
    about: meta.about,
    cross_team: meta.crossTeam,
    meta_title: meta.metaTitle,
    meta_description: meta.metaDescription,
    og_image: meta.ogImage,
    date_published: meta.datePublished,
    date_modified: meta.dateModified,
    last_verified: meta.lastVerified,
    faqs: meta.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
  };
}

function toScheduleRow(team: TeamId, row: JetTeamScheduleRow, i: number) {
  return {
    team,
    dates_label: row.datesLabel,
    start_date: row.startDate,
    end_date: row.endDate,
    city: row.city,
    state: row.state,
    show: row.show,
    venue: row.venue ?? null,
    admission: row.admission ?? null,
    status: row.status ?? 'scheduled',
    slug: row.slug,
    guide_label: row.guideLabel ?? null,
    sort_order: i,
  };
}

function toCityRow(c: JetTeamCity) {
  return {
    team: c.team,
    slug: c.slug,
    city: c.city,
    state: c.state,
    state_name: c.stateName,
    year: c.year,
    show: c.show,
    venue: c.venue,
    admission: c.admission,
    dates_label: c.datesLabel,
    start_date: c.startDate,
    end_date: c.endDate,
    second_dates_label: c.secondDatesLabel ?? null,
    second_start_date: c.secondStartDate ?? null,
    second_end_date: c.secondEndDate ?? null,
    status: c.status,
    published: c.published,
    needs_verification: c.needsVerification,
    hero_dateline: c.heroDateline,
    dek: c.dek ?? null,
    intro: c.intro,
    quick_facts: c.quickFacts,
    sections: c.sections,
    related_paragraph: c.relatedParagraph,
    card_summary: c.cardSummary,
    meta_title: c.metaTitle,
    meta_description: c.metaDescription,
    h1: c.h1,
    og_image: c.ogImage,
    date_published: c.datePublished,
    date_modified: c.dateModified,
    last_verified: c.lastVerified,
    faqs: c.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: c.sources.map((s, i) => ({ label: s.label, url: s.url, publisher: s.publisher ?? null, sort_order: i })),
  };
}

writeArtifact('jet-teams', teamMetas.map(toTeamRow));

const scheduleRows = (Object.keys(teamSchedules) as TeamId[]).flatMap((team) =>
  teamSchedules[team].map((row, i) => toScheduleRow(team, row, i)),
);
writeArtifact('jet-team-schedule', scheduleRows);

writeArtifact('jet-team-cities', jetTeamCities.map(toCityRow));
