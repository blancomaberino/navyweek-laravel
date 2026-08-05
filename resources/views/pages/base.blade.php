@extends('layouts.base')

{{-- Single naval-base page (/navy-bases/{slug}/). Ported markup-for-markup from
     the legacy src/page-views/NavyBaseDetail.tsx: back-link → breadcrumb → hero →
     (overseas advisory) → quick facts → overview → key facts → history → major
     units → location & geography (map + address panel) → host-nation (overseas) →
     notable events → nearby bases → FAQs → sources → footer nav. The head/JSON-LD
     is byte-locked by SeoHead + BasePageSchema. Styles live in
     resources/css/families/bases.css. --}}
@php
    use App\Domain\Pillars\Support\BaseMapSvg;
    use App\Domain\Publishing\Support\PagePaths;

    /** @var \App\Domain\Pillars\Models\Base $base */
    $basesRoot = PagePaths::root('bases');
    $overseasPath = PagePaths::child('bases', 'overseas');
    $overseas = $base->isOverseas();
    $regionPath = PagePaths::child('bases', (string) ($overseas ? $base->country_slug : $base->state));

    // Split a prose field into paragraphs on blank lines (used by three sections).
    // Filter on emptiness only — a paragraph that is literally "0" must survive.
    $paragraphs = fn (?string $text): array => array_values(array_filter(
        preg_split('/\n\n+/', trim((string) $text)),
        static fn (string $p): bool => $p !== '',
    ));
    $overview = $paragraphs($base->overview);
    $history = $paragraphs($base->history);
    $hostNationContext = $paragraphs($base->host_nation_context);

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size, string $stroke = 'currentColor', string $style = ''): string {
        $paths = [
            'globe' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>',
            'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle>',
            'external-link' => '<path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"></path>',
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="'.$stroke.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide lucide-'.$name.'" aria-hidden="true"'.($style === '' ? '' : ' style="'.$style.'"')
            .'>'.$paths[$name].'</svg>';
    };

    // Quick Facts vary by location type (NavyBaseDetail.tsx L116-138). Values are
    // taken verbatim, including the em-dash placeholder for a missing column.
    $coordinates = number_format((float) $base->lat, 3).'°, '.number_format((float) $base->lng, 3).'°';
    $quickFacts = $overseas
        ? [
            ['Established', (string) $base->established],
            ['Type', $base->type->label()],
            ['Location', $base->city.', '.$base->country],
            ['Country', $base->country ?: '—'],
            ['Region', $base->region?->value ?: '—'],
            ['Timezone', $base->timezone ?: '—'],
            ['Coordinates', $coordinates],
            ['Major Commands', (string) count($base->major_units)],
            ['Area', $base->area_acres ?: '—'],
            ['Personnel', $base->personnel_count ?: '—'],
        ]
        : [
            ['Established', (string) $base->established],
            ['Type', $base->type->label()],
            ['Location', $base->city.', '.$base->state_abbr],
            ['State', $base->state_name ?: '—'],
            ['Coordinates', $coordinates],
            ['Major Commands', (string) count($base->major_units)],
            ['Area', $base->area_acres ?: '—'],
            ['Personnel', $base->personnel_count ?: '—'],
        ];

    // Decimal degrees rendered as hemisphere pairs (NavyBaseDetail.tsx L74-82).
    $formatLat = static fn (float $v): string => number_format(abs($v), 4).'° '.($v >= 0 ? 'N' : 'S');
    $formatLng = static fn (float $v): string => number_format(abs($v), 4).'° '.($v >= 0 ? 'E' : 'W');
@endphp

@section('content')
    <main class="base-detail">
        {{-- The legacy detail page always renders the reference back-link, so it is
             emitted here rather than through the CMS-flagged shared partial. --}}
        <div class="reference-backlink">
            <a href="{{ PagePaths::root('navy_reference') }}">&larr; Navy Reference</a>
        </div>

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $basesRoot }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            @if ($overseas)
                <a href="{{ $overseasPath }}">Overseas</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $regionPath }}">{{ $base->country }}</a>
            @else
                <a href="{{ $regionPath }}">{{ $base->state_name }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $base->name }}</span>
        </nav>

        <header class="base-hero">
            <div class="base-eyebrow">// {{ $base->type->label() }} ·
                {{ $overseas ? $base->country_iso2.' · '.$base->region?->value : $base->state_abbr }}</div>
            {{-- The legacy detail page renders the installation name uppercased in the
                 markup (not via CSS), so the h1 matches the live site byte-for-byte. --}}
            <h1>{{ mb_strtoupper((string) ($page->h1 ?? $base->h1)) }}</h1>
            @if (! empty($base->aka))
                <div class="base-aka">also known as {{ implode(' · ', $base->aka) }}</div>
            @endif
            <p class="base-tagline">{{ $base->hero_tagline }}</p>
        </header>

        @if ($overseas)
            <div class="base-advisory" role="note" aria-label="Overseas base advisory">
                {!! $icon('globe', 18, 'var(--gold)', 'margin-top:2px;flex-shrink:0') !!}
                <div>
                    <strong>Overseas installation.</strong>
                    This is a forward-deployed U.S. Navy base in {{ $base->country }}, operating under the
                    host-nation Status of Forces framework summarized below. Travel, base access, command
                    sponsorship, and entry requirements are subject to current orders and host-nation policy —
                    always verify with your command and the installation's official public-affairs office before
                    traveling or visiting.
                </div>
            </div>
        @endif

        <section class="base-quick-facts" aria-label="Quick facts">
            @foreach ($quickFacts as [$label, $value])
                <div class="base-quick-fact">
                    <div class="base-quick-fact-label">{{ $label }}</div>
                    <div class="base-quick-fact-value">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <section class="base-prose" aria-label="Overview">
            <h2>OVERVIEW</h2>
            @foreach ($overview as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </section>

        <section class="base-key-facts" aria-label="Key facts list">
            <h2>KEY FACTS</h2>
            <ul>
                @foreach ($base->key_facts as $fact)
                    <li>
                        <span class="base-key-fact-label">{{ $fact['label'] ?? '' }}</span>
                        <span class="base-key-fact-value">{{ $fact['value'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="base-prose" aria-label="History">
            <h2>HISTORY</h2>
            @foreach ($history as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </section>

        <section class="base-units" aria-label="Major commands and tenant units">
            <h2>MAJOR COMMANDS &amp; TENANT UNITS</h2>
            <ul>
                @foreach ($base->major_units as $unit)
                    <li>{{ $unit }}</li>
                @endforeach
            </ul>
        </section>

        <section class="base-location" aria-label="Location and geography">
            <h2>LOCATION &amp; GEOGRAPHY</h2>
            <div class="base-map">
                <div class="base-map-caption">
                    {{ $base->name }} — Highlighted on {{ $overseas ? 'world map' : 'U.S. map' }}
                </div>
                @if ($overseas)
                    {!! BaseMapSvg::worldMap(
                        BaseMapSvg::pins([$base]),
                        BaseMapSvg::viewportForBase($base),
                        $base->slug,
                        "Map showing the location of {$base->name} in {$base->country}",
                    ) !!}
                @else
                    {!! BaseMapSvg::usMap(BaseMapSvg::pins([$base]), $base->slug) !!}
                @endif
            </div>
            <div class="base-place">
                <div>
                    <div class="base-place-head">
                        {!! $icon('map-pin', 16, 'var(--gold)') !!}
                        <div class="base-place-label">Address</div>
                    </div>
                    <div class="base-place-address">
                        {{ $overseas ? $base->city.', '.$base->country : $base->city.', '.$base->state_name.' ('.$base->state_abbr.')' }}
                    </div>
                    <div class="base-place-coords">
                        {{ $formatLat((float) $base->lat) }}, {{ $formatLng((float) $base->lng) }}
                    </div>
                    <a class="base-place-link"
                       href="https://www.google.com/maps/search/?api=1&amp;query={{ $base->lat }},{{ $base->lng }}"
                       rel="noopener noreferrer" target="_blank">View on Google Maps {!! $icon('external-link', 11) !!}</a>
                </div>
                <div>
                    <div class="base-place-label">Region</div>
                    <div class="base-place-region">
                        @if ($overseas)
                            {{ $base->region?->label() }}<br>
                            <span>{{ $base->location_context ?: $base->city.', '.$base->country }}</span>
                        @else
                            {{ $base->city }} metropolitan area, {{ $base->state_name }}
                        @endif
                    </div>
                    <div class="base-place-more">
                        <a href="{{ $regionPath }}">More bases in
                            {{ $overseas ? $base->country : $base->state_name }} &rarr;</a>
                    </div>
                </div>
            </div>
        </section>

        @if ($overseas && $hostNationContext !== [])
            <section class="base-host-nation" aria-label="Host nation context">
                <h2>HOST NATION CONTEXT</h2>
                <div class="base-host-panel">
                    <dl>
                        @if ($base->host_nation)
                            <div><dt>Host Nation</dt><dd>{{ $base->host_nation }}</dd></div>
                        @endif
                        @if ($base->region)
                            <div><dt>Combatant Command</dt><dd>{{ $base->region->label() }}</dd></div>
                        @endif
                        @if ($base->timezone)
                            <div><dt>Timezone</dt><dd>{{ $base->timezone }}</dd></div>
                        @endif
                        @if ($base->local_currency)
                            <div><dt>Currency</dt><dd>{{ $base->local_currency }}</dd></div>
                        @endif
                        @if (! empty($base->local_language))
                            <div><dt>Languages</dt>
                                <dd>{{ implode(' · ', array_map('mb_strtoupper', $base->local_language)) }}</dd></div>
                        @endif
                        @if (! is_null($base->command_sponsorship_required))
                            <div><dt>Command Sponsorship</dt>
                                <dd>{{ $base->command_sponsorship_required ? 'Required for dependents' : 'Not required' }}</dd></div>
                        @endif
                        @if (! is_null($base->passport_required))
                            <div><dt>Passport</dt>
                                <dd>{{ $base->passport_required ? 'Required for entry' : 'Not required' }}</dd></div>
                        @endif
                    </dl>
                    @if ($base->sofa_status)
                        <div class="base-sofa">
                            <div class="base-sofa-label">Status of Forces Agreement</div>
                            <p>{{ $base->sofa_status }}</p>
                        </div>
                    @endif
                </div>
                @foreach ($hostNationContext as $paragraph)
                    <p class="base-host-para">{{ $paragraph }}</p>
                @endforeach
                <div class="base-host-warning">
                    ⚠ Always verify SOFA status, command sponsorship, and entry requirements with your command and
                    the installation's official public-affairs office before traveling.
                </div>
            </section>
        @endif

        @if (! empty($base->notable_events))
            <section class="base-events" aria-label="Notable events">
                <h2>NOTABLE EVENTS</h2>
                <ol>
                    @foreach ($base->notable_events as $event)
                        <li>
                            @isset($event['year'])
                                <div class="base-event-year">{{ $event['year'] }}</div>
                            @endisset
                            <div>
                                <div class="base-event-title">{{ $event['title'] ?? '' }}</div>
                                <div class="base-event-desc">{{ $event['description'] ?? '' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="base-nearby" aria-label="Nearby bases">
            <h2>NEARBY BASES</h2>
            @if ($nearby->isEmpty() && $otherInRegion->isEmpty())
                <p class="base-nearby-empty">
                    No other bases are catalogued near {{ $base->name }} yet. As more installations are added to the
                    directory, related bases in {{ $overseas ? $base->country : $base->state_name }} and adjoining
                    regions will appear here.
                    <a href="{{ $overseas ? $overseasPath : $basesRoot }}">Browse the
                        {{ $overseas ? 'overseas' : 'full' }} directory &rarr;</a>
                </p>
            @else
                <div class="base-nearby-grid">
                    @foreach ($nearby as $item)
                        <a href="{{ PagePaths::child('bases', $item->slug) }}">
                            <div class="base-nearby-label">NEARBY ·
                                {{ $item->isOverseas() ? $item->country_iso2 : $item->state_abbr }}</div>
                            <div class="base-nearby-name">{{ $item->name }}</div>
                        </a>
                    @endforeach
                    @foreach ($otherInRegion as $item)
                        <a href="{{ PagePaths::child('bases', $item->slug) }}">
                            <div class="base-nearby-label">ALSO IN
                                {{ mb_strtoupper((string) ($overseas ? $base->country : $base->state_name)) }}</div>
                            <div class="base-nearby-name">{{ $item->name }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($base->faqs->isNotEmpty())
            <section class="base-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <div class="nw-faq-list">
                    @foreach ($base->faqs as $faq)
                        <details class="nw-faq" @if ($loop->first) open @endif>
                            <summary>
                                <h3>{{ $faq->question }}</h3>
                                {!! $icon('chevron-down', 18, 'currentColor') !!}
                            </summary>
                            <div class="nw-faq-a">{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($base->sources->isNotEmpty())
            <section class="base-sources" aria-label="Sources">
                <h2>SOURCES</h2>
                <ul>
                    @foreach ($base->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank">{{ $source->label }}
                                    {!! $icon('external-link', 11) !!}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="base-last-updated">Last updated {{ $base->last_updated?->toDateString() }}</div>
            </section>
        @endif

        <div class="base-footer-nav">
            <a href="{{ $regionPath }}">{!! $icon('chevron-left', 14) !!} All Bases in
                {{ $overseas ? $base->country : $base->state_name }}</a>
            <a href="{{ $overseas ? $overseasPath : $basesRoot }}">{{ $overseas ? 'Overseas Directory' : 'Full Directory' }}
                {!! $icon('arrow-right', 14) !!}</a>
        </div>
    </main>
@endsection
