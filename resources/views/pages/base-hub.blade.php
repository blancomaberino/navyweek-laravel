@extends('layouts.base')

{{-- /navy-bases/ — the directory root: key facts, headline stats, browse-by-state,
     the overseas promo, the filterable A–Z list and the FAQs. Ported
     markup-for-markup from the legacy src/page-views/NavyBasesHub.tsx; styles in
     resources/css/families/bases.css. --}}
@php
    use App\Domain\Publishing\Content\InlineSpans;
    use App\Domain\Publishing\Support\PagePaths;

    $basesRoot = PagePaths::root('bases');
    $overseasPath = PagePaths::child('bases', 'overseas');
    $arrow = static fn (int $size): string => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'"'
        .' height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        .' stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true">'
        .'<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"'
        .' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        .' class="lucide lucide-chevron-down" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
    // NavyBasesHub.tsx L152-154 renders a fixed editorial lead; it is CMS-editable
    // here (pages.body_blocks), falling back to the meta description.
    // Read through InlineSpans, NOT `['text']`: a lead the CMS has formatted is
    // stored as `spans`, and indexing `text` directly would silently drop it back
    // to the meta description the moment an editor bolds a word.
    $lead = InlineSpans::plainText((array) ($page->body_blocks[0] ?? [])) ?: $page->meta_description;
@endphp

@section('content')
    <main class="base-hub base-hub-root">
        <section class="base-hub-intro-section">
            @include('partials.trust.back-link')

            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Navy Bases</span>
            </nav>

            @include('partials.trust.disclosure')

            <div class="base-eyebrow">// U.S. Navy Installations</div>
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="base-hub-lead">{{ $lead }}</p>

            @include('partials.trust.byline')
            @include('partials.trust.key-facts')

            <div class="base-stat-row">
                @foreach ([[$basesTotal, 'Bases Catalogued'], [count($states), 'States Represented'], [$overseasTotal, 'Overseas Bases'], [count($baseTypes), 'Installation Types']] as [$value, $label])
                    <div>
                        <div class="base-stat-value">{{ $value }}</div>
                        <div class="base-stat-label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="base-hub-section" aria-label="Browse by state">
            <h2>BROWSE BY STATE</h2>
            <div class="base-region-grid">
                @foreach ($states as $state)
                    <a href="{{ PagePaths::child('bases', $state['slug']) }}">
                        <span><span class="base-region-abbr">{{ $state['abbr'] }}</span>{{ $state['name'] }}</span>
                        <span class="base-region-count">{{ $state['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="base-hub-section" aria-label="Overseas bases">
            <h2>OVERSEAS BASES</h2>
            <a class="base-overseas-promo" href="{{ $overseasPath }}">
                <div class="base-overseas-promo-head">
                    <div>
                        <div class="base-overseas-promo-eyebrow">// Forward-Deployed Installations</div>
                        <div class="base-overseas-promo-title">NAVY BASES OVERSEAS ({{ $overseasTotal }})</div>
                        <div class="base-overseas-promo-desc">Forward-deployed U.S. Navy installations across
                            {{ count($countries) }} host nations — Japan, Bahrain, Italy, Spain, and more. Includes
                            SOFA status, host-nation context, and regional command breakdowns.</div>
                    </div>
                    <span class="base-overseas-promo-cta">Explore {!! $arrow(14) !!}</span>
                </div>
                @if ($countries !== [])
                    <div class="base-country-chips">
                        @foreach ($countries as $country)
                            <span>{{ $country['iso2'] }} · {{ $country['name'] }} ({{ $country['count'] }})</span>
                        @endforeach
                    </div>
                @endif
            </a>
        </section>

        <section class="base-hub-section base-hub-all" aria-label="All bases A to Z">
            <div class="base-list-head">
                <h2>ALL BASES (A–Z)</h2>
                <span class="base-list-count" data-base-count>{{ $basesTotal }} of {{ $basesTotal }}
                    {{ $basesTotal === 1 ? 'base' : 'bases' }}</span>
            </div>
            <div class="base-filter-row" role="group" aria-label="Filter bases by installation type">
                <button type="button" aria-pressed="true" data-base-filter="">All Types</button>
                @foreach ($baseTypes as $type)
                    <button type="button" aria-pressed="false"
                            data-base-filter="{{ $type->value }}">{{ $type->pluralLabel() }}</button>
                @endforeach
            </div>
            <ul class="base-az-list">
                @foreach ($allBases as $base)
                    <li data-base-type="{{ $base->type->value }}">
                        <a href="{{ PagePaths::child('bases', $base->slug) }}">
                            <span class="base-az-name">{{ $base->name }}</span>
                            <span class="base-az-type">{{ mb_strtoupper($base->type->label()) }}</span>
                            <span class="base-az-place">{{ mb_strtoupper($base->city) }},
                                {{ $base->state_abbr }}{!! $arrow(12) !!}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="base-az-empty" hidden>No bases match the selected filters.</div>
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

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection

@push('scripts')
    {{-- Installation-type filter — the legacy hub filters the A–Z list client-side
         (NavyBasesHub.tsx L86-109); multiple types can be active at once. --}}
    <script>
        (function () {
            var row = document.querySelector('.base-filter-row');
            if (!row) return;
            var items = Array.prototype.slice.call(document.querySelectorAll('.base-az-list > li'));
            var counter = document.querySelector('[data-base-count]');
            var empty = document.querySelector('.base-az-empty');
            var list = document.querySelector('.base-az-list');
            var total = items.length;
            var active = [];

            function apply() {
                var shown = 0;
                items.forEach(function (li) {
                    var on = active.length === 0 || active.indexOf(li.dataset.baseType) !== -1;
                    li.hidden = !on;
                    if (on) shown++;
                });
                row.querySelectorAll('button').forEach(function (b) {
                    var v = b.dataset.baseFilter;
                    b.setAttribute('aria-pressed', String(v === '' ? active.length === 0 : active.indexOf(v) !== -1));
                });
                if (counter) counter.textContent = shown + ' of ' + total + ' ' + (shown === 1 ? 'base' : 'bases');
                if (empty) empty.hidden = shown !== 0;
                if (list) list.hidden = shown === 0;
            }

            row.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-base-filter]');
                if (!button) return;
                var value = button.dataset.baseFilter;
                if (value === '') {
                    active = [];
                } else {
                    var at = active.indexOf(value);
                    if (at === -1) active.push(value); else active.splice(at, 1);
                }
                apply();
            });
        })();
    </script>
@endpush
