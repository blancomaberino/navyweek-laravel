@extends('layouts.base')

{{-- /map/ — the Navy Week route map. A 1:1 port of src/page-views/MapPage.tsx:
     hero + intro, the KeyFacts block, then the two-column layout — the Albers-USA
     map panel (src/components/USMapSVG.tsx, geometry from src/data/usMapGeometry.ts
     via UsMapGeometry) with its status legend, and the scrollable tour-stop
     sidebar. Hover state (pin highlight + tooltip) is progressive enhancement over
     the server-rendered markup. The legacy map page has no breadcrumb. --}}
@php
    use App\Domain\Pillars\Enums\NavyWeekStatus;
    use App\Domain\Publishing\Support\PagePaths;
    use App\Domain\Publishing\Support\UsMapGeometry;

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
    $pins = UsMapGeometry::pinPositions();
    $inset = UsMapGeometry::insetLabels();
    $vbW = UsMapGeometry::VIEWBOX_WIDTH;
    $vbH = UsMapGeometry::VIEWBOX_HEIGHT;
    $stateCount = $events->pluck('state')->unique()->count();
    $firstTimeCount = $events->filter(static fn ($e): bool => $e->isFirstTimeLocation())->count();
    // Pin fill per status, ported from USMapSVG.tsx.
    $pinFill = ['active' => '#2E9E5E', 'completed' => '#8C8C7A', 'upcoming' => '#C9A84C'];
@endphp

