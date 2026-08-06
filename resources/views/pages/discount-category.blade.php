@extends('layouts.base')

{{-- Discount category hub — ported 1:1 from the legacy
     src/page-views/DiscountCategory.tsx (its styling was inline; the CSS
     equivalent lives in resources/css/families/discount.css). The ordered grid of
     live brands in one category comes from the repository's orderedConnections. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    $hubRoot = PagePaths::root('discounts');
@endphp

@section('content')
    <main class="discount-category">
        <section class="dh-section dh-hero">
            <nav class="dg-crumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $hubRoot }}">Military Discounts</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $category->name }}</span>
            </nav>

            {{-- The hub's own independence wording (TrustDisclosure's `children`
                 override in DiscountCategory.tsx), not the generic reference copy. --}}
            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>NavyWeek.org is an independent guide. We are <strong>not affiliated with, endorsed by, or sponsored by</strong> any brand listed here. Each company sets and controls its own discount terms, which can change at any time. Company names and logos are trademarks of their respective owners, shown for identification only. Always confirm the current offer on the brand&rsquo;s official page before purchasing.</p>
            </section>

            <div class="dh-eyebrow">// Military &amp; Veteran Savings</div>
            <h1>{{ $category->h1 }}</h1>
            @foreach ($category->intro as $paragraph)
                <p class="{{ $loop->first ? 'dh-intro' : 'dh-intro-secondary' }}">{{ $paragraph }}</p>
            @endforeach

            @include('partials.trust.byline', ['processLinkNewTab' => true])
        </section>

        <section class="dh-section dh-grid-band" aria-label="{{ $category->name }}">
            <div class="dh-grid-head">
                <h2>{{ $category->name }}</h2>
                <span class="dh-count">{{ $brands->count() }} {{ $brands->count() === 1 ? 'brand' : 'brands' }}</span>
            </div>

            @if ($brands->isEmpty())
                <p class="dh-empty">No brands are catalogued in this category yet. Browse the <a href="{{ $hubRoot }}">full discounts directory</a>.</p>
            @else
                <div class="dc-cards">
                    @foreach ($brands as $brand)
                        <a class="dc-card" href="{{ $brand['url'] }}" data-testid="link-category-brand-{{ $brand['slug'] }}">
                            <span class="dc-logo" style="background:{{ $brand['logo_background'] }}">
                                @if ($brand['logo_url'])
                                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['brand'] }} logo" loading="lazy"
                                         style="max-height:{{ $brand['logo_max_height'] }}px;max-width:{{ $brand['logo_max_width'] }}px">
                                @endif
                            </span>
                            <span class="dc-card-body">
                                <span class="dc-brand">{{ $brand['brand'] }}</span>
                                <span class="dc-view">View discount
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dh-section dh-section-last">
            <a class="dc-all-link" href="{{ $hubRoot }}" data-testid="link-category-all-discounts">&larr; All military discounts</a>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
