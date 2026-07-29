// Stage-A exporter — the discount CORE: the flat legacy `Discount` (+ the brand
// queue) normalized into connections / offers (+tiers/steps/audience/faqs/sources)
// / pages / affiliate_links / connection_aliases / research.
//
// KEY JOIN: the canonical brand key is the discount FILE BASENAME (src/data/
// discounts/<key>.ts) — it equals the queue `slug` and the brief stem
// (research/discounts/<key>.md). The record's own `.slug` (e.g.
// "yeti-military-veteran") is the OFFER/PAGE slug, NOT the connection slug. So
// connection.slug = basename = queue.slug; offer/page slug = record.slug.
//
// The queue (pipeline/queue/queue.json active + queue-backlog.json) is the
// reconciled master of ~15.3k brands and already contains the published ones, so
// connections are SEEDED from the queue and the 981 Discount records OVERLAY
// their editorial/asset fields onto the matching slug (upsert, never a second
// insert). Affiliate networks + audiences are seeded separately (seeders), so
// they are not re-exported here; affiliate_links reference the seeded networks by
// key. Research is exported as a manifest PLUS a committed copy of each brief's
// verbatim Markdown under database/seed-data/research-briefs/<slug>.md (the
// ~20 MB corpus is too big for one JSON, but Stage B must still consume only
// committed artifacts — never the sibling Astro repo — so it is copied here at
// export time). Structured parsing of the briefs is deliberately deferred.

import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { discounts } from '../../../src/data/discounts/index';
import { discountMetaDescription, discountMetaTitle } from '../../../src/data/discounts/meta';
import type { Discount, DiscountAudience } from '../../../src/data/discounts/types';
import { writeArtifact, writeSeedText } from './lib/emit';

const ROOT = resolve(import.meta.dirname, '../../..'); // repo root (…/local.navyweek)
const DISCOUNTS_DIR = resolve(ROOT, 'src/data/discounts');
const BRIEFS_DIR = resolve(ROOT, 'research/discounts');

/** Non-record helper modules in src/data/discounts that must not be treated as brands. */
const NON_RECORD_FILES = new Set([
  'index.ts', 'types.ts', 'meta.ts', 'networks.ts', 'links.ts', 'share.ts',
  'categories.ts', 'logo.ts', 'seo.ts',
]);

type QueueBrand = {
  slug: string; brand: string; key: string; status: string; category?: string;
  priorityTier?: number | null; maxVolume?: number | null; totalVolume?: number | null;
  keywordCount?: number | null; minDifficulty?: number | null; cpc?: number | null;
  audiences?: string[]; topKeyword?: string | null; lastVerifiedAt?: string | null;
  briefPath?: string | null; duplicateOf?: string | null;
};

/** The 7 Audience enum backing values (the offer_audience / connection vocabulary). */
const VALID_AUDIENCES = new Set(['military', 'veteran', 'student', 'teacher', 'healthcare', 'government', 'senior']);

/**
 * Consolidate the legacy 9-boolean DiscountAudience into the distinct 7-value
 * Audience vocabulary. The four military-community flags collapse to `military`;
 * `firstResponders` has NO Audience case and is intentionally dropped (surfaced
 * in the PR — a genuine audience the new enum omits).
 */
function consolidateAudience(a: DiscountAudience): string[] {
  const keys = new Set<string>();
  if (a.activeDuty || a.reserveGuard || a.retirees || a.militaryFamily) keys.add('military');
  if (a.veterans) keys.add('veteran');
  if (a.medical) keys.add('healthcare');
  if (a.teachers) keys.add('teacher');
  if (a.government) keys.add('government');
  // a.firstResponders → no target case (dropped).
  return [...keys];
}

// ---- Load the queue master (active + backlog) + aliases from the root repo ----
type QueueFile = { _meta?: { sourceCsv?: string }; brands: QueueBrand[] };
const queueActive = JSON.parse(readFileSync(resolve(ROOT, 'pipeline/queue/queue.json'), 'utf8')) as QueueFile;
const queueBacklog = JSON.parse(readFileSync(resolve(ROOT, 'pipeline/queue/queue-backlog.json'), 'utf8')) as QueueFile;
const aliasesRaw = JSON.parse(readFileSync(resolve(ROOT, 'pipeline/queue/aliases.json'), 'utf8')) as { aliases: Record<string, string> };
const sourceCsv = queueActive._meta?.sourceCsv ?? null;

