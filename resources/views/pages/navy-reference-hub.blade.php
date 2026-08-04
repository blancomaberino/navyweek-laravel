@extends('layouts.base')

{{-- /navy-reference/ — the reference library landing page every "← Navy Reference"
     back link points at. Card counts are live from the pillars. --}}
@section('content')
    <main class="reference-hub">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Reference</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="reference-hub-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.byline')

        <ul class="reference-hub-cards">
            @foreach ($cards as $card)
                <li>
                    <a href="{{ $card['href'] }}">
                        <span class="reference-hub-badge">{{ $card['badge'] }}</span>
                        <span class="reference-hub-title">{{ $card['title'] }}</span>
                        <span class="reference-hub-desc">{{ $card['description'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <section class="reference-hub-fleet" aria-label="See the fleet in person">
            <h2>SEE THE FLEET IN PERSON</h2>
            <p>This reference library sits alongside our coverage of the Navy Week touring outreach program — browse the full schedule to see when the fleet visits a city near you.</p>
            <ul class="reference-hub-cards">
                <li>
                    <a href="/schedule/">
                        <span class="reference-hub-badge">Full Schedule</span>
                        <span class="reference-hub-title">2026 Navy Week Schedule</span>
                    </a>
                </li>
                @foreach ($upcoming as $event)
                    <li>
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $event->slug) }}">
                            <span class="reference-hub-badge">{{ $event->start_date?->format('M j') }} &ndash; {{ $event->end_date?->format('M j, Y') }}</span>
                            <span class="reference-hub-title">{{ $event->city }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
