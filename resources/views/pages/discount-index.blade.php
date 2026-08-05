@extends('layouts.base')

{{-- /discount/ directory landing. Head/JSON-LD (Breadcrumb + Article + ItemList + FAQPage)
     byte-locked by SeoHead + DiscountIndexSchema. Body is a clean semantic rebuild. --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Publishing\Models\Page> $brandPages */
    $brands = $brandPages
        ->map(static function ($p) {
            $offer = $p->pageable;
            return $offer instanceof \App\Domain\Catalog\Models\Offer
                ? ['brand' => $offer->connection->brand, 'url' => $p->url_path, 'audience' => $offer->audience_label]
                : null;
        })
        ->filter()
        ->values();
@endphp

@section('content')
    <main class="discount-index">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Military Discounts</span>
        </nav>

        <p class="independence-disclosure" role="note">
            NavyWeek.org is an independent editorial publisher and is
            <strong>not affiliated</strong> with the brands listed below or the U.S. Navy.
            We may earn a commission from links on this page.
        </p>

        <header class="directory-hero">
            <p class="eyebrow">// Military &amp; Veteran Savings</p>
            <h1>{{ $page->h1 ?? 'MILITARY DISCOUNTS' }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.key-facts')

        <section class="brand-grid-section" aria-label="Browse by category">
            <h2>BROWSE BY CATEGORY</h2>
            <ul class="brand-grid">
                @foreach ($categories as $category)
                    <li class="brand-card">
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('discounts', $category->slug) }}">
                            <span class="brand-name">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="brand-grid-section" aria-label="All military discounts">
            <h2>ALL DISCOUNTS</h2>
            @if ($brands->isEmpty())
                <p class="empty-state">Discount guides are coming soon.</p>
            @else
                <ul class="brand-grid">
                    @foreach ($brands as $brand)
                        <li class="brand-card">
                            <a href="{{ $brand['url'] }}">
                                <span class="brand-name">{{ $brand['brand'] }}</span>
                                @if ($brand['audience'])
                                    <span class="brand-audience">{{ $brand['audience'] }}</span>
                                @endif
                                <span class="view-link" aria-hidden="true">View discount →</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($page->faqs->isNotEmpty())
            <section class="directory-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($page->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif
        @include('partials.trust.editorial-policy')
    </main>
@endsection