// ---- Map each Discount record to its connection slug (the file basename) ----
// The `discounts` array carries the long offer slug; the file basename is the
// connection key. Build recordSlug → basename by reading each record file.
const slugToKey = new Map<string, string>();
for (const file of readdirSync(DISCOUNTS_DIR)) {
  if (!file.endsWith('.ts') || NON_RECORD_FILES.has(file)) continue;
  const text = readFileSync(resolve(DISCOUNTS_DIR, file), 'utf8');
  const m = text.match(/slug:\s*['"]([^'"]+)['"]/);
  if (m) slugToKey.set(m[1], file.replace(/\.ts$/, ''));
}
function connSlugFor(d: Discount): string {
  const key = slugToKey.get(d.slug);
  if (!key) throw new Error(`discount-core: no file basename (connection slug) for record slug "${d.slug}"`);
  return key;
}

// ================= connections =================
type ConnRow = Record<string, unknown>;
const connections = new Map<string, ConnRow>();

function seedFromQueue(b: QueueBrand, isBacklog: boolean): void {
  if (connections.has(b.slug)) return; // first file (active) wins over backlog on dupe
  connections.set(b.slug, {
    slug: b.slug,
    brand: b.brand,
    key: b.key,
    category: b.category ?? null,
    status: b.status,
    priority_tier: b.priorityTier ?? null,
    is_backlog: isBacklog,
    max_volume: b.maxVolume ?? null,
    total_volume: b.totalVolume ?? null,
    keyword_count: b.keywordCount ?? null,
    min_difficulty: b.minDifficulty ?? null,
    cpc: b.cpc ?? null,
    top_keyword: b.topKeyword ?? null,
    audiences: (b.audiences ?? []).filter((x) => VALID_AUDIENCES.has(x)),
    last_verified_at: b.lastVerifiedAt ?? null,
    brief_path: b.briefPath ?? null,
    duplicate_of_slug: b.duplicateOf ?? null,
    source_csv: sourceCsv,
    brand_home_url: null,
    official_url: null,
    logo_url: null,
    added_by: null,
  });
}
for (const b of queueActive.brands) seedFromQueue(b, false);
for (const b of queueBacklog.brands) seedFromQueue(b, true);

// Overlay the 981 Discount records' editorial/asset fields onto their slug.
for (const d of discounts) {
  const slug = connSlugFor(d);
  const row = connections.get(slug) ?? { slug };
  connections.set(slug, {
    ...row,
    brand: d.company,
    category: d.category,
    status: 'published', // a Discount record is a live page (authoritative)
    official_url: d.officialUrl,
    logo_url: d.logo,
    brand_home_url: d.brandHomeUrl,
    audiences: consolidateAudience(d.audience), // richer than the queue's list
    is_backlog: false,
  });
}
writeArtifact('connections', [...connections.values()]);

// ================= connection_aliases =================
const aliasRows = Object.entries(aliasesRaw.aliases).map(([alias_slug, canonical_slug]) => ({
  alias_slug,
  canonical_slug,
}));
writeArtifact('connection-aliases', aliasRows);

