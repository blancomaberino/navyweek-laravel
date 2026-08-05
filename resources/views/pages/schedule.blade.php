@extends('layouts.base')

{{-- /schedule/ — the full Navy Week tour: key facts, official schedule links, the
     status/month filters, and a card per host city. Ported from the legacy
     Schedule view; the filters are progressive enhancement over an SSR list. --}}
@php
    $firstStop = $events->first();
    $lastStop = $events->last();
    $firstTimeCount = $events->where('first_time', true)->count();
    $fmtRange = static fn ($e): string => $e->start_date->format('M d').' – '.$e->end_date->format('M d, Y');
@endphp

@section('content')
    <main class="schedule-page">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Schedule</span>
        </nav>

        <header class="schedule-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">The U.S. Navy Week {{ $year }} tour visits {{ $events->count() }} cities across America from January through November, celebrating the Navy's 250th birthday with free, public-facing events in every host city.</p>
            @if ($page->last_reviewed)
                <p class="hero-meta">Last verified: <strong>{{ $page->last_reviewed->format('F j, Y') }}</strong></p>
            @endif
        </header>

        @include('partials.trust.key-facts')

        <section class="schedule-official" aria-label="Official schedules">
            <p><strong>Official schedules:</strong>
                <a href="https://outreach.navy.mil/Navy-Weeks/" rel="noopener noreferrer" target="_blank">NAVCO master Navy Week schedule</a>
                ·
                <a href="https://www.blueangels.navy.mil/show-schedule/" rel="noopener noreferrer" target="_blank">U.S. Navy Blue Angels show schedule</a>
            </p>
        </section>

        {{-- Filters: SSR-rendered controls, progressively enhanced. Every card is in
             the HTML regardless, so the list works with JS disabled. --}}
        <div class="schedule-filters" role="search">
            <div class="schedule-status-filter" role="group" aria-label="Filter by status">
                <button type="button" data-sched="status" value="all" aria-pressed="true">all</button>
                <button type="button" data-sched="status" value="upcoming" aria-pressed="false">upcoming</button>
                <button type="button" data-sched="status" value="past" aria-pressed="false">past</button>
            </div>
            <label class="schedule-month-filter">Month
                <select data-sched="month">
                    <option value="all">All Months</option>
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create(null, $month, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <ul class="schedule-cards">
            @foreach ($events as $event)
                <li class="schedule-card"
                    data-status="{{ $event->status->value === 'upcoming' ? 'upcoming' : 'past' }}"
                    data-month="{{ $event->start_date->month }}">
                    <span class="schedule-card-dates">{{ $fmtRange($event) }}</span>
                    <span class="schedule-card-city">{{ $event->city }}</span>
                    <span class="schedule-card-state">{{ $event->state_name ?? $event->state }}@if ($event->first_time) <span class="schedule-card-firsttime">· {{ $event->first_time_location ?: 'First Time Host' }}</span>@endif</span>
                    @if ($event->anchor_event)
                        <span class="schedule-card-anchor"><strong>Anchor event:</strong> {{ $event->anchor_event }}</span>
                    @endif
                    <a class="schedule-card-cta" href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $event->slug) }}">Learn More</a>
                    <span class="schedule-card-status">{{ mb_strtoupper($event->status->label()) }}</span>
                    @if ($event->first_time)
                        <span class="schedule-card-badge">{{ mb_strtoupper($event->first_time_badge ?: 'First-time host') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </main>

    @push('scripts')
        <script>
            // Filter the SSR-rendered cards in place. No cards are fetched or built
            // client-side, so the list is complete without JS.
            (function () {
                var cards = Array.prototype.slice.call(document.querySelectorAll('.schedule-card'));
                var status = 'all', month = 'all';
                function apply() {
                    cards.forEach(function (card) {
                        var okStatus = status === 'all' || card.dataset.status === status;
                        var okMonth = month === 'all' || card.dataset.month === month;
                        card.hidden = !(okStatus && okMonth);
                    });
                }
                document.querySelectorAll('[data-sched="status"]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        status = btn.value;
                        document.querySelectorAll('[data-sched="status"]').forEach(function (b) {
                            b.setAttribute('aria-pressed', String(b === btn));
                        });
                        apply();
                    });
                });
                var monthSelect = document.querySelector('[data-sched="month"]');
                if (monthSelect) {
                    monthSelect.addEventListener('change', function () { month = monthSelect.value; apply(); });
                }
            })();
        </script>
    @endpush
@endsection
