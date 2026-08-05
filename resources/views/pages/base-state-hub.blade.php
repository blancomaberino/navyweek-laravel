@extends('layouts.base')

{{-- /navy-bases/{state}/ — every installation in one US state, grouped by base
     type ("Naval Stations (1)"). Ported markup-for-markup from the legacy
     src/page-views/NavyBaseState.tsx; styles in resources/css/families/bases.css. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    $basesRoot = PagePaths::root('bases');
    $chevronLeft = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-chevron-left" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>';
    $arrowRight = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
@endphp

@section('content')
    <main class="base-hub base-region-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $basesRoot }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $regionName }}</span>
        </nav>

        <a class="base-hub-back" href="{{ $basesRoot }}">{!! $chevronLeft !!} All Navy Bases</a>

        <div class="base-eyebrow">// {{ $stateAbbr }} · Navy Installations</div>
        <h1>{{ $page->h1 ?? $page->title }}</h1>
        {{-- Legacy intro: derived from the installation count, not editorial copy
             (NavyBaseState.tsx L128-132). --}}
        <p class="base-hub-intro">
            @if ($baseCount === 1)
                One major U.S. Navy installation is catalogued in {{ $regionName }}.
            @else
                {{ $baseCount }} U.S. Navy installations are catalogued in {{ $regionName }}, grouped below by
                installation type.
            @endif
        </p>

        <div class="base-hub-groups">
            @foreach ($grouped as $typeLabel => $bases)
                <section aria-label="{{ $typeLabel }} in {{ $regionName }}">
                    <h2>{{ $typeLabel }} ({{ $bases->count() }})</h2>
                    <div class="base-type-grid">
                        @foreach ($bases as $base)
                            <a href="{{ PagePaths::child('bases', $base->slug) }}">
                                <div class="base-type-card-kind">{{ mb_strtoupper($base->type->label()) }}</div>
                                <div class="base-type-card-name">{{ $base->name }}</div>
                                <div class="base-type-card-meta">{{ $base->city }}, {{ $base->state_abbr }} ·
                                    Established {{ $base->established }}</div>
                                <div class="base-type-card-tagline">{{ $base->hero_tagline }}</div>
                                <div class="base-type-card-cta">View Details {!! $arrowRight !!}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </main>
@endsection
