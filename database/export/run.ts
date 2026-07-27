// Stage-A exporter entrypoint. Run with `npm run export:legacy` (or
// `npx tsx database/export/run.ts`) from the platform root, with the sibling
// Astro repo checked out at ../ (it supplies the legacy src/data modules).
//
// Add one import line per domain as its exporter lands. Each imported module
// writes its own database/seed-data/*.json artifacts as a side effect.

import './air-shows';
import './bases';
import './fleet-weeks';
import './navy-week-events';
import './ranks';
