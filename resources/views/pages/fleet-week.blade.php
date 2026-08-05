@extends('layouts.base')

{{-- Fleet-week city guide (/fleetweek/{slug}/). Head/JSON-LD (Article + WebPage +
     author/reviewer Person + FAQPage + Festival) is byte-locked by SeoHead +
     FleetWeekPageSchema; this body is a clean semantic rebuild. --}}
@php
    /** @var \App\Domain\Pillars\Models\FleetWeek $week */
    $intro = is_array($week->intro) ? $week->intro : [];
    $history = is_array($week->history) ? $week->history : [];
@endphp

@section('content')
    <main class="fleet-week">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/fleetweek/">Fleet Week</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $week->city }} Fleet Week</span>
        </nav>

        <p class="independence-disclosure" role="note">
            NavyWeek.org is an independent guide and is <strong>not affiliated</strong> with the
            organizers of this event or the U.S. Navy.
        </p>

        <header class="fleet-week-hero">
            <p class="eyebrow">// {{ $week->branding_name }} {{ $week->year }}</p>
            <h1>{{ $week->h1 }}</h1>
            @if ($week->dek)
                <p class="dek">{{ $week->dek }}</p>
            @endif
            @if ($week->status_label)
                <p class="status">{{ $week->status_label }}</p>
            @endif
            @foreach ($intro as $paragraph)
                <p class="intro">{{ $paragraph }}</p>
            @endforeach
        </header>

        @include('partials.trust.key-facts', ['keyFacts' => filled($week->quick_facts) ? [
            'title' => trim(($week->branding_name ?? $week->city).' '.$week->year).' — Key Facts',
            'facts' => $week->quick_facts,
            'source' => $week->official_url ? ['label' => $week->official_site_label ?? 'Official event site', 'url' => $week->official_url] : null,
        ] : null])

        @if ($week->official_url)
            <p class="official-link">
                <a href="{{ $week->official_url }}" rel="noopener noreferrer nofollow" target="_blank">
                    {{ $week->official_site_label ?: 'Official site' }}
                </a>
            </p>
        @endif


        {{-- SCHEDULE: a dated event list. --}}
        @if (filled($week->schedule))
            <section class="fw-section" aria-label="Schedule">
                <h2>SCHEDULE</h2>
                @if ($week->schedule_note)
                    <p>{{ $week->schedule_note }}</p>
                @endif
                <ul class="fw-schedule">
                    @foreach ($week->schedule as $item)
                        <li>
                            <span class="fw-date">{{ $item['date'] ?? '' }}@if (! empty($item['day'])) ({{ $item['day'] }})@endif</span>
                            <span class="fw-event">{{ $item['event'] ?? '' }}</span>
                            @if (! empty($item['time']))
                                <span class="fw-time">{{ $item['time'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- AIR SHOW: paragraphs + a named performer list. --}}
        @if (filled($week->airshow))
            @php($air = $week->airshow)
            <section class="fw-section" aria-label="Air show">
                <h2>AIR SHOW</h2>
                @foreach (($air['paragraphs'] ?? []) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @foreach (['showWindow', 'headlinerFlightTime', 'practiceNote', 'ticketNote'] as $note)
                    @if (! empty($air[$note]))
                        <p>{{ $air[$note] }}</p>
                    @endif
                @endforeach
                @if (! empty($air['performers']))
                    <h3>Performers</h3>
                    <ul>
                        @foreach ($air['performers'] as $performer)
                            <li>
                                <strong>{{ $performer['name'] ?? '' }}</strong>@if (! empty($performer['note'])) — {{ $performer['note'] }}@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (filled($week->parade_of_ships))
            <section class="fw-section" aria-label="Parade of ships">
                <h2>PARADE OF SHIPS</h2>
                @foreach (($week->parade_of_ships['paragraphs'] ?? $week->parade_of_ships) as $paragraph)
                    @if (is_string($paragraph))<p>{{ $paragraph }}</p>@endif
                @endforeach
            </section>
        @endif

        @if (filled($week->ship_tours))
            <section class="fw-section" aria-label="Free ship tours">
                <h2>FREE SHIP TOURS</h2>
                @foreach (($week->ship_tours['paragraphs'] ?? []) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if (! empty($week->ship_tours['rules']))
                    <h3>What to know before you board</h3>
                    <ul>
                        @foreach ($week->ship_tours['rules'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (filled($week->viewing_spots))
            <section class="fw-section" aria-label="Best places to watch">
                <h2>BEST PLACES TO WATCH</h2>
                @if ($week->viewing_intro)
                    <p>{{ $week->viewing_intro }}</p>
                @endif
                <ul>
                    @foreach ($week->viewing_spots as $spot)
                        <li>
                            <strong>{{ $spot['name'] ?? '' }}</strong>@if (! empty($spot['why'])) — {{ $spot['why'] }}@endif
                            @if (! empty($spot['transit'])) <span class="fw-transit">{{ $spot['transit'] }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (filled($week->getting_there))
            <section class="fw-section" aria-label="Getting there and parking">
                <h2>GETTING THERE &amp; PARKING</h2>
                <ul>
                    @foreach ($week->getting_there as $tip)
                        <li>{{ $tip }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($history !== [])
            <section class="fleet-week-history" aria-label="History">
                <h2>HISTORY &amp; BACKGROUND</h2>
                @foreach ($history as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif


        @if (filled($week->past_years))
            <section class="fw-section" aria-label="Past years">
                <h2>PAST YEARS</h2>
                <ul>
                    @foreach ($week->past_years as $past)
                        <li><strong>{{ $past['year'] ?? '' }}</strong>@if (! empty($past['note'])) — {{ $past['note'] }}@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($week->faqs->isNotEmpty())
            <section class="fleet-week-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($week->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($week->sources->isNotEmpty())
            <footer class="fleet-week-sources">
                <h2>SOURCES</h2>
                <ul>
                    @foreach ($week->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </footer>
        @endif

        @if (filled($relatedWeeks))
            <section class="fw-section" aria-label="More fleet weeks">
                <h2>MORE FLEET WEEKS</h2>
                <ul>
                    @foreach ($relatedWeeks as $relatedSlug)
                        <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::child('fleet_weeks', (string) $relatedSlug) }}">{{ \Illuminate\Support\Str::headline((string) $relatedSlug) }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('partials.trust.editorial-policy')
    </main>
@endsection
