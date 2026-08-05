@extends('layouts.base')

{{-- /veterans-day/free-meals/ roundup. Head + JSON-LD (Breadcrumb + Article + Person +
     ItemList + FAQPage) locked by SeoHead + VeteransDayFreeMealsSchema. The table, stats,
     and FAQ answers are computed live from the verified() meals (YMYL gate: verified +
     primary source). SSR-first table, progressively enhanced with client-side filters. --}}
@section('content')
    <main class="veterans-day-free-meals" style="max-width:1080px">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/veterans-day/">Veterans Day</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Free Meals 2026</span>
        </nav>

        <p class="back-link"><a href="/veterans-day/">← Veterans Day 2026 hub</a></p>

        <header class="fm-hero">
            <p class="eyebrow">// Veterans Day 2026 · Free Meals</p>
            <h1>{{ $page->h1 ?? 'VETERANS DAY FREE MEALS 2026' }}</h1>
            <p class="intro">
                Where veterans and service members can get a free meal on Wednesday, November 11, 2026.
                Every offer below is checked against the restaurant's <strong>own official source</strong>
                and stamped with the month we verified it — so the list is right when you walk in, not
                wrong by November 11.
            </p>
            <p class="hero-meta">Last updated: <strong>{{ $lastUpdatedLabel }}</strong> · {{ $stats['verified'] }} verified offers</p>
        </header>

        @if ($page->author)
            <aside class="fm-byline" data-testid="free-meals-byline">
                @if ($page->author->avatar_path)
                    <img src="{{ $page->author->avatar_path }}" width="56" height="56"
                         alt="Portrait of {{ $page->author->name }}" class="byline-avatar">
                @endif
                <div>
                    <span class="byline-label">Researched &amp; verified by</span>
                    <p class="byline-name">
                        <a href="/authors/{{ $page->author->slug }}/">{{ $page->author->name }}</a>@if ($page->author->credentials) — {{ $page->author->credentials }}@endif
                    </p>
                    @if ($lastUpdatedLabel)
                        <p class="byline-verified">Offers re-verified: {{ $lastUpdatedLabel }}</p>
                    @endif
                </div>
            </aside>
        @endif

        <section class="fm-headline-stat" data-testid="headline-stat" aria-label="Verification count">
            <p class="eyebrow">The NavyWeek verification count</p>
            <p>
                NavyWeek verified {{ $stats['verified'] }} Veterans Day 2026 free-meal offers against each
                brand's own official source — {{ $stats['nationwide'] }} good at all locations and
                {{ $stats['participating'] }} at participating locations only (navyweek.org).
            </p>
        </section>

        {{-- Named with aria-label rather than an sr-only <h2>, matching the live
             page's heading outline exactly while keeping the accessible name. --}}
        <section class="fm-offers" aria-label="Verified Veterans Day 2026 free-meal offers">

            <div class="vdm-controls" role="search">
                <label>Search <input type="search" data-vdm="q" data-testid="vdm-search" placeholder="Brand or offer"></label>
                <label>Who qualifies
                    <select data-vdm="eligibility" data-testid="vdm-eligibility">
                        <option value="all">Anyone eligible</option>
                        <option value="veteran">Veterans</option>
                        <option value="active">Active duty</option>
                        <option value="reserve">Reserve</option>
                        <option value="guard">National Guard</option>
                        <option value="retired">Retirees</option>
                    </select>
                </label>
                <label>How to get it
                    <select data-vdm="redemption" data-testid="vdm-redemption">
                        <option value="all">Dine-in or takeout</option>
                        <option value="dine-in">Dine-in</option>
                        <option value="takeout">Takeout</option>
                    </select>
                </label>
                <label>Locations
                    <select data-vdm="scope" data-testid="vdm-scope">
                        <option value="all">All locations</option>
                        <option value="nationwide">Nationwide</option>
                        <option value="participating">Participating only</option>
                    </select>
                </label>
                <label>Sort by
                    <select data-vdm="sort" data-testid="vdm-sort">
                        <option value="brand">Brand (A–Z)</option>
                        <option value="date">Offer date</option>
                    </select>
                </label>
            </div>

            <p class="vdm-resultbar">
                <span data-testid="vdm-count">Showing {{ $stats['verified'] }} of {{ $stats['verified'] }} verified offers</span>
                <button type="button" data-vdm="reset" data-testid="vdm-reset" hidden>Clear filters</button>
            </p>

            <table class="vdm-table" data-total="{{ $stats['verified'] }}">
                <thead>
                    <tr>
                        <th scope="col">Brand</th>
                        <th scope="col">Offer</th>
                        <th scope="col">Who qualifies</th>
                        <th scope="col">How to get it</th>
                        <th scope="col">Proof required</th>
                        <th scope="col">When</th>
                        <th scope="col">Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meals as $meal)
                        <tr data-testid="vdm-row-{{ $meal->slug }}"
                            data-brand="{{ \Illuminate\Support\Str::lower($meal->brand.' '.$meal->offer) }}"
                            data-eligibility="{{ $meal->eligibility->map(fn ($e) => $e->value)->implode(' ') }}"
                            data-redemption="{{ $meal->redemption->value }}"
                            data-scope="{{ $meal->nationwide ? 'nationwide' : 'participating' }}"
                            data-date="{{ $meal->offer_date }}">
                            <td data-label="Brand">
                                @if ($meal->discount_slug)
                                    <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('discounts', $meal->discount_slug) }}"
                                       data-testid="vdm-brand-link-{{ $meal->slug }}">{{ $meal->brand }}</a>
                                @else
                                    {{ $meal->brand }}
                                @endif
                                <span class="vdm-badge" title="Verified at the brand's official source">{{ $meal->verifiedBadge() }}</span>
                                @unless ($meal->nationwide)
                                    <span class="vdm-tag">Participating only</span>
                                @endunless
                            </td>
                            <td data-label="Offer">
                                {{ $meal->offer }}
                                @if ($meal->notes)
                                    <span class="vdm-note">{{ $meal->notes }}</span>
                                @endif
                            </td>
                            <td data-label="Who qualifies">{{ $meal->eligibilityLabelList() }}</td>
                            <td data-label="How to get it">{{ $meal->redemption->label() }}</td>
                            <td data-label="Proof required">{{ $meal->proof_required }}</td>
                            <td data-label="When">{{ $meal->whenLabel() }}</td>
                            <td data-label="Source">
                                <a href="{{ $meal->source_url }}" target="_blank" rel="nofollow noopener noreferrer"
                                   data-testid="vdm-source-{{ $meal->slug }}">{{ $meal->source_label }} ↗</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="vdm-empty" data-testid="vdm-empty" hidden>No offers match your filters.</p>
        </section>

        <div class="fm-supporting">
            <section id="who-qualifies">
                <h2>WHO COUNTS AS A “VETERAN” FOR THESE OFFERS</h2>
                <p>
                    There is no single rule — each restaurant sets its own eligibility, and the table above
                    shows exactly who each brand says qualifies. In practice, most Veterans Day free-meal
                    offers extend to <strong>both veterans and active-duty service members</strong>, and many
                    also include members of the Reserve, National Guard, and military retirees.
                </p>
                <p>
                    We list only the eligibility a brand states on its own page. If a brand does not say that
                    spouses, dependents, or family members qualify, we do not claim they do. When an offer is
                    limited (for example, dine-in only, or one free entrée per guest), that caveat is shown in
                    the row's notes.
                </p>
            </section>

            <section id="how-to-redeem">
                <h2>HOW TO REDEEM A FREE VETERANS DAY MEAL</h2>
                <ul>
                    <li><strong>Bring proof of service.</strong> Commonly accepted: a military ID (CAC),
                        Veterans Health Identification Card (VHIC), VA ID card, DD‑214, a state-issued Veteran ID
                        Card, or in some cases a military uniform. Each row lists the proof that brand asks for.</li>
                    <li><strong>Check dine-in vs. takeout.</strong> Many free meals are dine-in only; some chains
                        also allow takeout or carryout. The “How to get it” column tells you which.</li>
                    <li><strong>Confirm your location participates.</strong> Offers marked “participating
                        locations only” are honored at the discretion of individual franchises — call ahead.</li>
                    <li><strong>Go on the right day.</strong> Most offers are valid only on November 11; a few run
                        across a weekend. The “When” column and notes flag any multi-day windows.</li>
                </ul>
                <p class="fm-callout">
                    <strong>One offer per guest, generally.</strong> Free Veterans Day meals are typically
                    limited to one per eligible person and are not combinable with other discounts or coupons
                    unless the brand says otherwise. Always confirm current terms at the brand's official source
                    (linked in each row) before you go.
                </p>
            </section>

            <section id="verify">
                <h2>HOW WE VERIFY EVERY OFFER</h2>
                <p>
                    The highest-volume Veterans Day search is for free meals — and most of the lists you'll find
                    are stale media roundups that are wrong by November 11. NavyWeek does it differently: every
                    offer here is <strong>gated against a primary source</strong> (the brand's own website or
                    official announcement), and we re-verify the entire list against those sources every
                    September, ahead of the November rush.
                </p>
                <p>
                    That's what the <em>Verified</em> badge on each row means: the month and year we last
                    confirmed that offer at the brand's official source, which is linked in the Source column.
                    If we can't tie an offer to a primary source, it doesn't appear here. NavyWeek.org is an
                    independent publisher and is not affiliated with, endorsed by, or sponsored by any brand
                    listed; brand names are used for identification only.
                </p>
            </section>

            <section id="more">
                <h2>MORE VETERANS DAY SAVINGS</h2>
                <p>
                    Looking for year-round military savings beyond Veterans Day? Browse our
                    <a href="/discount/">military &amp; veteran discounts</a>, or read the
                    <a href="/veterans-day/">Veterans Day 2026 guide</a> for the history and how the Navy observes it.
                </p>
            </section>

            <section id="faq" class="fm-faqs">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                @foreach ($faqs as $faq)
                    <details>
                        <summary>{{ $faq->question }}</summary>
                        <div>{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </section>
        </div>
    </main>

    <script>
        (function () {
            var table = document.querySelector('.vdm-table');
            if (!table) return;
            var tbody = table.tBodies[0];
            var rows = Array.prototype.slice.call(tbody.rows);
            var total = parseInt(table.getAttribute('data-total'), 10) || rows.length;
            var controls = {};
            document.querySelectorAll('[data-vdm]').forEach(function (el) { controls[el.getAttribute('data-vdm')] = el; });
            var countEl = document.querySelector('[data-testid="vdm-count"]');
            var emptyEl = document.querySelector('[data-testid="vdm-empty"]');
            var resetEl = controls.reset;

            function val(k) { return controls[k] ? controls[k].value : 'all'; }

            function apply() {
                var q = (val('q') || '').trim().toLowerCase();
                var elig = val('eligibility'), redemption = val('redemption'), scope = val('scope');
                var shown = 0;
                rows.forEach(function (row) {
                    var ok = true;
                    if (q && row.getAttribute('data-brand').indexOf(q) === -1) ok = false;
                    if (ok && elig !== 'all' && row.getAttribute('data-eligibility').split(' ').indexOf(elig) === -1) ok = false;
                    if (ok && redemption !== 'all') {
                        var r = row.getAttribute('data-redemption');
                        // dine-in filter excludes takeout-only; takeout filter excludes dine-in-only; 'both' matches either.
                        if (redemption === 'dine-in' && r === 'takeout') ok = false;
                        if (redemption === 'takeout' && r === 'dine-in') ok = false;
                    }
                    if (ok && scope !== 'all' && row.getAttribute('data-scope') !== scope) ok = false;
                    row.hidden = !ok;
                    if (ok) shown++;
                });
                if (countEl) countEl.textContent = 'Showing ' + shown + ' of ' + total + ' verified offers';
                if (emptyEl) emptyEl.hidden = shown !== 0;
                var active = q || elig !== 'all' || redemption !== 'all' || scope !== 'all' || val('sort') !== 'brand';
                if (resetEl) resetEl.hidden = !active;
            }

            function sortRows() {
                var mode = val('sort');
                var sorted = rows.slice().sort(function (a, b) {
                    if (mode === 'date') {
                        var d = a.getAttribute('data-date').localeCompare(b.getAttribute('data-date'));
                        if (d !== 0) return d;
                    }
                    return a.getAttribute('data-brand').localeCompare(b.getAttribute('data-brand'));
                });
                sorted.forEach(function (r) { tbody.appendChild(r); });
            }

            Object.keys(controls).forEach(function (k) {
                if (k === 'reset') return;
                var ev = k === 'q' ? 'input' : 'change';
                controls[k].addEventListener(ev, function () { if (k === 'sort') sortRows(); apply(); });
            });
            if (resetEl) resetEl.addEventListener('click', function () {
                Object.keys(controls).forEach(function (k) {
                    if (k === 'reset') return;
                    controls[k].value = k === 'q' ? '' : (controls[k].tagName === 'SELECT' ? controls[k].options[0].value : '');
                });
                sortRows(); apply();
            });
        })();
    </script>
@endsection
