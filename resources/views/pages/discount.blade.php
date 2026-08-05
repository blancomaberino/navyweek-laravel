@extends('layouts.base')

{{-- Discount-brand guide body — ported 1:1 from the legacy
     src/page-views/DiscountDetail.tsx (its styling is inline in the component;
     the CSS equivalent lives in resources/css/families/discount.css).

     Section order, verbatim from the legacy component:
       hero (breadcrumb → logo chip + category → h1 → tagline → intro →
             credit-card callout → promo → byline → CTA + subnote → key facts)
       → insider savings hack → best savings path → who qualifies (+ tier table)
       → ask-the-brand share CTA (advisory pages) → how to redeem → how it works
       (+ exclusions) → sources → FAQ → more discounts + back link + trust footer.

     Everything renders from the CMS (Offer + Connection), never the legacy registry. --}}
@php
    use App\Domain\Navigation\Support\LinkUrl;
    use App\Domain\Publishing\Support\PagePaths;

    $connection = $offer->connection;
    $brand = $connection?->brand ?? $page->title;
    $officialUrl = LinkUrl::sanitize((string) $offer->official_url);
    // Legacy `ctaSubnote` fallback: "Opens {host+path} · Verification via {provider}".
    $officialHost = preg_replace('#^https?://|/$#', '', (string) $offer->official_url);
    $verificationLabel = $offer->verification?->label();
    $brandHome = preg_replace('#^https?://|/$#', '', (string) ($connection?->brand_home_url ?? $offer->official_url));
    $lastVerified = $page->last_reviewed?->format('F j, Y') ?? $page->date_modified?->format('F j, Y');
    $online = $offer->redemptionSteps->where('channel', \App\Domain\Catalog\Enums\RedemptionChannel::Online);
    $inStore = $offer->redemptionSteps->where('channel', \App\Domain\Catalog\Enums\RedemptionChannel::InStore);
    $chooser = $offer->chooser;
    $singleQuestion = $chooser !== null && ($chooser['question2'] ?? null) === null;
@endphp

