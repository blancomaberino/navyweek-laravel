@extends('layouts.base')

{{-- /schedule/ — ported from the legacy src/page-views/Schedule.tsx: hero + long
     intro, "Last verified", the 6-fact KeyFacts block, the official-schedule links,
     the filter bar (status / month / grid-table view) and the EventCard grid.
     Every card is server-rendered; the filters only hide rows. --}}
@php
    $shortRange = static fn ($e): string => $e->start_date->format('M d').' – '.$e->end_date->format('M d, Y');
    $verified = ($page->last_reviewed ?? $page->date_modified)?->format('F j, Y');
@endphp

@section('content')
    <main class="schedule-page">
        <div class="schedule-head">
            <h1>2026 SCHEDULE</h1>
            <p class="schedule-intro">The U.S. Navy Week 2026 tour visits {{ $events->count() }} cities across America from January through November, celebrating the nation's 250th birthday with the "Road Trip to 250" theme. Each week-long stop features 75 to 100 free events including Blue Angels flight demonstrations, U.S. Navy Band performances, STEM exhibits, community outreach, and ship tours at coastal stops. Browse the complete schedule below to find Navy Week dates and anchor events for your city. Eight of the stops mark a first-time Navy Week location in 2026; the seven full first-time host cities are marked with a "First Time Host" badge.</p>

            @if ($verified)
                <div class="schedule-verified">Last verified: {{ $verified }}</div>
            @endif

            @include('partials.trust.key-facts')

            <div class="schedule-official">
                <span class="schedule-official-label">Official schedules:</span>
                <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">NAVCO master Navy Week schedule <span aria-hidden="true">↗</span></a>
                <a href="https://www.blueangels.navy.mil/schedule/" target="_blank" rel="noopener noreferrer">U.S. Navy Blue Angels show schedule <span aria-hidden="true">↗</span></a>
            </div>
        </div>

        <div class="schedule-filterbar">
            <div class="schedule-filter-group">
                <div class="schedule-status-buttons">
                    @foreach (['all', 'upcoming', 'past'] as $status)
                        <button type="button" data-sched="status" value="{{ $status }}"
                                class="@if ($status === 'all') is-on @endif">{{ $status }}</button>
                    @endforeach
                </div>
                <select data-sched="month" aria-label="Filter by month">
                    <option value="all">All Months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(2026, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="schedule-view-buttons">
                <button type="button" data-sched="view" value="grid" class="is-on" title="Grid View" aria-label="Grid view">&#9638;</button>
                <button type="button" data-sched="view" value="table" title="Table View" aria-label="Table view">&#9776;</button>
            </div>
        </div>

        <div class="schedule-grid" data-view="grid">
            @foreach ($events as $event)
                @php($isPast = $event->status->value === 'completed')
                <article class="event-card status-{{ $event->status->value }} @if ($isPast) is-past @endif"
                         data-status="{{ $isPast ? 'past' : 'upcoming' }}"
                         data-month="{{ $event->start_date->month }}">
                    <div class="event-card-dates">{{ $shortRange($event) }}</div>
                    <div class="event-card-city">{{ $event->city }}</div>
                    <div class="event-card-state">{{ $event->state_name ?? $event->state }}@if ($event->first_time) · First Time Host @elseif ($event->first_time_badge) · {{ $event->first_time_badge }}@endif</div>
                    <div class="event-card-anchor">Anchor event: {{ $event->anchor_event }}</div>
                    <div class="event-card-foot">
                        <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $event->slug) }}">Learn More <span aria-hidden="true">&rarr;</span></a>
                        <div class="status-badge">
                            <span class="status-badge-pill is-{{ $event->status->value }}">{{ mb_strtoupper($event->status->label()) }}</span>
                            @if ($event->first_time || $event->first_time_badge)
                                <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    @push('scripts')
        <script>
            // Filter/view toggles over the server-rendered cards. Nothing is fetched
            // or built client-side, so the full schedule is present without JS.
            (function () {
                var cards = [].slice.call(document.querySelectorAll('.event-card'));
                var grid = document.querySelector('.schedule-grid');
                var status = 'all', month = 'all';
                function apply() {
                    cards.forEach(function (c) {
                        var okS = status === 'all' || c.dataset.status === status;
                        var okM = month === 'all' || c.dataset.month === month;
                        c.hidden = !(okS && okM);
                    });
                }
                document.querySelectorAll('[data-sched="status"]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        status = b.value;
                        document.querySelectorAll('[data-sched="status"]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
                        apply();
                    });
                });
                var sel = document.querySelector('[data-sched="month"]');
                if (sel) { sel.addEventListener('change', function () { month = sel.value; apply(); }); }
                document.querySelectorAll('[data-sched="view"]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        document.querySelectorAll('[data-sched="view"]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
                        if (grid) { grid.dataset.view = b.value; }
                    });
                });
            })();
        </script>
    @endpush
@endsection
