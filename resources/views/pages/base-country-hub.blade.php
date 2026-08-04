@extends('layouts.base')

{{-- /navy-bases/{country}/ — every installation in one host country, grouped by
     base type, plus host-nation context and FAQs (legacy NavyBasesCountry). --}}
@section('content')
    <main class="base-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('bases') }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', 'overseas') }}">Overseas</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $regionName }}</span>
        </nav>

        <header class="base-hub-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.key-facts')

        @foreach ($grouped as $typeLabel => $bases)
            <section class="base-hub-group" aria-label="{{ $typeLabel }}">
                <h2>{{ $typeLabel }} ({{ $bases->count() }})</h2>
                @include('partials.base-card-list', ['bases' => $bases])
            </section>
        @endforeach

        @if ($hostNationContext)
            <section class="base-hub-host-nation" aria-label="Host nation context">
                <h2>HOST NATION CONTEXT</h2>
                <p>{{ $hostNationContext }}</p>
            </section>
        @endif

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

    </main>
@endsection
