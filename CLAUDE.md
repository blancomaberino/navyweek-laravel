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

## Everything else

The mandated per-task skill/quality-gate workflow (`/frontend-design` + `/seo-geo`
→ implement → `/simplify` → `/security-review` → commit; Pest + Larastan max + Pint
green; every task ships as a PR) is documented in `platform/README.md` and the root
`CLAUDE.md`. Follow it as written.
