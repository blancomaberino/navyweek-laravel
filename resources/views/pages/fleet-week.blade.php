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

        @if (! empty($week->quick_facts))
            <section class="quick-facts" aria-label="Quick facts">
                <dl>
                    @foreach ($week->quick_facts as $fact)
                        <div><dt>{{ $fact['label'] ?? '' }}</dt><dd>{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($week->official_url)
            <p class="official-link">
                <a href="{{ $week->official_url }}" rel="noopener noreferrer nofollow" target="_blank">
                    {{ $week->official_site_label ?: 'Official site' }}
                </a>
            </p>
        @endif

        @if ($history !== [])
            <section class="fleet-week-history" aria-label="History">
                <h2>History</h2>
                @foreach ($history as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if ($week->faqs->isNotEmpty())
            <section class="fleet-week-faqs" aria-label="Frequently asked questions">
                <h2>Frequently Asked Questions</h2>
                <dl>
                    @foreach ($week->faqs as $faq)
                        <dt>{{ $faq->question }}</dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($week->sources->isNotEmpty())
            <footer class="fleet-week-sources">
                <h2>Sources</h2>
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
    </main>
@endsection