@section('content')
    <main class="nw-discount-page discount-guide">
        {{-- ---------------------------------------------------------------- Hero --}}
        <section class="dg-section dg-hero">
            <nav class="dg-crumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ PagePaths::root('discounts') }}" data-testid="breadcrumb-discounts">Military Discounts</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $brand }}</span>
            </nav>

            <div class="dg-brandrow">
                @if ($connection?->logo_url)
                    {{-- The chip colour is the brand's own `logoBackground` (~120 marks
                         are light-on-dark and set navy/black); the controller has
                         already constrained it to a hex literal. --}}
                    <div class="dg-logo-chip" style="background:{{ $logoHero['background'] }}">
                        <img src="{{ $connection->logo_url }}"
                             alt="{{ $brand }} logo"
                             loading="eager"
                             style="max-height:{{ $logoHero['maxHeight'] }}px;max-width:{{ $logoHero['maxWidth'] }}px">
                    </div>
                @endif
                @if ($connection?->category)
                    <span class="dg-category">{{ $connection->category }}</span>
                @endif
            </div>

            {{-- The legacy records carry a separate on-page `h1` distinct from the
                 <title> (`metaTitle`), so prefer it and fall back to the title. --}}
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            @if ($offer->hero_tagline)
                <p class="dg-tagline">{{ $offer->hero_tagline }}</p>
            @endif

            @foreach ($offer->intro ?? [] as $paragraph)
                <p class="dg-lead">{{ $paragraph }}</p>
            @endforeach

            {{-- Template-wide interlink to the credit-cards guide: sits right after the
                 intro on every brand guide so the link is crawled early site-wide, but
                 stays a bordered callout (not a button) so it never competes with the
                 hero CTA below. --}}
            <aside class="dg-cc-callout" aria-label="Related guide: best credit cards for military" data-testid="discount-credit-card-callout">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dg-cc-icon" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                <div>
                    <p class="dg-cc-eyebrow">Maximize every purchase</p>
                    <p class="dg-cc-body">A military discount is only half the savings — the right card earns on what&rsquo;s left. See our guide to the <a href="/best-credit-cards-for-military/" data-testid="link-best-credit-cards">best credit cards for military</a> members, including cards with annual fees waived under SCRA and MLA.</p>
                </div>
            </aside>

            @if (filled($offer->promo))
                <div class="dg-promo" data-testid="discount-promo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg>
                    {{ $offer->promo['label'] ?? '' }} — through {{ $offer->promo['expiresLabel'] ?? '' }}
                </div>
            @endif

            @include('partials.trust.byline', ['publishDate' => true, 'processLinkNewTab' => true])

            {{-- Primary CTA. `nw-hero-cta` publishes the view timeline that drives the
                 mobile sticky footer CTA's reveal (see the sticky bar at the end). --}}
            @if ($officialUrl !== '')
                <a class="nw-hero-cta"
                   href="{{ $officialUrl }}"
                   target="_blank"
                   rel="sponsored noopener noreferrer"
                   data-testid="discount-cta">
                    {{ $offer->cta_label ?: 'Verify & redeem at '.$brand }}
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg>
                </a>
                <p class="dg-cta-note">{{ $offer->cta_subnote ?: 'Opens '.$officialHost.($verificationLabel ? ' · Verification via '.$verificationLabel : '') }}</p>
            @endif

            {{-- KeyFacts block. Discount pages key off the OFFER's key_facts (the
                 per-brand facts), not the page-level column. --}}
            @include('partials.trust.key-facts', ['keyFacts' => filled($offer->key_facts) ? [
                'title' => $brand.' Military Discount — Key Facts',
                'ariaLabel' => $brand.' discount key facts',
                'facts' => $offer->key_facts,
                'lastVerified' => $lastVerified,
                'source' => $officialUrl !== '' ? [
                    'label' => $offer->sources->first()?->label ?? $brand.' official page',
                    'url' => $officialUrl,
                    'rel' => 'sponsored noopener noreferrer',
                ] : null,
            ] : null])
        </section>

        {{-- Insider Savings Hack — the single highest-value real move, high on the
             page so it closes the curiosity gap the title opens. --}}
        @if (filled($offer->savings_hack))
            <section class="dg-section" aria-labelledby="insider-hack">
                <div class="dg-hack" data-testid="savings-hack">
                    <div class="dg-hack-eyebrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path><path d="M20 2v4"></path><path d="M22 4h-4"></path><circle cx="4" cy="20" r="2"></circle></svg>
                        {{ $offer->savings_hack['label'] ?? 'Insider Savings Hack' }}
                    </div>
                    <h2 id="insider-hack" class="dg-hack-headline">{{ $offer->savings_hack['headline'] ?? '' }}</h2>
                    @if (filled($offer->savings_hack['body'] ?? null))
                        <p class="dg-lead">{{ $offer->savings_hack['body'] }}</p>
                    @endif
                    @if (filled($offer->savings_hack['steps'] ?? null))
                        <ol class="dg-steps">
                            @foreach ($offer->savings_hack['steps'] as $step)
                                <li>
                                    <span class="dg-step-num" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <span class="dg-step-text">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if (filled($offer->savings_hack['caveat'] ?? null))
                        <p class="dg-hack-caveat">{{ $offer->savings_hack['caveat'] }}</p>
                    @endif
                </div>
            </section>
        @endif

        {{-- Best savings path — optional decision table(s) + the CSS-only chooser --}}
        @if (filled($chooser) || filled($offer->savings_table) || filled($offer->savings_table_secondary))
            <section class="dg-section" aria-labelledby="best-savings-path">
                <h2 id="best-savings-path">BEST SAVINGS PATH</h2>
                <p class="dg-lead">The smartest route depends on your situation. {{ $singleQuestion ? 'Answer the question' : 'Answer the two questions' }} to find your best path, or scan the full decision {{ filled($offer->savings_table_secondary) ? 'tables' : 'table' }} below.</p>

                @if (filled($chooser))
                    {{-- CSS-only interactive chooser — ZERO JavaScript. Hidden radio inputs
                         hold the answers; labels are the Yes/No buttons; every outcome is in
                         the DOM (crawlable) and the matching one is revealed via :has() on
                         the checked radios. With nothing selected the static
                         `defaultRecommendation` shows. --}}
                    @php
                        $outcomeClass = static fn (array $o): string => $singleQuestion
                            ? 'nw-out-'.(($o['q1'] ?? false) ? 'yes' : 'no')
                            : 'nw-out-'.(($o['q1'] ?? false) ? 'yes' : 'no').'-'.(($o['q2'] ?? false) ? 'yes' : 'no');
                        $chooserRules = '';
                        foreach ($chooser['outcomes'] ?? [] as $o) {
                            $sel = $singleQuestion
                                ? '.nw-chooser-body:has(#nw-q1-'.((($o['q1'] ?? false)) ? 'yes' : 'no').':checked)'
                                : '.nw-chooser-body:has(#nw-q1-'.((($o['q1'] ?? false)) ? 'yes' : 'no').':checked):has(#nw-q2-'.((($o['q2'] ?? false)) ? 'yes' : 'no').':checked)';
                            $chooserRules .= $sel.' .nw-default{display:none;}'.$sel.' .'.$outcomeClass($o).'{display:block;}';
                        }
                    @endphp
                    <div class="nw-chooser" data-testid="savings-chooser">
                        <style>{!! $chooserRules !!}</style>
                        <h3 class="dg-block-h3">Find your best path</h3>
                        <div class="nw-chooser-body">
                            <input type="radio" name="nw-q1" id="nw-q1-yes" class="nw-cr">
                            <input type="radio" name="nw-q1" id="nw-q1-no" class="nw-cr">
                            @unless ($singleQuestion)
                                <input type="radio" name="nw-q2" id="nw-q2-yes" class="nw-cr">
                                <input type="radio" name="nw-q2" id="nw-q2-no" class="nw-cr">
                            @endunless

                            <div class="nw-chooser-q">
                                <p class="nw-chooser-qlabel">1. {{ $chooser['question1'] ?? '' }}</p>
                                <div class="nw-chooser-btns">
                                    <label for="nw-q1-yes" class="nw-btn nw-lbl-q1-yes" data-testid="chooser-q1-yes">Yes</label>
                                    <label for="nw-q1-no" class="nw-btn nw-lbl-q1-no" data-testid="chooser-q1-no">No</label>
                                </div>
                            </div>

                            @unless ($singleQuestion)
                                <div class="nw-chooser-q">
                                    <p class="nw-chooser-qlabel">2. {{ $chooser['question2'] }}</p>
                                    <div class="nw-chooser-btns">
                                        <label for="nw-q2-yes" class="nw-btn nw-lbl-q2-yes" data-testid="chooser-q2-yes">Yes</label>
                                        <label for="nw-q2-no" class="nw-btn nw-lbl-q2-no" data-testid="chooser-q2-no">No</label>
                                    </div>
                                </div>
                            @endunless

                            <div class="nw-chooser-out" role="status">
                                <div class="nw-out nw-default" data-testid="chooser-default">
                                    <div class="nw-out-eyebrow">{{ $singleQuestion ? 'Answer to see your best path' : 'Answer both to see your best path' }}</div>
                                    <p class="nw-out-reason">{{ $chooser['defaultRecommendation'] ?? '' }}</p>
                                </div>
                                @foreach ($chooser['outcomes'] ?? [] as $o)
                                    <div class="nw-out {{ $outcomeClass($o) }}" data-testid="chooser-result">
                                        <div class="nw-out-eyebrow">Your best path</div>
                                        <div class="nw-out-winner">{{ $o['winner'] ?? '' }}</div>
                                        <p class="nw-out-reason">{{ $o['reason'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @foreach (array_filter([$offer->savings_table, $offer->savings_table_secondary]) as $table)
                    <div class="dg-savings-table @if (! $loop->first) dg-savings-table-second @endif" data-testid="savings-table">
                        @if (filled($table['title'] ?? null))
                            <h3 class="dg-block-h3 dg-block-h3-tight">{{ $table['title'] }}</h3>
                        @endif
                        <div class="dg-table-wrap">
                            <table class="dg-path-table">
                                <caption>Effective price on {{ $table['baselineLabel'] ?? '' }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Path</th>
                                        <th scope="col" class="dg-nowrap">Stack</th>
                                        <th scope="col" class="dg-nowrap">Effective price</th>
                                        <th scope="col" class="dg-nowrap">You save</th>
                                        <th scope="col">Best when</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] ?? [] as $row)
                                        <tr>
                                            <th scope="row" class="dg-path-name">{{ $row['path'] ?? '' }}</th>
                                            <td>{{ $row['stack'] ?? '' }}</td>
                                            <td class="dg-price">{{ $row['effectivePrice'] ?? '' }}@if ($row['rateStamped'] ?? false)<span class="dg-star" aria-hidden="true"> *</span>@endif</td>
                                            <td class="dg-price dg-nowrap">{{ $row['youSave'] ?? '' }}</td>
                                            <td>{{ $row['bestWhen'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (collect($table['rows'] ?? [])->contains(static fn (array $r): bool => (bool) ($r['rateStamped'] ?? false)))
                            <p class="dg-rates-note">* Cashback figures — rates as of {{ $table['ratesAsOf'] ?? '' }}. Portal cashback rates move weekly, so re-check the current top portal before you buy.</p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        {{-- --------------------------------------------------------- Who qualifies --}}
        <section class="dg-section" aria-labelledby="who-qualifies">
            <h2 id="who-qualifies">WHO QUALIFIES</h2>
            @if ($offer->discount_summary)
                <p class="dg-lead">{{ $offer->discount_summary }}</p>
            @endif
            @if (filled($offer->eligibility))
                <ul class="dg-eligibility">
                    @foreach ($offer->eligibility as $item)
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (filled($offer->tiers))
                <div class="dg-table-wrap">
                    <table class="dg-tier-table">
                        <caption>{{ $brand }} discount by community</caption>
                        <thead>
                            <tr><th scope="col">Audience</th><th scope="col" class="dg-nowrap">Discount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($offer->tiers as $tier)
                                <tr>
                                    <th scope="row">
                                        {{ $tier->audience }}
                                        @if ($tier->note)<span class="dg-tier-note">{{ $tier->note }}</span>@endif
                                    </th>
                                    <td class="dg-price dg-nowrap">{{ $tier->amount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Ask-the-brand social share — advisory (no-discount) pages only. Turns the
             "no discount" answer into a growth loop: pre-composed posts ask the brand
             for a military discount and link back here. Static, no client JS. --}}
        @if ($showShareCta)
            <section class="dg-section" aria-labelledby="ask-brand">
                <div class="dg-share" data-testid="discount-share-cta">
                    <div class="dg-share-eyebrow">No discount? Make some noise</div>
                    <h2 id="ask-brand" class="dg-share-headline">{{ $share['headline'] }}</h2>
                    <p class="dg-lead">{{ $share['blurb'] }}</p>
                    <blockquote class="dg-share-preview" data-testid="discount-share-preview">{{ $share['postText'] }}</blockquote>
                    <div class="nw-share-btn-row">
                        <a href="{{ $share['xIntentUrl'] }}" target="_blank" rel="noopener noreferrer" class="dg-share-btn dg-share-btn-solid" aria-label="Share this ask on X" data-testid="discount-share-x">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                            Share on X
                        </a>
                        <a href="{{ $share['facebookUrl'] }}" target="_blank" rel="noopener noreferrer" class="dg-share-btn dg-share-btn-ghost" aria-label="Share this ask on Facebook" data-testid="discount-share-facebook">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                            Share on Facebook
                        </a>
                        <details class="nw-share-ig" data-testid="discount-share-instagram">
                            <summary aria-label="Get the Instagram caption">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"></path></svg>
                                Instagram
                            </summary>
                            <div class="nw-share-ig-body">
                                <p class="dg-share-ig-label">Copy this caption, then post it on Instagram</p>
                                <p class="nw-share-caption" data-testid="discount-share-ig-caption">{{ $share['instagramCaption'] }}</p>
                                <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="nw-share-open-ig" data-testid="discount-share-ig-open">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"></path></svg>
                                    Open Instagram
                                </a>
                            </div>
                        </details>
                    </div>
                    <p class="dg-share-foot">Every post links back to this page, so the next person searching for a {{ $brand }} military discount finds the straight answer — and the brands that do honor the military.</p>
                </div>
            </section>
        @endif

        {{-- --------------------------------------------------------- How to redeem --}}
        {{-- The online column always renders (an empty <ol> when a brand is in-store
             only) — it is what splits the grid into two tracks, and `auto-fit` would
             otherwise collapse to one full-width track and re-wrap every step. --}}
        <section class="dg-section" aria-labelledby="how-to-redeem">
            <h2 id="how-to-redeem">HOW TO REDEEM</h2>
            <div class="dg-redeem-grid @if ($inStore->isNotEmpty()) dg-redeem-grid-split @endif">
                @foreach ([($brandHome !== '' ? "Online at {$brandHome}" : 'Online') => $online, 'In store' => $inStore] as $channelLabel => $steps)
                    @if ($loop->first || $steps->isNotEmpty())
                        <div>
                            <h3 class="dg-block-h3">{{ $channelLabel }}</h3>
                            <ol class="dg-steps">
                                @foreach ($steps as $step)
                                    <li>
                                        <span class="dg-step-num" aria-hidden="true">{{ $loop->iteration }}</span>
                                        <div>
                                            <div class="dg-step-title">{{ $step->title }}</div>
                                            <div class="dg-step-detail">{{ $step->detail }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- ------------------------------------------- How it works + fine print --}}
        @if (filled($offer->details) || filled($offer->exclusions))
            <section class="dg-section" aria-labelledby="how-it-works">
                <h2 id="how-it-works">HOW IT WORKS</h2>
                @foreach ($offer->details ?? [] as $paragraph)
                    <p class="dg-lead">{{ $paragraph }}</p>
                @endforeach

                @if (filled($offer->exclusions))
                    <h3 class="dg-block-h3 dg-block-h3-spaced">Exclusions &amp; fine print</h3>
                    <ul class="dg-fineprint">
                        @foreach ($offer->exclusions as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- ------------------------------------------------------------- Sources --}}
        @if ($offer->sources->isNotEmpty())
            <section class="dg-section" aria-labelledby="sources">
                <h2 id="sources">SOURCES</h2>
                <ul class="dg-sources">
                    @foreach ($offer->sources as $source)
                        <li>
                            <a href="{{ LinkUrl::sanitize((string) $source->url) }}" target="_blank" rel="noopener noreferrer nofollow">{{ $source->label }}</a>
                            @if ($source->publisher)<span class="dg-source-publisher"> — {{ $source->publisher }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ----------------------------------------------------------------- FAQ --}}
        @if ($offer->faqs->isNotEmpty())
            <section class="dg-section" aria-labelledby="faq">
                <h2 id="faq">FREQUENTLY ASKED QUESTIONS</h2>
                {{-- CSS-only FAQ accordion — native <details>/<summary>, ZERO JavaScript.
                     Every answer is in the DOM (crawlable); the first item is open. --}}
                <div class="dg-faq-list">
                    @foreach ($offer->faqs as $faq)
                        <details class="nw-faq" @if ($loop->first) open @endif>
                            <summary>
                                <h3>{{ $faq->question }}</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nw-faq-chev" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </summary>
                            <div class="nw-faq-a">{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ------------------------------------------- Related + back link + trust --}}
        <section class="dg-section dg-section-last">
            @if (filled($relatedBrands))
                <h2>MORE MILITARY DISCOUNTS</h2>
                <div class="dg-related">
                    @foreach ($relatedBrands as $related)
                        <a href="{{ $related['url'] }}" data-testid="link-related-{{ $related['slug'] }}">
                            <span class="dg-related-headline">{{ $related['headline'] }}</span>
                            <span class="dg-related-brand">{{ $related['brand'] }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <a class="dg-back-link" href="{{ PagePaths::root('discounts') }}" data-testid="link-back-to-hub">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dg-flip" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                All military &amp; veteran discounts
            </a>

            @include('partials.trust.editorial-policy')
        </section>
        {{-- Mobile-only sticky "Claim {Brand} Discount" bar. ZERO client JavaScript: the
             hero CTA publishes a named view timeline, `main` hoists it via timeline-scope,
             and the bar animates hidden→visible across the hero CTA's exit range. Browsers
             with no scroll-driven animation support FAIL OPEN (bar always shown on mobile). --}}
        @if ($officialUrl !== '')
            <div class="nw-sticky-cta">
                <a href="{{ $officialUrl }}" target="_blank" rel="sponsored noopener noreferrer" data-testid="discount-sticky-cta">
                    <span aria-hidden="true">&#128073;</span>
                    <span>{{ $offer->sticky_cta_label ?: 'Claim '.$brand.' Discount' }}</span>
                </a>
            </div>
        @endif
    </main>

@endsection
