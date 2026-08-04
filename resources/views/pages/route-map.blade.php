@extends('layouts.base')

{{-- /map/ — the Navy Week route map and the tour-stop list. --}}
@section('content')
    <main class="schedule-page">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Map</span>
        </nav>

        <header class="schedule-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.key-facts')

        <section class="route-map" aria-label="Tour stops">
            <h2>TOUR STOPS</h2>
            {{-- Coordinates are plotted on an equirectangular projection of the
                 continental U.S., so the map is plain SVG with no JS or tiles. --}}
            <svg class="route-map-svg" viewBox="0 0 100 60" role="img" aria-label="Map of {{ $events->count() }} Navy Week host cities">
                @foreach ($events as $event)
                    @continue($event->lat === null || $event->lng === null)
                    @php($x = (((float) $event->lng) + 125) / 60 * 100)
                    @php($y = (50 - ((float) $event->lat)) / 26 * 60)
                    <circle cx="{{ round($x, 2) }}" cy="{{ round($y, 2) }}" r="1.1">
                        <title>{{ $event->city }}</title>
                    </circle>
                @endforeach
            </svg>

            <ul class="schedule-list">
                @foreach ($events as $event)
                    <li class="schedule-row">
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $event->slug) }}">
                            <span class="schedule-dates">{{ $event->start_date?->format('M j') }} &ndash; {{ $event->end_date?->format('M j, Y') }}</span>
                            <span class="schedule-city">{{ $event->city }}@if ($event->state_abbr), {{ $event->state_abbr }}@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </main>
@endsection
