// Stage-A exporter — shared artifact writer.
//
// Each domain exporter transforms the legacy TypeScript data (imported from the
// sibling Astro repo at ../../../src/data) into the exact DB-column shape and
// hands it to `writeArtifact`, which serializes it to database/seed-data/<name>.json.
// Those committed JSON artifacts are the handoff contract to Stage B (the artisan
// importers), so the import is reproducible without the Astro source present.

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

/** Absolute path to platform/database/seed-data (this file lives in export/lib). */
const SEED_DIR = resolve(import.meta.dirname, '../../seed-data');

/** Kebab-slug guard — the same shape Stage B validates before building a path. */
const SLUG = /^[a-z0-9-]+$/;

/**
 * Serialize one domain's records to database/seed-data/<name>.json, pretty-printed
 * with a trailing newline so the artifacts are stable and diff-friendly.
 */
export function writeArtifact(name: string, records: readonly unknown[]): void {
  const path = resolve(SEED_DIR, `${name}.json`);
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, `${JSON.stringify(records, null, 2)}\n`, 'utf8');
  console.log(`wrote ${name}.json — ${records.length} records`);
}

/**
 * Copy a text corpus file (e.g. a research brief) into
 * database/seed-data/<dir>/<slug>.md so Stage B can read the verbatim content
 * from a committed artifact — the same handoff contract as the JSON, for payloads
 * too large to inline into one file. The slug is a bare kebab key, never a path.
 */
export function writeSeedText(dir: string, slug: string, contents: string): void {
  if (!SLUG.test(dir) || !SLUG.test(slug)) {
    throw new Error(`writeSeedText: unsafe dir/slug "${dir}/${slug}"`);
  }
  const path = resolve(SEED_DIR, dir, `${slug}.md`);
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, contents, 'utf8');
}
