@extends('layouts.base')

{{-- /navy-bases/overseas/ — world map, browse by country, and the full A–Z list
     of overseas installations (legacy NavyBasesOverseas). --}}
@section('content')
    <main class="base-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('bases') }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Overseas</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="base-hub-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.byline')
        @include('partials.trust.key-facts')

        <section class="base-hub-map" aria-label="World map">
            <h2>WORLD MAP</h2>
            <ul class="base-region-list">
                @foreach ($byRegion as $regionLabel => $regionBases)
                    <li><strong>{{ $regionLabel }}</strong> <span class="count">({{ $regionBases->count() }})</span></li>
                @endforeach
            </ul>
        </section>

        <section class="base-hub-regions" aria-label="Browse by country">
            <h2>BROWSE BY COUNTRY</h2>
            <ul class="base-region-list">
                @foreach ($countries as $slug => $countryBases)
                    <li>
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', (string) $slug) }}">
                            {{ $countryBases->first()->country }} <span class="count">({{ $countryBases->count() }})</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="base-hub-all" aria-label="All overseas bases A to Z">
            <h2>ALL OVERSEAS BASES (A–Z)</h2>
            @include('partials.base-card-list', ['bases' => $allBases])
        </section>

        @if ($page->faqs->isNotEmpty())
            <section class="base-hub-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                @foreach ($page->faqs as $faq)
                    <details>
                        <summary><h3>{{ $faq->question }}</h3></summary>
                        <div>{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </section>
        @endif

        @include('partials.trust.editorial-policy')
    </main>
@endsection
