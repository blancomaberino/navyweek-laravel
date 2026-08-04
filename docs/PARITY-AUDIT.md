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
