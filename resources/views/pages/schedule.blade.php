@extends('layouts.base')

{{-- /schedule/ — ported from the legacy src/page-views/Schedule.tsx: hero + long
     intro, "Last verified", the 6-fact KeyFacts block, the official-schedule links,
     the filter bar (status / month / grid-table view), the EventCard grid and the
     table view. Both views are server-rendered; the filters only hide rows. --}}
@php
    use App\Domain\Pillars\Enums\NavyWeekStatus;
    use App\Domain\Publishing\Support\PagePaths;

    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\NavyWeekEvent> $events */
    // formatShortDateRange (src/data/data.ts): zero-padded days, month repeated only
    // when the range crosses a month boundary.
    $shortRange = static function ($e): string {
        $s = $e->start_date;
        $t = $e->end_date;

        return $s->format('M') === $t->format('M')
            ? $s->format('M d').' – '.$t->format('d, Y')
            : $s->format('M d').' – '.$t->format('M d, Y');
    };
    $statusLabel = static fn ($e): string => $e->status === NavyWeekStatus::Active
        ? 'ACTIVE NOW'
        : mb_strtoupper($e->status->label());
    $verified = ($page->last_reviewed ?? $page->date_modified)?->format('F j, Y');
    // lucide icons at the sizes the legacy component requests — not text glyphs.
    $arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $externalLink = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>';
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
                <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer">NAVCO master Navy Week schedule {!! $externalLink !!}</a>
                <a href="https://www.blueangels.navy.mil/schedule/" target="_blank" rel="noopener noreferrer">U.S. Navy Blue Angels show schedule {!! $externalLink !!}</a>
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
                <button type="button" data-sched="view" value="grid" class="is-on" title="Grid View" aria-label="Grid view"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg></button>
                <button type="button" data-sched="view" value="table" title="Table View" aria-label="Table view"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h.01"></path><path d="M3 12h.01"></path><path d="M3 19h.01"></path><path d="M8 5h13"></path><path d="M8 12h13"></path><path d="M8 19h13"></path></svg></button>
            </div>
        </div>

        <div class="schedule-grid">
            @foreach ($events as $event)
                @php($isPast = $event->status === NavyWeekStatus::Completed)
                <article class="event-card status-{{ $event->status->value }} @if ($isPast) is-past @endif"
                         data-status="{{ $isPast ? 'past' : 'upcoming' }}"
                         data-month="{{ $event->start_date->month }}">
                    <div class="event-card-dates">{{ $shortRange($event) }}</div>
                    <div class="event-card-city">{{ $event->city }}</div>
                    <div class="event-card-state">{{ $event->state }}@if ($event->first_time) · First Time Host @elseif ($event->first_time_badge) · {{ $event->first_time_badge }}@endif</div>
                    <div class="event-card-anchor">Anchor event: {{ $event->anchor_event }}</div>
                    <div class="event-card-foot">
                        <a href="{{ PagePaths::child('navy_week_cities', $event->slug) }}">Learn More {!! $arrow !!}</a>
                        <div class="status-badge">
                            <span class="status-badge-pill is-{{ $event->status->value }}">{{ $statusLabel($event) }}</span>
                            @if ($event->first_time || $event->first_time_badge)
                                <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- The legacy table view, rendered up front and toggled by the view buttons so
             the alternate presentation exists without JS-built markup. --}}
        <div class="schedule-table-wrap" hidden>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date Range</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Anchor Event</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        @php($isPast = $event->status === NavyWeekStatus::Completed)
                        <tr data-status="{{ $isPast ? 'past' : 'upcoming' }}" data-month="{{ $event->start_date->month }}">
                            <td class="schedule-table-id">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="schedule-table-dates">{{ $shortRange($event) }}</td>
                            <td class="schedule-table-city">{{ $event->city }}</td>
                            <td class="schedule-table-state">{{ $event->state }} @if ($event->first_time)<span class="schedule-table-star">*</span>@endif</td>
                            <td class="schedule-table-anchor">{{ $event->anchor_event }}</td>
                            <td>
                                <div class="status-badge">
                                    <span class="status-badge-pill is-{{ $event->status->value }}">{{ $statusLabel($event) }}</span>
                                    @if ($event->first_time || $event->first_time_badge)
                                        <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="schedule-table-note">* First Time Host</div>
        </div>

        <div class="schedule-empty" hidden>No events found matching the selected filters.</div>
    </main>

    @push('scripts')
        <script>
            // Filter/view toggles over the server-rendered cards and table rows. Nothing
            // is fetched or built client-side, so the full schedule is present without JS.
            (function () {
                var rows = [].slice.call(document.querySelectorAll('.event-card, .schedule-table tbody tr'));
                var grid = document.querySelector('.schedule-grid');
                var table = document.querySelector('.schedule-table-wrap');
                var empty = document.querySelector('.schedule-empty');
                var status = 'all', month = 'all', view = 'grid';
                function apply() {
                    var shown = 0;
                    rows.forEach(function (c) {
                        var okS = status === 'all' || c.dataset.status === status;
                        var okM = month === 'all' || c.dataset.month === month;
                        c.hidden = !(okS && okM);
                        if (okS && okM) { shown++; }
                    });
                    var none = shown === 0;
                    if (empty) { empty.hidden = !none; }
                    if (grid) { grid.hidden = none || view !== 'grid'; }
                    if (table) { table.hidden = none || view !== 'table'; }
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
                        view = b.value;
                        document.querySelectorAll('[data-sched="view"]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
                        apply();
                    });
                });
            })();
        </script>
    @endpush
@endsection
