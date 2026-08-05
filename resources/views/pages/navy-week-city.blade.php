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

        @include('partials.trust.key-facts', ['keyFacts' => filled($event->quick_facts ?? null) ? [
            'title' => 'Navy Week '.$event->city.' '.\Illuminate\Support\Carbon::parse($event->start_date)->format('Y').' — Key Facts',
            'facts' => $event->quick_facts,
        ] : [
            'title' => 'Navy Week '.$event->city.' '.\Illuminate\Support\Carbon::parse($event->start_date)->format('Y').' — Key Facts',
            'facts' => array_values(array_filter([
                ['label' => 'Dates', 'value' => \Illuminate\Support\Carbon::parse($event->start_date)->format('F j').' – '.\Illuminate\Support\Carbon::parse($event->end_date)->format('F j, Y')],
                ['label' => 'Host city', 'value' => trim($event->city.', '.($event->state ?? ''), ', ')],
                $event->anchor_event ? ['label' => 'Anchor event', 'value' => $event->anchor_event] : null,
                ['label' => 'Cost', 'value' => $event->cost_summary ?: 'Free — every Navy Week event is open to the public at no charge'],
            ])),
            'source' => ['label' => 'Navy Office of Community Outreach (outreach.navy.mil)', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
        ]])

        {{-- MISSION DOSSIER — the anchor event + what to expect, matching the live guide. --}}
        <section class="navy-week-dossier" aria-label="Mission dossier">
            <h2>MISSION DOSSIER</h2>
            @if ($event->anchor_event || $event->anchor_event_detail)
                <h3>Anchor Event</h3>
                @if ($event->anchor_event)<p><strong>{{ $event->anchor_event }}</strong></p>@endif
                @if ($event->anchor_event_detail)<p>{{ $event->anchor_event_detail }}</p>@endif
            @endif
            @if (filled($event->military_context) || $event->first_time_note)
                <h3>What to Expect</h3>
                @if ($event->first_time_note)<p>{{ $event->first_time_note }}</p>@endif
                @foreach ((array) $event->military_context as $contextPara)
                    <p>{{ $contextPara }}</p>
                @endforeach
            @endif
        </section>

        @php
            $scheduleDays = collect($dailySchedule)->keyBy(fn ($d) => \Illuminate\Support\Carbon::parse($d['date'] ?? null)->toDateString());
            $window = \Illuminate\Support\Carbon::parse($event->start_date)
                ->daysUntil(\Illuminate\Support\Carbon::parse($event->end_date));
        @endphp
        @if (true)
            <section class="navy-week-schedule" aria-label="Daily schedule">
                <h2>DAILY SCHEDULE</h2>
                @foreach ($window as $windowDay)
                    @php($day = $scheduleDays->get($windowDay->toDateString()))
                    <div class="schedule-day">
                        <h3>{{ $windowDay->format('l, F j, Y') }}</h3>
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

        <section class="navy-week-venues" aria-label="Venues and map">
            <h2>VENUES &amp; MAP</h2>
            @php($venueList = filled($event->venues) ? $event->venues : $keyVenues)
            @foreach ($venueList as $venue)
                <h3>{{ is_array($venue) ? ($venue['name'] ?? '') : $venue }}</h3>
                @if (is_array($venue))
                    @if (! empty($venue['address']))<p>{{ $venue['address'] }}</p>@endif
                    @if (! empty($venue['notes']))<p>{{ $venue['notes'] }}</p>@endif
                @endif
            @endforeach
            @if ($event->parking_notes)
                <h3>Parking &amp; Getting There</h3>
                <p>{{ $event->parking_notes }}</p>
            @endif
            @if ($event->cost_summary)
                <h3>Cost</h3>
                <p>{{ $event->cost_summary }}</p>
            @endif
            @if ($event->navco_url)
                <h3>Official Sources</h3>
                <p><a href="{{ $event->navco_url }}" rel="noopener noreferrer" target="_blank">Navy Office of Community Outreach</a></p>
            @endif
            @if (filled($event->military_context))
                <h3>Local Military Context</h3>
                @foreach ((array) $event->military_context as $contextPara)
                    <p>{{ $contextPara }}</p>
                @endforeach
            @endif
            @if ($navyAssets !== [])
                <h3>Navy Assets &amp; Units</h3>
                <ul>
                    @foreach ($navyAssets as $asset)
                        <li>{{ is_array($asset) ? ($asset['name'] ?? '') : $asset }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($event->faqs->isNotEmpty())
            <section class="navy-week-faqs" aria-label="Frequently asked questions">
                <h2>{{ mb_strtoupper('FAQ — Navy Week '.$event->city) }}</h2>
                <dl>
                    @foreach ($event->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="navy-week-more" aria-label="More Navy Week cities">
            <h2>MORE NAVY WEEK 2026 CITIES</h2>
            <ul>
                @foreach ($otherCities as $other)
                    <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::child('navy_week_cities', $other->slug) }}">{{ $other->city }}</a></li>
                @endforeach
            </ul>
        </section>

        <section class="navy-week-reference" aria-label="U.S. Navy reference">
            <h2>U.S. NAVY REFERENCE</h2>
            <ul>
                <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::root('bases') }}">Navy Bases</a></li>
                <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::root('ranks') }}">Navy Ranks</a></li>
                <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::root('ratings') }}">Navy Ratings</a></li>
                <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::root('designators') }}">Officer Designators</a></li>
            </ul>
        </section>

    </main>
@endsection
