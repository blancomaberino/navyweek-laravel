@extends('layouts.base')

{{-- /navy-bases/{country}/ — every installation in one host country, grouped by
     base type, with a zoomed world map, host-nation context panel and FAQs.
     Ported markup-for-markup from the legacy src/page-views/NavyBasesCountry.tsx;
     styles in resources/css/families/bases.css. --}}
@php
    use App\Domain\Pillars\Support\BaseMapSvg;
    use App\Domain\Publishing\Support\PagePaths;

    $basesRoot = PagePaths::root('bases');
    $overseasPath = PagePaths::child('bases', 'overseas');
    $chevronLeft = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-chevron-left" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>';
    $arrowRight = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-chevron-down" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
    $pins = BaseMapSvg::pins($countryBases);
@endphp

@section('content')
    <main class="base-hub base-region-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $basesRoot }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $overseasPath }}">Overseas</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $regionName }}</span>
        </nav>

        <a class="base-hub-back" href="{{ $overseasPath }}">{!! $chevronLeft !!} All Overseas Bases</a>

        <div class="base-eyebrow">// {{ $countryIso2 }} · {{ $commandLabel }}</div>
        <h1>{{ $page->h1 ?? $page->title }}</h1>
        {{-- Legacy intro: derived from the installation count and the combatant
             command (NavyBasesCountry.tsx L203-208). --}}
        <p class="base-hub-intro">
            @if ($baseCount === 1)
                One major U.S. Navy installation is catalogued in {{ $regionName }}, operating under
                {{ $commandLabel }}.
            @else
                {{ $baseCount }} U.S. Navy installations are catalogued in {{ $regionName }}, all operating under
                {{ $commandLabel }}.
            @endif
            @if ($isUsTerritory)
                This entry covers a U.S. territory; the host nation is the United States.
            @endif
        </p>

        @if ($pins !== [])
            <section class="base-country-map" aria-label="{{ $regionName }} map">
                <div>
                    {!! BaseMapSvg::worldMap(
                        $pins,
                        BaseMapSvg::viewportForPins($pins, $page->slug),
                        null,
                        'Map showing '.$baseCount.' U.S. Navy '.($baseCount === 1 ? 'installation' : 'installations').' in '.$regionName,
                    ) !!}
                </div>
            </section>
        @endif

        <div class="base-hub-groups">
            @foreach ($grouped as $typeLabel => $bases)
                <section aria-label="{{ $typeLabel }} in {{ $regionName }}">
                    <h2>{{ $typeLabel }} ({{ $bases->count() }})</h2>
                    <div class="base-type-grid">
                        @foreach ($bases as $base)
                            <a href="{{ PagePaths::child('bases', $base->slug) }}">
                                <div class="base-type-card-kind">{{ mb_strtoupper($base->type->label()) }}</div>
                                <div class="base-type-card-name">{{ $base->name }}</div>
                                <div class="base-type-card-meta">{{ $base->city }}, {{ $base->country }} ·
                                    Established {{ $base->established }}</div>
                                <div class="base-type-card-tagline">{{ $base->hero_tagline }}</div>
                                <div class="base-type-card-cta">View Details {!! $arrowRight !!}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <section class="base-country-host" aria-label="Host nation context">
            <h2>HOST NATION CONTEXT</h2>
            <dl>
                <div><dt>Country</dt><dd>{{ $regionName }} ({{ $countryIso2 }})</dd></div>
                <div><dt>Combatant Command</dt><dd>{{ $commandLabel }}</dd></div>
                @if ($isUsTerritory)
                    <div><dt>Status</dt><dd>U.S. Territory</dd></div>
                @endif
            </dl>
            <p>Each base page below includes its specific Status of Forces Agreement (SOFA) framework, command
                sponsorship requirements, and host-nation entry guidance.</p>
        </section>

        @if ($page->faqs->isNotEmpty())
            <section class="base-hub-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <div class="nw-faq-list">
                    @foreach ($page->faqs as $faq)
                        <details class="nw-faq" @if ($loop->first) open @endif>
                            <summary>
                                <h3>{{ $faq->question }}</h3>
                                {!! $chevronDown !!}
                            </summary>
                            <div class="nw-faq-a">{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
@endsection
