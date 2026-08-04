@extends('layouts.base')

{{-- /schedule/ — the full Navy Week schedule, every host city in tour order. --}}
@section('content')
    <main class="schedule-page">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Schedule</span>
        </nav>

        <header class="schedule-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.key-facts')

        <ul class="schedule-list">
            @foreach ($events as $event)
                <li class="schedule-row">
                    <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $event->slug) }}">
                        <span class="schedule-dates">{{ $event->start_date?->format('M j') }} &ndash; {{ $event->end_date?->format('M j, Y') }}</span>
                        <span class="schedule-city">{{ $event->city }}@if ($event->state_abbr), {{ $event->state_abbr }}@endif</span>
                        @if ($event->anchor_event)
                            <span class="schedule-anchor">{{ $event->anchor_event }}</span>
                        @endif
                        @if ($event->first_time_badge)
                            <span class="schedule-badge">{{ $event->first_time_badge }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </main>
@endsection
