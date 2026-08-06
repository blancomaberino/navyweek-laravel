@extends('layouts.base')

{{-- Navy Week city page (/city/{slug}/). Head/JSON-LD (Breadcrumb + 2 GovernmentOrg +
     Event(+subEvents) + FAQPage) is byte-locked by SeoHead + NavyWeekCitySchema; this
     body is a 1:1 port of the legacy src/page-views/CityDetail.tsx (plus
     src/components/CityMap.tsx, StatusBadge.tsx and SourceLabel.tsx). Every style
     below is copied from those components' inline styles into
     resources/css/families/city.css — none of it is eyeballed from a screenshot. --}}
@php
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent $event */
    use App\Domain\Navigation\Support\LinkUrl;
    use App\Domain\Publishing\Support\PagePaths;
    use Illuminate\Support\Carbon;

    // Cohesive display lists are JSON columns; a record may legitimately have none.
    // `military_context` in particular is a LIST of paragraphs, never a string.
    $listOf = static fn ($value): array => is_array($value) ? array_values($value) : [];

    $description = $listOf($event->description);
    $highlights = $listOf($event->highlights);
    $navyAssets = $listOf($event->navy_assets);
    $militaryContext = array_values(array_filter(
        $listOf($event->military_context),
        static fn ($p): bool => is_string($p) && trim($p) !== '',
    ));
    $venues = array_values(array_filter($listOf($event->venues), 'is_array'));
    $officialSources = $event->sources;
    $anchorEventUrl = $event->anchor_event_url ?: $event->navco_url;
    $navcoUrl = $event->navco_url ?: 'https://outreach.navy.mil/Navy-Weeks/';

    // data.ts `formatShortDateRange` — "Mar 09 – 15, 2026" / "Jan 26 – Feb 01, 2026".
    $shortRange = static function (Carbon $start, Carbon $end): string {
        return $start->month === $end->month
            ? $start->format('M d').' – '.$end->format('d').', '.$start->format('Y')
            : $start->format('M d').' – '.$end->format('M d').', '.$start->format('Y');
    };

    // CityDetail.tsx `formatLongDate` — "Monday, March 9, 2026".
    $longDate = static fn (Carbon $day): string => $day->format('l, F j, Y');

    // data.ts SOURCE_LEVELS + SourceLabel.tsx.
    $sourceLevels = [
        'navco' => ['NAVCO-confirmed', 'Confirmed by the Navy Office of Community Outreach (NAVCO) official city page.'],
        'anchor' => ['Anchor-event-confirmed', 'Confirmed by the anchor event organizer (air show, festival, or host venue).'],
        'local' => ['Local context — unverified', 'Local context or expected programming compiled by NavyWeek.org — not yet confirmed by NAVCO.'],
    ];
    $sourceLabel = static function (string $level) use ($sourceLevels): string {
        // `$level` is interpolated into a class + data attribute inside a raw-HTML
        // sink, and it arrives from editor JSON (`venues[].source_level`,
        // `daily_schedule[]…source_level`). Pin it to a KNOWN KEY first — the
        // `?? $sourceLevels['local']` below only guards the lookup, not the two
        // interpolations. JSX escaped className for the legacy; Blade does not here.
        $level = isset($sourceLevels[$level]) ? $level : 'local';
        [$label, $description] = $sourceLevels[$level];

        return '<span class="nwc-src is-'.$level.'" title="'.e($description).'" data-testid="source-label-'.$level.'">'.e($label).'</span>';
    };

    // data.ts `deriveScheduleSource` / `deriveVenueSource`.
    $scheduleSource = static function (array $item): string {
        if (is_string($item['source_level'] ?? null)) {
            return $item['source_level'];
        }
        $source = is_string($item['source'] ?? null) ? $item['source'] : null;

        return $source === null
            ? 'local'
            : (preg_match('/outreach\.navy\.mil/i', $source) === 1 ? 'navco' : 'anchor');
    };

    // cityExtras.ts `getCityDailySchedule` — the stored day list, else one TBA day
    // per calendar day of the stop (`tbaDays`).
    $dailySchedule = array_values(array_filter($listOf($event->daily_schedule), 'is_array'));
    if ($dailySchedule === []) {
        foreach ($event->start_date->daysUntil($event->end_date) as $day) {
            $dailySchedule[] = [
                'date' => $day->toDateString(),
                'tba' => true,
                'items' => [[
                    'title' => 'Daily schedule TBA',
                    'description' => 'NAVCO has not published a detailed daily breakdown for this day. Check the official NAVCO city page for updates.',
                ]],
            ];
        }
    }

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size = 18, string $stroke = 'currentColor', string $class = ''): string {
        $paths = [
            'anchor' => '<path d="M12 6v16"></path><path d="m19 13 2-1a9 9 0 0 1-18 0l2 1"></path><path d="M9 11h6"></path><circle cx="12" cy="4" r="2"></circle>',
            'arrow-left' => '<path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>',
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
            'calendar' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path>',
            'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"></path><circle cx="7" cy="17" r="2"></circle><path d="M9 17h6"></path><circle cx="17" cy="17" r="2"></circle>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'dollar-sign' => '<line x1="12" x2="12" y1="2" y2="22"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
            'external-link' => '<path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
            'link-2' => '<path d="M9 17H7A5 5 0 0 1 7 7h2"></path><path d="M15 7h2a5 5 0 1 1 0 10h-2"></path><line x1="8" x2="16" y1="12" y2="12"></line>',
            'map' => '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path>',
            'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="'.$stroke.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide lucide-'.$name.($class === '' ? '' : ' '.$class).'" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };

    // CityMap.tsx — a prerender-safe schematic: project the city centre and every
    // pinned venue onto a fixed 800x480 viewBox with a 25% (min 0.02°) pad.
    $mapW = 800;
    $mapH = 480;
    $pinned = array_values(array_filter(
        $venues,
        static fn (array $v): bool => is_numeric($v['lat'] ?? null) && is_numeric($v['lng'] ?? null),
    ));
    $lats = array_map(static fn (array $v): float => (float) $v['lat'], $pinned);
    $lngs = array_map(static fn (array $v): float => (float) $v['lng'], $pinned);
    $lats[] = (float) $event->lat;
    $lngs[] = (float) $event->lng;
    $minLat = min($lats);
    $maxLat = max($lats);
    $minLng = min($lngs);
    $maxLng = max($lngs);
    $padLat = max(($maxLat - $minLat) * 0.25, 0.02);
    $padLng = max(($maxLng - $minLng) * 0.25, 0.02);
    $minLat -= $padLat;
    $maxLat += $padLat;
    $minLng -= $padLng;
    $maxLng += $padLng;
    $project = static fn (float $lat, float $lng): array => [
        (($lng - $minLng) / ($maxLng - $minLng)) * $mapW,
        (($maxLat - $lat) / ($maxLat - $minLat)) * $mapH,
    ];
@endphp

@section('content')
    <main class="nwc-page">
        {{-- Hero — the city photo at 25% opacity under a radial tint, with the
             back-link, h1, stat row and the status/source strip on top. --}}
        @php
            $heroSlug = file_exists(public_path("images/hero-{$event->slug}-1408.avif")) ? "hero-{$event->slug}" : 'hero-navy-week-event';
            $heroSlug = file_exists(public_path("images/{$heroSlug}-1408.avif")) ? $heroSlug : 'hero-navy-week';
        @endphp
        <section class="nwc-hero" aria-label="Navy Week {{ $event->city }} overview">
            <picture>
                <source type="image/avif" srcset="/images/{{ $heroSlug }}-704.avif 704w, /images/{{ $heroSlug }}-1408.avif 1408w" sizes="100vw">
                <source type="image/webp" srcset="/images/{{ $heroSlug }}-704.webp 704w, /images/{{ $heroSlug }}-1408.webp 1408w" sizes="100vw">
                <img class="nwc-hero-img" src="/images/{{ $heroSlug }}.png" width="1600" height="900"
                     loading="eager" decoding="async" fetchpriority="high"
                     alt="Navy Week {{ $event->city }} 2026 — {{ $event->anchor_event }}">
            </picture>
            <div class="nwc-hero-tint" aria-hidden="true"></div>

            <div class="nwc-hero-body">
                <div class="nwc-back-wrap">
                    <a class="nwc-back" href="/schedule/" data-testid="link-back-schedule">{!! $icon('arrow-left', 12) !!} Back to Full Schedule</a>
                </div>

                <h1>{{ $event->city }} Navy Week 2026: Dates, Schedule, Events &amp; {{ $event->anchor_event }}</h1>

                <div class="nwc-stats">
                    <div class="nwc-stat"><span class="nwc-stat-label">State</span><span class="nwc-stat-value">{{ $event->state }}</span></div>
                    <div class="nwc-stat-rule"></div>
                    <div class="nwc-stat"><span class="nwc-stat-label">Dates</span><span class="nwc-stat-value">{{ $shortRange($event->start_date, $event->end_date) }}</span></div>
                    <div class="nwc-stat-rule"></div>
                    <div class="nwc-stat"><span class="nwc-stat-label">Anchor Event</span><span class="nwc-stat-value">{{ $event->anchor_event }}</span></div>
                    <div class="nwc-stat-rule"></div>
                    <div class="nwc-stat"><span class="nwc-stat-label">Cost</span><span class="nwc-stat-value">Free</span></div>
                </div>

                <div class="nwc-hero-meta">
                    <div class="nwc-badges" data-testid="status-badge-{{ $event->status->value }}">
                        <span class="nwc-badge is-{{ $event->status->value }}">{{ ['upcoming' => 'UPCOMING', 'active' => 'ACTIVE NOW', 'completed' => 'COMPLETED'][$event->status->value] ?? 'UPCOMING' }}</span>
                        @if ($event->first_time)
                            <span class="nwc-badge-first">First-time host</span>
                        @endif
                    </div>
                    <a class="nwc-navco" href="{{ LinkUrl::sanitize($navcoUrl) }}" target="_blank" rel="noopener noreferrer" data-testid="link-source-navco">Source: NAVCO {!! $icon('external-link', 11) !!}</a>
                    @if ($event->last_verified_at)
                        <span class="nwc-verified">Last verified {{ $longDate($event->last_verified_at) }}</span>
                    @endif
                </div>
            </div>
        </section>

        {{-- LLM-friendly key facts. --}}
        <section class="nwc-kf" aria-label="Navy Week {{ $event->city }} key facts">
            @include('partials.trust.key-facts', ['keyFacts' => [
                'title' => 'Navy Week '.$event->city.' '.$event->start_date->format('Y').' — Key Facts',
                'facts' => array_values(array_filter([
                    ['label' => 'Host city', 'value' => $event->city.', '.$event->state],
                    ['label' => 'Dates', 'value' => $event->dateRangeLabel()],
                    ['label' => 'Anchor event', 'value' => $event->anchor_event],
                    ['label' => 'First-time host?', 'value' => $event->first_time ? 'Yes' : 'No'],
                    ['label' => 'Cost', 'value' => 'Free and open to the public'],
                    ['label' => 'Operator', 'value' => 'Navy Office of Community Outreach (NAVCO)'],
                    $navyAssets === [] ? null : ['label' => 'Navy assets', 'value' => implode('; ', array_slice($navyAssets, 0, 4)).(count($navyAssets) > 4 ? '…' : '')],
                    ['label' => 'Coordinates', 'value' => number_format((float) $event->lat, 3, '.', '').'°, '.number_format((float) $event->lng, 3, '.', '').'°'],
                ])),
                'source' => [
                    'label' => $event->navco_url ? 'NAVCO — Navy Week '.$event->city.' '.$event->start_date->format('Y') : 'outreach.navy.mil/Navy-Weeks',
                    'url' => $event->navco_url ?: 'https://outreach.navy.mil/Navy-Weeks/',
                ],
                'lastVerified' => $event->last_verified_at?->format('F j, Y'),
            ]])
        </section>

        {{-- Mission Dossier + Anchor Event / What to Expect. --}}
        <section class="nwc-dossier" aria-label="Mission overview and anchor event">
            <div class="nwc-dossier-grid">
                <article>
                    <h2 class="nwc-h2">MISSION DOSSIER</h2>
                    @foreach ($description as $paragraph)
                        <p class="nwc-dossier-p">{{ $paragraph }}</p>
                    @endforeach
                    <div class="nwc-cta-row">
                        <a class="nwc-cta" href="{{ LinkUrl::sanitize($navcoUrl) }}" target="_blank" rel="noopener noreferrer">View Official NAVCO Page {!! $icon('external-link', 14) !!}</a>
                    </div>
                </article>

                <div class="nwc-side">
                    @if ($event->anchor_event_detail)
                        <div class="nwc-card nwc-card--top">
                            <div class="nwc-card-head">
                                <h3 class="nwc-h3 nwc-h3--flush">{!! $icon('anchor') !!} Anchor Event</h3>
                                {!! $sourceLabel('anchor') !!}
                            </div>
                            <p class="nwc-anchor-p">{{ $event->anchor_event_detail }}</p>
                            @if ($anchorEventUrl)
                                <a class="nwc-anchor-link" href="{{ LinkUrl::sanitize($anchorEventUrl) }}" target="_blank" rel="noopener noreferrer" data-testid="link-anchor-event">Anchor event website {!! $icon('external-link', 11) !!}</a>
                            @endif
                            @if ($event->first_time_note)
                                <p class="nwc-firsttime">{{ $event->first_time_note }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="nwc-card">
                        <h3 class="nwc-h3">{!! $icon('shield') !!} What to Expect</h3>
                        <ul class="nwc-bullets">
                            @foreach ($highlights as $highlight)
                                <li><div class="nwc-dot" aria-hidden="true"></div><span>{{ $highlight }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- Daily schedule. --}}
        <section class="nwc-sec" aria-label="Daily schedule">
            <h2 class="nwc-h2 nwc-h2--icon">{!! $icon('calendar', 26, 'var(--gold)') !!} DAILY SCHEDULE</h2>
            <p class="nwc-lede">Day-by-day breakdown. Items marked "TBA" mean NAVCO hasn't published precise times yet — check the official NAVCO city page or anchor event website closer to event week for confirmed schedules.</p>
            <div class="nwc-legend-wrap">
                <div class="nwc-legend" data-testid="source-legend">
                    <span class="nwc-legend-key">Source key:</span>
                    @foreach ($sourceLevels as $level => $levelMeta)
                        <span class="nwc-legend-item">{!! $sourceLabel($level) !!}<span class="nwc-legend-desc">{{ $levelMeta[1] }}</span></span>
                    @endforeach
                </div>
            </div>
            <div class="nwc-days">
                @foreach ($dailySchedule as $day)
                    <div class="nwc-day">
                        <div class="nwc-day-head">
                            <h3 class="nwc-day-title">{{ $longDate(Carbon::parse((string) ($day['date'] ?? ''))) }}</h3>
                            @if (! empty($day['tba']))
                                <span class="nwc-tba">TBA</span>
                            @endif
                        </div>
                        <ul class="nwc-items">
                            @foreach (array_filter($listOf($day['items'] ?? []), 'is_array') as $item)
                                <li>
                                    <div class="nwc-item-head">
                                        @if (! empty($item['time']))
                                            <span class="nwc-item-time">{{ $item['time'] }}</span>
                                        @endif
                                        <span class="nwc-item-title">{{ $item['title'] ?? '' }}</span>
                                        {!! $sourceLabel($scheduleSource($item)) !!}
                                    </div>
                                    @if (! empty($item['venue']))
                                        <span class="nwc-item-venue">{!! $icon('map-pin', 11, 'currentColor', 'nwc-inline-icon') !!}{{ $item['venue'] }}</span>
                                    @endif
                                    @if (! empty($item['description']))
                                        <p class="nwc-item-desc">{{ $item['description'] }}</p>
                                    @endif
                                    @if (! empty($item['source']))
                                        <a class="nwc-item-src" href="{{ LinkUrl::sanitize((string) $item['source']) }}" target="_blank" rel="noopener noreferrer">source {!! $icon('external-link', 10) !!}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Venues + schematic map. --}}
        @if ($venues !== [])
            <section class="nwc-sec" aria-label="Venues and parking">
                <h2 class="nwc-h2 nwc-h2--icon">{!! $icon('map', 26, 'var(--gold)') !!} VENUES &amp; MAP</h2>
                <div class="nwc-map-wrap">
                    <div class="nwc-map">
                        <svg viewBox="0 0 {{ $mapW }} {{ $mapH }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Map of Navy Week {{ $event->city }} key venues">
                            <rect x="0" y="0" width="{{ $mapW }}" height="{{ $mapH }}" fill="rgba(21,35,64,0.65)"></rect>
                            <g class="nwc-map-grid">
                                @for ($i = 0; $i <= 16; $i++)
                                    <line x1="{{ ($mapW / 16) * $i }}" y1="0" x2="{{ ($mapW / 16) * $i }}" y2="{{ $mapH }}" stroke="#C9A84C" stroke-width="0.5"></line>
                                @endfor
                                @for ($i = 0; $i <= 10; $i++)
                                    <line x1="0" y1="{{ ($mapH / 10) * $i }}" x2="{{ $mapW }}" y2="{{ ($mapH / 10) * $i }}" stroke="#C9A84C" stroke-width="0.5"></line>
                                @endfor
                            </g>
                            @php
                                [$cx, $cy] = $project((float) $event->lat, (float) $event->lng);
                            @endphp
                            <g>
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="6" fill="rgba(122,168,216,0.25)" stroke="#7AA8D8" stroke-width="1"></circle>
                                <text x="{{ $cx + 10 }}" y="{{ $cy + 4 }}" fill="rgba(250,250,248,0.55)" font-family="'IBM Plex Mono', monospace" font-size="10" letter-spacing="1">{{ mb_strtoupper($event->city) }}</text>
                            </g>
                            @foreach ($pinned as $i => $venue)
                                @php
                                    [$vx, $vy] = $project((float) $venue['lat'], (float) $venue['lng']);
                                @endphp
                                <g>
                                    <circle cx="{{ $vx }}" cy="{{ $vy }}" r="8" fill="#C9A84C" stroke="rgba(10,22,40,0.9)" stroke-width="2"></circle>
                                    <text x="{{ $vx }}" y="{{ $vy + 3 }}" fill="#0A1628" font-family="'IBM Plex Mono', monospace" font-size="9" text-anchor="middle" font-weight="700">{{ $i + 1 }}</text>
                                    <text x="{{ $vx + 10 }}" y="{{ $vy + 4 }}" fill="rgba(250,250,248,0.85)" font-family="'IBM Plex Mono', monospace" font-size="10" letter-spacing="0.5">{{ $venue['name'] ?? '' }}</text>
                                </g>
                            @endforeach
                        </svg>
                        <div class="nwc-map-note">Schematic map — not to scale. Pins are approximate locations of primary Navy Week venues in {{ $event->city }}. Use the venue addresses below for navigation.</div>
                    </div>
                </div>

                <div class="nwc-venues">
                    @foreach ($venues as $i => $venue)
                        @php
                            $venueName = (string) ($venue['name'] ?? '');
                            $venueAddress = (string) ($venue['address'] ?? '');
                            $mapsQuery = rawurlencode($venueAddress !== ''
                                ? $venueName.', '.$venueAddress
                                : $venueName.', '.$event->city.', '.$event->state);
                        @endphp
                        <div class="nwc-venue">
                            <div class="nwc-venue-head">
                                <span class="nwc-venue-num">{{ $i + 1 }}.</span>
                                <h3 class="nwc-venue-name">{{ $venueName }}</h3>
                                {!! $sourceLabel(is_string($venue['source_level'] ?? null) ? $venue['source_level'] : 'local') !!}
                            </div>
                            @if ($venueAddress !== '')
                                <a class="nwc-venue-addr" href="https://www.google.com/maps/search/?api=1&amp;query={{ $mapsQuery }}" target="_blank" rel="noopener noreferrer">{!! $icon('map-pin', 11, 'currentColor', 'nwc-inline-icon') !!}{{ $venueAddress }}</a>
                            @endif
                            @if (! empty($venue['notes']))
                                <p class="nwc-venue-notes">{{ $venue['notes'] }}</p>
                            @endif
                            @if (! empty($venue['parking']))
                                <p class="nwc-venue-parking">{!! $icon('car', 11, 'currentColor', 'nwc-inline-icon') !!}{{ $venue['parking'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($event->parking_notes)
                    <div class="nwc-card nwc-card--left nwc-parking">
                        <div class="nwc-card-head nwc-card-head--gap">
                            <h3 class="nwc-h3 nwc-h3--flush">{!! $icon('car') !!} Parking &amp; Getting There</h3>
                            {!! $sourceLabel('local') !!}
                        </div>
                        <p class="nwc-card-p">{{ $event->parking_notes }}</p>
                    </div>
                @endif
            </section>
        @endif

        {{-- Cost + official sources. --}}
        <section class="nwc-sec" aria-label="Cost and official sources">
            <div class="nwc-cost-grid">
                @if ($event->cost_summary)
                    <div class="nwc-card nwc-card--left">
                        <h3 class="nwc-h3">{!! $icon('dollar-sign') !!} Cost</h3>
                        <p class="nwc-card-p">{{ $event->cost_summary }}</p>
                    </div>
                @endif
                @if ($officialSources->isNotEmpty())
                    <div class="nwc-card">
                        <h3 class="nwc-h3">{!! $icon('link-2') !!} Official Sources</h3>
                        <ul class="nwc-sources">
                            @foreach ($officialSources as $source)
                                <li><a href="{{ LinkUrl::sanitize((string) $source->url) }}" target="_blank" rel="noopener noreferrer">{{ $source->label }} {!! $icon('external-link', 12) !!}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        {{-- Local military context (a LIST of paragraphs, never a string). --}}
        @if ($militaryContext !== [])
            <section class="nwc-sec" aria-label="Local military context">
                <div class="nwc-card">
                    <h3 class="nwc-h3">{!! $icon('shield') !!} Local Military Context</h3>
                    @foreach ($militaryContext as $paragraph)
                        <p class="nwc-card-p @if (! $loop->last) nwc-card-p--stacked @endif">{{ $paragraph }}</p>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Navy assets & units. --}}
        @if ($navyAssets !== [])
            <section class="nwc-sec" aria-label="Navy assets and units">
                <div class="nwc-card">
                    <h3 class="nwc-h3">{!! $icon('anchor') !!} Navy Assets &amp; Units</h3>
                    <ul class="nwc-bullets nwc-bullets--grid">
                        @foreach ($navyAssets as $asset)
                            <li><div class="nwc-dot" aria-hidden="true"></div><span>{{ $asset }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- FAQ. --}}
        @if ($event->faqs->isNotEmpty())
            <section class="nwc-sec" aria-label="Frequently asked questions about Navy Week {{ $event->city }}">
                <h2 class="nwc-h2">FAQ — NAVY WEEK {{ mb_strtoupper($event->city) }}</h2>
                <div class="nwc-card">
                    <div class="nwc-faq-list">
                        @foreach ($event->faqs as $faq)
                            <details class="nw-faq" @if ($loop->first) open @endif>
                                <summary>
                                    <h3>{{ $faq->question }}</h3>
                                    {!! $icon('chevron-down', 18, 'currentColor', 'nw-faq-chev') !!}
                                </summary>
                                <div class="nw-faq-a">{{ $faq->answer }}</div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- More Navy Week cities. --}}
        @if ($relatedCities->isNotEmpty())
            <section class="nwc-more" aria-label="Other Navy Week cities">
                <div class="nwc-more-inner">
                    <h2 class="nwc-more-h2">MORE NAVY WEEK 2026 CITIES</h2>
                    <div class="nwc-more-grid">
                        @foreach ($relatedCities as $other)
                            <a class="nwc-more-card" href="{{ PagePaths::child('navy_week_cities', $other->slug) }}">
                                <span class="nwc-more-dates">{{ $shortRange($other->start_date, $other->end_date) }}</span>
                                <span class="nwc-more-city">{{ $other->city }}</span>
                                <span class="nwc-more-meta">{{ $other->state }} &middot; {{ $other->anchor_event }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Navy reference cross-links. --}}
        @php
            $refCards = [
                $stateWithBases === null
                    ? ['href' => PagePaths::root('bases'), 'eyebrow' => 'Installations', 'title' => 'Navy Bases']
                    : ['href' => PagePaths::child('bases', $stateWithBases['slug']), 'eyebrow' => $stateWithBases['count'].' Installation'.($stateWithBases['count'] === 1 ? '' : 's'), 'title' => 'Navy Bases in '.$stateWithBases['name']],
                ['href' => PagePaths::root('ranks'), 'eyebrow' => 'Officer & Enlisted', 'title' => 'Navy Ranks'],
                ['href' => PagePaths::root('navy_reference'), 'eyebrow' => 'Reference Hub', 'title' => 'All Navy Reference'],
            ];
        @endphp
        <section class="nwc-ref" aria-label="Learn more about the U.S. Navy">
            <div class="nwc-ref-eyebrow">// Researching the Navy itself?</div>
            <h2 class="nwc-ref-h2">U.S. NAVY REFERENCE</h2>
            <p class="nwc-ref-lede">Beyond {{ $event->city }} Navy Week, our reference library covers the service behind the outreach — bases, ranks, officer designators, and veteran benefits.</p>
            <ul class="nwc-ref-list">
                @foreach ($refCards as $card)
                    <li>
                        <a href="{{ $card['href'] }}" data-testid="link-city-ref-{{ trim(preg_replace('/[^a-z0-9]+/i', '-', $card['href']) ?? '', '-') }}">
                            <span class="nwc-ref-card-eyebrow">{{ $card['eyebrow'] }}</span>
                            <span class="nwc-ref-card-title">{{ $card['title'] }} {!! $icon('arrow-right', 14) !!}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Previous / next stop. --}}
        <section class="nwc-adj" aria-label="Navigate to previous or next city">
            <div class="nwc-adj-inner">
                @if ($prevCity)
                    <a class="nwc-adj-link" href="{{ PagePaths::child('navy_week_cities', $prevCity->slug) }}" data-testid="link-prev-city">
                        <div class="nwc-adj-icon">{!! $icon('arrow-left', 24) !!}</div>
                        <div class="nwc-adj-body">
                            <span class="nwc-adj-label">Previous Stop</span>
                            <span class="nwc-adj-city">{{ $prevCity->city }}</span>
                            <span class="nwc-adj-dates">{{ $shortRange($prevCity->start_date, $prevCity->end_date) }}</span>
                        </div>
                    </a>
                @endif
                @if ($nextCity)
                    <a class="nwc-adj-link nwc-adj-link--next" href="{{ PagePaths::child('navy_week_cities', $nextCity->slug) }}" data-testid="link-next-city">
                        <div class="nwc-adj-body">
                            <span class="nwc-adj-label">Next Stop</span>
                            <span class="nwc-adj-city">{{ $nextCity->city }}</span>
                            <span class="nwc-adj-dates">{{ $shortRange($nextCity->start_date, $nextCity->end_date) }}</span>
                        </div>
                        <div class="nwc-adj-icon">{!! $icon('arrow-right', 24) !!}</div>
                    </a>
                @endif
            </div>
        </section>
    </main>
@endsection
