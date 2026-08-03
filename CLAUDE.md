# NavyWeek Platform — agent rules (Laravel 13 + Filament v4)

Rules specific to the `platform/` Laravel app. The root `CLAUDE.md` holds the
site-wide context, SEO invariants, and the rebuild plan; `platform/README.md`
holds the commands, module map, and the domain-model/repository reference.

## Documentation rules (non-negotiable)

Two artifacts must never drift from the code. Both are updated **in the same PR**
as the change that affects them — a PR that adds domain code but not its docs is
incomplete.

- **Document every new model and repository.** When you add a domain model, or a
  repository (interface + its Eloquent implementation), add a row to the
  **"Domain models & repositories"** table in `platform/README.md` saying what it
  is for and which aggregate/module it belongs to. One row per model and one per
  repository. Keep the one-line purpose in sync if a model's responsibility
  changes.

- **Keep the architecture diagram current.** `platform/docs/architecture.md` is the
  living architecture diagram (data model + request/redirect pipeline). Any change
  that adds or removes a **table, model, repository, middleware, service, event/
  listener, or request-flow step** must update that diagram in the same PR. Treat a
  stale diagram as a failing gate — reviewers check it against the diff.

## Data access (repositories)

All data access in running-application code (controllers, console commands,
Filament widgets/pages, domain actions/listeners) goes through the repository
**interfaces** bound in `DomainServiceProvider` — never `Model::query()`/`::where`/
`::find` directly. (Filament Resource/Table/Form/RelationManager model binding is
the framework's own layer and is exempt; Stage-B ETL importers under
`app/Domain/*/Import/` are the one approved exception.)

- **Reuse before writing.** Before adding a new method to a repository — or writing
  any new function — first check whether one that already covers the need exists,
  and reuse it. Read the repository interface and its Eloquent implementation, and
  grep for similar signatures across the aggregate. Extend or compose an existing
  method rather than duplicating one that differs only trivially (e.g. batch
  `latestForConnection` into `latestForConnections` instead of adding a parallel
  query; call a repo method that already encodes a predicate instead of re-inlining
  it). Duplicate near-identical queries drift apart and cause bugs.

## URL paths for generated pages (identity ≠ location)

A generated page's IDENTITY is its stable `pages.generation_key`; its `url_path` is
mutable LOCATION (an editor can rename it; a family prefix can change). Three rules,
enforced **fail-closed** by `tests/Feature/PagePathHygieneTest.php` (static — a schema or
generator may only spell a route literal on an explicit ALLOWLIST; a NEW family's
hardcoded prefix fails until it goes through PagePaths) + `PageFamilyPathKnobTest.php`
(behavioral — one case per config family, plus a coverage test that fails if a
`config('publishing.paths')` family has no case). Treat a failure there as a real defect:

- **Never hardcode a family route literal** (`/navy-bases/`, `/discount/`, …). A page's
  OWN url comes from `$page->url_path`; family roots + ancestor/child links come from
  `App\Domain\Publishing\Support\PagePaths` (→ `config('publishing.paths.*')`, the single
  source of truth). This holds absolutely in `*Schema` classes.
- **Key idempotent generation on `generation_key`, never on `url_path`** (a mutable value).
  Build the key from stable ids/slugs (`base:{slug}`, `local-hub:city:{state}:{city}`, …).
- **When you add a page family** the guards tell you what's missing (fail-closed): add its
  prefix to `config/publishing.php`, build paths via `PagePaths`, key `upsertPillarPage` on
  a stable `generation_key`, derive the schema's `@id`/canonical from `$page->url_path`, and
  add a `knobFamilyCases()` row in `PageFamilyPathKnobTest` (the coverage test fails until
  you do). A genuinely one-off page (not a family) instead adds its fixed path to the
  allowlist in `PagePathHygieneTest` — a deliberate, reviewed opt-out.

**Meta-lesson (why this rule exists):** a faithful 1:1 port imports the SOURCE's
assumptions. The legacy static site had fixed URLs, so the port hardcoded them — wrong
for a CMS with editable URLs. When porting or repeating a pattern, first list the
assumptions the new architecture invalidates, and ask "is there a single source of truth
for this value, and is identity coupled to a mutable attribute?" — before writing the code.

## Everything else

The mandated per-task skill/quality-gate workflow (`/frontend-design` + `/seo-geo`
→ implement → `/simplify` → `/security-review` → commit; Pest + Larastan max + Pint
green; every task ships as a PR) is documented in `platform/README.md` and the root
`CLAUDE.md`. Follow it as written.
