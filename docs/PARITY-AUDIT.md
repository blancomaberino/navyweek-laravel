# Local ↔ Remote parity audit (2026-08-04)

Evidence-based diff of the local Laravel platform against the live
`https://www.navyweek.org`. Method: remote sitemap URL inventory vs the local
`pages` registry, plus per-family `<main>` heading extraction from both sides
(script: `_scratch`/`cmp.py`, re-runnable).

**Verdict: the platform is NOT a mirror.** Three independent classes of gap.

---

## 1. Missing URLs — 73 remote URLs have no local page

| Family | Count | Examples |
|---|---|---|
| `/navy-designators/` hub + details + community hubs | 28 | `/navy-designators/`, `/navy-designators/1110-surface-warfare-officer/`, `/navy-designators/url/` |
| `/navy-bases/` hub + state/country hubs | 33 | `/navy-bases/`, `/navy-bases/virginia/`, `/navy-bases/japan/`, `/navy-bases/overseas/` |
| Discount category hubs | 5 | `/discount/hotels-military-veteran/`, `/discount/flights-military-veteran/` |
| Standalone pages | 5 | `/schedule/`, `/navy-reference/`, `/our-process/`, `/map/`, `/best-credit-cards-for-military/` |
| Feed artifacts | 2 | `/llms.txt`, `/data/navy-week-2026.json` |

Local-only (not on remote): `/authors/nw-admin/` (junk — throwaway admin user),
`/air-show/san-diego/` (unpublished remotely). `/contact/`, `/privacy/`, `/terms/`
exist on both but aren't sitemapped remotely.

## 2. Missing shared "trust" components — affects nearly EVERY page

The legacy `ReferenceTrust.tsx` / `KeyFacts.tsx` / `NavyReferenceBackLink.tsx`
components were never ported. Every reference/guide page on remote carries these
and local carries NONE of them:

- **`← Navy Reference` back link**
- **TrustDisclosure** — "NavyWeek.org is an independent publication…"
- **TrustByline** — *Written by* T Madden Alford + credentials, *Reviewed by*
  Erik Rivera + credentials, *Last reviewed* date, *Sources checked* date,
  "How we research & review: Our editorial process"
- **KeyFacts** — a titled label/value block + a primary `Source:` link
  (e.g. "U.S. Navy Ranks — Key Facts", 5 rows, navy.mil source)
- **ReferenceTrustFooter / "Editorial policy"** — 6 subsections (Source priority,
  Independence, Review cadence, Reviewer, Corrections, Not advice) + "See
  something out of date? / Report an outdated fact / contact page"
- **Cross-link cards** (e.g. ranks → Navy Ratings + Officer Designators cards)

## 3. Page bodies substantially unported

Heading counts, remote vs local (`<main>` only):

| Page | Remote | Local | Gap |
|---|---|---|---|
| `/veterans-home-care/` | 36 | 4 | body essentially absent |
| `/va-disability/` | 31 | 4 | body essentially absent |
| `/discount/{brand}/` ×981 | 20 | 10 | missing Who qualifies, How it works, Exclusions, More discounts, Editorial policy, most FAQs |
| `/navy-bases/{slug}/` ×58 | 18 | 10 | missing Overview, History, Commands, Geography, Host nation, Notable events, Nearby bases, FAQs |
| `/discount/` hub | 10 | 3 | missing Key Facts, Browse by category, FAQ h3s, Editorial policy |
| `/navy-ranks/` | 7 | 4 | missing Key Facts, Editorial policy |
| `/navy-ratings/` | 13 | 11 | missing Key Facts, Editorial policy |

### Systemic sub-issues

- **Heading case.** Remote section headings are LITERALLY uppercase in the HTML
  ("WHAT VETERANS DAY IS", "COMMISSIONED OFFICERS (HIGH → LOW)"); local renders
  sentence case. Not a CSS transform — the source content differs.
- **Heading style.** Remote h2 = display font, gold, 24px, 1.5px letter-spacing,
  bottom rule. Local h2 is unstyled by comparison.
- **Wrong title/h1 source for discounts.** Legacy `src/data/discounts/{slug}.ts`
  carries BOTH `metaTitle` and a separate `h1`. Remote `/discount/yeti-military-veteran/`
  → h1 "YETI Military & Veteran Discount", title "YETI Military Discount 2026:
  What They Don't Advertise". Local → h1 AND title both "YETI Military Discount
  2026: The Insider Hack". The platform import ignored `h1` and took a stale
  title variant. **The 988 files in `src/data/discounts/` are the live site's
  source of truth; the platform imported from `research/` briefs instead.** This
  divergence affects all 981 discount pages.
- **NATO prefix** dropped on rank rows (remote "NATO OF-9", local "OF-9").
- **Markdown not rendered** — air-show/city intros emit literal `[Blue Angels](/blue-angels/)`.

---

## Fix sequencing (each a gated PR)

1. Shared trust components (Disclosure, Byline, KeyFacts, EditorialPolicy,
   back-link, cross-link cards) as Blade partials + CSS — unlocks every page.
2. Re-import discounts from `src/data/discounts/*.ts` (h1 + metaTitle + all body
   sections) — fixes 981 pages.
3. Port the YMYL guide bodies (`va-disability`, `veterans-home-care`,
   `veterans-day`) — CMS-backed per the agreed `body_blocks` spec.
4. Base-page body sections (58 pages).
5. Build the 7 missing families (designators, bases hubs, category hubs,
   schedule, navy-reference, our-process, map, best-credit-cards).
6. Heading case/style pass + markdown rendering + NATO prefix + drop
   `/authors/nw-admin/`.

Every step verified by re-running the parity script, not by eyeballing.

