@extends('layouts.base')

{{-- /discount/ directory landing — ported 1:1 from the legacy
     src/page-views/DiscountHub.tsx (styling was inline there; the CSS equivalent
     lives in resources/css/families/discount.css). Head/JSON-LD (Breadcrumb +
     Article + ItemList + FAQPage) stay byte-locked by SeoHead + DiscountIndexSchema. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    $lastVerified = $page->last_reviewed?->format('F j, Y') ?? $page->date_modified?->format('F j, Y');
    $hubRoot = PagePaths::root('discounts');
@endphp

@section('content')
    <main class="discount-index">
        <section class="dh-section dh-hero">
            <nav class="dg-crumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Military Discounts</span>
            </nav>

            {{-- The hub's own independence wording (TrustDisclosure's `children`
                 override in DiscountHub.tsx), not the generic reference-page copy. --}}
            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>NavyWeek.org is an independent guide. We are <strong>not affiliated with, endorsed by, or sponsored by</strong> any brand listed here. Each company sets and controls its own discount terms, which can change at any time. Company names and logos are trademarks of their respective owners, shown for identification only. Always confirm the current offer on the brand&rsquo;s official page before purchasing.</p>
            </section>

            <div class="dh-eyebrow">// Military &amp; Veteran Savings</div>
            <h1>{{ $page->h1 ?? 'MILITARY DISCOUNTS' }}</h1>
            <p class="dh-intro">A growing directory of verified military and veteran discounts from major brands. Every guide breaks down who qualifies, how to verify your service for free (through ID.me, GovX, and similar), how to redeem online and in store, and the fine print to watch — then links you straight to the brand&rsquo;s official offer.</p>

            @include('partials.trust.byline', ['processLinkNewTab' => true])

            {{-- The directory's key facts are computed from the live catalogue (the
                 legacy view interpolates `discountCount`), not a stored column. --}}
            @include('partials.trust.key-facts', ['keyFacts' => [
                'title' => 'Military Discounts — Key Facts',
                'ariaLabel' => 'Military discounts key facts',
                'facts' => [
                    ['label' => 'Brands catalogued (this directory)', 'value' => (string) $brands->count()],
                    ['label' => 'Typical savings', 'value' => '10–25% off, varies by brand'],
                    ['label' => 'Verification', 'value' => 'Free — ID.me, GovX, or in-store ID'],
                    ['label' => 'Who qualifies', 'value' => 'Active duty, veterans, reserve/Guard, retirees, families, first responders'],
                ],
                'lastVerified' => $lastVerified,
            ]])

            <p class="dh-cc-note">Discounts cut the price — the right card earns on what&rsquo;s left. See our guide to the <a href="/best-credit-cards-for-military/" data-testid="link-best-credit-cards">best credit cards for military</a>, including which issuers waive annual fees under SCRA and MLA.</p>
        </section>

        @if (filled($categories))
            <section class="dh-section dh-band">
                <h2>BROWSE BY CATEGORY</h2>
                {{-- Real anchors to each category hub, so the filter is crawlable and
                     works with no JavaScript (the legacy pills are progressive
                     enhancement over the same links). --}}
                <div class="dh-pills" role="group" aria-label="Filter discounts by category">
                    <a class="dh-pill" href="{{ $hubRoot }}" aria-current="true">All<span>{{ $brands->count() }}</span></a>
                    @foreach ($categories as $category)
                        <a class="dh-pill" href="{{ PagePaths::child('discounts', $category['slug']) }}">{{ $category['name'] }}<span>{{ $category['count'] }}</span></a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="dh-section dh-grid-band" aria-label="All military discounts">
            <div class="dh-grid-head">
                <h2>ALL DISCOUNTS</h2>
                <span class="dh-count">{{ $brands->count() }} {{ $brands->count() === 1 ? 'brand' : 'brands' }}</span>
            </div>

            @if ($brands->isEmpty())
                <p class="dh-empty">Discount guides are coming soon.</p>
            @else
                <div class="dh-cards">
                    @foreach ($brands as $brand)
                        <a class="dh-card" href="{{ $brand['url'] }}" data-testid="link-discount-{{ $brand['slug'] }}">
                            <div class="dh-card-top">
                                <span class="dh-logo" style="background:{{ $brand['logo_background'] }}">
                                    @if ($brand['logo_url'])
                                        <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['brand'] }} logo" loading="lazy"
                                             style="max-height:{{ $brand['logo_max_height'] }}px;max-width:{{ $brand['logo_max_width'] }}px">
                                    @endif
                                </span>
                                <span class="dh-headline">{{ $brand['headline'] }}</span>
                            </div>
                            <div>
                                <div class="dh-brand">{{ $brand['brand'] }}</div>
                                <div class="dh-cat">{{ $brand['category'] }}</div>
                            </div>
                            <span class="dh-view">View discount
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dh-section dh-section-last">
            <h2>FREQUENTLY ASKED QUESTIONS</h2>
            @if ($page->faqs->isNotEmpty())
                <div class="dg-faq-list">
                    @foreach ($page->faqs as $faq)
                        <details class="nw-faq" @if ($loop->first) open @endif>
                            <summary>
                                <h3>{{ $faq->question }}</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nw-faq-chev" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </summary>
                            <div class="nw-faq-a">{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </div>
            @endif

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
