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
