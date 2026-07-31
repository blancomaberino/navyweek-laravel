@extends('layouts.base')

{{-- Fleet-week hub (/fleetweek/). The city directory + JSON-LD ItemList + FAQPage
     (hub FAQs seeded on the page). Head/JSON-LD byte-locked by FleetWeekPageSchema::buildHub. --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\FleetWeek> $weeks */
@endphp

@section('content')
    <main class="fleet-week-hub">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Fleet Week</span>
        </nav>

        <header class="hub-hero">
            <p class="eyebrow">// U.S. Navy Reference</p>
            <h1>U.S. Fleet Week Guide, City by City</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        <section class="hub-cities" aria-label="Fleet Week cities">
            <h2>Fleet Week Cities <span class="count">({{ $weeks->count() }})</span></h2>
            @if ($weeks->isEmpty())
                <p class="empty-state">City guides are coming soon.</p>
            @else
                <ul class="city-list">
                    @foreach ($weeks as $week)
                        <li class="city-card">
                            <a href="/fleetweek/{{ $week->slug }}/">
                                <span class="city-name">{{ $week->branding_name }} {{ $week->year }}</span>
                                <span class="city-loc">{{ $week->city }}, {{ $week->state_abbr }}</span>
                                @if ($week->status_label)
                                    <span class="city-status">{{ $week->status_label }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($page->faqs->isNotEmpty())
            <section class="hub-faqs" aria-label="Frequently asked questions">
                <h2>Frequently Asked Questions</h2>
                <dl>
                    @foreach ($page->faqs as $faq)
                        <dt>{{ $faq->question }}</dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif
    </main>
@endsection
