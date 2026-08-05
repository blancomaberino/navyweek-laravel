@extends('layouts.base')

{{-- Navy Week city page (/city/{slug}/). Head/JSON-LD (Breadcrumb + 2 GovernmentOrg +
     Event(+subEvents) + FAQPage) is byte-locked by SeoHead + NavyWeekCitySchema; this
     body is a clean semantic rebuild. --}}
@php
    /** @var \App\Domain\Pillars\Models\NavyWeekEvent $event */
    $highlights = is_array($event->highlights) ? $event->highlights : [];
    $navyAssets = is_array($event->navy_assets) ? $event->navy_assets : [];
    $keyVenues = is_array($event->key_venues) ? $event->key_venues : [];
    $dailySchedule = is_array($event->daily_schedule) ? $event->daily_schedule : [];
@endphp

@section('content')
    <main class="navy-week-city">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/schedule/">Schedule</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $event->city }}</span>
        </nav>

        <header class="navy-week-hero">
            <p class="eyebrow">// U.S. Navy Week 2026</p>
            <h1>{{ $event->city }} Navy Week 2026: Dates, Schedule, Events &amp; {{ $event->anchor_event }}</h1>
            <p class="dates">{{ \Illuminate\Support\Carbon::parse($event->start_date)->format('F j') }} – {{ \Illuminate\Support\Carbon::parse($event->end_date)->format('F j, Y') }}</p>
            @if ($event->anchor_event_detail)
                <p class="anchor">{{ $event->anchor_event_detail }}</p>
            @endif
        </header>

        @if ($highlights !== [])
            <section class="navy-week-highlights" aria-label="Highlights">
                <h2>FREE PUBLIC EVENTS</h2>
                <ul>
                    @foreach ($highlights as $highlight)
                        <li>{{ $highlight }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($navyAssets !== [])
            <section class="navy-week-assets" aria-label="Navy assets">
                <h2>NAVY ASSETS &AMP; PERFORMERS</h2>
                <ul>
                    @foreach ($navyAssets as $asset)
                        <li>{{ $asset }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($dailySchedule !== [])
            <section class="navy-week-schedule" aria-label="Daily schedule">
                <h2>SCHEDULE</h2>
                @foreach ($dailySchedule as $day)
                    <div class="schedule-day">
                        @isset($day['date'])<h3>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('l, F j') }}</h3>@endisset
                        <ul>
                            @foreach (($day['items'] ?? []) as $item)
                                <li>
                                    <strong>{{ $item['title'] ?? '' }}</strong>
                                    @isset($item['venue']) — {{ $item['venue'] }}@endisset
                                    @isset($item['description'])<span class="desc"> {{ $item['description'] }}</span>@endisset
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($keyVenues !== [])
            <section class="navy-week-venues" aria-label="Key venues">
                <h2>KEY VENUES</h2>
                <ul>
                    @foreach ($keyVenues as $venue)
                        <li>{{ $venue }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($event->parking_notes)
            <section class="navy-week-parking" aria-label="Parking">
                <h2>PARKING</h2>
                <p>{{ $event->parking_notes }}</p>
            </section>
        @endif

        @if ($event->cost_summary)
            <section class="navy-week-cost" aria-label="Cost">
                <h2>COST</h2>
                <p>{{ $event->cost_summary }}</p>
            </section>
        @endif

        @if ($event->faqs->isNotEmpty())
            <section class="navy-week-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($event->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif
        @include('partials.trust.editorial-policy')
    </main>
@endsection
