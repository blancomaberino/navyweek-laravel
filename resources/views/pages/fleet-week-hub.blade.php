@extends('layouts.base')

{{-- Fleet-week hub (/fleetweek/). The city directory + JSON-LD ItemList + FAQPage
     (hub FAQs seeded on the page). Head/JSON-LD byte-locked by FleetWeekPageSchema::buildHub.
     Body is a 1:1 port of the legacy src/page-views/FleetWeekHub.tsx (its inline
     styles now live as classes in resources/css/families/fleet-week.css). --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\FleetWeek> $weeks */
    use App\Domain\Pillars\Enums\FleetWeekSeason;
    use App\Domain\Publishing\Support\PagePaths;

    // The legacy hub renders the registry order (src/data/fleetweek/index.ts), which
    // the importer preserved as the insertion order — not the repository's A–Z sort.
    $ordered = $weeks->sortBy('id')->values();
    $seasonGroups = collect(FleetWeekSeason::cases())
        ->map(static fn (FleetWeekSeason $season): array => [
            'season' => $season,
            'cities' => $ordered->filter(
                static fn ($week): bool => $week->has_official_fleet_week && $week->season === $season,
            )->values(),
        ])
        ->filter(static fn (array $group): bool => $group['cities']->isNotEmpty());
    $tierThree = $ordered->reject(static fn ($week): bool => $week->has_official_fleet_week)->values();

    $lastVerified = ($page->last_reviewed ?? $page->date_modified)?->format('F j, Y');

    $arrowRight = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide nw-faq-chev" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
@endphp

@section('content')
    <main class="fw-hub-page">
        <section class="fw-hub-sec fw-hub-sec--hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Fleet Week</span>
            </nav>

            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>
                    NavyWeek.org is an independent guide. Each fleet week is organized by its own local
                    association, and we are <strong>not affiliated with, endorsed by, or sponsored by</strong>
                    those organizers or the U.S. Navy. Dates and schedules are set by the organizers and can
                    change at any time. Always confirm the current details on each event's official site
                    before you travel.
                </p>
            </section>

            <div class="fw-hub-eyebrow">// Ships, Air Shows &amp; the Waterfront</div>
            <h1>{{ $page->h1 ?: 'FLEET WEEK' }}</h1>
            <p class="fw-hub-intro">
                A city-by-city guide to U.S. Fleet Week. Each guide covers the dates, the air show schedule
                (including the Blue Angels where they fly), the Parade of Ships, free public ship tours, and
                the best free places to watch along the waterfront — then links you straight to the
                organizer's official site.
            </p>

            @include('partials.trust.byline')

            {{-- The legacy hub's key facts are constants in FleetWeekHub.tsx (only the
                 city count is derived). Ported verbatim; they belong in the page's
                 `key_facts` CMS column once it is seeded. --}}
            @include('partials.trust.key-facts', ['keyFacts' => [
                'title' => 'U.S. Fleet Week — Key Facts',
                'ariaLabel' => 'U.S. Fleet Week key facts',
                'lastVerified' => $lastVerified,
                'facts' => [
                    ['label' => 'Cities catalogued (this guide)', 'value' => (string) $ordered->count()],
                    ['label' => 'Typical cost', 'value' => 'Free from public areas; premium seats optional'],
                    ['label' => 'What to expect', 'value' => 'Air shows, Parade of Ships, free ship tours'],
                    ['label' => 'Best-known events', 'value' => 'San Francisco, New York, Los Angeles'],
                    ['label' => 'Organized by', 'value' => 'Local fleet week associations (not NavyWeek.org)'],
                ],
            ]])
        </section>

        @foreach ($seasonGroups as $group)
            <section class="fw-hub-sec" aria-labelledby="season-{{ $group['season']->value }}">
                <div class="fw-hub-head">
                    <h2 id="season-{{ $group['season']->value }}">{{ strtoupper($group['season']->label()) }}</h2>
                    <span class="fw-hub-count">{{ $group['cities']->count() }} {{ $group['cities']->count() === 1 ? 'city' : 'cities' }}</span>
                </div>
                <div class="fw-hub-grid">
                    @foreach ($group['cities'] as $week)
                        <a class="fw-hub-card" href="{{ PagePaths::child('fleet_weeks', $week->slug) }}">
                            <div class="fw-hub-card-top">
                                <span class="fw-hub-card-when">{{ $week->festival_dates_label ?: $week->month_label.' '.$week->year }}</span>
                                <span class="fw-hub-card-state">{{ $week->state_abbr }}</span>
                            </div>
                            <div>
                                <div class="fw-hub-card-city">{{ $week->city }}</div>
                                <div class="fw-hub-card-brand">{{ $week->branding_name }}</div>
                            </div>
                            <p class="fw-hub-card-summary">{{ $week->card_summary }}</p>
                            <span class="fw-hub-card-cta">View guide {!! $arrowRight !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($tierThree->isNotEmpty())
            <section class="fw-hub-sec" aria-labelledby="no-fleet-week">
                <div class="fw-hub-head fw-hub-head--tight">
                    <h2 id="no-fleet-week">CITIES WITHOUT A STANDING FLEET WEEK</h2>
                    <span class="fw-hub-count">{{ $tierThree->count() }} {{ $tierThree->count() === 1 ? 'city' : 'cities' }}</span>
                </div>
                <p class="fw-hub-lede">
                    Popular searches that don't have a traditional ship-tour fleet week. These guides give
                    the honest picture — the Navy history, any air show, and where to find the nearest real
                    fleet week.
                </p>
                <div class="fw-hub-grid">
                    @foreach ($tierThree as $week)
                        <a class="fw-hub-card" href="{{ PagePaths::child('fleet_weeks', $week->slug) }}">
                            <div class="fw-hub-card-top">
                                <span class="fw-hub-card-when">{{ $week->festival_dates_label ?: 'No standing fleet week' }}</span>
                                <span class="fw-hub-card-state">{{ $week->state_abbr }}</span>
                            </div>
                            <div>
                                <div class="fw-hub-card-city">{{ $week->city }}</div>
                                <div class="fw-hub-card-brand">{{ $week->branding_name }}</div>
                            </div>
                            <p class="fw-hub-card-summary">{{ $week->card_summary }}</p>
                            <span class="fw-hub-card-cta">View guide {!! $arrowRight !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="fw-hub-sec fw-hub-sec--faq">
            <h2>FREQUENTLY ASKED QUESTIONS</h2>
            <div class="fw-faqs">
                @foreach ($page->faqs as $faq)
                    <details class="nw-faq" @if ($loop->first) open @endif>
                        <summary>
                            <h3>{{ $faq->question }}</h3>
                            {!! $chevronDown !!}
                        </summary>
                        <div class="nw-faq-a">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
