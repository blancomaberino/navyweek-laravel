@extends('layouts.base')

{{-- /navy-bases/overseas/ — the forward-deployed rollup: headline stats, the world
     map with its combatant-command filter, browse-by-country grouped by command,
     the A–Z list and the FAQs. Ported markup-for-markup from the legacy
     src/page-views/NavyBasesOverseas.tsx; styles in resources/css/families/bases.css. --}}
@php
    use App\Domain\Publishing\Content\InlineSpans;
    use App\Domain\Pillars\Support\BaseMapSvg;
    use App\Domain\Publishing\Support\PagePaths;

    $basesRoot = PagePaths::root('bases');
    $arrow = static fn (int $size): string => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'"'
        .' height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        .' stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true">'
        .'<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-chevron-down" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
    // NavyBasesOverseas.tsx L202-204 renders a fixed editorial lead; it is
    // CMS-editable here (pages.body_blocks), falling back to the meta description.
    // Read through InlineSpans, NOT `['text']`: a lead the CMS has formatted is
    // stored as `spans`, and indexing `text` directly would silently drop it back
    // to the meta description the moment an editor bolds a word.
    $lead = InlineSpans::plainText($page->body_blocks[0] ?? []) ?: $page->meta_description;
    $pins = BaseMapSvg::pins($allBases);
@endphp

@section('content')
    <main class="base-hub base-hub-root base-overseas-hub">
        <section class="base-hub-intro-section">
            @include('partials.trust.back-link')

            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $basesRoot }}">Navy Bases</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Overseas</span>
            </nav>

            @include('partials.trust.disclosure')

            <div class="base-eyebrow">// Forward-Deployed U.S. Navy</div>
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="base-hub-lead">{{ $lead }}</p>

            @include('partials.trust.byline')

            <div class="base-stat-row">
                @foreach ([[$overseasTotal, 'Overseas Bases'], [count($countries), 'Host Nations'], [count($byRegion), 'Combatant Commands']] as [$value, $label])
                    <div>
                        <div class="base-stat-value">{{ $value }}</div>
                        <div class="base-stat-label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="base-hub-map" aria-label="World map">
            <h2>WORLD MAP</h2>
            <div class="base-region-filter" role="group" aria-label="Filter overseas bases by combatant command region">
                <button type="button" aria-pressed="true" data-region-filter="" title="All combatant commands">All
                    Regions</button>
                @foreach ($regionOptions as $value => $full)
                    <button type="button" aria-pressed="false" data-region-filter="{{ $value }}"
                            title="{{ $full }}">{{ $value }}</button>
                @endforeach
            </div>
            <div class="base-world-map">{!! BaseMapSvg::worldMap($pins) !!}</div>
            <div class="base-map-showing" data-region-count>Showing {{ $overseasTotal }} of {{ $overseasTotal }}
                overseas {{ $overseasTotal === 1 ? 'base' : 'bases' }}</div>
        </section>

        <section class="base-hub-section" aria-label="Browse by country">
            <h2>BROWSE BY COUNTRY</h2>
            <div class="base-command-groups">
                @foreach ($byRegion as $group)
                    <div data-command-region="{{ $group['value'] }}">
                        <div class="base-command-label">{{ $group['label'] }} ({{ count($group['countries']) }})</div>
                        <div class="base-region-grid">
                            @foreach ($group['countries'] as $country)
                                <a href="{{ PagePaths::child('bases', $country['slug']) }}">
                                    <span><span class="base-region-abbr">{{ $country['iso2'] }}</span>{{ $country['name'] }}@if ($country['territory'])<span class="base-region-terr">· U.S. TERR.</span>@endif</span>
                                    <span class="base-region-count">{{ $country['count'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="base-hub-section base-hub-all" aria-label="All overseas bases A to Z">
            <div class="base-list-head">
                <h2>ALL OVERSEAS BASES (A–Z)</h2>
                <span class="base-list-count" data-base-count>{{ $overseasTotal }} of {{ $overseasTotal }}</span>
            </div>
            <ul class="base-az-list base-az-list-overseas">
                @foreach ($allBases as $base)
                    <li data-base-region="{{ $base->region?->value }}">
                        <a href="{{ PagePaths::child('bases', $base->slug) }}">
                            <span class="base-az-name">{{ $base->name }}</span>
                            <span class="base-az-type">{{ mb_strtoupper($base->type->label()) }}</span>
                            <span class="base-az-region">{{ $base->region?->value }}</span>
                            <span class="base-az-place">{{ mb_strtoupper((string) $base->country) }}{!! $arrow(12) !!}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="base-az-empty" hidden>No overseas bases match the selected region filter.</div>
        </section>

        <section class="base-hub-section base-hub-faqs" aria-label="Frequently asked questions">
            <h2>FREQUENTLY ASKED QUESTIONS</h2>
            @if ($page->faqs->isNotEmpty())
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
            @endif

            <div class="base-overseas-footer-nav">
                <a href="{{ $basesRoot }}">&larr; All Navy Bases ({{ $basesTotal }})</a>
            </div>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection

@push('scripts')
    {{-- Combatant-command filter — the legacy hub filters the map, the country list
         and the A–Z list client-side and mirrors the choice into ?region=
         (NavyBasesOverseas.tsx L104-138). --}}
    <script>
        (function () {
            var row = document.querySelector('.base-region-filter');
            if (!row) return;
            var items = Array.prototype.slice.call(document.querySelectorAll('.base-az-list > li'));
            var groups = Array.prototype.slice.call(document.querySelectorAll('.base-command-groups > div'));
            var pins = Array.prototype.slice.call(document.querySelectorAll('.base-world-map [data-pin-region]'));
            var counter = document.querySelector('[data-base-count]');
            var showing = document.querySelector('[data-region-count]');
            var empty = document.querySelector('.base-az-empty');
            var list = document.querySelector('.base-az-list');
            var total = items.length;

            function apply(region) {
                var shown = 0;
                items.forEach(function (li) {
                    var on = region === '' || li.dataset.baseRegion === region;
                    li.hidden = !on;
                    if (on) shown++;
                });
                groups.forEach(function (group) {
                    group.hidden = region !== '' && group.dataset.commandRegion !== region;
                });
                pins.forEach(function (pin) {
                    pin.style.display = region === '' || pin.dataset.pinRegion === region ? '' : 'none';
                });
                row.querySelectorAll('button').forEach(function (b) {
                    b.setAttribute('aria-pressed', String(b.dataset.regionFilter === region));
                });
                if (counter) counter.textContent = shown + ' of ' + total;
                if (showing) showing.textContent = 'Showing ' + shown + ' of ' + total + ' overseas ' + (shown === 1 ? 'base' : 'bases');
                if (empty) empty.hidden = shown !== 0;
                if (list) list.hidden = shown === 0;
                var url = new URL(window.location.href);
                if (region === '') url.searchParams.delete('region'); else url.searchParams.set('region', region);
                window.history.replaceState(null, '', url.pathname + (url.search || ''));
            }

            row.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-region-filter]');
                if (button) apply(button.dataset.regionFilter);
            });

            var initial = new URL(window.location.href).searchParams.get('region');
            if (initial && row.querySelector('[data-region-filter="' + initial.toUpperCase() + '"]')) {
                apply(initial.toUpperCase());
            }
        })();
    </script>
@endpush
