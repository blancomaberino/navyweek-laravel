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

## Everything else

The mandated per-task skill/quality-gate workflow (`/frontend-design` + `/seo-geo`
→ implement → `/simplify` → `/security-review` → commit; Pest + Larastan max + Pint
green; every task ships as a PR) is documented in `platform/README.md` and the root
`CLAUDE.md`. Follow it as written.
