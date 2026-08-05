@extends('layouts.base')

{{-- Fleet-week city guide (/fleetweek/{slug}/). Head/JSON-LD (Article + WebPage +
     author/reviewer Person + FAQPage + Festival) is byte-locked by SeoHead +
     FleetWeekPageSchema; this body is a 1:1 port of the legacy
     src/page-views/FleetWeekDetail.tsx (markup + its inline styles, which now live
     as classes in resources/css/families/fleet-week.css). --}}
@php
    /** @var \App\Domain\Pillars\Models\FleetWeek $week */
    use App\Domain\Navigation\Support\LinkUrl;
    use App\Domain\Publishing\Support\PagePaths;

    $fleetWeekRoot = PagePaths::root('fleet_weeks');

    $intro = is_array($week->intro) ? $week->intro : [];
    $history = is_array($week->history) ? $week->history : [];
    $airshow = is_array($week->airshow) ? $week->airshow : [];
    $paradeOfShips = is_array($week->parade_of_ships) ? $week->parade_of_ships : [];
    $shipTours = is_array($week->ship_tours) ? $week->ship_tours : [];
    $viewingSpots = is_array($week->viewing_spots) ? $week->viewing_spots : [];
    $festival = is_array($week->festival) ? $week->festival : null;
    $organizer = is_array($festival['organizer'] ?? null) ? $festival['organizer'] : null;
    $statusClass = 'is-'.$week->status->value;

    // " (Organizer Name)" — the parenthetical the disclosure appends when the
    // record carries a festival organizer, omitted entirely when it does not.
    $organizerParen = $organizer
        ? ' (<a href="'.e(LinkUrl::sanitize((string) ($organizer['url'] ?? ''))).'" target="_blank" rel="noopener noreferrer nofollow">'.e((string) ($organizer['name'] ?? '')).'</a>)'
        : '';

    // A payload field can be a bare list of strings on one record and a
    // {paragraphs: [...]} map on another — normalise before rendering.
    $paragraphs = static function (array $payload): array {
        $list = is_array($payload['paragraphs'] ?? null) ? $payload['paragraphs'] : $payload;

        return array_values(array_filter($list, static fn ($p): bool => is_string($p) && $p !== ''));
    };

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size = 22, string $class = ''): string {
        $paths = [
            'triangle-alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
            'arrow-up-right' => '<path d="M7 7h10v10"></path><path d="M7 17 17 7"></path>',
            'calendar-days' => '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path>',
            'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path>',
            'check' => '<path d="M20 6 9 17l-5-5"></path>',
            'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
            'ship' => '<path d="M12 10.189V14"></path><path d="M12 2v3"></path><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"></path><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-8.188-3.639a2 2 0 0 0-1.624 0L3 14a11.6 11.6 0 0 0 2.81 7.76"></path><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1s1.2 1 2.5 1c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"></path>',
            'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide '.$class.'" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };

    // FleetWeekViewingMap.tsx — a prerender-safe schematic: project each spot's
    // lat/lng onto a fixed 800x460 viewBox. Spots without coordinates get no pin
    // (and a city with no coordinates at all renders no map).
    $pins = [];
    foreach ($viewingSpots as $index => $spot) {
        $lat = is_array($spot) ? ($spot['lat'] ?? null) : null;
        $lng = is_array($spot) ? ($spot['lng'] ?? null) : null;
        if (is_int($lat) || is_float($lat)) {
            if (is_int($lng) || is_float($lng)) {
                $pins[] = ['lat' => (float) $lat, 'lng' => (float) $lng, 'label' => $index + 1];
            }
        }
    }

    $map = null;
    if ($pins !== []) {
        $lats = array_column($pins, 'lat');
        $lngs = array_column($pins, 'lng');
        $centerLat = $festival['location']['lat'] ?? null;
        $centerLng = $festival['location']['lng'] ?? null;
        $cLat = (is_int($centerLat) || is_float($centerLat)) ? (float) $centerLat : array_sum($lats) / count($lats);
        $cLng = (is_int($centerLng) || is_float($centerLng)) ? (float) $centerLng : array_sum($lngs) / count($lngs);

        $minLat = min(min($lats), $cLat);
        $maxLat = max(max($lats), $cLat);
        $minLng = min(min($lngs), $cLng);
        $maxLng = max(max($lngs), $cLng);
        $padLat = max(($maxLat - $minLat) * 0.18, 0.01);
        $padLng = max(($maxLng - $minLng) * 0.18, 0.01);
        $minLat -= $padLat;
        $maxLat += $padLat;
        $minLng -= $padLng;
        $maxLng += $padLng;

        $mapW = 800;
        $mapH = 460;
        $project = static fn (float $lat, float $lng): array => [
            (($lng - $minLng) / ($maxLng - $minLng)) * $mapW,
            (($maxLat - $lat) / ($maxLat - $minLat)) * $mapH,
        ];
        $map = ['center' => $project($cLat, $cLng), 'pins' => []];
        foreach ($pins as $pin) {
            $map['pins'][] = ['xy' => $project($pin['lat'], $pin['lng']), 'label' => $pin['label']];
        }
    }
@endphp

@section('content')
    <main class="fw-page">
        <section class="fw-sec fw-sec--hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $fleetWeekRoot }}">Fleet Week</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $week->city }}</span>
            </nav>

            {{-- TrustDisclosure — the three legacy variants (official event, a
                 third-party festival only, or no event at all). --}}
            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>
                    @if ($week->has_official_fleet_week)
                        <strong>{{ $week->branding_name }}</strong> is organized by a third party{!! $organizerParen !!}.
                        NavyWeek.org is an independent guide and is
                        <strong>not affiliated with, endorsed by, or sponsored by</strong> the event, its
                        organizers, or the U.S. Navy. Dates, schedules, and ticketing are set by the organizer
                        and can change — always confirm current details on the official site before you travel.
                    @elseif ($festival)
                        <strong>{{ $festival['name'] ?? '' }}</strong> is organized by a third party{!! $organizerParen !!},
                        and there is <strong>no traditional ship-tour fleet week</strong> in {{ $week->city }}.
                        NavyWeek.org is an independent guide and is
                        <strong>not affiliated with, endorsed by, or sponsored by</strong> the event, its
                        organizers, or the U.S. military. Dates and schedules are set by the organizer and can
                        change — always confirm current details on the official site before you travel.
                    @else
                        There is <strong>no official, Navy-run fleet week</strong> in {{ $week->city }}. NavyWeek.org is
                        an independent guide and is
                        <strong>not affiliated with, endorsed by, or sponsored by</strong> the U.S. Navy. This
                        guide is background and history — confirm anything time-sensitive with the official
                        sources cited below before you travel.
                    @endif
                </p>
            </section>

            <div class="fw-statusrow">
                <span class="fw-pill {{ $statusClass }}">{{ $week->status_label }}</span>
                <span class="fw-place">{{ $week->city }}, {{ $week->state_abbr }}</span>
            </div>

            <h1>{{ $week->h1 }}</h1>

            @if ($week->dek)
                <p class="fw-dek">{{ $week->dek }}</p>
            @endif

            @if ($week->status_note)
                <div class="fw-note {{ $statusClass }}">
                    {!! $icon('triangle-alert', 18) !!}
                    <span>{{ $week->status_note }}</span>
                </div>
            @endif

            @foreach ($intro as $paragraph)
                <p class="fw-p">{{ $paragraph }}</p>
            @endforeach

            @include('partials.trust.byline')

            @if ($week->official_url)
                <a class="fw-cta" href="{{ LinkUrl::sanitize($week->official_url) }}" rel="noopener noreferrer nofollow" target="_blank">
                    Official site &amp; schedule
                    {!! $icon('arrow-up-right', 16) !!}
                </a>
                <p class="fw-cta-note">
                    Opens {{ preg_replace(['#^https?://#', '#/$#'], '', $week->official_site_label ?: $week->official_url) }}
                </p>
            @endif

            @include('partials.trust.key-facts', ['keyFacts' => filled($week->quick_facts) ? [
                'title' => $week->branding_name.' '.$week->year.' — Key Facts',
                'ariaLabel' => $week->city.' Fleet Week key facts',
                'facts' => $week->quick_facts,
                'lastVerified' => $week->last_verified,
                'source' => $week->official_url ? [
                    'label' => $week->official_site_label ?: ($week->sources->first()->label ?? 'Official site'),
                    'url' => $week->official_url,
                ] : null,
            ] : null])
        </section>

        {{-- SCHEDULE: the day-by-day table. --}}
        @if (filled($week->schedule))
            <section class="fw-sec" aria-labelledby="schedule">
                <h2 id="schedule">{!! $icon('calendar-days', 22, 'fw-h2-icon') !!}SCHEDULE</h2>
                <div class="fw-table-wrap">
                    <table class="fw-table">
                        <caption>{{ $week->branding_name }} {{ $week->year }} day-by-day</caption>
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Event</th>
                                <th scope="col">Time</th>
                                <th scope="col">Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($week->schedule as $row)
                                <tr>
                                    <th scope="row">
                                        {{ $row['date'] ?? '' }}
                                        <span class="fw-table-day">{{ $row['day'] ?? '' }}</span>
                                    </th>
                                    <td>{{ $row['event'] ?? '' }}</td>
                                    <td>{{ $row['time'] ?? '' }}</td>
                                    <td>{{ $row['location'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($week->schedule_note)
                    <p class="fw-sched-note">{{ $week->schedule_note }}</p>
                @endif
            </section>
        @endif

        {{-- AIR SHOW: paragraphs, the performer roster, then the timing notes. --}}
        @if ($week->has_air_show && $airshow !== [])
            <section class="fw-sec" aria-labelledby="air-show">
                <h2 id="air-show">{!! $icon('plane', 22, 'fw-h2-icon') !!}AIR SHOW</h2>
                @foreach ($paragraphs($airshow) as $paragraph)
                    <p class="fw-p">{{ $paragraph }}</p>
                @endforeach

                @if (filled($airshow['performers'] ?? null))
                    <h3 class="fw-h3">Performers</h3>
                    <ul class="fw-performers">
                        @foreach ($airshow['performers'] as $performer)
                            <li>
                                {!! $icon('check', 18) !!}
                                <span>
                                    {{ is_array($performer) ? ($performer['name'] ?? '') : $performer }}
                                    @if (is_array($performer) && ! empty($performer['note']))
                                        <span class="fw-muted"> — {{ $performer['note'] }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (filled($airshow['showWindow'] ?? null) || filled($airshow['headlinerFlightTime'] ?? null) || filled($airshow['practiceNote'] ?? null) || filled($airshow['ticketNote'] ?? null))
                    <div class="fw-air-notes">
                        @if (! empty($airshow['showWindow']))
                            <p class="fw-p fw-p--flush fw-air-window">
                                {!! $icon('clock', 18) !!}
                                <span>{{ $airshow['showWindow'] }}</span>
                            </p>
                        @endif
                        @foreach (['headlinerFlightTime', 'practiceNote', 'ticketNote'] as $note)
                            @if (! empty($airshow[$note]))
                                <p class="fw-p fw-p--flush">{{ $airshow[$note] }}</p>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($paradeOfShips !== [])
            <section class="fw-sec" aria-labelledby="parade-of-ships">
                <h2 id="parade-of-ships">{!! $icon('ship', 22, 'fw-h2-icon') !!}PARADE OF SHIPS</h2>
                @foreach ($paragraphs($paradeOfShips) as $paragraph)
                    <p class="fw-p">{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if ($shipTours !== [])
            <section class="fw-sec" aria-labelledby="ship-tours">
                <h2 id="ship-tours">FREE SHIP TOURS</h2>
                @foreach ($paragraphs($shipTours) as $paragraph)
                    <p class="fw-p">{{ $paragraph }}</p>
                @endforeach
                @if (filled($shipTours['rules'] ?? null))
                    <h3 class="fw-h3">What to know before you board</h3>
                    <ul class="fw-rules">
                        @foreach ($shipTours['rules'] as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($viewingSpots !== [])
            <section class="fw-sec" aria-labelledby="viewing">
                <h2 id="viewing">{!! $icon('map-pin', 22, 'fw-h2-icon') !!}BEST PLACES TO WATCH</h2>
                @if ($week->viewing_intro)
                    <p class="fw-p">{{ $week->viewing_intro }}</p>
                @endif

                @if ($map)
                    <div class="fw-map">
                        <svg viewBox="0 0 800 460" preserveAspectRatio="xMidYMid meet" role="img"
                             aria-label="Schematic map of {{ $week->city }} fleet week viewing locations">
                            <rect x="0" y="0" width="800" height="460" fill="rgba(21,35,64,0.65)"></rect>
                            <g class="fw-map-grid">
                                @for ($i = 0; $i <= 16; $i++)
                                    <line x1="{{ (800 / 16) * $i }}" y1="0" x2="{{ (800 / 16) * $i }}" y2="460" stroke="#C9A84C" stroke-width="0.5"></line>
                                @endfor
                                @for ($i = 0; $i <= 9; $i++)
                                    <line x1="0" y1="{{ (460 / 9) * $i }}" x2="800" y2="{{ (460 / 9) * $i }}" stroke="#C9A84C" stroke-width="0.5"></line>
                                @endfor
                            </g>
                            <circle cx="{{ $map['center'][0] }}" cy="{{ $map['center'][1] }}" r="6" fill="rgba(122,168,216,0.25)" stroke="#7AA8D8" stroke-width="1"></circle>
                            @foreach ($map['pins'] as $pin)
                                <g>
                                    <circle cx="{{ $pin['xy'][0] }}" cy="{{ $pin['xy'][1] }}" r="11" fill="#C9A84C" stroke="rgba(10,22,40,0.9)" stroke-width="2"></circle>
                                    <text x="{{ $pin['xy'][0] }}" y="{{ $pin['xy'][1] + 4 }}" fill="#0A1628" font-family="'IBM Plex Mono', monospace" font-size="12" text-anchor="middle" font-weight="700">{{ $pin['label'] }}</text>
                                </g>
                            @endforeach
                        </svg>
                        <div class="fw-map-note">
                            Schematic map — not to scale. Numbered pins match the viewing spots listed below;
                            confirm exact locations and access on the day.
                        </div>
                    </div>
                @endif

                <div class="fw-spots">
                    @foreach ($viewingSpots as $index => $spot)
                        @php
                            $spotLat = $spot['lat'] ?? null;
                            $spotLng = $spot['lng'] ?? null;
                            $hasPin = (is_int($spotLat) || is_float($spotLat)) && (is_int($spotLng) || is_float($spotLng));
                        @endphp
                        <div class="fw-spot">
                            <div class="fw-spot-head">
                                @if ($hasPin)
                                    <span class="fw-spot-num" aria-hidden="true">{{ $index + 1 }}</span>
                                @endif
                                <div class="fw-spot-name">{{ $spot['name'] ?? '' }}</div>
                            </div>
                            <p class="fw-spot-why">{{ $spot['why'] ?? '' }}</p>
                            @if (! empty($spot['transit']))
                                <div class="fw-spot-transit">Transit: {{ $spot['transit'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (filled($week->getting_there))
            <section class="fw-sec" aria-labelledby="getting-there">
                <h2 id="getting-there">GETTING THERE &amp; PARKING</h2>
                <ul class="fw-getting">
                    @foreach ($week->getting_there as $tip)
                        <li>{{ $tip }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($history !== [])
            <section class="fw-sec" aria-labelledby="history">
                <h2 id="history">HISTORY &amp; BACKGROUND</h2>
                @foreach ($history as $paragraph)
                    <p class="fw-p">{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if (filled($week->past_years))
            <section class="fw-sec" aria-labelledby="past-years">
                <h2 id="past-years">PAST YEARS</h2>
                <div class="fw-past">
                    @foreach ($week->past_years as $past)
                        <div class="fw-past-row">
                            <span class="fw-past-year">{{ $past['year'] ?? '' }}</span>
                            <p class="fw-p fw-p--flush">{{ $past['note'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($week->sources->isNotEmpty())
            <section class="fw-sec" aria-labelledby="sources">
                <h2 id="sources">SOURCES</h2>
                <ul class="fw-sources">
                    @foreach ($week->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ LinkUrl::sanitize($source->url) }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                            @if ($source->publisher)
                                <span class="fw-muted"> — {{ $source->publisher }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="fw-sec" aria-labelledby="faq">
            <h2 id="faq">FREQUENTLY ASKED QUESTIONS</h2>
            <div class="fw-faqs">
                @foreach ($week->faqs as $faq)
                    <details class="nw-faq" @if ($loop->first) open @endif>
                        <summary>
                            <h3>{{ $faq->question }}</h3>
                            {!! $icon('chevron-down', 18, 'nw-faq-chev') !!}
                        </summary>
                        <div class="nw-faq-a">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="fw-sec fw-sec--last">
            @if (filled($relatedWeeks))
                <h2>MORE FLEET WEEKS</h2>
                <div class="fw-related">
                    @foreach ($relatedWeeks as $related)
                        <a href="{{ PagePaths::child('fleet_weeks', (string) $related->slug) }}">
                            <span class="fw-related-when">{{ $related->month_label }} {{ $related->year }}</span>
                            <span class="fw-related-city">
                                {{ $related->city }}
                                {!! $icon('arrow-right', 14) !!}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <a class="fw-back" href="{{ $fleetWeekRoot }}">
                {!! $icon('arrow-right', 14) !!}
                All U.S. fleet weeks
            </a>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
