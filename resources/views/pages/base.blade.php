@extends('layouts.base')

{{-- Single naval-base page (/navy-bases/{slug}/). Ported from NavyBaseDetail:
     header → overview → key facts → history → major units → location → host-nation
     (overseas) → notable events → FAQs → sources. The head/JSON-LD is byte-locked
     by SeoHead + BasePageSchema; this body is a clean semantic rebuild. --}}
@php
    /** @var \App\Domain\Pillars\Models\Base $base */
    // Split a prose field into paragraphs on blank lines (used by three sections).
    // Filter on emptiness only — a paragraph that is literally "0" must survive.
    $paragraphs = fn (?string $text): array => array_values(array_filter(
        preg_split('/\n\n+/', trim((string) $text)),
        static fn (string $p): bool => $p !== '',
    ));
    $overview = $paragraphs($base->overview);
    $history = $paragraphs($base->history);
    $hostNationContext = $paragraphs($base->host_nation_context);
    $regionLabel = $base->isOverseas()
        ? trim("{$base->city}, {$base->country}", ', ')
        : trim("{$base->city}, {$base->state_name}", ', ');
@endphp

@section('content')
    <main class="base-detail">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/navy-bases/">Navy Bases</a>
            <span aria-hidden="true">/</span>
            @if ($base->isOverseas())
                <a href="/navy-bases/overseas/">Overseas</a>
                <span aria-hidden="true">/</span>
                <a href="/navy-bases/{{ $base->country_slug }}/">{{ $base->country }}</a>
            @else
                <a href="/navy-bases/{{ $base->state }}/">{{ $base->state_name }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $base->name }}</span>
        </nav>

        <header class="base-hero">
            <p class="eyebrow">// {{ $base->type->label() }} · {{ $regionLabel }}</p>
            <h1>{{ $base->h1 }}</h1>
            @if (! empty($base->aka))
                <p class="aka">Also known as: {{ implode(', ', $base->aka) }}</p>
            @endif
            @if ($base->hero_tagline)
                <p class="hero-tagline">{{ $base->hero_tagline }}</p>
            @endif
        </header>

        @if ($base->isOverseas())
            <p class="advisory" role="note">
                This is an overseas U.S. Navy installation. Access, currency, language, and
                travel requirements differ from CONUS bases — see host-nation details below.
            </p>
        @endif

        <section class="quick-facts" aria-label="Quick facts">
            <dl>
                <div><dt>Established</dt><dd>{{ $base->established }}</dd></div>
                <div><dt>Type</dt><dd>{{ $base->type->label() }}</dd></div>
                <div><dt>Location</dt><dd>{{ $regionLabel }}</dd></div>
                @if ($base->region)
                    <div><dt>Command</dt><dd>{{ $base->region->label() }}</dd></div>
                @endif
                @if ($base->timezone)
                    <div><dt>Time zone</dt><dd>{{ $base->timezone }}</dd></div>
                @endif
                @if ($base->personnel_count)
                    <div><dt>Personnel</dt><dd>{{ $base->personnel_count }}</dd></div>
                @endif
                @if ($base->area_acres)
                    <div><dt>Area</dt><dd>{{ $base->area_acres }} acres</dd></div>
                @endif
            </dl>
        </section>

        @if ($overview !== [])
            <section class="base-overview" aria-label="Overview">
                <h2>Overview</h2>
                @foreach ($overview as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if (! empty($base->key_facts))
            <section class="base-key-facts" aria-label="Key facts">
                <h2>Key Facts</h2>
                <dl>
                    @foreach ($base->key_facts as $fact)
                        <div><dt>{{ $fact['label'] ?? '' }}</dt><dd>{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($history !== [])
            <section class="base-history" aria-label="History">
                <h2>History</h2>
                @foreach ($history as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if (! empty($base->major_units))
            <section class="base-units" aria-label="Major commands and tenant units">
                <h2>Major Commands &amp; Tenant Units</h2>
                <ul>
                    @foreach ($base->major_units as $unit)
                        <li>{{ $unit }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="base-location" aria-label="Location and geography">
            <h2>Location &amp; Geography</h2>
            <p class="coordinates">{{ $base->lat }}, {{ $base->lng }}</p>
            <p>
                <a href="https://www.google.com/maps/search/?api=1&amp;query={{ $base->lat }},{{ $base->lng }}"
                   rel="noopener noreferrer" target="_blank">View on Google Maps</a>
            </p>
            @if ($base->location_context)
                <p>{{ $base->location_context }}</p>
            @endif
        </section>

        @if ($base->isOverseas() && $hostNationContext !== [])
            <section class="base-host-nation" aria-label="Host nation context">
                <h2>Host Nation Context</h2>
                <dl>
                    @if ($base->host_nation)
                        <div><dt>Host nation</dt><dd>{{ $base->host_nation }}</dd></div>
                    @endif
                    @if ($base->local_currency)
                        <div><dt>Currency</dt><dd>{{ $base->local_currency }}</dd></div>
                    @endif
                    @if (! empty($base->local_language))
                        <div><dt>Language</dt><dd>{{ implode(', ', $base->local_language) }}</dd></div>
                    @endif
                    @if ($base->sofa_status)
                        <div><dt>SOFA status</dt><dd>{{ $base->sofa_status }}</dd></div>
                    @endif
                </dl>
                @foreach ($hostNationContext as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        @if (! empty($base->notable_events))
            <section class="base-events" aria-label="Notable events">
                <h2>Notable Events</h2>
                <ul>
                    @foreach ($base->notable_events as $event)
                        <li>
                            @isset($event['year'])<strong>{{ $event['year'] }}</strong> — @endisset
                            @isset($event['title'])<strong>{{ $event['title'] }}</strong>@endisset
                            @isset($event['description']) {{ $event['description'] }}@endisset
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($base->faqs->isNotEmpty())
            <section class="base-faqs" aria-label="Frequently asked questions">
                <h2>Frequently Asked Questions</h2>
                <dl>
                    @foreach ($base->faqs as $faq)
                        <dt>{{ $faq->question }}</dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($base->sources->isNotEmpty())
            <footer class="base-sources">
                <h2>Sources</h2>
                <ul>
                    @foreach ($base->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="last-updated">Last updated {{ $base->last_updated?->toDateString() }}</p>
            </footer>
        @endif
    </main>
@endsection