---

# RESULT (2026-08-05) — parity reached

Re-run of the same measurements that opened this document.

## URL inventory

| | Then | Now |
|---|---|---|
| Remote sitemap URLs | 1164 | 1164 |
| Missing locally | **73** | **0** |

The only two entries the diff still lists (`/llms.txt`,
`/data/navy-week-2026.json`) are generated files rather than `pages` rows; both
serve 200.

## Heading-outline parity — 34/34 sampled pages match

One page per family, verified with the diff script (not by eye):

| Page | Then | Now |
|---|---|---|
| `/` | 8 | 16/16 |
| `/navy-ranks/`, `/navy-ratings/` | 4, 11 | 6/6, 13/13 |
| `/navy-reference/`, `/schedule/`, `/map/` | — (404) | 3/3, 2/2, 3/3 |
| `/navy-bases/` + state/country/overseas hubs | — (404) | 12/12, 5/5, 10/10, 12/12 |
| `/navy-bases/camp-lemonnier/` | 10 | 18/18 |
| `/navy-designators/` hub + community + detail | — (404) | 5/5, 1/1, 14/14 |
| `/discount/` | 3 | 10/10 |
| `/discount/{brand}/` ×981 | 10 | 20/20 |
| `/discount/{category}/` ×5 | — (404) | 3/3 |
| `/discounts/{state}/{city}/{biz}/` | 9 (wrong titles) | 9/9 |
| `/veterans-day/`, `/veterans-day/free-meals/` | 8, 7 | 10/10, 6/6 |
| `/va-disability/`, `/veterans-home-care/` | 4, 4 | 31/31, 36/36 |
| `/best-credit-cards-for-military/`, `/our-process/` | — (404) | 27/27, 11/11 |
| `/authors/{slug}/` | 4 | 6/6 |
| `/city/{slug}/` ×12 | 13 | 32/32, 34/34 |
| `/fleetweek/{slug}/` ×16 | 4 | 22/22, 25/25 |
| `/air-show/{slug}/`, jet-team hubs + cities | 9, 3, 7 | 18/18, 11/11, 16/16 |

## What the gaps actually were

Most of this was **render**, not data: the pillars had already imported
overview/history/schedule/FAQ/quick-facts rows that no view read. The genuine
data gaps were narrow — discount `details` ("How it works"), the YMYL long-form
bodies, and the author service-history fields — and each was closed by a
committed seed artifact + an idempotent importer, so the CMS now owns them.

Two crashes were fixed along the way: the shared KeyFacts partial 500'd on an
array fact value (every air-show and jet-team-city page), and `military_context`
being a list rather than a string 500'd the city guides.

## Known non-issues

`/our-process/` reports two phantom diffs: the remote h1 is split across spans so
the extractor drops a space, and the DEALS section sits inside `<main>` there but
after it here. Both are artifacts of the text extractor, not visible differences.


---

# CORRECTION (2026-08-05) — the heading metric was wrong

The "34/34 headings match" result above **did not mean the site looked the same**.
Heading outlines are blind to layout, spacing, typography, icons and behaviour.
`/schedule/` scored 2/2 while **94.3% of its pixels differed** — the entire body
(intro copy, six key facts, official-schedule links, filter bar, event cards) was
missing or invented. Treat that section as a record of structural coverage only.

Parity is now measured with `tools/vdiff` (Playwright + pixelmatch): same path on
local and remote, both viewports, ranked worst-first. **>1% differing pixels is a
failing gate.**

## Desktop pixel diff — progress

| Page | First measured | Now |
|---|---|---|
| `/schedule/` | 94.3% | **1.5%** |
| `/city/billings/` | 20.8% | **2.1%** |
| `/` | 18.1% | **1.2%** |
| `/city/rio-grande-valley/` | 11.0% | **2.8%** |
| `/navy-ranks/` | 4.1% | **1.8%** |
| worst page on the site | 94.3% | **12.3%** |

No page is above 15% any more; the median sits near 4%.

## Root causes found by measuring rather than eyeballing

Systemic (every page):
- **`main` padding** — the legacy uses 48px horizontally, ours used 24px.
- **No base element styles in the legacy.** `global.css` defines NO h1–h4 / p /
  ul / li rules; Tailwind preflight plus per-component INLINE styles do it all.
  Our invented globals fought every ported component. Aligned body line-height
  (1.5), h1 (clamp(48px,6vw,72px)/lh 1/ls 4px), removed the `li` margin.
- **Shared components were being overridden.** `.key-facts h2` sits inside pages
  that style their own h2 at equal specificity, so the page rule won by source
  order and rendered it at 24px display instead of 11px mono.
- **`.intro`** was 1.15rem/70ch vs the legacy 17px/760px, so long intros wrapped
  early on every reference page.
- **Breadcrumb** typography and margin were all slightly off, shifting everything
  below it.

Per family: the home and city heroes (full-bleed image + overlays) were missing
entirely; `/schedule/` had no filter bar or event cards; discount guides had no
logo chip, no lead intro, no breadcrumb and no credit-cards callout.

## Still open

- `/discounts/{state}/{city}/{biz}/` (12.3%) — the legacy ships a self-contained
  `LOCAL_DISCOUNT_CSS` block with a two-column layout and a sticky NAP sidebar;
  ours is a generic single column.
- The YMYL/content pages (6–8%) and event families (5–7%) need their per-view
  inline styles ported the same way.
- **Discount data divergence** persists beyond h1/intro/details: `discount_summary`,
  CTA label/subnote, tier table columns and source labels still come from the
  research briefs rather than `src/data/discounts/*.ts`, so YETI (5.9%) differs
  from Nike (1.2%) on the same template.
