@extends('layouts.base')

{{-- Local-business discount detail (/discounts/{state}/{city}/{business}/). Ported 1:1
     from the legacy src/page-views/LocalDiscountDetail.tsx: the `ld-*` two-column layout
     (article column + sticky NAP sidebar). Styles live in
     resources/css/families/local-discount.css. The head/JSON-LD is byte-locked by
     SeoHead + LocalDiscountSchema. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    /** @var \App\Domain\Catalog\Models\LocalDiscount $discount */
    $primary = $discount->stores->first();

    // Legacy `firstLocationMapHref`: the primary store's own directions URL, else a
    // Google Maps search built from the company + address.
    $mapHref = $primary?->directions_url ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(
        trim($discount->company.' '.($primary->street ?? '').' '.($primary->city ?? $discount->city_name).' '.($primary->state_abbr ?? $discount->state_abbr))
    );

    // Legacy renders only these three audience flags as badges.
    $audienceBadges = array_values(array_filter([
        $discount->active_duty ? 'Active duty' : null,
        $discount->veterans ? 'Veterans' : null,
        $discount->military_family ? 'Military families' : null,
    ]));

    $firstSource = $discount->sources->first();
@endphp

@section('content')
    <main class="ld-page ld-wrap">
        <nav class="ld-crumb" aria-label="Breadcrumb">
            <a href="/">Home</a><span class="sep">›</span>
            <a href="{{ PagePaths::root('local_discounts') }}">Discounts</a><span class="sep">›</span>
            <a href="{{ PagePaths::child('local_discounts', $discount->state) }}">{{ $discount->state_name }}</a><span class="sep">›</span>
            <a href="{{ PagePaths::child('local_discounts', $discount->state, $discount->city) }}">{{ $discount->city_name }}</a><span class="sep">›</span>
            <span class="here">{{ $discount->company }}</span>
        </nav>

        <header class="ld-hero">
            <span class="ld-pin">📍 {{ $discount->city_name }}, {{ $discount->state_abbr }} · {{ $discount->category }}</span>
            <h1 class="ld-h1">{{ $discount->company }} Military &amp; Veteran <em>Discount</em></h1>
            <p class="ld-tag">{{ $discount->hero_tagline }}</p>
            <div class="ld-badges">
                <span class="ld-badge ok">✓ {{ $discount->headline_discount }}</span>
                @foreach ($audienceBadges as $badge)
                    <span class="ld-badge">{{ $badge }}</span>
                @endforeach
                <span class="ld-badge">{{ $discount->verification->label() }}</span>
            </div>
        </header>

        <div class="ld-grid">
            <div>
                <section class="ld-block ld-prose">
                    <p class="ld-eyebrow">The offer, locally</p>
                    @foreach ($discount->intro as $i => $paragraph)
                        <p @class(['lead' => $i === 0])>{{ $paragraph }}</p>
                    @endforeach
                </section>

                <section class="ld-block">
                    @include('partials.trust.key-facts', ['keyFacts' => [
                        'facts' => $discount->key_facts,
                        'ariaLabel' => $discount->company.' military discount key facts',
                        'source' => $firstSource === null ? null : ['label' => $firstSource->label, 'url' => $firstSource->url],
                        'lastVerified' => $discount->last_verified,
                    ]])
                </section>

                @if ($discount->tiers !== [])
                    <section class="ld-block">
                        <h2 class="ld-h2">What you get</h2>
                        <div class="ld-tiers">
                            @foreach ($discount->tiers as $tier)
                                <div class="ld-tier">
                                    <div>
                                        <div class="aud">{{ $tier['audience'] }}</div>
                                        @if (! empty($tier['note']))
                                            <div class="note">{{ $tier['note'] }}</div>
                                        @endif
                                    </div>
                                    <div class="amt">{{ $tier['amount'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($discount->redeem_in_store !== [])
                    <section class="ld-block">
                        <h2 class="ld-h2">How to redeem in {{ $discount->city_name }}</h2>
                        <ol class="ld-steps">
                            @foreach ($discount->redeem_in_store as $step)
                                <li><b>{{ $step['title'] }}</b><span>{{ $step['detail'] }}</span></li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <section class="ld-block ld-prose">
                    <h2 class="ld-h2">The details</h2>
                    @foreach ($discount->details as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>

                @if ($discount->eligibility !== [])
                    <section class="ld-block">
                        <h2 class="ld-h2">Who qualifies</h2>
                        <ul class="ld-list">
                            @foreach ($discount->eligibility as $who)
                                <li>{{ $who }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($discount->exclusions !== [])
                    <section class="ld-block">
                        <h2 class="ld-h2">Good to know</h2>
                        <ul class="ld-list">
                            @foreach ($discount->exclusions as $exclusion)
                                <li>{{ $exclusion }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($discount->nearby_bases !== [])
                    <section class="ld-block">
                        <p class="ld-eyebrow">Serving the {{ $discount->city_name }} military community</p>
                        <div class="ld-loc">
                            <h3>Installations near {{ $discount->company }}</h3>
                            <ul class="ld-bases">
                                @foreach ($discount->nearby_bases as $base)
                                    <li>
                                        <span class="mi">~{{ $base['distanceMi'] ?? '' }} mi</span>
                                        <span><b>{{ $base['name'] ?? '' }}</b>@if (! empty($base['note'])) — {{ $base['note'] }}@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endif

                @if ($discount->faqs->isNotEmpty())
                    <section class="ld-block">
                        <h2 class="ld-h2">{{ $discount->city_name }} FAQ</h2>
                        @foreach ($discount->faqs as $i => $faq)
                            <details class="ld-faq" @if ($i === 0) open @endif>
                                <summary>{{ $faq->question }}</summary>
                                <p>{{ $faq->answer }}</p>
                            </details>
                        @endforeach
                    </section>
                @endif

                @if ($discount->sources->isNotEmpty())
                    <section class="ld-block">
                        <p class="ld-eyebrow">Sources</p>
                        <div class="ld-src">
                            @foreach ($discount->sources as $source)
                                <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($source->url) }}" target="_blank" rel="noopener noreferrer nofollow">{{ $source->label }}@if ($source->publisher)<span class="pub"> · {{ $source->publisher }}</span>@endif</a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="ld-side">
                <div class="ld-nap">
                    <div class="ld-nap-head">
                        <div class="co">{{ $discount->company }}</div>
                        <div class="loc">{{ $primary?->city }}, {{ $primary?->state_abbr }}</div>
                    </div>
                    @if ($primary?->map_embed_url)
                        <iframe class="ld-map"
                                src="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize((string) $primary->map_embed_url) }}"
                                title="Map of {{ $discount->company }}, {{ $primary->street }}, {{ $primary->city }}, {{ $primary->state_abbr }}"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen></iframe>
                    @endif
                    @if ($primary !== null)
                        <div class="ld-nap-body">
                            <div class="ld-nap-row">
                                <span aria-hidden="true">📌</span>
                                <div>
                                    <div class="lab">Address</div>
                                    {{ $primary->street }}<br />{{ $primary->city }}, {{ $primary->state_abbr }} {{ $primary->zip }}
                                </div>
                            </div>
                            @if ($primary->phone)
                                <div class="ld-nap-row">
                                    <span aria-hidden="true">📞</span>
                                    <div>
                                        <div class="lab">Phone</div>
                                        <a href="tel:{{ preg_replace('/[^0-9]/', '', $primary->phone) }}">{{ $primary->phone }}</a>
                                    </div>
                                </div>
                            @endif
                            @if ($primary->hours->isNotEmpty())
                                <div class="ld-nap-row">
                                    <span aria-hidden="true">🕑</span>
                                    <div>
                                        <div class="lab">Hours</div>
                                        <div class="ld-nap-hours">
                                            @foreach ($primary->hours as $row)
                                                <span>{{ $row->days }} &nbsp; {{ $row->opens }}–{{ $row->closes }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="ld-verif">
                        Verify: <b>{{ $discount->verification_detail ?: $discount->verification->label() }}</b>
                    </div>
                    <a class="ld-cta" href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize((string) $mapHref) }}" target="_blank" rel="noopener noreferrer">Get directions →</a>
                </div>
            </aside>
        </div>

        <p class="ld-disclaim">
            NavyWeek.org is an independent editorial publisher and is not affiliated with, endorsed by,
            or sponsored by {{ $discount->company }}. {{ $discount->company }} controls the program and can change or end it at any
            time; confirm current terms before you visit. Last verified {{ $discount->last_verified }}.
        </p>
    </main>
@endsection