@section('content')
    <main class="map-page">
        <div class="map-head">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="map-intro">The Navy Week 2026 tour spans the entire United States, visiting {{ $events->count() }} cities across {{ $stateCount }} states from January through November. Click any city on the map or list to view event details. This year's "Road Trip to 250" tour includes {{ $firstTimeCount }} first-time Navy Week locations. All Navy Week events are free and open to the public.</p>

            @include('partials.trust.key-facts')
        </div>

        <div class="map-layout">
            <div class="map-panel">
                <div class="map-canvas">
                    <svg class="map-svg" viewBox="0 0 {{ $vbW }} {{ $vbH }}" preserveAspectRatio="xMidYMid meet"
                         role="img" aria-label="Interactive map of the United States showing all {{ $events->count() }} Navy Week 2026 tour stops with their locations and status">
                        <defs>
                            <filter id="glow">
                                <feGaussianBlur stdDeviation="3" result="coloredBlur" />
                                <feMerge>
                                    <feMergeNode in="coloredBlur" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                        </defs>

                        <g class="map-svg-grid">
                            @for ($i = 0; $i < (int) ceil($vbW / 40) + 1; $i++)
                                <line x1="{{ $i * 40 }}" y1="0" x2="{{ $i * 40 }}" y2="{{ $vbH }}" stroke="#FAFAF8" stroke-width="0.5" />
                            @endfor
                            @for ($i = 0; $i < (int) ceil($vbH / 40) + 1; $i++)
                                <line x1="0" y1="{{ $i * 40 }}" x2="{{ $vbW }}" y2="{{ $i * 40 }}" stroke="#FAFAF8" stroke-width="0.5" />
                            @endfor
                        </g>

                        {{-- Accurate national landmass (Albers USA: continental US + AK/HI insets) --}}
                        <path d="{{ UsMapGeometry::NATION_PATH }}" fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.35)"
                              stroke-width="1.25" stroke-linejoin="round" stroke-linecap="round" />
                        {{-- Interior state borders --}}
                        <path d="{{ UsMapGeometry::STATE_BORDERS_PATH }}" fill="none" stroke="rgba(201,168,76,0.16)"
                              stroke-width="0.6" stroke-linejoin="round" stroke-linecap="round" />

                        <text class="map-svg-inset" x="{{ $inset['alaska']['x'] }}" y="{{ $inset['alaska']['y'] + 4 }}" text-anchor="middle">ALASKA</text>
                        <text class="map-svg-inset" x="{{ $inset['hawaii']['x'] }}" y="{{ $inset['hawaii']['y'] + 22 }}" text-anchor="middle">HAWAII</text>

                        @foreach ($events as $event)
                            @continue(! isset($pins[$event->slug]))
                            @php
                                $pos = $pins[$event->slug];
                                $offset = UsMapGeometry::labelOffset($event->city);
                                $status = $event->status->value;
                                $isActive = $status === 'active';
                                $radius = $isActive ? 7 : 5;
                            @endphp
                            <g class="map-pin is-{{ $status }}" data-slug="{{ $event->slug }}"
                               role="button" tabindex="0" aria-label="Navy Week {{ $event->city }} — click to view details">
                                @if ($isActive)
                                    <circle cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="14" fill="none" stroke="{{ $pinFill[$status] }}" stroke-width="1.5" opacity="0.5">
                                        <animate attributeName="r" values="8;20;8" dur="2s" repeatCount="indefinite" />
                                        <animate attributeName="opacity" values="0.6;0;0.6" dur="2s" repeatCount="indefinite" />
                                    </circle>
                                    <circle cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="10" fill="none" stroke="{{ $pinFill[$status] }}" stroke-width="0.8" opacity="0.3">
                                        <animate attributeName="r" values="6;15;6" dur="2s" repeatCount="indefinite" begin="0.4s" />
                                        <animate attributeName="opacity" values="0.4;0;0.4" dur="2s" repeatCount="indefinite" begin="0.4s" />
                                    </circle>
                                @endif
                                <circle class="map-pin-hit" cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="{{ $radius }}" fill="transparent" />
                                <circle class="map-pin-dot" cx="{{ $pos['x'] }}" cy="{{ $pos['y'] }}" r="{{ $radius }}" fill="{{ $pinFill[$status] }}"
                                        stroke="rgba(10,22,40,0.8)" stroke-width="1.5" />
                                <text class="map-pin-label" x="{{ $pos['x'] + $offset['dx'] }}" y="{{ $pos['y'] + $offset['dy'] }}" text-anchor="{{ $offset['anchor'] }}">{{ $event->city }}</text>
                                @if ($isActive)
                                    <text class="map-pin-active" x="{{ $pos['x'] + $offset['dx'] }}" y="{{ $pos['y'] + $offset['dy'] + 13 }}" text-anchor="{{ $offset['anchor'] }}">ACTIVE NOW</text>
                                @endif
                            </g>
                        @endforeach
                    </svg>

                    @foreach ($events as $event)
                        <div class="map-tooltip is-{{ $event->status->value }}" data-tooltip="{{ $event->slug }}" hidden>
                            <div class="map-tooltip-dates">{{ $shortRange($event) }}</div>
                            <div class="map-tooltip-city">{{ $event->city }}</div>
                            <div class="map-tooltip-meta">{{ $event->state }} &middot; {{ $event->anchor_event }}</div>
                            <div class="status-badge">
                                <span class="status-badge-pill is-{{ $event->status->value }}">{{ $statusLabel($event) }}</span>
                                @if ($event->first_time || $event->first_time_badge)
                                    <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="map-legend">
                    <div><span class="map-legend-dot is-active"></span><span>Active Now</span></div>
                    <div><span class="map-legend-dot is-upcoming"></span><span>Upcoming</span></div>
                    <div><span class="map-legend-dot is-completed"></span><span>Completed</span></div>
                </div>
            </div>

            <div class="map-sidebar">
                <h2>TOUR STOPS</h2>
                <div class="map-stops">
                    @foreach ($events as $event)
                        <a class="map-stop is-{{ $event->status->value }}" data-stop="{{ $event->slug }}"
                           href="{{ PagePaths::child('navy_week_cities', $event->slug) }}">
                            <span class="map-stop-row">
                                <span class="map-stop-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="map-stop-city">{{ $event->city }}</span>
                                <svg class="map-stop-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                            </span>
                            <span class="map-stop-meta">
                                <span class="map-stop-dates">{{ $shortRange($event) }}</span>
                                <span class="status-badge">
                                    <span class="status-badge-pill is-{{ $event->status->value }}">{{ $statusLabel($event) }}</span>
                                    @if ($event->first_time || $event->first_time_badge)
                                        <span class="status-badge-pill is-firsttime">{{ $event->first_time ? 'FIRST-TIME HOST' : mb_strtoupper($event->first_time_badge) }}</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </main>

    @push('scripts')
        <script>
            // Hover linkage between the map pins and the tour-stop list, ported from the
            // legacy MapPage `hoveredEventId` state: highlight the pin, tint the row and
            // float the detail tooltip. Everything is server-rendered, so the page is
            // complete without JS.
            (function () {
                var canvas = document.querySelector('.map-canvas');
                if (!canvas) { return; }
                var current;
                function set(slug) {
                    if (current === slug) { return; }
                    current = slug;
                    document.querySelectorAll('.map-pin').forEach(function (g) {
                        g.classList.toggle('is-hovered', g.dataset.slug === slug);
                    });
                    document.querySelectorAll('.map-stop').forEach(function (a) {
                        a.classList.toggle('is-hovered', a.dataset.stop === slug);
                    });
                    document.querySelectorAll('.map-tooltip').forEach(function (t) {
                        t.hidden = t.dataset.tooltip !== slug;
                    });
                }
                document.querySelectorAll('.map-pin').forEach(function (g) {
                    var go = function () {
                        var row = document.querySelector('.map-stop[data-stop="' + g.dataset.slug + '"]');
                        if (row) { window.location.href = row.getAttribute('href'); }
                    };
                    g.addEventListener('mouseenter', function () { set(g.dataset.slug); });
                    g.addEventListener('mouseleave', function () { set(undefined); });
                    g.addEventListener('click', go);
                    g.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
                    });
                });
                document.querySelectorAll('.map-stop').forEach(function (a) {
                    a.addEventListener('mouseenter', function () { set(a.dataset.stop); });
                    a.addEventListener('mouseleave', function () { set(undefined); });
                });
            })();
        </script>
    @endpush
@endsection
