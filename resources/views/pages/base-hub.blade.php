@extends('layouts.base')

{{-- /navy-bases/ — the directory root: browse by state, overseas, and the full
     A–Z list (legacy NavyBases hub). --}}
@section('content')
    <main class="base-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Bases</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="base-hub-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.byline')
        @include('partials.trust.key-facts')

        <section class="base-hub-regions" aria-label="Browse by state">
            <h2>BROWSE BY STATE</h2>
            <ul class="base-region-list">
                @foreach ($states as $slug => $stateBases)
                    <li>
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', (string) $slug) }}">
                            {{ $stateBases->first()->state_name }} <span class="count">({{ $stateBases->count() }})</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="base-hub-regions" aria-label="Overseas bases">
            <h2>OVERSEAS BASES</h2>
            <ul class="base-region-list">
                @foreach ($countries as $slug => $countryBases)
                    <li>
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', (string) $slug) }}">
                            {{ $countryBases->first()->country }} <span class="count">({{ $countryBases->count() }})</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p><a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', 'overseas') }}">All overseas bases &rarr;</a></p>
        </section>

        <section class="base-hub-all" aria-label="All bases A to Z">
            <h2>ALL BASES (A–Z)</h2>
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
