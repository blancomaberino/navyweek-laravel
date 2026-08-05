@extends('layouts.base')

{{-- Local-business discount detail (/discounts/{state}/{city}/{business}/). Ported from
     the legacy local-discount detail view: header → intro → key facts → savings tiers →
     in-store redemption → store locations (NAP + hours) → exclusions → nearby bases →
     details → FAQs → sources. The head/JSON-LD is byte-locked by SeoHead +
     LocalDiscountSchema; this body is a clean semantic rebuild. --}}
@php
    /** @var \App\Domain\Catalog\Models\LocalDiscount $discount */
@endphp

@section('content')
    <main class="local-discount-detail">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/discounts/{{ $discount->state }}/">{{ $discount->state_name }}</a>
            <span aria-hidden="true">/</span>
            <a href="/discounts/{{ $discount->state }}/{{ $discount->city }}/">{{ $discount->city_name }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $discount->company }}</span>
        </nav>

        <header class="local-discount-header">
            <h1>{{ $discount->company }} Military &amp; Veteran Discount</h1>
            @if ($discount->hero_tagline !== '')
                <p class="tagline">{{ $discount->hero_tagline }}</p>
            @endif
            <p class="headline-discount">{{ $discount->headline_discount }}</p>
            <p class="summary">{{ $discount->discount_summary }}</p>
            <p class="verification">Verification: {{ $discount->verification->label() }}@if ($discount->verification_detail) — {{ $discount->verification_detail }}@endif</p>
            @if ($discount->official_url !== '')
                <a class="cta" href="{{ $discount->official_url }}" target="_blank" rel="noopener nofollow">Visit {{ $discount->company }}</a>
            @endif
        </header>

        @foreach ($discount->intro as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach

        @if ($discount->key_facts !== [])
            <section class="key-facts" aria-label="Key facts">
                <h2>Key Facts</h2>
                <dl>
                    @foreach ($discount->key_facts as $fact)
                        <div>
                            <dt>{{ $fact['label'] }}</dt>
                            <dd>{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($discount->tiers !== [])
            <section class="savings-tiers" aria-label="Savings by audience">
                <h2>What you get</h2>
                <ul>
                    @foreach ($discount->tiers as $tier)
                        <li><strong>{{ $tier['audience'] }}:</strong> {{ $tier['amount'] }}@if (! empty($tier['note'])) — {{ $tier['note'] }}@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($discount->redeem_in_store !== [])
            <section class="redeem" aria-label="How to redeem in store">
                <h2>How to redeem in {{ $discount->city_name }}</h2>
                <ol>
                    @foreach ($discount->redeem_in_store as $step)
                        <li><strong>{{ $step['title'] }}</strong> — {{ $step['detail'] }}</li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if (filled($discount->eligibility))
            <section class="who-qualifies" aria-label="Who qualifies">
                <h2>Who qualifies</h2>
                <ul>
                    @foreach ((array) $discount->eligibility as $who)
                        <li>{{ $who }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($discount->stores->isNotEmpty())
            <section class="stores" aria-label="Store locations">
                <h2>The details</h2>
                @foreach ($discount->stores->sortBy('sort_order') as $store)
                    <article class="store">
                        <h3>Installations near {{ $discount->company }}</h3>
                        <address>
                            {{ $store->street }}, {{ $store->city }}, {{ $store->state_abbr }} {{ $store->zip }}
                            @if ($store->phone)<br><a href="tel:{{ $store->phone }}">{{ $store->phone }}</a>@endif
                        </address>
                        @if ($store->hours->isNotEmpty())
                            <ul class="hours">
                                @foreach ($store->hours as $row)
                                    <li>{{ $row->days }}: {{ $row->opens }}–{{ $row->closes }}@if ($row->note) ({{ $row->note }})@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($store->directions_url)
                            <a href="{{ $store->directions_url }}" target="_blank" rel="noopener nofollow">Directions</a>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        @if ($discount->exclusions !== [])
            <section class="exclusions" aria-label="Exclusions">
                <h2>Good to know</h2>
                <ul>
                    @foreach ($discount->exclusions as $exclusion)
                        <li>{{ $exclusion }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @foreach ($discount->details as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach

        @if ($discount->faqs->isNotEmpty())
            <section class="faqs" aria-label="Frequently asked questions">
                <h2>{{ $discount->city_name }} FAQ</h2>
                @foreach ($discount->faqs as $faq)
                    <details>
                        <summary>{{ $faq->question }}</summary>
                        <p>{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </section>
        @endif

    </main>
@endsection
