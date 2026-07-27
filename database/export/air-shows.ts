// Stage-A exporter — air_shows event guides + the single-row air_show_hub.
//
// Pure top-level camelCase → snake_case column rename; nested block payloads
// (sections, location, offer, organizer, quick_facts, related_paragraph,
// email_cta) pass through as JSON. faqs/sources lifted to the shared polymorphic
// tables (the hub has faqs only). Explicit one-line-per-column mapping.

import { airShowHubMeta } from '../../../src/data/airshows/hub';
import { airShows } from '../../../src/data/airshows/index';
import type { AirShow, AirShowHubMeta } from '../../../src/data/airshows/types';
import { writeArtifact } from './lib/emit';

function toAirShowRow(a: AirShow) {
  return {
    slug: a.slug,
    short_name: a.shortName,
    name: a.name,
    city: a.city,
    state: a.state,
    state_name: a.stateName,
    year: a.year,
    base: a.base,
    dates_label: a.datesLabel,
    start_date: a.startDate,
    end_date: a.endDate,
    date_unconfirmed: a.dateUnconfirmed ?? false,
    gate_time: a.gateTime ?? null,
    admission: a.admission,
    parking: a.parking ?? null,
    headliner: a.headliner,
    performers: a.performers,
    official_url: a.officialUrl,
    phone: a.phone ?? null,
    status: a.status,
    published: a.published,
    needs_verification: a.needsVerification,
    hero_headline: a.heroHeadline,
    intro: a.intro,
    quick_facts: a.quickFacts,
    sections: a.sections,
    related_paragraph: a.relatedParagraph,
    card_summary: a.cardSummary,
    email_cta: a.emailCta ?? null,
    schema_name: a.schemaName,
    event_description: a.eventDescription,
    location: a.location,
    offer: a.offer,
    organizer: a.organizer,
    meta_title: a.metaTitle,
    meta_description: a.metaDescription,
    h1: a.h1,
    og_image: a.ogImage,
    canonical_override: a.canonicalOverride ?? null,
    date_published: a.datePublished,
    date_modified: a.dateModified,
    last_verified: a.lastVerified,
    faqs: a.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: a.sources.map((s, i) => ({ label: s.label, url: s.url, publisher: s.publisher ?? null, sort_order: i })),
  };
}

function toHubRow(h: AirShowHubMeta) {
  return {
    base_path: h.basePath,
    year: h.year,
    eyebrow: h.eyebrow,
    hub_title: h.hubTitle,
    hub_subtitle: h.hubSubtitle,
    seo_headline: h.seoHeadline,
    intro: h.intro,
    key_facts: h.keyFacts,
    about: h.about,
    meta_title: h.metaTitle,
    meta_description: h.metaDescription,
    og_image: h.ogImage,
    date_published: h.datePublished,
    date_modified: h.dateModified,
    last_verified: h.lastVerified,
    faqs: h.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
  };
}

writeArtifact('air-shows', airShows.map(toAirShowRow));
writeArtifact('air-show-hub', [toHubRow(airShowHubMeta)]);
