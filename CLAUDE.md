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

## Visual verification is part of "done" (non-negotiable for any rendered page)

A page is NOT done because it returns HTTP 200 and its DOM/JSON-LD greps clean.
`curl` + status-code + content-grep prove the page is *functionally* and *SEO*
correct; they say NOTHING about whether it is styled or looks right. This is a
website — visual fidelity is a first-class requirement, not a follow-up.

For ANY task that renders or changes a user-facing page, before you call it done:

- **Never verify UI with `curl`.** `curl` + status + content-grep is not acceptable
  proof for a rendered feature. Use a real browser (headless or the in-app browser /
  `mcp__Claude_Browser__*`).
- **Actually look at it.** Open the running app in the browser and screenshot the page
  at desktop AND mobile widths. Confirm a stylesheet is linked and applied (the base
  layout must `@vite` the built CSS), the design tokens render (Fleet Navy background,
  Service Gold accents, Bebas Neue / IBM Plex fonts), and the chrome (header + footer)
  is present.
- **E2E-test every user-facing feature.** A rendered feature ships with a browser-driven
  end-to-end test (Laravel Dusk) that loads the real page in a headless browser and
  asserts the user-visible behavior — the page paints, the design system is applied
  (e.g. a known brand token/CSS rule is in effect, not just present in markup), links
  navigate, and any interactive control (filters, accordions, mobile menu) works.
  Pest feature tests over the HTTP kernel check the response body but NOT rendering,
  CSS, or JS — they are necessary but NOT sufficient. Both are required.
- **Compare to the legacy.** This is a 1:1 rebuild — put the platform page next to
  the legacy page (`_scratch/source/` or the live site) and check they read the same.
- **Treat "renders but unstyled" as a failing gate**, exactly like a red test. A
  missing/again-unlinked stylesheet, a page with no header/footer, or a view whose
  classes have no CSS is a defect to fix now, not later.

(Root cause of the 2026-08-03 miss: pages were verified only by `curl`/status/DOM,
so an entire un-ported design system — no CSS even linked in the base layout — shipped
invisibly. Never verify a rendered page without viewing it.)

## Parity means PIXELS, not headings (non-negotiable)

This is a 1:1 rebuild of a live site. "Parity" is only ever proven by a **visual
diff of the rendered page against the remote**, at BOTH desktop (1280) and mobile
(375) widths. Nothing else counts.

**Never claim parity from a structural proxy.** Heading counts, DOM greps,
JSON-LD checks and HTTP 200s are all blind to layout, spacing, typography, icons
and behaviour. A page can match heading-for-heading and still be completely wrong:
`/schedule/` once scored "2/2 headings match" while 94% of its pixels differed —
the whole body (filters, cards, key facts, intro copy) was missing or invented.

The harness lives at `_scratch`/`vdiff/diff.mjs` (Playwright + pixelmatch):
screenshots the same path on local and remote, pixel-diffs, and ranks worst-first.
Run it for every page you touch, and re-run it after each fix:

```
node diff.mjs urls.txt [desktop|mobile]
```

Treat **>1% differing pixels as a failing gate**, exactly like a red test, and read
the emitted `*.diff.png` to see WHERE it differs.

**Port from the source, don't infer from the rendering.** The original site's code
is in the repo root (`src/components/*`, `src/page-views/*`, `src/styles/global.css`).
When a page differs, open its component and port the actual markup and styles —
much of the legacy styling is INLINE in the components, so re-deriving it from a
screenshot guarantees drift. Approximating "close enough" CSS is how the header nav
shipped with a text glyph instead of the lucide chevron and with the Events
dropdown missing its indented air-show sub-items entirely.

**Behaviour is part of parity too** — dropdowns, filters, accordions and the mobile
menu must work the same way, not just look the same at rest.

**A full-page diff CANNOT see an interaction state.** `diff.mjs` screenshots the
page at rest, so a menu, accordion or modal that is closed contributes nothing to
its number — a component can be 100% wrong and the page still score 0.0%. Anything
that opens needs its own diff in the opened state:

```
node tools/vdiff/menu-desktop.mjs      # Events dropdown, forced open
node tools/vdiff/menu-mobile.mjs       # mobile panel + Events accordion, forced open
```

Checking the DOM instead does NOT substitute. The Events sub-items once had the
right labels, the right count and the right hrefs while rendering gold mixed-case
unindented text against remote's grey uppercase indented rows, because
`.nw-dropdown-subevent` had no CSS at all — a DOM check passes that, a pixel diff
of the open menu catches it immediately. When you add a component with an open
state, add its diff script here in the same change.

## CSS authoring — alphabetical property order (enforced)

Within every declaration block of our **authored** CSS, list properties in
**alphabetical order**. Scope: `resources/css/*.css` and every hand-written `<style>`
block in a Blade view. Enforced fail-closed by `tests/Feature/CssPropertyOrderTest.php`
(it names the offending block and prints the expected order) — treat a failure there as
a real defect, exactly like the other guards.

- **Keep inline comments OUT of declaration blocks** — put the note above the selector.
  The guard strips comments before checking, so a mid-block comment can neither hide an
  out-of-order property nor be reordered relative to a declaration.
- **Machine-generated CSS is out of scope** (e.g. the compiled Tailwind dump in the stock
  `welcome.blade.php`); the test skips any `<style>` carrying the Tailwind banner. Never
  hand-order generated output.
- Alphabetical order is cascade-safe here because a shorthand always sorts before its
  longhands (`border` before `border-top`), which is the correct override order — so don't
  reintroduce a longhand-before-shorthand pairing to "fix" the cascade.

## Everything else

The mandated per-task skill/quality-gate workflow (`/frontend-design` + `/seo-geo`
→ implement → `/simplify` → `/security-review` → commit; Pest + Larastan max + Pint
green; every task ships as a PR) is documented in `platform/README.md` and the root
`CLAUDE.md`. Follow it as written. **`/frontend-design` is not optional** — the design
system exists (`design/` + legacy `src/styles/global.css` tokens); port it faithfully
and view the result.
