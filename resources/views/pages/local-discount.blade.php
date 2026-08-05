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
            <h1>{{ $discount->h1 }}</h1>
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
                <h2>WHO SAVES</h2>
                <ul>
                    @foreach ($discount->tiers as $tier)
                        <li><strong>{{ $tier['audience'] }}:</strong> {{ $tier['amount'] }}@if (! empty($tier['note'])) — {{ $tier['note'] }}@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($discount->redeem_in_store !== [])
            <section class="redeem" aria-label="How to redeem in store">
                <h2>HOW TO REDEEM IN STORE</h2>
                <ol>
                    @foreach ($discount->redeem_in_store as $step)
                        <li><strong>{{ $step['title'] }}</strong> — {{ $step['detail'] }}</li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($discount->stores->isNotEmpty())
            <section class="stores" aria-label="Store locations">
                <h2>LOCATIONS</h2>
                @foreach ($discount->stores->sortBy('sort_order') as $store)
                    <article class="store">
                        <h3>{{ $store->name }}</h3>
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
                <h2>EXCLUSIONS</h2>
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
                <h2>FAQS</h2>
                @foreach ($discount->faqs as $faq)
                    <details>
                        <summary>{{ $faq->question }}</summary>
                        <p>{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </section>
        @endif

        @if ($discount->sources->isNotEmpty())
            <section class="sources" aria-label="Sources">
                <h2>SOURCES</h2>
                <ul>
                    @foreach ($discount->sources as $source)
                        <li><a href="{{ $source->url }}" target="_blank" rel="noopener nofollow">{{ $source->label }}</a>@if ($source->publisher) — {{ $source->publisher }}@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif
        @include('partials.trust.editorial-policy')
    </main>
@endsection