// ================= offers (+ nested children + affiliate_links) =================
// Two always-present monetized placements per offer (the keyfacts-source citation
// is render-conditional on the KeyFacts strip citing officialUrl, so it is not
// materialized here).
const MONETIZED_REL = 'sponsored noopener noreferrer';
function offerRow(d: Discount) {
  const offerType = d.advisoryNoDiscount ? 'advisory_no_discount' : 'everyday';
  const typeLabel = d.advisoryNoDiscount ? 'Advisory (no discount)' : 'Everyday discount';
  return {
    connection_slug: connSlugFor(d),
    internal_label: `${d.company} — ${typeLabel}`,
    offer_type: offerType,
    headline_discount: d.headlineDiscount,
    discount_summary: d.discountSummary,
    verification: d.verification,
    verification_url: d.verificationUrl ?? null,
    official_url: d.officialUrl,
    audience_label: d.audienceLabel ?? null,
    eligibility: d.eligibility,
    exclusions: d.exclusions,
    key_facts: d.keyFacts,
    promo: d.promo ?? null,
    savings_hack: d.savingsHack ?? null,
    savings_table: d.savingsTable ?? null,
    savings_table_secondary: d.savingsTableSecondary ?? null,
    chooser: d.chooser ?? null,
    share_cta: d.shareCta ?? null,
    cta_label: d.ctaLabel ?? null,
    cta_subnote: d.ctaSubnote ?? null,
    source_priority_note: d.sourcePriorityNote ?? null,
    sticky_cta_label: d.stickyCtaLabel ?? null,
    is_primary: true,
    sort_order: 0,
    is_published: true,
    // nested children (Stage B lifts these out)
    tiers: d.tiers.map((t, i) => ({ audience: t.audience, amount: t.amount, note: t.note ?? null, sort_order: i })),
    online_steps: d.redeemOnline.map((s, i) => ({ channel: 'online', title: s.title, detail: s.detail, sort_order: i })),
    in_store_steps: (d.redeemInStore ?? []).map((s, i) => ({ channel: 'in_store', title: s.title, detail: s.detail, sort_order: i })),
    audience_keys: consolidateAudience(d.audience),
    faqs: d.faqs.map((f, i) => ({ question: f.question, answer: f.answer, sort_order: i })),
    sources: d.sources.map((s, i) => ({ label: s.label, url: s.url, publisher: s.publisher ?? null, sort_order: i })),
    affiliate_links: [
      { network_key: d.network ?? 'direct', base_url: d.officialUrl, placement: 'hero-cta', rel: MONETIZED_REL },
      { network_key: d.network ?? 'direct', base_url: d.officialUrl, placement: 'sticky-footer', rel: MONETIZED_REL },
    ],
  };
}
writeArtifact('offers', discounts.map(offerRow));

// ================= pages =================
function pageRow(d: Discount) {
  const urlPath = `/discount/${d.slug}/`;
  return {
    connection_slug: connSlugFor(d), // Stage B resolves pageable → the connection's primary offer
    page_type: 'discount_brand',
    slug: d.slug,
    url_path: urlPath,
    title: discountMetaTitle(d),
    meta_description: discountMetaDescription(d),
    canonical_path: urlPath,
    og_type: 'article',
    og_image_path: d.ogImage,
    noindex: false,
    date_published: d.datePublished,
    date_modified: d.dateModified,
    json_ld: null, // Article+Person is built at render (ported 1:1); not snapshotted
    is_published: true,
  };
}
writeArtifact('pages', discounts.map(pageRow));

// ================= research (manifest — raw_markdown read from disk at import) =================
function researchStatusFor(queueStatus: string | undefined): string {
  if (queueStatus === 'published') return 'complete';
  if (queueStatus === 'needs-reverify') return 'stale';
  return 'draft';
}
const briefStems = new Set(
  readdirSync(BRIEFS_DIR).filter((f) => f.endsWith('.md')).map((f) => f.replace(/\.md$/, '')),
);
// A brief whose stem isn't a queue brand (e.g. a local-guide slug) has no
// connection to attach to — log the drop rather than swallowing it silently.
const orphanBriefs = [...briefStems].filter((stem) => !connections.has(stem));
if (orphanBriefs.length > 0) {
  console.warn(`discount-core: ${orphanBriefs.length} brief(s) skipped — no matching connection: ${orphanBriefs.join(', ')}`);
}
const researchRows = [...briefStems]
  .filter((stem) => connections.has(stem)) // only briefs that resolve to a connection
  .map((stem) => {
    const b = connections.get(stem)!;
    // Copy the verbatim brief into the committed seed-data corpus so Stage B reads
    // it from an artifact, not the (out-of-repo) Astro source. `brief_path` stays
    // the source-of-record path for provenance/traceability.
    writeSeedText('research-briefs', stem, readFileSync(resolve(BRIEFS_DIR, `${stem}.md`), 'utf8'));
    return {
      connection_slug: stem,
      brief_path: `research/discounts/${stem}.md`,
      last_verified: (b.last_verified_at as string | null) ?? null,
      researched_by: 'claude-pipeline',
      status: researchStatusFor(b.status as string | undefined),
      version: 1,
    };
  });
writeArtifact('research', researchRows);
console.log(`wrote research-briefs/ — ${researchRows.length} briefs`);
