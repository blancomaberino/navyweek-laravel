@extends('layouts.base')

{{-- /navy-ranks/ — every officer + enlisted rank on one page, three paygrade-ordered
     sections rendered high→low (each row anchored by slug for /navy-ranks/#<slug>).
     Head/JSON-LD is byte-locked by SeoHead + RankListSchema; this body is a clean
     semantic rebuild. --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $commissioned */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $warrant */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $enlisted */
    // Section headings match the legacy NavyRanksHub verbatim (uppercase, high→low).
    $sections = [
        'COMMISSIONED OFFICERS (HIGH → LOW)' => $commissioned,
        'WARRANT OFFICERS (HIGH → LOW)' => $warrant,
        'ENLISTED PAYGRADES (HIGH → LOW)' => $enlisted,
    ];
@endphp

@section('content')
    <main class="rank-list">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Ranks</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="rank-hero">
            <p class="eyebrow">// U.S. Navy Hierarchy</p>
            <h1>{{ $page->h1 ?? 'NAVY RANKS' }}</h1>
            <p class="intro">The complete United States Navy rank structure on a single page — commissioned officers, warrant officers, and enlisted paygrades, each listed high to low with paygrade, pixel-art insignia, abbreviation, and NATO code.</p>
        </header>

        @include('partials.trust.byline')
        @include('partials.trust.key-facts')

        @foreach ($sections as $heading => $ranks)
            @continue($ranks->isEmpty())
            <section class="rank-section" aria-label="{{ $heading }}">
                <h2>{{ $heading }}</h2>
                <ul class="rank-rows">
                    {{-- High → low: the repository returns ascending paygrade order. --}}
                    @foreach ($ranks->reverse() as $rank)
                        <li id="{{ $rank->slug }}" class="rank-row">
                            <span class="paygrade">{{ $rank->paygrade }}</span>
                            @if ($rank->insignia_path)
                                <img class="insignia" src="{{ $rank->insignia_path }}" alt="{{ $rank->insignia_alt }}" width="40" height="40" loading="lazy">
                            @endif
                            <span class="rank-name">{{ $rank->name }} <span class="abbr">({{ $rank->abbreviation }})</span></span>
                            @if ($rank->nato_code)
                                <span class="nato">NATO {{ $rank->nato_code }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- Cross-link cards to the sibling reference hubs (legacy NavyRanksHub). --}}
        <div class="reference-cards">
            <a class="reference-card" href="{{ \App\Domain\Publishing\Support\PagePaths::root('ratings') }}">
                <div class="reference-card-eyebrow">Enlisted Ratings</div>
                <div class="reference-card-title">Navy Ratings</div>
                <div class="reference-card-body">The {{ $ratingsActiveTotal ?? 90 }} active enlisted job specialties — Hospital Corpsman, Boatswain's Mate, Master-at-Arms, and every other rating.</div>
                <div class="reference-card-cta">Browse Navy Ratings &rarr;</div>
            </a>
            <a class="reference-card" href="{{ \App\Domain\Publishing\Support\PagePaths::root('designators') }}">
                <div class="reference-card-eyebrow">Officer Designators</div>
                <div class="reference-card-title">4-Digit Codes</div>
                <div class="reference-card-body">URL, Restricted Line, and Staff Corps officer communities — every Navy officer carries a four-digit designator.</div>
                <div class="reference-card-cta">Browse Officer Designators &rarr;</div>
            </a>
        </div>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
